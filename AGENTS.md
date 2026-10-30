# geniusbrain-media-webp-compressor

GeniusBrain - Media WebP Compressor WordPress eklentisinin kodu. Üst ürün: [../AGENTS.md](../AGENTS.md).

## Dokümanlar (tembel)
| İhtiyaç | Doküman |
|---|---|
| Ürün davranışı | [../docs/domain-image-conversion.md](../docs/domain-image-conversion.md) |

## Kurallar
- WordPress Coding Standards; tüm çıktı escape edilir, girdiler sanitize edilir, admin işlemleri nonce + `manage_options` ile korunur.
- Metinler çevrilebilir (`geniusbrain-media-webp-compressor` text domain); kod ve yorumlar İngilizce.
- Dış HTTP isteği, izleme veya ücretli özellik kilidi yok (WordPress.org kuralları).

## Düzen
`geniusbrain-media-webp-compressor.php` (başlık + hook'lar), `includes/` (Settings, Converter, Content, Admin), `assets/` (ayarlar sayfası JS/CSS), `uninstall.php`, `readme.txt` (WordPress.org). `dev/` ve `bin/` pakete girmez (`.distignore`).

## Komutlar
- Yerel WordPress 7.1.4 (http://localhost:4230, `dev/mu-plugins` ile otomatik admin girişi; şifre yazılmaz): `docker compose -f dev/docker-compose.yml up -d`, durdurma `... down` (verilerle birlikte silme `down -v`).
- WP-CLI: `docker compose -f dev/docker-compose.yml run --rm cli wp <komut>`; ilk kurulum `wp core install --url=http://localhost:4230 ...` sonra `wp plugin activate geniusbrain-media-webp-compressor`.
- Sözdizimi: `docker compose -f dev/docker-compose.yml exec -T wordpress php -l wp-content/plugins/geniusbrain-media-webp-compressor/<dosya>`.
- Plugin Check (WordPress.org incelemesinin aracı): `... run --rm cli wp plugin check geniusbrain-media-webp-compressor --exclude-directories=dev,bin,dist --exclude-files=AGENTS.md,CLAUDE.md,.distignore,.gitignore`.
- Paket: `bin/build-zip.sh` → `dist/geniusbrain-media-webp-compressor-<sürüm>.zip` (sürüm eklenti başlığından; `readme.txt` Stable tag aynı tutulur).
