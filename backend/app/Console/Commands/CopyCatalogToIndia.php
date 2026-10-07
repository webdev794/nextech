<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Sku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies NexTech's own US catalogue into the India market: same category,
 * description, images, gallery, variants, deal flags and stock, with Indian
 * retail prices (INR, GST-inclusive, MRP where the product is on offer) and
 * the details Indian listings need (HSN code, GST rate, origin, importer).
 * Products already copied (same name in India) are skipped, so it is safe
 * to run again. Manufacturer / importer details are placeholders for admin
 * to replace with the real ones.
 */
class CopyCatalogToIndia extends Command
{
    protected $signature = 'catalog:copy-to-india {--dry-run : Only list what would be copied}';

    protected $description = 'Copy NexTech\'s US products into the India market with rupee prices.';

    /**
     * US product name => [price, MRP (null = not on offer), [variant label => price]] in whole rupees.
     *
     * @var array<string, array{0: int, 1: ?int, 2?: array<string, int>}>
     */
    private const PRICES = [
        'Apple iPhone 15 Pro' => [134900, null, ['256GB' => 144900, '512GB' => 164900]],
        'Samsung Galaxy S24' => [74999, null, ['256GB' => 79999]],
        'Google Pixel 8' => [75999, 82999],
        'OnePlus 12' => [64999, null],
        'Xiaomi 14' => [69999, null],
        'Apple MacBook Air M3' => [114900, null, ['16GB / 512GB' => 144900]],
        'Dell XPS 13' => [119990, null, ['256GB SSD / 8GB RAM' => 119990, '512GB SSD / 16GB RAM' => 144990, '1TB SSD / 32GB RAM' => 179990]],
        'HP Spectre x360' => [149999, null, ['16GB / 1TB' => 179999]],
        'Lenovo ThinkPad X1 Carbon' => [189990, null],
        'Asus ROG Zephyrus G14' => [199990, null],
        'Sony WH-1000XM5' => [29990, null, ['Midnight Black' => 29990, 'Platinum Silver' => 31990]],
        'Apple AirPods Pro 2' => [24900, null],
        'Bose QuietComfort Ultra' => [35900, null],
        'JBL Flip 6 Speaker' => [11999, null],
        'Sennheiser Momentum 4' => [29990, null],
        'Tempered Glass Screen Protector' => [499, null],
        'Silicone Phone Case' => [799, null, ['Ocean Blue' => 799, 'Blossom Pink' => 799]],
        'MagSafe Wireless Charger' => [4500, null],
        'Apple Watch Series 9' => [41900, null, ['45mm' => 44900]],
        'Samsung Galaxy Watch 6' => [29999, null],
        'Fitbit Charge 6' => [14999, null, ['Coral' => 14999]],
        'Canon EOS R50' => [71995, null],
        'Sony Alpha ZV-E10' => [59990, null],
        'GoPro Hero 12' => [37990, null],
        'Samsung 55" QLED TV' => [69990, null],
        'LG 65" OLED TV' => [219990, null],
        'Sony 43" Bravia TV' => [47990, null],
        'Sony PlayStation 5' => [54990, null, ['Digital Edition' => 44990]],
        'Microsoft Xbox Series X' => [55990, null],
        'Nintendo Switch OLED' => [32990, null, ['Neon Red / Neon Blue' => 32990]],
        'Dyson V15 Vacuum Cleaner' => [65900, null],
        'Philips Air Fryer XXL' => [19999, null],
        'LG 8kg Front Load Washing Machine' => [39990, null],
        'Logitech MX Master 3S Mouse' => [9995, null, ['Pale Grey' => 9995]],
        'Keychron K2 Mechanical Keyboard' => [8499, null],
        'Dell 27" 4K Monitor' => [32999, null],
        'Anker 20000mAh Power Bank' => [3999, null],
        'Apple 20W USB-C Fast Charger' => [1900, null],
        'Belkin 3-in-1 Wireless Charging Stand' => [9999, null],
        'SanDisk 1TB Portable SSD' => [8999, null],
        'Samsung 256GB microSD Card' => [2299, null],
        'WD 2TB External Hard Drive' => [6499, null],
        'TP-Link Archer WiFi 6 Router' => [6999, null],
        'Netgear Orbi Mesh WiFi System' => [24999, null],
        'TP-Link 8-Port Gigabit Switch' => [1899, null],
        'Philips Hair Dryer' => [1695, null],
        'Oral-B Electric Toothbrush' => [2999, null],
        'Panasonic Beard Trimmer' => [1999, null],
        'Motorola Video Baby Monitor' => [7999, null],
        'Amazon Fire Kids Tablet' => [9999, null],
        'LeapFrog Learning Tablet' => [5499, null],
        'HP LaserJet Printer' => [14499, null],
        'Epson Portable Projector' => [39999, null],
        'Logitech Webcam C920' => [6995, 8995],
        'Amazon Echo Dot (5th Gen)' => [5499, null],
        'Philips Hue Smart Bulb Starter Kit' => [8999, null],
        'TP-Link Kasa Smart Plug' => [1499, null],
        'Ring Video Doorbell' => [8999, null],
        'Eufy RoboVac 11S Robot Vacuum' => [17999, null],
        'Garmin Vivosmart 5 Fitness Band' => [13990, null],
        'Withings Body+ Smart Scale' => [9999, null],
        'Omron Digital Blood Pressure Monitor' => [2499, null],
        'Wellue Pulse Oximeter' => [1999, null],
        'Xiaomi Smart Skipping Rope' => [1299, null],
        'Apple iPhone 15 Pro Max' => [159900, null],
        'Samsung Galaxy Z Fold 6' => [164999, null],
        'Sony Xperia 1 VI' => [129990, null],
        'Asus ROG Phone 8' => [94999, null],
        'Dell XPS 15 Plus' => [219990, null],
        'Garmin DriveSmart 55 GPS Navigator' => [21990, null],
        'Pioneer Bluetooth Car Stereo Receiver' => [8990, null],
        'iOttie Car Dashboard Phone Mount' => [2499, null],
        'Car Vent Air Purifier & Freshener' => [1299, null],
        'Pioneer Digital Car Clock Gauge' => [1999, null],
    ];

