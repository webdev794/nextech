<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Request changes" on a seller application names the exact items to fix
 * (Temu-style), each with an optional note: [{key, note}].
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sellers', 'change_items')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->json('change_items')->nullable()->after('rejection_reason');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sellers', fn (Blueprint $table) => $table->dropColumn('change_items'));
    }
};
