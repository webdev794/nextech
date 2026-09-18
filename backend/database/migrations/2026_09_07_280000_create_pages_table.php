<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content')->nullable(); // Markdown
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->string('footer_group')->default('useful_links');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'show_in_footer', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
