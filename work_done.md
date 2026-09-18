# NexTech — Access & Menu Guide

---

## Admin access

**URL:** https://testcaresortwork.co.in/gdp/admin
**Username:** test@example.com
**Password:** password

### Admin menus

| Menu | What it does |
| --- | --- |
| **Dashboard** | Business overview — revenue, order and customer counts, discounts, refunds, plus orders-trend, payments-vs-refunds, "when orders come in" and period-comparison charts. All charts bucket by the admin's own local time (auto-detected from the browser), not the server's clock. The orders-trend chart can show Orders, Revenue and Refunds together, each its own colour and scale — toggle any combination on/off. The 🔔 **notification bell** (top bar) lists new orders awaiting packing, customers who refused cash-on-delivery, riders sitting on unreturned cash, recent negative feedback, and recent refunds/gift cards issued — each dismissible with its own ✕, with a badge count and a one-time ring when something new arrives. A separate 🔊 **speaker** menu lists new orders and new chat messages needing a reply, and holds the new-order/chat sound mute toggle. Any customer rating (good or bad) also pops a brief toast that fades on its own. |
| **Orders** | View every order (with the store that fulfils it), filter by status, advance the delivery stage, assign a rider, mark cash collected, and issue full/partial Stripe refunds or store-credit gift cards (once only per order, capped at what's left to refund, redeemable only by that order's own customer). Refunds and gift cards show who issued them and why, both in the order drawer and in the Orders table. The Delivery column shows each order's rider-offer state (offered / accepted / declined ×N) and is colour-coded when the order carries negative customer feedback. Click an order number for a summary drawer (items, fee breakdown, delivery address, courier — locked once the order is delivered or cancelled); a card order whose payment was abandoned can be Cancelled. If a rider reports the customer refused to pay for a cash-on-delivery order, it's auto-cancelled and flagged for the admin to confirm once the bagged items are physically back at the store. Cancelling an order automatically refunds any gift card spent on it. The customer is emailed an order summary with the PDF bill attached once an order is paid and delivered. A sound + on-screen alert fires for every new order that's ready to be packed. Two customers sharing the same name are told apart everywhere with a "[1]" / "[2]" suffix. |
| **Products** | Add, edit and delete products — price, sale price, image, per-item variants (size/weight/flavour), and **per-store stock**: turn on "Track stock per store" for a grid of each store's own count (0 = shown "out of stock", unticked = not carried there). Search by name/SKU and filter by category and store. |
| **Categories** | Add, edit, delete and reorder product categories, set their image and active/inactive state. |
| **Customers** | See all customers with order count and spend, open a customer's addresses and order history, and grant the delivery-rider role. |
| **Riders** | Add a rider (by email) — a store must be assigned at hire time so it's always clear where their cash gets returned — and set their phone, on/off-shift state, home base (address or map pin) and which stores they serve. Orders are auto-assigned to the nearest available on-shift rider linked to the order's store; unassigned ones fall back to the pickup pool. Each row shows the rider's live status (clocked in / on break / off / unavailable), current location, ★ rating, acceptance / rejected / missed figures, and any cash-on-delivery money they're still holding (amber if from today, red once it's carried over a day) with a **Confirm cash returned** button. **Reviews** opens a reviews-only panel (score, star breakdown, every customer comment — admin-only); **Attendance & stats** opens the drawer with worked-hours tiles and the monthly attendance report. Force a rider offline with a reason note. |
| **Stores** | Manage store/hub locations — address, map coordinates, delivery radius and active state. Stores can be in different cities; a customer inside any store's radius can order, and the nearest covering store serves them. |
| **Store settings** | Set the store name, tagline, logo, favicon, light/dark theme, brand colours and boxed/full page width. |
| **Secure access** | Change the admin email/phone and manage the Stripe API keys, protected by a password re-check. |
| **Pages → Homepage** | Manage the curated category tiles shown in the homepage carousel — link, order and visibility (the tile's image now comes from the category itself, set in **Categories**). The old hero/strip banners are still editable here but are no longer shown on the storefront homepage. |
| **Pages → Footer** | Edit the footer copyright, disclaimer, App Store / Play Store links, social-media links and custom links. |
| **Pages → All pages** | Create and edit content pages (About, Contact, Privacy, etc.) using text or drag-and-drop section blocks and a header banner image. |
| **Pages → Blogs** | Create and edit blog posts as their own pages, kept in a separate group. |
| **Pages → Formatting guide** | A reference of every Markdown element the page/blog body supports (headings, bold, italic, code, links, lists, divider), each shown as the code to type next to a live preview. |
| **Support** | Read customer support chats, reply, mark them resolved/reopened, and issue a full/partial refund from within a conversation. For an order with no card payment to reverse (e.g. cash on delivery), issue **store credit** instead — pick the missing item(s) or an amount and a gift-card code + password are generated and posted straight into the chat for the customer to use on a future order. If the customer already has store credit from an earlier order, it can be applied straight to a different order of theirs that's still unpaid, right from the chat. A reason typed in for a refund or gift card is saved as an internal note in the same thread — kept for admin reference only, never shown to the customer. A sound + toast alerts the admin to every new message. Each thread shows the customer's ★ chat rating (with their comment) in the list and on the thread drawer. |
| **Settings** | Configure checkout charges — tax rate, delivery fee, handling fee, small-cart surcharge — turn cash-on-delivery on/off, and turn rider auto-assignment on/off. |

---

## Client access

**URL:** https://testcaresortwork.co.in/gdp/
**Username:** testcaresort@outlook.com
**Password:** password

### Client menus

| Menu | What a client can do |
| --- | --- |
| **Search bar** | Type to find any product by name. |
| **Set your location** | Drop a map pin, detect location or search an address to check delivery and get an ETA. |
| **Categories** | Browse products by category from the tiles or the top rail. |
| **Product / Add** | Pick a variant, see regular vs sale price, and add items to the cart. |
| **Cart / View cart** | Change quantities, remove items, and see the running subtotal, fees, tax and total. |
| **Checkout** | Choose a saved or new delivery address, add a phone number and delivery note, pick card or cash on delivery, and — if support has issued one — enter a gift-card code + password to apply store credit (checked for a balance before you pay; any leftover stays on the card for next time). |
| **Payment** | Pay by card (Stripe), use a saved card, or tick "save this card" for next time. |
| **Orders** | Track delivery status, complete payment, cancel an order, download the bill (PDF), or get help on an order. Once an order is delivered, rate the rider 1–5 stars with an optional private note (also available from the delivery chat); the note goes to the NexTech team only. |
| **Account → Profile** | Update name and phone, and change password. |
| **Account → Addresses** | Add, edit, delete and set a default delivery address. |
| **Account → Payment methods** | Add a card, set a default, and remove saved cards. |
| **Help** | Start a support chat (optionally linked to an order) and message the store. Once the store has replied, rate the conversation 1–5 stars with an optional comment at the end of the chat. |
| **Footer pages** | Read About Us, Blog, Contact, FAQs, Privacy Policy, Terms of Service and Security. |
| **Sign in / Sign up** | Email-code, or email + password (Create an account / Forgot password on the Password tab). |

### Rider console (web) — BASE + /rider

Sign in with a rider account (`rider@example.com` / `password`). Shows the rider's
assigned deliveries (including orders still being packed) and the pickup pool,
refreshed every 15 seconds.

| Action | What it does |
| --- | --- |
| **Start delivery** | Moves a ready order to out-for-delivery. |
| **Deliver** | Confirms the handover: sends a 6-digit code to the customer to read back, or — if that can't be done — marks it delivered with a required note. On a cash-on-delivery order this is only available after **Cash collected** is used. |
| **Cash collected** | Marks a cash-on-delivery order paid — required before that order can be marked delivered. |
| **Customer refused to pay** | Cancels a cash-on-delivery order on the spot when the customer won't pay, with a required note for the store. |
| **Pick up** | Claims an unassigned order from the pool. |
| **Directions** | Opens the delivery address in Google Maps. |
| **Message customer** | Chat with the customer for that order (shows up in their Help inbox and the admin Support tab). |
| **Clock in / Lunch break / Clock out** | Track availability hours. Auto-assignment only offers orders while the rider is clocked in and not on break; the rider (or an admin) can also go "unavailable" with a reason note. |

A dashboard strip at the top of the console shows lifetime deliveries, this
week's count with the change vs last week, the ★ rating and the code-verified
share, cash currently held from cash-on-delivery drop-offs, plus
**rejected / missed** counts and **acceptance rate**. Scores only — customer
comments are never shown to the rider. If a customer refused to pay and the
rider is still holding those bagged items, a standing reminder shows on the
dashboard until the store confirms they're back.

**Delivery offers.** When an order is assigned (by the admin or auto-assign) the
rider gets a full-screen **60-second offer** they must **Accept** or **Reject**,
with a looping alarm and an email. A reject — or letting the timer run out —
re-offers the order to the next-best rider, and if none is eligible it drops to
the shared pickup pool. The admin's Orders list shows each order's offer state
(offered to X / accepted / declined ×N). The **Alert sound** menu in the header
picks a preset tone (Urgent alarm / Chime / Bell / Siren) or lets the rider
upload their own short clip, with a mute toggle and a Test button. The mobile app
vibrates and shows the same prompt.

A per-rider **monthly attendance report** (reachable from the admin rider drawer)
classifies each day as a full day, short day or day off from the clock-in ledger,
with hours worked and averages.
