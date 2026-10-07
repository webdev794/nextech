<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LiveTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AfterShip's tracking webhook (set its URL to /api/webhooks/aftership in
 * AfterShip -> Settings -> Webhooks, and paste the webhook secret into
 * Secure access -> Courier). Moves the package's tracking along.
 */
class TrackingWebhookController extends Controller
{
    public function aftership(Request $request): JsonResponse
    {
        abort_unless(LiveTracking::validSignature($request->getContent(), $request->header('aftership-hmac-sha256')), 401, 'Bad signature.');

        $tracking = $request->input('msg') ?? $request->input('data.tracking') ?? $request->input('data');
        if (is_array($tracking)) {
            LiveTracking::fromWebhook($tracking);
        }

        return response()->json(['ok' => true]);
    }
}
