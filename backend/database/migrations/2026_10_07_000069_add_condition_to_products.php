<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Product condition: null = new; 'refurbished' = second-hand / refurbished,
// shown to shoppers as a "Refurbished" tag (optional, set by the seller or admin).
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'condition')) {
            Schema::table('products', fn (Blueprint $table) => $table->string('condition', 16)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('condition'));
    }
};
