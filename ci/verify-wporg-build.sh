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
for PATTERN in 'googletagmanager.com' 'google-analytics.com' \
               'connect.facebook.net' 'facebook.com/tr' \
               'fbq(' 'CEYPAY_GA4_'; do
  if grep -rInF --exclude='*.pot' -- "$PATTERN" "$DEST" ; then
    fail "tracking reference '$PATTERN' found in WordPress.org build"
  fi
done

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
MISSING=0
for IMG in $(grep -rhoE 'images/[a-zA-Z0-9_.-]+\.(svg|png)' "$DEST" --include='*.php' --include='*.js' --include='*.css' | sed 's|images/||' | sort -u); do
  if [ ! -f "$DEST/assets/images/$IMG" ]; then
    fail "referenced image '$IMG' is missing from the build"
    MISSING=1
  fi
done
[ "$MISSING" -eq 0 ] && echo "ok: all referenced images present"

if [ "$FAILED" -ne 0 ]; then
  echo "WordPress.org build failed compliance checks; refusing to deploy."
  exit 1
fi

echo "✅ WordPress.org build is clean"
