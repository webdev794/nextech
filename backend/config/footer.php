<?php

/*
 * Storefront footer defaults. An administrator overrides these under
 * Admin console -> Pages -> Footer (stored in the `settings` table under the
 * `footer` key). App\Support\FooterConfig merges + normalises; GET /api/config
 * exposes the effective values.
 */

return [
    // "{year}" is replaced with the current year by the storefront.
    'copyright' => env('FOOTER_COPYRIGHT', '© {year} NexTech'),

    'app_store_url' => env('FOOTER_APP_STORE_URL', ''),
    'play_store_url' => env('FOOTER_PLAY_STORE_URL', ''),

    // Footer colors — override the storefront's default light footer with any
    // hex color, e.g. a dark footer like large marketplace sites use.
    'bg_color' => env('FOOTER_BG_COLOR', '#f3f5f2'),
    'text_color' => env('FOOTER_TEXT_COLOR', '#18211c'),

    // Only the platforms in this list are rendered; a blank url hides that icon.
    'socials' => [
        'facebook' => env('FOOTER_FACEBOOK_URL', ''),
        'x' => env('FOOTER_X_URL', ''),
        'instagram' => env('FOOTER_INSTAGRAM_URL', ''),
        'linkedin' => env('FOOTER_LINKEDIN_URL', ''),
        'youtube' => env('FOOTER_YOUTUBE_URL', ''),
    ],

    // Extra footer links (label + url), shown under "Useful Links" after the pages.
    'links' => [],
];
