<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Shop;

/**
 * Selling abroad: the seller applies with their export ID (and document where
 * the country needs one), accepts the international policies and signs a
 * declaration that they ship only legal goods, declare them truthfully and
 * handle export paperwork. Signing approves it — no admin step; admin can
 * stop a seller who breaks the rules, and can switch the whole step off.
 */
class SellerIntl
{
    public static function requiresApproval(): bool
    {
        return (bool) Setting::get('intl_requires_approval', true);
    }

    /** 'approved' | 'pending' | 'rejected' | 'revoked' | null (hasn't applied). */
    public static function status(Shop $shop): ?string
    {
        return ((array) $shop->intl_approval)['status'] ?? null;
    }

    public static function allowed(Shop $shop): bool
    {
        return $shop->is_house || ! self::requiresApproval() || self::status($shop) === 'approved';
    }

    /** The country's export ID and document rules (config/markets.php → onboarding.export). */
    public static function rules(?string $market): array
    {
        return (array) (Market::profile($market)['onboarding']['export'] ?? []) + [
            'id_label' => 'Export registration number',
            'id_regex' => null,
            'document_label' => 'Export registration document',
            'document_required' => false,
        ];
    }

    /** What the seller signs. */
    public static function declaration(string $country): string
    {
        return "I confirm that everything I ship abroad through ".Branding::name()." is legal to export from {$country} and to import into the buyer's country; "
            .'I will not ship prohibited, restricted or dangerous items, or anything needing an export licence I do not hold; '
            .'I will describe the contents and value truthfully on every customs declaration; '
            .'and I am responsible for export paperwork, and for any fines or losses if I break these rules. '
            .Branding::name().' may stop my international selling and remove listings at any time if I do.';
    }
}
