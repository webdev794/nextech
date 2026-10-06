# v9 changes — what changed and what to double-check

Everything on branch `v9` on top of `v8` (12 commits, `b36852a`…`e0a545d`).
Use this file to merge `v9` into your local work and check that nothing local breaks.

## 1. Apply the database changes locally

Five new migrations. Each one skips itself if the column or table already exists,
so it is safe on a database imported from `backend/web_deploy/edp.sql`.

| Migration | Change |
|---|---|
| `2026_10_05_000045_add_guides_to_products` | `products.guides` JSON, nullable (after `info_sections`) |
| `2026_10_05_000046_add_commission_rate_to_shops` | `shops.commission_rate_bps` unsigned smallint, nullable (after `market`) |
| `2026_10_05_000047_create_sales_tax_rates` | new table `sales_tax_rates` (`zip_code` PK varchar 5, `rate_bps`, `source`, `fetched_at`, timestamps) |
| `2026_10_05_000048_add_deactivated_by_to_products` | `products.deactivated_by` varchar 10, nullable (after `is_active`) |
| `2026_10_05_000049_add_deletion_request_to_products` | `products.deletion_requested_at` timestamp, `products.deletion_reason` varchar 500, `products.archived_at` timestamp — all nullable |

Every new column is nullable with no default, so existing rows behave exactly as before.

```sh
cd backend
"D:/xampp/php84/php.exe" artisan migrate:status   # check for clashes first (see section 4)
"D:/xampp/php84/php.exe" artisan migrate
```

`edp.sql` already has these columns, the table and migration rows 137–141, so a fresh import needs no migrate.

## 2. Features, with the files they touch

### Downloads
- After paying for an order that has **only** digital items, the buyer goes straight to `#/account/downloads`. Orders with any physical item still show the order popup. Files: `web/src/Storefront.jsx` (`isDigitalOrder`, `openDownloads`, and the gift-card, `finalizePayment` and `resumePayment` paths).
- Paid digital items can be reviewed. Undelivered physical items show "You can review it once it's delivered". Files: `backend/app/Support/Reviews.php` (`received()`), `web/src/Reviews.jsx`.

### Product guides and documents (PDF manuals)
- Sellers upload PDFs (up to 10, 20 MB each, with a name and language) in Add product, step 01. Buyers see one link on the product page that opens a popup with a dropdown, preview and download.
- Backend files:
  - `MediaController::storeSellerProductDocument`, route `POST /seller/product-document`, stored in `storage/app/public/product-documents`
  - `Personalization::guideRules()`, which only accepts URLs under `/api/media/file/product-documents/…pdf`
  - `Product` (`guides` added to fillable and array casts)
- Frontend files: `web/src/ProductDocuments.jsx` + `.css`, `SellerProductWizard.jsx` (form key `guides`), `Storefront.jsx`.
- The key is called `guides`, not `documents`, because the wizard already uses `documents` for compliance files.

### Seller commission
- **New-seller rate:** a separate rate for a seller's first N days after approval (default 90), set in Secure access. Settings keys: `new_seller_commission_rate_bps` (null = same as everyone), `new_seller_days`.
- **Keeping existing sellers' rate:** when admin changes a commission rate, existing shops keep their old rate (saved in `shops.commission_rate_bps`) unless "Apply a rate change to existing sellers too" is ticked. Request key: `commission_apply_existing`.
- **Moving chosen sellers:** a Secure access list of sellers on an older rate, with select / select all, moves chosen sellers to the current rate.
  - Routes: `GET /admin/secure-access/kept-rates`, `POST /admin/secure-access/kept-rates/release`
- **Backend files:**
  - `SellerLedger::shopRate()`, which now prices both the sale credit and the refund debit. Order of precedence: new-seller window, then the shop's kept rate, then the market rate. It's judged at the order's date, so a refund matches its sale.
  - `SellerLedger::marketRates()`, `SellerLedger::lockShopRates()`
  - `AdminSettingController` (`update()`, `keptRates()`, `releaseKeptRates()`)
  - `Shop` (fillable)
- **Frontend files:** `web/src/AdminKeptRates.jsx`, `Admin.jsx` (Secure access form and Charges tick box), `AdminMarkets.jsx` (per-country tick box).

### Bill PDF
- Prints amounts in the order's currency. Tax-inclusive markets (India) show "Includes GST ₹…" instead of "Tax $0.00". File: `backend/resources/views/receipts/order.blade.php`.

### US sales tax
- Tax is charged by where the order goes: ZIP lookup → state table → default rate.
  - The state table's built-in rates live in `config/sales_tax.php`. Admin can edit them; a blank state uses the default rate.
  - The ZIP lookup is optional (API Ninjas). Its key is in Secure access, and results are cached in `sales_tax_rates` for 30 days. A failed lookup falls back to the state table.
  - "Fetch automatically" fills the state table: route `POST /admin/settings/sales-tax/fetch`.
- **Settings keys:** `sales_tax_mode` (`state` | `flat`), `sales_tax_states`, `sales_tax_api_key`.
  - "Tax rate" in Charges is now labelled "Default tax rate" (same `tax_rate_bps`).
- **Backend files:**
  - `app/Support/SalesTax.php`, `app/Models/SalesTaxRate.php`, `config/sales_tax.php`
  - `CheckoutController` (tax line)
  - `ShippingQuoteController`: accepts `postal_code` and returns `tax_rate_bps` for the cart estimate
  - `AdminSettingController`
