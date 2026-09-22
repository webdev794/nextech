<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // bank|paypal — where admin manually sends this seller's payouts.
            $table->string('payout_method', 16)->nullable()->after('submitted_at');
            // Bank: {holder_name, account_number, routing_number, bank_name}. PayPal: {email}.
            $table->json('payout_details')->nullable()->after('payout_method');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'payout_details']);
        });
    }
};
