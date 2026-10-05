<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin can approve a product before the seller has filled in every detail
// (e.g. HSN / GST rate, compliance documents) so the shop can start selling;
// what's still missing is kept here and shown to the seller to add soon.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('followup_items')->nullable()->after('rejection_reason');
            $table->timestamp('followup_requested_at')->nullable()->after('followup_items');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['followup_items', 'followup_requested_at']));
    }
};
