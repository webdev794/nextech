<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seller-written text sections on a product page: FAQs, Specifications,
// System requirements (e.g. Windows version, RAM for a game) or their own.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('info_sections')->nullable()->after('bullet_points');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('info_sections'));
    }
};
