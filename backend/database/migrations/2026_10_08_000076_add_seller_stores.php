<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sellers' stores in Stores / hubs: a store can belong to a seller's shop (their
 * local-delivery base, with their own riders) instead of NexTech. Null shop_id =
 * NexTech's own store, as before. Local delivery can be switched on or off per seller store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('stores', 'local_delivery_active')) {
                $table->boolean('local_delivery_active')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'shop_id')) {
                $table->dropConstrainedForeignId('shop_id');
            }
            if (Schema::hasColumn('stores', 'local_delivery_active')) {
                $table->dropColumn('local_delivery_active');
            }
        });
    }
};
