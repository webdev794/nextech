<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // On shift / accepting deliveries — distinct from `is_rider` (the
            // role). An admin can pause a rider without removing the role.
            $table->boolean('rider_is_active')->default(true)->after('is_rider');

            // Home base: where the rider starts from when they have no live fix.
            $table->string('rider_base_address')->nullable()->after('rider_is_active');
            $table->decimal('rider_base_lat', 10, 7)->nullable()->after('rider_base_address');
            $table->decimal('rider_base_lng', 10, 7)->nullable()->after('rider_base_lat');

            // Last live position pinged by the rider app.
            $table->decimal('rider_last_lat', 10, 7)->nullable()->after('rider_base_lng');
            $table->decimal('rider_last_lng', 10, 7)->nullable()->after('rider_last_lat');
            $table->timestamp('rider_last_located_at')->nullable()->after('rider_last_lng');
        });

        // A rider serves one or more stores (nearby cities can share a rider).
        Schema::create('rider_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['user_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_store');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'rider_is_active', 'rider_base_address', 'rider_base_lat', 'rider_base_lng',
                'rider_last_lat', 'rider_last_lng', 'rider_last_located_at',
            ]);
        });
    }
};
