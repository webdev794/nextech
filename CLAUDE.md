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
| `backend/` | Laravel 13, PHP 8.3, MySQL (db `edp`) | REST API + serves the built SPA |
| `web/` | React 19 + Vite 8 (plain JS, no TS) | Customer storefront, admin console, rider console |
| `mobile/` | Expo ~52 / React Native | Customer mobile app |

The web app has three entry surfaces: storefront (`Storefront.jsx`), admin (`Admin.jsx` / `AdminEntry.jsx`), rider (`RiderConsole.jsx` / `RiderEntry.jsx`).

## Running locally (Windows)

PHP is at `D:\xampp8-2-12\php84\php.exe` (not on PATH). From the repo root:

```sh
# API — http://127.0.0.1:8000
cd backend && "D:/xampp8-2-12/php84/php.exe" -d display_errors=0 artisan serve --host 127.0.0.1 --port 8000

# Web — http://127.0.0.1:5173 (Vite picks the next free port if taken)
cd web && npm --cache D:/edp/.tmp/npm-cache run dev -- --host 127.0.0.1 --port 5173
```

`web/` build: `npm run build` (outputs `web/dist/`). Lint: `npm run lint`.
Test accounts and more detail live in `README.md`.

## Conventions

- **Environment is Windows + PowerShell.** A Bash tool is also available for POSIX scripts.
- **Production is served under a sub-path** (`/edp/`, set via `VITE_BASE` in `web/.env.production`). Reference bundled assets with paths Vite can rewrite (`./assets/...` from CSS, `import.meta.env.BASE_URL` in JS) — never hard-code a leading `/`.
- **Fonts:** the web app uses **Okra** (the typeface blinkit.com uses), self-hosted in `web/src/assets/fonts/` and declared in `web/src/index.css`. Type scale: 14px base / 12px secondary (nothing smaller), default weight 500, section headings 24px/600. Storefront UI patterns (deals strips, category carousel, product cards, product detail page) follow Temu as the visual reference, not Blinkit — check recent Temu screenshots the user shares before assuming Blinkit conventions.
- **CSS lives in per-surface files** (`StorefrontBase.css`, `Storefront.css`, `Admin.css`, `Rider.css`, `Checkout.css`) — mostly single-line minified-style rules. Match the surrounding format when editing.
- Deploy tooling for cPanel is in `scripts/`.
- Commit / push only when asked. This repo's `origin` is `webdev794/nextech`, tracking `main`.

## Notes

- `backend/CLAUDE.md` is an auto-generated Laravel Boost bootstrap stub — ignore it; PHP is already installed.
