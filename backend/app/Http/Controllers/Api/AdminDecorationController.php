<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreDecoration;
use App\Support\Market;
use App\Support\StoreDecorations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Spot checks of store decorations: versions picked for review wait here
 * until NexTech approves them (the seller can then publish) or rejects them
 * with a reason. Live designs can be taken down the same way.
 */
class AdminDecorationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['sometimes', Rule::in(['in_review', 'live', 'rejected', 'all'])]])['status'] ?? 'in_review';

        $rows = StoreDecoration::query()
            ->with('shop')
            ->whereHas('shop', fn ($q) => $q->where('market', Market::fromRequest($request)))
            ->when($status === 'live', fn ($q) => $q->where('is_live', true))
            ->when(in_array($status, ['in_review', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest('submitted_at')
            ->limit(100)
            ->get()
            ->map(fn (StoreDecoration $d) => $d->only(['id', 'platform', 'name', 'status', 'is_live', 'review_note', 'submitted_at', 'reviewed_at']) + [
                'shop' => $d->shop->only(['id', 'name', 'slug', 'logo_url']),
                'preview' => StoreDecorations::resolve($d, $d->shop),
            ]);

        return response()->json(['data' => $rows, 'settings' => ['min_products' => StoreDecorations::minProducts(), 'spot_check_rate' => StoreDecorations::spotCheckRate()]]);
    }

    public function review(Request $request, StoreDecoration $decoration): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ], ['note.required_if' => 'Tell the seller what to change.']);

        $approve = $data['decision'] === 'approve';
        $decoration->update([
            'status' => $approve ? 'approved' : 'rejected',
            'review_note' => $approve ? null : $data['note'],
            'reviewed_at' => now(),
        ] + ($approve ? [] : ['is_live' => false]));

        return response()->json(['data' => $decoration->fresh()]);
    }
}
