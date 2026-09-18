<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This is a grocery app, not a food-prep one: "preparing" becomes "packing",
     * and a "ready_for_delivery" step is added between packing and dispatch.
     * Only the rename needs a data fix; the new step has no historical rows.
     */
    public function up(): void
    {
        DB::table('orders')->where('status', 'preparing')->update(['status' => 'packing']);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'ready_for_delivery')->update(['status' => 'packing']);
        DB::table('orders')->where('status', 'packing')->update(['status' => 'preparing']);
    }
};
