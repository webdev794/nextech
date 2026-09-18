<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Geo;
use App\Support\RiderAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdminRiderController extends Controller
{
    public function index(): JsonResponse
    {
        $riders = User::query()
            ->where('is_rider', true)
            ->with('stores:id,name,city')
            ->withCount(['deliveries as active_deliveries' => fn ($query) => $query
                ->whereIn('status', ['ready_for_delivery', 'out_for_delivery'])])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $riders->map($this->row(...))->values()]);
    }

    /**
     * A single rider with every customer review — including the written comments,
     * which are admin-only and never surface on the rider's own dashboard.
     */
    public function show(User $user): JsonResponse
    {
        abort_unless($user->is_rider, 404);

        $user->load('stores:id,name,city')
            ->loadCount([
                'deliveries as active_deliveries' => fn ($query) => $query
                    ->whereIn('status', ['ready_for_delivery', 'out_for_delivery']),
                'deliveries as completed_deliveries' => fn ($query) => $query->where('status', 'completed'),
            ]);

        $reviews = $user->riderReviews()
            ->with('order:id')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'order_id' => $r->order_id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'source' => $r->source,
                'at' => $r->created_at,
            ]);

        return response()->json(['data' => [
            'rider' => $this->row($user) + ['completed_deliveries' => (int) ($user->completed_deliveries ?? 0)],
            'reviews' => $reviews,
            'attendance' => RiderAttendance::summary($user, 14),
        ]]);
    }

    /**
     * Roster timesheet: one block per rider, each with a day-by-day breakdown of
     * hours worked over the requested window (default: the last 7 days).
     */
    public function attendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now();
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : $to->copy()->subDays(6)->startOfDay();
        $days = max(1, (int) $from->diffInDays($to) + 1);

        $riders = User::query()->where('is_rider', true)->orderBy('name')->get();

        return response()->json([
            'data' => $riders->map(function (User $rider) use ($days) {
                $rows = RiderAttendance::summary($rider, $days);

                return [
                    'rider' => ['id' => $rider->id, 'name' => $rider->name],
                    'total_worked_minutes' => (int) array_sum(array_column($rows, 'worked_minutes')),
                    'days' => $rows,
                ];
            })->values(),
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    /**
     * A single rider's full attendance for a date range (default: this calendar
     * month) — every day classified full / short / off, with roll-up totals.
     */
    public function riderAttendance(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_rider, 404);

        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : (clone $from)->endOfMonth();

        return response()->json(['data' => RiderAttendance::report($user, $from, $to)]);
    }

    /**
     * Promote an existing account to a delivery rider, by email.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'store_ids' => ['required', 'array', 'min:1'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No account with that email.']]);
        }

        if ($user->is_admin) {
            throw ValidationException::withMessages(['email' => ['That account is an administrator.']]);
        }

        $user->forceFill([
            'is_rider' => true,
            'rider_is_active' => true,
            'rider_since' => $user->rider_since ?? now(),
        ])->save();

        // A rider must have a store to return cash to — never hired store-less.
        $user->stores()->sync($data['store_ids']);

        return response()->json(['data' => $this->row($user->fresh()->load('stores:id,name,city'))], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_rider, 404);

        $data = $request->validate([
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'rider_is_active' => ['sometimes', 'boolean'],
            // Admin force-offline / bring-online. Does NOT touch the shift ledger —
            // it's an override on top of whatever the rider has clocked.
            'rider_available' => ['sometimes', 'boolean'],
            'rider_unavailable_reason' => ['sometimes', 'nullable', 'string', 'max:200'],
            // Expected worked minutes for a "full" day in the attendance report.
            'rider_daily_target_minutes' => ['sometimes', 'nullable', 'integer', 'between:30,1440'],
            'rider_base_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rider_base_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'rider_base_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'store_ids' => ['sometimes', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ]);

        $attributes = collect($data)->only([
            'phone', 'rider_is_active', 'rider_available', 'rider_unavailable_reason',
            'rider_daily_target_minutes', 'rider_base_address', 'rider_base_lat', 'rider_base_lng',
        ])->all();

        // Bringing a rider back online clears any stale "why" note.
        if (($attributes['rider_available'] ?? null) === true
            && ! array_key_exists('rider_unavailable_reason', $attributes)) {
            $attributes['rider_unavailable_reason'] = null;
        }

        // Geocode the base address when coordinates weren't supplied with it.
        if (array_key_exists('rider_base_address', $attributes)
            && ! empty($attributes['rider_base_address'])
            && empty($data['rider_base_lat'])
            && empty($data['rider_base_lng'])) {
            [$lat, $lng] = Geo::geocode($attributes['rider_base_address']);
            $attributes['rider_base_lat'] = $lat;
            $attributes['rider_base_lng'] = $lng;
        }

        if ($attributes) {
            $user->forceFill($attributes)->save();
        }

        if (array_key_exists('store_ids', $data)) {
            $user->stores()->sync($data['store_ids']);
        }

        return response()->json(['data' => $this->row(
            $user->fresh()->load('stores:id,name,city')->loadCount(['deliveries as active_deliveries' => fn ($query) => $query
                ->whereIn('status', ['ready_for_delivery', 'out_for_delivery'])])
        )]);
    }

    /**
     * Drop the rider role. The account stays; it just can't deliver any more.
     */
    public function destroy(User $user): JsonResponse
    {
        abort_unless($user->is_rider, 404);

        $user->stores()->detach();
        $user->forceFill(['is_rider' => false, 'rider_is_active' => false])->save();

        return response()->json(status: 204);
    }

    /**
     * Confirm the rider has physically handed back all the cash they're
     * currently holding from cash-on-delivery orders. Clears their holding
     * balance immediately (on the admin list and the rider's own dashboard).
     */
    public function settleCash(User $user): JsonResponse
    {
        abort_unless($user->is_rider, 404);

        $settled = $user->settleCodCash();

        if ($settled <= 0) {
            return response()->json(['message' => 'This rider has no cash on hand to settle.'], 422);
        }

        return response()->json(['data' => ['settled_cents' => $settled, 'holding_cents' => 0]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $rider): array
    {
        $location = $rider->riderLocation();

        $shift = RiderAttendance::state($rider);

        return [
            'id' => $rider->id,
            'name' => $rider->name,
            'email' => $rider->email,
            'phone' => $rider->phone,
            'rider_is_active' => (bool) $rider->rider_is_active,
            'attendance' => $shift,
            'online' => $rider->rider_last_seen_at !== null
                && $rider->rider_last_seen_at->gt(now()->subMinutes(2)),
            'last_seen_at' => $rider->rider_last_seen_at,
            'rider_base_address' => $rider->rider_base_address,
            'rider_base_lat' => $rider->rider_base_lat,
            'rider_base_lng' => $rider->rider_base_lng,
            'located' => $location ? [
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'source' => $location['source'],
                'last_ping_at' => $rider->rider_last_located_at,
            ] : null,
            'active_deliveries' => (int) ($rider->active_deliveries ?? 0),
            'rating_avg' => $rider->rider_rating_avg !== null ? (float) $rider->rider_rating_avg : null,
            'rating_count' => (int) $rider->rider_rating_count,
            'daily_target_minutes' => $rider->rider_daily_target_minutes ?: RiderAttendance::DEFAULT_TARGET_MINUTES,
            'offers_count' => (int) $rider->rider_offers_count,
            'declined_count' => (int) $rider->rider_declined_count,
            'missed_count' => (int) $rider->rider_missed_count,
            'acceptance_rate' => $rider->riderAcceptanceRate(),
            'cash_holding_cents' => $rider->codHoldingCents(),
            'cash_holding_since' => $rider->codHoldingSince(),
            'stores' => $rider->relationLoaded('stores')
                ? $rider->stores->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'city' => $s->city])->values()
                : [],
        ];
    }
}
