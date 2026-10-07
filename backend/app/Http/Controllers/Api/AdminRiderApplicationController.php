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
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $applications = RiderApplication::query()
            ->with(['user:id,name,email', 'store:id,name,city', 'reviewer:id,name'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("status = 'pending' desc")
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

        DB::transaction(function () use ($request, $application, $data): void {
            $user = $application->user;
            abort_if($user->is_admin, 422, 'That account is an administrator.');

            $user->forceFill([
                'is_rider' => true,
                'rider_is_active' => true,
                'rider_since' => $user->rider_since ?? now(),
                'phone' => $user->phone ?: $application->phone,
                'rider_base_address' => $user->rider_base_address ?: $application->home_address,
                'rider_base_lat' => $user->rider_base_lat ?? $application->home_lat,
                'rider_base_lng' => $user->rider_base_lng ?? $application->home_lng,
            ])->save();

            $storeIds = $data['store_ids'] ?? array_filter([$application->store_id]);
            abort_if($storeIds === [], 422, 'Pick a store for this rider.');
            $user->stores()->syncWithoutDetaching($storeIds);

            $application->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['data' => $application->fresh(['user:id,name,email', 'store:id,name,city', 'reviewer:id,name'])]);
    }

    public function reject(Request $request, RiderApplication $application): JsonResponse
    {
        abort_unless($application->status === 'pending', 422, 'Only a pending application can be rejected.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $application->fresh(['user:id,name,email', 'store:id,name,city', 'reviewer:id,name'])]);
    }
}
