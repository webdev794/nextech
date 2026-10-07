<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Affiliate products: admin's own product listings that link to a partner's
// page (affiliate link) instead of being sold here. Shown among products,
// marked "Ad"; a click opens the partner's page in a new tab and is counted.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'affiliate_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('affiliate_url', 1000)->nullable();
                $table->string('affiliate_merchant', 80)->nullable();
                $table->unsignedInteger('affiliate_clicks')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['affiliate_url', 'affiliate_merchant', 'affiliate_clicks']));
    }
};
