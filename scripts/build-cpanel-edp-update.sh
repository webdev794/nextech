#!/usr/bin/env bash
# Code-only update bundle for the already-live https://testcaresortwork.co.in/edp/
# install: no .env, no database — safe to extract over the existing install
# without touching its configured .env or imported data. Includes a one-time
# web-hit migration runner (_migrate.php) since cPanel shared hosting has no
# Terminal/SSH access to run `php artisan migrate` by hand.
set -euo pipefail

SRC=/d/edp
STAGE=/d/edp/.tmp/deploy/stage-edp-update
OUT=/d/edp/.tmp/deploy/edp-cpanel-update.zip
SUBPATH=edp

rm -rf "$STAGE" "$OUT"
mkdir -p "$STAGE/edp"
DEST="$STAGE/edp"

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
# storage/app is runtime data (uploaded product/seller images etc.) on the
# live server — never overwrite it from this bundle.
rm -rf "$DEST/storage/app" 2>/dev/null || true

echo "==> building React storefront for /$SUBPATH/ (web/.env.production is already VITE_BASE=/edp/)"
cd "$SRC/web"
NODE_OPTIONS="--max-old-space-size=4096" npm --cache D:/edp/.tmp/npm-cache run build
cp -r "$SRC/web/dist/." "$DEST/"
# PHP upload limits for product videos (100 MB) — the web root is this folder.
cp "$SRC/backend/public/.user.ini" "$DEST/.user.ini"

echo "==> front controller (self-healing: cache clear + OPcache reset on first hit)"
DEPLOY_TAG=$(date +%Y%m%d%H%M%S)
cat > "$DEST/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// One-time post-deploy self-heal (no shell needed). A cached config/route file
// left by the previous deploy would otherwise keep the old app running. This
// clears the compiled caches and resets OPcache exactly once per bundle, then
// writes a marker so it never runs again.
$deployTag = '__DEPLOY_TAG__';
$deployMark = __DIR__.'/storage/framework/.deployed-'.$deployTag;
if (! is_file($deployMark)) {
    foreach (glob(__DIR__.'/bootstrap/cache/*.php') ?: [] as $stale) { @unlink($stale); }
    foreach (glob(__DIR__.'/storage/framework/views/*.php') ?: [] as $stale) { @unlink($stale); }
    if (function_exists('opcache_reset')) { @opcache_reset(); }
    @file_put_contents($deployMark, $deployTag."\n");
}

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
sed -i "s/__DEPLOY_TAG__/$DEPLOY_TAG/" "$DEST/index.php"

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

echo "==> _migrate.php (one-time web runner — no Terminal/SSH on shared hosting)"
cat > "$DEST/_migrate.php" <<PHP
<?php
/**
 * One-time migration runner. Visit
 *   https://testcaresortwork.co.in/$SUBPATH/_migrate.php?key=RUNME
 * Runs the migrations added since the last deploy against the LIVE database
 * (uses the live .env already sitting next to this file — nothing here
 * touches your DB credentials), then clears caches. Does NOT delete itself,
 * so it's safe to hit again on a future update — but DELETE THIS FILE once
 * you're done, since it's an unauthenticated migration runner otherwise
 * gated only by the ?key= in the URL.
 */
if ((\$_GET['key'] ?? '') !== 'RUNME') { http_response_code(403); exit('Forbidden'); }

require __DIR__.'/vendor/autoload.php';
\$app = require __DIR__.'/bootstrap/app.php';
\$app->usePublicPath(__DIR__);
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/plain');
foreach (['migrate --force', 'optimize:clear'] as \$cmd) {
    echo "\\\$ php artisan \$cmd\n";
    try { \$kernel->call(\$cmd); echo \$kernel->output(); }
    catch (Throwable \$e) { echo 'ERROR: '.\$e->getMessage()."\n"; }
    echo "\n";
}
echo "Done. Remember to delete _migrate.php.\n";
PHP

echo "==> writing runtime dirs (empty — server keeps its own)"
mkdir -p "$DEST/storage/framework/cache/data" \
         "$DEST/storage/framework/sessions" \
         "$DEST/storage/framework/views" \
         "$DEST/storage/logs" \
         "$DEST/storage/app/public" \
         "$DEST/bootstrap/cache"
find "$DEST/storage" "$DEST/bootstrap/cache" -name '.gitignore' -delete 2>/dev/null || true

