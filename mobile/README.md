# NexTech mobile (Expo)

React Native customer app built with Expo. It talks to the same Laravel API as the web
storefront (`backend/`). No Android Studio required — it runs in **Expo Go**.

## First run

```cmd
cd /d D:\edp\mobile
npm.cmd --cache D:\edp\.tmp\npm-cache install
npm.cmd --cache D:\edp\.tmp\npm-cache start
```

Then:

- **Physical Android phone**: install **Expo Go** from the Play Store and scan the QR code
  in the terminal. The phone and PC must be on the same Wi-Fi.
- **Android emulator**: press `a` in the Expo terminal (emulator must already be running).

## Point the app at the API

`src/config.js` resolves the API base URL in this order:

1. `EXPO_PUBLIC_API_URL` environment variable (manual override)
2. **Auto-detected**: the machine serving Expo, on port 8000 — works on a physical phone
   and the emulator with no configuration
3. `extra.apiUrl` in `app.json`
4. `http://10.0.2.2:8000/api` (emulator fallback)

The one thing you must do: start Laravel so the phone can reach it. `artisan serve` binds
to `127.0.0.1` only by default, which a phone cannot see. Bind to all interfaces:

```cmd
cd /d D:\edp\backend
"D:\xampp\php84\php.exe" artisan serve --host 0.0.0.0 --port 8000
```

Windows will likely prompt to allow PHP through the firewall — allow it (Private networks).

**Check it from the phone's browser** before opening the app:
`http://<your-PC-LAN-IP>:8000/api/config` should return JSON. The PC's LAN IP is printed by
`expo start` as `exp://<ip>:8081`, and by `ipconfig` (IPv4 address).

## Opening the app in Expo Go

`expo start` prints a QR code in the terminal. Either:

- **Scan it** with the Expo Go app (Android) — use Expo Go's own scanner, not the camera.
- Or in Expo Go tap **Enter URL manually** and type `exp://<ip>:8081` (the address shown as
  "Metro waiting on ...").

The `a` / `w` keys are for an emulator or web preview and are not needed for a physical phone.

## What's in this build

- Email + password auth with the email OTP step (register and login)
- Catalog: categories, search, product detail
- **Use my location** (`expo-location`, foreground permission — works in Expo Go): scopes
  the catalog to the store that serves you, and saves a new checkout address with the
  coordinates so the server can pick the fulfilling store and check stock
- Local cart with quantity controls
- Checkout: saved address picker or a new address; the server calculates totals
- Stripe card payment in a WebView (Stripe Elements + the `payment-intent` endpoint),
  using the publishable key from `GET /api/config`
- Order history with the live delivery-status tracker and assigned courier
- Rider mode (`is_rider` accounts): deliveries queue + pickup pool; the app pings its
  live location so the backend can auto-assign the nearest rider

## OTP codes in local development

The backend uses `MAIL_MAILER=log`, so the code is written to
`backend/storage/logs/laravel.log` (line `OTP for <email> (<purpose>): <code>` when
`APP_DEBUG=true`). Set `AUTH_OTP_ENABLED=false` in `backend/.env` to skip OTP.

## Not yet built

- Push notifications
- In-app customer support / live chat (backend priority 11)
- Profile editing beyond addresses
