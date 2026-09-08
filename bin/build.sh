#!/usr/bin/env bash
# no404 WordPress plugin — builds the distribution package.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="$(grep -m1 "^ \* Version:" no404.php | sed 's/.*Version: *//' | tr -d '[:space:]')"
if [ -z "$VERSION" ]; then
  echo "Could not read the version (no404.php header)." >&2
  exit 1
fi

# If readme.txt's Stable tag disagrees with the plugin header, WordPress.org
# publishes the wrong version.
STABLE="$(grep -m1 "^Stable tag:" readme.txt | sed 's/.*Stable tag: *//' | tr -d '[:space:]')"
if [ "$VERSION" != "$STABLE" ]; then
  echo "Mismatch: no404.php Version=$VERSION, readme.txt Stable tag=$STABLE" >&2
  exit 1
fi

# The WordPress.org slug is derived from the plugin NAME, and the text domain
# MUST match it. If they diverge, translate.wordpress.org never loads a
# translation — and it does so silently. Hence this check before packaging.
NAME="$(grep -m1 "^ \* Plugin Name:" no404.php | sed 's/.*Plugin Name: *//' | sed 's/[[:space:]]*$//')"
DOMAIN="$(grep -m1 "^ \* Text Domain:" no404.php | sed 's/.*Text Domain: *//' | tr -d '[:space:]')"
SLUG="$(printf '%s' "$NAME" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '-' | tr -s '-' | sed 's/^-//;s/-$//')"

if [ "$DOMAIN" != "$SLUG" ]; then
  echo "Text domain does not match the slug derived from the plugin name:" >&2
  echo "  Plugin Name : $NAME" >&2
  echo "  expected slug: $SLUG" >&2
  echo "  Text Domain : $DOMAIN" >&2
  exit 1
fi

# The WordPress.org review REJECTS "Plugin URI is the same as Author URI":
# the first describes this plugin, the second its author. Neither is mandatory,
# but they cannot carry the same value. This check prevents that rejection.
PLUGIN_URI="$(grep -m1 "^ \* Plugin URI:" no404.php | sed 's/.*Plugin URI: *//' | tr -d '[:space:]' || true)"
AUTHOR_URI="$(grep -m1 "^ \* Author URI:" no404.php | sed 's/.*Author URI: *//' | tr -d '[:space:]' || true)"

if [ -n "$PLUGIN_URI" ] && [ "$PLUGIN_URI" = "$AUTHOR_URI" ]; then
  echo "Plugin URI and Author URI are identical: $PLUGIN_URI" >&2
  echo "They must differ; change one of them or drop one from the header." >&2
  exit 1
fi

# The supported locales are read from ONE place (bin/locales.php). Keeping a
# hand-written list here would silently drop a new locale's .mo from the package.
LOCALES="$(php -r 'foreach ( array_keys( require "bin/locales.php" ) as $l ) { echo $l, "
"; }')"
if [ -z "$LOCALES" ]; then
  echo "bin/locales.php returned no locales." >&2
  exit 1
fi

REQUIRED="languages/$DOMAIN.pot"
for loc in $LOCALES; do
  REQUIRED="$REQUIRED languages/$DOMAIN-$loc.po languages/$DOMAIN-$loc.mo"
done

for f in $REQUIRED; do
  if [ ! -f "$f" ]; then
    echo "Missing translation catalogue: $f (run: php bin/i18n.php)" >&2
    exit 1
  fi
done

echo "Running tests…"
php tests/test-core.php > /dev/null
php tests/test-wordpress.php > /dev/null

echo "Rebuilding translation catalogues…"
php bin/i18n.php > /dev/null

echo "Checking PHP syntax…"
find . -name "*.php" -not -path "./dist/*" -print0 | xargs -0 -n1 php -l > /dev/null

rm -rf dist
mkdir -p "dist/$SLUG"

# The package root folder must BE the slug: once installed the plugin directory
# takes that name, and WordPress looks for translations at
# `WP_LANG_DIR/plugins/<slug>-<locale>.mo`. If the folder name diverges from the
# slug, wp.org translations and updates will not match.
# Only runtime files are packaged.
cp no404.php uninstall.php readme.txt "dist/$SLUG/"
cp -r includes assets languages "dist/$SLUG/"

php bin/zip.php "dist/$SLUG" "dist/$SLUG-$VERSION.zip" "$SLUG"
rm -rf "dist/$SLUG"

echo "Ready: dist/$SLUG-$VERSION.zip"
