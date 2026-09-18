<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store credit issued as a refund (e.g. missing items on a cash order).
        // Redeemable only by the customer it was issued to, with code + password.
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('pin_hash');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the customer
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // the order the refund is for
            $table->unsignedInteger('initial_cents');
            $table->unsignedInteger('balance_cents');
            $table->string('reason', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // One row per time a card is applied to an order (partial use is allowed).
        Schema::create('gift_card_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('gift_card_discount_cents')->default(0)->after('small_cart_fee_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('gift_card_discount_cents');
        });
        Schema::dropIfExists('gift_card_redemptions');
        Schema::dropIfExists('gift_cards');
    }
};
