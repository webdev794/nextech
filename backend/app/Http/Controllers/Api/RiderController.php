<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ChatPage;
use App\Models\Order;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\DeliveryHandoverCode;
use App\Notifications\RiderMessage;
use App\Support\DeliveryOfferSweeper;
use App\Support\RiderAssignment;
use App\Support\RiderAttendance;
use App\Support\SellerLedger;
use App\Support\RiderLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderController extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        // Any rider hitting their queue also drives the offer-timeout sweep.
        try {
            DeliveryOfferSweeper::sweep();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->board($request->user())
            + ['shift' => RiderAttendance::state($request->user()),
                // Paused in every store until cash is handed over (a seller's, or over the store's own limit).
                'cash_block' => \App\Support\SellerRiderCash::blockedReason($request->user()),
                // The hours each of my stores needs me on duty.
                'work_hours' => $request->user()->stores()->with('shop:id,name')->get()
                    ->filter(fn ($s) => \App\Support\RiderWorkHours::of($s))
                    ->map(fn ($s) => ['store' => $s->shop?->name ?? $s->name, 'hours' => \App\Support\RiderWorkHours::label(\App\Support\RiderWorkHours::of($s)), 'end' => \App\Support\RiderWorkHours::of($s)['end'], 'now' => \App\Support\RiderWorkHours::isWorkTime($s)])->values(),
                // No store delivering now: details on file, linked again automatically, or delete them.
                'no_active_store' => \App\Support\RiderHiring::activeStores($request->user())->isEmpty(),
                'move_request' => $request->user()->rider_move_request,
                // Deliveries of the last 3 days to rate (store + buyer; private, only admin sees it).
                'to_rate' => $this->toRate($request->user()),
                // Stores inviting me to deliver for them again.
                'invites' => \App\Models\RiderInvite::query()->where('user_id', $request->user()->id)->where('status', 'invited')->with('store.shop:id,name')->get()
                    ->map(fn ($i) => ['id' => $i->id, 'store' => $i->store?->shop?->name ?? $i->store?->name, 'city' => $i->store?->city]),
                // Days off: allowed a month, used, and coming up.
                'leave' => [
                    'allowed' => \App\Support\RiderWorkHours::allowedFor($request->user()),
                    'used' => \App\Support\RiderWorkHours::usedThisMonth($request->user()),
                    'list' => \App\Models\RiderLeave::query()->where('user_id', $request->user()->id)->whereDate('date', '>=', now()->startOfMonth()->toDateString())->orderBy('date')->get(['id', 'date', 'kind', 'told_ahead', 'reason']),
                ]]]);
    }

    /**
     * Attendance clock: check in, take / end a lunch break, check out. The live
     * `rider_available` flag (which gates auto-assignment) is derived here.
     */
    public function shift(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['clock_in', 'clock_out', 'break_start', 'break_end'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $rider = $request->user();

        $error = DB::transaction(function () use ($data, $rider) {
            $shift = $rider->currentShift();
            $openBreak = $shift?->breaks->firstWhere('ended_at', null);

            switch ($data['action']) {
                case 'clock_in':
                    if ($shift) {
                        return 'You are already clocked in.';
                    }
                    // No work without a way to be paid (one the store allows).
                    if ($why = \App\Support\RiderLedger::payoutBlocker($rider)) {
                        return $why;
                    }
                    $rider->riderShifts()->create(['clock_in_at' => now(), 'source' => 'rider']);
                    $rider->forceFill(['rider_available' => true, 'rider_unavailable_reason' => null])->save();
                    break;

                case 'clock_out':
                    if (! $shift) {
                        return 'You are not clocked in.';
                    }
                    $shift->breaks()->whereNull('ended_at')->update(['ended_at' => now()]);
                    $shift->forceFill(['clock_out_at' => now()])->save();
                    $rider->forceFill(['rider_available' => false, 'rider_unavailable_reason' => null])->save();
                    break;

                case 'break_start':
                    if (! $shift) {
                        return 'Clock in before taking a break.';
                    }
                    if ($openBreak) {
                        return 'You are already on a break.';
                    }
                    $reason = ($data['reason'] ?? null) ?: 'Break';
                    $shift->breaks()->create(['started_at' => now(), 'reason' => $reason]);
                    $rider->forceFill(['rider_available' => false, 'rider_unavailable_reason' => $reason])->save();
                    break;

                case 'break_end':
                    if (! $openBreak) {
                        return 'You are not on a break.';
                    }
                    $openBreak->forceFill(['ended_at' => now()])->save();
                    $rider->forceFill(['rider_available' => true, 'rider_unavailable_reason' => null])->save();
                    break;
            }

            return null;
        });

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        // Coming online (clock in / back from a break): open orders at my stores with nobody offered get offered
        // now — ending at the same deadline as for everyone else.
        if (in_array($request->input('action'), ['clock_in', 'break_end'], true)) {
            Order::query()->where('status', 'ready_for_delivery')->whereNull('delivery_partner_id')->where('delivery_method', 'own_rider')
                ->whereIn('store_id', $rider->stores()->pluck('stores.id'))->get()
                ->each(fn (Order $order) => \App\Support\RiderAssignment::assign($order));
        }

        if ($request->input('action') === 'clock_out') {
            \App\Support\RiderWorkHours::clockedOut($rider); // during a store's hours: the store is told
        }

        // Going on a break or clocking out: offers not accepted yet go to the next available rider
        // (or, with nobody free, the store is told at once).
        if (in_array($request->input('action'), ['clock_out', 'break_start'], true)) {
            Order::query()->where('delivery_partner_id', $rider->id)->where('status', 'ready_for_delivery')
                ->whereNull('rider_accepted_at')->whereNotNull('rider_offer_expires_at')->get()
                ->each(function (Order $order) use ($rider) {
                    $order->forceFill(['delivery_partner_id' => null, 'courier_name' => null, 'rider_offer_expires_at' => null])->save();
                    \App\Support\RiderAssignment::assign($order->fresh(), [$rider->id]);
                });
        }

        return response()->json(['data' => RiderAttendance::state($rider->fresh())]);
    }

    /**
     * The rider's delivery board: orders assigned to them (including pending
     * offers), the shared first-come pool, and any cancelled-at-the-door
     * orders whose items are still owed back to the store.
     *
     * @return array{assigned: Collection, pool: Collection, pending_returns: Collection}
     */
    private function board(User $rider): array
    {
        $me = $rider->id;

        // Include orders still being packed so the rider sees what's coming;
        // the delivery actions only unlock once it's ready_for_delivery.
        $assigned = Order::query()
            ->where('delivery_partner_id', $me)
            ->whereIn('status', ['confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery'])
            ->with(['items', 'user:id,name,phone'])
            ->latest()
            ->get();

        // Once a rider is linked to stores, their pool is scoped to those stores
        // (plus any order with no store recorded — older data visible to all). A
        // rider with no store links yet sees the whole pool, as before.
        $storeIds = $rider->stores()->pluck('stores.id');

        // Unassigned orders, plus ones offered to another rider who hasn't accepted yet:
        // every rider at the store sees the same countdown, and the first to take it gets it.
        $pool = Order::query()
            ->where(fn ($q) => $q->whereNull('delivery_partner_id')->orWhere(fn ($q) => $q
                ->where('delivery_partner_id', '!=', $me)->whereNull('rider_accepted_at')->whereNotNull('rider_offer_expires_at')))
            ->where('status', 'ready_for_delivery')
            ->when($storeIds->isNotEmpty(), fn ($query) => $query->where(fn ($q) => $q
                ->whereNull('store_id')
                ->orWhereIn('store_id', $storeIds)))
            ->with(['items', 'user:id,name,phone'])
            ->latest()
            ->get();

        // Orders cancelled at the door (customer refused to pay) — the bagged
        // items are still with the rider until the store confirms they're back.
        $pendingReturns = Order::query()
            ->where('delivery_partner_id', $me)
            ->where('status', 'cancelled')
            ->where('cancelled_by', 'rider')
            ->whereNull('items_returned_at')
            ->latest()
            ->get(['id', 'cancel_reason', 'updated_at'])
            ->map(fn (Order $o) => ['id' => $o->id, 'reason' => $o->cancel_reason, 'at' => $o->updated_at])
            ->values();

        return [
            'assigned' => $assigned->map($this->row(...))->values(),
            'pool' => $pool->map($this->row(...))->values(),
            'pending_returns' => $pendingReturns,
        ];
    }

    /**
     * The rider's own dashboard: lifetime deliveries, how this week and month
     * compare with the one before, their star rating, the share of deliveries
     * confirmed by a handover code, cash collected, and their most recent
     * ratings (scores and dates only — written feedback is for the admin).
     */
    /** "I handed over the cash" for a seller's store: the seller confirms or disputes within 24 hours. */
    public function cashHanded(Request $request, \App\Models\Store $store): JsonResponse
    {
        abort_unless($store->shop_id && $request->user()->stores()->whereKey($store->id)->exists(), 404);
        \App\Support\SellerRiderCash::claimHandover($request->user(), $store);

        return $this->packages($request);
    }

    /** Take an order a seller offered to all their riders (first come, first served). */
    public function takeOffer(Request $request, \App\Models\OrderShopShipping $promise): JsonResponse
    {
        \App\Support\SellerRiders::take($request->user(), $promise);

        return $this->packages($request);
    }

    /**
     * My joining letters: for each store I work for, when I joined and my pay.
     * (The full terms are shown once, when joining; the store keeps the signed copy.)
     */
    public function letters(Request $request): JsonResponse
    {
        $rider = $request->user();
        $letters = $rider->stores()->with('shop:id,name')->get()->map(function ($store) use ($rider) {
            $currency = \App\Support\Market::currency($store->country);
            $pay = $store->shop_id
                ? \App\Support\Money::format(\App\Support\RiderMoney::sellerRateCents($store), $currency).' per delivery'
                : \App\Support\Money::format(\App\Support\RiderLedger::baseCents($store->country), $currency).' per delivery + '.\App\Support\Money::format(\App\Support\RiderLedger::perMileCents($store->country), $currency).' per '.($store->country === 'US' ? 'mile' : 'km');

            return [
                'store' => $store->shop?->name ?? $store->name,
                'name' => $rider->name,
                'joined_on' => ($store->pivot?->linked_at ? \Illuminate\Support\Carbon::parse($store->pivot->linked_at) : ($rider->rider_since ?? $rider->created_at))->toDateString(),
                'pay' => $pay,
                'hours' => \App\Support\RiderWorkHours::label(\App\Support\RiderWorkHours::of($store)),
            ];
        })->values();

        return response()->json(['data' => $letters]);
    }

    /** Answer a store's invitation: join or no thanks. */
    public function answerInvite(Request $request, \App\Models\RiderInvite $invite): JsonResponse
    {
        abort_unless($invite->user_id === $request->user()->id, 404);
        $join = (bool) $request->validate(['join' => ['required', 'boolean']])['join'];
        \App\Support\RiderHiring::riderDecides($invite, $join);

        return response()->json(['message' => $join ? 'You’re linked to the store — clock in during its working hours.' : 'Done — the store is told.']);
    }

    /** Recent deliveries I haven't rated yet (NexTech orders and sellers' packages). */
    private function toRate(\App\Models\User $rider): array
    {
        $done = \App\Models\RiderFeedback::query()->where('rider_id', $rider->id)->where('created_at', '>=', now()->subDays(4))->pluck('order_id');
        $own = Order::query()->where('delivery_partner_id', $rider->id)->whereNotNull('delivered_at')->where('delivered_at', '>=', now()->subDays(3))
            ->whereNotIn('id', $done)->with('user:id,name')->latest('delivered_at')->limit(5)->get()
            ->map(fn ($o) => ['order_id' => $o->id, 'shop_id' => null, 'store' => \App\Support\Branding::name(), 'buyer' => $o->user?->name]);
        $seller = \App\Models\OrderPackage::query()->where('rider_id', $rider->id)->where('status', 'delivered')->where('delivered_at', '>=', now()->subDays(3))
            ->whereNotIn('order_id', $done)->with(['shop:id,name', 'order.user:id,name'])->latest('delivered_at')->limit(5)->get()
            ->map(fn ($p) => ['order_id' => $p->order_id, 'shop_id' => $p->shop_id, 'store' => $p->shop?->name, 'buyer' => $p->order?->user?->name]);

        return $own->concat($seller)->unique('order_id')->take(5)->values()->all();
    }

    /** Rate the store and the buyer of a delivery I made (private — only admin sees it). */
    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'store_rating' => ['nullable', 'integer', 'between:1,5'],
            'buyer_rating' => ['nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $rider = $request->user();
        $package = \App\Models\OrderPackage::query()->where('order_id', $data['order_id'])->where('rider_id', $rider->id)->first();
        abort_unless($package || Order::query()->whereKey($data['order_id'])->where('delivery_partner_id', $rider->id)->exists(), 404);
        \App\Models\RiderFeedback::updateOrCreate(['rider_id' => $rider->id, 'order_id' => $data['order_id']],
            ['shop_id' => $package?->shop_id, 'store_rating' => $data['store_rating'] ?? null, 'buyer_rating' => $data['buyer_rating'] ?? null, 'note' => $data['note'] ?? null]);

        return response()->json(['message' => 'Thanks — only '.\App\Support\Branding::name().'’s team sees this.']);
    }

    /** My chats with the sellers I deliver for (one per seller store). */
    public function sellerChats(Request $request): JsonResponse
    {
        $rider = $request->user();
        $list = $rider->stores()->whereNotNull('shop_id')->with('shop:id,name')->get()->filter(fn ($s) => $s->shop)->map(function ($s) use ($rider) {
            $t = \App\Support\RiderSellerChat::thread($rider, $s->shop);

            return ['store_id' => $s->id, 'shop' => $s->shop->name, 'thread_id' => $t?->id, 'last_message_at' => $t?->last_message_at, 'ticket_status' => $t?->ticket_status, 'ticket_by' => $t?->ticket_by,
                'messages' => $t ? \App\Support\RiderSellerChat::messages($t, $rider) : []];
        })->values();

        return response()->json(['data' => $list]);
    }

    /** Send a message to a seller I deliver for. */
    public function postSellerChat(Request $request, \App\Models\Store $store): JsonResponse
    {
        $rider = $request->user();
        abort_unless($store->shop_id && $rider->stores()->whereKey($store->id)->exists(), 404);
        $body = $request->validate(['body' => ['required', 'string', 'max:2000']])['body'];
        $thread = \App\Support\RiderSellerChat::open($rider, $store->shop);
        if ($thread->status === 'resolved') {
            $thread->forceFill(['status' => 'open', 'resolved_at' => null])->save();
        }
        $thread->post($rider, trim($body));

        return $this->sellerChats($request);
    }

    /** Open a support ticket on my chat with a seller (or close the one I opened). */
    public function sellerChatTicket(Request $request, \App\Models\SupportThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 404);
        $request->validate(['action' => ['required', 'in:open,close']])['action'] === 'open'
            ? \App\Support\RiderSellerChat::openTicket($thread, $request->user(), 'rider')
            : \App\Support\RiderSellerChat::withdrawTicket($thread, $request->user(), 'rider');

        return $this->sellerChats($request);
    }

    /** Ask to move to a new home area (the store approves or declines). */
    public function requestMove(Request $request): JsonResponse
    {
        $data = $request->validate(['address' => ['required', 'string', 'min:5', 'max:255']]);
        \App\Support\RiderHiring::requestMove($request->user(), $data['address']);

        return response()->json(['message' => 'Sent — the store will approve or decline your move.']);
    }

    /** Delete my rider details (no active store, nothing owed either way). */
    public function deleteDetails(Request $request): JsonResponse
    {
        \App\Support\RiderHiring::deleteDetails($request->user());

        return response()->json(['message' => 'Your rider details and documents are deleted. To deliver again, apply with new documents.']);
    }

    /** Ask for a day off: a day ahead or more is planned; today is told late. Every store I work for is told. */
    public function requestLeave(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'reason' => ['nullable', 'string', 'max:300']]);
        $rider = $request->user();
        abort_if(\App\Support\RiderHiring::activeStores($rider)->isEmpty(), 422, 'You have no store right now, so there’s no day off to ask for.');
        abort_if(\App\Models\RiderLeave::query()->where('user_id', $rider->id)->whereDate('date', $data['date'])->exists(), 422, 'You already have that day off.');
        $ahead = $data['date'] > now()->toDateString();
        \App\Models\RiderLeave::create(['user_id' => $rider->id, 'date' => $data['date'], 'kind' => 'leave', 'told_ahead' => $ahead, 'reason' => $data['reason'] ?? null]);
        $used = \App\Support\RiderWorkHours::usedThisMonth($rider);
        $allowed = \App\Support\RiderWorkHours::allowedFor($rider);
        $note = ($ahead ? '' : ' (asked on the day, not a day ahead)').($allowed !== null && $used > $allowed ? " — that's {$used} days off this month, more than the {$allowed} allowed" : '');
        foreach ($rider->stores()->with('shop.seller')->get() as $store) {
            \App\Support\RiderWorkHours::notifyStore($store, 'Rider day off', "{$rider->name} asked for a day off on {$data['date']}".($data['reason'] ?? '' ? ": {$data['reason']}" : '')."{$note}.", $ahead && $note === '');
        }

        return response()->json(['message' => $ahead ? 'Day off asked for — your store is told.' : 'Your store is told. Days off should be asked for a day ahead; this may affect today’s pay.'], 201);
    }

    /** Take back a day off that hasn't come yet. */
    public function cancelLeave(Request $request, \App\Models\RiderLeave $leave): JsonResponse
    {
        abort_unless($leave->user_id === $request->user()->id && $leave->kind === 'leave' && $leave->date->toDateString() > now()->toDateString(), 422, 'Only a day off that hasn’t come yet can be taken back.');
        $leave->delete();

        return response()->json(['message' => 'Day off taken back.']);
    }

    /** Sellers' own-delivery packages given to me, not delivered yet. */
    public function packages(Request $request): JsonResponse
    {
        $packages = \App\Models\OrderPackage::query()->where('rider_id', $request->user()->id)->whereNotIn('status', ['delivered', 'returned', 'lost'])
            ->with(['order.user:id,name,phone', 'shop:id,name', 'items.orderItem:id,product_name,unit_price_cents'])->oldest('rider_assigned_at')->get();

        // Cash I hold for each seller's store, and whether I'm paused there until I hand it over.
        $cash = $request->user()->stores()->whereNotNull('shop_id')->get()->map(fn ($store) => [
            'store_id' => $store->id,
            'store' => $store->shop?->name ?? $store->name,
            // Claimed as handed over, waiting for the seller to confirm.
            'claimed' => \App\Support\SellerRiderCash::outstanding($request->user(), $store)->whereNotNull('cash_handover_claimed_at')->exists(),
            'held_cents' => \App\Support\SellerRiderCash::heldCents($request->user(), $store),
            'limit_cents' => \App\Support\SellerRiderCash::limitCents($store),
            'currency' => \App\Support\Market::currency($store->country),
            'paused' => (bool) $store->pivot?->cash_paused_at,
        ])->filter(fn ($c) => $c['held_cents'] > 0 || $c['paused'] || $c['claimed'])->values();

        // Each seller store's days off in the next 2 weeks (weekends it doesn't ship, holidays): my timetable.
        $daysOff = $request->user()->stores()->whereNotNull('shop_id')->with('shop')->get()->filter(fn ($s) => $s->shop)
            ->map(fn ($s) => ['store' => $s->shop->name, 'days' => \App\Support\SellerShipping::daysOff($s->shop, now(), now()->addDays(14))])
            ->filter(fn ($d) => $d['days'] !== [])->values();

        // Orders sellers offered to all their riders: the first to take it gets it (same deadline for everyone).
        $open = \App\Support\SellerRiders::openOffers($request->user())->map(fn ($p) => [
            'id' => $p->id,
            'order_id' => $p->order_id,
            'store' => $p->shop?->name,
            'area' => trim(($p->order?->delivery_address['city'] ?? '').' '.($p->order?->delivery_address['postal_code'] ?? '')),
            'items' => (int) $p->order?->items->where('shop_id', $p->shop_id)->sum('quantity'),
            'cod' => $p->order?->payment_method === 'cod',
            'until' => $p->rider_offer_until,
            'missed' => (bool) $p->rider_offer_missed_at,
        ])->values();

        return response()->json(['data' => $packages->map(fn ($p) => \App\Support\SellerRiders::forRider($p))->values(), 'cash' => $cash, 'days_off' => $daysOff, 'open' => $open]);
    }

    /** Deliver a seller's package with the buyer's code (and the cash, for cash on delivery). */
    public function deliverPackage(Request $request, \App\Models\OrderPackage $package): JsonResponse
    {
        abort_unless($package->rider_id === $request->user()->id, 404);
        $data = $request->validate(['delivery_code' => ['required', 'string', 'max:8'], 'cash_collected' => ['sometimes', 'boolean']]);
        abort_unless($package->delivery_code && hash_equals($package->delivery_code, trim($data['delivery_code'])), 422, 'That delivery code doesn’t match — check it with the buyer.');
        $cod = $package->order?->payment_method === 'cod';
        abort_if($cod && empty($data['cash_collected']), 422, 'Cash on delivery: collect the cash and tick "Cash collected".');
        \App\Support\SellerProgress::advance($package, 'delivered', 'rider', $cod);
        $package->forceFill(['delivered_by' => 'rider'])->save();
        \App\Support\RiderMoney::creditSellerDelivery($package->fresh('shop'));
        if ($cod) {
            \App\Support\SellerRiderCash::collected($package->fresh('shop.seller'), $request->user());
        }

        return $this->packages($request);
    }

    public function stats(Request $request): JsonResponse
    {
        $rider = $request->user();
        $me = $rider->id;

        $delivered = Order::query()
            ->where('delivery_partner_id', $me)
            ->where('status', 'completed');

        $since = fn ($from, $to = null) => (clone $delivered)
            ->where('delivered_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('delivered_at', '<', $to))
            ->count();

        $now = now();
        $weekAgo = $now->copy()->subDays(7);
        $twoWeeksAgo = $now->copy()->subDays(14);
        $monthAgo = $now->copy()->subDays(30);
        $twoMonthsAgo = $now->copy()->subDays(60);

        $total = (clone $delivered)->count();
        $verified = (clone $delivered)->where('delivery_verified', true)->count();
        // Cash still in the rider's hand — zeroes out the moment an admin
        // confirms it's been handed back to the store.
        $codCents = (int) Order::query()
            ->where('delivery_partner_id', $me)
            ->where('status', 'completed')
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereNull('cash_settled_at')
            ->sum('total_cents');

        $recent = $rider->riderReviews()
            ->latest()
            ->limit(10)
            ->get(['rating', 'source', 'created_at'])
            ->map(fn ($r) => ['rating' => (int) $r->rating, 'source' => $r->source, 'at' => $r->created_at]);

        return response()->json(['data' => [
            'deliveries_total' => $total,
            'deliveries_week' => $since($weekAgo),
            'deliveries_week_prev' => $since($twoWeeksAgo, $weekAgo),
            'deliveries_month' => $since($monthAgo),
            'deliveries_month_prev' => $since($twoMonthsAgo, $monthAgo),
            'rating_avg' => $rider->rider_rating_avg !== null ? (float) $rider->rider_rating_avg : null,
            'rating_count' => (int) $rider->rider_rating_count,
            'verified_rate' => $total ? round($verified / $total, 3) : null,
            'cod_collected_cents' => $codCents,
            'cod_holding_cents' => $rider->codHoldingCents(),
            'recent_ratings' => $recent,
            'offers_total' => (int) $rider->rider_offers_count,
            'declined_total' => (int) $rider->rider_declined_count,
            'missed_total' => (int) $rider->rider_missed_count,
            'acceptance_rate' => $rider->riderAcceptanceRate(),
            'shift' => RiderAttendance::state($rider),
            'attendance' => RiderAttendance::summary($rider, 14),
        ]]);
    }

    public function claim(Request $request, Order $order): JsonResponse
    {
        if ($why = \App\Support\SellerRiderCash::blockedReason($request->user())) {
            return response()->json(['message' => "You’re paused in every store — {$why}."], 422);
        }
        $me = $request->user();
        $storeIds = $me->stores()->pluck('stores.id');
        abort_if($order->store_id && $storeIds->isNotEmpty() && ! $storeIds->contains($order->store_id), 422, 'This order is for a store you don’t deliver for.');

        // Locked: two riders pressing at once — only the first gets it.
        $taken = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $me) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            // Free, or offered to someone who hasn't accepted yet (the first to take it gets it).
            $free = ! $locked->delivery_partner_id || $locked->delivery_partner_id === $me->id
                || ($locked->rider_accepted_at === null && $locked->rider_offer_expires_at !== null);
            if ($locked->status !== 'ready_for_delivery' || ! $free) {
                return null;
            }
            $locked->update([
                'delivery_partner_id' => $me->id,
                'courier_name' => $me->name,
                'status' => 'out_for_delivery',
                // Pulling from the pool is a deliberate choice — no offer to accept.
                'rider_accepted_at' => now(),
                'rider_offer_expires_at' => null,
            ]);

            return $locked;
        });
        if (! $taken) {
            return response()->json(['message' => 'Another rider has already taken this order.'], 422);
        }

        return response()->json(['data' => $this->row($taken->fresh(['items', 'user:id,name,phone']))]);
    }

    /**
     * Accept or reject a pending delivery offer. Rejecting (and, via the sweep,
     * timing out) re-offers the order to the next-best rider, or drops it to the
     * shared pool when none is eligible.
     */
    public function respond(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $me = $request->user();

        $outcome = DB::transaction(function () use ($order, $me, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked
                || $locked->delivery_partner_id !== $me->id
                || in_array($locked->status, ['completed', 'cancelled'], true)) {
                return 'gone';
            }

            if ($locked->rider_accepted_at !== null) {
                return 'accepted'; // idempotent
            }

            if ($data['accept']) {
                // Lenient: allowed while still mine and unaccepted, even a few
                // seconds past expiry if nothing has swept it yet.
                $locked->forceFill([
                    'rider_accepted_at' => now(),
                    'rider_offer_expires_at' => null,
                ])->save();

                return 'accepted';
            }

            $declined = $locked->rider_offer_declined_ids ?? [];
            if (! in_array($me->id, $declined, true)) {
                $declined[] = $me->id;
            }

            $locked->forceFill([
                'rider_offer_declined_ids' => $declined,
                'rider_offer_decline_count' => (int) $locked->rider_offer_decline_count + 1,
                'delivery_partner_id' => null,
                'courier_name' => null,
                'rider_offer_expires_at' => null,
                'rider_accepted_at' => null,
            ])->save();

            $me->increment('rider_declined_count');
            RiderAssignment::assign($locked->fresh(), $declined); // null -> pool

            return 'rejected';
        });

        if ($outcome === 'gone') {
            return response()->json(['message' => 'This offer has moved to another rider.'], 409);
        }

        return response()->json(['data' => $this->board($me)
            + ['shift' => RiderAttendance::state($me->fresh())]]);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        // "Picked up" only. Completing a delivery goes through deliver() so it
        // carries an OTP confirmation (or a recorded override).
        $validated = $request->validate([
            'status' => ['required', Rule::in(['out_for_delivery'])],
        ]);

        if (! $order->canTransitionTo($validated['status'])) {
            return response()->json(['message' => "Cannot move a {$order->status} order to {$validated['status']}."], 422);
        }

        $order->update(['status' => $validated['status']]);

        return response()->json(['data' => $this->row($order->fresh(['items', 'user:id,name,phone']))]);
    }

    /**
     * At the door: generate a short handover code and send it to the customer.
     * The customer reads it back to the rider, who confirms the delivery.
     */
    public function sendDeliveryOtp(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['message' => 'Start the delivery before requesting a code.'], 422);
        }

        $ttl = 15;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $order->forceFill([
            'delivery_code' => $code,
            'delivery_code_expires_at' => now()->addMinutes($ttl),
        ])->save();

        $email = $order->user?->email;
        if ($email) {
            Notification::route('mail', $email)->notify(new DeliveryHandoverCode($order->id, $code, $ttl));
        }
        if (config('app.debug')) {
            Log::info("Delivery code for order #{$order->id} ({$email}): {$code}");
        }

        return response()->json(['data' => [
            'sent' => (bool) $email,
            'to' => $email ? $this->maskEmail($email) : null,
            'expires_in' => $ttl * 60,
        ]]);
    }

    /**
     * Complete the delivery. Either verify the handover code the customer gave
     * (`code`), or — when that can't be done — record an unverified handover
     * (`override` + a short `note`) so the store still has the evidence.
     */
    public function deliver(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        if (! $order->canTransitionTo('completed')) {
            return response()->json(['message' => "A {$order->status} order can't be marked delivered."], 422);
        }

        // Cash-on-delivery: the cash must be collected before the delivery is completed.
        if ($order->isCashOnDelivery() && $order->payment_status !== 'paid') {
            return response()->json(['message' => 'Mark the cash collected first, then complete the delivery.'], 422);
        }

        $data = $request->validate([
            'code' => ['required_without:override', 'nullable', 'string', 'max:8'],
            'override' => ['sometimes', 'boolean'],
            'note' => ['required_if:override,true', 'nullable', 'string', 'max:300'],
        ]);

        $override = (bool) ($data['override'] ?? false);

        if (! $override) {
            if (! $order->deliveryCodeActive() || ! hash_equals((string) $order->delivery_code, trim((string) $data['code']))) {
                throw ValidationException::withMessages([
                    'code' => ['That code is wrong or has expired. Ask the customer to check their email, or use "mark delivered without code".'],
                ]);
            }
        }

        $order->forceFill([
            'status' => 'completed',
            'delivered_at' => now(),
            'delivery_verified' => ! $override,
            'delivery_note' => $override ? trim((string) $data['note']) : null,
            'delivery_code' => null,
            'delivery_code_expires_at' => null,
            'rider_offer_expires_at' => null,
        ])->save();

        // Paid + delivered: send the customer their summary email with the PDF bill.
        $order->sendDeliveredReceiptIfReady();

        // Base + per-mile pay for this delivery (idempotent).
        RiderLedger::creditForDelivery($order);

        return response()->json(['data' => $this->row($order->fresh(['items', 'user:id,name,phone']))]);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $head = mb_substr($name, 0, 1);
        $tail = mb_strlen($name) > 1 ? mb_substr($name, -1) : '';

        return "{$head}***{$tail}@{$domain}";
    }

    /**
     * The rider app pings its live position here; auto-assignment prefers it
     * over the base while it's fresh (< 15 min old).
     */
    public function location(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $request->user()->forceFill([
            'rider_last_lat' => $data['lat'],
            'rider_last_lng' => $data['lng'],
            'rider_last_located_at' => now(),
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function cashCollected(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        if (! $order->isCashOnDelivery()) {
            return response()->json(['message' => 'This order was not cash on delivery.'], 422);
        }

        if ($order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'paid', 'cash_collected_at' => now()]);
            SellerLedger::creditForOrder($order);
            // Cash settled after the drop-off completes the paid + delivered pair.
            $order->sendDeliveredReceiptIfReady();
            // Over the store's own cash limit: paused in every store until the cash is returned.
            $rider = $request->user();
            $country = $order->market ?? $rider->stores()->whereNull('shop_id')->value('country');
            if ($rider->codHoldingCents() > \App\Support\SellerRiderCash::ownLimitCents($country)) {
                $held = \App\Support\Money::format($rider->codHoldingCents(), \App\Support\Market::currency($country));
                try {
                    $rider->notify(new \App\Notifications\RiderNotice('Paused — return your cash', "You hold {$held} of cash on delivery, over the limit. Return it to the store before more deliveries — until then you’re paused in every store."));
                    \Illuminate\Support\Facades\Notification::send(\App\Models\User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice("{$rider->name} is over the cash limit", "{$rider->name} holds {$held} of cash on delivery (over the limit) and is paused until it's returned. Settle it under Riders."));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return response()->json(['data' => $this->row($order->fresh(['items', 'user:id,name,phone']))]);
    }

    /**
     * The customer refuses to pay for a cash-on-delivery order at the door.
     * There's no "collect it anyway" path — the rider reports it, the order is
     * cancelled on the spot, and any gift-card spend is credited back.
     */
    public function paymentRefused(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        if (! $order->isCashOnDelivery() || $order->payment_status === 'paid') {
            return response()->json(['message' => 'This order is not awaiting cash on delivery.'], 422);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['message' => 'This order is not out for delivery.'], 422);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'max:300'],
        ]);

        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'cancelled_by' => 'rider',
            'cancel_reason' => trim($data['note']),
        ]);
        $order->restoreGiftCardRedemptions();

        return response()->json(['data' => $this->row($order->fresh(['items', 'user:id,name,phone']))]);
    }

    /**
     * Rider <-> customer chat for a delivery. Reuses the support-thread system,
     * so the customer sees it in their existing "Get help" inbox and staff see
     * it in the admin Support tab.
     */
    public function messages(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        return response()->json(['data' => $this->threadPayload($this->threadFor($order), $request->user()->id)]);
    }

    /** The customer chat as a page of messages, for the docked chat window. */
    public function chat(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);

        // Just looking doesn't start a conversation — the first message does (postMessage()).
        $thread = SupportThread::where('order_id', $order->id)->where('user_id', $order->user_id)->orderBy('id')->first();

        return response()->json(['data' => ChatPage::of($thread, 'rider', $request->integer('before') ?: null)]);
    }

    public function postMessage(Request $request, Order $order): JsonResponse
    {
        $this->assertMine($request, $order);
        $validated = $request->validate(ChatPage::MESSAGE_RULES);
        $body = trim((string) ($validated['body'] ?? ''));

        $thread = $this->threadFor($order);
        if ($thread->status === 'resolved') {
            $thread->forceFill(['status' => 'open', 'resolved_at' => null])->save();
        }
        // Rider messages read as "staff" on the customer's side.
        $thread->post($request->user(), $body, isStaff: true, attachments: $validated['attachments'] ?? []);

        // Delivery chat is time-sensitive — nudge the customer by email too.
        $email = $order->user?->email;
        if ($email) {
            Notification::route('mail', $email)->notify(new RiderMessage($order->id, $body !== '' ? $body : 'Sent you a photo.'));
        }

        return response()->json(['data' => $this->threadPayload($thread->fresh(), $request->user()->id)]);
    }

    private function threadFor(Order $order): SupportThread
    {
        $thread = SupportThread::where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->orderBy('id')
            ->first();

        if (! $thread) {
            $thread = SupportThread::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'issue_type' => 'delivery',
                'status' => 'open',
            ]);
            $thread->post(null, "Delivery chat for order #{$order->id} — your rider will message you here.", system: true);
        }

        return $thread;
    }

    /**
     * @return array<string, mixed>
     */
    private function threadPayload(SupportThread $thread, int $riderId): array
    {
        $thread->load('messages');

        return [
            'thread_id' => $thread->id,
            'status' => $thread->status,
            'messages' => $thread->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => $m->user_id === $riderId,
                'from' => $m->user_id === null ? 'system' : ($m->is_staff ? 'staff' : 'customer'),
                'at' => $m->created_at,
            ])->values(),
        ];
    }

    private function assertMine(Request $request, Order $order): void
    {
        abort_unless($order->delivery_partner_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        $codDue = $order->isCashOnDelivery() && $order->payment_status !== 'paid' ? (int) $order->total_cents : 0;

        return [
            'id' => $order->id,
            'status' => $order->status,
            'customer_name' => $order->user?->name,
            'customer_phone' => $order->delivery_address['phone'] ?? $order->user?->phone,
            'delivery_address' => $order->delivery_address,
            'delivery_instructions' => $order->delivery_instructions,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'total_cents' => (int) $order->total_cents,
            'cod_due' => $codDue,
            'offer_pending' => $order->rider_offer_expires_at !== null
                && $order->rider_accepted_at === null
                && $order->status === 'ready_for_delivery',
            'offer_expires_at' => $order->rider_offer_expires_at,
            // The same pick-up deadline for every rider; after it the store is told nobody took it.
            'pickup_by' => $order->status === 'ready_for_delivery' && $order->rider_accepted_at === null ? \App\Support\RiderAssignment::deadline($order) : null,
            'accepted' => $order->rider_accepted_at !== null,
            'delivery_code_active' => $order->deliveryCodeActive(),
            'delivered_at' => $order->delivered_at,
            'delivery_verified' => $order->delivery_verified,
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->product_name.($i->variant_label ? " · {$i->variant_label}" : ''),
                'quantity' => $i->quantity,
            ])->values(),
        ];
    }
}
