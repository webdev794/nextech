<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product reviews from buyers (one per purchased order item), with photos.
 * Every review waits for admin approval before anyone else sees it. An
 * approved review shows on the product page and — unless admin kept it to
 * the product page only — on the reviewer's public profile, where shoppers
 * can mark it helpful.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('variant_label', 120)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('fit', 16)->nullable(); // small | true_to_size | large
            $table->json('images')->nullable();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->boolean('show_on_profile')->default(true);
            $table->string('admin_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        Schema::create('review_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_helpful_votes');
        Schema::dropIfExists('product_reviews');
    }
};
