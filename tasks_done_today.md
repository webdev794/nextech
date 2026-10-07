# Tasks Done Today

## Orders
- **Open orders in the admin top bar:** a red count of orders not delivered or cancelled yet; one click opens Orders filtered to them (also an **Open** filter on the Orders page). It also counts orders whose seller package is still on its way after NexTech's part arrived.
- **Courier orders complete on their own:** tracking is checked every 30 minutes and delivered parcels mark the order delivered (the admin "Sync tracking" button still works).
- **Long customer / seller names** wrap onto two lines instead of widening the Orders table.
- **Cancelling needs a reason:** admin picks why (out of stock, customer asked, can't deliver, suspected fraud, duplicate, other). A paid order then shows as **Refund due** (red) — new **Refund due** filter on Orders and **Refunds to issue** in the 🔔 bell — until refunded. Buyers see "Cancelled by NexTech — reason · Refund on its way".

## Bills
- **Only charges that apply:** no zero-value Handling or Tax lines, and no Delivery line for downloads.
- **Who sold what (like Amazon):** "Sold by" shows each seller's legal name and registered address, plus their GSTIN (India) when they have one. Missing optional details are simply left off — nothing blocks an order.
- **NexTech's own details:** new **Admin → Settings → Business & tax details** — legal name, registered address and GSTIN per country, printed on bills for NexTech's own products.
- **What was bought is frozen on the order:** each order line keeps the product as sold (name, details, warranty, return terms). **Return & warranty policy** in Your orders shows these terms, not the live listing.

## Sellers
- **Request changes item by item (like Temu):** the admin ticks what the seller must fix (business type, tax ID, ID document, pickup address…), each with an optional note. The seller sees "2 items to update", red "!" on those steps and the fields outlined in red.
- **"You receive" on the price step:** sellers see what they get per unit after NexTech's commission (and TCS / TDS in India), with a tip to set one all-in price.
- **Compliance sent back** now says so clearly on the Bank account step.
- **Category requests reach the admin:** a new **"New categories sellers asked for"** section in the 🔔 bell; one click opens a new category with the name filled in. It clears once the category exists.
- **Sellers button in the admin top bar:** seller notifications kept apart from the 🔔 bell — onboarding to review (tax, compliance, bank), products waiting for review, removal requests, new applications, category requests, payout requests, labels to upload, cash kept by sellers and sellers owing NexTech. Admins are also **emailed** when a seller submits tax, compliance or bank details.
- **Sellers hear about every decision:** approved / rejected / paused application, tax / compliance / bank approved or sent back, product approved or rejected, removal request, payout sent or declined — as a message in Seller Center and an email.
- **Cash on delivery for sellers, controlled by admin:** each seller has its own switch (Sellers → View), off until allowed; an overall setting too. It pauses itself when a seller owes NexTech more than a limit (default $100 / ₹5,000); what they owe comes out of their next orders' earnings. Admins are told each time a seller keeps the cash.
- **Required fields marked** with a red * in Tax information, Compliance and Bank account; Bank's **Continue** stays off until everything is filled, with a "Still needed" line.
- **HSN explained** in Tax information (it's only a default — each product has its own HSN), with more codes (batteries, cameras, gadgets, software, digital downloads, Other).
- **Messages:** NexTech support is the first tab (where "New message" is).
- **New-order banner** says how it reaches the buyer: you ship it, digital download (automatic) or pickup — no more "NexTech collects it" for downloads.
- **Finances:** activity is a table, coloured by where the money is — dark green paid, green available, brown held for returns, grey cancelled out by a refund, red deducted.
- **Shipping labels** say "From — if undelivered, please return to" (the seller's ship-from address).

## Products
- **Can't be taken down while buyers are covered:** while past buyers are inside the return window or warranty, sellers can't deactivate, delete or ask to remove the product, and admin can't remove it — set stock to 0 instead.
- **Out of stock** shows in red on product cards and the product page.
- **Return conditions:** sellers and admin tick when returns are accepted (stopped working, arrived damaged, wrong item…) and when not (dropped, liquid damage, misuse…), plus a note. Buyers see a **Returns & warranty** box on the product page.
- **Warranty conditions required** whenever a warranty is chosen.
- **Return & warranty policy link** next to "Download bill" in Your orders, only for items with returns or a warranty.
- **A sold product keeps its identity:** once sold, its type, name, category, brand and model number can't change (like Amazon — a different product is a new listing); price, stock, photos and description still can.
- **Categories as a tree, physical or digital:** parent → child → grandchild (up to 3 levels). Downloadable is now Digital with Games (Arcade, Card & board, Action & adventure, Puzzle, Strategy, Sports & racing), Software & apps, E-books & PDFs, Music & audio, Video & courses, Templates & design. Admin sets the parent and type (+ Sub button); sellers only see digital categories for downloads and physical ones for shipped goods; a category page shows its subcategories' products and chips.
- **Bulk import products** (renamed): the Excel opens straight on the column headings — instructions are on the page (Step 02). No SKU column (SKUs are made automatically); **Product group code** groups variants; **Variant image URL** per row; **Physical / Digital downloads** templates (digital: download fields, price and download link — no battery, weight or shipping).

## Digital downloads
- **Upload limits:** **Admin → Settings → Digital downloads** — default 50 MB per file and 200 MB per product (was 4 GB), so the hosting isn't filled with big ZIPs. Bigger files go up as a download link.
- **Download links checked:** Google Drive / Dropbox share links are turned into direct downloads and tested; broken links or links that ask buyers to sign in are refused. Sellers see ✓ / ⚠ / ✕ with **Re-check**; all links are re-checked every night. Buyers only get the link after paying.
- **Admin → Products → Files:** see a digital product's files, links (status, re-check), download counts, license keys and settings, and download uploaded files to inspect them.
- **PDF guides** download with their own name.

## Admin screens
- **Products and Categories submenus (like WordPress):** All products / + Add new product, All categories / + Add new category. While editing, only the form shows, with **← All**. Clicking the menu again folds the submenu.
- **Product form in boxes:** Product details, Images, Video, Description, Variants, Store stock, then Return conditions at the bottom. **Video upload** button added.
- **Easier to read, in every admin form:** headings and field names bold (12px), help text small and italic.
- **Page URL:** set a custom URL when creating a page; it can't be changed afterwards (create a new page for a new URL).
- **Store name everywhere:** renaming the store in Store settings now renames it across the storefront, admin, Seller Center, emails and messages (and the email sender name).
- **Clearer settings:** Stores are "Stores / hubs" (warehouses, linked to delivery); Business & tax details is the registered office for bills; notes say Delivery charges are paid by customers and Rider pay is paid to riders.
- **Admin seller page** shows each shop's real shipping method (it always said "NexTech collects & delivers").

## Buyers
- **Write a review** is now a clear button on delivered items in Your orders.
- **Categories menu** stays on screen at every width, and follows the category tree.
- **Deals pages** show only categories that have products in that deal (no empty Downloadable or Car Electronics).

## Fixed
- All backend tests pass again (405): two old migrations didn't run on the test database, new stores now get a country, and six tests were updated for features that had changed.
- The admin 🔔 bell and Sellers button loaded 10 seconds late after opening the admin.
- Tests now: 415, all passing.
