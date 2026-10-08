<?php

use App\Models\Shop;
use App\Support\SellerStores;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every seller shop has a store in Stores / hubs (local delivery off until switched
 * on), and a seller's request to turn local delivery off waits for admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'local_delivery_off_requested_at')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->timestamp('local_delivery_off_requested_at')->nullable()->after('local_delivery_active');
            });
        }
        Shop::query()->whereNotNull('seller_id')->get()->each(fn (Shop $shop) => SellerStores::ensure($shop));
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'local_delivery_off_requested_at')) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('local_delivery_off_requested_at'));
        }
    }
};
