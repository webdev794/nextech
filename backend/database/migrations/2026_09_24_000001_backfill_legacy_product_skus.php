<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rewrites SKUs created before auto-generation (e.g. "GDP-PROD-001") into
     * the App\Support\Sku format: ADM###### for admin products, SLR{code}####
     * for seller products, and {productSku}-V# for their variants. Order items
     * keep their frozen SKU, so past orders are untouched.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $products = DB::table('products')
                ->where('sku', 'not regexp', '^(ADM[0-9]{6}|SLR[A-Z0-9]{4,}[0-9]{4})$')
                ->orderBy('id')
                ->get(['id', 'shop_id']);

            foreach ($products as $product) {
                if ($product->shop_id === null) {
                    $sku = sprintf('ADM%06d', $product->id);
                } else {
                    $shop = DB::table('shops')->where('id', $product->shop_id)->lockForUpdate()->first(['shop_code', 'next_product_seq']);
                    $sku = sprintf('SLR%s%04d', $shop->shop_code, $shop->next_product_seq);
                    DB::table('shops')->where('id', $product->shop_id)->increment('next_product_seq');
                }

                DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
            }

            // Renumber every variant whose SKU doesn't already hang off its
            // product's (possibly just-rewritten) SKU.
            foreach (DB::table('products')->orderBy('id')->get(['id', 'sku', 'next_variant_seq']) as $product) {
                $seq = $product->next_variant_seq;
                $variants = DB::table('product_variants')
                    ->where('product_id', $product->id)
                    ->where('sku', 'not like', $product->sku.'-V%')
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($variants as $variantId) {
                    DB::table('product_variants')->where('id', $variantId)->update(['sku' => "{$product->sku}-V{$seq}"]);
                    $seq++;
                }

                if ($seq !== $product->next_variant_seq) {
                    DB::table('products')->where('id', $product->id)->update(['next_variant_seq' => $seq]);
                }
            }
        });
    }

    public function down(): void
    {
        // Legacy SKUs aren't recoverable; nothing to undo.
    }
};
