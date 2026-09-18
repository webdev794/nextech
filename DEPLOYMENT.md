# Deploying to cPanel at `https://testcaresortwork.co.in/gdp/`

The Laravel API and the React storefront are served from the **same path**, and every file
lives **inside `public_html/gdp/`** — nothing above `public_html`.

- `https://testcaresortwork.co.in/gdp/`        → React storefront + admin console
- `https://testcaresortwork.co.in/gdp/api/...` → Laravel API

No sub-domain, no CORS (same origin), one MySQL database. Auth is Bearer-token, so there are
no cookie/session/CSRF concerns.

## The easy way: the prebuilt bundle

Use `gdp-cpanel.zip` (built by `.tmp/deploy/build-deploy.sh`). It already contains the
single-folder layout below. Follow `READ_ME_FIRST.txt` inside it:

1. cPanel → File Manager → `public_html/` → upload the zip → **Extract**.
   Result: `public_html/gdp/index.php`, `public_html/gdp/app/`, `.../vendor/`, `.../assets/`, …
2. cPanel → **MySQL Databases**: create a DB + user (All Privileges).
3. Edit `public_html/gdp/.env` — set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   (and `MAIL_PASSWORD`, or set `AUTH_OTP_ENABLED=false` to skip email).
4. cPanel → **MultiPHP Manager**: set the domain to **PHP 8.3** (8.2 minimum).
5. Visit `https://testcaresortwork.co.in/gdp/_setup.php?key=RUNME` — it migrates, seeds demo
   data, caches config, then deletes itself.
6. Open `https://testcaresortwork.co.in/gdp/` — admin login `test@example.com` / `password`.
7. Stripe → Webhooks → add `https://testcaresortwork.co.in/gdp/api/payments/stripe/webhook`
   (events `payment_intent.succeeded|payment_failed|canceled`), put its signing secret in
   `.env` as `STRIPE_WEBHOOK_SECRET`, then delete `public_html/gdp/bootstrap/cache/config.php`.

## Layout on the server (what the bundle creates)

```
public_html/gdp/
├── index.php            front controller: requires ./vendor + ./bootstrap,
│                        and calls $app->usePublicPath(__DIR__)
├── .htaccess            rewrites to index.php + RewriteBase /gdp/ +
│                        "Require all denied" for app/ config/ vendor/ .env …
├── index.html          React entry (served by the SpaController fallback)
├── assets/             React JS/CSS  (referenced as /gdp/assets/…)
├── favicon.svg  …
├── .env                production config
├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/ artisan
└── _setup.php           one-time installer (self-deletes)
```

Direct web access to the framework folders is blocked by `.htaccess`, so keeping them under
`public_html` is safe for this MVP. (If your host lets you move a document root, you *can*
put `app/ vendor/ …` one level up and point `index.php`'s requires at `../`, but it is not
required.)

## Building the bundle yourself

```cmd
:: production PHP deps (no dev packages -> smaller)
cd /d D:\gdp\backend
php composer.phar install --no-dev --optimize-autoloader

:: storefront, already configured for /gdp/ via web/.env.production
cd /d D:\gdp\web
npm run build

:: assemble + zip (writes to D:\gdp\.tmp\deploy\)
bash D:\gdp\.tmp\deploy\build-deploy.sh
```

`web/.env.production` sets `VITE_BASE=/gdp/` and `VITE_API_URL=/gdp/api`, so the built
`index.html` points assets and API calls at `/gdp/...`.

## `.env` values that matter in production

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://testcaresortwork.co.in/gdp
APP_KEY=base64:...              # already generated in the bundle
DB_DATABASE / DB_USERNAME / DB_PASSWORD
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync           # no worker on shared hosting; OTP mail sends inline
AUTH_OTP_ENABLED=true           # false = password-only login, no email needed
MAIL_MAILER=smtp + MAIL_* …     # a cPanel mailbox, for OTP codes
STRIPE_PUBLISHABLE_KEY / STRIPE_SECRET / STRIPE_WEBHOOK_SECRET
```

## Redeploying later

```cmd
cd /d D:\gdp\backend && php composer.phar install --no-dev --optimize-autoloader
cd /d D:\gdp\web && npm run build
bash D:\gdp\.tmp\deploy\build-deploy.sh
```

Upload the new zip, extract over the old files (the hashed `assets/*` names change — remove
stale ones), then in cPanel delete `public_html/gdp/bootstrap/cache/config.php` and hit any
page so it re-caches. If migrations changed, re-run `_setup.php` (it is safe to re-run;
`migrate` and the seeder are idempotent) or run `php artisan migrate --force` via Terminal.

## Notes / limits on shared hosting

- `QUEUE_CONNECTION=sync` — no background worker; OTP mail and notifications send during the
  request. Fine for this MVP.
- No Redis — file cache and file sessions.
- The seeder is idempotent (`updateOrCreate`) and needs no Faker, so it runs on a
  `--no-dev` install.
- The mobile app (`mobile/`) is not part of this deploy. Point it at the live API with
  `EXPO_PUBLIC_API_URL=https://testcaresortwork.co.in/gdp/api` before `expo start`.
- Keep `APP_DEBUG=false`. To debug a 500: set it true, delete
  `bootstrap/cache/config.php`, reload, read the error, set it back.
