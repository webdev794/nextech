<?php

return [
    /*
     * How the admin unlocks the "Secure access" console section (Stripe keys,
     * admin email / phone).
     *
     *   password — the admin re-enters their account password (default; handy
     *              while email delivery is not wired up).
     *   otp      — a one-time code is e-mailed to the admin address.
     */
    'method' => env('SECURE_ACCESS_METHOD', 'password'),

    /** Minutes a successful unlock stays valid. */
    'ttl_minutes' => (int) env('SECURE_ACCESS_TTL_MINUTES', 15),
];
