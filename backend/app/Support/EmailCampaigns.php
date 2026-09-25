<?php

namespace App\Support;

use App\Models\EmailCampaign;
use App\Models\User;
use App\Notifications\CustomEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Admin emails: who a campaign goes to (its audience), sending it, and moving
 * a repeating one to its next date. Used by the admin "Send now" action and
 * the every-minute `emails:send-scheduled` command.
 */
class EmailCampaigns
{
    /**
     * Customers matching an audience:
     *   {"user_id": 12}                       one customer
     *   {"market": "IN"}                      customers who ordered in that country
     *   {"has_ordered": true|false}           ordered at least once / never
     *   {"no_order_in_days": 30}              no order in the last N days
     * Promotional sends skip customers who unsubscribed.
     */
    public static function audience(array $audience, bool $promotional): Builder
    {
        $query = User::query()->where('is_admin', false)->whereNotNull('email');

        if (! empty($audience['user_id'])) {
            return $query->whereKey((int) $audience['user_id'])
                ->when($promotional, fn ($q) => $q->where('marketing_opt_out', false));
        }

        if (! empty($audience['market'])) {
            $query->whereHas('orders', fn ($q) => $q->where('market', strtoupper($audience['market'])));
        }
        if (array_key_exists('has_ordered', $audience) && $audience['has_ordered'] !== null && $audience['has_ordered'] !== '') {
            $audience['has_ordered'] ? $query->has('orders') : $query->doesntHave('orders');
        }
        if (! empty($audience['no_order_in_days'])) {
            $since = now()->subDays((int) $audience['no_order_in_days']);
            $query->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', $since));
        }

        return $query->when($promotional, fn ($q) => $q->where('marketing_opt_out', false));
    }

    /** Send a campaign to its audience now; a repeating one is rescheduled. Returns how many were sent. */
    public static function run(EmailCampaign $campaign): int
    {
        $campaign->update(['status' => 'sending']);
        $sent = 0;

        self::audience((array) $campaign->audience, $campaign->promotional)
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($campaign, &$sent): void {
                foreach ($users as $user) {
                    $toOne = ! empty(($campaign->audience ?? [])['user_id']);
                    if (CustomerMail::send($user, new CustomEmail($campaign->subject, $campaign->body, $campaign->id, $campaign->promotional, $toOne))) {
                        $sent++;
                    }
                }
            });

        $next = self::nextRun($campaign);
        $campaign->update([
            'status' => $next ? 'scheduled' : 'sent',
            'send_at' => $next ?? $campaign->send_at,
            'last_run_at' => now(),
            'sent_count' => $campaign->sent_count + $sent,
        ]);

        return $sent;
    }

    /** Next date for a repeating campaign (always in the future), or null. */
    public static function nextRun(EmailCampaign $campaign): ?Carbon
    {
        if ($campaign->repeat === 'none') {
            return null;
        }
        $next = ($campaign->send_at ?? now())->copy();
        do {
            $next = match ($campaign->repeat) {
                'daily' => $next->addDay(),
                'weekly' => $next->addWeek(),
                'monthly' => $next->addMonthNoOverflow(),
            };
        } while ($next->lte(now()));

        return $next;
    }

    /** Campaigns due now. */
    public static function due()
    {
        return EmailCampaign::where('status', 'scheduled')->where('send_at', '<=', now())->orderBy('send_at')->get();
    }
}
