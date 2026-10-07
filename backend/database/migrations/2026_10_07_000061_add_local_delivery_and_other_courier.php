<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sellers' own local delivery and couriers not on our list:
//  - shops.local_delivery: {radius_km, fee_cents, days, address_id, lat, lng}; null = off.
//  - order_shop_shipping.method: 'local' when the seller delivers it themselves.
//  - order_packages: the courier's name and tracking website for "Other carrier",
//    and the buyer's delivery code for own-delivery packages.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('shops', 'local_delivery')) {
            Schema::table('shops', fn (Blueprint $table) => $table->json('local_delivery')->nullable());
        }
        if (! Schema::hasColumn('order_shop_shipping', 'method')) {
            Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->string('method', 16)->nullable());
        }
        if (! Schema::hasColumn('order_packages', 'carrier_name')) {
            Schema::table('order_packages', function (Blueprint $table) {
                $table->string('carrier_name', 60)->nullable();
                $table->string('tracking_site', 255)->nullable();
                $table->string('delivery_code', 8)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('local_delivery'));
        Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn('method'));
        Schema::table('order_packages', fn (Blueprint $table) => $table->dropColumn(['carrier_name', 'tracking_site', 'delivery_code']));
    }
};
