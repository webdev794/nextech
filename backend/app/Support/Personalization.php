<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Buyer photo personalization (e.g. a printed mug, photo case, custom
 * portrait): the seller switches it on per product, and the buyer uploads
 * photos (and optionally types a note) before adding it to the cart. The
 * photos travel with the cart line onto the order line, where the seller
 * and admin can see and download them.
 */
class Personalization
{
    public const MAX_PHOTOS = 10;

    /** Uploaded through POST /personalization-images. */
    public const PATH_PREFIX = '/api/media/file/personalization/';

    /** Validation rules for a product's settings (seller listing form). */
    public static function settingsRules(): array
    {
        return [
            'personalization' => ['sometimes', 'nullable', 'array'],
            'personalization.enabled' => ['sometimes', 'boolean'],
            'personalization.required' => ['sometimes', 'boolean'],
            'personalization.max_photos' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PHOTOS],
            'personalization.instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'personalization.note_label' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    /** Seller-written text sections on the product page (FAQs, specifications, requirements, own). */
    public static function infoSectionRules(): array
    {
        return [
            'info_sections' => ['sometimes', 'nullable', 'array', 'max:8'],
            'info_sections.*.kind' => ['required', 'in:faq,specs,requirements,custom'],
            'info_sections.*.title' => ['required', 'string', 'max:80'],
            'info_sections.*.body' => ['required', 'string', 'max:4000'],
        ];
    }

    /** A product's settings, normalised; null when off. */
    public static function settings(?Product $product): ?array
    {
        $p = (array) ($product?->personalization ?? []);
        if (empty($p['enabled'])) {
            return null;
        }

        return [
            'enabled' => true,
            'required' => (bool) ($p['required'] ?? true),
            'max_photos' => max(1, min(self::MAX_PHOTOS, (int) ($p['max_photos'] ?? 1))),
            'instructions' => trim((string) ($p['instructions'] ?? '')) ?: null,
            'note_label' => trim((string) ($p['note_label'] ?? '')) ?: null,
        ];
    }

    /**
     * Check what the buyer sent for a cart line against the product's
     * settings; returns the cleaned value (null = none) or throws.
     *
     * @return array{photos: list<string>, note: ?string}|null
     */
    public static function forCartLine(Product $product, mixed $input): ?array
    {
        $settings = self::settings($product);
        $input = is_array($input) ? $input : [];
        $photos = array_values(array_filter((array) ($input['photos'] ?? []), fn ($u) => is_string($u) && str_starts_with($u, self::PATH_PREFIX) && strlen($u) <= 500));
        $note = trim((string) ($input['note'] ?? '')) ?: null;

        if (! $settings) {
            return null; // not a personalized product: ignore anything sent
        }
        if ($settings['required'] && $photos === []) {
            throw ValidationException::withMessages(['personalization' => ["Upload your photo for {$product->name} first."]]);
        }
        if (count($photos) > $settings['max_photos']) {
            throw ValidationException::withMessages(['personalization' => ["{$product->name} takes up to {$settings['max_photos']} photo(s)."]]);
        }
        if ($note !== null && mb_strlen($note) > 500) {
            throw ValidationException::withMessages(['personalization' => ['Keep the note under 500 characters.']]);
        }

        return $photos === [] && $note === null ? null : ['photos' => $photos, 'note' => $settings['note_label'] ? $note : null];
    }

    /** Separates cart lines with different photos. */
    public static function key(?array $value): string
    {
        return $value ? sha1(json_encode($value)) : '';
    }
}
