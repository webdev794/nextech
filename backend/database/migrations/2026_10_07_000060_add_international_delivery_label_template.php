<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A printable "International Delivery" sheet sellers download for orders going
// abroad: address label plus a customs declaration. Admin can edit it in
// Settings → Shipping label templates.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (DB::table('label_templates')->where('name', 'International Delivery')->exists()) {
            return;
        }
        DB::table('label_templates')->insert([
            'name' => 'International Delivery',
            'size' => 'a4',
            'header_text' => '{store} — International Delivery',
            'footer_note' => 'Attach one copy to the outside of the parcel and put a second copy inside with the customs paperwork.',
            'show_items' => true,
            'show_phone' => true,
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('label_templates')->where('name', 'International Delivery')->delete();
    }
};
