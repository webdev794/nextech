<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Selling abroad needs admin approval: shops.intl_approval holds the seller's
// application (export ID, document, signed declaration) and admin's decision.
// Seller policies admin marks as needing acceptance — to sell at all ('selling')
// or to sell abroad ('international') — are read, accepted and signed (typed
// name + date) by each seller; a changed policy needs accepting again.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('shops', 'intl_approval')) {
            Schema::table('shops', fn (Blueprint $table) => $table->json('intl_approval')->nullable());
        }
        if (! Schema::hasColumn('pages', 'acceptance_for')) {
            Schema::table('pages', fn (Blueprint $table) => $table->string('acceptance_for', 16)->nullable());
        }
        if (! Schema::hasTable('policy_acceptances')) {
            Schema::create('policy_acceptances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
                $table->foreignId('page_id')->constrained()->cascadeOnDelete();
                $table->string('page_version', 40); // hash of the text accepted
                $table->string('signed_name', 160);
                $table->string('ip', 45)->nullable();
                $table->timestamp('accepted_at');
                $table->timestamps();
                $table->index(['seller_id', 'page_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acceptances');
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('acceptance_for'));
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('intl_approval'));
    }
};
