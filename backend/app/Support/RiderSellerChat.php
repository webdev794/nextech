<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\Store;
use App\Models\SupportThread;
use App\Models\User;

/**
 * A rider and a seller they deliver for chat directly (a support thread linked to
 * the seller's shop). The store's team stays out until either side calls them in;
 * then it shows in admin Support like any chat.
 */
final class RiderSellerChat
{
    public static function thread(User $rider, Shop $shop): ?SupportThread
    {
        return SupportThread::query()->where('user_id', $rider->id)->where('seller_shop_id', $shop->id)->where('issue_type', 'rider_seller')->latest('id')->first();
    }

    public static function open(User $rider, Shop $shop): SupportThread
    {
        return self::thread($rider, $shop) ?? SupportThread::create(['user_id' => $rider->id, 'seller_shop_id' => $shop->id, 'issue_type' => 'rider_seller', 'status' => 'open']);
    }

    public static function messages(SupportThread $thread, User $rider): array
    {
        return $thread->messages()->where('internal', false)->orderBy('id')->get()->map(fn ($m) => [
            'id' => $m->id,
            'from' => $m->user_id === $rider->id ? 'me' : ($m->user_id === null ? 'system' : ($m->from_seller ? 'seller' : 'team')),
            'agent' => $m->agent_name,
            'body' => $m->body,
            'at' => $m->created_at,
        ])->all();
    }

    /**
     * Either side opens a support ticket: the support team reads the whole chat and
     * replies within 1–2 working days (shown as "{store} Support", never a person).
     */
    public static function openTicket(SupportThread $thread, User $by, string $side): void
    {
        abort_unless($thread->issue_type === 'rider_seller', 404);
        abort_if($thread->ticket_status === 'open', 422, 'A ticket is already open — support will reply within 1–2 working days.');
        $thread->forceFill(['admin_called_at' => $thread->admin_called_at ?? now(), 'ticket_by' => $side, 'ticket_status' => 'open', 'status' => 'open', 'resolved_at' => null])->save();
        // No email: the admin dashboard lists open tickets; a support person is assigned to answer.
        $thread->post(null, "{$by->name} opened a support ticket. Our support team will read the full chat and reply within 1–2 working days.", system: true);
    }

    /** The side that opened the ticket closes it without a decision. */
    public static function withdrawTicket(SupportThread $thread, User $by, string $side): void
    {
        abort_unless($thread->ticket_status === 'open' && $thread->ticket_by === $side, 422, 'Only the side that opened the ticket can close it.');
        $thread->forceFill(['ticket_status' => 'withdrawn'])->save();
        $thread->post(null, "{$by->name} closed the support ticket.", system: true);
    }

    /** Support names admin picks from (editable; replies show "Mak from {store} Support"). */
    public static function agents(): array
    {
        $saved = array_values(array_filter((array) \App\Models\Setting::get('support_agents', [])));

        return $saved ?: ['Mak', 'Terry', 'Priya', 'Sam', 'Alex'];
    }

    public static function label(?string $agent): string
    {
        return trim(($agent ? "{$agent} from " : '').Branding::name().' Support');
    }

    /** Admin assigns a support person to a ticket (they answer it; the chat shows their name). */
    public static function assign(SupportThread $thread, string $agent): void
    {
        $agent = trim($agent);
        abort_if($agent === '', 422, 'Pick a support name.');
        $thread->forceFill(['support_agent' => $agent])->save();
        if (! in_array($agent, self::agents(), true)) {
            \App\Models\Setting::put('support_agents', array_values(array_unique(array_merge(self::agents(), [$agent]))));
        }
        $thread->post(null, self::label($agent).($thread->issue_type === 'rider_seller' ? ' has picked up this ticket.' : ' has joined the chat.'), system: true);
    }

    /**
     * A support reply in any chat (buyer, seller, rider): shown under the assigned support
     * name, e.g. "Mak from {store} Support" — one is picked if none yet. Admin is never named.
     */
    public static function stampAgent(SupportThread $thread, \App\Models\SupportMessage $message): void
    {
        if ($message->user_id === null || $message->from_seller) {
            return;
        }
        if (! $thread->support_agent) {
            $thread->forceFill(['support_agent' => self::agents()[array_rand(self::agents())]])->save();
        }
        $message->forceFill(['agent_name' => $thread->support_agent])->save();
    }

    /** Support closes a ticket: resolved, or declined (too busy to take it up). */
    public static function supportCloses(SupportThread $thread, string $how): void
    {
        abort_unless($thread->ticket_status === 'open', 422, 'This ticket isn’t open.');
        $thread->forceFill(['ticket_status' => $how])->save();
        $who = self::label($thread->support_agent);
        $thread->post(null, $how === 'declined'
            ? "{$who}: we can’t take up this ticket right now. Please try to settle it between you — you can open a new ticket later if needed."
            : "{$who} closed this ticket.", system: true);
    }
}
