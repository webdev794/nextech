#!/usr/bin/env bash
# Code-only update bundle for the nextech_demo install: no .env, no _setup.php,
# no database — safe to extract over the existing live install without
# touching its configured .env or imported data.
set -euo pipefail

SRC=/d/gdp
STAGE=/d/gdp/.tmp/deploy/stage-nextech-demo-update
OUT=/d/gdp/.tmp/deploy/nextech-demo-update.zip
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
# storage/app is runtime data (uploaded product images etc.) on the live
# server — never overwrite it from this bundle.
rm -rf "$DEST/storage/app" 2>/dev/null || true

echo "==> building React storefront for /$SUBPATH/"
cd "$SRC/web"
cp .env.production .env.production.bak
# However this exits (success, build failure, Ctrl-C), .env.production always
# comes back to the committed /gdp/ default — a prior version of this script
# left it stuck on /gdp/nextech_demo/ after a crashed build.
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

echo "==> front controller (self-healing: cache clear + storage:link + URL fix on first hit)"
DEPLOY_TAG=$(date +%Y%m%d%H%M%S)
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

$app->usePublicPath(__DIR__);

// One-time post-deploy self-heal, run exactly once per bundle (guarded by the
// deploy-tag marker file): clear stale compiled caches, and strip any
// http://127.0.0.1:8000 baked into image URLs by an upload made from local
// dev before this fix existed. (No storage:link here — this single-folder
// layout makes public_path('storage') the same path as storage_path()
// itself, so that symlink can't be created; routes/web.php serves
// storage/app/public/... directly instead.)
$deployTag = '__DEPLOY_TAG__';
$deployMark = __DIR__.'/storage/framework/.deployed-'.$deployTag;
if (! is_file($deployMark)) {
    foreach (glob(__DIR__.'/bootstrap/cache/*.php') ?: [] as $stale) { @unlink($stale); }
    foreach (glob(__DIR__.'/storage/framework/views/*.php') ?: [] as $stale) { @unlink($stale); }
    if (function_exists('opcache_reset')) { @opcache_reset(); }

    try {
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $pattern = '#^https?://127\.0\.0\.1:8000#';
        foreach ([App\Models\Banner::class, App\Models\ProductVariant::class] as $model) {
            foreach ($model::where('image_url', 'like', '%127.0.0.1:8000%')->get() as $row) {
                $row->update(['image_url' => preg_replace($pattern, '', $row->image_url)]);
            }
        }
        $branding = App\Models\Setting::get('branding', []);
        $changed = false;
        foreach (['logo_url', 'favicon_url'] as $field) {
            if (!empty($branding[$field]) && preg_match($pattern, $branding[$field])) {
                $branding[$field] = preg_replace($pattern, '', $branding[$field]);
                $changed = true;
            }
        }
        if ($changed) {
            App\Models\Setting::put('branding', $branding);
        }
    } catch (Throwable $e) {
        // Don't let a self-heal failure take the site down; it retries next
        // deploy. Logged so it's visible in storage/logs if something's off.
        report($e);
    }

    @file_put_contents($deployMark, $deployTag."\n");
}

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
NEXTECH DEMO - code update bundle
==================================
Updated application code + rebuilt frontend. No .env, no database - your live
config and imported data are untouched. index.php clears stale compiled
caches and resets OPcache on first hit after upload, no Terminal needed.

Includes: media-upload fix (uploads no longer bake in a domain), the new
blog/about/security/terms/privacy/faq/contact content, search bar text fix,
the seasonal-tech-deals blog image fix, and the last leftover grocery-store
copy (sign-in/orders modal text, uncategorized-product label, offline
fallback data).

Code only — no .env, no database in this zip. If you're importing a fresh
database, use the corrected dump at backend/web_deploy/gdp (1).sql (fixes
the homepage banners/category tiles pointing at categories that no longer
exist after the electronics rebrand).

DEPLOY
  1. Back up: cPanel > File Manager, download public_html/gdp/nextech_demo/ .
  2. Upload this zip into public_html/gdp/ and Extract, overwrite when asked.
     (Writes into public_html/gdp/nextech_demo/ .)
  3. Open https://testcaresortwork.co.in/gdp/nextech_demo/ and hard-refresh
     (Ctrl+Shift+R).

Rollback: re-upload your public_html/gdp/nextech_demo/ backup.
TXT

echo "==> zipping"
if command -v zip >/dev/null 2>&1; then
  cd "$STAGE"
  zip -qr "$OUT" nextech_demo READ_ME_FIRST.txt -x '*/.DS_Store'
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
