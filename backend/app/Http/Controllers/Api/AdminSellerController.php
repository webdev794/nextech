<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Shop;
use App\Support\SellerLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminSellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'suspended'])],
        ]);

        $sellers = Seller::query()
            ->with(['user:id,name,email', 'shop:id,seller_id,name,slug,is_active'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $sellers->map($this->row(...))->values()]);
    }

    public function show(Seller $seller): JsonResponse
    {
        $seller->load(['user:id,name,email,phone', 'shop', 'reviewer:id,name']);

        return response()->json(['data' => $this->row($seller, detailed: true)]);
    }

    /**
     * Lightweight approved-shops list for the admin Products form's Shop
     * picker. Kept as its own endpoint (rather than a query param on
     * index()) so that payload stays product-picker-shaped (id + name only)
     * while index() stays KYC-application-shaped.
     */
    public function shops(): JsonResponse
    {
        return response()->json([
            'data' => Shop::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function approve(Seller $seller): JsonResponse
    {
        abort_if($seller->status === 'approved', 422, 'Already approved.');

        DB::transaction(function () use ($seller): void {
            $seller->forceFill([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => request()->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $seller->shop?->forceFill(['is_active' => true])->save();
        });

        return response()->json(['data' => $this->row($seller->fresh()->load('shop'))]);
    }

    public function reject(Request $request, Seller $seller): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($seller, $data): void {
            $seller->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $data['reason'],
                'reviewed_by' => request()->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $seller->shop?->forceFill(['is_active' => false])->save();
        });

        return response()->json(['data' => $this->row($seller->fresh()->load('shop'))]);
    }

    public function suspend(Request $request, Seller $seller): JsonResponse
    {
        abort_unless($seller->status === 'approved', 422, 'Only an approved seller can be suspended.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($seller, $data): void {
            $seller->forceFill([
                'status' => 'suspended',
                'rejection_reason' => $data['reason'],
                'reviewed_by' => request()->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $seller->shop?->forceFill(['is_active' => false])->save();
        });

        return response()->json(['data' => $this->row($seller->fresh()->load('shop'))]);
    }

    /**
     * Record a manual payout (bank transfer etc, settled outside the app) as a
     * payout_debit ledger entry against the seller's shop.
     */
    public function payout(Request $request, Seller $seller): JsonResponse
    {
        $shop = $seller->shop;
        abort_unless($shop, 404, 'This seller has no shop.');

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $balance = $shop->balanceCents();
        if ($data['amount_cents'] > $balance) {
            return response()->json([
                'message' => "Payout can't exceed the seller's balance of $".number_format($balance / 100, 2).'.',
            ], 422);
        }

        SellerLedger::recordPayout($shop, $data['amount_cents'], $data['note'] ?? null, $request->user());

        return response()->json([
            'data' => $this->row($seller->fresh()->load(['user:id,name,email', 'shop', 'reviewer:id,name']), detailed: true),
        ]);
    }

    public function reinstate(Seller $seller): JsonResponse
    {
        abort_unless($seller->status === 'suspended', 422, 'Only a suspended seller can be reinstated.');

        DB::transaction(function () use ($seller): void {
            $seller->forceFill([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => request()->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $seller->shop?->forceFill(['is_active' => true])->save();
        });

        return response()->json(['data' => $this->row($seller->fresh()->load('shop'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Seller $seller, bool $detailed = false): array
    {
        $row = [
            'id' => $seller->id,
            'user' => $seller->relationLoaded('user') && $seller->user ? [
                'id' => $seller->user->id,
                'name' => $seller->user->name,
                'email' => $seller->user->email,
            ] : null,
            'country' => $seller->country,
            'business_type' => $seller->business_type,
            'company_name' => $seller->company_name,
            'status' => $seller->status,
            'rejection_reason' => $seller->rejection_reason,
            'submitted_at' => $seller->submitted_at,
            'reviewed_at' => $seller->reviewed_at,
            'shop' => $seller->relationLoaded('shop') && $seller->shop ? [
                'id' => $seller->shop->id,
                'name' => $seller->shop->name,
                'slug' => $seller->shop->slug,
                'is_active' => (bool) $seller->shop->is_active,
            ] : null,
        ];

        if ($detailed) {
            $row += [
                'tax_id' => $seller->tax_id,
                'registered_line1' => $seller->registered_line1,
                'registered_line2' => $seller->registered_line2,
                'registered_city' => $seller->registered_city,
                'registered_state' => $seller->registered_state,
                'registered_postal_code' => $seller->registered_postal_code,
                'registered_country' => $seller->registered_country,
                'contact_name' => $seller->contact_name,
                'id_type' => $seller->id_type,
                'id_number' => $seller->id_number,
                'date_of_birth' => $seller->date_of_birth,
                'id_document_path' => $seller->id_document_path,
                'business_document_path' => $seller->business_document_path,
                'reviewer' => $seller->reviewer ? ['id' => $seller->reviewer->id, 'name' => $seller->reviewer->name] : null,
            ];

            $shop = $seller->relationLoaded('shop') ? $seller->shop : null;
            $row['balance_cents'] = $shop ? $shop->balanceCents() : null;
            $row['ledger_entries'] = $shop
                ? $shop->ledgerEntries()->latest()->limit(20)->get(['id', 'shop_id', 'order_id', 'type', 'amount_cents', 'commission_cents', 'note', 'created_at'])
                : [];
        }

        return $row;
    }
}
