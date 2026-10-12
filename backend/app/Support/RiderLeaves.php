<?php

namespace App\Support;

use App\Models\RiderLeave;
use App\Models\Store;
use App\Models\User;
use App\Notifications\RiderNotice;

/**
 * Riders' day-off requests: the store they work for decides (the seller for a
 * seller's store, admin for NexTech's own). Only accepted days off count.
 * Once answered, a day off can't be taken back: the rider asks to move it to another
 * date and the store confirms or declines that. A declined request is removed once
 * the rider is told.
 */
final class RiderLeaves
{
    /** Waiting for an answer: new requests, and accepted days off the rider asks to move. */
    public static function waiting()
    {
        return RiderLeave::query()->where('kind', 'leave')->whereDate('date', '>=', now()->toDateString())
            ->where(fn ($q) => $q->where('status', 'requested')->orWhere(fn ($q) => $q->where('status', 'approved')->whereNotNull('new_date')));
    }

    /** Waiting requests for the riders of a store. */
    public static function waitingFor(Store $store)
    {
        return self::waiting()->whereIn('user_id', $store->riders()->pluck('users.id'))
            ->with('user:id,name')->orderBy('date')->get();
    }

    /** Does this store need riders on that date? (Its rider work days, and — for a seller — its shipping days and holidays.) */
    public static function storeWorks(Store $store, string $date): bool
    {
        $day = \Illuminate\Support\Carbon::parse($date)->startOfDay();
        $h = RiderWorkHours::of($store);
        if ($h && ! in_array($day->isoWeekday(), array_map('intval', $h['days']), true)) {
            return false;
        }

        return ! ($store->shop && ! SellerShipping::isWorkingDay($store->shop, $day));
    }

    /** Every store of the rider is closed that day: no day off needed (it would only use up the allowance). */
    public static function assertSomeStoreWorks(User $rider, string $date): void
    {
        $stores = RiderHiring::activeStores($rider);
        if ($stores->isNotEmpty() && $stores->every(fn ($s) => ! self::storeWorks($s, $date))) {
            $names = $stores->map(fn ($s) => $s->shop?->name ?? $s->name)->join(', ');
            abort(422, "{$names} ".($stores->count() === 1 ? 'is' : 'are').' closed on '.\Illuminate\Support\Carbon::parse($date)->format('D j M').' — you’re off that day already, no need to ask.');
        }
    }

    /** The rider asks to move an accepted day off (not passed yet) to another date. */
    public static function askToMove(RiderLeave $leave, User $rider, string $newDate, ?string $reason): void
    {
        abort_unless($leave->user_id === $rider->id && $leave->kind === 'leave', 404);
        abort_unless($leave->status === 'approved', 422, 'Only an accepted day off can be moved. A request still waiting can be taken back.');
        abort_unless($leave->date->toDateString() > now()->toDateString(), 422, 'That day off has already come — it can’t be moved now.');
        abort_if($leave->new_date !== null, 422, 'You already asked to move this day off — wait for the store’s answer.');
        abort_unless($newDate > now()->toDateString(), 422, 'Pick a date from tomorrow on.');
        abort_if($newDate === $leave->date->toDateString(), 422, 'That’s the same day.');
        abort_if(RiderLeave::query()->where('user_id', $rider->id)->whereDate('date', $newDate)->exists(), 422, 'You already have that day off.');
        self::assertSomeStoreWorks($rider, $newDate);
        $leave->update(['new_date' => $newDate, 'change_reason' => $reason]);
        foreach ($rider->stores()->with('shop.seller')->get() as $store) {
            RiderWorkHours::notifyStore($store, 'Day-off date change — please answer', "{$rider->name} asks to move their day off from {$leave->date->format('D j M')} to ".\Illuminate\Support\Carbon::parse($newDate)->format('D j M').($reason ? ": {$reason}" : '').'. Confirm or decline it in '.($store->shop_id ? 'Local delivery & riders' : 'the admin 🔔').'.');
        }
    }

    /** May this person decide this request? (The seller of one of the rider's stores, or admin.) */
    public static function mayDecide(RiderLeave $leave, User $by): bool
    {
        if ($by->is_admin) {
            return true;
        }
        $shopId = $by->seller?->shop?->id;

        return $shopId && $leave->user?->stores()->where('shop_id', $shopId)->exists();
    }

    public static function decide(RiderLeave $leave, User $by, bool $accept, ?string $note = null): void
    {
        abort_unless(self::mayDecide($leave, $by), 404);
        if ($leave->kind === 'leave' && $leave->status === 'approved' && $leave->new_date) {
            self::decideMove($leave, $by, $accept, $note);

            return;
        }
        abort_unless($leave->kind === 'leave' && $leave->status === 'requested', 422, 'This request has already been answered.');
        $day = $leave->date->format('D j M');
        $rider = $leave->user;
        if ($accept) {
            $leave->update(['status' => 'approved', 'decision_note' => $note, 'decided_by' => $by->id, 'decided_at' => now()]);
        } else {
            $leave->delete(); // the rider is told below; nothing is kept
        }
        self::tell($rider, $accept ? 'Day off accepted' : 'Day off declined', $accept
            ? "Your day off on {$day} is accepted."
            : "Your day off on {$day} wasn’t accepted".($note ? ": {$note}" : '.').' Please work your usual hours that day.');
    }

    /** The store confirms or declines moving an accepted day off to the new date. */
    private static function decideMove(RiderLeave $leave, User $by, bool $accept, ?string $note): void
    {
        $from = $leave->date->format('D j M');
        $to = $leave->new_date->format('D j M');
        if ($accept) {
            abort_unless($leave->date->toDateString() > now()->toDateString() && $leave->new_date->toDateString() > now()->toDateString(), 422, 'One of those days has already come — it can’t be moved now.');
            $leave->update(['date' => $leave->new_date, 'new_date' => null, 'change_reason' => null, 'decision_note' => $note, 'decided_by' => $by->id, 'decided_at' => now()]);
        } else {
            $leave->update(['new_date' => null, 'change_reason' => null]);
        }
        self::tell($leave->user, $accept ? 'Day off moved' : 'Day off not moved', $accept
            ? "Your day off is moved from {$from} to {$to}."
            : "Your day off stays on {$from} — moving it to {$to} wasn’t accepted".($note ? ": {$note}" : '.'));
    }

    private static function tell(?User $rider, string $subject, string $body): void
    {
        try {
            $rider?->notify(new RiderNotice($subject, $body));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
