<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller product listing modelled on Temu's Add product flow:
 *  - products: bullet points, detail images / detail video, trademark,
 *    category attributes ("product details"), a variation theme of up to two
 *    types, a size chart (apparel), handling time, compliance documents and
 *    links backing up the price. `status` gains 'draft' (Manage products ->
 *    Incomplete).
 *  - product_variants: option values per variation type, weight and size.
 *  - trademarks a seller registers (Account health) for NexTech to review.
 *  - sales boost offers (a recommended price for a "Low traffic" product)
 *    and the pricing records left when a seller accepts one.
 *  - bulk product upload tasks (Add products via upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('bullet_points')->nullable()->after('description');
            $table->json('detail_images')->nullable()->after('bullet_points');
            $table->string('detail_video_url', 500)->nullable()->after('video_url');
            $table->unsignedBigInteger('trademark_id')->nullable()->after('shop_id')->index();
            $table->json('product_details')->nullable()->after('detail_images');
            $table->json('variation_theme')->nullable()->after('product_details');
            $table->json('size_chart')->nullable()->after('variation_theme');
            $table->unsignedTinyInteger('handling_days')->nullable()->after('shipping_template_id');
            $table->json('compliance')->nullable()->after('manufacturer_info');
            $table->json('price_references')->nullable()->after('compare_at_price_cents');
            $table->string('seller_code', 60)->nullable()->after('sku');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('options')->nullable()->after('label');
            $table->string('seller_code', 60)->nullable()->after('sku');
            $table->unsignedInteger('weight_grams')->nullable()->after('inventory_quantity');
            $table->unsignedInteger('length_mm')->nullable()->after('weight_grams');
            $table->unsignedInteger('width_mm')->nullable()->after('length_mm');
            $table->unsignedInteger('height_mm')->nullable()->after('width_mm');
        });

        Schema::create('trademarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('registration_number', 60);
            $table->string('registration_country', 2);
            $table->string('logo_url', 500)->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->string('note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::create('sales_boost_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_price_cents');
            $table->unsignedInteger('recommended_price_cents');
            $table->string('status', 12)->default('pending'); // pending | accepted | rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('price_change_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('old_price_cents');
            $table->unsignedInteger('new_price_cents');
            $table->string('source', 20)->default('sales_boost');
            $table->foreignId('sales_boost_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_upload_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path')->nullable();
            $table->string('status', 16)->default('processing'); // processing | completed | action_required
            $table->unsignedInteger('records')->default(0);
            $table->unsignedInteger('error_records')->default(0);
            $table->json('rows')->nullable();    // the parsed sheet rows, as uploaded
            $table->json('results')->nullable(); // per row: ok / draft / error + messages
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_upload_tasks');
        Schema::dropIfExists('price_change_records');
        Schema::dropIfExists('sales_boost_offers');
        Schema::dropIfExists('trademarks');
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['options', 'seller_code', 'weight_grams', 'length_mm', 'width_mm', 'height_mm']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['bullet_points', 'detail_images', 'detail_video_url', 'trademark_id', 'product_details', 'variation_theme', 'size_chart', 'handling_days', 'compliance', 'price_references', 'seller_code']);
        });
    }
};