echo "==> READ_ME_FIRST.txt"
cat > "$STAGE/READ_ME_FIRST.txt" <<TXT
NEXTECH - /edp/ code update bundle
===================================
Updated application code + rebuilt frontend. No .env, no database in this
zip - your live config and imported data are untouched. index.php clears
stale compiled caches and resets OPcache on first hit after upload, no
Terminal needed for that part.

This release (25 Sep 2026): seller onboarding tasks (tax info, compliance
info, bank verification), Temu-style Manage orders (pending / unshipped /
shipped / cancelled, search by up to 100 IDs, buyer address changes), buyer
privacy for sellers, step-by-step Add product wizard, Excel bulk upload,
sales boost / pricing health, product compliance, trademarks, store
decoration (desktop + mobile), shop category carousel, admin label postage.

UPLOADED FILES: product photos, videos, store designs and seller documents
live in storage/app/, which this zip never touches. If you import the local
database, also upload  edp-storage.zip  (made next to this zip) into
public_html/  and extract it, so the files the database points to exist.

DATABASE: you're importing the local database, which already has every
migration applied — _migrate.php is then only a safety check (it reports
"Nothing to migrate"). If you DON'T import the DB, run it to add the new
tables/columns.

AFTER IMPORT: check Admin > Stores - the two stores are in India but saved
as country US; edit each and set Country = India. Fill in the India
grievance officer under Admin > Settings (currency switch = INR).

SCHEDULED EMAILS (Admin > Emails, Customers > View > Schedule email): add ONE
cron job in cPanel > Cron Jobs, every minute:
  * * * * * /usr/local/bin/php /home/<cpanel-user>/public_html/$SUBPATH/artisan schedule:run >/dev/null 2>&1
(check the PHP path in cPanel > MultiPHP; without this cron, "Send now" still
works but scheduled/repeating emails never go out). Emails are sent with the
MAIL_* settings in .env - set a real mailer there (not MAIL_MAILER=log).

DEPLOY
  1. Back up: cPanel > File Manager, download  public_html/$SUBPATH/  first.
  2. Upload this zip into  public_html/  and Extract, overwrite when asked.
     (Writes into  public_html/$SUBPATH/ — your .env and storage/app/ are
     not in this zip, so they're left exactly as they are.)
  3. Open  https://testcaresortwork.co.in/$SUBPATH/  and hard-refresh
     (Ctrl+Shift+R) — confirms the new code is live.
  4. Run the new migrations once:
       https://testcaresortwork.co.in/$SUBPATH/_migrate.php?key=RUNME
     Read the plain-text output for errors. Then delete
     public_html/$SUBPATH/_migrate.php (it has no auth beyond that URL key).

Optional cleanup: in  public_html/$SUBPATH/assets/  the old  *-<hash>.js / .css
files no longer named in index.html are just unused bytes - delete anytime.

Rollback: re-upload your  public_html/$SUBPATH/  backup from step 1. If you
already ran _migrate.php, the new columns are additive (nothing dropped), so
rolling back the code is safe even if you don't also roll back the DB.
TXT

echo "==> removing Windows-only helpers (hosting virus scanners reject zips with .exe/.bat)"
find "$DEST" -type f \( -iname '*.exe' -o -iname '*.bat' -o -iname '*.cmd' \) -delete

echo "==> storage bundle (uploads that the local database refers to)"
STORAGE_OUT=/d/edp/.tmp/deploy/edp-storage.zip
rm -f "$STORAGE_OUT"
( cd "$SRC/backend" && /d/xampp/php84/php.exe -r '
    $out = $argv[1]; $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "cannot open storage zip\n"); exit(1); }
    $n = 0;
    foreach (["storage/app/public", "storage/app/private"] as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->getFilename() === ".gitignore") continue; $zip->addFile($f->getPathname(), "edp/".str_replace("\\", "/", $f->getPathname())); $n++; }
    }
    $zip->close(); echo "   $n files -> $out\n";' "$STORAGE_OUT" )

echo "==> zipping"
cd "$STAGE"
if command -v zip >/dev/null 2>&1; then
  zip -qr "$OUT" edp READ_ME_FIRST.txt -x '*/.DS_Store'
else
  STAGE_WIN=$(cygpath -w "$STAGE" 2>/dev/null || echo "$STAGE")
  OUT_WIN=$(cygpath -w "$OUT" 2>/dev/null || echo "$OUT")
  STAGE_PHP=${STAGE_WIN//\\//}
  OUT_PHP=${OUT_WIN//\\//}
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
  ' "$STAGE_PHP" "$OUT_PHP"
fi

echo
echo "BUILT: $OUT"
du -h "$OUT" | cut -f1
