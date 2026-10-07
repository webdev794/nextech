<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderLedgerEntry;
use App\Models\RiderPayoutRequest;
use App\Models\User;
use App\Support\Market;
use App\Support\Money;
use App\Support\RiderLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The rider's own pay: earnings ledger, payout method, and payout requests. */
class RiderEarningsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $rider = $request->user();
        $market = RiderLedger::marketFor($rider);

        return response()->json(['data' => [
            'currency' => Market::currency($market),
            'balance_cents' => RiderLedger::balanceCents($rider),
            'cash_holding_cents' => $rider->codHoldingCents(),
            'owed_cents' => RiderLedger::owedCents($rider),
            'requestable_cents' => RiderLedger::requestableCents($rider),
            'earned_week_cents' => (int) RiderLedgerEntry::where('user_id', $rider->id)
                ->where('type', 'delivery_credit')
                ->where('created_at', '>=', now()->subDays(7))
                ->sum('amount_cents'),
            'rates' => [
                'base_cents' => RiderLedger::baseCents($market),
                'per_mile_cents' => RiderLedger::perMileCents($market),
            ],
            'min_payout_cents' => RiderLedger::minPayoutCents($market),
            'max_payout_cents' => RiderLedger::maxPayoutCents($market),
            'payout_method' => $rider->rider_payout_method,
            'payout_details' => $rider->rider_payout_details,
            'last_payout_request' => RiderPayoutRequest::where('user_id', $rider->id)->latest('id')->first(),
            'entries' => RiderLedgerEntry::where('user_id', $rider->id)
                ->latest('id')
                ->limit(30)
                ->get(['id', 'order_id', 'type', 'amount_cents', 'distance_miles', 'note', 'created_at']),
        ]]);
    }

    public function payoutMethod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payout_method' => ['required', Rule::in(['bank', 'paypal'])],
            'holder_name' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:160'],
            'account_number' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:60'],
            'routing_number' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:60'],
            'bank_name' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:160'],
            'email' => ['required_if:payout_method,paypal', 'nullable', 'email', 'max:160'],
        ]);

        $details = $data['payout_method'] === 'bank'
            ? array_intersect_key($data, array_flip(['holder_name', 'account_number', 'routing_number', 'bank_name']))
            : ['email' => $data['email']];

        $request->user()->forceFill([
            'rider_payout_method' => $data['payout_method'],
            'rider_payout_details' => $details,
        ])->save();

        return $this->show($request);
    }

    /**
     * One open request at a time, for what the rider is owed (earnings minus
     * COD cash still held), capped at the per-payout maximum.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider->rider_payout_method, 422, 'Add your payout method (bank or PayPal) first.');

        DB::transaction(function () use ($rider): void {
            User::whereKey($rider->id)->lockForUpdate()->first();
            abort_if(
                RiderPayoutRequest::where('user_id', $rider->id)->where('status', 'pending')->exists(),
                422,
                'You already have a payout request waiting.'
            );

            $market = RiderLedger::marketFor($rider);
            $cur = Market::currency($market);
            $min = RiderLedger::minPayoutCents($market);
            $owed = RiderLedger::owedCents($rider);
            if ($owed < $min) {
                $held = $rider->codHoldingCents();
                abort(422, 'You can request a payout once you are owed '.Money::format($min, $cur)
                    .($held > 0 ? ' — return the '.Money::format($held, $cur).' cash you are holding to your store first.' : '.'));
            }

            RiderPayoutRequest::create([
                'user_id' => $rider->id,
                'amount_cents' => RiderLedger::requestableCents($rider),
                'status' => 'pending',
            ]);
        });

        return $this->show($request);
    }
}
