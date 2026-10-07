<?php

namespace App\Support;

use App\Models\Seller;

/**
 * What a seller's tax ID allows.
 *
 * India: a GSTIN-registered seller can sell anywhere in India and must put
 * the HSN code and GST rate on every product (tax invoices need them). Under
 * Notification No. 34/2023-Central Tax (in force 1 Oct 2023) a seller may
 * instead sell through a marketplace with only a PAN — if they take a
 * PAN-based enrolment number on the GST portal, stay under the registration
 * turnover threshold, and sell only within their own state. So PAN-only
 * sellers' items can only be delivered inside the seller's state.
 *
 * US: marketplaces need a taxpayer ID for Form 1099-K — an EIN, or the
 * owner's SSN / ITIN for sole proprietors.
 */
class SellerTax
{
    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public const PAN = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';

    public static function normalized(?string $taxId): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $taxId));
    }

    /** An Indian seller registered with a PAN only (no GSTIN). */
    public static function panOnly(?Seller $seller): bool
    {
        return $seller !== null
            && strtoupper((string) $seller->country) === 'IN'
            && preg_match(self::PAN, self::normalized($seller->tax_id)) === 1;
    }

    /** GSTIN-registered Indian sellers must give HSN code + GST rate on products. */
    public static function gstRequired(?Seller $seller): bool
    {
        return $seller !== null
            && strtoupper((string) $seller->country) === 'IN'
            && preg_match(self::GSTIN, self::normalized($seller->tax_id)) === 1;
    }

    /** The state code a PAN-only seller may deliver to, or null when not limited. */
    public static function onlyState(?Seller $seller): ?string
    {
        return self::panOnly($seller) ? SellerShipping::stateCode($seller->registered_state, 'IN') : null;
    }
}
