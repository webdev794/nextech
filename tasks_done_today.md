# Tasks Done Today — 8 Oct 2026

Sellers' own local delivery with their own riders, built step by step (plan and what's still to do: `Possible_tasks.md`, section 2).

## Stores / hubs
- **Every seller has a store** (from their ship-from or registered address), shown once the seller is approved. Each store is tagged **Own store** or **Seller**, with a filter, a riders count and how long it has been listed. The seller's name opens a **card**: owner, email, phone, business type, status, "selling since" (admin only), last message.
- **Local delivery (riders) per store:** own stores show **Riders on / Courier only** (new stores start off; needs a rider linked). Seller stores show **On / Off / Seller asks to turn off / Off · locked**, with Turn on, Turn off (reason), Approve, Keep on.
- **NexTech's own checkout, delivery fee, routing, stock and rider pay never use sellers' stores.**
- **Hiring** switch per store.

## Seller local delivery
- The seller's **Own delivery (local)** settings (address, map point — latitude/longitude can be entered — radius up to 10 km by default, fee, days) create or update their store.
- **Turning it off:** a confirmation popup; with riders linked it's a request to admin (riders told only after approval, they stay linked for pay); without riders it's simply off and can be turned on again. Locked turn-offs can only be turned back on by admin.
- **"Before you turn it on"** box: deliver yourself until riders are hired, delivery fees, cash on delivery is the seller's to collect daily, products and cash riders keep are the seller's risk.
- **Seller Center → My account → Local delivery:** store status, step-by-step hiring guide (Quikr, OLX, shop poster, people you trust; riders bring their own vehicle), hiring switch, applications, riders, cash, pay per delivery and a **rider money** table.

## Hiring riders
- **"Work with us — deliver"** footer link for signed-in buyers when a store in their country is hiring.
- **Application form:** email, date of birth (minimum age per country, 18 by default), education, past work, experience, health, stores they'd work for in priority order, ID proof, photo, licence + vehicle RC for motor vehicles, own-vehicle and consent ticks (removal terms, 30 days' notice, pay per delivery, same-day cash handover).
- **Seller accepts → admin approves** (basic requirements only); minimum rules enforced (nobody under the minimum age). Applications stay in the admin 🔔 until decided.
- **Rider profile** filled from the application (home base, passport photo, all details); admin Riders list shows the photo; the rider panel shows the full profile.
- **Riders work in one country only.** Admin removing a rider from a seller's store needs a reason (the seller is told); a seller removing one needs a reason (admin is told) and no open orders.
- **Notice period:** "Give 30 days' notice" in the Rider app; admin told when given and on the last day, until marked processed.

## Deliveries by sellers' riders
- **Ship orders:** "Who delivers it" — a rider (on shift first) or "I'll deliver it myself"; change the rider later. High-value local orders (over the cash-on-delivery maximum) are flagged: send by courier or deliver yourself.
- **Auto — nearest free rider** when sending an order out (on shift, not paused, nearest the store, fewest deliveries out).
- **Rider app → Store deliveries:** pickup and buyer details, cash to collect, delivered with the buyer's code.
- Buyers see **"Sam is delivering your order"** (rider's first name, or the seller's shop name).
- Admin can only watch a seller's own-delivery steps (no status changes; cancel/refund still possible).

## Cash on delivery and riders' money
- **Cash on delivery only up to ₹5,000 / $200 per order** by default (admin can raise it at their own risk; a seller's limit lowers it for their orders).
- **Cash limits:** sellers set the most one rider may hold; the store sets one for its own riders. Over a limit, or paused by any seller for cash, a rider is **paused in every store** until it's handed over.
- **Seller cash handling:** notifications when a rider collects cash, evening reminders, next-morning pause (a few days' grace if their earnings cover it), **Cash received**, **Later** (seller's own risk). The rider can say **"I handed over the cash"**; only the seller confirms it or marks it **Not received** (rider paused; covered only by their earnings).
- **Pay per delivery** set by the seller: the store pays the rider and charges the seller ("Rider pay"). At month end, cash a rider kept goes to the seller from their earnings ("Cash a rider kept").
- **Money table** per month (admin: all riders + balance; seller: their store) and a **settlement box** per rider (earned − cash held, settle 7 days after their last delivery).
- **Adjust a balance** (Secure access → Payouts): admin credit or charge on a seller's or rider's ledger, with a reason they see (also for fines).
- End of day: the store's own riders holding cash, and admin, are told.

## Admin screens & other
- **Riders filters:** store, country, status (active / not active / leaving), right now (on shift…).
- Fixed: the rider application file had two imports on one line (separated only by a bare carriage return).

## Checks
- Tests: **443, all passing** (new: seller stores, rider hiring, own-delivery riders, cash rules, rider money, adjustments).
- ESLint passes (the pagefile was moved to D:, so linting no longer runs out of memory); production build passes.
