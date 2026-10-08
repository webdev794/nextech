<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin review of rider applications — approving one hires the rider at their chosen store. */
class AdminRiderApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'seller_accepted', 'approved', 'rejected'])],
        ]);

        $applications = RiderApplication::query()
            ->with(['user:id,name,email', 'store:id,name,city,shop_id', 'store.shop:id,name', 'reviewer:id,name'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // A seller's accepted applicant waiting for the final approval comes first.
            ->orderByRaw("status = 'seller_accepted' desc, status = 'pending' desc")
            ->latest()
            ->limit(200)
            ->get();

        return response()->json(['data' => $applications]);
    }

    public function approve(Request $request, RiderApplication $application): JsonResponse
    {
        abort_if($application->status === 'approved', 422, 'Already approved.');

        $data = $request->validate([
            // Admin may hire them at a different / additional store than requested.
            'store_ids' => ['sometimes', 'array', 'min:1'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ]);

        $recommended = $application->status === 'seller_accepted';
        DB::transaction(fn () => \App\Support\RiderHiring::hire($application, $request->user(), $data['store_ids'] ?? [], bySeller: $recommended));
        if ($recommended && ($seller = $application->store?->shop?->seller)) {
            \App\Support\SellerNotify::send($seller, $request->user(), 'Rider approved', "{$application->user?->name} is approved and now delivers for your store.");
        }

        return response()->json(['data' => $application->fresh(['user:id,name,email', 'store:id,name,city', 'reviewer:id,name'])]);
    }

    public function reject(Request $request, RiderApplication $application): JsonResponse
    {
        abort_unless(in_array($application->status, ['pending', 'seller_accepted'], true), 422, 'Only an application waiting for a decision can be rejected.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if ($application->status === 'seller_accepted' && ($seller = $application->store?->shop?->seller)) {
            \App\Support\SellerNotify::send($seller, $request->user(), 'Rider not approved', "{$application->user?->name} wasn't approved to deliver for your store: {$data['reason']}");
        }
        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $application->fresh(['user:id,name,email', 'store:id,name,city', 'reviewer:id,name'])]);
    }
}
