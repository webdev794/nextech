<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// International orders: the seller's customs / export paperwork charge for
// that country (Shipping settings), shown on the buyer's bill only when set.
// paperwork_cents in the buyer's currency; seller_paperwork_cents in the seller's.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('order_shop_shipping', 'paperwork_cents')) {
            Schema::table('order_shop_shipping', function (Blueprint $table) {
                $table->unsignedInteger('paperwork_cents')->default(0);
                $table->unsignedInteger('seller_paperwork_cents')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn(['paperwork_cents', 'seller_paperwork_cents']));
    }
};
