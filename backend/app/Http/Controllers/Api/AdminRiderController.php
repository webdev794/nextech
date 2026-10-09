<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderLedgerEntry;
use App\Models\RiderPayoutRequest;
use App\Models\User;
use App\Support\Geo;
use App\Support\Market;
use App\Support\Money;
use App\Support\RiderAttendance;
use App\Support\RiderLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdminRiderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $market = Market::adminFilter($request);
        $riders = User::query()
            ->where('is_rider', true)
            // Riders of this country's stores (and ones not linked to a store yet).
            ->when($market, fn ($query) => $query->where(fn ($q) => $q->whereHas('stores', fn ($s) => $s->where('country', $market))->orWhereDoesntHave('stores')))
            ->with('stores:id,name,city,country,shop_id')
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

        $user->load('stores:id,name,city,country,shop_id')
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
            'pay' => $this->pay($user),
            'reviews' => $reviews,
            'attendance' => RiderAttendance::summary($user, 14),
            // Final settlement: earnings minus all cash held; paid 7 days after their last delivery.
            'settlement' => \App\Support\RiderMoney::settlement($user),
            // Everything from the application they were hired from (or their latest one).
            'profile' => ($app = \App\Models\RiderApplication::find($user->rider_application_id) ?? \App\Models\RiderApplication::where('user_id', $user->id)->latest()->first()) ? [
                'email' => $app->email, 'phone' => $app->phone, 'date_of_birth' => $app->date_of_birth, 'age' => $app->date_of_birth?->age,
                'home_address' => $app->home_address, 'vehicle_type' => $app->vehicle_type, 'own_vehicle' => $app->own_vehicle, 'license_number' => $app->license_number,
                'experience_months' => $app->experience_months, 'education' => $app->education, 'work_history' => $app->work_history,
                'health' => $app->health_issue ? ($app->health_details ?: 'Yes') : 'None declared',
                'preferred_stores' => \App\Models\Store::query()->whereIn('id', \App\Support\RiderHiring::preferred($app))->get(['id', 'name', 'shop_id'])
                    ->sortBy(fn ($s) => array_search($s->id, \App\Support\RiderHiring::preferred($app), true))->values(),
                'documents' => array_filter(['Photo' => $app->photo_path, 'ID proof' => $app->id_document_path, 'Driving licence' => $app->license_document_path, 'Vehicle RC' => $app->rc_document_path, 'Education' => $app->education_document_path]),
                'applied_at' => $app->created_at,
                // Their signature on the store's terms, and the terms as signed.
                'signed' => $app->signed_name ? ['name' => $app->signed_name, 'place' => $app->signed_place, 'at' => $app->signed_at, 'terms' => $app->signed_terms ?? []] : null,
            ] : null,
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
        abort_if(\App\Models\Store::query()->whereIn('id', $data['store_ids'])->distinct()->count('country') > 1, 422, 'A rider works in one country only — pick stores in one country.');
        $user->stores()->sync($data['store_ids']);

        return response()->json(['data' => $this->row($user->fresh()->load('stores:id,name,city,country,shop_id'))], 201);
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
            $removed = \App\Models\Store::query()->whereNotNull('shop_id')->whereIn('id', $user->stores()->pluck('stores.id'))->whereNotIn('id', $data['store_ids'])->with('shop.seller')->get();
            abort_if(\App\Models\Store::query()->whereIn('id', $data['store_ids'])->distinct()->count('country') > 1, 422, 'A rider works in one country only — pick stores in one country.');
            abort_if($removed->isNotEmpty() && blank($request->input('reason')), 422, 'Say why you’re removing this rider from '.$removed->pluck('shop.name')->join(', ').' — the seller gets it as a message.');
            $user->stores()->sync($data['store_ids']);
            foreach ($removed as $store) {
                if ($seller = $store->shop?->seller) {
                    \App\Support\SellerNotify::send($seller, $request->user(), 'A rider was removed from your store', "{$user->name} no longer delivers for your store: ".trim((string) $request->input('reason')));
                }
            }
        }

        return response()->json(['data' => $this->row(
            $user->fresh()->load('stores:id,name,city,country,shop_id')->loadCount(['deliveries as active_deliveries' => fn ($query) => $query
                ->whereIn('status', ['ready_for_delivery', 'out_for_delivery'])])
        )]);
    }

    /** The riders' money table for a month (admin: every rider in the country). */
    public function money(Request $request): JsonResponse
    {
        $month = \Illuminate\Support\Carbon::parse(($request->validate(['month' => ['sometimes', 'date_format:Y-m']])['month'] ?? now()->format('Y-m')).'-01');

        return response()->json(['data' => \App\Support\RiderMoney::table($month, null, Market::adminFilter($request))]);
    }

    /** Riders suggested to NexTech's own stores (delivered before, live nearby). */
    public function invites(): JsonResponse
    {
        return response()->json(['data' => \App\Models\RiderInvite::query()->whereIn('status', ['suggested', 'invited'])
            ->whereHas('store', fn ($q) => $q->whereNull('shop_id'))->with(['user:id,name,phone', 'store:id,name'])->get()
            ->map(fn ($i) => ['id' => $i->id, 'status' => $i->status, 'name' => $i->user?->name, 'phone' => $i->user?->phone, 'store' => $i->store?->name])]);
    }

    public function decideInvite(Request $request, \App\Models\RiderInvite $invite): JsonResponse
    {
        abort_unless($invite->store && ! $invite->store->shop_id, 404);
        \App\Support\RiderHiring::storeDecides($invite, (bool) $request->validate(['invite' => ['required', 'boolean']])['invite']);

        return $this->invites();
    }

    /** Approve or decline a rider's move to a new home area. */
    public function decideMove(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:300']]);
        \App\Support\RiderHiring::decideMove($user, $data['approve'], $data['reason'] ?? null);

        return response()->json(['message' => $data['approve'] ? 'Move approved — the rider is told.' : 'Declined — the rider is told.']);
    }

    /** Dismiss a performance warning. */
    public function dismissWarning(string $id): JsonResponse
    {
        \App\Support\PerformanceWatch::dismiss($id);

        return response()->json(['data' => \App\Support\PerformanceWatch::warnings()]);
    }

    /** Set a rider's pay plan: per delivery (null) or monthly pay with target, bonus and cap. */
    public function payPlan(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['present', 'nullable', 'array'],
            'plan.monthly_cents' => ['required_with:plan', 'integer', 'min:1'],
            'plan.target' => ['required_with:plan', 'integer', 'min:1', 'max:10000'],
            'plan.bonus_per_extra_cents' => ['required_with:plan', 'integer', 'min:0'],
            'plan.bonus_cap_cents' => ['required_with:plan', 'integer', 'min:0'],
        ]);
        $user->forceFill(['rider_pay_plan' => $data['plan'] ? ['type' => 'monthly'] + array_map('intval', $data['plan']) : null])->save();

        return response()->json(['data' => $user->fresh()->rider_pay_plan]);
    }

    /** Top-up pools: last months' pools and riders who fell short. */
    public function pools(): JsonResponse
    {
        return response()->json(['data' => \App\Support\RiderPayPlan::pools()]);
    }

    public function topUp(Request $request): JsonResponse
    {
        $data = $request->validate(['pool_id' => ['required', 'string'], 'rider_id' => ['required', 'integer'], 'amount_cents' => ['required', 'integer', 'min:1']]);
        \App\Support\RiderPayPlan::topUp($data['pool_id'], $data['rider_id'], $data['amount_cents'], $request->user());

        return $this->pools();
    }

    /** Remove a bonus suggestion (bonus given, or not needed). */
    public function dismissBonus(string $id): JsonResponse
    {
        \App\Support\RiderBonus::dismiss($id);

        return response()->json(['data' => \App\Support\RiderBonus::suggestions()]);
    }

    /** Take cash a rider holds for sellers from their earnings and give it to the sellers now. */
    public function offsetSellerCash(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_rider, 404);
        $moved = \App\Support\RiderMoney::offsetSellerCash($user, $request->user());

        return response()->json(['data' => ['moved_cents' => $moved, 'settlement' => \App\Support\RiderMoney::settlement($user)]]);
    }

    /** A rider's notice is settled: final pay done, they stop working (account stays). */
    public function noticeProcessed(User $user): JsonResponse
    {
        abort_unless($user->rider_notice_at && ! $user->rider_notice_processed_at, 422, 'This rider has no notice to process.');
        $user->forceFill(['rider_notice_processed_at' => now(), 'rider_is_active' => false, 'rider_available' => false])->save();

        return response()->json(['data' => ['processed' => true]]);
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
     * Record a payout (sent outside the app) against a rider's earnings. Never
     * more than they're owed (earnings minus COD cash still held) or the
     * per-payout maximum. Settles their open payout request, if any.
     */
    public function payout(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_rider || RiderLedger::balanceCents($user) > 0, 404);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $user, $data): void {
            User::whereKey($user->id)->lockForUpdate()->first();

            $owed = RiderLedger::owedCents($user);
            if ($data['amount_cents'] > $owed) {
                $held = $user->codHoldingCents();
                $cur = Market::currency(RiderLedger::marketFor($user));
                abort(422, 'This rider is owed '.Money::format($owed, $cur)
                    .($held > 0 ? ' (earnings minus '.Money::format($held, $cur).' cash they still hold).' : '.'));
            }

            $max = RiderLedger::maxPayoutCents(RiderLedger::marketFor($user));
            abort_if($max > 0 && $data['amount_cents'] > $max, 422, 'A single rider payout can be at most '.Money::format($max, Market::currency(RiderLedger::marketFor($user))).'.');

            $entry = RiderLedger::recordPayout($user, $data['amount_cents'], $data['note'] ?? null, $request->user());

            RiderPayoutRequest::where('user_id', $user->id)->where('status', 'pending')->update([
                'status' => 'paid',
                'ledger_entry_id' => $entry->id,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);
        });

        return response()->json(['data' => $this->pay($user->fresh())]);
    }

    public function rejectPayoutRequest(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $payoutRequest = RiderPayoutRequest::where('user_id', $user->id)->where('status', 'pending')->first();
        abort_unless($payoutRequest, 404, 'No open payout request.');

        $payoutRequest->update([
            'status' => 'rejected',
            'admin_note' => $data['note'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return response()->json(['data' => $this->pay($user->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pay(User $rider): array
    {
        return [
            'balance_cents' => RiderLedger::balanceCents($rider),
            'cash_holding_cents' => $rider->codHoldingCents(),
            'owed_cents' => RiderLedger::owedCents($rider),
            'min_payout_cents' => RiderLedger::minPayoutCents(RiderLedger::marketFor($rider)),
            'max_payout_cents' => RiderLedger::maxPayoutCents(RiderLedger::marketFor($rider)),
            'currency' => Market::currency(RiderLedger::marketFor($rider)),
            'payout_method' => $rider->rider_payout_method,
            'payout_details' => $rider->rider_payout_details,
            'pending_payout_request' => RiderPayoutRequest::where('user_id', $rider->id)->where('status', 'pending')->first(),
            'paid_total_cents' => (int) -RiderLedgerEntry::where('user_id', $rider->id)->where('type', 'payout_debit')->sum('amount_cents'),
            'entries' => RiderLedgerEntry::where('user_id', $rider->id)->latest('id')->limit(30)
                ->get(['id', 'order_id', 'type', 'amount_cents', 'distance_miles', 'note', 'created_at']),
        ];
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
            'earnings_balance_cents' => RiderLedger::balanceCents($rider),
            'payout_requested_cents' => RiderPayoutRequest::where('user_id', $rider->id)->where('status', 'pending')->value('amount_cents'),
            // Experience: from their application, total time as a rider, and time with each store.
            'experience_months' => \App\Models\RiderApplication::where('user_id', $rider->id)->value('experience_months'),
            'rider_since' => $rider->rider_since,
            'photo_path' => $rider->rider_photo_path,
            'move_request' => $rider->rider_move_request,
            'pay_plan' => \App\Support\RiderPayPlan::of($rider),
            'deliveries_this_month' => \App\Support\RiderPayPlan::of($rider) ? \App\Support\RiderPayPlan::deliveries($rider, now()) : null,
            // How this rider rates stores and buyers (private; admin only).
            'feedback_given' => ($fg = \App\Models\RiderFeedback::query()->where('rider_id', $rider->id)->selectRaw('count(*) as c, avg(store_rating) as s, avg(buyer_rating) as b')->first()) && $fg->c
                ? ['count' => (int) $fg->c, 'store_avg' => $fg->s !== null ? round((float) $fg->s, 1) : null, 'buyer_avg' => $fg->b !== null ? round((float) $fg->b, 1) : null] : null,
            'notice' => $rider->rider_notice_at && ! $rider->rider_notice_processed_at ? ['given_at' => $rider->rider_notice_at, 'leaving_on' => $rider->rider_leaving_on?->toDateString()] : null,
            'stores' => $rider->relationLoaded('stores')
                ? $rider->stores->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'city' => $s->city, 'country' => $s->country, 'shop_id' => $s->shop_id, 'linked_at' => $s->pivot?->linked_at])->values()
                : [],
        ];
    }
}
