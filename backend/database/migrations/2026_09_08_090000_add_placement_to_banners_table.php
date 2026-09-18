<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // hero = full-width top banner; strip = the Blinkit-style 3-up row.
            $table->string('placement')->default('hero')->after('link_url');
        });

        // Existing installs: keep the first as the hero, the rest as strip.
        $ids = \Illuminate\Support\Facades\DB::table('banners')
            ->orderBy('sort_order')->orderBy('id')->pluck('id');
        if ($ids->count() > 1) {
            \Illuminate\Support\Facades\DB::table('banners')
                ->whereIn('id', $ids->slice(1))
                ->update(['placement' => 'strip']);
        }
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
    }
};
