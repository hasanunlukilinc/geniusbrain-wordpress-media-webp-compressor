#!/usr/bin/env bash
# Builds dist/geniusbrain-media-webp-compressor-<version>.zip without the files listed in .distignore.
set -euo pipefail
cd "$(dirname "$0")/.."
version=$(sed -n 's/^ \* Version: *//p' geniusbrain-media-webp-compressor.php)
rm -rf dist && mkdir -p dist/geniusbrain-media-webp-compressor
rsync -a --exclude-from=.distignore ./ dist/geniusbrain-media-webp-compressor/
(cd dist && zip -qr "geniusbrain-media-webp-compressor-${version}.zip" geniusbrain-media-webp-compressor)
echo "dist/geniusbrain-media-webp-compressor-${version}.zip"
