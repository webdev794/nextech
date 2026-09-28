<?php

namespace App\Support;

use App\Models\Shop;

/**
 * Stricter seller rules admin can switch on per shop (Admin -> Sellers ->
 * a seller -> Requirements). Everything is off unless admin turns it on, so
 * new sellers can list and sell without the extra checks.
 */
class SellerRequirements
{
    public const RULES = [
        'compliance_docs' => ['Compliance documents', 'Products need their required compliance documents before they can be approved, and sellers see "documents missing" warnings.'],
        'listing_details' => ['Full listing checks', 'Submitting a product needs every required product detail, a description, the country of origin, a handling time and (India) HSN / GST / manufacturer details. Name, category, image and price are always required.'],
        'shipping_setup' => ['Shipping setup', 'The seller must set up their own shipping (a shipping template, or NexTech pickup where it is offered) before adding products.'],
        'bank_verification' => ['Bank verification', 'Payout requests need a bank account that admin has verified.'],
        'onboarding_tasks' => ['Onboarding tasks', 'Shows the "Get your shop ready" checklist (tax, compliance, bank, shipping) on the seller homepage.'],
        'store_min_products' => ['Store design minimum', 'A decorated store page is shown only once the store has the minimum number of live products (Settings).'],
    ];

    public static function on(?Shop $shop, string $rule): bool
    {
        return (bool) (($shop?->requirements ?? [])[$rule] ?? false);
    }

    /** @return array<string, bool> */
    public static function all(?Shop $shop): array
    {
        return collect(self::RULES)->keys()->mapWithKeys(fn ($rule) => [$rule => self::on($shop, $rule)])->all();
    }
}
