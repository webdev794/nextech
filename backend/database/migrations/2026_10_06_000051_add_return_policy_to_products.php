<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The seller's return conditions per product: which cases are accepted
 * (stopped working, arrived damaged…), which aren't (dropped, liquid,
 * mishandling…), plus a free-text note. {accepts: [], excludes: [], notes}
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'return_policy')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('return_policy')->nullable()->after('return_days');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('return_policy'));
    }
};