    /** Name keyword => HSN code (first match wins), then a per-category fallback. */
    private const HSN_BY_NAME = [
        'Screen Protector' => '70071900', 'Phone Case' => '39269099', 'Charger' => '85044030', 'Charging' => '85044030',
        'Power Bank' => '85076000', 'microSD' => '85235100', 'SSD' => '85235100', 'Hard Drive' => '84717020',
        'Monitor' => '85285200', 'Mouse' => '84716060', 'Keyboard' => '84716060', 'Air Fryer' => '85167200',
        'Hair Dryer' => '85163100', 'Toothbrush' => '85094090', 'Trimmer' => '85101000', 'Vacuum' => '85081100',
        'RoboVac' => '85081100', 'Washing Machine' => '84501100', 'Printer' => '84433100', 'Projector' => '85286900',
        'Webcam' => '85258900', 'Baby Monitor' => '85258900', 'Doorbell' => '85258900', 'Echo Dot' => '85182200',
        'Smart Bulb' => '85395000', 'Smart Plug' => '85365090', 'Scale' => '84231000', 'Blood Pressure' => '90189099',
        'Oximeter' => '90189099', 'Skipping Rope' => '95069190', 'GPS' => '85269190', 'Car Stereo' => '85272100',
        'Phone Mount' => '39269099', 'Air Purifier' => '84213920', 'Clock' => '91040000', 'Kids Tablet' => '84713010',
        'Learning Tablet' => '95030090', 'Router' => '85176290', 'Mesh' => '85176290', 'Switch' => '85176290',
        'PlayStation' => '95045000', 'Xbox' => '95045000', 'Nintendo' => '95045000', 'TV' => '85287219',
        'Watch' => '85176290', 'Fitness Band' => '85176290', 'Fitbit' => '85176290',
    ];

