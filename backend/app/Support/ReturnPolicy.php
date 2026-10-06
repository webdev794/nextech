<?php

namespace App\Support;

/**
 * Return conditions a seller picks per product (keys shared with web/src/returnPolicy.jsx).
 */
class ReturnPolicy
{
    public const ACCEPTS = ['defective', 'damaged_in_transit', 'wrong_item', 'missing_parts', 'changed_mind'];

    public const EXCLUDES = ['physical_damage', 'liquid_damage', 'mishandling', 'used_or_opened', 'missing_packaging'];

    /** Validation rules for a product's `return_policy` field. */
    public static function rules(): array
    {
        return [
            'return_policy' => ['sometimes', 'nullable', 'array'],
            'return_policy.accepts' => ['sometimes', 'array'],
            'return_policy.accepts.*' => ['string', \Illuminate\Validation\Rule::in(self::ACCEPTS)],
            'return_policy.excludes' => ['sometimes', 'array'],
            'return_policy.excludes.*' => ['string', \Illuminate\Validation\Rule::in(self::EXCLUDES)],
            'return_policy.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** Tidy a submitted policy; null when nothing was chosen. */
    public static function clean(?array $policy): ?array
    {
        if (! $policy) {
            return null;
        }
        $clean = [
            'accepts' => array_values(array_unique(array_intersect((array) ($policy['accepts'] ?? []), self::ACCEPTS))),
            'excludes' => array_values(array_unique(array_intersect((array) ($policy['excludes'] ?? []), self::EXCLUDES))),
            'notes' => trim((string) ($policy['notes'] ?? '')) ?: null,
        ];

        return $clean['accepts'] || $clean['excludes'] || $clean['notes'] ? $clean : null;
    }
}
