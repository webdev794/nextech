<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Support\SellerLedger;
use App\Support\SellerOnboarding;
use App\Support\Market;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminSellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'needs_changes', 'approved', 'rejected', 'suspended'])],
        ]);

        $market = Market::fromRequest($request);
        $others = array_values(array_diff(array_keys(config('markets', [])), [Market::home()]));
        $sellers = Seller::query()
            ->with(['user:id,name,email', 'shop:id,seller_id,name,slug,is_active,market'])
            ->where(fn ($q) => $q->whereHas('shop', fn ($shop) => $shop->where('market', $market))
                ->orWhere(fn ($q) => $q->doesntHave('shop')->where(fn ($c) => $market === Market::home()
                    ? $c->whereNotIn('country', $others)->orWhereNull('country')
                    : $c->where('country', $market))))
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
    public function shops(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Shop::query()->where('is_active', true)->where('market', Market::fromRequest($request))->orderBy('name')->get(['id', 'name']),
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

        // Only earnings past their return window can be paid out.
        $cur = Market::currency($shop->market);
        $balance = max(0, SellerLedger::availableCents($shop));
        if ($data['amount_cents'] > $balance) {
            return response()->json([
                'message' => "Payout can't exceed the seller's available balance of ".Money::format($balance, $cur).' — the rest is still inside the return window.',
            ], 422);
        }

        $minPayout = SellerLedger::minPayoutCents($shop->market);
        if ($balance < $minPayout) {
            return response()->json([
                'message' => 'Balance must reach '.Money::format($minPayout, $cur).' before a payout can be recorded (currently '.Money::format($balance, $cur).').',
            ], 422);
        }

        $maxPayout = SellerLedger::maxPayoutCents($shop->market);
        if ($maxPayout > 0 && $data['amount_cents'] > $maxPayout) {
            return response()->json([
                'message' => 'A single payout can be at most '.Money::format($maxPayout, $cur).' — pay the rest in another transfer.',
            ], 422);
        }

        // One payout at a time platform-wide, so two admins paying different
        // sellers can't both squeeze under the daily cap at the same moment.
        Cache::lock('seller-payouts', 10)->block(5, function () use ($shop, $data, $request, $cur): void {
            $remaining = SellerLedger::dailyPayoutRemainingCents($shop->market);
            if ($remaining !== null && $data['amount_cents'] > $remaining) {
                abort(422, "That would go over today's payout cap across all sellers — ".Money::format($remaining, $cur).' left today.');
            }

            DB::transaction(function () use ($shop, $data, $request): void {
                $entry = SellerLedger::recordPayout($shop, $data['amount_cents'], $data['note'] ?? null, $request->user());

                // Paying out settles the seller's open request, if any.
                $shop->payoutRequests()->where('status', 'pending')->update([
                    'status' => 'paid',
                    'ledger_entry_id' => $entry->id,
                    'processed_by' => $request->user()->id,
                    'processed_at' => now(),
                ]);
            });
        });

        return response()->json([
            'data' => $this->row($seller->fresh()->load(['user:id,name,email', 'shop', 'reviewer:id,name']), detailed: true),
        ]);
    }

    /** Decline a seller's open payout request (e.g. refunds still pending), with a reason they'll see. */
    public function rejectPayoutRequest(Request $request, Seller $seller): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $payoutRequest = $seller->shop?->payoutRequests()->where('status', 'pending')->first();
        abort_unless($payoutRequest, 404, 'No open payout request.');

        $payoutRequest->update([
            'status' => 'rejected',
            'admin_note' => $data['note'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return response()->json([
            'data' => $this->row($seller->fresh()->load(['user:id,name,email', 'shop', 'reviewer:id,name']), detailed: true),
        ]);
    }

    /**
     * Admin-originated message to a seller — posts into the same
     * seller_product_issue/seller_other thread the seller sees and can
     * reply to from their own Seller Center (SupportThreadController, same
     * /support/threads endpoints, scoped to their user_id).
     */
    public function message(Request $request, Seller $seller): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $thread = $this->postToSellerThread($seller, $request->user(), $data['body']);

        return response()->json(['data' => $thread->fresh()]);
    }

    /**
     * Sends the application back to the seller for edits — sets status to
     * needs_changes (which unlocks SellerController::apply() for a
     * resubmission against this same row) and posts the reason into the
     * seller's message thread so it isn't just a status change with no
     * explanation of what to fix.
     */
    public function requestChanges(Request $request, Seller $seller): JsonResponse
    {
        abort_unless(in_array($seller->status, ['pending', 'rejected'], true), 422, 'Changes can only be requested on a pending or rejected application.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($seller, $data, $request): void {
            $seller->forceFill([
                'status' => 'needs_changes',
                'rejection_reason' => $data['reason'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $this->postToSellerThread($seller, $request->user(), $data['reason']);
        });

        return response()->json(['data' => $this->row($seller->fresh()->load('shop'))]);
    }

    /**
     * Approve or send back an onboarding task (tax information, compliance
     * information, bank account). A rejection needs a reason, which the
     * seller sees on the task and gets as a message.
     */
    public function reviewOnboarding(Request $request, Seller $seller, string $task): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ], ['note.required_if' => 'Say what the seller needs to fix.']);

        $current = $seller->{$task.'_status'};
        abort_unless(in_array($current, $task === 'bank' ? ['processing', 'linked', 'failed'] : ['pending', 'approved', 'rejected'], true), 422, 'The seller hasn’t submitted this yet.');

        $approve = $data['decision'] === 'approve';
        $status = $task === 'bank' ? ($approve ? 'linked' : 'failed') : ($approve ? 'approved' : 'rejected');
        $seller->forceFill([
            $task.'_status' => $status,
            $task.'_note' => $approve ? null : $data['note'],
        ] + ($task === 'bank' ? ['bank_verified_at' => $approve ? now() : null] : []))->save();

        if (! $approve) {
            $label = ['tax' => 'tax information', 'compliance' => 'additional compliance information', 'bank' => 'bank account'][$task];
            $this->postToSellerThread($seller, $request->user(), "Your $label needs another look: {$data['note']} Update it from the Seller Center homepage.");
        }

        return response()->json(['data' => $this->row($seller->fresh()->load(['user:id,name,email,phone', 'shop', 'reviewer:id,name']), detailed: true)]);
    }

    /**
     * Finds or creates an open seller<->admin thread for this seller and
     * posts a staff message into it — mirrors RiderController::threadFor().
     */
    private function postToSellerThread(Seller $seller, User $sender, string $body): SupportThread
    {
        $thread = SupportThread::where('user_id', $seller->user_id)
            ->whereIn('issue_type', ['seller_product_issue', 'seller_other'])
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();

        if (! $thread) {
            $thread = SupportThread::create([
                'user_id' => $seller->user_id,
                'issue_type' => 'seller_product_issue',
                'status' => 'open',
            ]);
        }

        $thread->post($sender, $body, isStaff: true);

        return $thread;
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
            'tax_status' => $seller->tax_status,
            'compliance_status' => $seller->compliance_status,
            'bank_status' => $seller->bank_status,
            // Onboarding tasks waiting for NexTech's review.
            'reviews_pending' => (int) ($seller->tax_status === 'pending') + (int) ($seller->compliance_status === 'pending') + (int) ($seller->bank_status === 'processing'),
            'last_message' => $this->lastMessage($seller),
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
                'pickup_same_as_registered' => (bool) $seller->pickup_same_as_registered,
                'pickup_phone' => $seller->pickup_phone,
                'pickup_line1' => $seller->pickup_line1,
                'pickup_line2' => $seller->pickup_line2,
                'pickup_city' => $seller->pickup_city,
                'pickup_state' => $seller->pickup_state,
                'pickup_postal_code' => $seller->pickup_postal_code,
                'pickup_country' => $seller->pickup_country,
                'contact_name' => $seller->contact_name,
                'id_type' => $seller->id_type,
                'id_number' => $seller->id_number,
                'date_of_birth' => $seller->date_of_birth,
                'id_document_path' => $seller->id_document_path,
                'business_document_path' => $seller->business_document_path,
                'reviewer' => $seller->reviewer ? ['id' => $seller->reviewer->id, 'name' => $seller->reviewer->name] : null,
                'payout_method' => $seller->payout_method,
                'payout_details' => $seller->payout_details,
                'tax_info' => $seller->tax_info,
                'tax_note' => $seller->tax_note,
                'tax_submitted_at' => $seller->tax_submitted_at,
                'tax_codes' => SellerOnboarding::config($seller)['tax_codes'] ?? [],
                'corporate_document_types' => SellerOnboarding::config($seller)['corporate_documents'] ?? [],
                'compliance' => $seller->compliance,
                'compliance_note' => $seller->compliance_note,
                'compliance_submitted_at' => $seller->compliance_submitted_at,
                'bank_note' => $seller->bank_note,
                'bank_submitted_at' => $seller->bank_submitted_at,
                'bank_verified_at' => $seller->bank_verified_at,
            ];

            $shop = $seller->relationLoaded('shop') ? $seller->shop : null;
            $row['balance_cents'] = $shop ? $shop->balanceCents() : null;
            if ($shop) {
                $split = SellerLedger::breakdown($shop);
                $row['available_cents'] = $split['available_cents'];
                $row['pending_cents'] = $split['pending_cents'];
                $row['pending_orders'] = $split['pending'];
            }
            $row['ledger_entries'] = $shop
                ? $shop->ledgerEntries()->latest()->limit(20)->get(['id', 'shop_id', 'order_id', 'type', 'amount_cents', 'commission_cents', 'note', 'created_at'])
                : [];
            $row['min_payout_cents'] = SellerLedger::minPayoutCents($shop?->market);
            $row['max_payout_cents'] = SellerLedger::maxPayoutCents($shop?->market);
            $row['daily_payout_remaining_cents'] = SellerLedger::dailyPayoutRemainingCents($shop?->market);
            $row['currency'] = Market::currency($shop?->market);
            $row['pending_payout_request'] = $shop?->payoutRequests()->where('status', 'pending')->first();
        }

        return $row;
    }

    /**
     * The newest non-internal message in the seller's admin thread, so the
     * list/drawer can show what was last asked without opening the separate
     * Support inbox. Null once no message has ever been exchanged.
     */
    private function lastMessage(Seller $seller): ?array
    {
        $message = SupportMessage::whereHas('thread', function ($query) use ($seller): void {
            $query->where('user_id', $seller->user_id)->whereIn('issue_type', ['seller_product_issue', 'seller_other']);
        })->where('internal', false)->latest()->first();

        if (! $message) {
            return null;
        }

        return [
            'body' => $message->body,
            'is_staff' => (bool) $message->is_staff,
            'created_at' => $message->created_at,
        ];
    }
}