- **Frontend files:** `web/src/AdminSalesTax.jsx`, `Admin.jsx`, `Storefront.jsx` (cart estimate uses `sellerQuote.tax_rate_bps`).
- **Tests:** new `tests/Feature/SalesTaxTest.php`. `phpunit.xml` sets `SALES_TAX_MODE=flat` so the existing tests keep their 8.87% expectations.

### Admin screens
- Wide tables (`.admin-table-wrap`, `.sc-table-wrap`) are capped to the screen height with a sticky header, so the sideways scrollbar stays in reach. Files: `Admin.css`, `Seller.css`.
- The seller details page has an always-visible **Tax information** card (GSTIN/PAN, enrolment, tax code, certificate). The list button reads "View", or "Review" when something is waiting for review. File: `Admin.jsx`.

### Seller products: deactivate, relist, removal
- **Deactivate / Relist:** works on live, pending and rejected products, with no review. Route: `POST /seller/products/{id}/active`.
  - A product hidden by admin (`deactivated_by = 'admin'`) can't be relisted by the seller.
  - Admin turning a product off or on through product edit sets or clears `deactivated_by`.
- **Removal request:** if Delete fails because the product is on orders, the API now returns `can_request_deletion: true` and the seller can request removal. Route: `POST /seller/products/{id}/request-deletion`. The product is hidden at once.
- **Admin decision:** Products → "Removal requested" filter, then **Remove** or **Keep** (`POST /admin/products/{id}/deletion`).
  - Remove deletes the product, or archives it (`archived_at`) when it has orders.
  - The row shows "Buyers covered until …", the latest order plus the longer of the return window and the product's Warranty detail (`ProductCatalog::supportUntil()`, `warrantyMonths()`).
- **Buyer page:** `GET /products/{slug}` now returns approved products that the **seller** hid, or that were archived, with `no_longer_sold: true` and `support_until`, instead of a 404. They can't be bought (`Purchasable` still requires `is_active`). Products hidden by admin still return 404.
- **Files:** `SellerProductController` (`setActive`, `requestDeletion`, `index`, `update`, `destroy`), `AdminProductController` (`index`, `statusCounts`, `byStatus`, `update`, `decideDeletion`), `CatalogController::product`, `ProductCatalog`, `Product`, `Seller.jsx`, `Admin.jsx`, `Storefront.jsx`, `Storefront.css`.

## 3. Behaviour changes that could affect existing features

Check these against your local work:

1. **US tax amounts change.** The default `sales_tax_mode` is `state`, so US orders now use the buyer's state rate instead of a flat 8.87%. To keep the old behaviour, set `SALES_TAX_MODE=flat` in `.env` or choose "One rate everywhere" in admin.
2. **A seller's product edit no longer changes `is_active`.** `SellerProductController::update` drops it; going on or off sale only happens through `setActive`. If local code sends `is_active` from the seller wizard, it is now ignored.
3. **Archived products are hidden** from the seller's product list, the admin product list and the admin status counts (`whereNull('archived_at')`).
4. **The public product endpoint no longer 404s** for seller-hidden or archived approved products. If local code relies on that 404 (sitemaps, SEO, links), check `no_longer_sold`.
5. **Commission:** the ledger now calls `SellerLedger::shopRate()` instead of `rate()` for credits and refunds. Every admin settings save runs `lockShopRates()`, but it only writes when a market's rate actually changed.
6. **CSS:** every `.admin-table-wrap` and `.sc-table-wrap` now has a `max-height` and a sticky `thead th`. A table inside a fixed-height popup could get a second scrollbar.
7. **The seller Delete error changed.** A 409 now carries `can_request_deletion`, and the message text changed.
8. **`/shipping/quote`** accepts `postal_code` and returns `tax_rate_bps`; both are additions only.
9. **Receipt template:** `$money` now uses `Money::format()` with the order's currency.

## 4. Merge checklist for local Claude

- Migration names: if your local branch already has migrations named `2026_10_05_000045`–`000049` (different files with the same prefix), they will both run, which is fine. Check that none of them adds the same columns (`guides`, `commission_rate_bps`, `deactivated_by`, `deletion_requested_at`, `deletion_reason`, `archived_at`) or the `sales_tax_rates` table.
- Most likely merge conflicts, because they're large files both sides edit: `web/src/Admin.jsx`, `Seller.jsx`, `Storefront.jsx`, `SellerProductWizard.jsx`, `backend/routes/api.php`, `AdminSettingController.php`, `SellerProductController.php`, `CheckoutController.php`, `backend/web_deploy/edp.sql`.
- After merging, run:
  ```sh
  cd backend && "D:/xampp/php84/php.exe" artisan migrate && "D:/xampp/php84/php.exe" artisan test
  cd web && npm run lint && npm run build
  ```
- None of the backend tests ran in the cloud session (PHP 8.4 wasn't available). Run them locally, especially `SalesTaxTest`, `CheckoutTest`, `GiftCardTest` and any seller ledger or commission tests.
- Quick manual checks:
  - Buy a digital-only item → you land on Your downloads.
  - Add a PDF guide in the seller wizard → the popup appears on the product page.
  - US checkout → the tax line uses the state rate.
  - Seller Deactivate / Relist.
  - Seller Delete on an ordered product → Removal requested → admin Remove.
