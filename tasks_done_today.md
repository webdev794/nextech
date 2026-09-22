# Tasks Done Today

- Built the multi-vendor Seller Center (`/seller`): registration wizard, admin approval, country-driven business fields (India/US), seller-managed products (variants, image gallery, admin approval queue), seller-side order view, and a commission + payout ledger with a $1000 minimum payout threshold
- Added a dual delivery system: own riders (unchanged) plus a pluggable online-courier fallback for out-of-radius orders, with a mock provider now and admin-entered credentials for a real one later
- Added seller payout method (bank/PayPal), a seller-only Terms & Conditions page, and two-way seller↔admin messaging (reusing the support-thread system, visible to admin under a dedicated filter)
- Added strict product-photo rules (1:1, JPEG/PNG, 800KB max, 8-photo gallery cap) with instant client-side validation, without breaking category/banner/branding uploads that share the same upload code
- Fixed a real regression: an earlier cleanup pass had deleted the seeded `test@example.com` admin account — restored it and cleaned up other leftover test data; `logins.md` now documents all current accounts accurately
- Committed and pushed everything to a new `v4` branch on GitHub (not merged — no PR opened, per instruction)
