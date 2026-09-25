<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which kinds of delivery address a shipping group covers ("Address type" on
 * Temu templates): standard street addresses, PO boxes and — in the US —
 * military APO/FPO/DPO addresses. Null (groups saved before this) = all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_template_groups', function (Blueprint $table) {
            $table->json('address_types')->nullable()->after('regions');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_template_groups', function (Blueprint $table) {
            $table->dropColumn('address_types');
        });
    }
};
