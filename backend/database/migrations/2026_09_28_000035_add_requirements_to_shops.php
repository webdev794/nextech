<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-seller switches for the stricter seller rules (compliance documents,
 * full listing checks, shipping setup, bank verification, onboarding tasks,
 * store design minimum). Admin turns them on per shop; all off by default
 * so a new seller can start selling straight away (App\Support\SellerRequirements).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('decoration_terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });
    }
};
