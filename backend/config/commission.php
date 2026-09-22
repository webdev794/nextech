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
];
