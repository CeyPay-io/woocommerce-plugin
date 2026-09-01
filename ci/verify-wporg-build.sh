#!/usr/bin/env bash
#
# Compliance guard for the WordPress.org distribution.
#
# Fails the build if anything that gets a plugin rejected survives the strip
# step. This is the backstop against the GitHub-release build and the .org build
# silently converging again.
#
# Usage: ci/verify-wporg-build.sh <build-dir>

set -uo pipefail

DEST="${1:?usage: verify-wporg-build.sh <build-dir>}"
FAILED=0

fail() {
  echo "FAIL: $1"
  # Surface as a GitHub Actions annotation when running in CI.
  if [ -n "${GITHUB_ACTIONS:-}" ]; then
    echo "::error::$1"
  fi
  FAILED=1
}

# Guideline 7 — no visitor tracking without consent.
#
# The hostname patterns alone were not enough: the analytics scaffolding
# (ceypay_has_analytics(), the ceypay-analytics script handle, the
# CeyPay_Analytics class, the ga_client_id order meta) contains none of them, so
# it passed every grep here while still shipping a settings field that read
# "Data is sent to Google Analytics". Match the scaffolding by name too.
for PATTERN in 'googletagmanager.com' 'google-analytics.com' \
               'connect.facebook.net' 'facebook.com/tr' \
               'fbq(' 'CEYPAY_GA4_' \
               'CeyPay_Analytics' 'ceypay_has_analytics' \
               'ceypay-analytics' 'ga_client_id' 'Google Analytics'; do
  if grep -rInF --exclude='*.pot' -- "$PATTERN" "$DEST" ; then
    fail "tracking reference '$PATTERN' found in WordPress.org build"
  fi
done

# The .pot is excluded above, since it legitimately carries every other UI
# string, but it must not reproduce the stripped analytics copy either.
POT="$DEST/languages/ceypay-payment-gateway.pot"
if [ -f "$POT" ] && grep -InF -e 'Google Analytics' -e 'Enable usage analytics' "$POT" ; then
  fail "analytics strings survive in the translation template"
fi

# Guideline 8 — no references to files absent from the ZIP. A conditional
# require or enqueue of a file that is not shipped is what reads as a payload
# loader; this is the general form of the analytics-scaffolding bug above.
for REF in $(grep -rhoE 'assets/js/[a-zA-Z0-9_./-]+\.js' "$DEST" --include='*.php' | sort -u); do
  if [ ! -f "$DEST/$REF" ]; then
    fail "PHP references '$REF', which is missing from the build"
  fi
done

# Development leftovers must never ship. Copying a directory's contents will
# otherwise include dot directories such as .playwright-mcp/.
if find "$DEST" -mindepth 1 -name '.*' -print | grep -q . ; then
  find "$DEST" -mindepth 1 -name '.*' -print
  fail "dot files/directories found in WordPress.org build"
fi

# Guideline 8 — no self-updating from outside the directory.
for PATTERN in 'plugin-update-checker' 'YahnisElsts' 'Puc_v'; do
  if grep -rInF -- "$PATTERN" "$DEST" ; then
    fail "update checker '$PATTERN' found in WordPress.org build"
  fi
done

# Guideline 8 — external assets must be bundled locally.
if grep -rIn 'fonts\.\(googleapis\|gstatic\)\.com' "$DEST" ; then
  fail "remote font reference found in WordPress.org build"
fi

# Guideline 15 — Stable tag must match the plugin header Version.
VERSION=$(grep -oE '^\s*\*\s*Version:\s*[0-9.]+' "$DEST/ceypay-payment-gateway.php" | grep -oE '[0-9.]+$' || true)
STABLE=$(grep -oE '^Stable tag:\s*[0-9.]+' "$DEST/readme.txt" | grep -oE '[0-9.]+$' || true)
if [ -z "$VERSION" ] || [ -z "$STABLE" ]; then
  fail "could not read Version ('$VERSION') or Stable tag ('$STABLE')"
elif [ "$VERSION" != "$STABLE" ]; then
  fail "Stable tag ($STABLE) does not match plugin Version ($VERSION)"
fi

# Guideline 12 — at most 5 tags.
TAGS=$(grep -E '^Tags:' "$DEST/readme.txt" | sed 's/^Tags://' || true)
TAG_COUNT=$(echo "$TAGS" | tr ',' '\n' | grep -c '[^[:space:]]' || true)
if [ "$TAG_COUNT" -gt 5 ]; then
  fail "readme.txt declares $TAG_COUNT tags (maximum is 5)"
fi

# Guideline 17 — terms banned at the trademark holder's request.
if grep -riE '^(Tags|===).*binance pay' "$DEST/readme.txt" ; then
  fail "'Binance Pay' is a banned term in the plugin name/tags"
fi

# Every referenced image must exist (deleting an asset must not leave a 404).
# The character class must include '/' so that subdirectories (assets/images/
# providers/, which holds the third-party exchange logos) are still covered --
# without it these references match nothing and go silently unchecked.
MISSING=0
for IMG in $(grep -rhoE 'images/[a-zA-Z0-9_./-]+\.(svg|png)' "$DEST" --include='*.php' --include='*.js' --include='*.css' | sed 's|^images/||' | sort -u); do
  if [ ! -f "$DEST/assets/images/$IMG" ]; then
    fail "referenced image '$IMG' is missing from the build"
    MISSING=1
  fi
done
[ "$MISSING" -eq 0 ] && echo "ok: all referenced images present"

# The strip step deletes code, so it can produce a file that no longer parses.
# It has done exactly that: a marker pair inside a docblock had the middle of
# the block removed, leaving an unterminated comment. Nothing else here would
# catch a fatal, so syntax-check what actually ships.
if command -v php >/dev/null 2>&1; then
  while IFS= read -r FILE; do
    php -l "$FILE" >/dev/null 2>&1 || fail "PHP syntax error in built $FILE"
  done < <(find "$DEST" -name '*.php' -print)
  echo "ok: PHP syntax valid"
else
  echo "warn: php not available, skipping syntax check"
fi

if command -v node >/dev/null 2>&1; then
  while IFS= read -r FILE; do
    node --check "$FILE" >/dev/null 2>&1 || fail "JS syntax error in built $FILE"
  done < <(find "$DEST" -name '*.js' -print)
  echo "ok: JS syntax valid"
else
  echo "warn: node not available, skipping syntax check"
fi

# Leftover fence markers mean a start/end pair was unbalanced and the strip
# silently did nothing (or too much).
if grep -rIn 'ceypay:analytics-' "$DEST" ; then
  fail "unstripped analytics fence markers found in WordPress.org build"
fi

if [ "$FAILED" -ne 0 ]; then
  echo "WordPress.org build failed compliance checks; refusing to deploy."
  exit 1
fi

echo "✅ WordPress.org build is clean"
