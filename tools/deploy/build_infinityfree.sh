#!/usr/bin/env bash
# Builds a ready-to-upload copy of the app for InfinityFree (no SSH, no composer,
# no artisan on the server). Output: build/infinityfree/htdocs, uploaded as-is into htdocs.
#
#   htdocs/            what public/ holds (index.php, build/, css/, js/, images/...)
#   htdocs/core/       the rest of Laravel, closed to the web by core/.htaccess
#
# Usage (from the project root, Linux, macOS or Git Bash):  bash tools/deploy/build_infinityfree.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/build/infinityfree"
WORK="$OUT/work"
HTDOCS="$OUT/htdocs"

rm -rf "$OUT"
mkdir -p "$WORK" "$HTDOCS/core"

# Committed files only: no .env, no vendor, no local uploads.
git -C "$ROOT" archive HEAD | tar -x -C "$WORK"

cd "$WORK"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts
composer dump-autoload --optimize --no-dev --no-scripts
php artisan package:discover --ansi
npm ci --no-audit --no-fund
npm run build

# Docs, tests and fixtures inside vendor: InfinityFree limits the number of files.
find vendor -type d \( -iname tests -o -iname test -o -iname docs -o -iname doc -o -name .github -o -name .git \) -prune -exec rm -rf {} +
find vendor -type f \( -iname '*.md' -o -name 'phpunit.xml*' -o -name '.gitattributes' -o -name '.editorconfig' \) -delete

# Web root.
cp -R public/. "$HTDOCS/"
rm -rf "$HTDOCS/amer.php" "$HTDOCS/hot" "$HTDOCS/Python" "$HTDOCS/storage"
cp "$ROOT/tools/deploy/infinityfree/index.php" "$HTDOCS/index.php"

# Application, outside the public files.
for item in app bootstrap config database lang resources routes storage vendor artisan composer.json; do
    cp -R "$item" "$HTDOCS/core/"
done
rm -rf "$HTDOCS/core/resources/js" "$HTDOCS/core/resources/css"
rm -f "$HTDOCS/core/bootstrap/cache/"*.php
cp "$ROOT/tools/deploy/infinityfree/core.htaccess" "$HTDOCS/core/.htaccess"
cp "$ROOT/tools/deploy/infinityfree/env.example" "$HTDOCS/core/.env.example"

# Empty folders Laravel writes into (git does not keep them all).
mkdir -p "$HTDOCS/core/storage/app/public" "$HTDOCS/core/storage/framework/"{cache/data,sessions,views} "$HTDOCS/core/storage/logs"

rm -rf "$WORK"
cd "$OUT"
( cd htdocs && zip -qr ../nutrisyria-htdocs.zip . )

echo "Done: $HTDOCS"
echo "Files to upload: $(find "$HTDOCS" -type f | wc -l)"
