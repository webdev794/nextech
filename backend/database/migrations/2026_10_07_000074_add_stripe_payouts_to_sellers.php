<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stripe payouts: the seller's Stripe connected account and whether it can receive transfers yet. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'stripe_account_id')) {
                $table->string('stripe_account_id', 64)->nullable()->after('payout_details');
            }
            if (! Schema::hasColumn('sellers', 'stripe_ready')) {
                $table->boolean('stripe_ready')->default(false)->after('stripe_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            foreach (['stripe_ready', 'stripe_account_id'] as $column) {
                if (Schema::hasColumn('sellers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
