<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabelRequest;
use App\Models\OrderPackage;
use App\Models\SellerLedgerEntry;
use App\Support\Market;
use App\Support\SellerFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Manual NexTech labels: sellers request a label and an admin uploads the
 * label PDF for them to download. Tracking and postage are optional: with a
 * tracking number the package ships right away (and any postage is deducted
 * from the seller's earnings); without one the seller adds tracking when they
 * hand the package over.
 */
class AdminLabelRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'requested');

        $requests = LabelRequest::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['shop:id,name,market', 'shipFrom', 'order:id,delivery_address,status,created_at', 'order.items:id,order_id,product_name,variant_label,sku,quantity'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (LabelRequest $r) => $this->present($r));

        return response()->json(['data' => $requests]);
    }

    public function fulfil(Request $request, LabelRequest $labelRequest): JsonResponse
    {
        abort_unless($labelRequest->status === 'requested', 422, 'This request has already been handled.');
        $shop = $labelRequest->shop;
        $data = $request->validate([
            'label' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'carrier' => ['nullable', 'required_with:tracking_number', Rule::in(array_keys(Market::carriers($shop->market)))],
            'tracking_number' => ['nullable', 'string', 'min:6', 'max:60', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'cost_cents' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        // Private disk: the label carries the customer's name and address.
        $path = $request->file('label')->store('labels', 'local');

        try {
            DB::transaction(function () use ($labelRequest, $shop, $data, $path, $request): void {
                // Just the file: the seller downloads it, ships, and adds tracking.
                if (empty($data['tracking_number'])) {
                    $labelRequest->update([
                        'status' => 'ready',
                        'label_path' => $path,
                        'handled_by' => $request->user()->id,
                        'handled_at' => now(),
                    ]);
                    if ((int) ($data['cost_cents'] ?? 0) > 0) {
                        SellerLedgerEntry::create([
                            'shop_id' => $shop->id,
                            'order_id' => $labelRequest->order_id,
                            'type' => 'shipping_label',
                            'amount_cents' => -(int) $data['cost_cents'],
                            'note' => 'NexTech label for order #'.$labelRequest->order_id,
                        ]);
                    }

                    return;
                }

                $package = SellerFulfillment::createPackage($labelRequest->order, $shop, $labelRequest->items, $labelRequest->ship_from_address_id, [
                    'label_source' => 'nextech',
                    'carrier' => $data['carrier'],
                    'tracking_number' => strtoupper(preg_replace('/\s+/', '', $data['tracking_number'])),
                    'label_path' => $path,
                    'label_cost_cents' => (int) ($data['cost_cents'] ?? 0),
                ], $labelRequest);
                SellerFulfillment::chargeLabel($package);

                $labelRequest->update([
                    'status' => 'ready',
                    'label_path' => $path,
                    'order_package_id' => $package->id,
                    'handled_by' => $request->user()->id,
                    'handled_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json(['data' => $this->present($labelRequest->fresh())]);
    }

    /**
     * Swap in a label the admin made (e.g. the customer changed something, or
     * a custom label made elsewhere). Works on generated or uploaded labels,
     * shipped or not; the seller downloads the new file from the same place.
     */
    public function replace(Request $request, LabelRequest $labelRequest): JsonResponse
    {
        abort_unless($labelRequest->status === 'ready', 422, 'Only an issued label can be replaced — upload to a waiting request instead.');
        $request->validate(['label' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120']]);

        $old = $labelRequest->label_path;
        $path = $request->file('label')->store('labels', 'local');
        $labelRequest->update(['label_path' => $path, 'label_template_id' => null, 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        $labelRequest->package?->update(['label_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        return response()->json(['data' => $this->present($labelRequest->fresh())]);
    }

    public function cancel(Request $request, LabelRequest $labelRequest): JsonResponse
    {
        abort_unless($labelRequest->status === 'requested', 422, 'This request has already been handled.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $labelRequest->update([
            'status' => 'cancelled',
            'admin_note' => $data['reason'],
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return response()->json(['data' => $this->present($labelRequest->fresh())]);
    }

    public function downloadRequest(LabelRequest $labelRequest)
    {
        abort_unless($labelRequest->label_path, 404);

        return Storage::disk('local')->download($labelRequest->label_path, "label-order-{$labelRequest->order_id}.".pathinfo($labelRequest->label_path, PATHINFO_EXTENSION));
    }

    public function download(OrderPackage $package)
    {
        abort_unless($package->label_path, 404);

        return Storage::disk('local')->download($package->label_path, "label-order-{$package->order_id}-{$package->id}.".pathinfo($package->label_path, PATHINFO_EXTENSION));
    }

    /** @return array<string, mixed> */
    private function present(LabelRequest $r): array
    {
        $r->loadMissing(['shop:id,name,market', 'shipFrom', 'order.items']);
        $address = (array) $r->order?->delivery_address;
        $lines = $r->order?->items->keyBy('id');

        return [
            'id' => $r->id,
            'status' => $r->status,
            'order_id' => $r->order_id,
            'shop_id' => $r->shop_id,
            'shop_name' => $r->shop?->name,
            'market' => $r->shop?->market,
            'currency' => Market::currency($r->shop?->market),
            'carriers' => collect(Market::carriers($r->shop?->market))->map(fn ($c, $key) => ['value' => $key, 'label' => $c[0]])->values(),
            'note' => $r->note,
            'admin_note' => $r->admin_note,
            'order_package_id' => $r->order_package_id,
            'has_label_file' => $r->has_label_file,
            'created_at' => $r->created_at,
            'handled_at' => $r->handled_at,
            'ship_from' => ($r->shipFrom?->toCourierAddress() ?? []) + ['name' => $r->shipFrom?->name, 'contact_name' => $r->shipFrom?->contact_name],
            'ship_to' => [
                'name' => $address['name'] ?? null,
                'line1' => $address['line1'] ?? null,
                'line2' => $address['line2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'phone' => $address['phone'] ?? null,
            ],
            'items' => collect($r->items)->map(fn ($i) => [
                'order_item_id' => $i['order_item_id'],
                'quantity' => $i['quantity'],
                'product_name' => $lines?->get($i['order_item_id'])?->product_name,
                'variant_label' => $lines?->get($i['order_item_id'])?->variant_label,
                'sku' => $lines?->get($i['order_item_id'])?->sku,
            ])->values(),
        ];
    }
}
