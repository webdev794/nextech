<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Trademark;
use App\Support\TrademarkChanges;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Performance -> Account health -> Trademarks: a seller registers the brands
 * they sell under; NexTech reviews each one (AdminTrademarkController) before
 * it can be chosen on a product. A logo can be added at any time.
 */
class SellerTrademarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Each approved trademark says whether buyers are still covered (so it can't be changed yet).
        $rows = $this->shop($request)->trademarks()->latest()->get()->map(fn (Trademark $t) => $t->toArray() + [
            'covered' => $t->status === 'approved' ? TrademarkChanges::covered($t) : [],
        ]);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('trademarks')->where('shop_id', $shop->id)],
            'registration_number' => ['required', 'string', 'max:60'],
            'registration_country' => ['required', 'string', 'size:2'],
            'certificate_path' => ['required', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ], ['certificate_path.required' => 'Upload the trademark registration certificate.']);

        $trademark = $shop->trademarks()->create($data + ['status' => 'pending']);

        return response()->json(['data' => $trademark], 201);
    }

    /**
     * Add or change the logo (any time). While a trademark is under review or
     * rejected, everything can be edited (it goes back for review); once it's
     * approved, its name and registration change only through a change request.
     */
    public function update(Request $request, Trademark $trademark): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($trademark->shop_id === $shop->id, 404);
        $data = $request->validate([
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('trademarks')->where('shop_id', $shop->id)->ignore($trademark->id)],
            'registration_number' => ['sometimes', 'string', 'max:60'],
            'registration_country' => ['sometimes', 'string', 'size:2'],
            'certificate_path' => ['sometimes', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
        ]);
        if ($trademark->status === 'approved' && array_intersect_key($data, array_flip(TrademarkChanges::FIELDS))) {
            abort(422, 'This trademark is approved — send a change request instead.');
        }

        // Changing the registration itself sends it back for review; a logo doesn't.
        $resubmit = (bool) array_intersect_key($data, array_flip(TrademarkChanges::FIELDS));
        $trademark->update($data + ($resubmit ? ['status' => 'pending', 'note' => null, 'reviewed_at' => null] : []));

        return response()->json(['data' => $trademark->fresh()]);
    }

    /**
     * Ask NexTech to change an approved trademark's name or registration. Not
     * while buyers of its products are still covered by returns / warranty.
     * Sending it again (e.g. with the documents admin asked for) replaces it.
     */
    public function changeRequest(Request $request, Trademark $trademark): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($trademark->shop_id === $shop->id, 404);
        abort_unless($trademark->status === 'approved', 422, 'Only an approved trademark needs a change request — edit it directly.');
        if ($covered = TrademarkChanges::covered($trademark)) {
            $list = collect($covered)->take(3)->map(fn ($c) => "“{$c['name']}” (until ".Carbon::parse($c['until'])->format('j M Y').')')->join(', ');
            abort(422, "Buyers are still covered by returns / warranty on products sold under this trademark: {$list}. It can be changed after ".Carbon::parse($covered[0]['until'])->format('j M Y').'.');
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('trademarks')->where('shop_id', $shop->id)->ignore($trademark->id)],
            'registration_number' => ['sometimes', 'string', 'max:60'],
            'registration_country' => ['sometimes', 'string', 'size:2'],
            'certificate_path' => ['sometimes', 'nullable', 'string', 'max:255', 'starts_with:kyc/'.$request->user()->id.'/'],
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Say why the trademark needs to change.']);
        $changes = array_filter(array_intersect_key($data, array_flip(TrademarkChanges::FIELDS)), fn ($v, $k) => $v !== null && $v !== '' && $v !== $trademark->{$k}, ARRAY_FILTER_USE_BOTH);
        abort_if($changes === [], 422, 'Nothing changed.');
        $needsCertificate = isset($changes['name']) || isset($changes['registration_number']) || isset($changes['registration_country']);
        abort_if($needsCertificate && empty($changes['certificate_path']), 422, 'Upload the registration certificate for the new details.');

        $trademark->forceFill([
            'change_request' => $changes + ['reason' => $data['reason'], 'requested_at' => now()->toIso8601String()],
            'change_status' => 'pending',
            'change_note' => null,
        ])->save();

        return response()->json(['data' => $trademark->fresh()]);
    }

    public function cancelChangeRequest(Request $request, Trademark $trademark): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($trademark->shop_id === $shop->id, 404);
        $trademark->forceFill(['change_request' => null, 'change_status' => null, 'change_note' => null])->save();

        return response()->json(['data' => $trademark->fresh()]);
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');

        return $seller->shop;
    }
}
