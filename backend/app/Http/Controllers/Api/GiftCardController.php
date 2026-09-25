<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GiftCardController extends Controller
{
    /**
     * The customer's store credit (Account → Credit balance): their gift
     * cards with what's left on each. The password is never returned.
     */
    public function index(Request $request): JsonResponse
    {
        $cards = GiftCard::where('user_id', $request->user()->id)
            ->latest('id')
            ->get(['id', 'code', 'initial_cents', 'balance_cents', 'is_active', 'order_id', 'created_at'])
            ->map(fn (GiftCard $card) => [
                'id' => $card->id,
                'code' => $card->code,
                'initial_cents' => (int) $card->initial_cents,
                'balance_cents' => (int) $card->balance_cents,
                'spendable' => $card->isSpendable(),
                'order_id' => $card->order_id,
                'created_at' => $card->created_at,
            ]);

        return response()->json(['data' => [
            'cards' => $cards,
            'total_cents' => (int) $cards->where('spendable', true)->sum('balance_cents'),
        ]]);
    }

    /** Check a code + password before checkout and show the balance. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'pin' => ['required', 'string', 'max:64'],
        ]);

        $card = GiftCard::where('code', strtoupper(trim($data['code'])))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $card || ! Hash::check($data['pin'], $card->pin_hash) || ! $card->isSpendable()) {
            return response()->json(
                ['message' => 'That gift card and password don\'t match, or it has no balance left.'],
                422,
            );
        }

        return response()->json(['data' => [
            'code' => $card->code,
            'balance_cents' => $card->balance_cents,
        ]]);
    }
}
