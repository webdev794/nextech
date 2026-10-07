<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sellers' registered address: a proof-of-address document at sign-up, admin's
// "address checked" on approval, and the history of earlier addresses (it can
// only change when admin asks for it, and the old one is kept).
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('sellers', 'address_document_path')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->string('address_document_path', 255)->nullable();
                $table->timestamp('address_verified_at')->nullable();
                $table->unsignedBigInteger('address_verified_by')->nullable();
                $table->json('registered_history')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('sellers', fn (Blueprint $table) => $table->dropColumn(['address_document_path', 'address_verified_at', 'address_verified_by', 'registered_history']));
    }
};
