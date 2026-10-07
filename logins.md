# NexTech — Local URLs & Test Logins

Everything here assumes both dev servers are running (see `README.md` → *See Output Quickly*):
API on `http://127.0.0.1:8000`, storefront on `http://127.0.0.1:5173`.

## URLs

| Surface | URL | Notes |
|---|---|---|
| Storefront | <http://127.0.0.1:5173/> | Customer site — browse, cart, checkout |
| Admin console | <http://127.0.0.1:5173/admin> | `is_admin` accounts only |
| Rider console | <http://127.0.0.1:5173/rider> | `is_rider` accounts only |
| Seller Center | <http://127.0.0.1:5173/seller> | Any signed-in customer account — registers, then waits for admin approval |

Production (served under `/edp/`): same paths under `https://<host>/edp/...`, e.g. `/edp/admin`, `/edp/seller`.

## Test accounts

All seeded passwords are **`password`**.

| Role | Email | Notes |
|---|---|---|
| Admin | `test@example.com` | Seeded with `is_admin = true` |
| Rider | `rider@example.com` | Seeded with `is_rider = true` |
| Customer | `testcaresort@outlook.com` | A manually-created test signup, not seeded — its password isn't tracked here |
| Seller (demo) | `seller@example.com` | Manually created, password `password` — already **approved**, shop name "cs". Signing in at `/seller` skips the wizard and goes straight to the post-approval dashboard. `is_admin` is **off** (`/admin` is reserved for the website owner — funds/payouts/product-approval access — sellers only ever use `/seller`). |
| Admin (secondary) | `uiadmin@ex.com` | Another manually-created admin account on this local DB; password not tracked here. |

The Seller Center otherwise reuses the ordinary customer login — sign in (or register) at `/seller` with any account, including `test@example.com`, and it can apply.

## Trying the Seller Center

1. Go to `/seller`, sign in with any account (or register a new one).
2. If it has no application yet, the 4-step wizard shows: Business information → Seller information → Shop → Verification. The **Business location** dropdown (step 1) drives which business types, tax-ID field, and address labels (State/PIN vs State/ZIP) appear — try switching between India and United States to see it change live.
3. Submit. The account now shows a "pending review" status page.
4. Sign in to `/admin` as `test@example.com`, open the new **Sellers** tab (sidebar, after Riders), find the application, open it, and **Approve** (or **Reject** with a reason — the reason shows back to the seller).
5. Once approved, the seller's shop can be assigned to any product from **Admin → Products → edit a product → Shop** dropdown.

**Dev-only setting:** which countries appear in that dropdown is controlled by **Admin → Settings → Countries**. It's currently set to **India + United States** on this local DB (default is US-only) so both can be tried without extra setup — this is a `Setting` row (`active_countries`), not a code change, and won't survive a fresh migration/seed.

## Quick API checks

```cmd
curl http://127.0.0.1:8000/api/config
curl http://127.0.0.1:8000/api/categories
curl http://127.0.0.1:8000/api/products
```

See `README.md` for the full endpoint reference, and the **Seller Center** section there for how the feature is built.
