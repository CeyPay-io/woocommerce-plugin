#!/usr/bin/env bash
#
# Run the WordPress Playground integration tests against the WordPress.org
# build of the plugin.
#
# These exercise things static analysis cannot see: that the plugin actually
# boots against a real WordPress + WooCommerce, that the checkout script still
# enqueues after the analytics dependency is stripped, and that the AJAX
# ownership checks reject forged order keys.
#
# Usage: tests/run-playground-tests.sh
# Requires: node 20.18+, npx, curl, unzip

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK="${TMPDIR:-/tmp}/ceypay-playground-tests"
BUILD="$WORK/wporg-build"
WC_DIR="$WORK/wc"
WC_VERSION="${WC_VERSION:-11.0.0}"

mkdir -p "$WORK"

echo "==> Building WordPress.org distribution"
bash "$ROOT/ci/build-wporg.sh" "$ROOT/ceypay-payment-gateway" "$BUILD"

echo "==> Verifying build compliance"
bash "$ROOT/ci/verify-wporg-build.sh" "$BUILD" || exit 1

# WooCommerce is mounted from a local extract rather than installed via a
# wordpress.org resource, which pins the version and avoids flaky downloads.
WC_PLUGIN_DIR="$(bash "$ROOT/ci/fetch-woocommerce.sh" "$WORK" "$WC_VERSION")" || exit 1

FAILED=0

run_blueprint() {
  local name="$1" blueprint="$2" result_file="$3" expect="$4"

  echo
  echo "==> $name"
  rm -f "$BUILD/$result_file"

  npx --yes @wp-playground/cli@latest run-blueprint \
    --blueprint="$ROOT/tests/blueprints/$blueprint" \
    --mount-before-install="$BUILD:/wordpress/wp-content/plugins/ceypay-payment-gateway" \
    --mount-before-install="$WC_PLUGIN_DIR:/wordpress/wp-content/plugins/woocommerce" \
    > "$WORK/$name.log" 2>&1

  if [ ! -f "$BUILD/$result_file" ]; then
    echo "FAIL: $name produced no results (the PHP process died)"
    echo "----- Playground output -----"
    tail -30 "$WORK/$name.log"
    FAILED=1
    return
  fi

  cat "$BUILD/$result_file"

  if grep -q "$expect" "$BUILD/$result_file"; then
    echo "--> $name PASSED"
  else
    echo "--> $name FAILED"
    FAILED=1
  fi

  rm -f "$BUILD/$result_file"
}

run_blueprint "build-integrity" "smoke.json"          "SMOKE_RESULT.txt" "STATUS=ALL_PASS"
run_blueprint "ajax-ownership"  "ajax-ownership.json" "SMOKE_AJAX.txt"   "AJAX_STATUS=ALL_PASS"

echo
if [ "$FAILED" -ne 0 ]; then
  echo "❌ Playground tests failed"
  exit 1
fi
echo "✅ All Playground tests passed"
