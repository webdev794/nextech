# Possible tasks

Ideas noted for later. Nothing here is built yet.

---

## 1. Countries: show/hide and "Add country" from admin

### Today
- Countries are defined in code, and only two exist: **United States** and **India**.
  - `backend/config/markets.php` holds each selling country's profile: currency, tax rule, default fees, payouts, rider pay, seller onboarding rules, states, holidays and couriers.
  - `backend/config/countries.php` holds each country's name, currency symbol, address and postal-code format, and phone code.
- Admin → Settings → **Active countries** already switches a country on or off:
  - **Off:** the country leaves the storefront country picker and the admin per-country forms (Settings → Countries) (`Country::active()`, `Market::codes()`).
  - **Always on:** the home market (Settings → `home_market`) can't be switched off.
- Per-country settings already editable by admin (`web/src/AdminMarkets.jsx`): checkout fees, tax rate, seller commission, payout limits, return pickup fee, label postage and rider pay.
- Amounts only print correctly in **$ (USD)** and **₹ (INR)**: `backend/app/Support/Money.php` and `web/src/money.js`.

### Goal
Admin can show/hide countries and add new ones without a developer. A hidden country disappears everywhere: admin settings, admin lists and filters, Seller Center sign-up and every storefront page.

### A. Show / hide (finish what exists)
- [ ] Rename "Active countries" to **Countries — show / hide**, with a clear note on what hiding does.
- [ ] **Admin:** a hidden country disappears from the top-bar country switch, the Settings → Countries forms, the product, order and seller list filters, and the dashboard charts.
- [ ] **Storefront:** a hidden country disappears from the country picker. Its products, stores and shops stop showing. A buyer who arrives with a hidden country selected falls back to home.
- [ ] **Seller Center:** registration from a hidden country is blocked, with a message to contact the store.
- [ ] **Existing data in a hidden country stays.** Its sellers can sign in and see a "selling is paused in your country" notice, and its orders stay viewable for support, refunds and payouts. Decide: can its sellers still ship open orders? (Recommended: yes.)
- [ ] **Safety:** the home country can't be hidden. Hiding a country that has open orders or seller balances asks for confirmation and lists them.

### B. Add country (admin page)
- [ ] **New page:** Admin → Settings → Countries → **Add country**.
  - Pick from a full country list: name, ISO code, phone code and currency come pre-filled.
  - Choose a **template** to copy defaults from:
    - **"Tax added at checkout"** (US-style sales tax);
    - **"Tax included in prices"** (India-style GST/VAT).
- [ ] **Editable fields:**
  - currency code and symbol, and number format (thousands grouping, decimals);
  - tax mode (added / included), label ("Sales tax", "VAT", "GST") and default rate;
  - default fees, payout limits, commission and rider pay (the existing per-country form);
  - states/regions list (or none) and the postal-code label and format;
  - seller onboarding: the tax ID label and format (regex), whether a tax certificate is required, the bank account fields (labels and formats, e.g. IBAN or sort code), and the business document types;
  - **VAT countries (UK, EU and similar):** sign-up asks for the seller's **VAT registration number** (like Temu does).
    - Admin sets per country: the label ("VAT number"), its format (e.g. `GB` + 9 digits, or an EU country prefix + digits) and whether it's required. For example, required for sellers registered in that country, and optional or required for sellers from elsewhere selling into it.
    - Optional: check EU numbers against the EU VIES service and UK numbers against HMRC.
    - Shown to admin on the seller's details, and printed on invoices where the law needs it.
    - **DB change:** store it with the seller's other tax details; add the migration and mirror it in `backend/web_deploy/edp.sql`.
  - couriers and holidays (optional).
- [ ] **Storage:** move profiles from `config/markets.php` and `config/countries.php` into a database table, for example `markets` (code, name, currency, profile JSON, is_visible, sort order).
  - Keep the config files as the built-in defaults: US and India are seeded from them on migrate.
  - **DB change:** add the migration, and mirror it in `backend/web_deploy/edp.sql`.
