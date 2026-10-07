<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A seller application saved part-way ("Save and finish later", and at each
// step), so the applicant can carry on later, on any device.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('users', 'seller_application_draft')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('seller_application_draft')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('seller_application_draft'));
    }
};
