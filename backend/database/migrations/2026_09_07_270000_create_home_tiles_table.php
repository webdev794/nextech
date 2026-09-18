<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_tiles', function (Blueprint $table) {
            $table->id();
            // All optional: title / image fall back to the linked category; the
            // link target is the category (by slug) or a custom URL.
            $table->string('title')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category_slug')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_tiles');
    }
};
