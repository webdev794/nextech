<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_redemptions', function (Blueprint $table) {
            // Stamped when the order it paid for is cancelled and the spent
            // balance is credited back to the card. Null while still in effect.
            $table->timestamp('reversed_at')->nullable()->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_redemptions', function (Blueprint $table) {
            $table->dropColumn('reversed_at');
        });
    }
};
