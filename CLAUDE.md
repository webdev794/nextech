# CLAUDE.md

Guidance for Claude Code when working in this repository.

---

## Instructions from the user

<!-- Add your standing instructions below. They are read at the start of every session. -->

- Keep replies short — one line where possible, no long explanations unless asked.
-
-

---

## Project overview

**NexTech** ("Navigate to the Future of Technology") — a USA electronics delivery platform (phones, laptops, audio, smart home, and other gadgets), tracked internally under the `edp` repo/folder name. Delivery mechanics (riders, auto-assignment, COD, delivery radius) are Blinkit-inspired; the storefront browsing UI (deals strips, category carousel, product cards) follows Temu's patterns. Three apps in one repo:

| Path | Stack | Purpose |
|------|-------|---------|
| `backend/` | Laravel 13, PHP 8.4, MySQL (db `edp`) | REST API + serves the built SPA |
| `web/` | React 19 + Vite 8 (plain JS, no TS) | Customer storefront, admin console, rider console |
| `mobile/` | Expo ~52 / React Native | Customer mobile app |

The web app has three entry surfaces: storefront (`Storefront.jsx`), admin (`Admin.jsx` / `AdminEntry.jsx`), rider (`RiderConsole.jsx` / `RiderEntry.jsx`).

## Running locally (Windows)

PHP is at `D:\xampp\php84\php.exe` (not on PATH). From the repo root:

```sh
# API — http://127.0.0.1:8000
cd backend && "D:/xampp/php84/php.exe" -d display_errors=0 artisan serve --host 127.0.0.1 --port 8000

# Web — http://127.0.0.1:5173 (Vite picks the next free port if taken)
cd web && npm --cache D:/edp/.tmp/npm-cache run dev -- --host 127.0.0.1 --port 5173
```

`web/` build: `npm run build` (outputs `web/dist/`). Lint: `npm run lint`.
Test accounts and more detail live in `README.md`.

## Syncing branch v10 locally (pull + database)

When asked to "pull v10" / "update from v10":
1. `git status` — stop and ask if there are uncommitted local changes. Then `git fetch origin v10` and `git checkout v10` (no merging other branches into it), then `git pull origin v10`.
2. Back up the database first:
   `"D:/xampp/mysql/bin/mysqldump.exe" -u root edp > D:/edp/.tmp/edp-backup-<date>.sql`
   (adjust the user/password from `backend/.env`).
3. Database: run migrations, don't re-import `backend/web_deploy/edp.sql`. Migrations are guarded (`hasColumn` / `hasTable`), so they're safe on a database imported earlier.
   - Preview first: `cd backend && "D:/xampp/php84/php.exe" artisan migrate --pretend`
   - Then run them: `"D:/xampp/php84/php.exe" artisan migrate --force`
   - Then clear caches: `"D:/xampp/php84/php.exe" artisan optimize:clear`
4. Dependencies, only if `composer.json` / `package.json` changed:
   - `composer install` (with PHP 8.4);
   - `cd web && npm --cache D:/edp/.tmp/npm-cache install`.
5. Web: `cd web && npm run build` (or restart `npm run dev`).
6. Scheduled jobs (seller reminders, lightning deal restarts, courier tracking) need the scheduler. Locally run `"D:/xampp/php84/php.exe" artisan schedule:work` in a terminal. On the server, a cron runs `php artisan schedule:run` every minute.
7. Report which migrations ran and anything that failed.

## Conventions

- **Environment is Windows + PowerShell.** A Bash tool is also available for POSIX scripts.
- **Production is served under a sub-path** (`/edp/`, set via `VITE_BASE` in `web/.env.production`). Reference bundled assets with paths Vite can rewrite (`./assets/...` from CSS, `import.meta.env.BASE_URL` in JS) — never hard-code a leading `/`.
- **Fonts:** the web app uses **Okra** (the typeface blinkit.com uses), self-hosted in `web/src/assets/fonts/` and declared in `web/src/index.css`. Type scale: 14px base / 12px secondary (nothing smaller), default weight 500, section headings 24px/600. Storefront UI patterns (deals strips, category carousel, product cards, product detail page) follow Temu as the visual reference, not Blinkit — check recent Temu screenshots the user shares before assuming Blinkit conventions.
- **CSS lives in per-surface files** (`StorefrontBase.css`, `Storefront.css`, `Admin.css`, `Rider.css`, `Checkout.css`) — mostly single-line minified-style rules. Match the surrounding format when editing.
- Deploy tooling for cPanel is in `scripts/`.
- **Dependent settings:** when an option is off or hidden, hide the settings that only matter for it and don't require them; when it's on, its linked settings are required — grey out (with the reason) choices that can't work yet, and enforce the same rule on the server, checked only when switching the option on.
- Commit / push only when asked. This repo's `origin` is `webdev794/nextech`, tracking `main`.

## Notes

- `backend/CLAUDE.md` is an auto-generated Laravel Boost bootstrap stub — ignore it; PHP is already installed.
