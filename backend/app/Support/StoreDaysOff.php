<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Notifications\RiderNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Sellers' days off (weekends they don't ship, holidays, store days off) for
 * their local delivery: the evening before, admin and the store's riders are
 * told; that morning, riders with no other store open get the day off
 * (unavailable — they can still clock in if the seller asks them to work).
 */
final class StoreDaysOff
{
    /** Seller stores with local delivery that are closed on a day: [store, name of the day off]. */
    public static function closedOn(Carbon $day): array
    {
        $out = [];
        foreach (Store::query()->whereNotNull('shop_id')->where('local_delivery_active', true)->with('shop')->get() as $store) {
            if ($store->shop && ! SellerShipping::isWorkingDay($store->shop, $day)) {
                $out[] = [$store, SellerShipping::daysOff($store->shop, $day->copy()->subDay(), $day)[0] ?? $day->format('D j M')];
            }
        }

        return $out;
    }

    /** Evening: tell admin and the riders about stores closed tomorrow. */
    public static function warnTomorrow(): int
    {
        $closed = self::closedOn(now()->addDay()->startOfDay());
        foreach ($closed as [$store, $name]) {
            foreach ($store->riders()->get() as $rider) {
                self::tell($rider, 'No deliveries tomorrow', "{$store->shop->name} is closed tomorrow ({$name}) — no deliveries for them unless they ask you to work.", true);
            }
        }
        // Only weekday closures are news to admin (weekends are routine).
        $news = array_filter($closed, fn ($c) => ! now()->addDay()->isWeekend());
        if ($news !== []) {
            try {
                Notification::send(User::where('is_admin', true)->get(), new AdminNotice('Seller stores closed tomorrow', 'Closed tomorrow (no local delivery): '.collect($news)->map(fn ($c) => "{$c[0]->shop->name} — {$c[1]}")->join('; ').'.', true));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return count($closed);
    }

    /** Morning: riders whose every open store is closed today get the day off (not while clocked in). */
    public static function markRiders(): int
    {
        $today = now()->startOfDay();
        $closedIds = collect(self::closedOn($today))->mapWithKeys(fn ($c) => [$c[0]->id => $c[1]]);
        // Yesterday's automatic day off ends when a store of theirs is open again.
        User::query()->where('is_rider', true)->where('rider_available', false)->where('rider_unavailable_reason', 'like', 'Day off — %')->with('stores')->get()
            ->filter(fn ($r) => $r->stores->contains(fn ($s) => ! $closedIds->has($s->id)))
            ->each(fn ($r) => $r->forceFill(['rider_available' => true, 'rider_unavailable_reason' => null])->save());
        // Riders on a day off they asked for: off duty today.
        $onLeave = \App\Models\RiderLeave::query()->whereDate('date', $today->toDateString())->where('kind', 'leave')->pluck('user_id');
        User::query()->whereIn('id', $onLeave)->where('rider_available', true)->get()->each(function ($r) {
            if (! $r->currentShift()) {
                $r->forceFill(['rider_available' => false, 'rider_unavailable_reason' => 'Day off — leave'])->save();
            }
        });
        if ($closedIds->isEmpty()) {
            return 0;
        }
        $n = 0;
        $riders = User::query()->where('is_rider', true)->where('rider_is_active', true)->where('rider_available', true)
            ->whereHas('stores', fn ($q) => $q->whereIn('stores.id', $closedIds->keys()))->with('stores')->get();
        foreach ($riders as $rider) {
            $open = $rider->stores->filter(fn ($s) => ! $closedIds->has($s->id) && ($s->shop_id === null || $s->local_delivery_active));
            if ($open->isNotEmpty() || $rider->currentShift()) {
                continue;
            }
            $why = 'Day off — '.$rider->stores->filter(fn ($s) => $closedIds->has($s->id))->map(fn ($s) => ($s->shop?->name ?? $s->name).' closed ('.$closedIds[$s->id].')')->join(', ');
            $rider->forceFill(['rider_available' => false, 'rider_unavailable_reason' => mb_substr($why, 0, 190)])->save();
            $n++;
        }

        return $n;
    }

    private static function tell(User $rider, string $subject, string $body, bool $routine = false): void
    {
        try {
            $rider->notify(new RiderNotice($subject, $body, $routine));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
