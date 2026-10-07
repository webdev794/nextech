<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A seller's request to change an approved trademark (name / registration):
// held for admin, who approves, rejects or asks for documents first.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('trademarks', 'change_request')) {
            Schema::table('trademarks', function (Blueprint $table) {
                $table->json('change_request')->nullable();
                $table->string('change_status', 16)->nullable();
                $table->string('change_note', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('trademarks', fn (Blueprint $table) => $table->dropColumn(['change_request', 'change_status', 'change_note']));
    }
};
