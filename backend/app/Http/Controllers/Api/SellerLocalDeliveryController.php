<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderApplication;
use App\Models\Shop;
use App\Models\Store;
use App\Models\User;
use App\Support\RiderAttendance;
use App\Support\RiderHiring;
use App\Support\SellerStores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Seller Center → Local delivery: the seller's store, hiring riders for it
 * (applications they accept or decline), and the riders who deliver for it.
 * The seller hires, manages and pays their own riders; the store's admin sees
 * all of it and can step in.
 */
class SellerLocalDeliveryController extends Controller
{
    private function shop(Request $request): Shop
    {
        $shop = $request->user()->seller?->shop;
        abort_unless($shop && $request->user()->seller->status === 'approved', 403, 'Approved seller access required.');

        return $shop;
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->shop($request))]);
    }

    /** Open or close hiring for the seller's store (only while local delivery is on). */
    public function hiring(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate(['open' => ['required', 'boolean']]);
        $store = SellerStores::ensure($shop);
        abort_if($data['open'] && SellerStores::status($store) !== 'on', 422, 'Turn on local delivery first (Shipping settings → Own delivery (local)).');
        $store->forceFill(['hiring_open' => $data['open']])->save();

        return response()->json(['data' => $this->payload($shop)]);
    }

    /**
     * The seller accepts an applicant for their store. That's a recommendation:
     * the store's admin checks the documents and gives final approval.
     */
    public function approve(Request $request, RiderApplication $application): JsonResponse
    {
        $shop = $this->shop($request);
        $this->mine($shop, $application);
        abort_unless($application->status === 'pending', 422, 'This application was already decided.');
        $missing = RiderHiring::unmet($application);
        abort_if($missing !== [], 422, 'Can’t accept yet — the local rules need: '.implode(', ', $missing).'.');
        $application->update(['status' => 'seller_accepted', 'store_id' => SellerStores::ensure($shop)->id, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'decided_by_seller' => true]);
        try {
            \Illuminate\Support\Facades\Notification::send(User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice(
                "{$shop->name} accepted a rider — please approve", "{$shop->name} wants to hire {$application->user?->name} as a rider. Check their documents and approve or decline under Riders → Rider applications."));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->payload($shop)]);
    }

    public function reject(Request $request, RiderApplication $application): JsonResponse
    {
        $shop = $this->shop($request);
        $this->mine($shop, $application);
        abort_unless($application->status === 'pending', 422, 'Only a pending application can be declined.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $application->update(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'decided_by_seller' => true]);

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** Remove a rider from the seller's store — only when they have no open orders from it. */
    public function removeRider(Request $request, User $rider): JsonResponse
    {
        $shop = $this->shop($request);
        $store = SellerStores::ensure($shop);
        abort_unless($rider->stores()->whereKey($store->id)->exists(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $open = RiderHiring::openOrders($rider, $store);
        abort_if($open > 0, 422, "{$rider->name} still has {$open} open order".($open === 1 ? '' : 's')." from your store — finish or reassign them first.");
        $rider->stores()->detach($store->id);
        // The store's admins hear about it, with the seller's reason.
        try {
            \Illuminate\Support\Facades\Notification::send(User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice(
                "{$shop->name} removed a rider", "{$shop->name} removed {$rider->name} from their store. Reason: {$data['reason']}"));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** The seller got the cash from their rider: handed over, and the rider can deliver again. */
    public function cashReceived(Request $request, User $rider): JsonResponse
    {
        $shop = $this->shop($request);
        $store = SellerStores::ensure($shop);
        abort_unless($rider->stores()->whereKey($store->id)->exists(), 404);
        \App\Support\SellerRiderCash::received($rider, $store);

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** The rider says they handed over cash the seller didn't get: they still hold it and are paused. */
    public function cashNotReceived(Request $request, User $rider): JsonResponse
    {
        $shop = $this->shop($request);
        $store = SellerStores::ensure($shop);
        abort_unless($rider->stores()->whereKey($store->id)->exists(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        \App\Support\SellerRiderCash::notReceived($rider, $store, $data['reason']);

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** "Later": keep the rider working despite the limit — at the seller's own risk. */
    public function cashLater(Request $request, User $rider): JsonResponse
    {
        $shop = $this->shop($request);
        $store = SellerStores::ensure($shop);
        abort_unless($rider->stores()->whereKey($store->id)->exists(), 404);
        \App\Support\SellerRiderCash::later($rider, $store);

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** What the seller pays their riders per delivery (the store pays the rider and charges the seller). */
    public function riderPay(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate(['pay_cents' => ['required', 'integer', 'min:0', 'max:10000000']]);
        SellerStores::ensure($shop)->forceFill(['rider_pay_cents' => $data['pay_cents']])->save();

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** Money per rider for a month (deliveries, fees, pay, cash) — this seller's store only. */
    public function money(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $month = \Illuminate\Support\Carbon::parse(($request->validate(['month' => ['sometimes', 'date_format:Y-m']])['month'] ?? now()->format('Y-m')).'-01');

        return response()->json(['data' => \App\Support\RiderMoney::table($month, SellerStores::ensure($shop))]);
    }

    /** The most cash one rider may hold for the store before they're paused. */
    public function cashLimit(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate(['limit_cents' => ['required', 'integer', 'min:0', 'max:100000000']]);
        SellerStores::ensure($shop)->forceFill(['rider_cash_limit_cents' => $data['limit_cents']])->save();

        return response()->json(['data' => $this->payload($shop)]);
    }

    private function mine(Shop $shop, RiderApplication $application): void
    {
        abort_unless(in_array(SellerStores::ensure($shop)->id, RiderHiring::preferred($application), true), 404);
    }

    private function payload(Shop $shop): array
    {
        $store = SellerStores::ensure($shop)->loadCount('riders');
        $riders = $store->riders()->where('is_rider', true)
            ->withCount([
                'deliveries as active_deliveries' => fn ($q) => $q->where('store_id', $store->id)->whereIn('status', ['ready_for_delivery', 'out_for_delivery']),
                'deliveries as delivered_today' => fn ($q) => $q->where('store_id', $store->id)->where('status', 'delivered')->where('delivered_at', '>=', now()->startOfDay()),
            ])->orderBy('name')->get();
        $vehicles = RiderApplication::query()->whereIn('user_id', $riders->pluck('id'))->get(['user_id', 'vehicle_type', 'experience_months'])->keyBy('user_id')->map->only(['vehicle_type', 'experience_months']);
        // Applications that picked this store (at any priority).
        $applications = RiderApplication::query()->where(fn ($q) => $q->where('store_id', $store->id)->orWhereJsonContains('preferred_store_ids', $store->id))
            ->where(fn ($q) => $q->whereIn('status', ['pending', 'seller_accepted'])->orWhere('reviewed_at', '>=', now()->subDays(30)))
            ->with('user:id,name,email')->orderByRaw("status = 'pending' desc")->latest()->get();

        return [
            'store' => [
                'id' => $store->id,
                'status' => SellerStores::status($store),
                'hiring_open' => (bool) $store->hiring_open,
                'radius_km' => $store->delivery_radius_km,
                'address' => implode(', ', array_filter([$store->line1, $store->city, $store->postal_code])),
                'located' => $store->hasCoordinates(),
            ],
            'min_age' => RiderHiring::minAge($store->country),
            'cash_limit_cents' => \App\Support\SellerRiderCash::limitCents($store),
            'rider_pay_cents' => \App\Support\RiderMoney::sellerRateCents($store),
            'currency' => \App\Support\Market::currency($store->country),
            'apply_url' => rtrim((string) config('app.url'), '/').'/#/rider',
            'riders' => $riders->map(fn (User $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'vehicle' => $vehicles[$r->id]['vehicle_type'] ?? null,
                'experience_months' => $vehicles[$r->id]['experience_months'] ?? null,
                // How long they've delivered for this store.
                'with_store_since' => $r->pivot?->linked_at,
                // Total time as a rider (with any store).
                'rider_since' => $r->rider_since,
                // Cash they hold for this store; paused until handed over (or "Later").
                'cash_held_cents' => \App\Support\SellerRiderCash::heldCents($r, $store),
                'cash_paused' => (bool) $r->pivot?->cash_paused_at,
                'cash_later' => (bool) $r->pivot?->cash_later_at,
                // They say they handed over cash you haven't confirmed yet.
                'cash_claimed_cents' => (int) \App\Support\SellerRiderCash::outstanding($r, $store)->whereNotNull('cash_handover_claimed_at')->get()->sum(fn ($p) => \App\Support\SellerRiders::cashCents($p)),
                'shift' => RiderAttendance::state($r)['status'] ?? 'off',
                'active' => (bool) $r->rider_is_active,
                'active_deliveries' => (int) $r->active_deliveries,
                'delivered_today' => (int) $r->delivered_today,
                // Also delivers for other stores (admin can link riders to several nearby stores).
                'other_stores' => $r->stores()->whereKeyNot($store->id)->count(),
            ])->values(),
            'applications' => $applications->map(fn (RiderApplication $a) => [
                'id' => $a->id,
                'name' => $a->user?->name,
                'email' => $a->email ?? $a->user?->email,
                'phone' => $a->phone,
                'age' => $a->date_of_birth?->age,
                'home_address' => $a->home_address,
                'vehicle_type' => $a->vehicle_type,
                'experience_months' => $a->experience_months,
                'education' => $a->education,
                'work_history' => $a->work_history,
                'health' => $a->health_issue ? ($a->health_details ?: 'Yes') : 'None declared',
                // Where this store is in their list (1 = first choice).
                'priority' => array_search($store->id, RiderHiring::preferred($a), true) + 1,
                'unmet' => $a->status === 'pending' ? RiderHiring::unmet($a) : [],
                'license_number' => $a->license_number,
                'documents' => array_filter([
                    'ID proof' => $a->id_document_path,
                    'Driving licence' => $a->license_document_path,
                    'Vehicle RC' => $a->rc_document_path,
                    'Education' => $a->education_document_path,
                    'Photo' => $a->photo_path,
                ]),
                'status' => $a->status,
                'rejection_reason' => $a->rejection_reason,
                'applied_at' => $a->created_at,
            ])->values(),
        ];
    }
}
