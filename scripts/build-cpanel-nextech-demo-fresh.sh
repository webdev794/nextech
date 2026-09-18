#!/usr/bin/env bash
# Fresh, fully self-contained first-time deployment bundle for
# https://testcaresortwork.co.in/gdp/nextech_demo/ — app code, built
# frontend, AND the locally-uploaded banner/product images (storage/app),
# so nothing needs to be fetched separately. Only the database (import it
# yourself from backend/web_deploy/gdp (1).sql) and the DB/mail credentials
# in .env are left for you to fill in — there's no way to know your live
# DB name/user/password or mailbox password from here.
set -euo pipefail

SRC=/d/gdp
STAGE=/d/gdp/.tmp/deploy/stage-nextech-demo-fresh
OUT=/d/gdp/.tmp/deploy/nextech-demo-fresh.zip
APP_KEY="base64:$(openssl rand -base64 32)"
SUBPATH=gdp/nextech_demo

rm -rf "$STAGE" "$OUT"
mkdir -p "$STAGE/nextech_demo"
DEST="$STAGE/nextech_demo"

echo "==> copying Laravel app"
cd "$SRC/backend"
cp -a ./ "$DEST/"
rm -rf "$DEST/.git" "$DEST/node_modules" "$DEST/tests" "$DEST/phpunit.xml" \
       "$DEST/.phpunit.result.cache" "$DEST/.phpunit.cache" "$DEST/.env" "$DEST/.env.example" \
       "$DEST/public" "$DEST/database/database.sqlite" "$DEST/web_deploy"
rm -f  "$DEST/storage/logs/"*.log
rm -rf "$DEST/storage/framework/cache/data/"* \
       "$DEST/storage/framework/sessions/"* \
       "$DEST/storage/framework/views/"*.php \
       "$DEST/storage/framework/testing" 2>/dev/null || true
rm -f  "$DEST/bootstrap/cache/"*.php
# Unlike the code-only update bundle, storage/app IS included here — this is
# a first-time deploy, so there's no live storage/app to protect yet, and
# without it the banner/product images this database references would 404.

echo "==> building React storefront for /$SUBPATH/"
cd "$SRC/web"
cp .env.production .env.production.bak
trap 'mv -f .env.production.bak .env.production 2>/dev/null || true' EXIT
printf 'VITE_BASE=/%s/\nVITE_API_URL=/%s/api\n' "$SUBPATH" "$SUBPATH" > .env.production
for attempt in 1 2 3; do
  if NODE_OPTIONS="--max-old-space-size=4096" npm --cache D:/gdp/.tmp/npm-cache run build; then
    break
  fi
  echo "build attempt $attempt failed (transient OOM in the bundler is common here) — retrying..." >&2
  if [ "$attempt" = 3 ]; then echo "build failed 3 times, giving up" >&2; exit 1; fi
  sleep 3
done
trap - EXIT
mv .env.production.bak .env.production
cp -r "$SRC/web/dist/." "$DEST/"

echo "==> front controller (single-folder layout)"
cat > "$DEST/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

// App files and the web root are the same folder in this deployment.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
PHP

echo "==> .htaccess"
cat > "$DEST/.htaccess" <<HTACCESS
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteBase /$SUBPATH/

    DirectoryIndex index.php

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/\$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

RedirectMatch 404 (?i)/$SUBPATH/(app|bootstrap|config|database|resources|routes|vendor|tests)/
RedirectMatch 404 (?i)/$SUBPATH/storage/(framework|logs|app)/
<FilesMatch "^(\.env.*|composer\.(json|lock)|artisan|package.*\.json|.*\.md)\$">
    Require all denied
</FilesMatch>
HTACCESS
# Note: the RedirectMatch above blocks storage/framework, storage/logs and
# storage/app/*.php etc from direct access, but storage/app/public/... is
# still reachable — routes/web.php's Route::get('/storage/{path}', ...)
# serves those files itself (no storage:link needed/possible in this
# single-folder layout), and the block only matches the literal segments
# "framework", "logs" and raw "app" — not the app/public subtree Laravel's
# own route already validates.

echo "==> .env (fill in DB + mail before first visit)"
cat > "$DEST/.env" <<PHP
APP_NAME=NexTech
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://testcaresortwork.co.in/$SUBPATH
ASSET_URL=https://testcaresortwork.co.in/$SUBPATH

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# ---- Fill in after you import backend/web_deploy/gdp (1).sql ----
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=CHANGE_ME
DB_USERNAME=CHANGE_ME
DB_PASSWORD=CHANGE_ME
# -------------------------------------------------------------------

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# OTP email. Create a mailbox in cPanel > Email Accounts and fill this in,
# or set AUTH_OTP_ENABLED=false to sign in with password only.
AUTH_OTP_ENABLED=true
MAIL_MAILER=smtp
MAIL_HOST=mail.testcaresortwork.co.in
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@testcaresortwork.co.in
MAIL_PASSWORD=CHANGE_ME
MAIL_FROM_ADDRESS=no-reply@testcaresortwork.co.in
MAIL_FROM_NAME=NexTech

# Stripe TEST keys (rotate in the dashboard if you ever share this bundle).
STRIPE_PUBLISHABLE_KEY=__STRIPE_PK__
STRIPE_SECRET=__STRIPE_SK__
# Create a webhook at .../$SUBPATH/api/payments/stripe/webhook and paste its secret:
STRIPE_WEBHOOK_SECRET=whsec_CHANGE_ME
PHP

