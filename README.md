# GDP Grocery Delivery Platform

start "GDP API" cmd /k "cd /d D:\gdp\backend && D:\xampp8-2-12\php84\php.exe -d display_errors=0 artisan serve" && start "GDP Web" cmd /k "cd /d D:\gdp\web && npm.cmd --cache D:\gdp\.tmp\npm-cache run dev -- --host 127.0.0.1 --port 5173" 
                                  
/admin:	test@example.com
[client](http://127.0.0.1:5173/): testcaresort@outlook.com
/rider: rider@example.com

password: password

## See Output Quickly

Open Windows Command Prompt and run:

```cmd
cd /d D:\gdp\backend
"D:\xampp8-2-12\php84\php.exe" -d display_errors=0 artisan serve
```

Open the application in a browser:

- Website: <http://127.0.0.1:8000>
- Designed React storefront: <http://127.0.0.1:5173>
- API user endpoint: <http://127.0.0.1:8000/api/user>
- API categories endpoint: <http://127.0.0.1:8000/api/categories>
- API products endpoint: <http://127.0.0.1:8000/api/products>
- API orders endpoint (requires a bearer token): <http://127.0.0.1:8000/api/orders>
- API admin orders endpoint (requires an admin bearer token): <http://127.0.0.1:8000/api/admin/orders>
- API public client config (Stripe publishable key, checkout fees): <http://127.0.0.1:8000/api/config>

To start the designed React storefront, open another Command Prompt window and run:

```cmd
cd /d D:\gdp\web
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

Then open <http://127.0.0.1:5173>.

Keep both servers running together. The React storefront uses the Laravel API on port `8000` for catalog, authentication, cart synchronization, and checkout.

To see catalog JSON output from Command Prompt while the server is running:

```cmd
curl http://127.0.0.1:8000/api/categories
curl http://127.0.0.1:8000/api/products
```

Press `Ctrl+C` in the server terminal to stop Laravel.

USA online grocery delivery MVP with a Blinkit-style customer ordering journey. The initial market is the United States and the currency is USD.

The project is designed around one Laravel API that will serve the customer website, admin panel, and future Android and iOS applications.

## Product Goal

Build a simple, professional, reliable grocery delivery MVP that can be tested with real US customers and expanded in future versions. The objective is not to build a full Blinkit clone.

## Technology Plan

- Laravel API backend
- PHP 8.4
- MySQL
- Redis for cache, queues, and scalable application services
- Laravel Sanctum for API authentication
- Stripe for payments
- React customer website using the Laravel API
- React Native Android and iOS applications using the same Laravel API
- Cloud-storage-compatible product media storage
- Queues, notifications, logging, validation, and automated tests

## Development Rules

- Backend architecture, database schema, API contracts, authentication, and order/payment boundaries come before client UI.
- All important business logic remains on the Laravel backend.
- The server controls product prices, inventory, order totals, tax, delivery fees, payment status, and order status.
- Stripe webhook confirmation is the authoritative payment confirmation mechanism.
- Payment webhooks must verify signatures and handle successful, failed, cancelled, duplicate, and out-of-order events safely.
- The React website and React Native applications use the same versioned API contracts.
- Customer and administrator authorization is explicit and deny-by-default.
- The MVP prioritizes a complete and reliable customer ordering journey over speculative features.

## Customer Website Requirements

- Registration and login
- Homepage
- Categories
- Product listing and product details
- Product search
- Cart
- Checkout
- Address management
- Stripe payment
- Order confirmation
- Order history
- Order status tracking
- Customer support and live chat

## Mobile Application Requirements

Android and iOS applications must provide the same essential customer shopping and ordering functionality:

- Registration and login
- Product and category browsing
- Search
- Product details
- Cart
- Checkout
- Stripe payment
- Orders and order tracking
- Profile and address management
- Customer support and live chat

The mobile clients will use the Laravel API. They must not duplicate pricing, totals, payment, inventory, or order-state business logic.

## Admin Panel Requirements

Administrators must be able to manage:

- [x] Dashboard
- [x] Products
- [x] Categories
- [x] Customers
- [x] Orders
- [x] Payment status
- [x] Delivery assignment — auto-assign the nearest on-shift rider linked to the store, assign one by hand, or leave for the pool; free-text courier still allowed
- [x] Rider management — riders linked to one or more stores, home base, on/off shift (see Delivery riders)
- [x] Delivery and order status
- [x] Checkout settings (cash on delivery on/off)
- [x] Stores and delivery-area radius
- [ ] Customer support conversations (see priority 11)

## Backend Requirements

- Laravel API
- MySQL database
- Redis cache and queue support
- Authentication and authorization
- Product, category, customer, address, cart, order, and payment domains
- Stripe payment processing and secure Stripe webhooks
- Queued jobs and notifications
- Structured validation and API error responses
- Application logging and security controls
- Cloud-storage-compatible media handling
- Automated feature and unit tests

## Development Status

### Completed

- [x] Laravel 13 API project created in `backend/`
- [x] PHP 8.4 verified and used for Laravel commands
- [x] Composer dependencies installed
- [x] MySQL database `gdp` configured and migrations executed
- [x] Laravel application key generated
- [x] Laravel Sanctum installed
- [x] Sanctum token migration published and executed
- [x] Sanctum `HasApiTokens` added to the User model
- [x] API route scaffolding created
- [x] Customer registration endpoint
- [x] Customer login endpoint
- [x] Customer logout endpoint with token revocation
- [x] Protected current-user endpoint
- [x] Authentication feature tests
- [x] Product and category database schema
- [x] Public category and product API endpoints
- [x] Active catalog visibility and category filtering
- [x] Server-side product search by name, description, and SKU
- [x] Catalog feature tests
- [x] Repeatable demo grocery catalog seed data
- [x] JSON response for unauthenticated API requests
- [x] Authenticated server-side cart with inventory validation
- [x] Cart feature tests
- [x] Server-calculated checkout orders with tax and delivery fees
- [x] Checkout inventory validation and order snapshot tests
- [x] Responsive React customer storefront
- [x] Storefront catalog loading, search, category filters, and cart preview
- [x] Interactive cart drawer with quantity controls
- [x] Sign in and registration forms connected to Laravel API
- [x] Checkout address form and pending-payment order confirmation
- [x] Saved customer address database and protected address APIs
- [x] Checkout support for saved address IDs
- [x] Storefront address saving and saved-address checkout selection
- [x] Optional address capture during customer registration
- [x] Registration address saved as the customer default address
- [x] Stripe PaymentIntent and signed webhook backend boundary
- [x] Stripe card payment screen connected to PaymentIntent flow
- [x] PaymentIntent reused on retry instead of creating duplicates
- [x] Idempotent Stripe webhook that records processed event ids
- [x] Guarded payment lifecycle for successful, failed, cancelled, duplicate, and out-of-order events
- [x] Stripe webhook feature tests covering every transition
- [x] Customer order history and order detail API endpoints
- [x] Storefront order history view with payment status badges
- [x] Resume payment for an unpaid order from order history
- [x] Customer order cancellation until dispatch; one-click Stripe refund from the admin panel
- [x] Support chat (web + Expo) with admin inbox; partial/full Stripe refunds issued from a thread
- [x] Delivery rider role + Expo rider mode: admin-assign, auto-assign (nearest on-shift rider linked to the store), or pool claim; rider status + COD collection
- [x] Rider dashboard (web + Expo): lifetime/weekly deliveries with change-vs-last-week, ★ rating, code-verified share
- [x] Rider "new delivery" alert: email on assignment + live alarm tone (choose a preset or upload a clip) / vibration + banner in the consoles
- [x] Customer rates the rider 1–5 with an admin-only comment (from the delivered order or the delivery chat); feeds the rider's overall rating
- [x] Customer rates a support conversation 1–5 with a comment, at the end of the chat once staff have replied; shown to the admin
- [x] Server reconciles an order from Stripe when the webhook is missed or delayed
- [x] Checkout address modal dismissed when payment begins
- [x] Admin role flag on users, denied by default and never mass-assignable
- [x] Deny-by-default `admin` middleware guarding the admin API
- [x] Server-side order delivery state machine with guarded transitions
- [x] Admin order list, filter, detail, and status-advance endpoints
- [x] Storefront delivery progress tracker in order history
- [x] Delivery workflow feature tests
- [x] Admin dashboard metrics and customer list endpoints
- [x] Admin console in the storefront: dashboard, orders, products, categories, customers
- [x] Order status advancing, cancellation, and courier assignment from the admin order table
- [x] Admin product create, edit, delete with slug generation and order-safety guard
- [x] Product variants (Blinkit-style pack sizes): per-variant price, stock, SKU, image
- [x] Admin image upload for products and variants (`POST /api/admin/media`, swappable to S3)
- [x] Optional delivery instructions at checkout, shown to the admin on the order
- [x] Admin category create, edit, delete with in-use guard
- [x] Admin customer detail with address and order history
- [x] Admin dashboard, product, and category feature tests
- [x] Email OTP step on register and login, with resend cooldown and attempt lockout
- [x] Storefront OTP entry screen with resend
- [x] Throttled auth routes and OTP feature tests
- [x] Public `GET /api/config` for client Stripe key and checkout fees
- [x] Cash on delivery at checkout, with a runtime admin on/off toggle
- [x] Admin store manager: address + delivery radius, with out-of-area checkout block
- [x] Storefront map pin + backend geocoding (`/api/geocode/*`), store-biased search
- [x] Blinkit-style checkout charges: tiered delivery + handling + small-cart fee
- [x] Admin-editable charges + fixed-or-distance delivery fee (near→far across the radius)
- [x] Expo (React Native) Android customer app scaffolded in `mobile/`
- [x] Mobile: OTP auth, catalog, search, product detail, cart, checkout, order tracking
- [x] Mobile: in-app Stripe card payment via a WebView on the PaymentIntent flow
- [x] NexTech browser page metadata and local Vite development server
- [x] Public storage symlink created
- [x] Initial test suite passing
- [x] Initial project backup pushed to `webdev794/gdp`

### Pending Development Priorities

1. [x] Authentication
2. [x] Products and categories
3. [x] Search
4. [x] Cart
5. [x] Checkout
6. [x] Stripe payment and webhook processing
7. [x] Order management
8. [x] Delivery workflow
9. [x] Admin panel
10. [ ] Mobile applications for Android and iOS
    - [x] Android customer app (Expo): auth + OTP, catalog, cart, checkout, Stripe payment, order tracking
    - [x] Rider mode in the Expo app: a user with `is_rider` sees the delivery queue (pool + assigned) instead of the shop
    - [x] Location capture (`expo-location`): store-scoped catalog + serving-store checkout (see Per-store inventory); rider app pings its live position for auto-assignment
    - [ ] Push notifications
    - [x] In-app customer support (Support + conversation screens, shared API)
    - [ ] iOS pass: same Expo codebase, needs a Mac / EAS build and testing
    - [ ] Standalone build config (`expo-build-properties` for cleartext, app icons, EAS)
11. [x] Support chat (per-order + general threads, polling) with admin inbox + refunds from a thread
12. [ ] Testing and deployment hardening

### Known Gaps To Revisit

- `PaymentController::intent()` reconciliation and `PaymentController::refund()` call the live Stripe API; the guard/validation paths are tested, the SDK call itself is verified manually (needs a Stripe client fake).
- Support chat is polling-based (~4 s while the thread is open). A customer isn't notified of a staff reply when the app is closed — push/email is priority 10.
- Auto-dispatch picks the nearest on-shift rider linked to the order's store (live GPS if fresh, else base) with a load-balancing penalty; **batching** (grouping several stops onto one rider) and a live rider-position map for the customer are still not built.
- Phone + OTP login is not wired — the phone field is captured (required at checkout) but a real SMS gateway (Twilio/MSG91/SNS) is still a later config step; see **Authentication → Phone number**.
- The React app has no router; the admin console is a full-screen overlay shown to `is_admin` users. Revisit if the panel grows.
- Product and variant images can be uploaded (admin) or pasted as a URL; category and store images are still URL-only.
- Uploads land on the local `public` disk (`storage/app/public`, served via the `storage` symlink). Swap `FILESYSTEM_DISK` to S3 for production — `MediaController` and callers don't change.
- Product variants are one flat axis (a "pack size" list). A multi-axis matrix (size × colour) is out of scope for the grocery MVP.
- The mobile app confirms card payments in a WebView (Stripe Elements). A native
  `@stripe/stripe-react-native` PaymentSheet would need an Expo dev/EAS build and is a later option.
- `mobile/` has no app icon or splash image assets yet; Expo uses defaults.

## Delivery Workflow

Two tracks run in parallel:

- **Fulfilment `status`** — `pending_payment → confirmed → packing → ready_for_delivery → out_for_delivery → completed` (labelled "Delivered" in the UI), with `cancelled` as an off-ramp.
- **`payment_status`** — `pending → paid` (or `failed` / `cancelled`). "Paid" is not a fulfilment step; being paid is what produces `confirmed`.

### How it works

A card order becomes `confirmed` only when Stripe confirms payment; a COD order is
`confirmed` at checkout (and marked `paid` later when the admin records cash
collected). From `confirmed`, an administrator advances one step at a time:

`confirmed → packing → ready_for_delivery → out_for_delivery → completed`

Rules enforced server-side by `Order::canTransitionTo()` and `EnsureUserIsAdmin`:

- Only a user with `is_admin = true` may call the admin endpoints; everyone else gets `403`.
- Steps cannot be skipped and `completed` / `cancelled` are terminal.
- An order that is not `paid` (and not COD) cannot move into the delivery states.
- An administrator may cancel any time before dispatch — `confirmed`, `packing`, or `ready_for_delivery`.
- **The customer can also cancel** their own order in those same stages, from Order
  history (`POST /api/orders/{order}/cancel`). Once `out_for_delivery` it's
  support-only. A paid order becomes `payment_status = refund_pending`; an unpaid
  order just goes `cancelled`.
- **Refunding** a cancelled paid order: the admin's "Refund via Stripe" button
  (`POST /api/admin/orders/{order}/refund`) issues a full Stripe refund against the
  order's PaymentIntent, sets `payment_status = refunded`, and stores
  `stripe_refund_id`. A cash-on-delivery order (no PaymentIntent) instead uses
  "Mark refunded" (`PATCH …/orders/{order}` `{"refunded": true}`) after the cash is
  returned by hand.
- Every order carries a `stripe_dashboard_url` (test/live inferred from the secret
  key); after a refund the admin row shows a **"View in Stripe ↗"** link to that
  payment, where the refund is listed.
- Payment failure and cancellation never set `paid` or `completed`.
- The customer's order-history tracker shows each stage live as the store team advances it.

Admin endpoints (bearer token belonging to an admin user):

```text
GET    /api/admin/metrics              dashboard counts, paid revenue, low stock
GET    /api/admin/metrics/timeseries   orders bucketed over time — ?bucket=day|week|month (+ optional ?from=&to=)
GET    /api/admin/metrics/compare       one period-to-date vs the previous equal period — ?preset=day|two_day|week|month|six_month|year|custom (custom needs ?days=N)
GET    /api/admin/customers            customers with order count and paid spend
GET    /api/admin/customers/{user}     customer detail: addresses and order history
GET    /api/admin/orders               list every order, newest first, optional ?status=
GET    /api/admin/orders/{order}       order detail with items and customer
PATCH  /api/admin/orders/{order}       body: {"status":"packing"} and/or {"courier_name":"Sam Rider"} and/or {"cash_collected":true}
GET    /api/admin/products             all products incl. inactive, optional ?search=
POST   /api/admin/products             create; slug is generated from name when omitted
PATCH  /api/admin/products/{product}   partial update
DELETE /api/admin/products/{product}   409 if the product is on an existing order (deactivate instead)
GET    /api/admin/categories           all categories with product counts
POST   /api/admin/categories           create; slug generated when omitted
PATCH  /api/admin/categories/{category}   partial update
DELETE /api/admin/categories/{category}   409 while the category still has products
```

In the storefront, an `is_admin` account sees an **Admin** link in the header that opens a
full-screen console. A **left sidebar** holds the primary sections (Dashboard, Orders,
Products, Categories, Customers, Stores, **Store settings**, **Secure access**) plus a
collapsible **Pages** group (Homepage layout editor, then every content page);
Support, Settings, the chat-sound toggle and **Back to store** stay in the
top-right bar. The **☰** button collapses the
sidebar for a full-width view (remembered per browser in `localStorage`); on narrow
screens the sidebar overlays the content and a tap outside closes it. The main area is the
only scroll container, so it always fits the viewport.

The **dashboard** tab has three blocks:

- **Headline metric cards** — `GET /api/admin/metrics`.
- **Period-over-period comparison** — `GET /api/admin/metrics/compare?preset=`
  picks one span: `day` (today vs yesterday), `two_day`, `week`, `month`,
  `six_month`, `year`, or `custom` with `&days=N` (rolling N-day window vs the N
  days before it). Each is *period-to-date vs the previous equal-length period*
  (e.g. Sep 1–7 vs Aug 1–7). The response has `current` / `previous` window totals
  (`orders`, `paid_orders`, `revenue_cents`) plus an **aligned per-bucket
  `series`** (hour / day / month buckets, chosen by span) so bucket *i* of the
  current window lines up with bucket *i* of the previous one. The console renders
  it as a **single grouped bar chart** (previous vs current per bucket) with a
  period dropdown, a custom-days input, an orders ↔ revenue toggle, and a ▲/▼ %
  delta headline.
- **Orders trend** — `GET /api/admin/metrics/timeseries` returns a gap-filled
  series bucketed by **day / week / month** (default window 14 days / 12 weeks /
  12 months; a custom `from`/`to` is accepted and capped), with `by_status` and
  `by_payment_method` counts for the window. Rendered as a bar chart (toggle
  orders ↔ revenue) plus two pie charts.

Charts are hand-drawn SVG in `web/src/Charts.jsx` — no charting dependency. All
bucketing / windowing is done in PHP (or portable SQL) so it behaves identically
on SQLite (tests) and MySQL (runtime).

## Delivery riders

A third role, **`is_rider`** (deny-by-default like `is_admin`, never
mass-assignable). Grant it either from the admin **Customers** drawer
(`PATCH /api/admin/customers/{user}` `{is_rider}` — a quick toggle) or the
dedicated **Riders** section. The seeder creates `rider@example.com` / `password`.

### Riders admin section

**Admin console → Riders** (`AdminRiderController`, `*/api/admin/riders*`):

- `POST /api/admin/riders` `{email}` promotes an existing account to a rider.
- `PATCH /api/admin/riders/{user}` sets `phone`, `rider_is_active` (on/off shift),
  a **home base** (`rider_base_address` — geocoded on save when
  `rider_base_lat`/`lng` are blank — or a dragged map pin) and **`store_ids[]`**:
  the stores this rider serves. The `rider_store` pivot links a rider to one or
  more stores (nearby cities can share a rider).
- `DELETE /api/admin/riders/{user}` drops the role and detaches the stores; the
  account stays.
- The list shows each rider's stores, live/base location, on-shift state and
  current active-job count.

### Auto-assignment

When an order becomes `ready_for_delivery` **unassigned**, `RiderAssignment` (in
`AdminOrderController::update`) picks a rider automatically:

- candidates = riders with `is_rider` **and** `rider_is_active`, **linked to the
  order's `store_id`**, that have a usable location;
- ranked by **distance from the store** plus a load penalty
  (`BUSY_PENALTY_KM = 6` km per active delivery), so an idle rider a little
  further out beats a busy neighbour;
- **location** = the live GPS fix pinged by the rider app when it's fresh
  (`< 15 min`, `User::riderLocation()`), otherwise the admin-set base;
- if no rider is eligible the order stays in the **first-come pool** exactly as
  before. An admin can still assign/override from the Orders tab, and a
  pre-assigned order is never touched.
- Toggle the whole behaviour with **Admin console → Settings → Delivery →
  "Auto-assign riders to orders"** (`rider_auto_assign` setting, default **on**).

### Assignment notification

Whenever a rider is put on an order — auto-assigned by `RiderAssignment`, or
assigned by hand in the admin Orders tab — they get a `RiderAssigned` mail
notification (`$rider->notify(...)`; only on an actual change of rider, not on a
re-save or when the rider is cleared). Both rider consoles also react live:

- **Web** — the console diffs its own `assigned` list on every 15 s poll; a newly
  appeared order plays a chosen alarm tone and drops a yellow "New delivery
  assigned — #N" banner. The **Alert sound** menu in the header picks a preset
  (Urgent alarm / Chime / Bell / Siren, synthesised with Web Audio — no asset),
  or the rider can **upload a short clip of their own** (kept in that browser's
  `localStorage`), with a mute toggle and a Test button. It never fires for
  orders already in the queue when the screen opens.
- **Mobile** — the Deliveries screen does the same diff and fires a strong
  `Vibration` pattern plus the banner. (A custom sound file would need `expo-av`,
  which isn't in the app yet.)

### Rider API (`auth:sanctum` + `rider`)

- `GET /api/rider/orders` → `{ assigned, pool }`. The **pool is scoped to the
  rider's linked stores** (plus any order with no `store_id` — legacy data); a
  rider with no store links yet still sees the whole pool.
- `POST /api/rider/location` `{lat,lng}` — the Expo app pings this while the
  Deliveries screen is open (on focus, then every ~2 min); it feeds
  auto-assignment's "live location".
- `POST /api/rider/orders/{order}/claim` (pool → `out_for_delivery`, sets the
  rider); `POST …/status` (`out_for_delivery` only — "picked up");
  `POST …/cash-collected` (COD → `payment_status = paid`).
- **Proof of delivery.** `POST …/delivery-otp` mints a 6-digit handover code
  (15 min), emails it to the customer, and exposes it on the customer's own
  `GET /api/orders` (`delivery_code` — hidden from everyone else). `POST …/deliver`
  completes the order: with `{code}` it must match → `delivery_verified = true`;
  with `{override: true, note}` it completes anyway and records the note →
  `delivery_verified = false`, so the store keeps the evidence. `orders` gains
  `delivered_at`, `delivery_verified`, `delivery_note`; the admin Orders row shows
  *✓ code verified* or *⚠ delivered without code — {note}*.
- `GET/POST /api/rider/orders/{order}/messages` — rider ↔ customer chat for a
  delivery. It reuses the support-thread system: the rider's messages land as
  "staff" messages, so the customer sees them in their **Get help** inbox and
  staff in the admin **Support** tab.
- `GET /api/rider/stats` — the rider's own dashboard: lifetime deliveries, this
  week / month with the change vs the period before, `rating_avg` / `rating_count`,
  the code-verified share, cash collected, and the 10 most recent ratings —
  **scores and dates only; customer comments are never returned here**.

### Rider feedback & rating

- `POST /api/orders/{order}/rider-review` `{rating: 1..5, comment?, source?}`
  (`auth:sanctum`, owning customer). One review per order (`rider_reviews`,
  `order_id` unique) — a repeat call edits it. Allowed once the order is
  `out_for_delivery` or `completed` and has a `delivery_partner_id`. Writing a
  review recomputes the rider's denormalised `users.rider_rating_avg` /
  `rider_rating_count`. The **comment is admin-only** — it is never surfaced on
  any `/api/rider/*` route.
- Customers rate from **Orders → (delivered order)** and from inside the delivery
  chat panel; `GET /api/orders` embeds their own `rider_review` so the widget
  shows and can edit an existing score.
- `GET /api/admin/riders/{user}` (`AdminRiderController::show`) returns the rider
  with `completed_deliveries` and every review **including the comment**; the
  Riders list row carries `rating_avg` / `rating_count`.

### Rider consoles

Two front ends, same API:

- **Web** — `BASE + /rider` (or `#/rider`), its own sign-in gate (email +
  password, `is_rider` only), lazy-loaded chunk. Shows **My deliveries** (incl.
  orders still being packed, so the rider sees what's coming) + **Available to
  pick up**, polled every 15 s. Per order: address → Directions, tap-to-call,
  item list, COD amount, **Start delivery / Mark delivered / Cash collected**,
  and **Message customer** (chat drawer, polled every 4 s). A signed-in rider
  also gets a **Deliveries** link in the storefront header. A stats strip at the
  top (`GET /api/rider/stats`) shows lifetime deliveries, this week's count + the
  change vs last week, the ★ rating and the code-verified share.
- **Mobile** — the same Expo project; a user with `is_rider` gets the
  **Deliveries** screen instead of the shop (with the same stats strip), and
  pings its GPS for auto-assignment.

Admins can still override any status / courier from the Orders tab.

## Customer support

Customers raise issues from **Order history → "Get help"** (or the header **Help**
link): pick one of their orders (or "General"), pick an **issue type**
(`item_missing`, `item_damaged`, `wrong_item`, `not_delivered`, `payment_issue`,
`other`), then chat. Threads carry a `status` (`open` / `resolved`); a customer
reply re-opens a resolved thread. The client polls the thread every ~4 s while
it's open — no websocket server.

- Customer API (`auth:sanctum`): `GET/POST /api/support/threads`,
  `GET /api/support/threads/{thread}`, `POST …/{thread}/messages`,
  `POST …/{thread}/rating`.
- Admin API (`+admin`): `GET /api/admin/support/threads` (`?status=`,
  `?issue_type=`), `GET …/{thread}` (includes the linked order's items + refunds),
  `POST …/{thread}/messages` (staff reply), `PATCH …/{thread}` (`status`).
- Admin **Support** tab: an inbox (open-first) → thread drawer with the chat, a
  reply box, resolve/re-open, and — when the thread is linked to an order — a
  **Refund** panel.
- **Rate the conversation.** `POST /api/support/threads/{thread}/rating`
  `{rating: 1..5, comment?}` — the customer's CSAT for the chat, shown at the end
  of the thread once staff have replied. One rating per thread (`support_threads.
  rating` / `rating_comment` / `rated_at`); a repeat call edits it. Not shown on
  rider-opened `delivery` threads (those carry the rider rating instead). The
  admin sees it on the thread drawer and as a ★ column in the inbox.
- **Admin notifications**: the console polls open threads every 10 s on *any*
  tab. A new customer message (`needs_reply` — a customer message newer than the
  last staff reply) plays a two-tone Web-Audio chime, drops a clickable toast,
  and shows a count badge on the Support tab. A 🔔/🔕 toggle in the header mutes
  the sound (per browser). Each admin browser/login notifies independently, so
  several admins can work different chats at once.

### Refunds from a thread

`POST /api/admin/orders/{order}/refund` (admin) issues a **full or partial**
Stripe refund and is never automatic. Body: `item_ids[]` (sum those line totals),
`amount_cents` (override), `support_thread_id`, `reason`. A bare call refunds the
remaining balance. Each refund is an `order_refunds` row; `orders.refunded_amount_cents`
accumulates; `payment_status` becomes `partially_refunded` until it reaches
`total_cents`, then `refunded`. A thread-linked refund posts a system note into
the conversation. Cash-on-delivery orders (no PaymentIntent) still use the manual
`PATCH …/orders/{order}` `{"refunded": true}`.

The same flows are in the Expo app (`Support` / `SupportThread` screens,
`mobile/src/api.js` helpers) — verified in Expo Go, not automated here.

Grant admin rights to an existing account:

```cmd
"D:\xampp8-2-12\php84\php.exe" artisan tinker --execute="App\Models\User::where('email','test@example.com')->update(['is_admin'=>true]);"
```

The database seeder already flags `test@example.com` as an administrator.

## Authentication

Registration and login are email plus password, followed by a one-time code emailed to the
address. The code is 6 digits, expires in 10 minutes, allows 5 attempts, and cannot be
re-sent within 60 seconds.

- `POST /api/auth/register` and `POST /api/auth/login` return `202`/`200` with
  `{"requires_otp": true, "purpose": "...", "email": "..."}` and no token.
- `POST /api/auth/verify-otp` with `{"email", "purpose", "code"}` returns `{"user", "token"}`.
- `POST /api/auth/resend-otp` with `{"email", "purpose"}` sends a fresh code.
- **Forgot password**: `POST /api/auth/forgot-password` `{"email"}` emails a
  `password_reset` code (same generic response whether or not the account
  exists); `POST /api/auth/reset-password` `{"email", "code", "password",
  "password_confirmation"}` sets the new password, revokes every session and
  returns a fresh `{"user", "token"}`.
- **Change password** (signed in): `PATCH /api/profile/password`
  `{"current_password", "password", "password_confirmation"}` — verifies the
  current password and signs other devices out.
- The storefront **Password** tab has *Sign in* / *Create an account* /
  *Forgot password?*; the Account → Profile panel has a *Change password* form.
- All auth routes are rate limited to 12 requests per minute per IP.

Local development: `MAIL_MAILER=log`, so the email is written to
`backend/storage/logs/laravel.log`. With `APP_DEBUG=true` the code is also logged on its own
line as `OTP for <email> (<purpose>): <code>`. Watch it live with:

```cmd
"D:\xampp8-2-12\php84\php.exe" artisan pail
```

To sign in with password only (no code), set `AUTH_OTP_ENABLED=false` in `backend/.env` and
run `php artisan config:clear`.

### Phone number

`users.phone` is **optional at sign-up** and **required before the first
checkout** — the delivery rider needs a number to call.

- `POST /api/auth/register` accepts an optional `phone`. The passwordless email
  flow (`/api/auth/start`) never asks for one.
- `POST /api/checkout` takes a top-level `phone`. If the account has none and the
  request omits it, checkout returns `422` (`phone`). A supplied number is saved
  to the account and frozen onto `orders.delivery_address.phone`; a later profile
  edit doesn't rewrite past orders.
- `PATCH /api/profile` (`auth:sanctum`) updates the signed-in customer's own
  `name` / `phone` outside checkout.
- The web checkout modal and the Expo checkout screen show a phone field
  (prefilled from the account). The rider app (`GET /api/rider/orders` →
  `customer_phone`) and the admin Orders table / Customers drawer surface it,
  with a tap-to-call link on mobile.
- **Phone + OTP login** is not built. Delivering a code to a real handset needs a
  paid SMS gateway (Twilio / MSG91 / SNS) — there's no free in-app equivalent of
  `MAIL_MAILER=log` — so it's deferred to a config-only step once a provider is
  chosen. `OtpService` / `auth_otps` / `/api/auth/start` are already
  channel-agnostic and would be reused.

## Cash on Delivery

Checkout supports a second payment method: **cash on delivery (COD)**. It ships
**disabled**. An administrator turns it on under **Admin console -> settings**
("Accept cash on delivery"); the toggle is stored in the `settings` table
(`cod_enabled`) and read by `GET /api/config`.

- `POST /api/checkout` accepts `{"payment_method": "card" | "cod"}` (default
  `card`). A `cod` request while the toggle is off returns `422`.
- A COD order is created `status = confirmed`, `payment_status = pending`,
  `payment_method = cod`, with no Stripe PaymentIntent. It enters the delivery
  pipeline immediately.
- `POST /api/orders/{order}/payment-intent` returns `422` for a COD order.
- The courier collects cash on hand-off; an admin then marks the order paid with
  `PATCH /api/admin/orders/{order}` body `{"cash_collected": true}` (button:
  **Mark cash collected** on the Orders tab), which sets `payment_status = paid`.
- Cancelling an unpaid COD order also voids its payment (`payment_status =
  cancelled`).

## Delivery Area

An administrator defines where the service delivers under **Admin console ->
stores**. Each store has an address and a **delivery radius in km**. Leave
latitude/longitude blank and the address is geocoded on save (OpenStreetMap
Nominatim); if it can't be located, the row shows *"not located"* and does not
enforce anything until coordinates are added.

- Stores are independent — put them in **different cities**. A customer is
  deliverable when their point sits inside **any** active store's radius; the
  nearest such store *serves* the order and its per-product availability (below)
  applies. `Geo::servingStore()` / `Geo::coveringStores()` implement this, so a
  small store nearby no longer shadows a wider store slightly farther away.
- A checkout whose address falls outside **every** active, located store's radius
  is rejected with `422` and the message *"We don't deliver to your area yet — we're
  expanding fast and will reach you soon."*
- Out-of-area customers can still browse the catalog and build a cart. The
  storefront checks `GET /api/delivery-eta?lat&lng` when a location is set and
  shows the same message at the location step, a banner under the header, and a
  disabled checkout button.
- Enforcement is automatic whenever at least one active store has coordinates. It
  can be turned off globally with `CHECKOUT_ENFORCE_RADIUS=false` in
  `backend/.env` (then `php artisan config:clear`).
- Admin store endpoints (admin bearer token): `GET/POST /api/admin/stores`,
  `PATCH/DELETE /api/admin/stores/{store}`.

### Setting a delivery location (storefront)

The "Deliver to" modal has **Detect my location**, an address **search box**, and
a **draggable map pin** (Leaflet + OpenStreetMap tiles). The pin's coordinates
are authoritative for the radius check, so a customer can place it on an exact
building even when the street isn't in the geocoder.

Address lookups go through the backend, not the browser:

- `GET /api/geocode/search?q=&lat=&lng=` — forward search, **restricted** to a
  box (`viewbox` + `bounded=1`) around a store and to ~4x its delivery radius, so
  a sparse street query lands near the store instead of on a namesake in another
  city. The store is the one **nearest the `lat`/`lng`** the caller passes (the
  storefront sends the map's current centre), so a multi-store shop biases to the
  right city's store; with no `lat`/`lng` it uses the first active store. Falls
  back to a wider pass only if nothing local matches. Works in the USA or India
  (no country lock); relaxes the query progressively (drops a trailing
  `"..., CH"`, the house number, then trailing parts). With no store located yet
  there is no centre, so results come from anywhere — set the store's coordinates
  (drag its pin in Admin -> stores) to focus them.
- `GET /api/geocode/reverse?lat=&lng=` — used by Detect my location and the pin.
- Both reuse `App\Support\Geo` (day-long cache, Nominatim `User-Agent` from
  `NOMINATIM_USER_AGENT`) and are rate limited to 30/min per IP.

OpenStreetMap tiles are fine for local development; a production deployment
should switch `Storefront.jsx`'s `tileLayer` URL to a keyed provider.

OSM has thin street data in parts of the world, so an exact house number may not
resolve. Search jumps the map to the closest locality it can find and the
customer drops the pin on the exact spot; the admin store form has the same pin.

**Outbound HTTPS / CA bundle.** XAMPP's PHP often ships without `curl.cainfo` /
`openssl.cafile`, so every server-side HTTPS call (geocoding, Stripe) fails with
*"unable to get local issuer certificate"*. `AppServiceProvider` falls back to
the CA bundle committed at `backend/resources/certs/cacert.pem` when the ini has
none. The proper fix is to point `php.ini` at a real bundle:

```ini
curl.cainfo = "D:\xampp8-2-12\php84\extras\ssl\cacert.pem"
openssl.cafile = "D:\xampp8-2-12\php84\extras\ssl\cacert.pem"
```

## Product Variants

Products can carry **variants** (Blinkit's "unit" selector) — one flat list of
labelled options, each with its own **price, stock, SKU and image**. The label is
free text, so it covers pack size, weight, colour, flavour or a mix
("1 kg", "Red / Large"). There are no structured Size × Colour axes — one row per
sellable option.

- **Opt-in.** A product with no variant rows works exactly as before
  (price/stock/SKU/image on the product). Add rows under **Admin console →
  products → Options / variants** to switch it on.
- Schema: `product_variants` (`label, sku, price_cents, inventory_quantity,
  image_url, sort_order, is_active`). `cart_items` / `order_items` gain a nullable
  `product_variant_id`; order items also snapshot `variant_label`.
- `App\Support\Purchasable::resolve($product, $variant)` returns the effective
  price / stock / availability — "variant if present, else product" — reused by
  `CartController`, `CheckoutController` and the catalog.
- `GET /api/products` embeds active `variants` plus `price_min_cents` /
  `price_max_cents` (range spans the base product **and** its variants). The
  plain product is always the first ("base") option in the storefront dropdown;
  the cart/checkout accept `product_variant_id: null` (base) or an active variant
  id of that product.
- Checkout bills the variant's price and decrements the **variant's** stock;
  the order line records the pack label + variant SKU.
- Deleting a variant that is on an existing order is blocked (deactivate it
  instead), mirroring the product delete guard.
- Images: the product form and each variant row take a **file upload** (`POST
  /api/admin/media`, admin only — jpg/png/webp/gif, ≤ 4 MB) or a pasted URL.
  Files go to the `public` disk; the endpoint returns the stored URL, which is
  saved into `image_url`. `FILESYSTEM_DISK=s3` moves storage to the cloud with no
  code change.

## Per-store inventory

Each store keeps its **own stock count** for a product and its variants, so a
multi-store shop can say "Store A is out of milk, Store B has 20". Set it in
**Admin console → Products → Store stock**.

- Table `store_inventory` — one row per `(store_id, product_id, product_variant_id)`
  (a null variant is the base product): `quantity` + `is_stocked` (the store
  carries this line at all — can be `true` with `quantity 0` for "temporarily
  out"). **No rows for a product ⇒ "single stock" mode**: its
  `products.inventory_quantity` / `product_variants.inventory_quantity` applies
  everywhere, so single-store installs and newly created products keep working.
- `Product::availabilityAt($storeId, $variant)` → `{sold, quantity, per_store}`;
  `Product::scopeVisibleAtStore($storeId)` filters a catalog query.
  `Purchasable::resolve($product, $variant, $storeId)` folds it into the existing
  price/stock/active shape (plus `sold` / `per_store`).
- The storefront sends the chosen location as `?lat=&lng=` on `GET /api/products`,
  `GET /api/products/{slug}` and `GET /api/categories`. The API resolves the
  **serving store** (nearest store whose radius covers the point — see Delivery
  Area) and, for a product on per-store stock:
  - a line the store doesn't carry is **dropped** (uncarried variants too; a
    product with no carried option, and categories with nothing carried nearby,
    disappear);
  - `inventory_quantity` on the product and each variant is **rewritten to that
    store's count**, and `out_of_stock` flags a carried-but-empty line — the
    storefront greys the card and disables **Add** ("Out of stock").
  - No location → the full catalog at single-stock numbers (out-of-area customers
    still browse). `GET /api/products/{slug}` 404s only when the serving store
    carries no option of it.
- `POST /api/cart/items` and `PATCH /api/cart/items/{item}` take optional
  `lat`/`lng` so the add-to-cart stock check reads the serving store's shelf.
- `POST /api/checkout` re-checks every line against the serving store: not
  carried → `422` *"{name} isn't available for delivery to your area."*; over the
  store's count → *"…does not have enough inventory."* On success it **decrements
  that store's `store_inventory` row** (locked), not the shared column.
- Admin product editor: **"Track stock per store"** turns on a stores × options
  grid (Carried toggle + a quantity per store, per variant); `store_stock` on
  `POST/PATCH /api/admin/products` is a full replacement of the product's rows
  (empty array clears them, back to single stock).
- Bulk switch an existing catalog over: `php artisan inventory:seed-stores`
  seeds a row for every product/variant at every active store from its current
  single count (`--fresh` overwrites, `--store=ID` limits scope).
- The order is **stamped with the serving store** — `orders.store_id` (nullable;
  null when no stores are configured, or for orders placed before multi-store).
  The admin Orders tab shows *🏬 {store}* on each row (`store:id,name,city` is
  eager-loaded on the admin order endpoints). The **PDF bill** is issued from
  this store (`Order::fulfillingStore()`); a legacy order with no `store_id`
  falls back to the nearest active store to the delivery address, then the first.
- `GET /api/delivery-eta` now also returns `store_id` (the serving store).
- **Mobile**: the Expo app has a *"Use my location"* control (Catalog + Checkout,
  `expo-location`, foreground permission) that stores `{lat,lng}` in
  `AsyncStorage`; `api.products`/`api.categories`/`api.product` send it, and a
  new checkout address is saved with those coordinates so the same serving-store
  and availability checks apply.

## Checkout Charges

Every charge is **editable from Admin console → settings** ("Delivery &
charges"). The values live in the `settings` table under `checkout_fees`;
`config/checkout.php` / the `CHECKOUT_*` env vars are just the initial defaults.
`App\Support\CheckoutFees::current()` merges the two, `CheckoutController`
computes the order, and `GET /api/config` exposes the effective values.

| Charge | Rule | Default |
| --- | --- | --- |
| Delivery fee | **Fixed** (flat) **or** **distance** (linear: `near` at the store → `far` at that store's `delivery_radius_km`, clamped). Waived once subtotal ≥ the free-delivery threshold either way. | fixed `$2.99` · near `$1.99` / far `$5.99`, free ≥ `$35.00` |
| Handling fee | Flat, on **every** order | `$0.99` |
| Small-cart fee | Added when subtotal is **below the soft minimum** (no hard minimum order) | `$1.99` below `$10.00` |
| Tax | `subtotal × tax_rate_bps / 10000` (on the subtotal only) | `8.87%` |

`total = subtotal + tax + delivery_fee + handling_fee + small_cart_fee`. The
order row stores each line (`delivery_fee_cents`, `handling_fee_cents`,
`small_cart_fee_cents`, `tax_cents`, `total_cents`).

In **distance** mode the fee needs the customer's location: `GET /api/delivery-eta`
returns `delivery_mode` + `delivery_fee_cents` for a point and re-fires as the map
pin moves, so the cart drawer updates live. An address with **no coordinates**
(typed, un-geocoded) is charged the **far** price — the customer lowers it by
dropping the pin. The cart drawer shows an estimate and nudges ("Add $X more for
free delivery"); the server total is authoritative.

## Store settings & Secure access

Two admin sections back onto the `settings` key/value table (like the checkout
fees) and are exposed through `GET /api/config` / `GET /api/admin/settings`;
`PATCH /api/admin/settings` writes them.

- **Store settings** (`branding` key) — store name, tagline, **logo** and
  **favicon** (paste a URL or upload via `POST /api/admin/media`), a **light /
  dark theme**, and three brand colours (buttons, accent, headings). Defaults
  come from `config/branding.php` / `STORE_*` env vars; `App\Support\Branding`
  merges and normalises (hex-validated, theme whitelisted). The storefront reads
  `data.branding` from `/api/config` and applies it at runtime — CSS custom
  properties for the palette, `document.title`, and the `<link rel=icon>`. The
  admin console re-pins its own tokens so it stays readable whatever the store
  theme is.
- **Secure access** (sidebar: **Secure access**) — a step-up-guarded section
  holding the Stripe keys and the admin's own **email / phone / name**.
  - *Stripe* (`payments` key) — **publishable key**, **secret key**, **webhook
    signing secret**, editable so a live store can rotate keys without touching
    `.env`. `App\Support\Payments::stripe()` returns "saved value, else `STRIPE_*`
    config", and `AppServiceProvider` overlays the saved values onto
    `config('services.stripe.*')` at boot so the Stripe SDK calls in
    `PaymentController` pick them up. Secrets are write-only over the API:
    responses give the publishable key in full but only a **hint**
    (`sk_live_•••••1234`) for secrets, and a blank field keeps the stored value.
  - *Admin account* — `PATCH /api/admin/secure-access/account` (`name`, `email`,
    unique-checked, `phone`) updates the signed-in admin.

  **Step-up unlock.** `config/secure_access.php` `method` chooses how:
  - `password` (default, `SECURE_ACCESS_METHOD=password`) — the admin re-enters
    their **account password** (`POST /api/admin/secure-access/unlock`
    `{password}`). Handy while email delivery isn't wired up; the seeded admin's
    password is `password`.
  - `otp` — `POST /api/admin/secure-access/challenge` e-mails a code (reusing
    `OtpService` / `auth_otps`), then `unlock` takes `{code}`.

  Either way `unlock` returns a short-lived token (15 min cache grant). Any
  `stripe_*` change on `PATCH /api/admin/settings`, and the account endpoint,
  require it as `X-Secure-Access`. Non-sensitive settings are unaffected. Leaving
  the section re-locks it.

## Homepage editor

The storefront homepage (no search, no category selected) is a Blinkit-style feed
curated from **Admin console → homepage**:

1. **Promo banners** — the first (lowest `sort_order`) active banner is a
   full-width hero; the rest form a horizontal strip below it.
2. **Curated category tiles** — the first three active tiles render as large
   feature cards (image, item count, sample product names); the rest as a grid.

Both a banner and a tile link the same way: a chosen **category** wins, otherwise
a custom **`link_url`** opens in a new tab.

**Banners** (`banners` table) — `image_url` (paste a URL or upload via
`POST /api/admin/media`), optional `headline`, `category_slug` **or** `link_url`,
`sort_order`, `is_active`.

**Category tiles** (`home_tiles` table) — `category_slug` (the link target — "pick
up the category"), optional `title` and `image_url` overrides (blank falls back to
the category's own name/image), optional `link_url` for a non-category tile,
`sort_order`, `is_active`. With **no active tiles** the homepage falls back to
listing every category.

Admin API (`+admin`): `GET/POST /api/admin/banners`,
`PATCH/DELETE /api/admin/banners/{banner}`; `GET/POST /api/admin/home-tiles`,
`PATCH/DELETE /api/admin/home-tiles/{homeTile}`. `GET /api/config` exposes the
resolved `data.banners` and `data.home_tiles` (title/image already coalesced with
the category; tiles that resolve to no destination are dropped).

The seeder ships ~16 grocery categories (each with a few products), three demo
banners, and one tile per category so the editor is populated on a fresh install.

## Content pages

The storefront has a **Blinkit-style footer**: a **Useful Links** column, a
**Categories** column with "see all", then a row with the copyright line, the
**Download App** badges (App Store / Google Play) and **social icons**
(Facebook, X, Instagram, LinkedIn, YouTube), and a disclaimer note. "Useful Links"
lists the published **content pages** plus any extra links set in the footer editor.

Everything except the pages is a `footer` settings blob (`config/footer.php`
defaults, `App\Support\FooterConfig` merges/normalises, `GET /api/config` exposes
`data.footer`), edited under **Admin console → Pages → Footer**: copyright line
(`{year}` is substituted), disclaimer note, App Store / Play Store URLs, a URL per
social platform (blank hides that icon), and a list of extra label+URL links.
`PATCH /api/admin/settings` accepts a nested `footer` object.

- `pages` table: `slug` (unique), `title`, `content` (**Markdown**), `is_published`,
  `show_in_footer`, `footer_group`, `sort_order`.
- Public API: `GET /api/pages` (published, footer metadata) and
  `GET /api/pages/{slug}` (title + content).
- Admin: **Admin console → Pages** — a collapsible sidebar group whose first
  items are the **Homepage** layout editor (banners + category tiles) and the
  **Footer** editor, followed by every content page; each page opens an editor
  (title, auto-slug, Markdown body with a Write/Preview toggle, footer group,
  show-in-footer, published, sort order). Endpoints `GET/POST /api/admin/pages`,
  `PATCH/DELETE /api/admin/pages/{page}`.
- The storefront renders a page in-place at `#/p/<slug>` (hash route, so links are
  shareable and Back works). Markdown is rendered by a tiny built-in converter
  (`web/src/markdown.js`) — HTML-escaped first, so page content can't inject
  scripts — no Markdown dependency.
- The seeder creates About, Blog, Contact, FAQs, Privacy, Terms and Security with
  placeholder copy.

## Local Backend Setup

From Windows Command Prompt:

```cmd
set "TMP=D:\gdp\.tmp"
set "TEMP=D:\gdp\.tmp"
cd /d D:\gdp\backend

"D:\xampp8-2-12\php84\php.exe" artisan migrate
"D:\xampp8-2-12\php84\php.exe" artisan storage:link
"D:\xampp8-2-12\php84\php.exe" -d display_errors=0 artisan serve
```

The local API is available at `http://127.0.0.1:8000`.

For frontend commands, use the D-drive npm cache:

```cmd
cd /d D:\gdp\web
npm.cmd --cache D:\gdp\.tmp\npm-cache install
npm.cmd --cache D:\gdp\.tmp\npm-cache run dev -- --host 127.0.0.1 --port 5173
```

All temporary project output should go under `D:\gdp\.tmp`. Do not use the nearly-full `C:` drive for project caches or temporary output.

To enable Stripe test payments, add these values to `backend/.env`:

```env
STRIPE_SECRET=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
```

The webhook secret is not created by the application. Generate it with Stripe CLI while Laravel is running:

```cmd
stripe login
stripe listen --events payment_intent.succeeded,payment_intent.payment_failed,payment_intent.canceled --forward-to http://127.0.0.1:8000/api/payments/stripe/webhook
```

Copy the displayed `whsec_...` value into `STRIPE_WEBHOOK_SECRET`.

On Windows, verify the CLI first:

```cmd
stripe version
```

The CLI is installed for this workstation under the D-drive temporary tools folder. In a new Command Prompt, run:

```cmd
set "PATH=D:\gdp\.tmp\npm-global;%PATH%"
stripe version
stripe login
stripe listen --events payment_intent.succeeded,payment_intent.payment_failed,payment_intent.canceled --forward-to http://127.0.0.1:8000/api/payments/stripe/webhook
```

The listener prints the webhook signing secret. Copy its `whsec_...` value into `backend/.env` as `STRIPE_WEBHOOK_SECRET`. The CLI listener must stay running in its own terminal while testing payments. If you prefer another installation method, use <https://docs.stripe.com/stripe-cli>.

Create `web/.env.local` with the publishable key:

```env
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
```

Never commit either environment file or any Stripe secret.

Run tests with:

```cmd
set "TMP=D:\gdp\.tmp"
set "TEMP=D:\gdp\.tmp"
"D:\xampp8-2-12\php84\php.exe" artisan test
```

Never commit `.env`, Stripe keys, database passwords, customer data, `vendor/`, `node_modules/`, logs, or generated local files.

## Repository Layout

```text
gdp/
├── .github/agents/       Custom development agent
├── backend/              Laravel API
├── web/                  React customer website (Vite)
└── mobile/               React Native customer app (Expo) — Android first
```

See `mobile/README.md` for how to run the app in Expo Go and point it at the API.

## Backup Repository

GitHub repository: <https://github.com/webdev794/gdp>

-----
Tasks to do: 
Mobile android app.
Mobile ios app. 