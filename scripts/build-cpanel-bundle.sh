#!/usr/bin/env bash
set -euo pipefail

SRC=/d/gdp
STAGE=/d/gdp/.tmp/deploy/stage
OUT=/d/gdp/.tmp/deploy/gdp-cpanel.zip
APP_KEY="base64:85zylZmpyA4Kti0Y8AKwm72HyEswbNOQFDoF6kBZVc0="

rm -rf "$STAGE" "$OUT"
mkdir -p "$STAGE/gdp"
DEST="$STAGE/gdp"

echo "==> copying Laravel app"
cd "$SRC/backend"
cp -a ./ "$DEST/"
rm -rf "$DEST/.git" "$DEST/node_modules" "$DEST/tests" "$DEST/phpunit.xml"        "$DEST/.phpunit.result.cache" "$DEST/.phpunit.cache" "$DEST/.env" "$DEST/public"
rm -f  "$DEST/storage/logs/"*.log
rm -rf "$DEST/storage/framework/cache/data/"*        "$DEST/storage/framework/sessions/"*        "$DEST/storage/framework/views/"*.php        "$DEST/storage/framework/testing" 2>/dev/null || true
rm -f  "$DEST/bootstrap/cache/"*.php

echo "==> merging built React storefront"
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
cat > "$DEST/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteBase /gdp/

    DirectoryIndex index.php

    # Authorization / XSRF headers
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Trailing slashes
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Front controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Block direct web access to application internals
RedirectMatch 404 (?i)/gdp/(app|bootstrap|config|database|resources|routes|vendor|tests)/
RedirectMatch 404 (?i)/gdp/storage/(framework|logs|app)/
<FilesMatch "^(\.env.*|composer\.(json|lock)|artisan|package.*\.json|.*\.md)$">
    Require all denied
</FilesMatch>
HTACCESS

echo "==> .env"
cat > "$DEST/.env" <<PHP
APP_NAME=NexTech
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://testcaresortwork.co.in/gdp
ASSET_URL=https://testcaresortwork.co.in/gdp

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# ---- FILL THESE IN (cPanel > MySQL Databases) ----
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=CPUSER_gdp
DB_USERNAME=CPUSER_gdp
DB_PASSWORD=CHANGE_ME
# -------------------------------------------------

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# OTP email. Create a mailbox in cPanel > Email Accounts and fill these,
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
# Create a webhook at .../gdp/api/payments/stripe/webhook and paste its secret:
STRIPE_WEBHOOK_SECRET=whsec_CHANGE_ME
PHP

# splice in the real test keys from the working tree (kept out of the heredoc)
PK=$(grep -oE 'pk_test_[A-Za-z0-9]+' "$SRC/backend/.env" | head -1)
SK=$(grep -oE 'sk_test_[A-Za-z0-9]+' "$SRC/backend/.env" | head -1)
sed -i "s|__STRIPE_PK__|${PK}|; s|__STRIPE_SK__|${SK}|" "$DEST/.env"

echo "==> _setup.php (one-time web runner)"
cat > "$DEST/_setup.php" <<'PHP'
<?php
/**
 * One-time setup. Visit  https://testcaresortwork.co.in/gdp/_setup.php?key=RUNME
 * The database is imported by hand from a local dump, so this only links the
 * storage dir and clears caches, then deletes itself.
 * DELETE THIS FILE manually if it is still here afterwards.
 */
if (($_GET['key'] ?? '') !== 'RUNME') { http_response_code(403); exit('Forbidden'); }

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/plain');
foreach (['storage:link', 'optimize:clear'] as $cmd) {
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
cat > "$STAGE/READ_ME_FIRST.txt" <<'TXT'
NEXTECH - cPanel deployment bundle (single folder, DB imported by hand)
=====================================================================

1. In cPanel > File Manager, open  public_html/
2. Upload this zip INTO public_html/  and Extract it.
   You should end up with:  public_html/gdp/index.php  (and app/, vendor/, assets/, ...)
   Everything lives inside public_html/gdp/ - nothing is placed outside it.
3. cPanel > MySQL Databases: create a database + user (All Privileges),
   then import your local dump into it (phpMyAdmin > Import).
4. Edit  public_html/gdp/.env  and set:
      DB_DATABASE, DB_USERNAME, DB_PASSWORD   (the DB you just imported into)
      MAIL_PASSWORD            (or set AUTH_OTP_ENABLED=false and skip mail)
      STRIPE_WEBHOOK_SECRET    (see Stripe note below)
5. cPanel > MultiPHP Manager: set this domain to PHP 8.3 (8.2 minimum).
6. Visit:  https://testcaresortwork.co.in/gdp/_setup.php?key=RUNME
   It runs "storage:link" + "optimize:clear" and self-deletes. No migrate,
   no seed - your imported database is used as-is.
7. Open  https://testcaresortwork.co.in/gdp/
      admin login (from your dump): test@example.com / password
   OTP codes (if mail not set up) are in  public_html/gdp/storage/logs/laravel.log

Stripe: dashboard > Webhooks > add endpoint
   https://testcaresortwork.co.in/gdp/api/payments/stripe/webhook
   events: payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled
   put its signing secret in .env as STRIPE_WEBHOOK_SECRET, then delete
   public_html/gdp/bootstrap/cache/config.php  (it re-caches on next hit).

Troubleshooting: set APP_DEBUG=true in .env, delete bootstrap/cache/config.php,
reload to see the error, then set it back to false.
TXT

echo "==> zipping"
cd "$STAGE"
if command -v zip >/dev/null 2>&1; then
  zip -qr "$OUT" gdp READ_ME_FIRST.txt -x '*/\.DS_Store'
else
  # No `zip` binary: use PHP's ZipArchive so every entry keeps forward-slash
  # paths (PowerShell's Compress-Archive writes backslashes that Linux unzip
  # mangles). Entries are gdp/... plus READ_ME_FIRST.txt at the archive root.
  STAGE_WIN=$(cygpath -w "$STAGE"); OUT_WIN=$(cygpath -w "$OUT")
  php -r '
    $stage = $argv[1]; $out = $argv[2];
    @unlink($out);
    $zip = new ZipArchive();
    $zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($stage.DIRECTORY_SEPARATOR."gdp", FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );
    $n = 0;
    foreach ($it as $f) {
      $rel = "gdp/".str_replace("\\", "/", substr($f->getPathname(), strlen($stage) + 5));
      if ($f->isDir()) { $zip->addEmptyDir($rel); }
      else { $zip->addFile($f->getPathname(), $rel); $n++; }
    }
    $zip->addFile($stage.DIRECTORY_SEPARATOR."READ_ME_FIRST.txt", "READ_ME_FIRST.txt");
    $zip->close();
    echo "files: {$n}\n";
  ' "$STAGE_WIN" "$OUT_WIN"
fi

echo
echo "BUILT: $OUT"
du -h "$OUT" | cut -f1
