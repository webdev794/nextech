<?php

/*
 * Storefront branding defaults. These are the initial values only — an
 * administrator overrides them at runtime under Admin console -> Store settings
 * (stored in the `settings` table under the `branding` key). App\Support\Branding
 * merges the two, and GET /api/config exposes the effective values.
 */

return [
    'store_name' => env('STORE_NAME', 'NexTech'),
    'tagline' => env('STORE_TAGLINE', 'Fresh groceries, less fuss'),
    'logo_url' => env('STORE_LOGO_URL', ''),
    'favicon_url' => env('STORE_FAVICON_URL', ''),
    'theme' => env('STORE_THEME', 'light'), // light | dark
    'layout_width' => env('STORE_LAYOUT_WIDTH', 'boxed'), // boxed | full

    // Hex colours applied to storefront CSS custom properties.
    'color_brand' => env('STORE_COLOR_BRAND', '#1f7a3d'),   // --green
    'color_accent' => env('STORE_COLOR_ACCENT', '#ffd23f'), // --yellow
    'color_heading' => env('STORE_COLOR_HEADING', '#18211c'), // --ink
];
