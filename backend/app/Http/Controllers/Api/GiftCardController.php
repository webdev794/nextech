<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GiftCardController extends Controller
{
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
