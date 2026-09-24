<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The short "Seller Terms & Conditions" page is superseded by the NexTech
     * Seller Services Agreement (whose Section 3 now covers commission and
     * payouts). Unpublish it rather than delete, so admin can still see it.
     */
    public function up(): void
    {
        DB::table('pages')->where('slug', 'seller-terms')->update(['is_published' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'seller-terms')->update(['is_published' => true, 'updated_at' => now()]);
    }
};
