<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        // The cart_id foreign key currently relies on the (cart_id, product_id)
        // unique index; give it a dedicated index so that unique can be replaced.
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->unique(['cart_id', 'product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id', 'product_variant_id']);
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex(['cart_id']);
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
