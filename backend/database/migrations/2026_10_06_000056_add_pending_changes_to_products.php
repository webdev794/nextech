<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A seller's edit to a live product waits here for review; the live version
// keeps selling until admin approves (then it's applied) or rejects it.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'pending_changes')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('pending_changes')->nullable();
                $table->timestamp('pending_submitted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['pending_changes', 'pending_submitted_at']));
    }
};
