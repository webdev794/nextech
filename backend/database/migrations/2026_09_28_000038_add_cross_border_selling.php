<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cross-border selling: the countries a seller ships to (with fee and transit
// days per country, in the seller's currency), the conversion rates frozen on
// an order that has items from another country's sellers, and — on those
// orders — the seller's own price and shipping fee in their currency, so the
// seller is credited exactly what they listed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->json('intl_shipping')->nullable()->after('working_holidays');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->json('fx_rates')->nullable()->after('currency');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->bigInteger('seller_line_total_cents')->nullable()->after('line_total_cents');
        });
        Schema::table('order_shop_shipping', function (Blueprint $table) {
            $table->bigInteger('seller_fee_cents')->nullable()->after('fee_cents');
        });
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('intl_shipping'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('fx_rates'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('seller_line_total_cents'));
        Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn('seller_fee_cents'));
    }
};
