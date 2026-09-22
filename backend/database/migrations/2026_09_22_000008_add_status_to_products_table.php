<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Default 'approved' backfills every existing row (admin-created
            // products keep going live immediately, zero behavior change).
            // Only a seller-submitted product is ever created 'pending'.
            $table->string('status', 20)->default('approved')->after('shop_id');
            $table->text('rejection_reason')->nullable()->after('status');
            // A seller's free-text "nothing existing fits" note, for admin to
            // act on manually — never turned into a real category automatically.
            $table->string('suggested_category_name', 160)->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_reason', 'suggested_category_name']);
        });
    }
};
