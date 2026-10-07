<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Personalized products: the seller lets buyers upload photos (and optionally
// type a note) when ordering — e.g. a printed mug or a photo case. The
// product holds the settings; each cart line and order line holds the
// buyer's photos. Two cart lines of the same product with different photos
// are separate lines, so the unique key includes a personalization key.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('personalization')->nullable()->after('compliance');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->json('personalization')->nullable()->after('unit_price_cents');
            $table->string('personalization_key', 40)->default('')->after('personalization');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_id_product_id_product_variant_id_unique');
            $table->unique(['cart_id', 'product_id', 'product_variant_id', 'personalization_key'], 'cart_items_line_unique');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('personalization')->nullable()->after('variant_label');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_line_unique');
            $table->unique(['cart_id', 'product_id', 'product_variant_id']);
            $table->dropColumn(['personalization', 'personalization_key']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('personalization'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('personalization'));
    }
};
