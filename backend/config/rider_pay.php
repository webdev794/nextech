<?php

return [
    // Per completed delivery: a base fee plus a per-mile rate for the
    // store -> customer distance (straight line). Admin can override both at
    // runtime (settings -> rider_base_pay_cents / rider_per_mile_cents).
    'base_cents' => (int) env('RIDER_BASE_PAY_CENTS', 300),
    'per_mile_cents' => (int) env('RIDER_PER_MILE_CENTS', 75),

    // Payout batching, like sellers: a rider can request once what they are
    // owed reaches the minimum; a single payout is capped at the maximum.
    'min_payout_cents' => (int) env('RIDER_MIN_PAYOUT_CENTS', 5000),
    'max_payout_cents' => (int) env('RIDER_MAX_PAYOUT_CENTS', 200000),
];
