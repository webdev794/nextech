<?php

namespace App\Support;

use App\Models\Shop;

/**
 * Seller rules. The basics every seller follows are always on: full listing
 * checks, shipping set up before adding products, the "Get your shop ready"
 * checklist, and the store design product minimum. Admin can additionally
 * switch on stricter rules per shop (Admin -> Sellers -> a seller ->
 * Requirements) — all off by default so new sellers can start easily.
 */
class SellerRequirements
{
    /** Always on for every seller. */
    public const ALWAYS = ['listing_details', 'shipping_setup', 'onboarding_tasks', 'store_min_products'];

    /** Switchable per seller by admin (off by default). */
    public const RULES = [
        'compliance_docs' => ['Compliance documents', 'Products need their required compliance documents before they can be approved, and sellers see "documents missing" warnings.'],
        'bank_verification' => ['Bank verification', 'Payout requests need a bank account that admin has verified.'],
    ];

    public static function on(?Shop $shop, string $rule): bool
    {
        if (in_array($rule, self::ALWAYS, true)) {
            return true;
        }
        // Follows the seller's tax ID: GSTIN sellers must give HSN + GST rate, PAN-only sellers needn't.
        if ($rule === 'gst_details') {
            return SellerTax::gstRequired($shop?->seller);
        }

        return (bool) (($shop?->requirements ?? [])[$rule] ?? false);
    }

    /** @return array<string, bool> */
    public static function all(?Shop $shop): array
    {
        return collect([...self::ALWAYS, ...array_keys(self::RULES), 'gst_details'])->mapWithKeys(fn ($rule) => [$rule => self::on($shop, $rule)])->all();
    }
}
