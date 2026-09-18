<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\HomeTile;
use App\Models\Page;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // No factory here so `db:seed` works on a --no-dev install (no Faker).
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'phone' => '+15551234567', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        )->forceFill(['is_admin' => true])->save();

        User::updateOrCreate(
            ['email' => 'rider@example.com'],
            ['name' => 'Sam Rider', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        )->forceFill(['is_rider' => true])->save();

        // Electronics catalog: categories, products and variants. The slug on
        // each never changes so re-seeding just refreshes the row it matches;
        // Admin -> Homepage -> Category tiles is where per-tile artwork is
        // customised. Prices/inventory are demo values.
        $this->seedCategories();
        $this->seedProducts();
        $this->seedProductVariants();
        $this->seedBanners();
        $this->seedHomeTiles();
        $this->seedPages();
        $this->seedFooter();
    }

    /**
     * Demo footer: social links and app-store URLs so the footer's follow row
     * renders out of the box. An admin edits these under Admin -> Pages -> Footer.
     * Only seeded once so re-seeding does not wipe an operator's edits.
     */
    private function seedFooter(): void
    {
        if (\App\Models\Setting::get('footer')) {
            return;
        }

        \App\Models\Setting::put('footer', [
            'copyright' => '© {year} NexTech',
            'app_store_url' => 'https://apps.apple.com/app/nextech-demo',
            'play_store_url' => 'https://play.google.com/store/apps/details?id=com.nextech.demo',
            'socials' => [
                'facebook' => 'https://facebook.com/nextech',
                'x' => 'https://x.com/nextech',
                'instagram' => 'https://instagram.com/nextech',
                'linkedin' => 'https://www.linkedin.com/company/nextech',
                'youtube' => 'https://www.youtube.com/@nextech',
            ],
            'links' => [],
        ]);
    }

    /**
     * Starter content pages for the storefront footer, built with the same
     * drag-and-drop section blocks an admin uses in Admin console -> Pages
     * (hero / image + text / feature grid / call to action / rich text). The
     * plain-Markdown `content` stays as a fallback.
     */
    private function seedPages(): void
    {
        $hero = fn (string $heading, string $text, ?string $image = null, ?string $btnLabel = null, ?string $btnUrl = null) => array_filter([
            'type' => 'hero', 'image_url' => $image, 'heading' => $heading, 'text' => $text,
            'button_label' => $btnLabel, 'button_url' => $btnUrl,
        ], fn ($v) => $v !== null);

        $features = fn (string $heading, array $items) => [
            'type' => 'feature_grid', 'heading' => $heading,
            'items' => array_map(fn ($i) => ['title' => $i[0], 'text' => $i[1]], $items),
        ];

        // Feature grid whose cards link out. Each item: [image, title, excerpt, url].
        $cards = fn (string $heading, array $items) => [
            'type' => 'feature_grid', 'heading' => $heading,
            'items' => array_map(fn ($i) => ['image_url' => $i[0], 'title' => $i[1], 'text' => $i[2], 'link_url' => $i[3]], $items),
        ];

        $mediaText = fn (string $image, string $side, string $heading, string $markdown) => [
            'type' => 'media_text', 'image_url' => $image, 'image_side' => $side,
            'heading' => $heading, 'markdown' => $markdown,
        ];

        $richText = fn (string $markdown) => ['type' => 'rich_text', 'markdown' => $markdown];

        $stats = fn (string $heading, array $items) => [
            'type' => 'stats', 'heading' => $heading,
            'items' => array_map(fn ($i) => ['title' => $i[0], 'text' => $i[1]], $items),
        ];

        $steps = fn (string $heading, array $items) => [
            'type' => 'steps', 'heading' => $heading,
            'items' => array_map(fn ($i) => ['title' => $i[0], 'text' => $i[1]], $items),
        ];

        $quote = fn (string $text, string $author) => ['type' => 'quote', 'text' => $text, 'author' => $author];

        // FAQ accordion. Each item: [question, answer]. Answer may use Markdown.
        $faq = fn (string $heading, array $items) => [
            'type' => 'faq', 'heading' => $heading,
            'items' => array_map(fn ($i) => ['title' => $i[0], 'text' => $i[1]], $items),
        ];

        $cta = fn (string $heading, string $text, ?string $btnLabel = null, ?string $btnUrl = null) => array_filter([
            'type' => 'cta', 'heading' => $heading, 'text' => $text,
            'button_label' => $btnLabel, 'button_url' => $btnUrl,
        ], fn ($v) => $v !== null);

        $pages = [
            [
                'slug' => 'about', 'title' => 'About Us', 'footer_group' => 'company', 'sort_order' => 1,
                'content' => 'NexTech delivers everyday groceries and household essentials to your door, fast.',
                'sections' => [
                    $hero('Groceries at your door in minutes', 'NexTech is a demo storefront for fast local grocery delivery — fresh produce, pantry staples and household essentials, picked and delivered from a store near you.', '/img/pages/about-hero.jpg', 'Start shopping', '#/'),
                    $stats('NexTech by the numbers', [
                        ['~10 min', 'Average delivery time'],
                        ['20+', 'Categories in stock'],
                        ['4.8 / 5', 'Average order rating'],
                        ['Every morning', 'Fresh restocks'],
                    ]),
                    $features('Why shop with us', [
                        ['10-minute delivery', 'Orders leave the nearest store within minutes of checkout.'],
                        ['Real prices', 'Everyday low prices with discounts shown clearly — no surprises at checkout.'],
                        ['Fresh every day', 'Produce and dairy are restocked each morning.'],
                        ['Easy returns', 'Raise an issue from your order history and get a fast refund.'],
                    ]),
                    $steps('How it works', [
                        ['Fill your basket', 'Browse the aisles and add what you need. Prices and offers are shown upfront.'],
                        ['Check out in a tap', 'Pay by card or cash on delivery — the fee and ETA are confirmed before you pay.'],
                        ['We pick and pack', 'Your order is assembled at the nearest store within minutes.'],
                        ['Delivered to your door', 'Track it on the way; hand over cash on arrival if you chose that.'],
                    ]),
                    $mediaText('/img/pages/about-story.jpg', 'left', 'Our story', "NexTech started as a single neighbourhood store and now runs a small network of local hubs.\n\nThis whole site is a **demo build** — every page here, including this one, is editable in **Admin -> Pages** using drag-and-drop sections."),
                    $mediaText('/img/pages/about-hero.jpg', 'right', 'From local stores, not a warehouse', "We stock and dispatch from small hubs inside your neighbourhood, so produce travels metres, not miles.\n\nShorter journeys mean fresher food, less packaging and a delivery rider who can be at your door before the kettle boils."),
                    $quote('I ordered eggs and coriander at 8pm and it was at my door before I had finished chopping the onions. Genuinely faster than walking to the corner shop.', 'Priya M. — early tester'),
                    $features('On the roadmap', [
                        ['Scheduled delivery', 'Pick a future time slot, not just “as soon as possible”.'],
                        ['More neighbourhoods', 'New store hubs opening across the city through the year.'],
                        ['Loyalty perks', 'Rewards and member pricing for regulars, coming soon.'],
                    ]),
                    $cta('Hungry already?', 'Browse thousands of items and check out in under a minute.', 'Shop now', '#/'),
                ],
            ],
            [
                'slug' => 'blog', 'title' => 'Blog', 'footer_group' => 'company', 'sort_order' => 2,
                'content' => 'Recipes, seasonal picks and a look behind the delivery promise.',
                'sections' => [
                    $hero('The NexTech Blog', 'Recipes, seasonal picks and a look behind the 10-minute delivery promise.', '/img/pages/blog-seasonal.jpg'),
                    $cards('Latest posts', [
                        ['/img/pages/blog-delivery.jpg', 'How we get groceries to you in 10 minutes', 'A look under the hood of the delivery promise — from stocked hubs to planned routes.', '#/p/blog-10-minute-delivery'],
                        ['/img/pages/blog-dinner.jpg', '5 weeknight dinners in under 20 minutes', 'Five ingredients or fewer, on the table before the news finishes.', '#/p/blog-weeknight-dinners'],
                        ['/img/pages/blog-seasonal.jpg', "What's in season this month", 'The produce that is cheapest, freshest and best right now — and how to use it.', '#/p/blog-seasonal-produce'],
                        ['/img/pages/blog-waste.jpg', '7 easy ways to waste less food', 'Small habits that cut your grocery bill and your bin at the same time.', '#/p/blog-less-food-waste'],
                    ]),
                    $stats('The blog so far', [
                        ['4', 'Posts published'],
                        ['~5 min', 'Average read'],
                        ['Weekly', 'New posts (soon)'],
                        ['0', 'Sponsored posts'],
                    ]),
                    $features('Browse by topic', [
                        ['Recipes', 'Quick, real-food cooking with what is in the aisles this week.'],
                        ['Seasonal', 'What to buy now and why it tastes better.'],
                        ['Behind the scenes', 'How the store hubs, picking and routing actually work.'],
                        ['Sustainability', 'Less waste, less packaging, shorter journeys.'],
                    ]),
                    $quote('Short, useful and no fluff — I actually cooked two of the weeknight recipes the same evening I read them.', 'Alex R. — newsletter subscriber'),
                    $mediaText('/img/pages/blog-dinner.jpg', 'right', 'Write for us', "Got a fast recipe, a market tip or a strong opinion about tinned tomatoes? We publish guest posts.\n\nEmail **hello@nextech.example** with a two-line pitch. This is a demo build, so treat these as sample posts you can replace in **Admin -> Pages**."),
                    $richText("**Editorial note** — nothing here is sponsored. Product mentions are picked by the writer, and prices and availability shown in posts can change."),
                    $cta('Get new posts by email', "A subscribe box is coming soon — for now, check back weekly for the next one."),
                ],
            ],
            [
                'slug' => 'blog-10-minute-delivery', 'title' => 'How we get groceries to you in 10 minutes',
                'footer_group' => 'blog', 'sort_order' => 1, 'show_in_footer' => false,
                'content' => 'A look under the hood of the NexTech delivery promise.',
                'sections' => [
                    $hero('How we get groceries to you in 10 minutes', 'From stocked neighbourhood hubs to routes built for your street — a look under the hood.', '/img/pages/blog-delivery.jpg'),
                    $stats('The promise in numbers', [
                        ['under 10 min', 'Typical door-to-door'],
                        ['3+', 'Pickers on one basket at peak'],
                        ['Every line', 'Scanned before it leaves'],
                        ['Live ETA', 'Shown before you pay'],
                    ]),
                    $richText(
                        "### It starts with the store, not a warehouse\n".
                        "Instead of one big depot on the edge of town, we run small stocked hubs inside neighbourhoods. When your order lands, the picker is already a few metres from the shelf.\n\n".
                        "### Picking in parallel\n".
                        "The moment you check out, your list is split across the aisles so several people pack it at once. Chilled and frozen items are grabbed last so they stay cold.\n\n".
                        "### Short, planned routes\n".
                        "Riders leave with a route that already accounts for one-way streets and building access, so the last hundred metres don't eat the time we just saved.\n\n".
                        "### What can slow it down\n".
                        "Heavy weather, a very large basket, or an address we can't place on the map. You'll always see a live ETA before you pay, and it updates if something changes."
                    ),
                    $mediaText('/img/pages/about-story.jpg', 'left', 'Why a hub beats a warehouse', "A warehouse on the ring road is efficient for lorries, not for you.\n\nOur hubs carry a tighter range — the few thousand things people actually reorder — a short walk from where you live. Less range on the shelf, far less distance to your door."),
                    $features('What we optimise for', [
                        ['Distance', 'Metres from shelf to door, not miles.'],
                        ['Parallel picking', 'Several people pack one order at once.'],
                        ['Cold chain', 'Chilled and frozen items are grabbed last.'],
                        ['Route quality', 'One-way streets and door access, solved before the rider leaves.'],
                    ]),
                    $steps('The ten minutes, step by step', [
                        ['0:00 — Order placed', "Your list appears on the hub's screen and is split by aisle."],
                        ['0:30 — Picking starts', 'Several pickers work in parallel; chilled items come last.'],
                        ['3:00 — Packed and checked', 'A second person scans every line against your order.'],
                        ['4:00 — Rider dispatched', 'With a route built for your street, not just your postcode.'],
                        ['~10:00 — At your door', 'Hand over cash now if you chose cash on delivery.'],
                    ]),
                    $quote('The rider messaged when he was outside and waited while I found change. Felt like a neighbour dropping something round, not a courier.', 'Dan K. — Camberwell'),
                    $richText(
                        "### A few things people ask\n".
                        "**Can I add to an order after checkout?** Not once picking starts — but you can place a second order, and if it's within a few minutes we try to send them out together.\n\n".
                        "**What if I'm not in?** The rider calls, then waits a couple of minutes. Undelivered orders come back to the hub and we refund or retry.\n\n".
                        "**Do you deliver everywhere?** Only inside a hub's range for now. Enter your address on the home page to check."
                    ),
                    $cta('See how fast it lands for you', 'Enter your address and add a few items to get a live ETA.', 'Start shopping', '#/'),
                    $cards('Keep reading', [
                        ['/img/pages/blog-dinner.jpg', '5 weeknight dinners in under 20 minutes', 'Five ingredients or fewer, on the table fast.', '#/p/blog-weeknight-dinners'],
                        ['/img/pages/blog-seasonal.jpg', "What's in season this month", 'Cheaper, fresher, and it tastes better.', '#/p/blog-seasonal-produce'],
                        ['/img/pages/blog-waste.jpg', '7 easy ways to waste less food', 'Cut your bill and your bin at once.', '#/p/blog-less-food-waste'],
                    ]),
                ],
            ],
            [
                'slug' => 'blog-weeknight-dinners', 'title' => '5 weeknight dinners in under 20 minutes',
                'footer_group' => 'blog', 'sort_order' => 2, 'show_in_footer' => false,
                'content' => 'Five ingredients or fewer, on the table before the news finishes.',
                'sections' => [
                    $hero('5 weeknight dinners in under 20 minutes', 'Five ingredients or fewer, minimal washing up, on the table fast.', '/img/pages/blog-dinner.jpg'),
                    $richText(
                        "Keep a few basics in and any of these comes together in the time it takes rice to cook.\n\n".
                        "1. **Garlic butter pasta** — pasta, butter, garlic, parmesan, black pepper. Reserve a little pasta water to bring it together.\n".
                        "2. **Chickpea & spinach curry** — tinned chickpeas, curry paste, coconut milk, spinach. Simmer 10 minutes, serve with rice or bread.\n".
                        "3. **Egg fried rice** — cold cooked rice, eggs, spring onion, soy, frozen peas. High heat, keep it moving.\n".
                        "4. **Halloumi & tomato traybake** — halloumi, cherry tomatoes, olive oil, oregano. 15 minutes at 220°C.\n".
                        "5. **Tuna & white bean salad** — tinned tuna, cannellini beans, red onion, lemon, olive oil. No cooking at all."
                    ),
                    $stats('Why this works on a weeknight', [
                        ['5 or fewer', 'Ingredients per recipe'],
                        ['~15 min', 'Hands-on time'],
                        ['1 pan', 'For most of them'],
                        ['0', 'Special equipment'],
                    ]),
                    $mediaText('/img/pages/blog-seasonal.jpg', 'right', 'Swap with the seasons', "Every recipe above takes a swap. Spinach becomes chard or kale. Cherry tomatoes become any tomato, halved. Chickpeas become butter beans.\n\nCook whatever is cheap and good that week and the method still works."),
                    $features('Keep these in the cupboard', [
                        ['Dried pasta & rice', 'The base of three of the five above.'],
                        ['Tinned beans & tomatoes', 'Instant protein and a sauce in one tin.'],
                        ['Coconut milk & curry paste', 'A 10-minute curry any night.'],
                        ['Olive oil, garlic, lemon', 'Turns plain ingredients into a meal.'],
                    ]),
                    $steps('Get faster every week', [
                        ['Prep in batches', 'Chop onion and garlic for two nights at a time.'],
                        ['Cook rice ahead', "Cold rice is better for fried rice anyway."],
                        ['Always double it', "Tomorrow's lunch, sorted."],
                    ]),
                    $richText("### Make it a meal\nRound any of these out with a bag of salad, some bread, or a piece of fruit. None of them need a starter."),
                    $quote("I stopped ordering takeaway on Tuesdays. The chickpea curry is genuinely faster than opening the app.", 'Meera S.'),
                    $cta('Stock the basics', 'Add the cupboard staples to your next order in a couple of taps.', 'Shop staples', '#/'),
                    $cards('Keep reading', [
                        ['/img/pages/blog-delivery.jpg', 'How we get groceries to you in 10 minutes', 'A look under the hood of the delivery promise.', '#/p/blog-10-minute-delivery'],
                        ['/img/pages/blog-seasonal.jpg', "What's in season this month", 'The produce worth buying right now.', '#/p/blog-seasonal-produce'],
                        ['/img/pages/blog-waste.jpg', '7 easy ways to waste less food', 'Small habits, smaller bin.', '#/p/blog-less-food-waste'],
                    ]),
                ],
            ],
            [
                'slug' => 'blog-seasonal-produce', 'title' => "What's in season this month",
                'footer_group' => 'blog', 'sort_order' => 3, 'show_in_footer' => false,
                'content' => 'The produce that is cheapest, freshest and best right now.',
                'sections' => [
                    $hero("What's in season this month", 'Buy with the seasons: cheaper, fresher, and it simply tastes better.', '/img/pages/blog-seasonal.jpg'),
                    $richText(
                        "Produce that's in season hasn't travelled far or sat in storage, so it costs less and tastes more like itself.\n\n".
                        "### Vegetables to reach for\n".
                        "Leafy greens, carrots, beetroot, cabbage, leeks and squash are all at their best and their cheapest.\n\n".
                        "### Fruit worth buying\n".
                        "Apples, pears and citrus are crisp and well priced. Berries are better frozen this time of year."
                    ),
                    $stats('Why buy in season', [
                        ['Lower', 'Price when supply is high'],
                        ['Shorter', 'Time from field to shelf'],
                        ['Better', 'Flavour and texture'],
                        ['Less', 'Packaging and cold storage'],
                    ]),
                    $mediaText('/img/pages/blog-dinner.jpg', 'left', 'Cook it simply', "In-season produce doesn't need much done to it. Roast it, dress it with lemon and oil, or drop it in a soup.\n\nThe less you do, the more it tastes of itself."),
                    $features('A rough month-by-month', [
                        ['Late winter', 'Citrus, leeks, cabbage, stored apples.'],
                        ['Spring', 'Asparagus, spring greens, new potatoes, rhubarb.'],
                        ['Summer', 'Tomatoes, courgettes, berries, stone fruit.'],
                        ['Autumn', 'Squash, mushrooms, pears, root veg.'],
                    ]),
                    $features('Three ways to use a glut', [
                        ['Roast a tray', 'Any root veg, olive oil, salt, 30 minutes. Eats hot or cold all week.'],
                        ['Make a soup base', 'Onion, carrot, celery, stock. Freezes in portions.'],
                        ['Quick pickle', 'Vinegar, sugar, salt over sliced veg. Ready by dinner.'],
                    ]),
                    $richText("### What about frozen and tinned\nFrozen peas, spinach, berries and sweetcorn are picked and frozen at their peak — often better than \"fresh\" that has travelled a week. Tinned tomatoes and beans are pantry gold."),
                    $quote("Started shopping the 'in season' shelf and my veg bill dropped without me trying.", 'Tomasz W.'),
                    $cta('Shop fresh produce', 'See what your nearest store has in today.', 'Browse produce', '#/'),
                    $cards('Keep reading', [
                        ['/img/pages/blog-delivery.jpg', 'How we get groceries to you in 10 minutes', 'From stocked hubs to planned routes.', '#/p/blog-10-minute-delivery'],
                        ['/img/pages/blog-dinner.jpg', '5 weeknight dinners in under 20 minutes', 'Fast, cheap, five ingredients.', '#/p/blog-weeknight-dinners'],
                        ['/img/pages/blog-waste.jpg', '7 easy ways to waste less food', 'Buy less, bin less.', '#/p/blog-less-food-waste'],
                    ]),
                ],
            ],
            [
                'slug' => 'blog-less-food-waste', 'title' => '7 easy ways to waste less food',
                'footer_group' => 'blog', 'sort_order' => 4, 'show_in_footer' => false,
                'content' => 'Small habits that cut your grocery bill and your bin at the same time.',
                'sections' => [
                    $hero('7 easy ways to waste less food', 'Small habits that cut your grocery bill and your bin at the same time.', '/img/pages/blog-waste.jpg'),
                    $richText(
                        "1. **Shop your fridge first.** Plan two meals around what's already there before you order.\n".
                        "2. **Order little and often.** Fast delivery means you don't need to over-buy fresh food.\n".
                        "3. **Learn the labels.** \"Best before\" is about quality; \"use by\" is about safety.\n".
                        "4. **Store it right.** Herbs in water, potatoes in the dark, bread in the freezer.\n".
                        "5. **Cook once, eat twice.** Make a bit extra and label it for later.\n".
                        "6. **Keep a \"use me first\" shelf.** One spot in the fridge for things on the edge.\n".
                        "7. **Freeze the odds and ends.** Overripe fruit for smoothies, veg scraps for stock."
                    ),
                    $mediaText('/img/pages/blog-waste.jpg', 'right', "The 'use me first' shelf", "Pick one shelf in the fridge — eye level is best — for anything close to the edge.\n\nEveryone in the house checks it before opening a new pack. It's the single habit that moves the needle most."),
                    $steps('A two-minute weekly reset', [
                        ['Look', 'Scan the fridge and note what needs using.'],
                        ['Plan', 'Pin two meals to those items.'],
                        ['Top up', 'Order only the gaps.'],
                    ]),
                    $stats('What waste actually costs', [
                        ['~1 in 5', 'Bags of shopping binned, on average'],
                        ['Fresh food', 'The category wasted most'],
                        ['A month', 'How often a full reset helps'],
                        ['Planning', 'The thing that fixes it'],
                    ]),
                    $features('Store it so it lasts', [
                        ['Herbs', 'Stems in a glass of water, a loose bag over the top.'],
                        ['Bread', 'Freeze half the loaf the day you get it.'],
                        ['Potatoes & onions', 'Cool, dark, and not right next to each other.'],
                        ['Leafy greens', 'Wrapped in a dry cloth, not left soaking.'],
                    ]),
                    $richText("### Cook the scraps\nVegetable ends and herb stalks go in a stock bag in the freezer. Overripe bananas get peeled and frozen for smoothies or bread. Stale bread becomes croutons or breadcrumbs."),
                    $quote("Ordering smaller amounts more often was the fix. I don't buy a week of salad and watch half of it wilt any more.", 'Priya M.'),
                    $cta('Plan this week', 'Build a short list around what you already have.', 'Start a list', '#/'),
                    $cards('Keep reading', [
                        ['/img/pages/blog-delivery.jpg', 'How we get groceries to you in 10 minutes', 'Why fast delivery means buying less.', '#/p/blog-10-minute-delivery'],
                        ['/img/pages/blog-dinner.jpg', '5 weeknight dinners in under 20 minutes', 'Use what you have, fast.', '#/p/blog-weeknight-dinners'],
                        ['/img/pages/blog-seasonal.jpg', "What's in season this month", 'Buy well, waste less.', '#/p/blog-seasonal-produce'],
                    ]),
                ],
            ],
            [
                'slug' => 'contact', 'title' => 'Contact us', 'footer_group' => 'company', 'sort_order' => 3,
                'banner_image' => '/img/pages/contact-banner.jpg',
                // Plain text (headings, bold) — no section blocks.
                'sections' => [],
                'content' => <<<'MD'
**For any query about an order, your account or the service, use the addresses and contact details below. For the fastest help with a specific order, open it in your account and tap "Get help" so it reaches the team with the order already attached.**

## Registered office

NexTech Retail Private Limited

4th Floor, Market House, 12 Commerce Road

Cityville, State 100001, India

## Corporate office

NexTech Retail Private Limited

Tower B, Riverside Business Park, 88 Harbour Avenue

Metro City, State 400001, India

## Contact details

**Customer support:** support@nextech.example — replies within a few hours, every day 8am to 10pm.

**Phone:** +91 00000 00000 — for urgent delivery issues only.

**Press and partnerships:** hello@nextech.example

## Grievance Officer

In line with the Consumer Protection (E-Commerce) Rules, 2020, complaints can be sent to our Grievance Officer.

**Name:** Grievance Officer, NexTech Retail Private Limited

**Email:** grievance@nextech.example

**Address:** 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India

We acknowledge every complaint within 48 hours and aim to resolve it within one month of receipt.

## Company details

**Legal entity:** NexTech Retail Private Limited

**CIN:** U00000XX2020PTC000000

**GSTIN:** 00AAAAA0000A0Z0

**Registered address:** 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India

---

*This is placeholder contact information for a demo store. Replace the entity name, addresses, identifiers and officer details in Admin -> Pages before going live.*
MD,
            ],
            [
                'slug' => 'faqs', 'title' => 'FAQs', 'footer_group' => 'help', 'sort_order' => 1,
                'content' => 'Quick answers about delivery, payments and returns.',
                'sections' => [
                    $hero('Frequently asked questions', 'Answers about delivery, payments, refunds and your account. Tap a question to see the full answer.'),
                    $faq('Orders & delivery', [
                        ['How long does delivery take?', "Most orders arrive within the time window shown at checkout — often around 10 minutes. Weather, a large basket or building access can add a little time, and your live ETA updates if anything changes."],
                        ['Do you deliver to my area?', "Enter your address on the home page. If we deliver there you can start shopping straight away; if not, we'll say so and note your interest for when we expand."],
                        ['Is there a minimum order?', "There is no strict minimum, but a very small basket may carry a small-cart fee, which is always shown before you pay. Larger orders often qualify for free delivery."],
                        ['Can I add items after placing an order?', "Not once picking has started. You can place a second order, and if it is within a few minutes we will try to send both together."],
                        ['What if I am not home when the rider arrives?', "The rider calls and waits a couple of minutes. If delivery cannot be completed, the order returns to the store and we refund it or arrange a retry."],
                    ]),
                    $faq('Payments', [
                        ['How can I pay?', 'By card through our payment provider, or by cash on delivery where that option is shown at checkout.'],
                        ['Is it safe to save my card?', "Card details are handled by our PCI-compliant payment provider and are never stored on NexTech servers. We keep only a reference and the payment status."],
                        ['When am I charged?', 'For card orders, at checkout. For cash on delivery, you pay the rider the full amount on hand-over.'],
                        ['My payment failed but money was deducted — what now?', "A failed-payment hold is usually released by your bank within a few working days. If no order was created, no purchase was made. Contact support with the order time if it does not clear."],
                    ]),
                    $faq('Refunds & returns', [
                        ['How do I report a missing or wrong item?', "Open the order in your account and tap **Get help**. Tell us which items were affected; we review and, where appropriate, refund or replace them."],
                        ['How long do refunds take?', "Approved refunds go to your original payment method. Card refunds can take several working days to appear, depending on your bank. Cash-on-delivery refunds are made by a method we agree with you."],
                        ["Can I return groceries I've changed my mind about?", "Perishable items generally cannot be returned once delivered. For unopened non-perishable items, contact support within a reasonable time."],
                        ['Can I cancel an order?', "Yes, until it leaves the store. After that, cancellation may not be possible — contact support and we will help where we can."],
                    ]),
                    $faq('Your account', [
                        ['How do I sign in?', "Use the email-code option, or set a password and sign in with your email and password. Staff accounts sign in on a separate admin page."],
                        ['How do I change my address or phone number?', "Edit them in your account. The details on an order that is already placed are frozen at the time you placed it."],
                        ['How do I delete my account?', "Contact support or email privacy@nextech.example. The Privacy Policy explains what happens to your data."],
                        ['I am not getting order updates.', "Check the email address on your account and your spam folder. You can always see live status on the order in your account."],
                    ]),
                    $cta('Still need help?', "Open the order in your account and tap “Get help” — it reaches support with the order already attached."),
                ],
            ],
            [
                'slug' => 'privacy', 'title' => 'Privacy Policy', 'footer_group' => 'legal', 'sort_order' => 1,
                // A plain long-form policy (headings, bold, lists) — no section blocks.
                'sections' => [],
                'content' => <<<'MD'
NexTech Retail Private Limited (**"NexTech"**, **"we"**, **"us"** or **"our"**) is committed to protecting your privacy. This Privacy Policy explains what information we collect when you use the NexTech website and app (the **"Platform"**), how we use it, who we share it with, and the choices you have.

By using the Platform you agree to the practices described in this Policy. If you do not agree, please do not use the Platform.

**Effective date:** this is a demo document — set a real date before going live.

## 1. Information we collect

### 1.1 Information you give us

- **Account information** — your name, email address and phone number when you register or place an order.
- **Delivery information** — the addresses you save, delivery instructions, and the contact number for a given order.
- **Order information** — the items you buy, order value, and any issues or refunds you raise.
- **Communications** — messages you send us through support chat or email.

### 1.2 Information we collect automatically

- **Device and usage data** — device type, browser, operating system, IP address, pages viewed and actions taken on the Platform.
- **Approximate location** — derived from your address or, with your permission, your device, to check whether we deliver to you and to estimate delivery times.
- **Cookies and similar technologies** — see Section 4.

### 1.3 Information from third parties

- **Payment status** from our payment processor. We never receive your full card number.
- **Fraud and risk signals** from providers that help us keep accounts secure.

We do **not** knowingly collect sensitive personal data, and we ask that you do not send it to us.

## 2. How we use your information

We use your information to:

- create and manage your account;
- process, pack and deliver your orders, and handle returns and refunds;
- share the details a delivery rider needs — your name, address and phone — so your order can reach you;
- provide customer support and respond to your queries;
- detect, prevent and investigate fraud, abuse and security incidents;
- improve the Platform, our range and our delivery operations;
- send you service messages such as order updates and security notices; and
- send you offers and updates **only if you have opted in**, which you can stop at any time.

## 3. Payment information

Card payments are processed by our third-party payment processor. Your card details are entered on their secure systems and are **not stored on NexTech servers**. We retain only a payment reference and the status of the transaction.

## 4. Cookies and similar technologies

We use:

- **Essential cookies and local storage** to keep you signed in and remember your cart and chosen location. The Platform does not work properly without these.
- **Analytics** to understand which features are used so we can improve them.

You can clear or block cookies in your browser settings; some parts of the Platform may then stop working.

## 5. How we share information

We share information only as described here:

- **Delivery partners** — the name, address, phone number and order contents needed to deliver your order.
- **Service providers** — payment processing, hosting, communications, mapping and analytics providers who process data on our instructions.
- **Legal and safety** — where required by law, court order or a government request, or to protect the rights, property or safety of NexTech, our customers or the public.
- **Business transfers** — if NexTech is involved in a merger, acquisition or sale of assets, your information may be transferred, subject to this Policy.

We do **not** sell your personal information.

## 6. Data retention

We keep your information for as long as your account is active and for a reasonable period afterwards to meet legal, tax, accounting and dispute-resolution requirements. When it is no longer needed we delete or anonymise it.

## 7. Your rights and choices

Depending on where you live, you may have the right to:

- **access** the personal information we hold about you;
- **correct** information that is inaccurate — you can edit your profile and addresses in your account;
- **delete** your account and associated personal information;
- **object to or restrict** certain processing; and
- **withdraw consent** for marketing at any time.

To exercise any of these, contact us using the details in Section 12. We may need to verify your identity before acting on a request.

## 8. Security

We use technical and organisational measures to protect your information, including encryption in transit (HTTPS / TLS), access controls that limit staff and rider access to what they need, and revocable sign-in tokens. No method of transmission or storage is completely secure, so we cannot guarantee absolute security.

## 9. Children

The Platform is not directed at children below the age required to form a binding contract where they live, and we do not knowingly collect their personal information. If you believe a child has provided us information, contact us and we will delete it.

## 10. Third-party links

The Platform may link to third-party sites and services. We are not responsible for their privacy practices; please read their policies.

## 11. International transfers

Your information may be processed in countries other than the one you live in. Where we transfer information across borders, we use appropriate safeguards as required by applicable law.

## 12. Grievance Officer and contact

For questions about this Policy or to exercise your rights, contact:

Grievance Officer, NexTech Retail Private Limited

Email: privacy@nextech.example

Address: 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India

In line with the Consumer Protection (E-Commerce) Rules, 2020, we acknowledge complaints within 48 hours and aim to resolve them within one month of receipt.

## 13. Changes to this Policy

We may update this Policy from time to time. If we make material changes we will post the updated Policy on the Platform and, where appropriate, notify you. The **Effective date** above shows when it last changed.

---

*This is placeholder text for a demo store. Replace it with a privacy policy prepared and reviewed by your legal team, and set a real effective date, entity details and contact information in Admin -> Pages.*
MD,
            ],
            [
                'slug' => 'terms', 'title' => 'Terms of Service', 'footer_group' => 'legal', 'sort_order' => 2,
                // A plain long-form terms document (headings, bold, lists) — no section blocks.
                'sections' => [],
                'content' => <<<'MD'
These Terms of Service (**"Terms"**) govern your use of the NexTech website and app (the **"Platform"**), operated by NexTech Retail Private Limited (**"NexTech"**, **"we"**, **"us"** or **"our"**). By creating an account, placing an order or otherwise using the Platform, you agree to these Terms and to our Privacy Policy. If you do not agree, do not use the Platform.

**Effective date:** this is a demo document — set a real date before going live.

## 1. Eligibility and your account

- You must be old enough to form a legally binding contract where you live, and not barred from receiving our services under applicable law.
- You must provide accurate, current and complete account and delivery information, and keep it up to date.
- You are responsible for activity that happens under your account and for keeping your sign-in credentials secure. Tell us promptly if you suspect unauthorised use.
- We may refuse, suspend or close an account for a breach of these Terms, suspected fraud or abuse, or where required by law.

## 2. The service

The Platform lets you order groceries and household items from a nearby store for delivery. Product range, images, pricing and delivery areas vary by location and change over time. Nothing on the Platform is an offer; your order is an offer to buy, which we accept when we confirm it.

## 3. Orders, pricing and availability

- Prices, taxes, delivery fees and any other charges are shown before you confirm an order. Totals are calculated and confirmed by our servers at checkout.
- Product weights and pack sizes are approximate. Substitutions are only made with your agreement.
- If an item is unavailable, mispriced or ordered in quantities we consider abnormal, we may cancel all or part of the order and refund the affected amount.
- Promotional prices and offers are subject to their own terms and may be withdrawn at any time.

## 4. Payment

- You can pay by card through our third-party payment processor, or by cash on delivery where that option is shown.
- Card details are entered on the payment processor's systems and are **not stored on NexTech servers**.
- For cash-on-delivery orders, the full amount is due to the delivery rider on hand-over.
- If a payment fails or is reversed, we may cancel the order or suspend your account until it is resolved.

## 5. Delivery

- We deliver only to addresses within a serviceable area. Enter your address on the Platform to check.
- Delivery time estimates are indicative and may be affected by weather, traffic, demand or access to your building.
- Someone must be available to receive the order at the address. If delivery cannot be completed after reasonable attempts, the order may be returned and a cancellation fee or a partial refund may apply.
- Risk in the goods passes to you on delivery.

## 6. Cancellations and refunds

- You may cancel an order until it leaves the store. After that, cancellation may not be possible.
- Approved refunds are made to your original payment method. Card refunds may take several business days to appear, depending on your bank.
- For missing, damaged or incorrect items, raise an issue from your order history within a reasonable time so we can review and, where appropriate, refund or replace.

## 7. Acceptable use

You agree not to:

- use the Platform for any unlawful, fraudulent or harmful purpose;
- interfere with or disrupt the Platform, its servers or networks, or attempt to gain unauthorised access;
- scrape, copy or harvest data from the Platform except as expressly permitted;
- resell products bought through the Platform, or place orders you do not intend to pay for or receive;
- abuse promotions, referral schemes or the refund process; or
- upload or transmit anything unlawful, defamatory, infringing or malicious.

## 8. Intellectual property

The Platform, including its content, design, logos and software, is owned by NexTech or its licensors and is protected by intellectual-property laws. We grant you a limited, non-exclusive, non-transferable, revocable licence to use the Platform for its intended purpose. All other rights are reserved.

## 9. User content

If you submit content — such as support messages, feedback or ratings — you grant us a non-exclusive, worldwide, royalty-free licence to use it to operate and improve the service. You are responsible for the content you submit and confirm you have the right to submit it.

## 10. Third-party services

The Platform relies on and may link to third-party services (for example payments, mapping and messaging). Their terms and policies apply to your use of those services, and we are not responsible for them.

## 11. Disclaimers

The Platform and all products and services are provided on an **"as is"** and **"as available"** basis. To the fullest extent permitted by law, we disclaim all warranties, express or implied, including merchantability, fitness for a particular purpose and non-infringement. We do not warrant that the Platform will be uninterrupted, error-free or secure.

## 12. Limitation of liability

To the fullest extent permitted by law, NexTech and its officers, employees and partners will not be liable for any indirect, incidental, special, consequential or punitive damages, or for loss of profits, data or goodwill, arising from your use of the Platform. Our total liability for any claim relating to an order will not exceed the amount you paid for that order.

## 13. Indemnity

You agree to indemnify and hold NexTech harmless from claims, losses and expenses (including reasonable legal fees) arising from your breach of these Terms or your misuse of the Platform.

## 14. Suspension and termination

We may suspend or terminate your access to the Platform at any time for a breach of these Terms, suspected fraud or abuse, or where required by law. You may stop using the Platform and close your account at any time. Sections that by their nature should survive termination will do so.

## 15. Changes to these Terms

We may update these Terms from time to time. Material changes will be posted on the Platform and, where appropriate, notified to you. Continued use of the Platform after changes take effect means you accept the updated Terms.

## 16. Governing law and disputes

These Terms are governed by the laws of India, without regard to conflict-of-law rules. Subject to any mandatory consumer-protection rights you have where you live, the courts at Metro City, India will have jurisdiction over disputes arising from these Terms.

## 17. Grievance Officer and contact

For complaints or questions about these Terms, contact:

Grievance Officer, NexTech Retail Private Limited

Email: grievance@nextech.example

Address: 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India

In line with the Consumer Protection (E-Commerce) Rules, 2020, we acknowledge complaints within 48 hours and aim to resolve them within one month of receipt.

## 18. General

- **Entire agreement** — these Terms and the Privacy Policy are the entire agreement between you and NexTech regarding the Platform.
- **Severability** — if any provision is held unenforceable, the rest remains in effect.
- **No waiver** — our failure to enforce a provision is not a waiver of it.
- **Assignment** — you may not assign these Terms; we may assign them in connection with a merger, acquisition or sale of assets.
- **Force majeure** — we are not liable for delays or failures caused by events beyond our reasonable control.

---

*This is placeholder text for a demo store. Replace it with terms of service prepared and reviewed by your legal team, and set a real effective date, entity details, governing law and contact information in Admin -> Pages.*
MD,
            ],
            [
                'slug' => 'security', 'title' => 'Security', 'footer_group' => 'legal', 'sort_order' => 3,
                'banner_image' => '/img/pages/security-banner.jpg',
                // A plain responsible-disclosure policy (headings, bold, lists) — no section blocks.
                'sections' => [],
                'content' => <<<'MD'
NexTech Retail Private Limited (**"NexTech"**) takes the security of our customers and their data seriously. We value the work of security researchers and welcome reports of vulnerabilities in our website, app and infrastructure.

This page sets out how to report a security issue to us and what you can expect in return.

**Effective date:** this is a demo document — set a real date before going live.

## Our commitment

If you make a good-faith effort to comply with this policy during your research, we will:

- work with you to understand and validate your report;
- keep you informed of our progress towards a fix;
- not pursue or support legal action against you for accidental, good-faith violations of this policy; and
- credit you, with your permission, once the issue is resolved.

Activities carried out in a manner consistent with this policy will be considered authorised conduct, and we will not treat them as a breach of our Terms of Service.

## Guidelines

Please:

- only test against accounts and data that you own or have explicit permission to use;
- stop testing and report immediately if you encounter customer data, and do not access, modify, save, transfer or disclose it;
- give us a reasonable time to investigate and fix an issue before disclosing it publicly, and coordinate any disclosure with us;
- provide enough detail for us to reproduce the issue; and
- make every effort to avoid privacy violations, data loss and service disruption.

Please do **not**:

- run automated scanners against production, or any test that degrades or disrupts our services (including denial-of-service, brute force at volume, or spam);
- use social engineering, phishing, or physical attempts against our staff, riders, offices or infrastructure;
- attempt to access, download or exfiltrate data that is not yours;
- publicly disclose a vulnerability before we have confirmed it is fixed; or
- demand payment as a condition of disclosure.

## In scope

- Our customer website and web app
- Our customer mobile apps
- APIs that serve the above

## Out of scope

The following generally do **not** qualify on their own, unless you can show a concrete, exploitable security impact:

- Missing security headers, cookie flags, or best-practice hardening with no demonstrated exploit
- Self-XSS, or issues requiring a fully compromised device or browser
- Clickjacking on pages with no sensitive state-changing actions
- Rate-limiting or brute-force concerns on non-authentication endpoints
- Reports from automated tools without a working proof of concept
- SPF / DKIM / DMARC configuration, or email spoofing of non-existent addresses
- Outdated library versions with no proven vulnerability in our usage
- Denial-of-service, resource-exhaustion, or volumetric findings
- Social engineering, or physical security of our premises

## How to report

Email **security@nextech.example** with:

1. a clear description of the vulnerability and the affected URL, endpoint or app screen;
2. step-by-step instructions to reproduce it;
3. a proof of concept (script, request, screenshots or a short video); and
4. your assessment of the impact and any suggested remediation.

One issue per report, please. If you need to share sensitive details, ask us for a secure channel.

## What happens next

- **Acknowledgement** — we aim to confirm receipt within 3 working days.
- **Triage** — we validate the report and assign a severity, and will ask for more detail if needed.
- **Fix** — remediation time depends on severity and complexity; we will keep you updated.
- **Closure** — we let you know when the issue is resolved and confirm any credit.

## Recognition

With your consent, we are happy to acknowledge researchers who report valid, previously unknown issues. NexTech does not currently run a paid bug-bounty programme; any reward is at our discretion.

## Contact

Security reports: **security@nextech.example**

For anything else, see the [Contact](/#/p/contact) page.

---

*This is placeholder text for a demo store. Replace it with a responsible-disclosure policy reviewed by your security and legal teams, and set real scope, contact details and an effective date in Admin -> Pages.*
MD,
            ],
            [
                'slug' => 'careers', 'title' => 'Careers', 'footer_group' => 'company', 'sort_order' => 4,
                'sections' => [],
                'content' => 'We\'re not hiring through this demo site, but a real deployment would list open roles here — engineering, operations, delivery riders and store staff.',
            ],
            [
                'slug' => 'press', 'title' => 'Press', 'footer_group' => 'company', 'sort_order' => 5,
                'sections' => [],
                'content' => "Media enquiries: **press@nextech.example**.\n\nThis is a placeholder press page for a demo store — a real one would carry brand assets, recent coverage and a media contact.",
            ],
            [
                'slug' => 'affiliate-program', 'title' => 'Affiliate Program', 'footer_group' => 'company', 'sort_order' => 6,
                'sections' => [],
                'content' => 'A placeholder page for a demo store. A real affiliate program would explain how partners can earn commission for referring customers, along with sign-up details.',
            ],
            [
                'slug' => 'returns', 'title' => 'Return & Refund Policy', 'footer_group' => 'legal', 'sort_order' => 4,
                'sections' => [],
                'content' => "This is placeholder text for a demo store. A real policy would cover the return window, condition requirements, how to start a return, and how refunds are issued.\n\nSee the [Contact](/#/p/contact) page for support in the meantime.",
            ],
            [
                'slug' => 'shipping-info', 'title' => 'Shipping Info', 'footer_group' => 'legal', 'sort_order' => 5,
                'sections' => [],
                'content' => 'This is placeholder text for a demo store. A real page would cover delivery areas, estimated delivery times, shipping fees, and order tracking.',
            ],
            [
                'slug' => 'report-suspicious-activity', 'title' => 'Report Suspicious Activity', 'footer_group' => 'legal', 'sort_order' => 6,
                'sections' => [],
                'content' => "If something about an order, message or call claiming to be from NexTech looks suspicious, let us know at **security@nextech.example**.\n\nThis is a placeholder page for a demo store.",
            ],
            [
                'slug' => 'support-center', 'title' => 'Support Center', 'footer_group' => 'help', 'sort_order' => 2,
                'sections' => [],
                'content' => "Need help with an order, payment or your account? Use the **Help** button in the app, or see [FAQs](/#/p/faqs) and [Contact us](/#/p/contact).\n\nThis is a placeholder page for a demo store.",
            ],
            [
                'slug' => 'safety-center', 'title' => 'Safety Center', 'footer_group' => 'help', 'sort_order' => 3,
                'sections' => [],
                'content' => 'This is placeholder text for a demo store. A real safety center would cover rider and customer safety measures, verified deliveries, and how to report a safety concern.',
            ],
            [
                'slug' => 'sitemap', 'title' => 'Sitemap', 'footer_group' => 'help', 'sort_order' => 4,
                'sections' => [],
                'content' => "A full sitemap would list every page on the site. This is a placeholder for a demo store — see the footer links, or browse [categories](/#/) from the homepage.",
            ],
            [
                'slug' => 'privacy-choices', 'title' => 'Your Privacy Choices', 'footer_group' => 'bottom', 'sort_order' => 1,
                'sections' => [],
                'content' => "This is placeholder text for a demo store. A real page here would let visitors opt out of the sale or sharing of their personal information, as required by state privacy laws, and would link to the [Privacy Policy](/#/p/privacy) for full detail.",
            ],
            [
                'slug' => 'ad-preferences', 'title' => 'Ad Preferences', 'footer_group' => 'bottom', 'sort_order' => 2,
                'sections' => [],
                'content' => 'This is placeholder text for a demo store. A real page here would let visitors manage personalised-advertising preferences and cookie-based tracking choices.',
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(['slug' => $page['slug']], [
                'is_published' => true,
                'show_in_footer' => true,
                ...$page, // per-page keys (e.g. show_in_footer:false for blog posts) win
            ]);
        }
    }

    /**
     * Start the curated homepage tile list as a mirror of the categories, in
     * order. An admin trims / reorders / re-images these in the Homepage editor;
     * with no active tiles the storefront falls back to listing every category.
     */
    private function seedHomeTiles(): void
    {
        foreach (Category::query()->orderBy('sort_order')->get() as $index => $category) {
            HomeTile::updateOrCreate(
                ['category_slug' => $category->slug],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }

    /**
     * A few homepage banners so the storefront's promo strip is populated out of
     * the box. Images are public Blinkit layout assets (illustrative only).
     */
    private function seedBanners(): void
    {
        $banners = [
            [
                'image_url' => 'https://cdn.grofers.com/cdn-cgi/image/f=auto,fit=scale-down,q=70,metadata=none,w=2700/layout-engine/2026-01/Frame-1437256605-2-2.jpg',
                'headline' => null,
                'category_slug' => 'mobiles-smartphones',
                'placement' => 'hero',
                'sort_order' => 1,
            ],
            [
                'image_url' => 'https://cdn.grofers.com/cdn-cgi/image/f=auto,fit=scale-down,q=70,metadata=none,w=720/layout-engine/2023-07/pharmacy-WEB.jpg',
                'headline' => null,
                'category_slug' => 'personal-care-electronics',
                'placement' => 'strip',
                'sort_order' => 2,
            ],
            [
                'image_url' => 'https://cdn.grofers.com/cdn-cgi/image/f=auto,fit=scale-down,q=70,metadata=none,w=720/layout-engine/2026-01/pet_crystal_WEB-1.png',
                'headline' => null,
                'category_slug' => 'office-electronics',
                'placement' => 'strip',
                'sort_order' => 3,
            ],
            [
                'image_url' => 'https://cdn.grofers.com/cdn-cgi/image/f=auto,fit=scale-down,q=70,metadata=none,w=720/layout-engine/2026-01/baby_crystal_WEB-1.png',
                'headline' => null,
                'category_slug' => 'kids-and-baby-tech',
                'placement' => 'strip',
                'sort_order' => 4,
            ],
        ];

        // Matched by placement + sort_order (not image_url): once an admin
        // uploads a real banner image the image_url no longer matches this
        // placeholder, and matching on it would create a duplicate row on
        // every reseed instead of recognising the banner as already seeded.
        // If a banner is already there — seeded before, or admin-created —
        // leave it alone so a reseed never clobbers an edit.
        foreach ($banners as $banner) {
            $exists = Banner::where('placement', $banner['placement'])->where('sort_order', $banner['sort_order'])->exists();

            if (! $exists) {
                Banner::create($banner);
            }
        }
    }

    /**
     * The electronics catalog: 20 categories, 54 products and their variants.
     * This mirrors the live demo data (ported 2026-09-15 after the store was
     * rebranded from groceries) so a fresh seed matches what's actually
     * deployed instead of recreating the old grocery catalog.
     */
    private function seedCategories(): void
    {
        // [name, slug, image_url, sort_order]
        $categories = [
            ['Mobiles & Smartphones', 'mobiles-smartphones', '/img/cat/mobiles-smartphones.webp', 1],
            ['Laptops & Computers', 'laptops-computers', '/img/cat/laptops-computers.webp', 2],
            ['Audio & Headphones', 'audio-headphones', '/img/cat/audio-headphones.jpg', 3],
            ['Mobile Accessories', 'mobile-accessories', '/img/cat/mobile-accessories.webp', 4],
            ['Smart Watches & Wearables', 'smart-watches-wearables', '/img/cat/smart-watches-wearables.jpg', 5],
            ['Cameras & Photography', 'cameras-photography', '/img/cat/cameras-photography.jpg', 6],
            ['Televisions', 'televisions', '/img/cat/televisions.jpg', 7],
            ['Gaming Consoles & Accessories', 'gaming-consoles-accessories', '/img/cat/gaming-consoles-accessories.png', 8],
            ['Home Appliances', 'home-appliances', '/img/cat/home-appliances.jpg', 9],
            ['Computer Accessories', 'computer-accessories', '/img/cat/computer-accessories.jpg', 10],
            ['Power Banks & Chargers', 'power-banks-chargers', '/img/cat/power-banks-chargers.jpg', 11],
            ['Storage Devices', 'storage-devices', '/img/cat/storage-devices.jpg', 12],
            ['Networking Devices', 'networking-devices', '/img/cat/networking-devices.jpg', 13],
            ['Personal Care Electronics', 'personal-care-electronics', '/img/cat/personal-care-electronics.jpg', 14],
            ['Kids & Baby Tech', 'kids-and-baby-tech', '/img/cat/kids-and-baby-tech.webp', 15],
            ['Office Electronics', 'office-electronics', '/img/cat/office-electronics.jpg', 16],
            ['Smart Home', 'smart-home', '/img/cat/smart-home.webp', 17],
            ['Health & Fitness Tech', 'health-and-fitness-tech', '/img/cat/health-and-fitness-tech.jpg', 18],
            ['Premium & Flagship', 'premium-and-flagship', '/img/cat/premium-and-flagship.webp', 19],
            ['Car Electronics', 'car-electronics', '/img/cat/car-electronics.jpg', 20],
        ];

        foreach ($categories as [$name, $slug, $image, $sort]) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'image_url' => $image, 'sort_order' => $sort]);
        }
    }

    private function seedProducts(): void
    {
        // [name, slug, description, sku, price_cents, compare_at_price_cents, inventory_quantity, image_url, category_slug]
        $products = [
            ['Apple iPhone 15 Pro', 'apple-iphone-15-pro', 'Apple\'s titanium-body flagship with the A17 Pro chip, a 48MP main camera, and USB-C — built for all-day performance in the pocket.', 'GDP-PROD-001', 99900, null, 100, '/img/products/1.webp', 'mobiles-smartphones'],
            ['Samsung Galaxy S24', 'samsung-galaxy-s24', 'A compact Android flagship with a bright Dynamic AMOLED display, Snapdragon power, and Galaxy AI features built in.', 'GDP-PROD-002', 79900, null, 99, '/img/products/2.webp', 'mobiles-smartphones'],
            ['Google Pixel 8', 'google-pixel-8', 'Google\'s pure-Android phone with the Tensor G3 chip and a camera tuned for standout low-light and portrait shots.', 'GDP-PROD-007', 69900, 74900, 100, '/img/products/3.png', 'mobiles-smartphones'],
            ['OnePlus 12', 'oneplus-12', 'A fast, fluid flagship with Hasselblad-tuned cameras and 100W charging that tops up the battery in minutes.', 'GDP-PROD-008', 73900, null, 100, '/img/products/4.webp', 'mobiles-smartphones'],
            ['Xiaomi 14', 'xiaomi-14', 'A pocketable flagship with Leica optics and flagship-tier Snapdragon performance at a sharp price.', 'GDP-PROD-009', 64900, null, 100, '/img/products/5.png', 'mobiles-smartphones'],
            ['Apple MacBook Air M3', 'apple-macbook-air-m3', 'Apple\'s fanless, all-day laptop — the M3 chip handles everyday work and creative apps without breaking a sweat.', 'GDP-PROD-003', 109900, null, 80, '/img/products/6.webp', 'laptops-computers'],
            ['Dell XPS 13', 'dell-xps-13', 'A compact ultrabook with an edge-to-edge InfinityEdge display, built for work on the go.', 'GDP-PROD-004', 99900, null, 100, '/img/products/7.webp', 'laptops-computers'],
            ['HP Spectre x360', 'hp-spectre-x360', 'A convertible 2-in-1 with a gem-cut design that folds flat into tablet mode for sketching, notes, or streaming.', 'GDP-PROD-010', 129900, null, 91, '/img/products/8.webp', 'laptops-computers'],
            ['Lenovo ThinkPad X1 Carbon', 'lenovo-thinkpad-x1-carbon', 'The business standard: a carbon-fibre chassis, legendary keyboard, and MIL-SPEC durability.', 'GDP-PROD-011', 159900, null, 89, '/img/products/9.webp', 'laptops-computers'],
            ['Asus ROG Zephyrus G14', 'asus-rog-zephyrus-g14', 'A compact gaming laptop that punches well above its size, with enough GPU power for the latest titles.', 'GDP-PROD-012', 179900, null, 98, '/img/products/10.webp', 'laptops-computers'],
            ['Sony WH-1000XM5', 'sony-wh-1000xm5', 'Industry-leading noise cancellation and all-day comfort, tuned for travel and focus.', 'GDP-PROD-005', 34900, null, 99, '/img/products/11.jpg', 'audio-headphones'],
            ['Apple AirPods Pro 2', 'apple-airpods-pro-2', 'Adaptive noise cancellation, Transparency mode, and spatial audio in Apple\'s smallest true wireless earbuds.', 'GDP-PROD-006', 24900, null, 99, '/img/products/12.webp', 'audio-headphones'],
            ['Bose QuietComfort Ultra', 'bose-quietcomfort-ultra', 'Bose\'s quietest headphones yet, with immersive spatial audio and plush all-day comfort.', 'GDP-PROD-013', 42900, null, 100, '/img/products/13.jpg', 'audio-headphones'],
            ['JBL Flip 6 Speaker', 'jbl-flip-6-speaker', 'A rugged, waterproof Bluetooth speaker with punchy JBL sound for the beach, the shower, or the backyard.', 'GDP-PROD-014', 12900, null, 99, '/img/products/14.webp', 'audio-headphones'],
            ['Sennheiser Momentum 4', 'sennheiser-momentum-4', 'Audiophile-tuned sound with up to 60 hours of battery life on a single charge.', 'GDP-PROD-015', 34900, null, 99, '/img/products/15.webp', 'audio-headphones'],
            ['Tempered Glass Screen Protector', 'tempered-glass-screen-protector', '9H hardness, an oleophobic coating, and edge-to-edge clarity that keeps your screen scratch-free.', 'GDP-PROD-016', 999, null, 100, '/img/products/16.png', 'mobile-accessories'],
            ['Silicone Phone Case', 'silicone-phone-case', 'A soft-touch silicone case with a microfibre lining that protects without adding bulk.', 'GDP-PROD-017', 1499, null, 99, '/img/products/17.webp', 'mobile-accessories'],
            ['MagSafe Wireless Charger', 'magsafe-wireless-charger', 'Snap-on magnetic charging for a clean, cable-free charge every time you set your phone down.', 'GDP-PROD-018', 3999, null, 100, '/img/products/18.webp', 'mobile-accessories'],
            ['Apple Watch Series 9', 'apple-watch-series-9', 'Apple\'s smartwatch with the new double-tap gesture, a brighter always-on display, and deep health tracking.', 'GDP-PROD-019', 39900, null, 100, '/img/products/19.webp', 'smart-watches-wearables'],
            ['Samsung Galaxy Watch 6', 'samsung-galaxy-watch-6', 'A sleek Wear OS smartwatch with advanced sleep coaching and body composition tracking.', 'GDP-PROD-020', 32900, null, 100, '/img/products/20.jpg', 'smart-watches-wearables'],
            ['Fitbit Charge 6', 'fitbit-charge-6', 'A slim fitness tracker with built-in GPS, heart-rate tracking, and up to a week of battery life.', 'GDP-PROD-021', 15900, null, 100, '/img/products/21.jpg', 'smart-watches-wearables'],
            ['Canon EOS R50', 'canon-eos-r50', 'An entry-level mirrorless camera with fast autofocus, ideal for stepping up from a phone camera.', 'GDP-PROD-022', 79900, null, 100, '/img/products/22.jpg', 'cameras-photography'],
            ['Sony Alpha ZV-E10', 'sony-alpha-zv-e10', 'A vlogging-focused mirrorless camera with a fully articulating screen and background-defocus mode.', 'GDP-PROD-023', 69900, null, 100, '/img/products/23.jpg', 'cameras-photography'],
            ['GoPro Hero 12', 'gopro-hero-12', 'Rugged, waterproof, and stabilized — built to capture action from anywhere.', 'GDP-PROD-024', 39900, null, 100, '/img/products/24.jpg', 'cameras-photography'],
            ['Samsung 55" QLED TV', 'samsung-55-qled-tv', 'Quantum Dot colour and a wide viewing angle bring movies and sport to life in a 55-inch frame.', 'GDP-PROD-025', 89900, null, 100, '/img/products/25.jpg', 'televisions'],
            ['LG 65" OLED TV', 'lg-65-oled-tv', 'Self-lit OLED pixels deliver perfect blacks and infinite contrast on a 65-inch canvas.', 'GDP-PROD-026', 179900, null, 100, '/img/products/26.jpg', 'televisions'],
            ['Sony 43" Bravia TV', 'sony-43-bravia-tv', 'Sony\'s processing engine sharpens detail and motion for a crisp, cinematic picture.', 'GDP-PROD-027', 54900, null, 100, '/img/products/27.jpg', 'televisions'],
            ['Sony PlayStation 5', 'sony-playstation-5', 'Lightning-fast SSD loading, stunning visuals, and the DualSense controller\'s haptic feedback.', 'GDP-PROD-028', 49900, null, 100, '/img/products/28.png', 'gaming-consoles-accessories'],
            ['Microsoft Xbox Series X', 'microsoft-xbox-series-x', 'Microsoft\'s most powerful console, built for 4K gaming at up to 120fps.', 'GDP-PROD-029', 49900, null, 100, '/img/products/29.png', 'gaming-consoles-accessories'],
            ['Nintendo Switch OLED', 'nintendo-switch-oled', 'A vivid 7-inch OLED screen makes handheld play pop, and it still docks to the TV in seconds.', 'GDP-PROD-030', 34900, null, 100, '/img/products/30.jpg', 'gaming-consoles-accessories'],
            ['Dyson V15 Vacuum Cleaner', 'dyson-v15-vacuum-cleaner', 'A laser reveals hidden dust while a cordless motor delivers powerful, whole-home suction.', 'GDP-PROD-031', 74900, null, 100, '/img/products/31.jpg', 'home-appliances'],
            ['Philips Air Fryer XXL', 'philips-air-fryer-xxl', 'Rapid Air technology cooks crispy, low-oil favourites fast enough for a weeknight dinner.', 'GDP-PROD-032', 19900, null, 100, '/img/products/32.jpg', 'home-appliances'],
            ['LG 8kg Front Load Washing Machine', 'lg-8kg-front-load-washing-machine', 'Steam-cleaning and a quiet direct-drive motor make laundry day easier.', 'GDP-PROD-033', 54900, null, 100, '/img/products/33.jpg', 'home-appliances'],
            ['Logitech MX Master 3S Mouse', 'logitech-mx-master-3s-mouse', 'A precision mouse with silent clicks and an ultra-fast scroll wheel, built for all-day productivity.', 'GDP-PROD-034', 9900, null, 100, '/img/products/34.jpg', 'computer-accessories'],
            ['Keychron K2 Mechanical Keyboard', 'keychron-k2-mechanical-keyboard', 'Hot-swappable mechanical switches and Bluetooth multi-device pairing in a compact 75% layout.', 'GDP-PROD-035', 8900, null, 100, '/img/products/35.jpg', 'computer-accessories'],
            ['Dell 27" 4K Monitor', 'dell-27-4k-monitor', 'Sharp 4K clarity and accurate colour on a 27-inch panel built for work and creative editing.', 'GDP-PROD-036', 39900, null, 100, '/img/products/36.png', 'computer-accessories'],
            ['Anker 20000mAh Power Bank', 'anker-20000mah-power-bank', 'Enough capacity for multiple full phone charges, with fast pass-through charging.', 'GDP-PROD-037', 4999, null, 100, '/img/products/37.jpg', 'power-banks-chargers'],
            ['Apple 20W USB-C Fast Charger', 'apple-20w-usb-c-fast-charger', 'Apple\'s compact charger tops up an iPhone to 50% in about 30 minutes.', 'GDP-PROD-038', 1999, null, 100, '/img/products/38.webp', 'power-banks-chargers'],
            ['Belkin 3-in-1 Wireless Charging Stand', 'belkin-3-in-1-wireless-charging-stand', 'Charge your phone, watch, and earbuds together from a single stand.', 'GDP-PROD-039', 9999, null, 100, '/img/products/39.png', 'power-banks-chargers'],
            ['SanDisk 1TB Portable SSD', 'sandisk-1tb-portable-ssd', 'Pocket-sized storage with fast transfer speeds, built to survive drops and bumps on the go.', 'GDP-PROD-040', 8999, null, 100, '/img/products/40.jpg', 'storage-devices'],
            ['Samsung 256GB microSD Card', 'samsung-256gb-microsd-card', 'High-speed storage for phones, cameras, and handheld consoles.', 'GDP-PROD-041', 2999, null, 100, '/img/products/41.png', 'storage-devices'],
            ['WD 2TB External Hard Drive', 'wd-2tb-external-hard-drive', 'Reliable backup storage with plug-and-play simplicity for photos, videos, and files.', 'GDP-PROD-042', 6999, null, 100, '/img/products/42.jpg', 'storage-devices'],
            ['TP-Link Archer WiFi 6 Router', 'tp-link-archer-wifi-6-router', 'Faster, more reliable Wi-Fi for a house full of devices with WiFi 6 speeds.', 'GDP-PROD-043', 12900, null, 100, '/img/products/43.jpg', 'networking-devices'],
            ['Netgear Orbi Mesh WiFi System', 'netgear-orbi-mesh-wifi-system', 'Whole-home mesh coverage that eliminates dead zones without losing speed.', 'GDP-PROD-044', 22900, null, 100, '/img/products/44.jpg', 'networking-devices'],
            ['TP-Link 8-Port Gigabit Switch', 'tp-link-8-port-gigabit-switch', 'Expand your wired network with eight reliable gigabit ports.', 'GDP-PROD-045', 3999, null, 100, '/img/products/45.jpg', 'networking-devices'],
            ['Philips Hair Dryer', 'philips-hair-dryer', 'Fast-drying airflow with a cooling shot to lock in your style.', 'GDP-PROD-046', 2999, null, 100, '/img/products/46.jpg', 'personal-care-electronics'],
            ['Oral-B Electric Toothbrush', 'oral-b-electric-toothbrush', 'A pressure sensor and timer help you brush the dentist-recommended way, every time.', 'GDP-PROD-047', 4999, null, 100, '/img/products/47.jpg', 'personal-care-electronics'],
            ['Panasonic Beard Trimmer', 'panasonic-beard-trimmer', 'Precision blades and multiple length settings for a clean, consistent trim.', 'GDP-PROD-048', 3499, null, 100, '/img/products/48.jpg', 'personal-care-electronics'],
            ['Motorola Video Baby Monitor', 'motorola-video-baby-monitor', 'See and hear your baby clearly with night vision and two-way audio.', 'GDP-PROD-049', 8999, null, 100, '/img/products/49.jpg', 'kids-and-baby-tech'],
            ['Amazon Fire Kids Tablet', 'amazon-fire-kids-tablet', 'A durable, parent-controlled tablet built for young explorers, with a kid-proof case included.', 'GDP-PROD-050', 9999, null, 100, '/img/products/50.webp', 'kids-and-baby-tech'],
            ['LeapFrog Learning Tablet', 'leapfrog-learning-tablet', 'A screen-time companion designed to teach letters, numbers, and problem-solving through play.', 'GDP-PROD-051', 5999, null, 100, '/img/products/51.webp', 'kids-and-baby-tech'],
            ['HP LaserJet Printer', 'hp-laserjet-printer', 'Crisp, fast black-and-white printing built for the home office.', 'GDP-PROD-052', 17900, null, 100, '/img/products/52.jpg', 'office-electronics'],
            ['Epson Portable Projector', 'epson-portable-projector', 'A compact projector that turns any wall into a big screen for movies or presentations.', 'GDP-PROD-053', 39900, null, 100, '/img/products/53.jpg', 'office-electronics'],
            ['Logitech Webcam C920', 'logitech-webcam-c920', 'Full HD 1080p video and clear audio, built for sharp video calls and streaming.', 'GDP-PROD-054', 6999, 7999, 100, '/img/products/54.jpg', 'office-electronics'],

            ['Amazon Echo Dot (5th Gen)', 'amazon-echo-dot-5th-gen', 'A compact smart speaker with Alexa built in, for music, routines, and controlling the rest of your smart home.', 'GDP-PROD-055', 4999, null, 100, '/img/products/55.jpg', 'smart-home'],
            ['Philips Hue Smart Bulb Starter Kit', 'philips-hue-smart-bulb-starter-kit', 'Millions of colours and app-controlled scenes, with a bridge included to get your smart lighting started.', 'GDP-PROD-056', 6999, null, 100, '/img/products/56.jpg', 'smart-home'],
            ['TP-Link Kasa Smart Plug', 'tp-link-kasa-smart-plug', 'Turn any outlet smart — schedule, voice-control, or remotely switch appliances from your phone.', 'GDP-PROD-057', 1999, null, 100, '/img/products/57.jpg', 'smart-home'],
            ['Ring Video Doorbell', 'ring-video-doorbell', 'See, hear, and speak to visitors from anywhere, with motion alerts sent straight to your phone.', 'GDP-PROD-058', 9999, null, 100, '/img/products/58.jpg', 'smart-home'],
            ['Eufy RoboVac 11S Robot Vacuum', 'eufy-robovac-11s-robot-vacuum', 'A slim robot vacuum that slides under furniture and keeps floors clean on a schedule you set.', 'GDP-PROD-059', 19900, null, 100, '/img/products/59.jpg', 'smart-home'],

            ['Garmin Vivosmart 5 Fitness Band', 'garmin-vivosmart-5-fitness-band', 'A slim fitness band with heart-rate tracking, sleep scores, and up to seven days of battery life.', 'GDP-PROD-060', 12900, null, 100, '/img/products/60.jpg', 'health-and-fitness-tech'],
            ['Withings Body+ Smart Scale', 'withings-body-plus-smart-scale', 'Weight, body fat, and muscle mass synced automatically to your phone every time you step on.', 'GDP-PROD-061', 9900, null, 100, '/img/products/61.jpg', 'health-and-fitness-tech'],
            ['Omron Digital Blood Pressure Monitor', 'omron-digital-blood-pressure-monitor', 'Clinically validated, one-button readings you can track at home between doctor visits.', 'GDP-PROD-062', 4999, null, 100, '/img/products/62.jpg', 'health-and-fitness-tech'],
            ['Wellue Pulse Oximeter', 'wellue-pulse-oximeter', 'A fingertip sensor that reads blood oxygen and pulse rate in seconds, with an easy-read display.', 'GDP-PROD-063', 2999, null, 100, '/img/products/63.jpg', 'health-and-fitness-tech'],
            ['Xiaomi Smart Skipping Rope', 'xiaomi-smart-skipping-rope', 'Counts jumps, calories, and workout time automatically, and syncs your session to a fitness app.', 'GDP-PROD-064', 1999, null, 100, '/img/products/64.jpg', 'health-and-fitness-tech'],

            ['Apple iPhone 15 Pro Max', 'apple-iphone-15-pro-max', 'The largest, most capable iPhone — a titanium build, a 5x telephoto lens, and the A17 Pro chip.', 'GDP-PROD-065', 119900, null, 100, '/img/products/65.jpg', 'premium-and-flagship'],
            ['Samsung Galaxy Z Fold 6', 'samsung-galaxy-z-fold-6', 'A phone that unfolds into a tablet, with a smoother hinge and multitasking built for a bigger screen.', 'GDP-PROD-066', 179900, null, 100, '/img/products/66.jpg', 'premium-and-flagship'],
            ['Sony Xperia 1 VI', 'sony-xperia-1-vi', 'A creator-focused flagship with a versatile zoom lens system and pro-grade video controls.', 'GDP-PROD-067', 139900, null, 100, '/img/products/67.jpg', 'premium-and-flagship'],
            ['Asus ROG Phone 8', 'asus-rog-phone-8', 'A gaming flagship with a 165Hz display, AirTrigger controls, and cooling built for long sessions.', 'GDP-PROD-068', 109900, null, 100, '/img/products/68.jpg', 'premium-and-flagship'],
            ['Dell XPS 15 Plus', 'dell-xps-15-plus', 'A premium creator laptop with an edge-to-edge InfinityEdge display and serious rendering power.', 'GDP-PROD-069', 189900, null, 100, '/img/products/69.jpg', 'premium-and-flagship'],

            ['Garmin DriveSmart 55 GPS Navigator', 'garmin-drivesmart-55-gps-navigator', 'Voice-activated turn-by-turn navigation with live traffic, built for the dashboard.', 'GDP-PROD-070', 19900, null, 100, '/img/products/70.jpg', 'car-electronics'],
            ['Pioneer Bluetooth Car Stereo Receiver', 'pioneer-bluetooth-car-stereo-receiver', 'A touchscreen head unit upgrade with Bluetooth calling and streaming built in for any dashboard.', 'GDP-PROD-071', 8999, null, 100, '/img/products/71.jpg', 'car-electronics'],
            ['iOttie Car Dashboard Phone Mount', 'iottie-car-dashboard-phone-mount', 'A one-hand, one-touch mount that holds your phone steady on the dash or windshield for hands-free navigation.', 'GDP-PROD-072', 2499, null, 100, '/img/products/72.jpg', 'car-electronics'],
            ['Car Vent Air Purifier & Freshener', 'car-vent-air-purifier-freshener', 'Clips onto any air vent to filter odours and keep the cabin smelling fresh on every drive.', 'GDP-PROD-073', 1499, null, 100, '/img/products/73.jpg', 'car-electronics'],
            ['Pioneer Digital Car Clock Gauge', 'pioneer-digital-car-clock-gauge', 'A dash-mounted digital clock and gauge that drops into any spare vent or console slot.', 'GDP-PROD-074', 2999, null, 100, '/img/products/74.jpg', 'car-electronics'],
        ];

        foreach ($products as [$name, $slug, $description, $sku, $price, $compareAt, $inventory, $image, $categorySlug]) {
            $category = Category::where('slug', $categorySlug)->first();

            // Deterministic "random" from the SKU, so a reseed gives every
            // product the same dummy rating instead of a new one each time.
            // ~85% of products get a rating (skewed positive, like a real
            // storefront); the rest are left unrated to exercise that case.
            $hash = crc32($sku);
            $hasRating = $hash % 100 < 85;
            $ratingAvg = $hasRating ? round(3.5 + (($hash >> 8) % 151) / 100, 2) : null;
            $ratingCount = $hasRating ? 3 + ($hash % 797) : 0;
            $unitsSold = 2 + (($hash >> 16) % 14998);

            Product::updateOrCreate(['sku' => $sku], [
                'name' => $name, 'slug' => $slug, 'description' => $description,
                'price_cents' => $price, 'compare_at_price_cents' => $compareAt,
                'inventory_quantity' => $inventory, 'image_url' => $image, 'category_id' => $category->id,
                'rating_avg' => $ratingAvg, 'rating_count' => $ratingCount, 'units_sold' => $unitsSold,
            ]);
        }
    }

    private function seedProductVariants(): void
    {
        // [product_sku, label, sku, price_cents, compare_at_price_cents, inventory_quantity, image_url, sort_order]
        $variants = [
            ['GDP-PROD-004', '256GB SSD / 8GB RAM', 'GDP-PROD-004-500', 99900, null, 80, null, 1],
            ['GDP-PROD-004', '512GB SSD / 16GB RAM', 'GDP-PROD-004-1000', 119900, null, 58, null, 2],
            ['GDP-PROD-004', '1TB SSD / 32GB RAM', 'GDP-PROD-004-2000', 149900, null, 22, null, 3],
            ['GDP-PROD-005', 'Midnight Black', 'GDP-PROD-005-1KG', 34900, null, 50, null, 1],
            ['GDP-PROD-005', 'Platinum Silver', 'GDP-PROD-005-5KG', 36900, null, 15, null, 2],
            ['GDP-PROD-001', '256GB', 'GDP-PROD-001-V1', 109900, null, 40, '/storage/products/08qPFcHzRNRtfDWtI3xgql5mOPaCVg0fUdUWSYiN.webp', 0],
            ['GDP-PROD-001', '512GB', 'GDP-PROD-001-V2', 129900, null, 40, '/storage/products/PT66ONnzX8Kml67rgAB9wU8BYBRxvCSTkZ8hinSm.webp', 1],
            ['GDP-PROD-002', '256GB', 'GDP-PROD-002-V1', 89900, null, 40, null, 1],
            ['GDP-PROD-003', '16GB / 512GB', 'GDP-PROD-003-V1', 139900, null, 40, null, 1],
            ['GDP-PROD-010', '16GB / 1TB', 'GDP-PROD-010-V1', 159900, null, 40, null, 1],
            ['GDP-PROD-017', 'Ocean Blue', 'GDP-PROD-017-V1', 1499, null, 40, null, 1],
            ['GDP-PROD-017', 'Blossom Pink', 'GDP-PROD-017-V2', 1499, null, 40, null, 2],
            ['GDP-PROD-019', '45mm', 'GDP-PROD-019-V1', 42900, null, 40, null, 1],
            ['GDP-PROD-021', 'Coral', 'GDP-PROD-021-V1', 15900, null, 40, null, 1],
            ['GDP-PROD-028', 'Digital Edition', 'GDP-PROD-028-V1', 44900, null, 40, '/img/products/28-v15.png', 1],
            ['GDP-PROD-030', 'Neon Red / Neon Blue', 'GDP-PROD-030-V1', 34900, null, 40, null, 1],
            ['GDP-PROD-034', 'Pale Grey', 'GDP-PROD-034-V1', 9900, null, 40, '/img/products/34-v17.jpg', 1],
        ];

        foreach ($variants as [$productSku, $label, $sku, $price, $compareAt, $inventory, $image, $sort]) {
            $product = Product::where('sku', $productSku)->first();
            if (! $product) {
                continue;
            }

            $product->variants()->updateOrCreate(['sku' => $sku], [
                'label' => $label, 'price_cents' => $price, 'compare_at_price_cents' => $compareAt,
                'inventory_quantity' => $inventory, 'image_url' => $image, 'sort_order' => $sort,
            ]);
        }
    }
}
