<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer asking to change an order's shipping address before it ships.
 * The seller shipping it (or NexTech, for orders it delivers) accepts or
 * declines; accepting rewrites orders.delivery_address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_address_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->json('address');
            $table->string('status', 12)->default('pending'); // pending | approved | declined
            $table->string('note', 500)->nullable();
            $table->foreignId('decided_by_shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_address_changes');
    }
};
