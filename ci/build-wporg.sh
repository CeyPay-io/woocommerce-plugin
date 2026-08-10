#!/usr/bin/env bash
#
# Produce the WordPress.org distribution of the plugin.
#
# The GitHub release build and the WordPress.org build are NOT the same. This
# script is the single definition of the difference, so the deploy workflow and
# the test suite can never drift apart. Both call it.
#
# Usage: ci/build-wporg.sh <source-plugin-dir> <output-dir>

set -euo pipefail

SRC="${1:?usage: build-wporg.sh <source-plugin-dir> <output-dir>}"
DEST="${2:?usage: build-wporg.sh <source-plugin-dir> <output-dir>}"

if [ ! -f "$SRC/ceypay-payment-gateway.php" ]; then
  echo "error: '$SRC' is not the plugin directory" >&2
  exit 1
fi

rm -rf "$DEST"
mkdir -p "$DEST"
cp -R "$SRC"/. "$DEST"/

# --- Strip the optional analytics module -------------------------------------
#
# WordPress.org guideline 7 forbids collecting visitor data without explicit
# consent, and guideline 8 forbids loading third-party executable scripts.
# Removing these two files removes the GA4 loader, the Meta Pixel, the
# server-side Measurement Protocol calls, and the hardcoded GA4 credentials.
#
# The plugin gates every analytics touchpoint on ceypay_has_analytics(), and
# ceypay-checkout.js guards every window.CeyPayAnalytics call, so the build
# stays fully functional without them. tests/blueprints/smoke.json asserts this.
rm -f "$DEST/includes/class-ceypay-analytics.php"
rm -f "$DEST/assets/js/ceypay-analytics.js"

# --- Drop the remote Google Fonts import -------------------------------------
#
# Guideline 8 permits font CDNs, but loading them exposes visitor IPs to a third
# party, so the .org build falls back to the system font stack already declared
# in --ceypay-font.
# TODO: bundle Lato as local woff2 to restore the intended typography.
CSS="$DEST/assets/css/ceypay.css"
if [ -f "$CSS" ]; then
  # Portable in-place edit (GNU and BSD sed differ on -i).
  grep -v "@import url('https://fonts.googleapis.com" "$CSS" > "$CSS.tmp"
  mv "$CSS.tmp" "$CSS"
fi

# Never ship development leftovers.
find "$DEST" -name '.DS_Store' -delete
find "$DEST" -name '*.tmp' -delete

echo "Built WordPress.org distribution at $DEST"
