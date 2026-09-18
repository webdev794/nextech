<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-store stock. One row per (store, product, variant); a null variant
        // is the base product. `is_stocked` = the store carries this line at all
        // (it can be true with quantity 0 = "temporarily out"); no row = the
        // store does not carry it.
        //
        // A product with NO rows anywhere stays in "single stock" mode and uses
        // products.inventory_quantity / product_variants.inventory_quantity, so
        // single-store installs and freshly created products keep working.
        Schema::create('store_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('is_stocked')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'product_id', 'product_variant_id']);
            $table->index(['product_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory');
    }
};
