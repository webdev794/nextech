<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status')->default('pending_payment')->index();
            $table->string('payment_status')->default('pending')->index();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('delivery_fee_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->json('delivery_address');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('orders'); }
};