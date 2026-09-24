<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seller products saved before the gallery's first photo was copied into
     * image_url have no main image — fill it from their gallery.
     */
    public function up(): void
    {
        $firstUrls = DB::table('product_images')
            ->select('product_id', 'url')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique('product_id');

        foreach ($firstUrls as $image) {
            DB::table('products')
                ->where('id', $image->product_id)
                ->where(fn ($q) => $q->whereNull('image_url')->orWhere('image_url', ''))
                ->update(['image_url' => $image->url]);
        }
    }

    public function down(): void
    {
        // Harmless to keep; nothing to undo.
    }
};
