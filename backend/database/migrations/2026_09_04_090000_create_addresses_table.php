<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('label')->default('Home');
            $table->string('name', 120);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->char('state', 2);
            $table->string('postal_code', 10);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void { Schema::dropIfExists('addresses'); }
};