<?php

namespace App\Support;

use App\Models\Setting;

class Branding
{
    /** @var list<string> */
    private const STRINGS = ['store_name', 'tagline', 'logo_url', 'favicon_url'];

    /** @var list<string> */
    private const COLORS = ['color_brand', 'color_accent', 'color_heading'];

    /**
     * Effective branding: config defaults overlaid with the admin-saved values,
     * normalised so the storefront can trust every field.
     *
     * @return array<string, string>
     */
    public static function current(): array
    {
        $defaults = config('branding');
        $saved = Setting::get('branding', []);
        $saved = is_array($saved) ? array_intersect_key($saved, $defaults) : [];

        $branding = array_merge($defaults, $saved);

        $branding['theme'] = in_array($branding['theme'] ?? null, ['light', 'dark'], true)
            ? $branding['theme']
            : 'light';

        $branding['layout_width'] = in_array($branding['layout_width'] ?? null, ['boxed', 'full'], true)
            ? $branding['layout_width']
            : 'boxed';

        foreach (self::COLORS as $key) {
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($branding[$key] ?? ''))) {
                $branding[$key] = $defaults[$key];
            }
        }

        foreach (self::STRINGS as $key) {
            $branding[$key] = trim((string) ($branding[$key] ?? ''));
        }

        return $branding;
    }
}
