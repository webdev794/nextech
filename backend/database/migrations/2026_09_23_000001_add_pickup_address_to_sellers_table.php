<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Where a courier should collect this seller's orders from —
            // distinct from the registered/KYC address above, which is a
            // legal address and not necessarily where stock actually sits.
            $table->boolean('pickup_same_as_registered')->default(true)->after('registered_country');
            $table->string('pickup_phone', 32)->nullable()->after('pickup_same_as_registered');
            $table->string('pickup_line1')->nullable()->after('pickup_phone');
            $table->string('pickup_line2')->nullable()->after('pickup_line1');
            $table->string('pickup_city', 100)->nullable()->after('pickup_line2');
            $table->string('pickup_state', 60)->nullable()->after('pickup_city');
            $table->string('pickup_postal_code', 12)->nullable()->after('pickup_state');
            $table->string('pickup_country', 2)->nullable()->after('pickup_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_same_as_registered', 'pickup_phone', 'pickup_line1', 'pickup_line2',
                'pickup_city', 'pickup_state', 'pickup_postal_code', 'pickup_country',
            ]);
        });
    }
};
