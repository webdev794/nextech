<?php

use App\Models\Shop;
use App\Support\SellerStores;
use Illuminate\Database\Migrations\Migration;

/** Sellers already offering own delivery (local) get their store in Stores / hubs. */
return new class extends Migration
{
    public function up(): void
    {
        Shop::query()->whereNotNull('local_delivery')->get()->each(fn (Shop $shop) => SellerStores::sync($shop));
    }

    public function down(): void {}
};
