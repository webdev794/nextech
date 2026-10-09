<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Notifications\RiderNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * The hours a store's riders must be on duty (set by the seller for their
 * store, by admin for NexTech's own stores). When hours start, riders who
 * haven't clocked in are reminded; 30 minutes in, the store is told who is
 * missing; clocking out before the end is reported too. Store days off and
 * riders' days off don't count.
 */
final class RiderWorkHours
{
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** @return array{days: array<int>, start: string, end: string}|null */
    public static function of(Store $store): ?array
    {
        $h = $store->rider_hours;

        return is_array($h) && ! empty($h['days']) && ! empty($h['start']) && ! empty($h['end']) ? $h : null;
    }

    /** "Mon–Sat 09:00–18:00" style text. */
    public static function label(?array $h): ?string
    {
        if (! $h) {
            return null;
        }
        $days = collect($h['days'])->sort()->values();
        $text = $days->count() > 2 && $days->last() - $days->first() === $days->count() - 1
            ? self::DAYS[$days->first()].'–'.self::DAYS[$days->last()]
            : $days->map(fn ($d) => self::DAYS[$d])->join(', ');

        return "{$text} {$h['start']}–{$h['end']}";
    }

    /** Is it the store's working time right now (and the store open today)? */
    public static function isWorkTime(Store $store, ?Carbon $at = null): bool
    {
        $h = self::of($store);
        $at ??= now();
        if (! $h || ! in_array($at->isoWeekday(), array_map('intval', $h['days']), true)) {
            return false;
        }
        if ($store->shop && ! SellerShipping::isWorkingDay($store->shop, $at->copy()->startOfDay())) {
            return false; // the store's day off
        }
        $t = $at->format('H:i');

        return $t >= $h['start'] && $t < $h['end'];
    }

    /** Every 15 minutes: remind riders who aren't on duty, and tell the store who is missing. */
    public static function check(): int
    {
        $n = 0;
        foreach (Store::query()->whereNotNull('rider_hours')->with('shop.seller')->get() as $store) {
            $h = self::of($store);
            if (! $h || ! self::isWorkTime($store)) {
                continue;
            }
            $started = now()->copy()->setTimeFromTimeString($h['start']);
            $missing = $store->riders()->where('is_rider', true)->where('rider_is_active', true)->get()
                ->filter(fn (User $r) => ! $r->currentShift() && ! str_starts_with((string) $r->rider_unavailable_reason, 'Day off'));
            $today = now()->toDateString();
            foreach ($missing as $rider) {
                if (Cache::add("rider-hours-remind-{$store->id}-{$rider->id}-{$today}", 1, now()->addDay())) {
                    self::tell($rider, 'Time to clock in', ($store->shop?->name ?? $store->name)." works {$h['start']}–{$h['end']} today. Please clock in in the Rider app.");
                    $n++;
                }
            }
            if ($missing->isNotEmpty() && now()->gte($started->copy()->addMinutes(30)) && Cache::add("rider-hours-missing-{$store->id}-{$today}", 1, now()->addDay())) {
                self::tellStore($store, 'Riders not on duty', 'Not clocked in at '.($store->shop?->name ?? $store->name).' (hours '.self::label($h).'): '.$missing->pluck('name')->join(', ').'.');
            }
        }

        return $n;
    }

    /** A rider clocking out during a store's hours: the store is told. */
    public static function clockedOut(User $rider): void
    {
        foreach ($rider->stores()->with('shop.seller')->get() as $store) {
            if (self::isWorkTime($store)) {
                self::tellStore($store, 'A rider left early', "{$rider->name} clocked out at ".now()->format('H:i').', before the end of hours ('.self::of($store)['end'].').');
            }
        }
    }

    private static function tellStore(Store $store, string $subject, string $body): void
    {
        try {
            if ($store->shop?->seller) {
                SellerNotify::send($store->shop->seller, $store->shop->seller->user, $subject, $body);
            } else {
                Notification::send(User::where('is_admin', true)->get(), new AdminNotice($subject, $body));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function tell(User $rider, string $subject, string $body): void
    {
        try {
            $rider->notify(new RiderNotice($subject, $body));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Validation rules for saving hours (null clears them). */
    public static function rules(): array
    {
        return [
            'hours' => ['present', 'nullable', 'array'],
            'hours.days' => ['required_with:hours', 'array', 'min:1'],
            'hours.days.*' => ['integer', 'between:1,7'],
            'hours.start' => ['required_with:hours', 'date_format:H:i'],
            'hours.end' => ['required_with:hours', 'date_format:H:i', 'after:hours.start'],
        ];
    }
}
