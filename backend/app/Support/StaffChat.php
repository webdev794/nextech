<?php

namespace App\Support;

use App\Models\SupportThread;
use App\Models\User;

/**
 * A seller's or rider's own chat with NexTech: their latest thread of that
 * channel's issue types, shown in the docked chat windows on both sides.
 */
class StaffChat
{
    public const TYPES = [
        'seller' => ['seller_product_issue', 'seller_other'],
        'rider' => ['rider_support'],
    ];

    /** The open thread new messages go to (see post()), else the latest one. */
    public static function thread(int $userId, string $channel): ?SupportThread
    {
        return SupportThread::where('user_id', $userId)->whereIn('issue_type', self::TYPES[$channel])
            ->orderByRaw("status = 'open' desc")->orderByDesc('id')->first();
    }

    /**
     * Posts to the open thread, starting one when there is none.
     *
     * @param  list<string>  $attachments
     */
    public static function post(int $ownerId, User $sender, string $body, string $channel, bool $fromOwner, array $attachments = []): SupportThread
    {
        $thread = SupportThread::where('user_id', $ownerId)->whereIn('issue_type', self::TYPES[$channel])->where('status', 'open')->orderByDesc('id')->first()
            ?? SupportThread::create(['user_id' => $ownerId, 'issue_type' => $channel === 'rider' ? 'rider_support' : ($fromOwner ? 'seller_other' : 'seller_product_issue'), 'status' => 'open']);

        $thread->post($sender, $body, isStaff: ! $fromOwner, fromSeller: $fromOwner && $channel === 'seller', attachments: $attachments);

        return $thread;
    }

    /** @return array<string, mixed> */
    public static function page(int $ownerId, string $channel, string $viewer, ?int $before = null): array
    {
        return ChatPage::of(self::thread($ownerId, $channel), $viewer, $before);
    }
}
