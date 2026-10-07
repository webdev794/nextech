<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seller Center onboarding tasks after approval (modelled on Temu's):
 *  1. tax information   — tax registration number (+ certificate) and the
 *     default item tax code; reviewed by NexTech (1–3 business days)
 *  2. compliance        — business roles (ultimate beneficial owners,
 *     directors, executives) and corporate documents; reviewed
 *  3. bank account      — account details + a recent bank document, verified
 *     by NexTech (1–2 business days) before payouts can be requested
 * Shipping templates (task 4) already live in the shipping tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->json('tax_info')->nullable()->after('payout_details');
            $table->string('tax_status', 16)->nullable()->after('tax_info'); // pending|approved|rejected
            $table->string('tax_note', 500)->nullable()->after('tax_status');
            $table->timestamp('tax_submitted_at')->nullable()->after('tax_note');
            $table->json('compliance')->nullable()->after('tax_submitted_at');
            $table->string('compliance_status', 16)->nullable()->after('compliance'); // pending|approved|rejected
            $table->string('compliance_note', 500)->nullable()->after('compliance_status');
            $table->timestamp('compliance_submitted_at')->nullable()->after('compliance_note');
            $table->string('bank_status', 16)->nullable()->after('compliance_submitted_at'); // processing|linked|failed
            $table->string('bank_note', 500)->nullable()->after('bank_status');
            $table->timestamp('bank_submitted_at')->nullable()->after('bank_note');
            $table->timestamp('bank_verified_at')->nullable()->after('bank_submitted_at');
        });

        // Payout details saved before verification existed stay usable.
        DB::table('sellers')->whereNotNull('payout_method')->update(['bank_status' => 'linked', 'bank_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn([
                'tax_info', 'tax_status', 'tax_note', 'tax_submitted_at',
                'compliance', 'compliance_status', 'compliance_note', 'compliance_submitted_at',
                'bank_status', 'bank_note', 'bank_submitted_at', 'bank_verified_at',
            ]);
        });
    }
};
