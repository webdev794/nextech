<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seller fulfillment, modelled on Temu's Seller Center:
     *  - each shop picks how its orders ship: NexTech collects & delivers
     *    ("nextech", the original behaviour), the seller ships with their own
     *    courier ("self"), or the seller ships on a label bought through
     *    NexTech's courier account ("label", postage deducted from earnings);
     *  - self/label shops keep ship-from addresses, shipping templates (region
     *    groups with transit days + fee) and working days / holidays;
     *  - their items on an order ship as seller packages with a carrier and
     *    tracking number.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('fulfillment_mode', 12)->default('nextech')->after('is_active'); // nextech | self | label
            $table->boolean('ships_saturday')->default(false)->after('fulfillment_mode');
            $table->boolean('ships_sunday')->default(false)->after('ships_saturday');
            $table->json('working_holidays')->nullable()->after('ships_sunday'); // holiday keys the shop still works on
            $table->timestamp('free_shipping_accepted_at')->nullable()->after('working_holidays');
        });

        Schema::create('shop_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 2);
            $table->string('postal_code', 12);
            $table->string('country', 2)->default('US');
            $table->string('phone', 32);
            $table->string('contact_name', 120);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('shipping_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('product_type', 30)->default('standard'); // standard | oversized | fragile
            $table->foreignId('shop_address_id')->nullable()->constrained('shop_addresses')->nullOnDelete();
            $table->unsignedTinyInteger('handling_days')->default(1); // working days to hand over to the carrier
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('shipping_template_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_template_id')->constrained()->cascadeOnDelete();
            $table->json('regions'); // US state codes, or ["ALL"] for every state not in another group
            $table->unsignedTinyInteger('transit_min_days');
            $table->unsignedTinyInteger('transit_max_days');
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('shipping_template_id')->nullable()->after('return_days')->constrained('shipping_templates')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Who ships this line, frozen at checkout: "nextech" or "seller".
            $table->string('fulfilled_by', 10)->default('nextech')->after('shop_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('seller_shipping_cents')->default(0)->after('delivery_fee_cents');
        });

        // What each self/label-shipping seller charged on an order, and the
        // delivery promise made to the customer.
        Schema::create('order_shop_shipping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 12); // self | label
            $table->unsignedInteger('fee_cents')->default(0);
            $table->boolean('free_shipping')->default(false);
            $table->unsignedTinyInteger('transit_min_days');
            $table->unsignedTinyInteger('transit_max_days');
            $table->date('ship_by');
            $table->date('deliver_from');
            $table->date('deliver_by');
            $table->timestamps();
            $table->unique(['order_id', 'shop_id']);
        });

        Schema::create('order_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ship_from_address_id')->nullable()->constrained('shop_addresses')->nullOnDelete();
            $table->string('label_source', 10)->default('own'); // own | nextech
            $table->string('carrier', 40);
            $table->string('tracking_number', 60);
            $table->string('label_url', 500)->nullable();
            $table->unsignedInteger('label_cost_cents')->default(0);
            $table->string('status', 20)->default('shipped'); // shipped | in_transit | delivered | returned | lost
            $table->timestamp('shipped_at');
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedTinyInteger('edit_count')->default(0);
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::create('order_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_package_items');
        Schema::dropIfExists('order_packages');
        Schema::dropIfExists('order_shop_shipping');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('seller_shipping_cents'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('fulfilled_by'));
        Schema::table('products', fn (Blueprint $t) => $t->dropConstrainedForeignId('shipping_template_id'));
        Schema::dropIfExists('shipping_template_groups');
        Schema::dropIfExists('shipping_templates');
        Schema::dropIfExists('shop_addresses');
        Schema::table('shops', fn (Blueprint $t) => $t->dropColumn(['fulfillment_mode', 'ships_saturday', 'ships_sunday', 'working_holidays', 'free_shipping_accepted_at']));
    }
};
