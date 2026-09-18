<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;

class SeedStoreInventory extends Command
{
    protected $signature = 'inventory:seed-stores
        {--store=* : Limit to these store ids (default: every active store)}
        {--fresh : Overwrite quantities on rows that already exist}';

    protected $description = 'Give every product/variant a per-store stock row for each store, seeded from its current single inventory count.';

    public function handle(): int
    {
        $stores = Store::query()
            ->where('is_active', true)
            ->when($this->option('store'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('No matching active stores.');

            return self::SUCCESS;
        }

        $fresh = (bool) $this->option('fresh');
        $rows = 0;

        Product::with('variants')->chunkById(200, function ($products) use ($stores, $fresh, &$rows) {
            foreach ($products as $product) {
                // The base product, then each variant.
                $lines = [[null, (int) $product->inventory_quantity]];
                foreach ($product->variants as $variant) {
                    $lines[] = [$variant->id, (int) $variant->inventory_quantity];
                }

                foreach ($stores as $store) {
                    foreach ($lines as [$variantId, $qty]) {
                        $existing = $product->storeInventory()
                            ->where('store_id', $store->id)
                            ->where('product_variant_id', $variantId)
                            ->first();

                        if ($existing && ! $fresh) {
                            continue;
                        }

                        $product->storeInventory()->updateOrCreate(
                            ['store_id' => $store->id, 'product_variant_id' => $variantId],
                            ['quantity' => $qty, 'is_stocked' => true],
                        );
                        $rows++;
                    }
                }
            }
        });

        $this->info("Seeded {$rows} store-inventory row(s) across {$stores->count()} store(s).");
        $this->line('Products now use per-store stock. Adjust counts in Admin console → Products.');

        return self::SUCCESS;
    }
}
