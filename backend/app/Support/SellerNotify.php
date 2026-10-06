<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\SellerNotice;

/**
 * Tells a seller about an admin decision on something they submitted: posted
 * in their Seller Center Messages (where they can reply to NexTech) and
 * emailed. Never blocks the decision itself if the email fails.
 */
class SellerNotify
{
    public static function send(Seller $seller, User $sender, string $subject, string $body): void
    {
        self::thread($seller)->post($sender, $body, isStaff: true);
        try {
            $seller->user?->notify(new SellerNotice($subject, $body));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The seller's open thread with NexTech (created when there's none). */
    public static function thread(Seller $seller): SupportThread
    {
        return SupportThread::where('user_id', $seller->user_id)
            ->whereIn('issue_type', ['seller_product_issue', 'seller_other'])
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first()
            ?? SupportThread::create(['user_id' => $seller->user_id, 'issue_type' => 'seller_product_issue', 'status' => 'open']);
    }
}
