<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seller-uploaded PDFs on a product page ("Product guides and documents"):
// user manuals, quick-start guides, warranty cards — one per name/language.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'guides')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('guides')->nullable()->after('info_sections');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('guides'));
    }
};
