<?php

return [
    // Platform commission taken on every order line sold through a seller's
    // shop (a null shop_id — NexTech's own inventory — is never charged). The
    // admin can override this at runtime (settings -> commission_rate_bps).
    'rate_bps' => (int) env('COMMISSION_RATE_BPS', 1000),
];
