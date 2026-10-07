<?php

namespace App\Support;

use App\Models\Seller;

/**
 * Seller Center onboarding tasks an approved seller finishes before they can
 * be paid (modelled on Temu's): 1 tax information, 2 additional compliance
 * information, 3 bank account, 4 shipping templates. Tasks 1–3 are reviewed
 * by NexTech from the admin console; per-market wording and lists live in
 * config/markets.php -> onboarding.
 */
class SellerOnboarding
{
    /** Business types that must name directors, executives and beneficial owners separately. */
    public const COMPANY_TYPES = ['private_limited', 'state_owned', 'public_listed'];

    public const ROLES = ['ubo', 'director', 'executive'];

    /** Bank documents must be issued within this many days. */
    public const BANK_DOCUMENT_MAX_AGE_DAYS = 180;

    /** @return array<string, mixed> */
    public static function config(Seller $seller): array
    {
        $market = $seller->shop?->market ?? Market::forCountry($seller->country);

        return (array) (Market::profile($market)['onboarding'] ?? []);
    }

    public static function isCompany(Seller $seller): bool
    {
        return in_array($seller->business_type, self::COMPANY_TYPES, true);
    }

    /** Letters and digits only, upper-cased — "12-3456789" and "123456789" are the same EIN. */
    public static function normalizeTaxNumber(?string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    /**
     * The primary contact from the seller application. Its registration
     * fields can't be edited in the compliance form; only the details the
     * application didn't ask for (citizenship, residence, …) are filled in there.
     *
     * @return array<string, mixed>
     */
    public static function primaryContact(Seller $seller): array
    {
        return [
            'legal_name' => $seller->contact_name,
            'date_of_birth' => $seller->date_of_birth?->toDateString(),
            'id_type' => $seller->id_type,
            'id_number' => $seller->id_number,
            'id_country' => $seller->country,
        ];
    }

    /** Duplicate-person key: same legal name and date of birth. */
    public static function personKey(string $name, ?string $dob): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name))).'|'.$dob;
    }

    /**
     * Status of each task for the Seller Center homepage.
     *
     * @return array<string, string> todo | pending | approved | rejected (bank: todo | processing | linked | failed)
     */
    public static function tasks(Seller $seller): array
    {
        return [
            'tax' => $seller->tax_status ?? (($seller->tax_info['tax_number'] ?? null) ? 'step2' : 'todo'),
            'compliance' => $seller->compliance_status ?? 'todo',
            'bank' => $seller->bank_status ?? 'todo',
            'shipping' => $seller->shop && SellerShipping::setupComplete($seller->shop) ? 'done' : 'todo',
        ];
    }
}
