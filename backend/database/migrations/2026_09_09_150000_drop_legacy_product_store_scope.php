<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An earlier build shipped `products.store_scope` + a `product_store` pivot
     * (the all/only/except flag). That was superseded by `store_inventory`
     * before release. Drop the leftovers on any DB that ran the old migration
     * so the schema matches the code.
     */
    public function up(): void
    {
        Schema::dropIfExists('product_store');

        if (Schema::hasColumn('products', 'store_scope')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('store_scope'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'store_scope')) {
            Schema::table('products', fn (Blueprint $table) => $table->string('store_scope', 16)->default('all'));
        }
    }
};
