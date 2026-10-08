# NexTech — Access & Menu Guide

---

## Admin access

**URL:** https://testcaresortwork.co.in/edp/admin
**Username:** test@example.com
**Password:** password

### Admin menus

**Currency / country switch (top bar):** pick **$ USD · United States** or
**₹ INR · India**. Dashboard, Orders, Products, Sellers, Riders, Stores and the
charges in Settings then show only that country — each country runs as its own
store with its own currency. The choice is remembered.

| Menu | What it does |
| --- | --- |
| **Dashboard** | Business overview for the country selected in the top bar — revenue, order and customer counts, discounts, refunds, plus orders-trend, payments-vs-refunds, "when orders come in" and period-comparison charts, all in that country's currency. All charts bucket by the admin's own local time (auto-detected from the browser). The orders-trend chart can show Orders, Revenue and Refunds together, each its own colour and scale. The 🔔 **notification bell** lists new orders awaiting packing, seller payout requests, **shipping labels waiting for an upload**, customers who refused cash-on-delivery, riders sitting on unreturned cash, recent negative feedback, recent refunds/gift cards issued, and **new categories sellers asked for** (click to create it with the name filled in) — each dismissible, with a badge count and a one-time ring when something new arrives. A separate 🔊 **speaker** menu lists new orders and new chat messages and holds the sound mute toggle. A separate **Sellers** button in the top bar holds seller notifications: onboarding to review (tax, compliance, bank), products waiting for review, removal requests, new applications, category requests, payout requests, labels to upload, cash kept by sellers and sellers owing money. Admins are also emailed when a seller submits tax, compliance or bank details. |
| **Orders** | Track delivery status (seller packages show carrier + tracking link and a **Confirm received** button), complete payment, cancel an order, download the bill (PDF), or get help on an order. **Write a review** on delivered items; **Return & warranty policy** link for items with returns or a warranty. Once an order is delivered, rate the rider 1–5 stars with an optional private note (also available from the delivery chat); the note goes to the NexTech team only. **Return & warranty policy** shows the terms the item was bought under; a cancelled order shows why and its refund status. A seller's local delivery shows who is bringing it ("**Sam** is delivering your order") and the delivery code to read to them. |
| **Products** | Add, edit and delete products for the selected country — price, sale price (India: MRP incl. GST, HSN code, GST rate, country of origin, manufacturer), images, variants and **per-store stock**. NexTech's own products have a **Sold in (country store)** field, so a product can be moved between the US and India stores; seller products always stay in the seller's country. Search by name/SKU and filter by category and store. Submenu **All products / + Add new product**; while editing, only the form shows (in boxes: details, images, video upload, description, variants, store stock, **return conditions**). Digital products have a **Files** button: uploaded files (download to check), sellers' hosted links with their check status and **Re-check**, download counts, license keys and settings. A product past buyers are still covered on (returns / warranty) can't be removed until that date. A product that has sold keeps its type, name, category, brand and model number (a different product is a new listing). |
| **Categories** | Add, edit, delete and reorder product categories, set their image and active/inactive state. Submenu **All categories / Common product details / + Add new category**; subcategories fold under ▸ / ▾. Categories form a tree (up to 3 levels, e.g. Downloadable › Games › Arcade): set the **Parent category** and the **Type** (Physical products / Digital downloads — subcategories take their parent's), **+ Sub** adds a subcategory. **Product details sellers fill in** (edit a category): text boxes, number boxes (with unit), dropdowns, checkboxes and yes/no checkboxes, each with a label, required or not, in order; subcategories use their parent's list until given their own. **Common product details** are asked for every physical product in every category. A detail or choice that products use is **locked** (red, "used by N products"): it can be renamed but not removed; Model number and Warranty are always kept. |
| **Sellers** | Review applications and onboarding (tax, compliance, bank). **View** opens everything about a seller, incl. tax information. **Request changes** ticks the exact items to fix, each with a note — the seller sees them highlighted. **Cash on delivery** per seller: Allow / Stop (only admin decides); shows what the seller owes. Every decision (application, tax / compliance / bank, products, removals, payouts) is sent to the seller as a message and email. |
| **Customers** | See all customers with order count and spend, open a customer's addresses and order history, and grant the delivery-rider role. |
| **Riders** | Riders of the selected country's stores (a rider works in **one country** only). Filters: **store** (own and sellers'), country, status (active / not active / leaving) and right now (on shift…). Each row shows the rider's **photo**, time as a rider and experience, live status, location, ★ rating, figures and cash still held. Orders are auto-assigned to the nearest available on-shift rider linked to the order's store. **Rider applications** (seller-accepted ones first — admin approves after checking the basic requirements: age, ID, photo, licence + vehicle RC) stay in the 🔔 until decided. Opening a rider shows their full **profile** from the application (education, past work, experience, health, stores wanted, documents), a **settlement** box (earned − cash held, settle 7 days after the last delivery; **Give sellers their cash from earnings**) and their **notice** (Mark processed). Removing a rider from a seller's store asks for a reason, sent to the seller. **Rider money** (per month): deliveries, fees buyers paid, rider earned, difference, cash collected and held, balance. |
| **Stores / hubs** | Every store: **Own store** or **Seller** (a seller's local-delivery base, shown once the seller is approved), with a **Show** filter, riders count and how long it's been listed; the seller's name opens a **seller card** (contact, business type, status, selling since, last message). **Local delivery (riders):** own stores — Riders on / Courier only (needs a rider linked); seller stores — On / Off / Seller asks to turn off (**Approve** or **Keep on**) / Off · locked, with Turn on / Turn off (reason sent to the seller). **Hiring** switch per store (feeds the rider application page and the storefront's "Work with us" link). NexTech's checkout, delivery fee, routing and stock use own stores only. |
| **Store settings** | Set the store name, tagline, logo, favicon, light/dark theme, brand colours and boxed/full page width. |
| **Shipping** | Submenus: **Seller shipping & labels**, **Sellers' own delivery** (available or hidden; largest area, default 10 km), **Selling abroad**, **Order update reminders**, **Seller cash on delivery**, **NexTech delivery** (own riders or courier for everything; rider auto-assign; delivery fee; **cash on delivery** with the **largest order for cash on delivery** (default ₹5,000 / $200, at your own risk) and **most cash one rider may hold** — over it they're paused in every store; rider pay). |
| **Secure access** | Password (or emailed code) re-check; it locks again when you open another menu or after a minute without activity. Submenus: **Withdrawal fees** (per country: bank / PayPal / Stripe on or off, fee, minimum, currencies; payout note), **Payouts** (every seller with money to pay — method, fee, what they receive, Pay / Decline; Stripe sends it — plus **Adjust a balance**: a credit or charge with a reason on a seller's or rider's ledger, also for fines) and **Keys & account** (admin email/phone, Stripe keys, courier, tracking, seller commission). |
| **Pages → Homepage** | Manage the curated category tiles shown in the homepage carousel — link, order and visibility (the tile's image now comes from the category itself, set in **Categories**). The old hero/strip banners are still editable here but are no longer shown on the storefront homepage. |
| **Store settings → Footer** | Edit the footer copyright, disclaimer, App Store / Play Store links, social-media links and custom links. |
| **Pages** | **All pages**, **All blogs**, **Main help pages**, **Main footer pages**, **Seller policies & rules** (shown in Seller Center → My account → Policies & rules, some signed by sellers). **New page** asks which group it goes in; set the **Page URL** when creating (it can't change later). Formatting guide lists the Markdown the body supports. |
| **Pages → Formatting guide** | A reference of every Markdown element the page/blog body supports (headings, bold, italic, code, links, lists, divider), each shown as the code to type next to a live preview. |
| **Support** | Read customer support chats, reply, mark them resolved/reopened, and issue a full/partial refund from within a conversation. For an order with no card payment to reverse (e.g. cash on delivery), issue **store credit** instead — pick the missing item(s) or an amount and a gift-card code + password are generated and posted straight into the chat for the customer to use on a future order. If the customer already has store credit from an earlier order, it can be applied straight to a different order of theirs that's still unpaid, right from the chat. A reason typed in for a refund or gift card is saved as an internal note in the same thread — kept for admin reference only, never shown to the customer. A sound + toast alerts the admin to every new message. Each thread shows the customer's ★ chat rating (with their comment) in the list and on the thread drawer. |
| **Settings** | **Payment:** cash-on-delivery on/off. **Seller shipping:** show/hide/lock the "NexTech collects & delivers" option; NexTech labels built-in (instant PDF) or via courier API. **Shipping label templates:** add/edit/preview label layouts (4×6 thermal, A6, A4), pick the default. **Countries:** which countries are open and the **Default country** (make India the default and untick the US to run India-only). **Charges & payouts** for the country selected in the top bar — delivery fee, free-delivery threshold, handling and small-cart fees, tax, commission rate, seller payout limits, return pickup fee and rider pay; India also has the **grievance officer** details. **Delivery:** rider auto-assignment on/off. **Business & tax details:** NexTech's legal name, address and GSTIN per country, printed on bills for its own products. **Digital downloads:** upload limits per file and per product (default 50 MB / 200 MB). **Seller shipping:** cash on delivery for sellers (each seller as admin sets it / off / all) and the "owed" limit per country that pauses it. |

---

## Client access

**URL:** https://testcaresortwork.co.in/edp/
**Username:** testcaresort@outlook.com
**Password:** password

### Client menus

| Menu | What a client can do |
| --- | --- |
| **Country picker** | Choose the United States ($) or India (₹) store in the header. It's set automatically — from the device time zone on the first visit, then from the delivery location's country — and switching empties the cart (items can't ship between countries). |
| **Search bar** | Type to find any product by name. |
| **Set your location** | Drop a map pin, detect location or search an address to check delivery and get an ETA. |
| **Categories** | Browse products by category from the tiles or the top rail. |
| **Product / Add** | Pick a variant, see regular vs sale price, and add items to the cart. |
| **Cart / View cart** | Change quantities, remove items, and see the running subtotal, fees, tax and total. |
| **Checkout** | Prices, fees and tax follow the country (India: prices include GST, PIN code, Indian states). Items shipped by sellers show the seller's shipping fee and arrival dates (card only — no cash on delivery for those). Choose a saved or new delivery address, add a phone number and delivery note, pick card or cash on delivery, and — if support has issued one — enter a gift-card code + password to apply store credit (checked for a balance before you pay; any leftover stays on the card for next time). |
| **Payment** | Pay by card (Stripe), use a saved card, or tick "save this card" for next time. |
| **Orders** | Track delivery status (seller packages show carrier + tracking link and a **Confirm received** button), complete payment, cancel an order, download the bill (PDF), or get help on an order. **Write a review** on delivered items; **Return & warranty policy** link for items with returns or a warranty. Once an order is delivered, rate the rider 1–5 stars with an optional private note (also available from the delivery chat); the note goes to the NexTech team only. **Return & warranty policy** shows the terms the item was bought under; a cancelled order shows why and its refund status. |
| **Account → Profile** | Update name and phone, and change password. |
| **Account → Addresses** | Add, edit, delete and set a default delivery address. |
| **Account → Payment methods** | Add a card, set a default, and remove saved cards. |
| **Help** | Start a support chat (optionally linked to an order) and message the store. Once the store has replied, rate the conversation 1–5 stars with an optional comment at the end of the chat. |
| **Footer pages** | Read About Us, Blog, Contact, FAQs, Privacy Policy, Terms of Service and Security. Signed-in buyers see **Work with us — deliver** when a store in their country is hiring riders. |
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
| **Store deliveries** | Sellers' own-delivery orders given to you: pickup address, buyer name and phone, address and note, items and **cash to collect**; mark **Delivered** with the buyer's code (and Cash collected). Cash you hold per seller is shown with **I handed over the cash** (the seller confirms it, or marks it not received). Over a cash limit, or paused by a seller, you're paused in every store until it's handed over. |
| **Earnings → Leaving?** | **Give 30 days' notice** (optional reason) — the store is told; you see your last working day and can take it back. Final pay is settled after your open orders and any cash you hold are checked. |
| **Apply (BASE + /rider)** | Signed-in buyers apply to deliver: stores that are hiring (NexTech's or sellers') in priority order, contact details, date of birth (minimum age per country), education, work history, experience, health, ID proof, photo, licence + vehicle RC for motor vehicles, own vehicle and consent ticks. A seller's store: the seller accepts, then NexTech approves. |

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


---

## Seller Center — shipping & labels

| Menu | What a seller can do |
| --- | --- |
| **My account → Shipping settings** | Pick how orders ship: **NexTech collects & delivers** (if the admin offers it), **Ship it yourself** (own courier) or **I ship, NexTech label**. Add ship-from addresses, shipping templates (states, handling days, transit days, fee per group), working weekends / holidays, and accept the free-shipping rule. States, holidays and carriers follow the seller's country. **Cash on delivery** can only be switched on once NexTech allows it for the shop (paused while the seller owes more than the limit). |
| **Manage orders → Orders you ship** | To ship / Overdue / Shipped / Delivered. **Confirm shipment** with carrier + tracking (format check, up to 3 edits, bulk edit). **Get shipping label** makes a PDF instantly (pick 4×6 or A4, switch any time, Download label), then **Shipped — add tracking**. **Mark delivered** for own-courier packages. |
| **Products** | Indian sellers price in ₹ as MRP incl. GST and must add HSN code, GST rate, country of origin and manufacturer. Self-shipping shops pick the shipping template per product. |
| **Products → Manage products** | **Deactivate / Relist** without review — not while past buyers are covered by returns / warranty (set stock to 0; it shows Out of stock). Add product shows **You receive** per unit after commission (and TCS / TDS in India), **Return conditions**, and requires **Warranty conditions** when a warranty is chosen. Once a product has sold, its type, name, category, brand and model number are locked. Add product only lists categories of the chosen type (physical / digital). |
| **Digital products → Download files** | Upload files (admin-set limit, default 50 MB each / 200 MB per product) **or add a download link** (Google Drive, Dropbox, own server). Links are checked when added and every night (✓ works / ⚠ opens a page / ✕ broken, **Re-check**); buyers get them only after paying. |
| **Application** | If NexTech asks for changes, the items to update are listed and outlined in red in the form. |
| **Finances** | Earnings in the seller's currency; Indian sellers see TCS (GST sec. 52) and TDS (sec. 194-O) deductions, and label postage if the admin charged any. Activity is a table coloured by where the money is: paid (dark green), available (green), held for returns (brown), cancelled out by a refund (grey), deducted (red). |
| **Products → Bulk import products** | Pick **Physical products** or **Digital downloads**, choose categories and download the Excel template (it opens on the column headings; how to fill it is on the page). One row per variant — rows with the same **Product group code** are one product, SKUs are made automatically, **Variant image URL** per row. The digital template has the download fields, price and download link. Upload it to create the products. |
| **Messages** | **NexTech support** first (start a new message there), then customer chats. |
| **My account → Local delivery** | Your store's local delivery status, a **step-by-step guide to hiring riders** (open hiring → post on Quikr / OLX / a shop poster → they apply → you accept → NexTech approves), the **Hiring riders** switch, **Applications** (age, documents, experience, health; Accept / Decline), **Your riders** (vehicle, experience, time with you, shift, open orders, delivered today; Remove with a reason when they have no open orders), **cash** they hold (Cash received / Not received / Later — at your own risk), **you pay per delivery** (charged to you as Rider pay), **most cash one rider may hold** (also your largest cash-on-delivery order), and **Rider money** per month. |
| **Shipping settings → Own delivery (local)** | Address, map point (latitude / longitude optional), area (up to the admin's maximum), fee and days; a "Before you turn it on" box sets out the risks (cash and products riders keep are yours). Turning it off with riders linked asks NexTech to confirm. |
| **Manage orders → own delivery** | **Who delivers it**: a rider (on shift first), **Auto — nearest free rider**, or yourself; change the rider later. High-value orders (over the cash-on-delivery maximum) are flagged: send by courier or deliver yourself. |
