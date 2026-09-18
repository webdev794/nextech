<?php

return [
    // When false, register and login return a token immediately (password only).
    'enabled' => (bool) env('AUTH_OTP_ENABLED', true),

    'length' => (int) env('AUTH_OTP_LENGTH', 6),
    'ttl_minutes' => (int) env('AUTH_OTP_TTL_MINUTES', 10),
    'max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('AUTH_OTP_RESEND_COOLDOWN', 60),

    // Dev/staging only: when set, this code verifies any pending OTP. Leave blank in production.
    'bypass_code' => (string) env('AUTH_OTP_BYPASS_CODE', ''),
];
