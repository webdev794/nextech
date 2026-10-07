<?php

namespace App\Support;

use App\Models\SupportThread;

/**
 * A support thread as a page of chat messages for the docked chat windows
 * (web/src/ChatDock.jsx), newest last. Pass `before` (a message id) for the
 * page of older ones. Each message says who sent it — system, nextech,
 * seller, rider or customer — and `mine` marks the viewer's own side.
 */
class ChatPage
{
    public const PAGE = 30;

    /** A message sent from a chat window: text, photos (POST /support/attachments), or both. */
    public const MESSAGE_RULES = [
        'body' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
        'attachments' => ['sometimes', 'array', 'max:4'],
        'attachments.*' => ['string', 'max:500', 'starts_with:/api/media/file/support/'],
    ];

    /**
     * @param  string  $viewer  admin | seller | customer | rider
     * @return array<string, mixed>
     */
    public static function of(?SupportThread $thread, string $viewer, ?int $before = null): array
    {
        $messages = collect();
        if ($thread) {
            $messages = $thread->messages()->with('sender:id,is_rider')->where('internal', false)
                ->when($viewer === 'seller', fn ($q) => $q->where('hidden_from_seller', false))
                ->when($before, fn ($q) => $q->where('id', '<', $before))
                ->reorder('id', 'desc')->limit(self::PAGE + 1)->get();
        }
        $type = (string) $thread?->issue_type;
        // In a seller's or rider's own channel with NexTech, every non-staff message is theirs.
        $owner = str_starts_with($type, 'seller_') ? 'seller' : (str_starts_with($type, 'rider_') ? 'rider' : 'customer');
        $mine = ['admin' => 'nextech', 'seller' => 'seller', 'customer' => 'customer', 'rider' => 'rider'][$viewer];
        // Sellers never see buyers' contact details (see Privacy).
        $redact = $viewer === 'seller' && $owner === 'customer';

        return [
            'thread_id' => $thread?->id,
            'status' => $thread?->status,
            'has_more' => $messages->count() > self::PAGE,
            'messages' => $messages->take(self::PAGE)->reverse()->map(function ($m) use ($owner, $mine, $redact) {
                $from = match (true) {
                    $m->user_id === null => 'system',
                    (bool) $m->from_seller => 'seller',
                    (bool) $m->sender?->is_rider => 'rider',
                    (bool) $m->is_staff => 'nextech',
                    default => $owner,
                };

                return [
                    'id' => $m->id,
                    'body' => $redact && $from !== 'seller' ? Privacy::redactContacts($m->body) : $m->body,
                    'attachments' => $m->attachments ?? [],
                    'created_at' => $m->created_at,
                    'from' => $from,
                    'mine' => $from === $mine,
                ];
            })->values(),
        ];
    }
}