PK=$(grep -oE 'pk_test_[A-Za-z0-9]+' "$SRC/backend/.env" | head -1)
SK=$(grep -oE 'sk_test_[A-Za-z0-9]+' "$SRC/backend/.env" | head -1)
sed -i "s|__STRIPE_PK__|${PK}|; s|__STRIPE_SK__|${SK}|" "$DEST/.env"

echo "==> _setup.php (one-time web runner)"
cat > "$DEST/_setup.php" <<'PHP'
<?php
/**
 * One-time setup. Visit https://testcaresortwork.co.in/gdp/nextech_demo/_setup.php?key=RUNME
 * after editing .env and importing the database. Clears caches so the fresh
 * .env/DB actually take effect, then deletes itself.
 * DELETE THIS FILE manually if it is still here afterwards.
 */
if (($_GET['key'] ?? '') !== 'RUNME') { http_response_code(403); exit('Forbidden'); }

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/plain');
foreach (['optimize:clear'] as $cmd) {
    echo "\$ php artisan $cmd\n";
    try { $kernel->call($cmd); echo $kernel->output(); }
    catch (Throwable $e) { echo 'ERROR: '.$e->getMessage()."\n"; }
    echo "\n";
}
echo "Done. Removing _setup.php\n";
@unlink(__FILE__);
PHP

echo "==> writing runtime dirs"
mkdir -p "$DEST/storage/framework/cache/data" \
         "$DEST/storage/framework/sessions" \
         "$DEST/storage/framework/views" \
         "$DEST/storage/logs" \
         "$DEST/storage/app/public" \
         "$DEST/bootstrap/cache"
find "$DEST/storage" "$DEST/bootstrap/cache" -name '.gitignore' -delete 2>/dev/null || true
rm -f "$DEST/storage/logs/"*.log 2>/dev/null || true

echo "==> READ_ME_FIRST.txt"
cat > "$STAGE/READ_ME_FIRST.txt" <<TXT
NEXTECH DEMO - fresh, self-contained deploy bundle
===================================================
Everything is in this zip: app code, built frontend, and the uploaded
banner/product images (storage/app). Only the database and a few .env
values are left for you, since only you have the live DB credentials.

DEPLOY
  1. In cPanel > File Manager, open public_html/gdp/
     (create the gdp/ folder first if nothing is there yet).
  2. Upload this zip into public_html/gdp/ and Extract it, overwrite if asked.
     You should end up with: public_html/gdp/nextech_demo/index.php
  3. Import backend/web_deploy/gdp (1).sql into your live MySQL database
     (phpMyAdmin > Import), if you haven't already.
  4. Edit public_html/gdp/nextech_demo/.env and set:
        DB_DATABASE, DB_USERNAME, DB_PASSWORD   (the database you just imported into)
        MAIL_PASSWORD   (or set AUTH_OTP_ENABLED=false to sign in with password only)
        STRIPE_WEBHOOK_SECRET   (optional for a demo)
  5. cPanel > MultiPHP Manager: set this domain to PHP 8.3 (8.2 minimum).
  6. Visit once: https://testcaresortwork.co.in/gdp/nextech_demo/_setup.php?key=RUNME
     It clears caches so the .env/database you just set actually take effect,
     then deletes itself.
  7. Open https://testcaresortwork.co.in/gdp/nextech_demo/ and hard-refresh
     (Ctrl+Shift+R).

Stripe (optional): dashboard > Webhooks > add endpoint
   https://testcaresortwork.co.in/gdp/nextech_demo/api/payments/stripe/webhook
   events: payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled
   put its signing secret in .env as STRIPE_WEBHOOK_SECRET, then delete
   public_html/gdp/nextech_demo/bootstrap/cache/config.php (it re-caches on next hit).

Troubleshooting: set APP_DEBUG=true in .env, delete bootstrap/cache/config.php,
reload to see the error, then set it back to false.

Note: this bundle's APP_KEY is freshly generated (not shared with any other
install) — that's fine since nothing in the database is encrypted with it;
Laravel only uses it for sessions/cookies, which don't need to match.
TXT

echo "==> zipping"
cd "$STAGE"
if command -v zip >/dev/null 2>&1; then
  zip -qr "$OUT" nextech_demo READ_ME_FIRST.txt -x '*/.DS_Store'
else
  STAGE_WIN=$(cygpath -w "$STAGE" 2>/dev/null || echo "$STAGE")
  OUT_WIN=$(cygpath -w "$OUT" 2>/dev/null || echo "$OUT")
  php -r '
    $stage = $argv[1]; $out = $argv[2];
    @unlink($out);
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "cannot open zip\n"); exit(1); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    $n = 0;
    foreach ($it as $f) {
        $rel = substr(str_replace("\\", "/", $f->getPathname()), strlen($stage) + 1);
        if ($f->isDir()) { $zip->addEmptyDir($rel); }
        else { $zip->addFile($f->getPathname(), $rel); $n++; }
    }
    $zip->close();
    echo "files: $n\n";
  ' "$STAGE_WIN" "$OUT_WIN"
fi

echo
echo "BUILT: $OUT"
du -h "$OUT" | cut -f1
