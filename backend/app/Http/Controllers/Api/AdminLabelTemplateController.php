<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabelRequest;
use App\Models\LabelTemplate;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopAddress;
use App\Support\ShippingLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin -> Settings -> Shipping labels: the templates seller labels are
 * generated from. The default one is used unless the seller picks another.
 */
class AdminLabelTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => LabelTemplate::orderByDesc('is_default')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->save(new LabelTemplate(), $this->validated($request))], 201);
    }

    public function update(Request $request, LabelTemplate $labelTemplate): JsonResponse
    {
        return response()->json(['data' => $this->save($labelTemplate, $this->validated($request))]);
    }

    public function destroy(LabelTemplate $labelTemplate): JsonResponse
    {
        abort_if(LabelTemplate::whereKeyNot($labelTemplate->id)->where('is_active', true)->doesntExist() && $labelTemplate->is_active, 422, 'Keep at least one active template — or switch it off, and labels go back to admin uploads.');
        $wasDefault = $labelTemplate->is_default;
        $labelTemplate->delete();
        if ($wasDefault) {
            LabelTemplate::where('is_active', true)->orderBy('id')->first()?->update(['is_default' => true]);
        }

        return response()->json(status: 204);
    }

    /** A sample label with made-up addresses, to check the layout. */
    public function preview(LabelTemplate $labelTemplate)
    {
        $order = new Order(['delivery_address' => ['name' => 'Jane Customer', 'line1' => '123 Main Street', 'line2' => 'Apt 4B', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'phone' => '+1 555 010 0000']]);
        $order->id = 1234;
        $order->setRelation('items', collect());
        $request = new LabelRequest(['items' => []]);
        $request->id = 1;
        $request->setRelation('order', $order);
        $request->setRelation('shop', new Shop(['name' => 'Sample Seller Shop']));
        $request->setRelation('shipFrom', new ShopAddress(['contact_name' => 'Alex Seller', 'line1' => '500 Warehouse Rd', 'city' => 'Dallas', 'state' => 'TX', 'postal_code' => '75001', 'country' => 'US']));

        $path = ShippingLabel::generate($request, $labelTemplate);
        $pdf = Storage::disk('local')->get($path);
        Storage::disk('local')->delete($path);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="label-preview.pdf"']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'size' => ['required', Rule::in(LabelTemplate::SIZES)],
            'header_text' => ['nullable', 'string', 'max:80'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'footer_note' => ['nullable', 'string', 'max:300'],
            'show_items' => ['boolean'],
            'show_phone' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function save(LabelTemplate $template, array $data): LabelTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $template->fill($data)->save();
            if ($template->is_default) {
                LabelTemplate::whereKeyNot($template->id)->update(['is_default' => false]);
            } elseif (LabelTemplate::where('is_default', true)->doesntExist()) {
                LabelTemplate::where('is_active', true)->orderBy('id')->first()?->update(['is_default' => true]);
            }

            return $template->fresh();
        });
    }
}
