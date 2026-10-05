<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A seller no longer has a product that's on past orders (so it can't be
// deleted): they hide it and ask NexTech to remove it. Removing archives it —
// gone from the catalog and the seller's list, kept for those orders.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('deactivated_by');
            $table->string('deletion_reason', 500)->nullable()->after('deletion_requested_at');
            $table->timestamp('archived_at')->nullable()->after('deletion_reason');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['deletion_requested_at', 'deletion_reason', 'archived_at']));
    }
};
