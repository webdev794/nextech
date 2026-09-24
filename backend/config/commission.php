<?php

return [
    // Platform commission taken on every order line sold through a seller's
    // shop (a null shop_id — NexTech's own inventory — is never charged). The
    // admin can override this at runtime (settings -> commission_rate_bps).
    'rate_bps' => (int) env('COMMISSION_RATE_BPS', 1000),

    // Minimum balance a payout can be recorded against, in cents — batches
    // small amounts into one transfer instead of paying out per order (the
    // norm across marketplaces: fixed transfer fees eat into tiny amounts,
    // and batching leaves a window for refunds to land first). Admin can
    // override at runtime (settings -> min_payout_cents).
    'min_payout_cents' => (int) env('MIN_PAYOUT_CENTS', 100000),

    // Largest single payout, in cents — banks cap a single transfer too, so a
    // bigger balance is paid over several transfers. Admin-overridable
    // (settings -> max_payout_cents).
    'max_payout_cents' => (int) env('MAX_PAYOUT_CENTS', 1000000),

    // Total paid out across ALL sellers per calendar day, in cents (0 = no
    // cap) — keeps many sellers cashing out on the same day inside the
    // platform account's own daily transfer limit. Admin-overridable
    // (settings -> daily_payout_cap_cents).
    'daily_payout_cap_cents' => (int) env('DAILY_PAYOUT_CAP_CENTS', 5000000),

    // Default return window in days after delivery, for products that don't
    // set their own (products.return_days). Seller earnings for an order are
    // held until this passes. Admin-overridable (settings -> return_window_days).
    'return_window_days' => (int) env('RETURN_WINDOW_DAYS', 30),

    // Longest return window a product may set — also the longest a seller's
    // earnings can be held. Admin-overridable (settings -> max_return_days).
    'max_return_days' => (int) env('MAX_RETURN_DAYS', 90),

    // Charged to the seller for collecting a returned item from the customer,
    // when an admin refunds with "charge seller return pickup" ticked.
    // Admin-overridable (settings -> return_pickup_fee_cents).
    'return_pickup_fee_cents' => (int) env('RETURN_PICKUP_FEE_CENTS', 499),
];
