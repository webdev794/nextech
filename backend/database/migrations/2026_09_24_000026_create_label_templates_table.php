<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-designed shipping label templates. A seller's label request is
 * turned into a PDF from a template straight away (the order's addresses
 * filled in), so nobody waits on an admin; the seller can switch template
 * and re-download at any time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('size', 8)->default('4x6'); // 4x6 | a6 | a4
            $table->string('header_text', 80)->nullable();
            $table->string('logo_url', 500)->nullable(); // null = the store logo
            $table->string('footer_note', 300)->nullable();
            $table->boolean('show_items')->default(true);
            $table->boolean('show_phone')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('label_templates')->insert([
            'name' => 'Standard 4×6 (thermal printer)',
            'size' => '4x6',
            'header_text' => 'NexTech Shipping',
            'footer_note' => 'Handle with care — electronics inside.',
            'show_items' => true,
            'show_phone' => false,
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('label_templates')->insert([
            'name' => 'A4 sheet (regular printer)',
            'size' => 'a4',
            'header_text' => 'NexTech Shipping',
            'footer_note' => 'Cut along the border and tape it to the package.',
            'show_items' => true,
            'show_phone' => false,
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('label_requests', function (Blueprint $table) {
            $table->foreignId('label_template_id')->nullable()->after('label_path')->constrained('label_templates')->nullOnDelete();
        });

        Schema::table('shops', function (Blueprint $table) {
            // The seller's preferred label template (their last choice).
            $table->foreignId('label_template_id')->nullable()->constrained('label_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropConstrainedForeignId('label_template_id'));
        Schema::table('label_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('label_template_id'));
        Schema::dropIfExists('label_templates');
    }
};
