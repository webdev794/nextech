<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The store that fulfils the order — the nearest store whose delivery
        // radius covers the delivery address at checkout. Nullable: orders
        // placed before multi-store was set up (or with no stores configured)
        // have none, and deleting a store leaves its past orders intact.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
