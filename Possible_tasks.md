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
- Sellers who ship themselves can offer **Own delivery (local)**: buyers within a radius of a ship-from address get the seller's own delivery, free or for a flat fee (Shipping settings; admin can hide it and cap the distance in Shipping → Sellers' own delivery).
- There's no tracking number. The seller clicks **Out for delivery (own delivery)**, the buyer gets a delivery code, and the seller enters it to mark the order delivered. Hourly reminders chase sellers who don't update.
- Stores / hubs have no owner: every store is NexTech's. Riders are always NexTech's, and are linked to at least one store when hired (the store they return cash to).
- The admin Riders list has no filters yet (store, country, status, on shift).

### Goal (agreed 8 Oct)
A seller with local delivery runs their own riders inside NexTech, using the same rider system, while admin watches everything in one place:
- **Stores / hubs** lists NexTech's own stores **and** sellers' stores, each tagged **Own store** or **Seller**, with a **Local delivery: Active / Inactive** switch per seller store.
- The seller sets their store's address and radius, sees only their own riders (the same details admin sees), and moves their own orders through the steps. A rider is chosen from the seller's on-shift riders; with none free, the seller sends it by courier as today.
- Admin sees every status but can't change a seller's order steps (the order is the seller's). The seller pays their own riders. Cash on delivery collected by a seller's rider stays with the seller (the existing seller-COD rules and owed limit apply).

### Plan (one step at a time, each tested before the next)
- [x] **1. Seller stores in Stores / hubs.** *(done 8 Oct; the seller creating their own store moves to step 2; `edp.sql` not updated, since deploy files are made only when asked)*
  - `stores.shop_id` (null = NexTech's own) and `stores.local_delivery_active`. Migration, mirrored in `backend/web_deploy/edp.sql`.
  - Admin list: an **Own store / Seller** tag, a filter, and the Active / Inactive switch.
  - Seller name opens a card: phone, email, business type, application status, shop status, last message, and a link to the full seller page.
  - A seller store is created when the seller turns on local delivery (from their ship-from address and radius), or by admin for them.
  - NexTech's checkout area / delivery-fee logic must **ignore** seller stores (they don't deliver NexTech stock).
- [x] **2. Seller Center → Local delivery → My store.** *(done 8 Oct: the existing Shipping settings → Own delivery (local) form (address, radius, fee, days) now creates or updates the seller's store; admin's switch blocks it, with a reason messaged to the seller. The hiring guide comes with step 3.)* The seller sets the address (map pin) and radius, capped by admin's maximum. This replaces today's own-delivery radius (one place, not two). Only shown when admin allows own delivery and the store is Active (dependent-settings rule).
- [x] **2b. Every seller has a store; turning local delivery off is confirmed by admin** *(agreed and done 8 Oct)*.
  - Every seller shop gets a store in Stores / hubs automatically (from its ship-from or registered address), with local delivery **off**. Admin can switch it on for any seller (and hire riders there), or the seller switches it on from Own delivery (local).
  - **Seller turning it off:** a popup first ("This affects N riders linked to your store. Once turned off you can't turn it back on yourself; only admin can, e.g. when you have riders again"). With riders linked, it becomes a **request to admin** (Sellers panel + email); local delivery stays on until admin decides. With no riders, it turns off at once.
  - **Admin approves:** local delivery off and locked; linked riders are emailed ("this store has ended local delivery; you won't get its orders; delivery fees you earned are paid at the end of the month to your payout method"). They **stay linked** in the system (their pay is still due); a store that's off sends them no orders. Their other stores aren't affected. **Admin keeps it on:** the request is cleared and the seller is told why. Riders hear nothing until admin approves (a mistaken click never reaches them).
  - **Turning back on** after it was turned off: only admin.
- [x] **2c. Fixes to seller stores** *(agreed and done 8 Oct)*.
  - Stores / hubs shows a seller's store only once the seller is approved.
  - **No lock without riders:** a seller with no riders linked can turn local delivery off and on again freely. The lock (only the store can turn it on again) applies only after the store approves turning it off with riders linked.
  - **No pause button:** dropped (8 Oct). A seller can always deliver nearby orders themselves or send them by courier, so local delivery doesn't need pausing.
  - **Own stores too:** each NexTech store has its own "Local delivery (riders)" switch: Riders on / Courier only. New stores start with riders off, and turning them on needs a rider linked. Checkout uses riders only from stores with it on (the global setting in Shipping → NexTech delivery still applies on top).
  - **Map point:** the seller can set their store's exact latitude / longitude (or keep the one found from the address).
  - **Maximum area:** default maximum radius 10 km (admin can change it, Shipping → Sellers' own delivery), so riders' fuel cost stays worth each delivery.
  - **Told in advance:** turning on local delivery tells the seller they deliver themselves until riders are hired, and that **cash on delivery collected by their riders is theirs to collect from the rider by the end of each day — the store isn't responsible for it**. Delivery fees: buyers pay the fee the seller sets; if the seller offers free delivery they cover it.
  - Seller-facing messages use the store's name, not "admin".
- [x] **3. Hiring riders for a store (reuse the rider application flow).** *(done 8 Oct; a seller pausing a single rider and the rider moving location are still open — see Later)*
  - **Today:** a signed-in buyer applies at `/rider` ("Deliver with NexTech"): picks a nearby store, gives phone, home address, vehicle, licence number and licence document; admin approves and the rider is linked to that store (`rider_store` table, which already links riders to stores, several per rider).
  - **Hiring open per store:** a switch on each store (admin for any store; the seller for their own store once local delivery is Active). Only stores with hiring open are offered on the application page.
  - **"Work with us — deliver" link** in the storefront footer, shown to signed-in buyers only when a store near them (own or a seller's with local delivery Active) has hiring open.
  - **More on the form:** email, date of birth, ID proof (upload), education document (optional), photo, and a tick "I have my own vehicle and pay its running costs". **Minimum age per country** (18 in the US and India; admin-set per country): younger applicants can't submit, and the age is checked against the ID document when accepting.
  - **Who decides:** applications to a seller's store go to that seller (Seller Center → Local delivery → Applications) to accept or decline; admin sees them all and can accept or decline too. Applications to NexTech stores stay with admin.
  - **Linking:** admin can link or unlink any rider at any store (NexTech's or a seller's). When admin unlinks or pauses a rider the seller hired, the seller gets one notification with admin's written reason. A seller can only link or unlink riders on their own store. Riders see orders only from their linked, Active stores.
  - The seller sees their riders (name, phone, vehicle, on shift, active jobs, today's deliveries) and can pause or remove them from their store, **only when the rider has no open orders** (otherwise: "finish or reassign their N open orders first").
  - **Seller steps shown in Seller Center:** 1) turn on local delivery, 2) open hiring (Seller Center then says "The Apply link is now live for buyers near your store"), 3) find people: post the job on sites like Quikr or OLX, put a poster outside the shop, or ask people you know and trust who are looking for work; they sign up as a buyer and apply from the footer link (or a link the seller shares), 4) accept them under Applications, 5) they sign in to the Rider app and start shifts. Riders must have **their own vehicle and pay its running costs**.
