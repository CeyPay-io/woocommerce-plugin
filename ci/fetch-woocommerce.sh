#!/usr/bin/env bash
#
# Fetch and extract a pinned WooCommerce release for Playground mounting.
#
# Downloads are verified before use. A truncated download that still produced a
# readable woocommerce.php once caused WooCommerce to fatal on activation with a
# missing src/Autoloader.php, which surfaced as an unrelated-looking blueprint
# failure -- so the cache check validates the extract, not just its entry point.
#
# Usage: ci/fetch-woocommerce.sh <dest-dir> [version]
# Echoes the path to the extracted woocommerce/ directory.

set -uo pipefail

DEST="${1:?usage: fetch-woocommerce.sh <dest-dir> [version]}"
VERSION="${2:-${WC_VERSION:-11.0.0}}"

ZIP="$DEST/woocommerce-${VERSION}.zip"
WC_DIR="$DEST/wc"
PLUGIN_DIR="$WC_DIR/woocommerce"

# Files that must exist for the extract to be considered usable.
extract_is_valid() {
  [ -f "$PLUGIN_DIR/woocommerce.php" ] &&
  [ -f "$PLUGIN_DIR/src/Autoloader.php" ] &&
  [ -d "$PLUGIN_DIR/includes" ] &&
  [ -d "$PLUGIN_DIR/packages" ]
}

mkdir -p "$DEST"

if extract_is_valid; then
  echo "$PLUGIN_DIR"
  exit 0
fi

echo "==> Fetching WooCommerce $VERSION" >&2
rm -rf "$WC_DIR" "$ZIP"

for ATTEMPT in 1 2 3; do
  if curl -fsSL --retry 3 --retry-delay 2 --max-time 300 \
       -o "$ZIP" \
       "https://downloads.wordpress.org/plugin/woocommerce.${VERSION}.zip"; then
    # A truncated zip often still extracts partially; test it first.
    if unzip -tqq "$ZIP" >/dev/null 2>&1; then
      rm -rf "$WC_DIR"; mkdir -p "$WC_DIR"
      unzip -qo "$ZIP" -d "$WC_DIR"
      if extract_is_valid; then
        echo "$PLUGIN_DIR"
        exit 0
      fi
      echo "    extract incomplete (attempt $ATTEMPT)" >&2
    else
      echo "    zip failed integrity check (attempt $ATTEMPT)" >&2
    fi
  else
    echo "    download failed (attempt $ATTEMPT)" >&2
  fi
  rm -f "$ZIP"
  sleep 3
done

echo "error: could not obtain a valid WooCommerce $VERSION after 3 attempts" >&2
exit 1
