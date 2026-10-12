<?php

namespace App\Support;

use App\Models\Order;
use App\Models\RiderLedgerEntry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Monthly or weekly pay for NexTech's own riders (admin sets it per rider; the default is
 * pay per delivery). On the 1st (monthly) or Monday (weekly), for the period just ended:
 * the amount in full at the target number of deliveries (less in proportion below it),
 * plus a bonus per extra delivery up to a cap, less a set amount for each missed day
 * (absent without asking, or a day off beyond the allowance). What busy riders earn above
 * the cap goes into the store's top-up pool, which admin can use to top up riders who fell short.
 */
final class RiderPayPlan
{
    /** @return array{monthly_cents: int, target: int, bonus_per_extra_cents: int, bonus_cap_cents: int, period?: string, missed_day_cents?: int}|null */
    public static function of(User $rider): ?array
    {
        $p = $rider->rider_pay_plan;

        return is_array($p) && ($p['type'] ?? null) === 'monthly' ? $p : null;
    }

    /** 'week' or 'month'. */
    public static function period(array $plan): string
    {
        return ($plan['period'] ?? 'month') === 'week' ? 'week' : 'month';
    }

    /** The period (week Mon–Sun, or month) that contains $at. @return array{0: Carbon, 1: Carbon} */
    public static function bounds(array $plan, Carbon $at): array
    {
        return self::period($plan) === 'week'
            ? [$at->copy()->startOfWeek(Carbon::MONDAY), $at->copy()->endOfWeek(Carbon::SUNDAY)]
            : [$at->copy()->startOfMonth(), $at->copy()->endOfMonth()];
    }

    public static function label(array $plan, Carbon $from): string
    {
        return self::period($plan) === 'week' ? 'week of '.$from->format('j M Y') : $from->format('M Y');
    }

    /** NexTech deliveries a rider completed in a month. */
    public static function deliveries(User $rider, Carbon $month): int
    {
        return self::deliveriesBetween($rider, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());
    }

    public static function deliveriesBetween(User $rider, Carbon $from, Carbon $to): int
    {
        return Order::query()->where('delivery_partner_id', $rider->id)->where('status', 'completed')
            ->whereBetween('delivered_at', [$from, $to])->count();
    }

    /** Deliveries so far in the rider's current pay period (null when on pay per delivery). */
    public static function soFar(User $rider): ?int
    {
        $plan = self::of($rider);
        if (! $plan) {
            return null;
        }
        [$from, $to] = self::bounds($plan, now());

        return self::deliveriesBetween($rider, $from, $to);
    }

    /**
     * Missed days in a period: absent without asking, plus accepted days off beyond the
     * month's allowance (counted in date order within each month).
     */
    public static function missedDays(User $rider, Carbon $from, Carbon $to): int
    {
        $absent = \App\Models\RiderLeave::query()->where('user_id', $rider->id)->where('kind', 'absent')
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->count();
        $allowed = RiderWorkHours::allowedFor($rider);
        if ($allowed === null) {
            return $absent;
        }
        $over = 0;
        // Each month the period touches: that month's accepted days off, in date order; those past the allowance count.
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonthNoOverflow()) {
            $days = \App\Models\RiderLeave::query()->where('user_id', $rider->id)->where('status', 'approved')
                ->whereDate('date', '>=', $m->copy()->startOfMonth()->toDateString())->whereDate('date', '<=', $m->copy()->endOfMonth()->toDateString())
                ->orderBy('date')->orderBy('id')->get(['date', 'kind']);
            foreach ($days->slice($allowed) as $d) {
                $date = Carbon::parse($d->date);
                if ($d->kind === 'leave' && $date->betweenIncluded($from->copy()->startOfDay(), $to)) {
                    $over++;
                }
            }
        }

