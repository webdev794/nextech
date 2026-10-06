<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categories become a tree (parent → child → grandchild) and each tree is
 * Physical or Digital (downloads). Children take their top-level category's
 * kind. Downloadable becomes Digital with starter subcategories.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
                $table->string('kind', 10)->default('physical')->after('parent_id')->index();
            });
        }

        $root = DB::table('categories')->where('slug', 'downloadable')->first();
        if (! $root) {
            return;
        }
        DB::table('categories')->where('id', $root->id)->update(['kind' => 'digital']);

        $add = function (string $name, string $slug, int $parentId, int $sort): int {
            $existing = DB::table('categories')->where('slug', $slug)->value('id');
            if ($existing) {
                return (int) $existing;
            }

            return (int) DB::table('categories')->insertGetId([
                'parent_id' => $parentId, 'kind' => 'digital', 'name' => $name, 'slug' => $slug,
                'is_active' => true, 'show_on_home' => false, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $children = [
            'games' => 'Games', 'software-apps' => 'Software & apps', 'ebooks-pdfs' => 'E-books & PDFs',
            'music-audio' => 'Music & audio', 'video-courses' => 'Video & courses', 'templates-design' => 'Templates & design',
        ];
        $sort = 1;
        $ids = [];
        foreach ($children as $slug => $name) {
            $ids[$slug] = $add($name, $slug, (int) $root->id, $sort++);
        }
        $games = [
            'arcade-games' => 'Arcade', 'card-board-games' => 'Card & board', 'action-adventure-games' => 'Action & adventure',
            'puzzle-games' => 'Puzzle', 'strategy-games' => 'Strategy', 'sports-racing-games' => 'Sports & racing',
        ];
        $sort = 1;
        foreach ($games as $slug => $name) {
            $add($name, $slug, $ids['games'], $sort++);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('kind');
        });
    }
};
