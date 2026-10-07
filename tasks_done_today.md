# Tasks Done Today — 7 Oct 2026

(The 6 Oct list that was here is in git history: `git show 8df2c46:tasks_done_today.md`.)

## Seller payouts
- **Bank or PayPal, chosen per country:** admin turns each payout method on or off per country (at least one stays on). Sellers pick theirs in onboarding Task 3 "Set up how you get paid" and in Finances, seeing only the methods offered in their country.
- **Payout currency:** each method has the currency it pays in per country (e.g. PayPal pays Indian sellers in USD). Sellers choose from the currencies admin allows and confirm that their account can receive a foreign currency.
- **Withdrawal fee** (the name used everywhere): fixed amount and/or % per method and country, plus an optional minimum payout per method. Sellers see the fee and what they'll receive before requesting. The ledger shows a "Withdrawal fee" line.
- **Request payout:** always-visible button, a limits box (minimum, maximum per payout, daily limit, bank-rules note editable by admin), and a confirmation showing amount, method, fee and amount received. "Available" shows $0 until it reaches the minimum.
- **Stripe payouts** *(not committed yet)*: a third method. The seller sets up a Stripe account on Stripe's own pages and comes back to Seller Center. Admin's **Pay** sends the money from the store's Stripe balance. The seller pays the withdrawal fee. Stripe is off until admin switches it on, and is greyed out with the reason where it can't work: India (Stripe won't open Indian accounts for a US Stripe account) or no Stripe keys.

## Admin → Secure access (password) *(not committed yet)*
- **Three submenus after unlocking:** **Withdrawal fees** (moved from Store settings, with the payout note), **Payouts** and **Keys & account**.
- **Payouts table:** every seller with money to pay or a payout request, showing their payment method and details, available and held amounts, request, amount to pay, fee and what they receive, status (Ready / below minimum / why blocked), with **Pay** / **Decline**. It also shows totals, today's remaining cap and the Stripe balance. The bell and the seller page now link here.
- **Locks itself:** opening another menu, or a minute without activity, locks Secure access (on the server too). Changing withdrawal fees and paying sellers needs the unlock.

## Products & categories
- **Product details per category, set by admin** *(not committed yet)*: Categories → edit a category → "Product details sellers fill in".
  - Each detail is a text box, number box (with unit), dropdown, checkboxes or a yes/no checkbox, with a label, an optional "required" tick and an order.
  - Subcategories use their parent's list until given their own. The 13 built-in lists were copied in so they can be edited.
- **Common product details** *(not committed yet)*: Categories → Common product details. Every category, including new ones, asks for them; admin can add, rename or remove them.
- **Locked when used:** a detail or a dropdown choice that products use shows in red "🔒 used by N products". It can be renamed but not removed or changed in kind. Model number and Warranty are always kept (follow-ups use them).
- **Out of stock:** a seller product with variations is sold only as its variants. The product page shows a red **Out of stock** button. Seller Center shows "⚠ Out of stock" with a count, a red banner and a Products badge.
- **Update stock** button in Manage products (applies at once, no review). SKU table has Variation and highlighted Quantity in stock columns; the seller's own code is optional. Digital downloads can be limited copies.
- **Edits to live products** (e.g. adding an FAQ) wait for approval while the product stays on sale. Admins are **emailed** when products or edits are sent for review. Admin Products says how many are waiting in other countries (hidden by the top-bar country).
- **Refurbished** tag (optional) on products.
- **Ads as a product kind:** Not demo / Demo / Ad with a partner link. Ads show with an "Ad" label, open the partner site and count clicks, and appear only on the home Recommended list.
- **Trademark change requests** with a warranty check and admin advice.

## Deals & home page
- **Deals filled automatically:** Lightning deals (seller- or admin-set % off, start time, units, countdown, optional auto-restart, at most 40% of live products), Unbeatable (biggest discounts, cut-off worked out from the catalogue, spread across categories) and Exclusive (cheapest per country). No product in two sections. Demo store has auto-renewing ~30% lightning deals.
- **Personal Recommended:** recently viewed categories first.

## Sellers
- **Signed policies:** Seller Center footer pages can require reading and signing (before listing or selling abroad), with name, date, IP and version kept.
- **Selling abroad:** export ID plus signed declaration, approved on signing. Per-product "sell abroad" choice with optional extra shipping per item. Seller money is held through the warranty, and there's an optional customs/paperwork fee line. Plus an International Delivery label + customs sheet.
- **Sign-up:** proof of registered address (admin checks it, then the address is locked with history); save and finish later.
- **Own delivery (local):** radius, fee, days and a buyer delivery code. "Other courier" takes a name and tracking link. Hourly reminders, with admin alerted on missed ship-by dates.
- **Store decoration:** images by upload or link; size and KB limits per image, with automatic crop and compression and links to free tools. Phone, email, social and outside links aren't allowed, with a warning that the store can be paused. "View store page" link. The shop page has no breadcrumb and has a share icon.
- **Seller Center button** on the storefront for signed-in sellers only.

## Admin screens & settings
- **Left menu order:** Dashboard, Orders, Categories, Products, Stores / hubs, Sellers, Customers, Emails, Reviews, Riders, Shipping, Store settings, Secure access.
- **Shipping menu** with submenus: Seller shipping & labels, Sellers' own delivery, Selling abroad, Order update reminders, Seller cash on delivery, NexTech delivery. Checkout charges follow the country chosen in the top bar.
- **Pages menu:** All pages, All blogs, Main help pages, Main footer pages, Seller policies & rules. **New page** asks which menu it goes under. Footer settings moved to Store settings.
- **Store settings:** Setup checklist at the top with Change / Set up buttons; Selling, House shop and Deals next. Settings hide what's unused and require what's needed (checked on the server).
- **House shop** for the store owner (seller tools, no commission or review). NexTech delivery: own riders within the store radius, or a courier for everything.
- **Category requests** from sellers can be declined (removed from the bell).
- **Store name everywhere,** including the mobile app, emails and label headers (`{store}` placeholder).

## Fixed / checks
- Tests: **431, all passing** (new: Stripe payouts, payouts table and Secure access, category details).
- ESLint can't run while drive C: is full; changed files were checked with the JS parser instead.
