<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ChatPage;
use App\Support\StaffChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A seller's or rider's own chat with NexTech (the docked chat window in
 * Seller Center / the rider console). The route picks the channel.
 */
class SellerChatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $channel = $this->channel($request);

        return response()->json(['data' => StaffChat::page($request->user()->id, $channel, $channel, $request->integer('before') ?: null)]);
    }

    public function store(Request $request): JsonResponse
    {
        $channel = $this->channel($request);
        $data = $request->validate(ChatPage::MESSAGE_RULES);
        StaffChat::post($request->user()->id, $request->user(), trim((string) ($data['body'] ?? '')), $channel, fromOwner: true, attachments: $data['attachments'] ?? []);

        return response()->json(['data' => StaffChat::page($request->user()->id, $channel, $channel)]);
    }

    private function channel(Request $request): string
    {
        return $request->is('api/rider/*') ? 'rider' : 'seller';
    }
}
