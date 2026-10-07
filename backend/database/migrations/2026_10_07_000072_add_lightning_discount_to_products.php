<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lightning deals set their own discount: the % off during the deal (price
// goes back after), optionally picked each round from a range, and repeated
// automatically when a round ends.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'lightning_pct')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedTinyInteger('lightning_pct')->nullable();
                $table->unsignedTinyInteger('lightning_pct_min')->nullable();
                $table->unsignedTinyInteger('lightning_pct_max')->nullable();
                $table->boolean('lightning_repeat')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['lightning_pct', 'lightning_pct_min', 'lightning_pct_max', 'lightning_repeat']));
    }
};
