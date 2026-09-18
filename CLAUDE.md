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

**NexTech** ("GDP") — a Blinkit-style grocery delivery platform. Three apps in one repo:

| Path | Stack | Purpose |
|------|-------|---------|
| `backend/` | Laravel 13, PHP 8.3, MySQL (db `gdp`) | REST API + serves the built SPA |
| `web/` | React 19 + Vite 8 (plain JS, no TS) | Customer storefront, admin console, rider console |
| `mobile/` | Expo ~52 / React Native | Customer mobile app |

The web app has three entry surfaces: storefront (`Storefront.jsx`), admin (`Admin.jsx` / `AdminEntry.jsx`), rider (`RiderConsole.jsx` / `RiderEntry.jsx`).

## Running locally (Windows)

PHP is at `D:\xampp8-2-12\php84\php.exe` (not on PATH). From the repo root:

```sh
# API — http://127.0.0.1:8000
cd backend && "D:/xampp8-2-12/php84/php.exe" -d display_errors=0 artisan serve --host 127.0.0.1 --port 8000

# Web — http://127.0.0.1:5173 (Vite picks the next free port if taken)
cd web && npm --cache D:/gdp/.tmp/npm-cache run dev -- --host 127.0.0.1 --port 5173
```

`web/` build: `npm run build` (outputs `web/dist/`). Lint: `npm run lint`.
Test accounts and more detail live in `README.md`.

## Conventions

- **Environment is Windows + PowerShell.** A Bash tool is also available for POSIX scripts.
- **Production is served under a sub-path** (`/gdp/`, set via `VITE_BASE` in `web/.env.production`). Reference bundled assets with paths Vite can rewrite (`./assets/...` from CSS, `import.meta.env.BASE_URL` in JS) — never hard-code a leading `/`.
- **Fonts:** the web app uses **Okra** (the typeface blinkit.com uses), self-hosted in `web/src/assets/fonts/` and declared in `web/src/index.css`. Match Blinkit's type scale: 14px base / 12px secondary (nothing smaller), default weight 500, section headings 24px/600.
- **CSS lives in per-surface files** (`StorefrontBase.css`, `Storefront.css`, `Admin.css`, `Rider.css`, `Checkout.css`) — mostly single-line minified-style rules. Match the surrounding format when editing.
- Deploy tooling for cPanel is in `scripts/`.
- Commit / push only when asked. Default branch is `main`; feature work happens on `v7`.

## Notes

- `backend/CLAUDE.md` is an auto-generated Laravel Boost bootstrap stub — ignore it; PHP is already installed.