- [x] **3b. Rider profile and stricter hiring** *(agreed and done 8 Oct)*.
  - **Profile from the application:** hiring copies the rider's details into their rider profile: home base location (required; the address must be found on the map), passport-size photo (required), and all application details. The Riders list shows the photo; opening a rider shows everything (education, work history, experience, documents, health, store preferences), so nothing has to be looked up in old application entries.
  - **More on the form:** highest education and past work history (shown to admin / seller when deciding); driving licence **and vehicle RC** (registration) for motor vehicles; "any health condition that could affect delivery work?" (yes / no + details); the stores they're willing to work for, **in order of priority** (so they can be moved to the next one when a place opens or closes).
  - **Consent tick:** "If stores aren't available, or for bad behaviour, health or other issues, the seller or the store can remove me at any time."
  - **Minimum requirements enforced on accepting** (seller or admin): age at least the country's minimum (from date of birth; checked against the ID), ID proof, licence and RC for motor vehicles. The Accept button is blocked otherwise, so a seller can't hire anyone under 18.
  - **Removing a rider (seller):** needs a written reason, which is sent to the store's admin.
  - **Confirm popups** before serious steps by the seller or admin (removing a rider, deleting a store, turning local delivery off), saying who else it affects.
  - Admin Riders list: no per-store time; the Stores list shows how long each store has been listed.
