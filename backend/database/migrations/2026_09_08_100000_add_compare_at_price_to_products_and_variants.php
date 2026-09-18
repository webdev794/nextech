<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Regular" / was price shown struck-through when it is higher than the
        // selling price (Instacart / Shopify "compare at" convention). Display
        // only — checkout always bills `price_cents`.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('compare_at_price_cents')->nullable()->after('price_cents');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('compare_at_price_cents')->nullable()->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('compare_at_price_cents'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('compare_at_price_cents'));
    }
};
