#!/usr/bin/env bash
# no404 WordPress eklentisi — dağıtım paketi üretir.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="$(grep -m1 "^ \* Version:" no404.php | sed 's/.*Version: *//' | tr -d '[:space:]')"
if [ -z "$VERSION" ]; then
  echo "Sürüm okunamadı (no404.php başlığı)." >&2
  exit 1
fi

# readme.txt Stable tag ile eklenti başlığı uyuşmazsa WordPress.org sürümü yanlış yayımlar.
STABLE="$(grep -m1 "^Stable tag:" readme.txt | sed 's/.*Stable tag: *//' | tr -d '[:space:]')"
if [ "$VERSION" != "$STABLE" ]; then
  echo "Uyuşmazlık: no404.php Version=$VERSION, readme.txt Stable tag=$STABLE" >&2
  exit 1
fi

# WordPress.org slug'ı eklenti ADINDAN türetilir ve text domain ONUNLA aynı
# olmak zorunda; ayrışırsa translate.wordpress.org çevirileri hiç yüklemez ve
# bu sessizce olur. Bu yüzden paketlemeden önce denetliyoruz.
NAME="$(grep -m1 "^ \* Plugin Name:" no404.php | sed 's/.*Plugin Name: *//' | sed 's/[[:space:]]*$//')"
DOMAIN="$(grep -m1 "^ \* Text Domain:" no404.php | sed 's/.*Text Domain: *//' | tr -d '[:space:]')"
SLUG="$(printf '%s' "$NAME" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '-' | tr -s '-' | sed 's/^-//;s/-$//')"

if [ "$DOMAIN" != "$SLUG" ]; then
  echo "Text domain, eklenti adından türeyen slug ile uyuşmuyor:" >&2
  echo "  Plugin Name : $NAME" >&2
  echo "  beklenen slug: $SLUG" >&2
  echo "  Text Domain : $DOMAIN" >&2
  exit 1
fi

# WordPress.org incelemesi "Plugin URI ile Author URI ayni" diye REDDEDER:
# ilki bu eklentiyi, ikincisi yazari anlatan sayfadir. Ikisi de zorunlu degil,
# ama ayni degeri tasiyamazlar. Bu denetim o red turunu bir daha yasatmaz.
PLUGIN_URI="$(grep -m1 "^ \* Plugin URI:" no404.php | sed 's/.*Plugin URI: *//' | tr -d '[:space:]' || true)"
AUTHOR_URI="$(grep -m1 "^ \* Author URI:" no404.php | sed 's/.*Author URI: *//' | tr -d '[:space:]' || true)"

if [ -n "$PLUGIN_URI" ] && [ "$PLUGIN_URI" = "$AUTHOR_URI" ]; then
  echo "Plugin URI ile Author URI ayni: $PLUGIN_URI" >&2
  echo "Ikisi farkli olmali; ya birini degistirin ya da birini basliktan silin." >&2
  exit 1
fi

# Desteklenen diller TEK yerden okunur (bin/locales.php); burada elle liste
# tutmak, dil eklendiğinde .mo'yu paketten sessizce düşürür.
LOCALES="$(php -r 'foreach ( array_keys( require "bin/locales.php" ) as $l ) { echo $l, "
"; }')"
if [ -z "$LOCALES" ]; then
  echo "bin/locales.php hiç dil döndürmedi." >&2
  exit 1
fi

REQUIRED="languages/$DOMAIN.pot"
for loc in $LOCALES; do
  REQUIRED="$REQUIRED languages/$DOMAIN-$loc.po languages/$DOMAIN-$loc.mo"
done

for f in $REQUIRED; do
  if [ ! -f "$f" ]; then
    echo "Çeviri katalogu eksik: $f (php bin/i18n.php)" >&2
    exit 1
  fi
done

echo "Testler çalıştırılıyor…"
php tests/test-core.php > /dev/null
php tests/test-wordpress.php > /dev/null

echo "Çeviri katalogları güncelleniyor…"
php bin/i18n.php > /dev/null

echo "PHP sözdizimi denetimi…"
find . -name "*.php" -not -path "./dist/*" -print0 | xargs -0 -n1 php -l > /dev/null

rm -rf dist
mkdir -p "dist/$SLUG"

# Paket kök klasörü SLUG olmalı: kurulunca eklenti dizini bu ada sahip olur ve
# WordPress çevirileri `WP_LANG_DIR/plugins/<slug>-<locale>.mo` yolunda arar.
# Klasör adı slug'dan saparsa wp.org çevirileri ve güncellemeleri eşleşmez.
# Yalnızca çalışma zamanı dosyaları paketlenir.
cp no404.php uninstall.php readme.txt "dist/$SLUG/"
cp -r includes assets languages "dist/$SLUG/"

php bin/zip.php "dist/$SLUG" "dist/$SLUG-$VERSION.zip" "$SLUG"
rm -rf "dist/$SLUG"

echo "Hazır: dist/$SLUG-$VERSION.zip"
