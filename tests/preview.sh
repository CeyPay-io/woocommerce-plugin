#!/usr/bin/env bash
#
# Boot the WordPress.org build of the plugin in a disposable WordPress +
# WooCommerce site for manual inspection.
#
# This serves the SAME stripped build that ships to WordPress.org, so what you
# click through is what merchants get -- no analytics module, no remote fonts.
#
# Usage: tests/preview.sh [port]
# Then open the printed URL. Ctrl-C to stop; nothing is persisted.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${1:-9400}"
WORK="${TMPDIR:-/tmp}/ceypay-playground-tests"
BUILD="$WORK/preview-build"
WC_DIR="$WORK/wc"
WC_VERSION="${WC_VERSION:-11.0.0}"

mkdir -p "$WORK"

echo "==> Building WordPress.org distribution"
bash "$ROOT/ci/build-wporg.sh" "$ROOT/ceypay-payment-gateway" "$BUILD"

WC_PLUGIN_DIR="$(bash "$ROOT/ci/fetch-woocommerce.sh" "$WORK" "$WC_VERSION")" || exit 1

cat <<BANNER

  CeyPay preview  ->  http://127.0.0.1:$PORT/shop/

    Shop .......... http://127.0.0.1:$PORT/shop/
    Settings ...... http://127.0.0.1:$PORT/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ceypay
    Plugins ....... http://127.0.0.1:$PORT/wp-admin/plugins.php

  Auto-logged in as admin. Test mode is on, so the gateway uses the CeyPay
  sandbox and the modal offers "Simulate Success".

  Ctrl-C to stop. Nothing is persisted.

BANNER

exec npx --yes @wp-playground/cli@latest server \
  --port="$PORT" \
  --blueprint="$ROOT/tests/blueprints/preview.json" \
  --mount-before-install="$BUILD:/wordpress/wp-content/plugins/ceypay-payment-gateway" \
  --mount-before-install="$WC_PLUGIN_DIR:/wordpress/wp-content/plugins/woocommerce"
