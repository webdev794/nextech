<?php

namespace App\Support;

use App\Models\Setting;

class FooterConfig
{
    /** Social platforms we render, in display order. */
    public const PLATFORMS = ['facebook', 'x', 'instagram', 'linkedin', 'youtube'];

    /**
     * Effective footer config: config defaults overlaid with the admin-saved
     * value, normalised so the storefront can render it directly.
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        $defaults = config('footer');
        $saved = Setting::get('footer', []);
        $saved = is_array($saved) ? $saved : [];

        return self::sanitize(array_merge($defaults, $saved));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $str = fn ($value, int $max) => mb_substr(trim((string) ($value ?? '')), 0, $max);

        $socialsIn = is_array($input['socials'] ?? null) ? $input['socials'] : [];
        $socials = [];
        foreach (self::PLATFORMS as $platform) {
            $socials[$platform] = $str($socialsIn[$platform] ?? '', 2048);
        }

        $links = [];
        foreach ((is_array($input['links'] ?? null) ? $input['links'] : []) as $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = $str($link['label'] ?? '', 40);
            $url = $str($link['url'] ?? '', 2048);
            if ($label !== '' && $url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
            if (count($links) >= 12) {
                break;
            }
        }

        $defaults = config('footer');
        $hex = fn ($value, string $default) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? $value : $default;

        return [
            'copyright' => $str($input['copyright'] ?? '', 160),
            'app_store_url' => $str($input['app_store_url'] ?? '', 2048),
            'play_store_url' => $str($input['play_store_url'] ?? '', 2048),
            'socials' => $socials,
            'links' => $links,
            'bg_color' => $hex($input['bg_color'] ?? null, $defaults['bg_color']),
            'text_color' => $hex($input['text_color'] ?? null, $defaults['text_color']),
        ];
    }
}
