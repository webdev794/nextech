<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Riders with no active store, suggested to a store near their home: the store invites, the rider accepts. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rider_invites')) {
            Schema::create('rider_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 12)->default('suggested'); // suggested · invited · accepted · declined (store) · refused (rider)
                $table->timestamps();
                $table->unique(['store_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_invites');
    }
};
