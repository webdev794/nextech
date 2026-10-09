<?php

namespace App\Support;

use App\Models\Order;
use App\Models\RiderLedgerEntry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Monthly pay for NexTech's own riders (admin sets it per rider; the default is pay
 * per delivery). On the 1st, for last month: the monthly amount in full at the target
 * number of deliveries (less in proportion below it), plus a bonus per extra delivery
 * up to a cap. What busy riders earn above the cap goes into the store's top-up pool,
 * which admin can use to top up riders who fell short.
 */
final class RiderPayPlan
{
    /** @return array{monthly_cents: int, target: int, bonus_per_extra_cents: int, bonus_cap_cents: int}|null */
    public static function of(User $rider): ?array
    {
        $p = $rider->rider_pay_plan;

        return is_array($p) && ($p['type'] ?? null) === 'monthly' ? $p : null;
    }

    /** NexTech deliveries a rider completed in a month. */
    public static function deliveries(User $rider, Carbon $month): int
    {
        return Order::query()->where('delivery_partner_id', $rider->id)->where('status', 'completed')
            ->whereBetween('delivered_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count();
    }

    /** @return array{pay: int, bonus: int, to_pool: int, short: int} */
    public static function work(array $plan, int $deliveries): array
    {
        $target = max(1, (int) $plan['target']);
        $pay = (int) round($plan['monthly_cents'] * min(1, $deliveries / $target));
        $extra = max(0, $deliveries - $target) * (int) $plan['bonus_per_extra_cents'];
        $bonus = min($extra, (int) $plan['bonus_cap_cents']);

        return ['pay' => $pay, 'bonus' => $bonus, 'to_pool' => $extra - $bonus, 'short' => (int) $plan['monthly_cents'] - $pay];
    }

    /** @return list<array<string, mixed>> */
    public static function pools(): array
    {
        return array_values((array) Setting::get('rider_top_up_pools', []));
    }

    /** 1st of the month: pay last month to every rider on monthly pay. Returns how many were paid. */
    public static function payMonth(?Carbon $month = null): int
    {
        $month ??= now()->subMonthNoOverflow()->startOfMonth();
        $label = $month->format('M Y');
        $pools = self::pools();
        $n = 0;
        foreach (User::query()->where('is_rider', true)->whereNotNull('rider_pay_plan')->get() as $rider) {
            $plan = self::of($rider);
            if (! $plan || RiderLedgerEntry::query()->where('user_id', $rider->id)->where('type', 'monthly_pay')->where('note', 'like', "Monthly pay {$label}%")->exists()) {
                continue;
            }
            $d = self::deliveries($rider, $month);
            $w = self::work($plan, $d);
            RiderLedgerEntry::create(['user_id' => $rider->id, 'type' => 'monthly_pay', 'amount_cents' => $w['pay'] + $w['bonus'],
                'note' => "Monthly pay {$label}: {$d} of {$plan['target']} deliveries".($w['bonus'] ? ' + bonus '.Money::format($w['bonus'], Market::currency(RiderLedger::marketFor($rider))) : '')]);
            $market = RiderLedger::marketFor($rider);
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
                $rider->notify(new \App\Notifications\RiderNotice('Your monthly pay', "Monthly pay for {$label}: ".Money::format($w['pay'] + $w['bonus'], Market::currency($market))." ({$d} of {$plan['target']} deliveries). It’s added to your earnings."));
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
