<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Riders rate the store (seller / NexTech) and the buyer of a delivery — seen only by admin. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rider_feedback')) {
            Schema::create('rider_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete(); // null = NexTech's own store
                $table->unsignedTinyInteger('store_rating')->nullable();
                $table->unsignedTinyInteger('buyer_rating')->nullable();
                $table->string('note', 300)->nullable();
                $table->timestamps();
                $table->unique(['rider_id', 'order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_feedback');
    }
};