        return $absent + $over;
    }

    /** @return array{pay: int, bonus: int, to_pool: int, short: int, missed_cents: int} */
    public static function work(array $plan, int $deliveries, int $missedDays = 0): array
    {
        $target = max(1, (int) $plan['target']);
        $pay = (int) round($plan['monthly_cents'] * min(1, $deliveries / $target));
        $extra = max(0, $deliveries - $target) * (int) $plan['bonus_per_extra_cents'];
        $bonus = min($extra, (int) $plan['bonus_cap_cents']);
        // Missed days come off the pay (never below zero); they don't count as "short" for top-ups.
        $missed = min($pay, $missedDays * (int) ($plan['missed_day_cents'] ?? 0));

        return ['pay' => $pay - $missed, 'bonus' => $bonus, 'to_pool' => $extra - $bonus, 'short' => (int) $plan['monthly_cents'] - $pay, 'missed_cents' => $missed];
    }

    /** @return list<array<string, mixed>> */
    public static function pools(): array
    {
        return array_values((array) Setting::get('rider_top_up_pools', []));
    }

    /** 1st of the month: pay last month to every rider on monthly pay. Returns how many were paid. */
    public static function payMonth(?Carbon $month = null): int
    {
        return self::payPeriod('month', ($month ?? now()->subMonthNoOverflow())->copy()->startOfMonth());
    }

    /** Monday: pay last week (Mon–Sun) to every rider on weekly pay. Returns how many were paid. */
    public static function payWeek(?Carbon $week = null): int
    {
        return self::payPeriod('week', ($week ?? now()->subWeek())->copy()->startOfWeek(Carbon::MONDAY));
    }

    private static function payPeriod(string $period, Carbon $start): int
    {
        $pools = self::pools();
        $n = 0;
        foreach (User::query()->where('is_rider', true)->whereNotNull('rider_pay_plan')->get() as $rider) {
            $plan = self::of($rider);
            if (! $plan || self::period($plan) !== $period) {
                continue;
            }
            [$from, $to] = self::bounds($plan, $start);
            $label = self::label($plan, $from);
            $title = $period === 'week' ? 'Weekly pay' : 'Monthly pay';
            if (RiderLedgerEntry::query()->where('user_id', $rider->id)->where('type', 'monthly_pay')->where('note', 'like', "{$title} {$label}%")->exists()) {
                continue;
            }
            $market = RiderLedger::marketFor($rider);
            $cur = Market::currency($market);
            $d = self::deliveriesBetween($rider, $from, $to);
            $missedDays = (int) ($plan['missed_day_cents'] ?? 0) > 0 ? self::missedDays($rider, $from, $to) : 0;
            $w = self::work($plan, $d, $missedDays);
            RiderLedgerEntry::create(['user_id' => $rider->id, 'type' => 'monthly_pay', 'amount_cents' => $w['pay'] + $w['bonus'],
                'note' => "{$title} {$label}: {$d} of {$plan['target']} deliveries".($w['bonus'] ? ' + bonus '.Money::format($w['bonus'], $cur) : '')
                    .($w['missed_cents'] ? " − {$missedDays} missed ".($missedDays === 1 ? 'day' : 'days').' '.Money::format($w['missed_cents'], $cur) : '')]);
            $key = collect($pools)->search(fn ($p) => $p['market'] === $market && $p['month'] === $label);
            if ($key === false) {
                $pools[] = ['id' => (string) Str::uuid(), 'market' => $market, 'month' => $label, 'pool_cents' => 0, 'short' => []];
                $key = array_key_last($pools);
            }
            $pools[$key]['pool_cents'] += $w['to_pool'];
            if ($w['short'] > 0) {
                $pools[$key]['short'][] = ['rider_id' => $rider->id, 'name' => $rider->name, 'short_cents' => $w['short'], 'topped_cents' => 0];
            }
            try {
                $rider->notify(new \App\Notifications\RiderNotice('Your '.strtolower($title), "{$title} for {$label}: ".Money::format($w['pay'] + $w['bonus'], $cur)." ({$d} of {$plan['target']} deliveries".($w['missed_cents'] ? "; {$missedDays} missed ".($missedDays === 1 ? 'day' : 'days').' − '.Money::format($w['missed_cents'], $cur) : '')."). It’s added to your earnings."));
            } catch (\Throwable $e) {
                report($e);
            }
            $n++;
        }
        Setting::put('rider_top_up_pools', $pools);

        return $n;
    }

    /** Admin tops up a rider who fell short, from that month's pool. */
    public static function topUp(string $poolId, int $riderId, int $cents, User $by): void
    {
        $pools = self::pools();
        $i = collect($pools)->search(fn ($p) => $p['id'] === $poolId);
        abort_if($i === false, 404);
        abort_if($cents < 1 || $cents > $pools[$i]['pool_cents'], 422, 'That’s more than the pool holds.');
        $j = collect($pools[$i]['short'])->search(fn ($s) => (int) $s['rider_id'] === $riderId);
        abort_if($j === false, 422, 'That rider isn’t short this month.');
        $rider = User::findOrFail($riderId);
        RiderLedgerEntry::create(['user_id' => $rider->id, 'type' => 'top_up', 'amount_cents' => $cents, 'note' => "Top-up for {$pools[$i]['month']}", 'created_by' => $by->id]);
        $pools[$i]['pool_cents'] -= $cents;
        $pools[$i]['short'][$j]['topped_cents'] += $cents;
        Setting::put('rider_top_up_pools', $pools);
        try {
            $rider->notify(new \App\Notifications\RiderNotice('Pay top-up', 'You got a top-up of '.Money::format($cents, Market::currency($pools[$i]['market']))." for {$pools[$i]['month']}. It’s added to your earnings."));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
