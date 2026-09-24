<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Markets: each shop (and so each product) sells in its seller's country;
 * orders are placed in one market and currency. India adds GST-inclusive
 * pricing with per-product HSN / GST rate and the Legal Metrology label
 * details (country of origin, manufacturer / importer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('market', 2)->default('US')->after('seller_id')->index();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('market', 2)->default('US')->after('shop_id')->index();
            $table->string('hsn_code', 8)->nullable()->after('sku');
            $table->unsignedSmallInteger('gst_rate_bps')->nullable()->after('hsn_code');
            $table->string('country_of_origin', 60)->nullable()->after('gst_rate_bps');
            $table->string('manufacturer_info', 500)->nullable()->after('country_of_origin');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('market', 2)->default('US')->after('user_id')->index();
            $table->string('currency', 3)->default('usd')->after('market');
            $table->unsignedInteger('tax_included_cents')->default(0)->after('tax_cents');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('hsn_code', 8)->nullable()->after('sku');
            $table->unsignedSmallInteger('gst_rate_bps')->nullable()->after('hsn_code');
        });

        Schema::table('seller_ledger_entries', function (Blueprint $table) {
            $table->string('type', 24)->change();
        });

        // Existing shops take their seller's country when it has a market.
        foreach (DB::table('shops')->join('sellers', 'sellers.id', '=', 'shops.seller_id')->get(['shops.id', 'sellers.country']) as $row) {
            $country = strtoupper((string) $row->country);
            if (is_array(config('markets.'.$country))) {
                DB::table('shops')->where('id', $row->id)->update(['market' => $country]);
            }
        }
        DB::statement('UPDATE products p JOIN shops s ON s.id = p.shop_id SET p.market = s.market');
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['hsn_code', 'gst_rate_bps']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['market', 'currency', 'tax_included_cents']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['market', 'hsn_code', 'gst_rate_bps', 'country_of_origin', 'manufacturer_info']));
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('market'));
    }
};
