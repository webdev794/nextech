# Tasks Done Today — 12 Oct 2026

The plan and what's still open are in `Possible_tasks.md`.

## Careers and Partner pages
- **Partner with us** page (footer): Sell with us, Deliver with us and Other partnerships, each with a button.
- **Careers** page lists open jobs with **Apply**. Rider jobs go to the rider application; other jobs use a short form (name, email, phone, CV link, message).
- **Admin → Careers** (new left menu) has two parts:
  - **Open roles:** add a role (the form opens only on click). Tick one or more stores; "What you need" is one item per line. The default rider job lists age, ID, own vehicle with licence and papers, smartphone, experience, education, health, the local area and store hours.
  - **Applications:** New / Reviewed / Hired / Declined. New applications also show in the 🔔.
- A job at a seller's store: the seller is told and sees the applications in Local delivery & riders.
- Clicking **Careers** (or **Secure access**) again hides its submenu.

## Local delivery
- **Turning off: 3 days to talk it over.** Riders are emailed at once. The seller can **withdraw** the request within 3 days. Admin can't approve before then ("Approve from …"), and the 🔔 entry changes to "ready to approve" after.
- **Admin seller page → Local delivery:** status, riders and hiring, with Turn on / Turn off / Approve / Keep on.
  - Turning it on sends the seller the hiring steps in chat, with the "Work with us — deliver" footer link and the link to share.
  - If local delivery is **hidden for all sellers**, the page says so and links to Shipping → Sellers' local delivery; turning it on is refused.
- **Seller pages:**
  - Links go straight to Shipping settings → Local delivery.
  - When it's locked, a clear underlined message explains why there's no Turn on button, with a **Message NexTech to turn it back on** button.
  - Darker borders between sections.
- **Seller "What's this about?"** has a new topic: **Local delivery — turn it back on, riders, hiring**.

## Buyers and riders
- **When you're usually home:** buyers add it to an address; riders and sellers see it.
- **Rider's position and phone for the buyer** while out for delivery: "📍 Ravi is about 2.3 km away · See on map" and "📞 Ravi: +91 …". The buyer is also emailed who is delivering and their number.
- **Sellers' riders can message the buyer** while delivering (NexTech riders already could).

## Rider day offs need an answer
- A rider's day-off request now **waits for the seller** (admin for NexTech's own stores) to **Accept** or **Decline**.
- The seller sees a 📅 banner and a "Day-off requests — please answer" list in Local delivery & riders until they answer; admin sees it in the 🔔.
- The rider sees "waiting / accepted / declined" and is told the decision. Only accepted days count as days off.
- **Once answered, no Take back:** an accepted day off still ahead has **Change date** instead; the store confirms the new date or declines it (the day off then stays as it was). Only a request not answered yet can be taken back.
- **Declined requests are removed** once the rider is told.
- **Store closed days:** weekends a store doesn't work are simply days off — not listed or emailed. The Rider app shows only coming holidays ("📅 cs holidays: Diwali (8 Nov)"). A rider can't ask for a day off on a day their stores are closed.
- Day-off requests made before this change for upcoming dates now wait for an answer too (only past days count as accepted).

## Rider app
- **Welcome, {rider's name}** at the top of the Rider app.
- **Deliveries all-time / this week** now also count deliveries for sellers' stores, not just NexTech orders.

## Rider pay
- **Weekly pay:** Riders → rider → Pay now has Per delivery / Monthly / **Weekly** (paid every Monday for the last week, same target, bonus and top-up pool).
- **Missed days:** "less per missed day" on monthly / weekly pay — absent without asking, or a day off beyond the allowance.

## Countries
- Settings → **Countries — show / hide**: hiding one first lists what's still open there (orders, sellers, money owed to sellers).
- New sellers can't sign up from a hidden country; its existing sellers see "Selling is paused in your country" and can still ship open orders and get paid.

## Live updates
- Cash handover / settle / pause changes now show on open pages at once.

## Offers to riders
- **Bug fixed:** "Store deliveries" was hidden for a rider with no deliveries, so offered orders (e.g. #85 for rider2) never showed. They now show under Store deliveries → Open to take.
- An orange **Delivery to take** banner at the top of the Rider app (with **See it**) until it's taken; it rings once per new offer, even if the app is opened later.
- Riders' messages never mention other riders ("is waiting for you", "Please pick it up from cs").

## Rider moving away
- The request goes to the rider's sellers first (Local delivery & riders → **Let them go / Keep them**, and the 📅 banner until answered). Keep them = the request ends and the rider is told. Once every seller lets them go (or the rider works only for your own stores), admin approves or declines as before.

## Rider app wording
- When a rider takes an order: "Order #85 is assigned to you for delivery — collect it from cs." The seller is told who will collect it, to check the items at handover, and that handing over is at their own risk.
- "Store deliveries (1)" counts orders waiting to be taken; they say "Please pick it up from cs."
- No "Nothing assigned to you" line — My deliveries shows only when there is something in it.

## Admin chats
- The name at the top of a chat window is a link that opens that seller, rider or buyer's details behind the chat. The Support drawer has "Open seller →".

## Bugs from testing (all fixed)
- **Pending orders:** a clear reason ("the buyer can still cancel or change the address for free until 12:01"); the seller can **Mark packed** meanwhile.
- **Rider app tabs:** **Dashboard** (unchanged: shift, stats, earnings, deliveries) and **My account** (sellers, days off, documents, moving, emails — in boxes). Seller chats are also in the chat button (bottom right); offer pop-ups show on every tab.
- **"Offer to my riders":** every active rider of the seller is emailed, and the Rider app rings with a notice.
- **"Work with us — deliver" footer link:** uses the country being browsed (it never showed locally). /rider/apply tells existing riders "You're already a rider".
- **Seller Messages red dot:** no longer shows just because a chat was ended.
- **Unpaid orders:** the buyer is reminded to pay about 1 hour and 24 hours after ordering.
- **Digital orders** (e.g. #84): labelled "Digital download" for admin and seller; no rider can be assigned.
- **Unshipped orders:** a banner at the top of Seller Center that can't be closed, until they're shipped. "Orders / Ship orders" blink with the count.
- **Same order twice:** the buyer gets an automatic message from the seller's side; their reply reaches the seller (email + customer chats).
- **Rider application form:** Submit always works and lists what's missing; each upload shows its own error with × to remove; the same file can't be used twice; payment details moved to after approval (the approval email says where to add them).
- **Links in messages** use the website's address setting (`FRONTEND_URL` / `APP_URL`), e.g. …/rider/apply.

## Fixes
- Admin "Turn off" for a seller's store crashed after the 3-day change; fixed.
- A message edit briefly broke a backend file; caught by the tests and fixed.
- An old helper script was re-run by mistake and added a duplicate seller CSV download; removed the same day.
- rider@example.com's "1 delivery all-time" is correct: only order #27 was ever delivered by them (no data was deleted).

## For the server
- New migrations **2026_10_12_000102 – 000106** (careers, job role stores, address availability, day-off answers, day-off date changes) on top of 9 Oct's 000087–000101: run `php artisan migrate`.
- Scheduled jobs (payment reminders, store days off, rider pay) need the scheduler: locally keep `php artisan schedule:work` running; on the server the cron runs `schedule:run` every minute.
- Optional `.env` setting **FRONTEND_URL** = the shop's address (only needed if it differs from APP_URL; locally it's http://127.0.0.1:5173).

## Checks
- Tests: **489, all passing**. ESLint and the production build pass.