- [x] **3c. Seller recommends, admin approves** *(agreed and done 8 Oct)*: a seller's "Accept" sends the applicant to the store's admin for final approval (documents checked by admin), so a seller can't hire anyone local without a check. Admin approves (rider hired at the seller's store) or declines (the seller is told why).
- [x] **3d. Notice period for riders** *(agreed and done 8 Oct)*: consent tick "I'll give at least 30 days' notice before I stop working. If I leave without notice, my final pay is settled only after the store checks my open orders and any cash I hold, and I may not be hired again." Earned pay can't legally be withheld (US wage laws, India's Payment of Wages Act), so no "no payment" rule; settlement waits for checks instead. A rider can give notice from the Rider app; their stores see the leaving date.
- [x] **3e. Admin notifications for riders** *(agreed and done 8 Oct)*: rider applications waiting (and sellers' accepted riders) stay in the admin's top notifications until approved or declined (not dismissible); a rider's notice shows once when given and again when the leaving date arrives, until admin marks it processed (final pay settled). Admin's Approve popup says it's only to verify the basic requirements are met.
- [x] **4. Admin Riders filters:** *(done 8 Oct)* by store (dropdown, own and seller stores), country, status (active / paused / pending) and on shift.
- [x] **5. Seller order steps with their riders.** *(done 8 Oct, incl. "Auto — nearest free rider")*
  - Packed → Assigned to rider (pick an on-shift rider; optional auto-assign to the nearest, reusing `RiderAssignment` limited to the seller's store) → Out for delivery → Delivered with the buyer's code. The rider can do the same steps in the Rider app.
  - No rider free: the seller switches that order to courier (carrier, tracking number and link, as today).
  - Admin order view: read-only for these steps, with the full history (who, when). Admin keeps only **Cancel / refund** for disputes.
  - The existing hourly reminders cover "assigned but not picked up / not delivered in time".
  - **Deliver it myself (per order, agreed 8 Oct):** besides picking a rider or a courier, the seller can choose "I'll deliver this myself" for any local order. No rider is assigned and no rider pay or per-delivery rider fee is charged for it. The buyer's local delivery fee (if any) goes to the seller as today; with free delivery, nothing is charged to anyone. The rider money table records it as self-delivered.
- [x] **6. Cash on delivery by a seller's rider.** *(done 8 Oct: cash received / Later / limit / evening reminder / morning pause / rider + seller notifications. The grace for riders owed more than they hold, and the month-end offset, wait for the rider money table.)* The rider marks cash collected and the seller confirms receiving it from the rider. The order counts as "cash kept by seller", so NexTech's commission and fees come out of the seller's next earnings and the owed limit pauses COD when exceeded. No cash returns to NexTech stores.
  - **Cash rules for a seller's riders (agreed 8 Oct):**
    - The seller marks **"cash received from rider"** each time the rider hands cash over. They can clear it on every return when there are many cash orders in a day.
    - **Not returned the same day → rider auto-paused the next day** until it's settled; only the seller (for their riders; the store's admin for its own) can resume them, after marking the cash received.
    - **Auto-pause at a maximum:** if the cash a rider holds goes over the store's limit during the day, they're paused at once until they hand it over.
    - When paused, the rider is told: **"Visit the store and hand over the cash before more deliveries."**
    - **Seller notifications:** each cash-on-delivery collection by their rider (amount), and at the end of the day for any cash not returned.
    - **Month-end offset (agreed 8 Oct):** cash a rider still holds at month end is taken from their earned pay and passed to the seller (whose money it is), and the rider gets the rest. Their unpaid earnings act as cover, so the seller's risk is only cash above what the rider is owed.
    - **Grace when the rider is owed more:** if the rider's unpaid earnings are higher than the cash they hold, they get a few days to hand it over instead of a next-day pause. Going over the cash limit still pauses them at once.
    - **Rider notifications:** when they collect cash (amount, total held), at the end of the day (cash to hand over), and when they cross the limit (paused until handed over). Only the seller or the store's admin marking the cash received unpauses them.
    - **"Later" (seller's own risk):** a seller who trusts a rider can press "Later" to keep them working despite the limit. It's the seller's money at risk, so the store isn't responsible for it. Not available for NexTech's own stores.
    - Next to the "Later" button: **"At your own risk — {store} isn't responsible."**
    - **The seller delivering a cash order themselves** counts as "cash kept by seller" under the existing seller cash-on-delivery rules: {store}'s commission and fees come out of their next earnings, and cash on delivery pauses when they owe more than the limit.
    - NexTech's own riders already have a cash-return flow and holding limit; reuse it for sellers' stores.
- [x] **6a. Cash limit for the store's own riders too** *(agreed and done 8 Oct)*: a rider over the store's cash limit (admin-set per country, Shipping → {store} delivery → Cash on delivery), or paused by any seller for cash, is **paused in every store** until it's returned — no offers, no claiming, and the Rider app says why.
- [x] **6c. Cash-on-delivery maximum per order and end-of-day notices** *(agreed and done 8 Oct)*: cash on delivery only on orders up to ₹5,000 / $200 by default (admin can raise it per country, at their own risk; a seller's riders' cash limit lowers it for their own orders); bigger orders pay by card. End of day, the store's own riders holding cash are reminded and the admins told. A local order over the maximum is flagged to the seller ("send it by courier or deliver it yourself, not with a rider") in Ship orders and as a message.
- [ ] **6b. Who carries the risk, and pay for each delivery** *(agreed 8 Oct; done 8 Oct: cash on delivery off by default (already), self-delivery fee goes to the seller (already), terms shown to sellers and riders, buyers see the rider's or seller's name, admin's "Adjust a balance" in Secure access → Payouts. Still to do: per-delivery rider pay paid by the seller — with the rider money table; the 'system failure' clause in the seller policy pages — admin edits those pages)*:
  - **Cash on delivery is off by default for the store's own stores too**; admin can switch it on for their own stores at their own risk, just as each seller does for theirs.
  - **Rider pay goes through the store** (admin pays riders). The only cover for a seller is the rider's earned pay that month: cash a rider kept is taken from it and given to the seller. Beyond that, cash a rider keeps or a product a rider damages is **the seller's risk**; the store can't cover it.
  - **Shown to the seller when they turn on local delivery** (the conditions above), and to riders as **terms they accept before joining** (pay per delivery, cash handover, notice period, removal).
  - **The seller delivering it themselves:** the delivery fee for that order is credited to the seller (in their ledger / payment details).
  - **Given to a rider:** the rider is paid for that one delivery; the seller pays it (from the delivery fee), and the store isn't responsible beyond passing it on.
  - **The seller as a rider:** each seller who delivers gets a rider profile in their name, so the buyer sees "{seller name} is delivering your order" like any rider, not "the seller delivers".
  - **Refunds and system problems:** the seller documents (policies) say the store isn't responsible if the system or server fails. Admin can correct money by hand: a **manual adjustment** (charge or credit, with a reason) on the seller's or rider's ledger before final payments.
- [x] **7c. Seller confirms, no disputes** *(agreed 8 Oct, replaces 7b's auto-confirm and dispute)*: the rider's "I handed over the cash" waits for the seller — **no auto-confirm**; the seller is reminded until they press Confirm or **Not received**. Not received = the rider still holds it and is paused (every store); the seller's only cover is the rider's earnings (taken at month end), nothing beyond — they were warned. No admin dispute step; admin can still **fine** someone found guilty with "Adjust a balance". **One country per rider**: a rider can only be linked to stores in one country.
- [x] **Monthly pay for NexTech riders, with a top-up pool** *(done 9 Oct; covers the "reserve" idea)*: admin sets per rider (Riders → rider → **Pay**): per delivery (default) or **monthly pay** — amount, target deliveries, bonus per extra delivery, bonus cap. On the 1st, last month is paid: full at the target, less in proportion below it, plus the capped bonus; riders are emailed. What busy riders earn above the cap goes into the month's **top-up pool** (Riders → Top-up pools), which admin uses to **Top up** riders who fell short. Rider app → Earnings shows the plan and "this month: 64 of 100". Sellers' riders stay on pay per delivery (set by the seller).
- [ ] **Weekly pay option** for monthly-pay riders (same rules per week).
- [~] (superseded) **7b. Rider's handover claim** *(agreed and done 8 Oct)*: the rider presses "I handed over the cash"; the seller confirms or disputes within 24 hours, or it counts as received automatically (rider unpaused, nothing taken from their earnings). A dispute goes to the store's admin, and nothing is taken from the rider's earnings until it's decided. Protects riders from a seller who never marks cash received.
- [x] **7. Pay / rider money table.** *(done 8 Oct: sellers set pay per delivery — the store pays the rider and charges the seller ("Rider pay"); month-end (and on demand) the cash a rider kept goes to the seller from their earnings ("Cash a rider kept"); a few days' grace when their earnings cover the cash; money table per month for admin (all riders, balance) and sellers (their store); settlement box for each rider (earned − cash held, settle 7 days after the last delivery).)* Originally: The seller pays their riders outside NexTech: no rider ledger or payouts for seller riders. Optional later: a per-delivery list the seller can export.
- [ ] **8. Tests** for each step: seller store hidden from NexTech checkout areas; seller sees only their riders; seller riders never get NexTech orders; admin can't change seller steps; delivered only with the right code; seller COD charged back correctly.

### Decisions (8 Oct)
- **Riders see orders by store link.** A rider gets only orders from the stores they're linked to, within those stores' areas. Seller riders are linked to seller stores, so they never get NexTech orders, and NexTech riders never see a seller's orders. Admin can link one rider to several stores in a shared area on request (e.g. two nearby sellers share free riders); that rider then sees both sellers' orders.
- **Who adds riders:** the seller adds their own riders (name, phone, vehicle, ID / licence upload). No admin pre-approval, so there's no admin bottleneck, which is like Amazon's seller-fulfilled local delivery, where the seller's staff are the seller's responsibility (Temu sellers just use couriers). Admin sees every rider and can **pause** any of them. Admin can also hire riders and link them to sellers' stores in an area.
- **Seller instructions** (Seller Center, shown when turning local delivery on): you hire, manage and pay your riders; if you turn local delivery off, tell them; NexTech isn't their employer.
- **Turning local delivery off:** the store goes Inactive and its riders stop getting its orders, but riders linked to other stores keep working for those. Open orders go back to the seller to send by courier.
- **Rider pay:** NexTech's rider pay counts only deliveries from NexTech's own stores; seller-store deliveries are never paid by NexTech.
- **Cash on delivery** collected for a seller store's order belongs to that seller (seller-COD rules and owed limit); it is never returned to a NexTech store.
- **Checkout:** NexTech's delivery-area / store choice ignores seller stores.
- **Buyer contact:** a rider sees the buyer's name, phone and the order's delivery instructions only for orders assigned to them, until delivered (as NexTech riders do today).

- **Who sees how long (agreed 8 Oct):** sellers and admin see a rider's **work experience** (from the application), time as a rider and time with their store; only **admin** sees how long sellers have sold on the store ("Selling since"); a rider never sees a seller's time; a seller never sees other sellers' or the store's own figures.

### Still open
- **Admin-hired riders (agreed 8 Oct):** NexTech pays them, weekly or monthly as set when hiring. NexTech keeps the delivery fee buyers pay; when the seller offers free delivery, the fee is deducted from the seller's earnings instead.
  - **Pay terms shown to the rider when hired:** a set pay per week / month **if** they reach a target of X deliveries (worth at least that pay in delivery fees). Below the target, pay is reduced in proportion. Above it, a bonus per extra delivery, capped at an amount admin sets.
  - **Top-up pool (agreed 8 Oct):** what busy riders earn above the bonus cap goes into a pool for that period (per store or area). Admin can use it to top up riders who fell short of their minimum pay, so everyone is covered where possible, as many delivery companies smooth pay. The table shows the pool, who was topped up and by how much.
  - Pay due reminders and the rider money table (below) work out each period's pay.
- **COD lost by a seller's rider:** the seller bears it (agreed 8 Oct).

### Later (not now)
- [x] **Needs courier alert** *(done 9 Oct)*: a NexTech local order no rider takes within a set time (now the same rider timer, default **5 hours**) or an order outside every area with no courier account → admin emailed, 🔔 "Send by courier", Orders filter / pill; cleared once it goes out.
- [x] **One rider timer per order** *(done 9 Oct)*: riders have a set time to take a ready order (admin sets 1 minute – 1 day, **default 5 hours**). The nearest rider is alerted; every on-shift rider at the store sees it with the same countdown and the first to take it gets it. Time's up with nobody → it stays open to all riders and the store (admin) is emailed / 🔔 **Needs courier**: call a rider, deliver it yourself, or book a courier. No rider available at all (leave, logged out, paused) → told as soon as it's ready. Long offers: "Decide later" (Accept / Reject stay on the card).
- [x] **Days off told to buyers and riders** *(done 9 Oct)*: checkout's "arrives …" line for seller items says which seller days off it skips (weekends not shipped, holidays not worked); sellers' riders see each store's days off for the next 2 weeks in the Rider app.
- [x] **Breaks and clock-out move offers on** *(done 9 Oct)*: a rider going on a break or clocking out with an offer not yet accepted → it goes to the next available rider, or the store is told at once. (Clocked-out / on-break riders were already never offered orders.)
- [x] **Holidays managed by admin; sellers ask for days off** *(done 9 Oct)*: Shipping → **Holidays** (per country): built-in list, holidays admin adds for the **whole country** (sellers tick them if they work) or **one store** (always off), and sellers' **day-off requests** (Add for this store / whole country / Decline; also in the 🔔; seller told). Seller: Shipping settings → Holiday settings → **Your store's days off** + "Ask for a day off". Store days off count in delivery dates, the buyer's note and riders' timetable.
- [x] **Own delivery Save button feedback** *(done 9 Oct)*: the result now shows next to "Save own delivery" (it was only at the top of the page, so the button looked dead); unticking when it's already off says so. The store's map point comes from the address when it's created, so latitude / longitude stay optional.
- [x] **Rider application at its own address** *(done 9 Oct)*: **/rider/apply** (or `#/rider-apply`) is the hiring form; **/rider** stays the riders' sign-in and console (a non-rider account there is told so, with Sign out / Apply). The storefront "Work with us" link and the seller's hiring guide point to /rider/apply.
- [x] **Sellers' days off reach admin and riders** *(done 9 Oct)*: 6 pm the day before, the store's riders are told "no deliveries tomorrow" and admin gets the list of seller stores closed tomorrow (weekdays only); at 00:15 riders whose every open store is closed get the day off automatically (unavailable, reason shown; they can still clock in if asked), and are available again on the next open day. Moving festivals (Diwali, Holi) are listed to 2028 — admin adds later years in Shipping → Holidays; fixed national holidays need nothing.
- [x] **Clearer own-delivery saved message** *(done 9 Oct)*: says buyers within X km of your address get your own local delivery (fee, days), by you or your riders, and buyers further away get courier shipping.
- [x] **One deadline per order for every rider** *(done 9 Oct)*: pick-up deadline = ready time + riders' time (default 5 h), the same for all. Riders who come online later (clock in / back from a break) are offered open orders, ending at that same deadline; the pool shows "Open to all riders — X left". No rider online when it's ready → admin gets a heads-up (once), but the order stays open; only at the deadline is the store told nobody took it (Needs courier).
- [x] **"Own delivery" renamed "Local delivery"** *(done 9 Oct)*: Shipping settings → **Local delivery**, tick "Deliver to buyers near my shop (local delivery)", button **Save local delivery**; the saved message says it's only for buyers within X km of the shop address (others still get courier shipping); checkout shows "Local delivery by {shop} (you're near their shop)"; seller messages, order pill and admin labels use the same words.
- [x] **Local delivery box in plain words** *(done 9 Oct)*: status pill **ON / OFF / Turning off — waiting**; no tick box — **green "Turn on local deliveries"** when off (shows the form, then turns it on), **"Save changes"** + **red "Turn off local deliveries"** when on; map point tucked under "Shop location on the map (optional)"; messages in everyday words.
- [x] **Seller cash on delivery: "Only for local deliveries"** *(done 9 Oct)*: new admin choice in Shipping → Seller cash on delivery — sellers may take cash only when they (or their riders) deliver locally, on orders up to the cash-on-delivery maximum (a rider's earnings cover that risk). Checked at checkout once the delivery type is known. The local seller's switch was **Off** since 7 Oct (that's why cash on delivery stopped) — admin picks a mode to allow it again.
- [x] **Local delivery rules always shown** *(done 9 Oct)*: "How local delivery works — please read" (you deliver until riders are hired, fee is yours, rider pay comes from your earnings, cash on delivery is yours to collect every evening, cash or products riders keep or damage are your loss — NexTech isn't responsible beyond the rider's unpaid earnings, NexTech isn't the riders' employer, keep the area small) stays visible whether it's on or off.
- [x] **Live updates without refreshing** *(done 9 Oct)*: any saved change rewrites a tiny static file `backend/public/live.json` (once per request; not for sign-in tokens or rider GPS pings). Open pages read it every 3 s while visible — the web server serves it directly, no PHP / database — and fetch their data again only when it changed (and when you come back to the tab). Nothing reloads, typed text stays. Live now: admin Stores, Riders, Sellers, Customers, Products, Support, Shipping → Holidays (plus Orders' own 15 s refresh); Seller Center Shipping settings, Orders you ship, Manage orders, Local delivery & riders; Rider app already refreshes every 15–30 s. **Server:** `backend/public` must be writable by PHP (for live.json).
- [x] **Instant updates between tabs of one browser** *(done 9 Oct)*: after any successful save, the tab tells the browser's other open tabs (BroadcastChannel) and they fetch their data at once — e.g. admin and Seller Center open side by side. Other computers get it within ~3 s via live.json.
- [x] **Light on the server, chats instant** *(done 9 Oct)*: the live.json check slows down when nothing happens (10 s → 30 s; back to 10 s after a change or a click / key; nothing while the tab is hidden); admin Orders and other tables refresh only when something changed (+ 2-minute backup). **Chats and notifications keep their fast checks** (admin 🔔 10 s, open chat 5 s; Seller Center messages 8 s / 10 s, new-order alerts 30 s; Rider app 15 s / 30 s) and also react at once to a change. Same-browser tabs: instant, no server request.
- [x] **Store working hours for riders** *(done 9 Oct)*: the seller (Local delivery & riders → Riders' working hours) or admin (Stores → own store → Set riders' hours) sets days + from / to. Riders see "your working hours are …" in the Rider app; not clocked in when hours start → reminder (once a day); 30 minutes in → the store is told who is missing; clocking out during hours asks the rider to confirm and tells the store. Store days off and riders' days off don't count. Riders off by default: new sellers start with local delivery and hiring off.
- [x] **Rider signs the store's terms to join** *(done 9 Oct)*: once documents and ticks are done, the application shows "Before you join — the store's terms" (polite and well-behaved, neat; on time for the store's hours; days off up to N a month asked a day ahead — missing a day without asking may affect that day's pay or this agreement; fuel, vehicle upkeep, repairs, phone and data at own cost; look after products, hand over cash the same day; follow the store's and seller's rules — {store}'s decision is final on pay, hours, days off and other matters; 30 days' notice). The rider types their **full name** and **current town** as a signature (saved with the date) and presses **Sign and submit to join**.
- [x] **Days off and missed days** *(done 9 Oct)*: stores set **days off allowed a month** with the working hours (default 4). Rider app → **Days off**: used / allowed this month, ask for a day off (a day ahead; today is allowed but flagged), take back a future one; every store they work for is told. On a day off they're off duty and not reminded. Not clocked in 30 minutes into working hours without asking → recorded as **missed**, the rider is told it may affect that day's pay or their agreement, and the store is told when they're over the allowance. Break over an hour during working hours → reminder, store told. Seller sees riders' days off this month in Local delivery & riders. Riders with no active store see no Days off section and can't ask for one (they're off anyway).
- [x] **Signed terms saved** *(done 9 Oct)*: the terms come from the server (one list per store, incl. its hours and days off) and the exact text signed is saved with the name, place and date; admin sees it in the rider panel → **Signed terms**.
- [x] **Bonus for excellent work** *(done 9 Oct)*: terms say "Very good work — 5-star ratings on a high number of deliveries — can earn a bonus" ({store}'s decision is final on bonuses too). On the 1st of each month, riders whose last month had at least N deliveries, a rating of at least ★4.8 and earnings above a normal month's pay (admin sets all three per country in Shipping → NexTech delivery; defaults 100 / 4.8 / ₹20,000 or $2,000) show in the admin 🔔 as **Bonus suggestions** (+ email); admin adds it with Secure access → Payouts → Adjust a balance and dismisses the suggestion. Their seller is told too.
- [x] **Signed terms for seller and rider** *(done 9 Oct)*: the seller sees each rider's signed terms (Local delivery & riders → Signed terms); the rider opens **My signed terms** in the Rider app and can **Print / save as PDF**.
- [x] **Seller bonus for their riders** *(done 9 Oct)*: Local delivery & riders → **Give a bonus** (amount + note), paid from the seller's available earnings ("Rider bonus" in Finances), added to the rider's earnings ("🏅 Bonus") and counted in the rider money table; the rider is told. Admin's bonus suggestions now run on the **25th** for the month so far, so bonuses can be paid by the month's end.
- [x] **Riders' payout method from the store's allowed list** *(done 9 Oct)*: Rider app → Earnings → Payout method offers only the methods admin allows in the rider's country (Secure access → Withdrawal fees; bank / PayPal), and the server refuses others. **No clock-in until one is saved** ("Add how you want to be paid first…").
- [x] **Payout method on the application** *(done 9 Oct)*: the final step asks "How you want to be paid" (only the methods allowed in that country; bank details or PayPal email, stored encrypted and never shown in lists); on approval it becomes the rider's payout method, so they can clock in on day one (they can change it in Earnings).
- [x] **Joining letter instead of the full terms** *(done 9 Oct)*: the full terms are shown once, when joining (the store keeps the signed copy); the Rider app's **Documents** shows a joining letter per store — joined on this date, pay (per delivery / base + per mile), working hours. No print / PDF of the terms.
- [x] **Payday reminder without a payout method** *(done 9 Oct)*: on the 25th, riders owed pay with no allowed payout method are emailed, and the sellers they work for are told to remind them.
- [x] **Routine emails on / off** *(done 9 Oct)*: riders (Rider app), sellers (Local delivery & riders) and admins (🔊 menu) can untick **Email me routine reminders** — clock-in / break reminders, store closed tomorrow, each cash collection, orders to take, riders not on duty, the seller's email when a rider asks for a day off in good time, stores closed tomorrow (admin). Important emails always go (pay, cash to hand over, pauses, missed days, decisions, orders needing a courier).
- [ ] **Pay effect of missed days:** today riders are paid per delivery, so a missed day already earns nothing; when fixed monthly pay arrives, deduct missed / over-allowance days from it.
- [ ] **Changes made with bulk updates don't move live.json** (e.g. cash handover marks, some sweeps use `->update()` on a query): they show within the 2-minute backup. Bump live.json in those spots if it matters.
- [x] **Rider terms wording** *(done 9 Oct)*: no "independent / not an employee" lines. Seller rules: "You choose who delivers for your shop and check them. {store} pays them for each delivery for you." Rider application: "I work the hours the store sets, use my own vehicle, and I'm paid per delivery as the store sets it, through {store}. {store}'s decision is final on pay, working hours, days off and other matters; the seller I deliver for can also decide these for their store." Store name always comes from admin settings.
- [ ] **Server push (WebSockets) later:** truly instant updates on every device (Laravel Reverb or a hosted service like Pusher) — needs a program running permanently on the server, which shared cPanel hosting usually doesn't allow; live.json polling covers it until then.
- [x] **Live updates for the remaining pages** *(done 9 Oct)*: buyer's Orders page (while open), Seller Center balance / Finances, Products and Orders lists, admin Dashboard figures — all refresh when something changes. Left as they are on purpose: admin Settings / Shipping forms (so a refresh never interrupts typing) and Secure access → Payouts (reload when opened).
- [ ] **Rider's earnings as cover for bigger cash orders (idea, 9 Oct):** let a rider carry cash orders above the maximum when their unpaid earnings (e.g. the last days') cover the amount — ties in with the "rider cash limit tied to earnings" and "reserve" items below.
- [x] **Delivery zones within local delivery** *(done 9 Oct)*: Shipping settings → Local delivery → **"You deliver yourself within (km)"** (optional). Checkout saves how far each local buyer is; Orders you ship shows "1.2 km from your shop — your own zone: you deliver" or "— for your riders", and **Who delivers it** starts on "I'll deliver it myself" inside the zone and "Offer to all my riders" beyond it (the seller can still change it).
- [x] **Riders' own leave days** *(done 9 Oct — see "Days off and missed days")*: riders ask for days off in the Rider app (a day ahead), the store is told; the store's allowance a month; missed days recorded.
- [x] **Same deadline for sellers' riders** *(done 9 Oct)*: Who delivers it → **"Offer to all my riders (first to take it)"**: every rider at the seller's store sees it under **Open to take** in the Rider app with one deadline (seller sets hours in Local delivery & riders, 1–72, default 5; riders who come online later see the same countdown); **Take it** = first come, first served, then the buyer gets the delivery code. Nobody online when offered → seller told at once; nobody by the deadline → seller told (message + email) and the order shows "Nobody took it" — still open to riders. Riders on a break / logged out / day off aren't alerted.
- [x] **Seller shipping days wording** *(done 9 Oct)*: "I also ship on Saturdays / Sundays" (added to Mon–Fri, not instead); holidays ticked only when you and your courier both work.
- [x] **Seller menu renamed "Local delivery & riders"** so sellers find their riders.
- [x] **Arrival deadlines** *(done 9 Oct; part of "delivery time by distance")*: sellers already set the days per type (Local delivery → "Arrives within (days)"; courier → shipping templates' transit days), and buyers see the "arrives by" date. Now, hourly: a seller's order due **today or tomorrow** and not delivered → the seller is reminded (once a day); **past the date** and still not delivered → admin is told once. NexTech's own orders: the rider deadline / Needs courier covers them; far orders are flagged to admin at once.
- [x] **Performance watch for admin** *(done 9 Oct)*: daily, over the last 30 days — products rated under ★2.5 (5+ reviews); sellers whose products average under ★3, who had 3+ orders not shipped on time, or whom riders rate under ★3; riders whose deliveries average under ★3.5 (5+ ratings) or who missed 3+ working days without asking; buyers riders rate under ★2.5 (3+ deliveries). New warnings go to the admin 🔔 **Performance warnings** (open the rider / seller, dismiss ×) and by email, not repeated within a week. (Already there before: each bad delivery / chat / site rating in the 🔔, seller update reminders and overdue-shipping alerts, late-arrival alerts.)
- [x] **Riders rate the store and the buyer** *(done 9 Oct)*: Rider app → **Rate recent deliveries** (last 3 days): store ★ and buyer ★ + an optional note — private, only admin sees it (never the seller or buyer). Admin sees how each rider rates others ("Rates others" in the rider panel), and the performance watch flags sellers / buyers riders keep rating poorly.
- [x] **Riders' notes in the admin seller card** *(done 9 Oct)*: "Riders say ★x from N deliveries" with low ratings and notes (private — the seller never sees it). Still open: **auto-actions** on repeated warnings (pause a seller's local delivery or a rider) — admin decides by hand for now.
- [x] **Admin limits on delivery days** *(done 9 Oct)*: Shipping → Sellers' local delivery → **Most days a seller's local delivery may take** (default 7) and **Most courier transit days a seller may promise** (default 30); sellers can't save more.
- [x] **Rider ↔ seller chat with support tickets** *(done 9 Oct)*: Rider app → **Chat with your sellers**; the seller sees it as "Rider {name}". Either side can **Open a support ticket** (one at a time) — no email to admin: it shows in the admin 🔔 **Support tickets**; admin assigns a support name and answers within 1–2 working days. Support closes it as **resolved** or **"can't take this up now"** (a polite note asks them to settle it between them); the side that opened it can also close it. Not in the rider's buyer Help inbox.
- [x] **Support names instead of admin** *(done 9 Oct)*: every support reply (buyer, seller and rider chats) is signed **"Mak from {store} Support"** — the assigned name, or one picked automatically; admin is never shown. Names are set in the left menu **Support team**: 5 by default (Mak, Terry, Priya, Sam, Alex), admin can edit or add more; each chat can be assigned a name in its drawer.
- [ ] **Real support staff accounts (later):** let admin add support team members (their own sign-in, Support tab only — no money, settings or seller approvals), assign tickets to them, and see who answered what. The display names above would become their real names.
- [ ] **Rider cash limit tied to earnings? (to decide):** a rider may carry cash up to (a) only their unpaid earnings, or (b) the higher of the store's cash limit and their unpaid earnings (suggested), or another rule. Also: a seller's "maximum pay per delivery" instead of one fixed rate?
- [x] **Final settlement box for a leaving rider (admin):** *(done 8 Oct, in the rider panel)* pay earned − cash held = amount to pay (or to recover from them); settle 7 days after their last delivery (only delivery claims, like "never received" or "damaged in transit", involve the rider; product warranties and returns are the seller's, so pay never waits for them). A rider holding more cash than they're owed owes the difference; earned pay is never cancelled.
- [ ] **"Partner with {store}" and "Careers" footer pages, Temu style** (references: temu.com/ca/partner-with-temu.html and temu.com/ca/careers.html): a Partner page (sell with us, deliver with us, other partnerships, each with a call-to-action button) and a Careers page (open roles from the admin hiring menu, each with Apply). Both link to the forms: seller sign-up, rider application, and the role forms.
- [ ] **Careers / hiring menu in admin:** the Careers page (`#/p/careers`) has no Apply button today. Admin adds and removes **job roles** (name, description, requirements, location / store, open or closed); the Careers page lists open roles, each with an **Apply** button and form. Rider is the first role (its form is the rider application); other roles (packer, support, etc.) get a simpler form. Applications land in admin (and with the seller for their own store's rider roles).
- [x] **Rider moving to a new location** *(done 9 Oct)*: Rider app → **Moving to another area?** (new home address, at their own cost) → admin and their sellers are told; admin 🔔 "Riders asking to move" → rider panel **Approve / Decline** (reason). Approve needs no deliveries out and no cash held; the rider is unlinked from old stores (pay earned still due), their home is updated, and stores near the new home get them as **suggestions to invite** (they join only if they accept).
- [x] **Riders with no active store left** *(done 9 Oct)*: when a rider's last store stops deliveries they're told their details stay on file; Rider app shows "No store right now" with **Apply to a store near your new home** and **Delete my details** (only with no active store, pay settled and no cash held — documents and personal data removed). When a store near their home starts deliveries they're **suggested to the store** (seller: Local delivery & riders; admin: Riders), the store **invites** or says no thanks, and the rider **joins** or says no thanks in the Rider app (emailed) — linked only if both agree.
- [ ] **Rider money table (per store, per rider):** for each delivery, the delivery fee the buyer paid, cash collected (COD) and handed over, and what the rider is owed. Shows whether a rider is worth it (fees earned vs pay), a minimum payout before paying, and fixed pay vs per-delivery pay (fixed pay can save money for busy riders).
  - **COD a rider doesn't hand over** (e.g. leaves with the cash): for a seller's store the seller bears it; the table shows cash held by each rider so it's caught early, and a rider holding too much is paused (like NexTech riders today).
  - **A rider who leaves suddenly:** their unpaid amount and the cash they hold are settled from the table.
  - **When admin pays (NexTech riders or admin-hired riders):** monthly "pay due" reminders, with each rider's performance: on-time %, late or failed deliveries, refused offers and hours on shift.
  - NexTech riders already have some of this (rider ledger, base + per-mile pay, minimum payout, cash return to store); extend it to seller stores rather than building it twice.
- [ ] **Transfer a NexTech order to a nearby seller:** when a seller near the buyer stocks the same product, admin moves the order to that seller to save long-distance delivery (needs the seller's agreement, stock check, price and commission rules).
- [ ] **Live rider tracking for buyers** (rider name and live map). Status updates from the seller, rider or admin are enough for now.
- [ ] **Rider ↔ buyer chat** in the Rider app (today: the rider calls the buyer's phone).
- [ ] **Address availability:** buyers add days and times they're available at an address (e.g. "Mon–Fri after 6 pm"), shown to riders and sellers with the delivery instructions (orders already have delivery instructions).
- [x] A per-delivery list sellers can export to pay their riders *(done 9 Oct)*: Local delivery & riders → Rider money → **Download deliveries (CSV)** for the chosen month (date, order, rider, buyer area, fee the buyer paid, rider pay, cash collected, handed over).

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