- [ ] **Money formatting:** make `Money::format()` and `web/src/money.js` work for any currency, using `Intl.NumberFormat` in the browser and a symbol/decimals table on the server, instead of only USD and INR.
- [ ] **Currency conversion:** add new currencies to the conversion settings (`Fx`) for cross-border orders, with a manual rate field until an automatic rate source exists.
- [ ] **Checks before a new country can be shown:** currency set; tax rule set; at least one way to deliver (NexTech store and riders, or sellers shipping themselves); seller onboarding fields complete.
- [ ] **Tests:** add a country, show and hide it, a seller registers there, checkout prices and tax in its currency, payouts use its limits, and hiding removes it from the storefront and admin.

### C. More ideas
- [ ] **Same forms for every country:** the US still uses the original charge forms, other countries a simpler one (`Market::usesLegacySettings()`). Give every country the same fields, e.g. the **distance-based delivery fee** (near store / edge of radius), which only the US has today.
- [ ] **Payment methods per country:** cash on delivery and card availability per country; local methods where they matter (e.g. UPI in India). Check the card processor supports the currency before a country can be shown.
- [ ] **Holidays and couriers editable by admin** per country (today they're in `config/markets.php`), so a new courier or a changed holiday doesn't need a developer.
- [ ] **Legal footer details per country:** generalise India's grievance officer into "required legal info" per country (e.g. EU trader details, UK company number), shown in that country's storefront footer.
- [ ] **Time zone per country:** for order cut-off times, deal start/end times, holiday dates and reports.
- [ ] **Language per country:** the storefront shows a language choice; translations for each country's language (start with labels, then pages).
- [ ] **Selling between countries:** which countries a seller can ship to is set per seller today; let admin choose which country pairs are allowed at all (e.g. India → UAE yes, India → US no).

### Notes / risks
- Each country has its own tax law (VAT, GST, sales tax, invoice rules). The template only sets how tax is charged; confirm the rules for each country before going live.
- Payment provider: check the card processor supports the currency and country.
- Existing code that special-cases `'US'` or `'IN'` needs reviewing, for example `Market::usesLegacySettings()` and India's GST/TCS withholding.

---

## 2. Sellers' own delivery staff (riders per seller)

### Today
- Sellers who ship themselves can offer **Own delivery (local)**: buyers within a radius of a ship-from address get the seller's own delivery, free or for a flat fee (Shipping settings; admin can hide it and cap the distance in Settings → Shipping).
- There's no tracking number. The seller clicks **Out for delivery (own delivery)**, the buyer gets a delivery code, and the seller enters it to mark the order delivered.
- The rider system (Rider app, shifts, auto-assign, rider pay) only serves NexTech's own stores, managed by admin.

### Goal
A seller can manage their own delivery people inside NexTech, like admin manages riders: sign-in, shifts, working hours and assigning orders, for faster local deliveries within the seller's radius.

### Plan
- [ ] **Seller Center → Delivery staff:** the seller adds a delivery person by phone or email, and can pause or remove them.
- [ ] **Rider app:** the same Rider app; a seller's staff only see that shop's own-delivery orders.
  - Link: e.g. `users.rider_shop_id`, or a `shop_riders` table.
  - **DB change:** add the migration, and mirror it in `backend/web_deploy/edp.sql`.
- [ ] **Shifts:** staff clock in and out (reuse `RiderShift` / `RiderShiftBreak`). The seller sees who is online and their working hours per day and week.
- [ ] **Assigning:** the seller assigns each own-delivery order, or turns on auto-assign to the nearest online staff member within the shop's radius (reuse the `RiderAssignment` logic, limited to that shop).
- [ ] **Steps:** picked up → out for delivery → delivered, confirmed with the buyer's delivery code. Buyer, seller and admin (order view and support chat) see each step live.
- [ ] **Pay:** the seller pays their own staff outside NexTech, so no rider ledger or payouts for them. Optional later: a per-delivery record the seller can export.
- [ ] **Admin:**
  - a setting to allow or hide seller delivery staff. It only makes sense when Own delivery is available (dependent-settings rule: hidden → hide its options and don't require them);
  - a read-only list of each seller's staff and shifts;
  - the ability to deactivate a staff member.
- [ ] **Reminders:** the hourly seller reminders also cover orders assigned to staff but not picked up or delivered in time.
- [ ] **Tests:**
  - add a staff member who signs in and only sees their shop's orders;
  - clock in and out;
  - auto-assign within the radius;
  - delivered only with the right code;
  - admin hiding the option blocks it.

### Notes / risks
- Privacy: staff see the buyer's name, address and phone only for orders assigned to them, and only until they're delivered.
- One person can't be both a NexTech rider and a seller's staff member at the same time, or keep it to separate accounts.

---

## 3. Seller guide — PDF and in Seller Center (do last, once seller features are approved)

### Goal
A plain-language guide for sellers, covering everything they can do on the platform, step by step, with the conditions each task needs. It's used to teach new sellers and as a reference.

### Where
- [ ] **Files:** `Documents/Seller Documents/` in the repo, with one source file per topic (Markdown) plus the combined **PDF** (e.g. `NexTech-Seller-Guide.pdf`), generated with the existing dompdf setup or a build script.
- [ ] **Online:** Seller Center → My account → **Seller guide**, visible only to signed-in sellers.
  - The same topics, searchable, with a "Download PDF" button.
  - Served by an authenticated endpoint, not a public page.
- [ ] Sources to use: `work_done.md`, `tasks_done_today.md`, `flowcharts.md` and the Seller Center screens themselves (check every step against the live UI).

### Topics (each one: what it is, step-by-step, "You need first", tips)
1. **Joining:**
   - the application steps (business, ID, shop, documents including proof of address);
   - Save and finish later;
   - what admin checks;
   - Request changes / resubmitting;
   - the registered address lock and how to change it.
2. **Policies & rules:** which policies must be signed and when (to sell / to sell abroad), the signing window, and re-accepting a changed policy.
3. **Setup tasks:** tax information, compliance information, how you get paid (bank account or PayPal), and which currency you're paid in.
4. **Shop profile & store decoration:** the minimum number of live products before the design shows, and images, links and downloads.
5. **Products:**
   - adding a product (all wizard steps), variations and quantity, your own codes (optional);
   - digital downloads, product guides and documents, compliance documents;
   - drafts (Incomplete), review, held edits on live products;
   - out of stock / Update stock, hiding or deleting, trademarks.
6. **Shipping settings:**
   - how you ship (NexTech collects / own courier / NexTech label);
   - ship-from addresses, shipping templates (fees, transit days, address types);
   - the free-shipping rule, working days and holidays;
   - cash on delivery (conditions);
   - Own delivery (local): radius, fee, delivery code;
   - selling abroad: export ID, declaration, fees per country, customs/paperwork fee, the International Delivery sheet, money held until delivery plus the warranty.
7. **Orders:**
   - Manage orders tabs;
   - Ship orders: Mark packed, confirm a shipment (courier and tracking; "Other" courier name and link), labels, own-delivery dispatch and delivery code;
   - progress updates (in transit, out for delivery, delivered, cash collected);
   - reminders and deadlines (ship-by, repeat reminders, overdue flagged to admin);
   - address-change requests, cancellations, returns.
8. **Money:**
   - balance, held for returns/warranty, available to pay out;
   - minimum payout (per country and method), maximum, daily limit;
   - withdrawal fees, requesting a payout, the ledger.
9. **Messages & customer chats:** messages with NexTech; customer order chats you're brought into by admin, and their privacy rules.
10. **Notifications:** what you're emailed or alerted about, and when.
11. **Conditions quick-reference table:** "To do X you need Y". For example:
    - submit a product → the "to sell" policies signed, shipping set up, listing details complete;
    - sell abroad → own courier, the "sell abroad" policies, export ID and declaration;
    - cash on delivery → admin allows it, you ship yourself, your cash owed is under the limit;
    - payout → available balance at or over the minimum, a payout method set, bank verified.
12. **FAQ / troubleshooting.**

### Notes
- Keep the language simple: short steps, one action per step, screenshots where useful.
- Mark each section with the date it was last checked against the app. Update it whenever a seller feature changes.
- Country differences (US / India): tax IDs, couriers, currencies and export ID (IEC).

---

## 4. Flowcharts for every setting, per role (do last, with task 3)

### Goal
Visual flowcharts of every decision ("if this setting is on / off, then…") so each role understands what each setting does and what it unlocks. Separate charts per role and per area; wide where needed, with standard symbols.

### Format
- [ ] Mermaid flowcharts (render on GitHub and in the app), extending the existing `flowcharts.md`. Export each one to SVG/PDF for printing.
- [ ] Standard symbols:
  - oval = start / end;
  - rectangle = action / step;
  - diamond = decision (setting or condition);
  - parallelogram = input (form / upload);
  - document shape = PDF / email produced;
  - cylinder = saved data;
  - dashed lines for automatic / scheduled steps (reminders, auto-assign).
- [ ] One chart per area, each with a legend, the role in the title, and the date it was checked against the app.

### Where
- [ ] `Documents/Flowcharts/Seller/…` and `Documents/Flowcharts/Admin/…` (plus Rider and Buyer).
- [ ] **Seller:** shown in Seller Center → My account → Seller guide (with task 3), signed-in only.
- [ ] **Admin:** shown in the admin console (e.g. Settings → Help → Flowcharts), admin only.

### Charts (one each)
- **Seller:**
  - joining (application, documents, proof of address, Request changes, approval, address lock);
  - policies to sign (selling / abroad, the signing window, re-accepting);
  - setup tasks and payout method;
  - adding a product (type, variations, digital, refurbished, drafts, review, held edits);
  - shipping settings (fulfillment mode → templates / labels / NexTech pickup; COD; own delivery; selling abroad);
  - order handling (packed → shipped → in transit → out for delivery → delivered / cash collected; own delivery code; reminders and escalation);
  - returns and address changes;
  - money (holds, minimum / maximum, withdrawal fees, payout request).
- **Admin:**
  - setup checklist;
  - each Settings group with its dependent settings (shipping, checkout charges and payment, NexTech delivery, sellers' own delivery, selling abroad, reminders, payouts, countries, currency);
  - seller review (application, onboarding tasks, international stop / allow);
  - product review (pending, held edits, follow-ups, deletion requests, demo / ad / live, show / hide);
  - orders (riders vs courier vs hand-booked courier, seller packages, address changes, refunds, cancellations);
  - reviews moderation;
  - support chat (bringing in a seller);
  - house shop;
  - affiliate ads.
- **Rider:** shifts, offers, delivery code, cash, payouts.
- **Buyer:** browse / Recommended, cart and checkout (delivery method, fees, tax, COD rules), tracking, returns, reviews, chat.

### Notes
- Build them from the code (each `if` in controllers / `Support` classes), not from memory; list the setting key next to each diamond.
- Keep them up to date: update the chart whenever a setting changes (could be checked in review).

---

## Customer chat (no change needed — for reference)
- Sellers can't start chats with buyers. Admin brings a seller into a customer's order chat when needed (**Bring in [shop]** in the support chat). Admin stays in the chat, and the seller sees only a masked name and their own items.
- Decided: no buyer-side "Ask the seller to join" button. Admin decides when to bring the seller in.
