<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What each signed-in shopper looks at and buys, by category — the home page
// "Recommended" list shows their most recently viewed categories first, and
// lowers a category they've just bought from.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_interests')) {
            Schema::create('category_interests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('views')->default(0);
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamp('last_bought_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'category_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_interests');
    }
};
