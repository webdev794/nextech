<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store decoration (Seller Center -> My account -> Store decoration),
 * modelled on Temu's: a shop designs its store page separately for desktop
 * and mobile, as versions made of drag-and-drop sections. A submitted
 * version is checked automatically and may be spot-checked by NexTech; an
 * approved version can be published — one live version per platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_decorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 8); // desktop | mobile
            $table->string('name', 80);
            $table->string('status', 12)->default('draft'); // draft | in_review | approved | rejected
            $table->boolean('is_live')->default(false);
            $table->json('page')->nullable();     // background image / colours / theme
            $table->json('sections')->nullable(); // the ordered sections
            $table->string('review_note', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'platform', 'is_live']);
            $table->index('status');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('decoration_terms_accepted_at')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('decoration_terms_accepted_at');
        });
        Schema::dropIfExists('store_decorations');
    }
};
