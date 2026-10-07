<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slugs hardcoded into the storefront's hamburger "Help" menu
     * (Storefront.jsx) as of this migration — backfilled here since the menu
     * itself doesn't read from the database.
     */
    private const MAIN_MENU_SLUGS = [
        'shipping-info', 'purchase-protection', 'faqs', 'support-center', 'safety-center', 'privacy', 'terms',
    ];

    /** The Seller Center footer's Terms link (Seller.jsx) — same situation. */
    private const SELLER_FOOTER_SLUGS = ['seller-terms'];

    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Which nav/footer surface(s) a page is tracked against, for the
            // admin sidebar's grouping — separate from footer_group, which
            // only decides *where in the main footer* a page lands once
            // 'main_footer' is one of its placements.
            $table->json('menu_placements')->nullable()->after('footer_group');
        });

        // Best-effort backfill from the two places that actually hardcode
        // page slugs today (main nav help menu, seller footer), plus the
        // existing footer_group data for anything already in the main
        // footer or filed as a blog post. A page can land in more than one
        // group (e.g. 'terms' is both a footer link and a main-menu link).
        DB::table('pages')->orderBy('id')->get(['id', 'slug', 'footer_group', 'show_in_footer'])->each(function ($page) {
            $placements = [];

            if (in_array($page->slug, self::MAIN_MENU_SLUGS, true)) {
                $placements[] = 'main_menu';
            }
            if (in_array($page->slug, self::SELLER_FOOTER_SLUGS, true)) {
                $placements[] = 'seller_footer';
            }
            // show_in_footer gates the actual render — a page can carry a
            // footer_group value left over from a prior placement without
            // ever having been shown there.
            if ($page->show_in_footer && in_array($page->footer_group, ['company', 'help', 'legal', 'bottom'], true)) {
                $placements[] = 'main_footer';
            }
            if ($page->footer_group === 'blog') {
                $placements[] = 'blog';
            }

            DB::table('pages')->where('id', $page->id)->update(['menu_placements' => json_encode($placements)]);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('menu_placements');
        });
    }
};
