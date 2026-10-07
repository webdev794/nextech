<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product details a category asks sellers for, editable by admin (Categories →
 * edit → Product details). Null = use the parent's list (or the built-in one).
 * The built-in lists from config/product_catalog.php are copied in so admin can edit them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'detail_fields')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->json('detail_fields')->nullable()->after('kind');
            });
        }
        foreach ((array) config('product_catalog.category_attributes', []) as $slug => $fields) {
            DB::table('categories')->where('slug', $slug)->whereNull('detail_fields')->update(['detail_fields' => json_encode(array_values($fields))]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'detail_fields')) {
            Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('detail_fields'));
        }
    }
};
