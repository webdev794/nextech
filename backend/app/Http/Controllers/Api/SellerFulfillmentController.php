<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabelRequest;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\Shop;
use App\Support\Courier;
use App\Support\Market;
use App\Support\Privacy;
use App\Support\SellerFulfillment;
use App\Support\SellerOrders;
use App\Support\SellerShipping;
use App\Support\ShippingLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Seller Center -> Manage orders for sellers who ship themselves: orders to
 * ship (with the customer's shipping address — name and address only, never
 * their email or phone), confirming a shipment with carrier + tracking or a
 * NexTech-bought label, correcting tracking, and marking delivery.
 */
class SellerFulfillmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $orders = Order::query()
            ->whereHas('items', fn ($q) => $q->where('shop_id', $shop->id)->where('fulfilled_by', 'seller'))
            ->whereNotIn('status', ['pending_payment'])
            ->with([
                'items' => fn ($q) => $q->where('shop_id', $shop->id)->where('fulfilled_by', 'seller'),
                'packages' => fn ($q) => $q->where('shop_id', $shop->id)->with('items'),
                'shopShipping' => fn ($q) => $q->where('shop_id', $shop->id),
            ])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $orders->map(fn (Order $o) => $this->row($o))->values()]);
    }

    /** Own courier: the seller hands the package over, then enters carrier + tracking. */
    public function ship(Request $request, Order $order): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'ship_from_address_id' => ['required', 'integer', Rule::exists('shop_addresses', 'id')->where('shop_id', $shop->id)],
            'carrier' => ['required', Rule::in(array_keys(Market::carriers($request->user()->seller?->shop?->market)))],
            'tracking_number' => ['required', 'string', 'min:6', 'max:60', 'regex:/^[A-Za-z0-9\- ]+$/'],
            // The format check is advisory; the seller can confirm and submit anyway.
            'ignore_format_warning' => ['sometimes', 'boolean'],
            // Shipping a package that uses a label NexTech uploaded.
            'label_request_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $labelRequest = ! empty($data['label_request_id'])
            ? LabelRequest::where('shop_id', $shop->id)->where('order_id', $order->id)->where('status', 'ready')->whereNull('order_package_id')->findOrFail($data['label_request_id'])
            : null;

        $tracking = strtoupper(preg_replace('/\s+/', '', $data['tracking_number']));
        if (! ($data['ignore_format_warning'] ?? false) && ! SellerShipping::trackingLooksValid($data['carrier'], $tracking)) {
            return response()->json([
                'message' => "That doesn't look like a {$data['carrier']} tracking number. Check it, or submit anyway if you're sure.",
                'format_warning' => true,
            ], 422);
        }

        if ($labelRequest) {
            $package = DB::transaction(function () use ($order, $shop, $data, $tracking, $labelRequest) {
                $package = SellerFulfillment::createPackage($order, $shop, $labelRequest->items, (int) $data['ship_from_address_id'], [
                    'label_source' => 'own', // tracking entered by the seller, so they can still correct it
                    'carrier' => $data['carrier'],
                    'tracking_number' => $tracking,
                    'label_path' => $labelRequest->label_path,
                ], $labelRequest);
                $labelRequest->update(['order_package_id' => $package->id]);

                return $package;
            });
        } else {
            $package = $this->createPackage($order, $shop, $data['items'], (int) $data['ship_from_address_id'], [
                'label_source' => 'own',
                'carrier' => $data['carrier'],
                'tracking_number' => $tracking,
            ]);
        }

        return response()->json(['data' => $this->row($order->fresh()), 'package' => $package], 201);
    }

    /** NexTech label: bought on NexTech's courier account, postage deducted from the seller's earnings. */
    public function buyLabel(Request $request, Order $order): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'ship_from_address_id' => ['required', 'integer', Rule::exists('shop_addresses', 'id')->where('shop_id', $shop->id)],
        ]);

        abort_if(SellerFulfillment::labelMode() === 'manual', 422, 'Request the label instead — NexTech will upload it for you to download.');
        SellerOrders::assertShippable($order);
        $origin = $shop->addresses()->findOrFail($data['ship_from_address_id'])->toCourierAddress();
        $label = Courier::buyLabel($order, $origin);

        $package = $this->createPackage($order, $shop, $data['items'], (int) $data['ship_from_address_id'], [
            'label_source' => 'nextech',
            'carrier' => $label['carrier'],
            'tracking_number' => $label['tracking_number'],
            'label_url' => $label['label_url'],
            'label_cost_cents' => $label['cost_cents'],
        ]);
        SellerFulfillment::chargeLabel($package);

        return response()->json(['data' => $this->row($order->fresh()), 'package' => $package], 201);
    }

    /**
     * Manual NexTech labels: ask NexTech for a label for these units. An admin
     * buys it, uploads the file with tracking + postage, and the package is
     * created then (postage deducted from the seller's earnings).
     */
    public function requestLabel(Request $request, Order $order): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($shop->fulfillment_mode === 'label', 422, 'Switch to "I ship, NexTech label" in Shipping settings first.');
        SellerOrders::assertShippable($order);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'ship_from_address_id' => ['required', 'integer', Rule::exists('shop_addresses', 'id')->where('shop_id', $shop->id)],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'label_template_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        abort_if(in_array($order->status, ['pending_payment', 'cancelled'], true), 422, 'This order can\'t be shipped.');

        $lines = SellerFulfillment::shopLines($order, $shop)->keyBy('id');
        foreach ($data['items'] as $row) {
            $line = $lines->get($row['order_item_id']);
            abort_unless($line, 422, 'That item isn\'t one of yours on this order.');
            $free = SellerFulfillment::remainingQuantity($line) - SellerFulfillment::requestedQuantity($line);
            abort_if($row['quantity'] > $free, 422, $free > 0 ? "Only {$free} × {$line->product_name} can go on a new label." : "{$line->product_name} already has a label requested.");
        }

        $labelRequest = LabelRequest::create([
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'ship_from_address_id' => $data['ship_from_address_id'],
            'items' => collect($data['items'])->map(fn ($r) => ['order_item_id' => (int) $r['order_item_id'], 'quantity' => (int) $r['quantity']])->values()->all(),
            'note' => $data['note'] ?? null,
        ]);

        // Built in: the label PDF is generated from a template right away. Only
        // with no active template does it wait for an admin to upload one.
        $template = ShippingLabel::templateFor($data['label_template_id'] ?? null, $shop->label_template_id);
        if ($template) {
            $labelRequest->update([
                'status' => 'ready',
                'label_template_id' => $template->id,
                'label_path' => ShippingLabel::generate($labelRequest, $template),
                'handled_at' => now(),
            ]);
            $shop->update(['label_template_id' => $template->id]);
        }

        return response()->json(['data' => $this->row($order->fresh())], 201);
    }

    /** Switch a label to another template (any time) and regenerate the PDF. */
    public function changeLabelTemplate(Request $request, LabelRequest $labelRequest): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($labelRequest->shop_id === $shop->id, 404);
        abort_unless($labelRequest->status === 'ready' && $labelRequest->label_template_id, 422, 'Only generated labels can switch template.');
        $data = $request->validate(['label_template_id' => ['required', 'integer', Rule::exists('label_templates', 'id')->where('is_active', true)]]);

        $template = ShippingLabel::templateFor((int) $data['label_template_id'], null);
        $old = $labelRequest->label_path;
        $path = ShippingLabel::generate($labelRequest, $template);
        $labelRequest->update(['label_template_id' => $template->id, 'label_path' => $path]);
        $labelRequest->package?->update(['label_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
        $shop->update(['label_template_id' => $template->id]);

        return response()->json(['data' => $this->row($labelRequest->order->fresh())]);
    }

    public function cancelLabelRequest(Request $request, LabelRequest $labelRequest): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($labelRequest->shop_id === $shop->id, 404);
        abort_unless($labelRequest->status === 'requested' || ($labelRequest->status === 'ready' && ! $labelRequest->order_package_id), 422, 'This label is already on a shipped package.');
        $labelRequest->update(['status' => 'cancelled', 'admin_note' => 'Cancelled by the seller.']);

        return response()->json(['data' => $this->row($labelRequest->order->fresh())]);
    }

    /** Download the label NexTech uploaded for a request. */
    public function downloadRequestLabel(Request $request, LabelRequest $labelRequest)
    {
        $shop = $this->shop($request);
        abort_unless($labelRequest->shop_id === $shop->id && $labelRequest->label_path, 404);

        return Storage::disk('local')->download($labelRequest->label_path, "label-order-{$labelRequest->order_id}.".pathinfo($labelRequest->label_path, PATHINFO_EXTENSION));
    }

    /** Download an admin-uploaded label (private file — it carries the customer's address). */
    public function downloadLabel(Request $request, OrderPackage $package)
    {
        $shop = $this->shop($request);
        abort_unless($package->shop_id === $shop->id && $package->label_path, 404);

        return Storage::disk('local')->download($package->label_path, "label-order-{$package->order_id}-{$package->id}.".pathinfo($package->label_path, PATHINFO_EXTENSION));
    }

    /**
     * Correct a package's carrier/tracking. Allowed until it's delivered,
     * returned or lost, and at most OrderPackage::MAX_EDITS times; NexTech
     * labels can't be changed (their tracking comes from the courier).
     */
    public function updatePackage(Request $request, OrderPackage $package): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($package->shop_id === $shop->id, 404);
        $data = $request->validate([
            'carrier' => ['required', Rule::in(array_keys(Market::carriers($request->user()->seller?->shop?->market)))],
            'tracking_number' => ['required', 'string', 'min:6', 'max:60', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'ignore_format_warning' => ['sometimes', 'boolean'],
        ]);

        $this->applyTrackingEdit($package, $data);

        return response()->json(['data' => $this->row($package->order->fresh())]);
    }

    /** Correct tracking on several packages at once (Shipped tab, "Edit shipping information"). */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'packages' => ['required', 'array', 'min:1', 'max:50'],
            'packages.*.id' => ['required', 'integer'],
            'packages.*.carrier' => ['required', Rule::in(array_keys(Market::carriers($request->user()->seller?->shop?->market)))],
            'packages.*.tracking_number' => ['required', 'string', 'min:6', 'max:60', 'regex:/^[A-Za-z0-9\- ]+$/'],
        ]);

        DB::transaction(function () use ($shop, $data) {
            foreach ($data['packages'] as $row) {
                $package = OrderPackage::where('shop_id', $shop->id)->findOrFail($row['id']);
                $this->applyTrackingEdit($package, $row + ['ignore_format_warning' => true]);
            }
        });

        return response()->json(['data' => ['updated' => count($data['packages'])]]);
    }

    /** Own-courier packages: the seller confirms delivery (the customer or admin can too). */
    public function markDelivered(Request $request, OrderPackage $package): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($package->shop_id === $shop->id, 404);
        abort_if($package->status === 'delivered', 422, 'Already marked delivered.');

        $package->update(['status' => 'delivered', 'delivered_at' => now()]);
        SellerFulfillment::sync($package->order);

        return response()->json(['data' => $this->row($package->order->fresh())]);
    }

    /** NexTech labels: pull the latest status from the courier. */
    public function syncLabel(Request $request, OrderPackage $package): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($package->shop_id === $shop->id, 404);
        abort_unless($package->label_source === 'nextech' && ! $package->label_path, 422, 'Only NexTech labels bought through the courier connection are tracked automatically.');

        $status = Courier::trackLabel($package->carrier, $package->tracking_number, $package->shipped_at, $package->status);
        $package->update(['status' => $status, 'delivered_at' => $status === 'delivered' ? ($package->delivered_at ?? now()) : null]);
        SellerFulfillment::sync($package->order);

        return response()->json(['data' => $this->row($package->order->fresh())]);
    }

    // ---------------------------------------------------------------- helpers

    private function createPackage(Order $order, Shop $shop, array $items, int $addressId, array $attributes): OrderPackage
    {
        return SellerFulfillment::createPackage($order, $shop, $items, $addressId, $attributes);
    }

    /** @param  array<string, mixed>  $data */
    private function applyTrackingEdit(OrderPackage $package, array $data): void
    {
        abort_unless($package->label_source === 'own', 422, 'Tracking on a NexTech label comes from the courier and can\'t be edited.');
        abort_unless(in_array($package->status, ['shipped', 'in_transit'], true), 422, 'Tracking can\'t be changed once a package is delivered, returned or lost.');
        abort_if($package->edit_count >= OrderPackage::MAX_EDITS, 422, 'This package\'s tracking has already been changed '.OrderPackage::MAX_EDITS.' times.');

        $tracking = strtoupper(preg_replace('/\s+/', '', $data['tracking_number']));
        if (! ($data['ignore_format_warning'] ?? false) && ! SellerShipping::trackingLooksValid($data['carrier'], $tracking)) {
            abort(response()->json([
                'message' => "That doesn't look like a {$data['carrier']} tracking number. Check it, or submit anyway if you're sure.",
                'format_warning' => true,
            ], 422));
        }

        $package->update([
            'carrier' => $data['carrier'],
            'tracking_number' => $tracking,
            'edit_count' => $package->edit_count + 1,
            'last_edited_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Order $order): array
    {
        $ownLabel = request()->user()?->seller?->shop?->fulfillment_mode === 'self';
        $order->loadMissing(['items', 'packages.items', 'shopShipping', 'labelRequests']);
        $shopId = $order->packages->first()->shop_id ?? $order->shopShipping->first()->shop_id ?? $order->items->first()?->shop_id;
        $items = $order->items->where('shop_id', $shopId)->where('fulfilled_by', 'seller')->values();
        $address = (array) $order->delivery_address;
        $promise = $order->shopShipping->firstWhere('shop_id', $shopId);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'created_at' => $order->created_at,
            // Masked name; the street address and real name only for sellers who write
            // their own courier label — NexTech prints them on the labels it makes.
            'ship_to' => [
                'name' => Privacy::maskName($address['name'] ?? null),
                'recipient' => $ownLabel ? ($address['name'] ?? null) : null,
                'line1' => $ownLabel ? ($address['line1'] ?? null) : null,
                'line2' => $ownLabel ? ($address['line2'] ?? null) : null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $ownLabel ? ($address['postal_code'] ?? null) : null,
            ],
            'shipping' => $promise,
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'product_name' => $i->product_name,
                'variant_label' => $i->variant_label,
                'sku' => $i->sku,
                'quantity' => $i->quantity,
                'remaining' => SellerFulfillment::remainingQuantity($i),
                'label_requested' => SellerFulfillment::requestedQuantity($i),
            ]),
            'label_requests' => $order->labelRequests->where('shop_id', $shopId)->values(),
            'packages' => $order->packages->where('shop_id', $shopId)->values(),
            'to_ship' => $items->sum(fn ($i) => SellerFulfillment::remainingQuantity($i)),
            'overdue' => $promise && $promise->ship_by->lt(today()) && $items->contains(fn ($i) => SellerFulfillment::remainingQuantity($i) > 0),
            // Not to be shipped yet: still pending, or the buyer's address change is undecided.
            'pending' => SellerOrders::isPending($order),
            'address_change_pending' => $order->addressChanges()->where('status', 'pending')->exists(),
        ];
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');
        abort_unless($seller->shop, 404, 'No shop found for this seller.');

        return $seller->shop;
    }
}
