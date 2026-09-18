<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('support_thread_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('reason')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('refunded_amount_cents')->default(0)->after('stripe_refund_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_amount_cents');
        });
        Schema::dropIfExists('order_refunds');
    }
};
