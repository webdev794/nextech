<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use App\Support\ProductUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    // Bulk import: rows with the same Product group code are one product; SKUs are
    // generated, product images come from the first row, each row's variant image is its own.
    public function test_rows_of_a_group_become_one_product_with_generated_skus_and_variant_images(): void
    {
        $seller = Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self', 'market' => 'US']);
        $category = Category::factory()->create(['name' => 'Gadgets', 'slug' => 'gadgets']);

        $row = fn (string $color, string $variantImage) => [
            'category' => 'Gadgets', 'product_name' => 'Earbuds', 'contribution_goods' => 'P1', 'description' => 'Wireless earbuds',
            'variation_theme' => 'Color', 'variation_value_1' => $color, 'variant_image_url' => $variantImage,
            'image_url_1' => $color === 'Black' ? 'https://img.example/main.jpg' : '', 'image_url_2' => $color === 'Black' ? 'https://img.example/side.jpg' : '',
            'quantity' => '5', 'base_price' => '19.99', 'country_of_origin' => 'China',
        ];
        ProductUpload::process($shop, [$row('Black', 'https://img.example/black.jpg'), $row('White', 'https://img.example/white.jpg')], [$category->id]);

        $product = Product::where('shop_id', $shop->id)->with(['variants', 'images'])->sole();
        $this->assertNotEmpty($product->sku);
        $this->assertSame(['https://img.example/main.jpg', 'https://img.example/side.jpg'], $product->images->pluck('url')->all());
        $this->assertCount(2, $product->variants);
        $this->assertSame(['https://img.example/black.jpg', 'https://img.example/white.jpg'], $product->variants->sortBy('id')->pluck('image_url')->values()->all());
        $this->assertTrue($product->variants->every(fn ($v) => filled($v->sku)));
    }

    // A digital-downloads template: each row is one download, stock is unlimited, the link becomes its file.
    public function test_digital_rows_import_as_downloads_with_their_link(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('PK', 200, ['Content-Type' => 'application/zip', 'Content-Range' => 'bytes 0-0/2048'])]);
        $seller = Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self', 'market' => 'US']);
        $category = Category::factory()->create(['name' => 'Downloadable', 'slug' => 'downloadable']);

        ProductUpload::process($shop, [[
            'category' => 'Downloadable', 'product_name' => 'Space Game', 'description' => 'A game',
            'detail_digital_type' => 'Game', 'detail_platforms' => 'Windows; macOS',
            'base_price' => '9.99', 'download_url' => 'https://www.dropbox.com/s/abc/game.zip?dl=0', 'download_name' => 'Windows installer',
            'download_limit' => '3', 'image_url_1' => 'https://img.example/cover.jpg',
        ]], [$category->id], digital: true);

        $product = Product::where('shop_id', $shop->id)->with('files')->sole();
        $this->assertSame('digital', $product->product_type);
        $this->assertSame(999, $product->price_cents);
        $this->assertSame(['Windows', 'macOS'], $product->product_details['platforms']);
        $this->assertSame(3, $product->digital_settings['download_limit']);
        $this->assertSame('https://www.dropbox.com/s/abc/game.zip?dl=1', $product->files->first()->external_url);
        $this->assertSame('Windows installer', $product->files->first()->name);
    }
}
