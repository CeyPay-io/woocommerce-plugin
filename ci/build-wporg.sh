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

# Copy plugin content, excluding dot entries. A plain `cp -R "$SRC"/.` also
# drags in dot directories such as .playwright-mcp/ (Playwright console logs and
# page dumps), which must never reach the directory.
for ENTRY in "$SRC"/*; do
  [ -e "$ENTRY" ] || continue
  cp -R "$ENTRY" "$DEST"/
done

# --- Strip the optional analytics module -------------------------------------
#
# WordPress.org guideline 7 forbids collecting visitor data without explicit
# consent, and guideline 8 forbids loading third-party executable scripts.
# Removing these two files removes the GA4 loader, the Meta Pixel, the
# server-side Measurement Protocol calls, and the hardcoded GA4 credentials.
rm -f "$DEST/includes/class-ceypay-analytics.php"
rm -f "$DEST/assets/js/ceypay-analytics.js"

# Deleting the files is not enough -- the code that loads them has to go too.
# A conditional require of a file absent from the ZIP, and a wp_enqueue_script()
# for a script absent from the ZIP, read to a plugin reviewer as a payload
# loader (guideline 8). The settings field left behind also still advertised
# "Data is sent to Google Analytics", contradicting the readme (guideline 7).
#
# Every such touchpoint in the source is fenced with ceypay:analytics-start /
# ceypay:analytics-end. Drop the fenced regions, markers included.
for FILE in $(grep -rl 'ceypay:analytics-start' "$DEST" --include='*.php' --include='*.js'); do
  awk '
    /ceypay:analytics-start/ { skip = 1; next }
    /ceypay:analytics-end/   { skip = 0; next }
    !skip                    { print }
  ' "$FILE" > "$FILE.stripped"
  mv "$FILE.stripped" "$FILE"
done

# The translation template still carries the stripped settings copy, including
# the "Data is sent to Google Analytics" string. .pot records are blank-line
# separated, so filter whole records rather than lines.
POT="$DEST/languages/ceypay-payment-gateway.pot"
if [ -f "$POT" ]; then
  awk -v RS='' -v ORS='\n\n' \
    '!/Enable usage analytics|Google Analytics|msgid "Analytics"/' "$POT" > "$POT.pot-tmp"
  mv "$POT.pot-tmp" "$POT"
fi

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
