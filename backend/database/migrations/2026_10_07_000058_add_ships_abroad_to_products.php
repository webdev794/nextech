<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per product: also sold to the other countries the shop ships to (Shipping
// settings → International shipping), and an extra per-item shipping charge
// for those orders (e.g. heavy or bulky items). On by default — as before.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'ships_abroad')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('ships_abroad')->default(true);
                $table->unsignedInteger('intl_extra_fee_cents')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['ships_abroad', 'intl_extra_fee_cents']));
    }
};
