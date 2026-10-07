<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PolicyAcceptance;
use App\Models\Seller;
use App\Support\SellerPolicies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Seller Center → Policies & rules: which policies need accepting, and signing one. */
class SellerPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => SellerPolicies::status($this->seller($request))]);
    }

    /** Accept a policy: read to the end, tick, and sign with the seller's name (kept with the date, IP and text version). */
    public function accept(Request $request, string $slug): JsonResponse
    {
        $seller = $this->seller($request);
        $page = Page::query()->published()->where('slug', $slug)->whereIn('acceptance_for', SellerPolicies::FOR)->firstOrFail();
        $data = $request->validate([
            'agree' => ['required', 'accepted'],
            'signed_name' => ['required', 'string', 'min:3', 'max:160'],
        ], ['agree.accepted' => 'Tick that you have read and accept it.', 'signed_name.required' => 'Type your full name to sign.']);

        PolicyAcceptance::create([
            'seller_id' => $seller->id,
            'page_id' => $page->id,
            'page_version' => SellerPolicies::version($page),
            'signed_name' => trim($data['signed_name']),
            'ip' => $request->ip(),
            'accepted_at' => now(),
        ]);

        return response()->json(['data' => SellerPolicies::status($seller)]);
    }

    private function seller(Request $request): Seller
    {
        $seller = $request->user()->seller;
        abort_unless($seller, 404, 'No seller profile.');

        return $seller;
    }
}
