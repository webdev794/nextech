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
  - couriers and holidays (optional).
- [ ] **Storage:** move profiles from `config/markets.php` and `config/countries.php` into a database table, for example `markets` (code, name, currency, profile JSON, is_visible, sort order).
  - Keep the config files as the built-in defaults: US and India are seeded from them on migrate.
  - **DB change:** add the migration, and mirror it in `backend/web_deploy/edp.sql`.
- [ ] **Money formatting:** make `Money::format()` and `web/src/money.js` work for any currency, using `Intl.NumberFormat` in the browser and a symbol/decimals table on the server, instead of only USD and INR.
- [ ] **Currency conversion:** add new currencies to the conversion settings (`Fx`) for cross-border orders, with a manual rate field until an automatic rate source exists.
- [ ] **Checks before a new country can be shown:** currency set; tax rule set; at least one way to deliver (NexTech store and riders, or sellers shipping themselves); seller onboarding fields complete.
- [ ] **Tests:** add a country, show and hide it, a seller registers there, checkout prices and tax in its currency, payouts use its limits, and hiding removes it from the storefront and admin.

### Notes / risks
- Each country has its own tax law (VAT, GST, sales tax, invoice rules). The template only sets how tax is charged; confirm the rules for each country before going live.
- Payment provider: check the card processor supports the currency and country.
- Existing code that special-cases `'US'` or `'IN'` needs reviewing, for example `Market::usesLegacySettings()` and India's GST/TCS withholding.
