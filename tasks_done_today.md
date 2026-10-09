# Tasks Done Today — 9 Oct 2026

Sellers' local delivery, riders and support. The plan and what's still open are in `Possible_tasks.md`.

## Deliveries and timers
- **Needs courier:** NexTech local orders no rider takes in time, or outside every store's area with no courier account → admin 🔔 + Orders filter "Needs courier".
- **One rider timer per order** (default 5 hours): every on-shift rider at the store sees it with the same deadline (riders who come online later too); the first to take it gets it. When the time runs out, the store is told; nobody online at all → told at once.
- Breaks and clock-out move pending offers on. Long offers have "Decide later".
- **Sellers' riders:** "Offer to all my riders (first to take it)", with the seller's own hours; "Nobody took it" alert.
- **Delivery zones:** "You deliver yourself within X km"; nearer orders default to the seller, farther ones to riders. Checkout saves each buyer's distance.
- **Arrival deadlines:** sellers reminded when an order is due today or tomorrow; admin told when one is late.
- Admin caps on the days sellers may promise (local default 7, courier default 30).

## Holidays and working hours
- Buyers see which seller days off the delivery dates skip; riders see their stores' days off.
- **Admin Shipping → Holidays:** holidays added per country or per store; sellers ask for days off (add for this store / the whole country / decline).
- Store closed tomorrow → riders and admin told; riders with no other open store get the day off automatically.
- **Riders' working hours** set per store (seller / admin): clock-in reminders; the store is told who is missing or who leaves early.
- **Riders' days off:** allowance per month, asked for a day ahead in the Rider app. Missed days are recorded (may affect pay); breaks over 1 hour → reminder.

## Riders
- Application moved to **/rider/apply**; /rider is the rider login and console again.
- **Final step: sign to join.** The rider types their name and town after reading the store's terms (manners, on time, days off, own fuel and vehicle costs, bonus for great work, the store's decision is final). The signed text is saved.
- **Payout method:** chosen on the application from the methods allowed in that country; required before clocking in. On the 25th, riders still missing one are reminded, and so are their sellers.
- **Documents:** a joining letter (joined on, pay, hours) instead of the full terms.
- **No store left:** details stay on file. A nearby store can **invite** them, and they **join** only if both agree. Or they can delete their details.
- **Moving to a new area:** the rider asks, admin approves or declines, and stores near the new home can invite them.
- **Monthly pay** (NexTech riders): amount, target, bonus per extra delivery and a cap. Paid on the 1st, with a top-up pool for riders who fell short.
- **Bonuses:** suggested to admin on the 25th for excellent months; sellers can give their own riders a bonus from their earnings.
- **Riders rate the store and the buyer** (private, admin only); the seller's admin card shows "Riders say".
- **Rider ↔ seller chat**, with support tickets.

## Sellers
- "Own delivery" renamed **Local delivery**, in plain words: an ON / OFF status, a green "Turn on" button and a red "Turn off" button. The rules are always shown.
- **Cash on delivery "Only for local deliveries"** (new admin choice); clearer message when it's switched off.
- Rider money **CSV download** (one row per delivery).
- Shipping days wording: "I also ship on Saturdays / Sundays".

## Support and admin
- **Support names:** every support reply (buyer, seller, rider) shows as "Mak from {store} Support", never admin. Admin edits the names in the new left menu **Support team** (5 default names).
- **Tickets** on rider–seller chats: shown in the admin 🔔 (no email); a support name is assigned; reply within 1–2 working days; close as "resolved" or "can't take this up now".
- **Performance watch** (daily): poorly rated products, sellers, riders and buyers; late shipping; missed days → admin 🔔 "Performance warnings".
- **Emails:** routine reminders can be switched off by riders, sellers and admins; important emails always go.

## Live updates
- Pages refresh their data by themselves when something changes (a tiny `live.json` marker, light on the server). Tabs in the same browser update instantly.
- Chats and notifications stay fast.
- Fixed: the red "Could not load…" messages on the admin dashboard (a bug in the new live-update code).

## Other
- MySQL: two corrupted system tables repaired (a backup was made first).
- cs local delivery area restored to 10 km after a test changed it.
- New test rider: **rider2@example.com** (password `password`), linked to seller cs (India); listed in `logins.md`.

## For the server
- Migrations **2026_10_09_000087 – 000101** (15) to run: `php artisan migrate`.
- `backend/public` must be writable by PHP (for `live.json`).
- The scheduler must be running (new daily, hourly and monthly jobs).

## Checks
- Tests: **472, all passing**. ESLint passes; production build passes.
