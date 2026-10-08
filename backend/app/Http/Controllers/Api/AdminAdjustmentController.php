<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderLedgerEntry;
use App\Models\Seller;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Notifications\RiderNotice;
use App\Support\Branding;
use App\Support\Market;
use App\Support\Money;
use App\Support\SecureAccess;
use App\Support\SellerNotify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Secure access → Payouts → Adjust a balance: admin corrects money by hand
 * before final payments (e.g. when the system or a server missed something) —
 * a credit or a charge on a seller's or rider's ledger, with a reason they see.
 */
class AdminAdjustmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        SecureAccess::assert($request);
        $data = $request->validate([
            'target' => ['required', Rule::in(['seller', 'rider'])],
            'id' => ['required', 'integer'],
            // Positive = credit (they get more), negative = charge.
            'amount_cents' => ['required', 'integer', 'not_in:0', 'min:-100000000', 'max:100000000'],
            'reason' => ['required', 'string', 'max:300'],
        ]);
        $note = 'Adjustment by '.Branding::name().': '.trim($data['reason']);

        if ($data['target'] === 'seller') {
            $seller = Seller::with('shop')->findOrFail($data['id']);
            abort_unless($seller->shop, 422, 'This seller has no shop.');
            $entry = SellerLedgerEntry::create(['shop_id' => $seller->shop->id, 'order_id' => null, 'type' => 'adjustment', 'amount_cents' => $data['amount_cents'], 'note' => $note, 'created_by' => $request->user()->id]);
            $amount = Money::format(abs($data['amount_cents']), Market::currency($seller->shop->market));
            SellerNotify::send($seller, $request->user(), $data['amount_cents'] > 0 ? 'Balance credited' : 'Balance charged',
                ($data['amount_cents'] > 0 ? "We added {$amount} to your balance" : "We took {$amount} from your balance").": {$data['reason']} It shows under Finances.");
        } else {
            $rider = User::query()->where('is_rider', true)->findOrFail($data['id']);
            $entry = RiderLedgerEntry::create(['user_id' => $rider->id, 'order_id' => null, 'type' => 'adjustment', 'amount_cents' => $data['amount_cents'], 'note' => $note, 'created_by' => $request->user()->id]);
            $amount = Money::format(abs($data['amount_cents']), Market::currency(\App\Support\RiderLedger::marketFor($rider)));
            try {
                $rider->notify(new RiderNotice($data['amount_cents'] > 0 ? 'Earnings credited' : 'Earnings charged',
                    ($data['amount_cents'] > 0 ? "We added {$amount} to your earnings" : "We took {$amount} from your earnings").": {$data['reason']}"));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['data' => $entry], 201);
    }
}
