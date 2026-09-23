<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('shop_code', 8)->nullable()->unique()->after('slug');
            $table->unsignedInteger('next_product_seq')->default(1)->after('shop_code');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('next_variant_seq')->default(1)->after('sku');
        });

        // Backfill existing shops (created before SKU auto-generation existed)
        // with a code, in id order, using the same 3-letter-base + digit
        // collision rule the app uses for new shops going forward.
        $used = [];
        foreach (DB::table('shops')->orderBy('id')->get(['id', 'name']) as $shop) {
            $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $shop->name ?? ''));
            $letters = str_pad($letters === '' ? 'SHOP' : $letters, 4, 'X');
            $base3 = substr($letters, 0, 3);

            $code = $base3.substr($letters, 3, 1);
            if (isset($used[$code])) {
                $digit = 2;
                while (isset($used[$code = $base3.$digit])) {
                    $digit++;
                }
            }

            $used[$code] = true;
            DB::table('shops')->where('id', $shop->id)->update(['shop_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['shop_code', 'next_product_seq']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('next_variant_seq');
        });
    }
};
