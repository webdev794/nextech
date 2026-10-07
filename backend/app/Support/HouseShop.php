<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The house shop: the store owner's own shop, run from an admin account on
 * the seller tools — shipping templates and delivery estimates, couriers and
 * tracking, international shipping, own delivery, labels and order reminders.
 * No commission, no product review, no onboarding. One per store, in the
 * home country. NexTech's stores and riders are separate and unchanged.
 */
class HouseShop
{
    public static function current(): ?Shop
    {
        return Shop::where('is_house', true)->with('seller.user:id,name,email')->first();
    }

    public static function is(?Shop $shop): bool
    {
        return (bool) $shop?->is_house;
    }

    /** @param  array{name: string, line1: string, line2?: ?string, city: string, state: string, postal_code: string, tax_id?: ?string}  $data */
    public static function create(User $admin, array $data): Shop
    {
        abort_if(self::current(), 422, 'The house shop already exists.');
        abort_if($admin->seller()->exists(), 422, 'This admin account already has a seller profile — use another admin account for the house shop.');
        $market = Market::home();

        return DB::transaction(function () use ($admin, $data, $market) {
            $details = (array) (((array) \App\Models\Setting::get('business_details', []))[$market] ?? []);
            $seller = Seller::create([
                'user_id' => $admin->id,
                'country' => $market,
                'business_type' => 'company',
                'company_name' => $details['legal_name'] ?? $data['name'],
                'tax_id' => ($data['tax_id'] ?? null) ?: ($details['tax_number'] ?? 'House shop'),
                'registered_line1' => $data['line1'],
                'registered_line2' => $data['line2'] ?? null,
                'registered_city' => $data['city'],
                'registered_state' => $data['state'],
                'registered_postal_code' => $data['postal_code'],
                'registered_country' => $market,
                'pickup_same_as_registered' => true,
                'contact_name' => $admin->name,
                // The owner's own shop: no identity check, so these are placeholders.
                'id_type' => 'house',
                'id_number' => 'house',
                'date_of_birth' => '2000-01-01',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'submitted_at' => now(),
            ]);
            $shop = $seller->shop()->create([
                'market' => $market,
                'name' => $data['name'],
                'slug' => self::slug($data['name']),
                'shop_code' => Sku::assignShopCode($data['name']),
                'is_active' => true,
                'is_house' => true,
                'commission_rate_bps' => 0,
                'fulfillment_mode' => 'self',
                'free_shipping_accepted_at' => now(),
            ]);
            // Its first ship-from address: the registered one.
            $shop->addresses()->create([
                'name' => 'Main', 'line1' => $data['line1'], 'line2' => $data['line2'] ?? null, 'city' => $data['city'],
                'state' => $data['state'], 'postal_code' => $data['postal_code'], 'country' => $market,
                'phone' => $admin->phone ?? '', 'contact_name' => $admin->name, 'is_default' => true,
            ]);

            return $shop->fresh('seller.user');
        });
    }

    private static function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        for ($i = 2; Shop::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
