<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The homepage category carousel now comes straight from categories (the
     * admin "Homepage" tiles list only ever mirrored them). Carry over which
     * categories had an active tile, and the tiles' order.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(true)->after('is_active');
        });

        if (! Schema::hasTable('home_tiles') || ! DB::table('home_tiles')->exists()) {
            return;
        }

        $tiles = DB::table('home_tiles')->where('is_active', true)->whereNotNull('category_slug')->orderBy('sort_order')->get(['category_slug', 'sort_order']);
        $onHome = $tiles->pluck('category_slug')->all();

        DB::table('categories')->whereNotIn('slug', $onHome)->update(['show_on_home' => false]);
        foreach ($tiles as $tile) {
            DB::table('categories')->where('slug', $tile->category_slug)->update(['sort_order' => $tile->sort_order]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('show_on_home');
        });
    }
};