    private const HSN_BY_CATEGORY = [
        'mobiles-smartphones' => '85171300', 'premium-and-flagship' => '85171300', 'laptops-computers' => '84713010',
        'audio-headphones' => '85183000', 'cameras-photography' => '85258900', 'televisions' => '85287219',
    ];

    public function handle(): int
    {
        $source = Product::with(['variants', 'images', 'category'])->where('market', 'US')->whereNull('shop_id')->orderBy('id')->get();
        $existing = Product::where('market', 'IN')->whereNull('shop_id')->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip();
        $copied = 0;

        foreach ($source as $p) {
            $prices = self::PRICES[$p->name] ?? null;
            if (! $prices) {
                $this->warn("No rupee price for \"{$p->name}\" — skipped.");

                continue;
            }
            if ($existing->has(mb_strtolower($p->name))) {
                $this->line("Already in India: {$p->name}");

                continue;
            }
            [$price, $mrp] = $prices;
            $variantPrices = $prices[2] ?? [];
            $hsn = $this->hsn($p);
            $this->line(sprintf('%-45s ₹%s%s', $p->name, number_format($price), $mrp ? ' (MRP ₹'.number_format($mrp).')' : ''));
            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($p, $price, $mrp, $variantPrices, $hsn) {
                $copy = $p->replicate(['sku', 'slug', 'units_sold', 'rating_avg', 'rating_count', 'next_variant_seq']);
                $copy->fill([
                    'market' => 'IN',
                    'slug' => $this->slug($p->slug.'-in'),
                    'sku' => 'TMP-'.Str::random(20),
                    'price_cents' => $price * 100,
                    'compare_at_price_cents' => $mrp ? $mrp * 100 : null,
                    'hsn_code' => $hsn,
                    'gst_rate_bps' => in_array(substr($hsn, 0, 4), ['9018', '9506'], true) ? 500 : 1800,
                    'country_of_origin' => $p->country_of_origin ?: $this->origin($p->name),
                    'manufacturer_info' => $p->manufacturer_info ?: 'Imported and marketed by '.\App\Support\Branding::name().' — replace with the brand’s manufacturer / importer name and address.',
                    'units_sold' => 0,
                    'rating_avg' => $p->rating_avg,
                    'rating_count' => $p->rating_count,
                ]);
                $copy->market = 'IN';
                $copy->save();
                $copy->update(['sku' => Sku::forAdminProduct($copy->id)]);

                foreach ($p->images as $image) {
                    $copy->images()->create(['url' => $image->getRawOriginal('url') ?? $image->url, 'sort_order' => $image->sort_order]);
                }
                foreach ($p->variants as $v) {
                    $inr = $variantPrices[$v->label] ?? (int) round($price * $v->price_cents / max(1, $p->price_cents), -1) - 1;
                    $copy->variants()->create([
                        'label' => $v->label,
                        'sku' => Sku::nextVariantSku($copy),
                        'price_cents' => $inr * 100,
                        'compare_at_price_cents' => null,
                        'inventory_quantity' => $v->inventory_quantity,
                        'image_url' => $v->getRawOriginal('image_url'),
                        'sort_order' => $v->sort_order,
                        'is_active' => $v->is_active,
                        'options' => $v->options,
                    ]);
                }
            });
            $copied++;
        }

        $this->info($this->option('dry-run') ? 'Dry run — nothing saved.' : "Copied {$copied} products to India.");

        return self::SUCCESS;
    }

    private function hsn(Product $p): string
    {
        foreach (self::HSN_BY_NAME as $word => $code) {
            if (stripos($p->name, $word) !== false) {
                return $code;
            }
        }

        return self::HSN_BY_CATEGORY[$p->category?->slug] ?? '85176290';
    }

    /** iPhones and Samsung phones sold in India are made in India; most other electronics are imported. */
    private function origin(string $name): string
    {
        return preg_match('/iPhone|Galaxy (S|Z)/i', $name) ? 'India' : 'China';
    }

    private function slug(string $base): string
    {
        $slug = $base;
        for ($n = 2; Product::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
