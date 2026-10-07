<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lightning deals set by the seller (or admin): a time window and the units on
// offer; the storefront shows a countdown and how much has been claimed.
// (Unbeatable deals and Exclusive offers are picked automatically.)
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'lightning_starts_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('lightning_starts_at')->nullable();
                $table->timestamp('lightning_ends_at')->nullable();
                $table->unsignedInteger('lightning_qty')->nullable();
                $table->unsignedInteger('lightning_base_sold')->nullable(); // units_sold when the deal began
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['lightning_starts_at', 'lightning_ends_at', 'lightning_qty', 'lightning_base_sold']));
    }
};
