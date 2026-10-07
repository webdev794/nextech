<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Digital products (games, software, e-books, music, templates...): the
// seller uploads the files (kept private) or gives a download link, and can
// add license keys; buyers download from "Your downloads" once paid. No
// stock to ship, no delivery, no returns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 16)->default('physical')->after('market');
            $table->json('digital_settings')->nullable()->after('personalization');
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);                 // what buyers see, e.g. "Windows installer"
            $table->string('original_name', 255)->nullable();
            $table->string('path', 500)->nullable();     // private storage (storage/app/private/digital/...)
            $table->string('external_url', 1000)->nullable(); // or a link the seller hosts
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_license_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('license_key');                 // encrypted
            $table->string('key_hash', 64);              // duplicate check without decrypting
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'key_hash']);
            $table->index(['product_id', 'order_item_id']);
        });

        Schema::create('digital_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['order_item_id', 'product_file_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('digital_ready_at')->nullable()->after('personalization');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('digital_ready_at'));
        Schema::dropIfExists('digital_downloads');
        Schema::dropIfExists('product_license_keys');
        Schema::dropIfExists('product_files');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['product_type', 'digital_settings']));
    }
};
