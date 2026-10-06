# Tasks Done Today

## Orders
- **Open orders in the admin top bar:** a red count of orders not delivered or cancelled yet; one click opens Orders filtered to them (also an **Open** filter on the Orders page). It also counts orders whose seller package is still on its way after NexTech's part arrived.
- **Courier orders complete on their own:** tracking is checked every 30 minutes and delivered parcels mark the order delivered (the admin "Sync tracking" button still works).
- **Long customer / seller names** wrap onto two lines instead of widening the Orders table.

## Bills
- **Only charges that apply:** no zero-value Handling or Tax lines, and no Delivery line for downloads.
- **Who sold what (like Amazon):** "Sold by" shows each seller's legal name and registered address, plus their GSTIN (India) when they have one. Missing optional details are simply left off — nothing blocks an order.
- **NexTech's own details:** new **Admin → Settings → Business & tax details** — legal name, registered address and GSTIN per country, printed on bills for NexTech's own products.

## Sellers
- **Request changes item by item (like Temu):** the admin ticks what the seller must fix (business type, tax ID, ID document, pickup address…), each with an optional note. The seller sees "2 items to update", red "!" on those steps and the fields outlined in red.
- **"You receive" on the price step:** sellers see what they get per unit after NexTech's commission (and TCS / TDS in India), with a tip to set one all-in price.
- **Compliance sent back** now says so clearly on the Bank account step.
- **Category requests reach the admin:** a new **"New categories sellers asked for"** section in the 🔔 bell; one click opens a new category with the name filled in. It clears once the category exists.

## Products
- **Can't be taken down while buyers are covered:** while past buyers are inside the return window or warranty, sellers can't deactivate, delete or ask to remove the product, and admin can't remove it — set stock to 0 instead.
- **Out of stock** shows in red on product cards and the product page.
- **Return conditions:** sellers and admin tick when returns are accepted (stopped working, arrived damaged, wrong item…) and when not (dropped, liquid damage, misuse…), plus a note. Buyers see a **Returns & warranty** box on the product page.
- **Warranty conditions required** whenever a warranty is chosen.
- **Return & warranty policy link** next to "Download bill" in Your orders, only for items with returns or a warranty.

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

## Buyers
- **Write a review** is now a clear button on delivered items in Your orders.

## Fixed
- All backend tests pass again (405): two old migrations didn't run on the test database, new stores now get a country, and six tests were updated for features that had changed.
