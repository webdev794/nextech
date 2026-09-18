<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // A rider assignment is an *offer* until the rider accepts it. The
            // offer lapses at this time; a lapsed offer is re-offered to the
            // next rider (or dropped to the pool).
            $table->timestamp('rider_offer_expires_at')->nullable()->after('delivery_partner_id');
            // Set when the rider Accepts (or claims from the pool). Null = still
            // a pending offer.
            $table->timestamp('rider_accepted_at')->nullable()->after('rider_offer_expires_at');
            // users.id of every rider who rejected or missed this order — skipped
            // when re-offering.
            $table->json('rider_offer_declined_ids')->nullable()->after('rider_accepted_at');
            // Running total of rejects + misses on this order (admin display).
            $table->unsignedInteger('rider_offer_decline_count')->default(0)->after('rider_offer_declined_ids');

            $table->index('rider_offer_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['rider_offer_expires_at']);
            $table->dropColumn([
                'rider_offer_expires_at', 'rider_accepted_at',
                'rider_offer_declined_ids', 'rider_offer_decline_count',
            ]);
        });
    }
};
