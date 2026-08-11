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

READY_FILE="$BUILD/PREVIEW_READY.txt"
ERROR_FILE="$BUILD/PREVIEW_ERROR.txt"
LOG="$WORK/preview-$PORT.log"
ATTEMPTS="${PREVIEW_ATTEMPTS:-3}"

# Two things go wrong here often enough to be worth handling rather than
# explaining. The Playground CLI downloads WordPress on boot and dies with
# "Error: fetch failed" when that download hiccups; and the setup step can die
# part-way, leaving a site that serves pages but has no products, no Store API
# and no usable checkout -- which looks like a plugin bug until you dig.
#
# So: boot, wait for the blueprint's own receipt (not just an open port), and
# start over if it never arrives. The receipt is written only after the
# blueprint has verified the store end to end.
SERVER_PID=""
cleanup() {
  if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" 2>/dev/null
    wait "$SERVER_PID" 2>/dev/null
  fi
}
trap 'cleanup; exit 130' INT TERM

attempt=1
while [ "$attempt" -le "$ATTEMPTS" ]; do
  rm -f "$READY_FILE" "$ERROR_FILE"

  if [ "$attempt" -gt 1 ]; then
    echo "==> Retrying preview boot (attempt $attempt of $ATTEMPTS)"
  fi

  npx --yes @wp-playground/cli@latest server \
    --port="$PORT" \
    --blueprint="$ROOT/tests/blueprints/preview.json" \
    --mount-before-install="$BUILD:/wordpress/wp-content/plugins/ceypay-payment-gateway" \
    --mount-before-install="$WC_PLUGIN_DIR:/wordpress/wp-content/plugins/woocommerce" \
    > "$LOG" 2>&1 &
  SERVER_PID=$!

  # 120s: a cold boot downloads WordPress and installs WooCommerce.
  ready=0
  for _ in $(seq 1 240); do
    if [ -f "$READY_FILE" ]; then
      ready=1
      break
    fi
    if [ -f "$ERROR_FILE" ]; then
      echo "==> Store setup failed: $(cat "$ERROR_FILE")"
      break
    fi
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
      echo "==> Playground exited before the store was ready:"
      tail -5 "$LOG"
      break
    fi
    sleep 0.5
  done

  if [ "$ready" -eq 1 ]; then
    break
  fi

  cleanup
  SERVER_PID=""
  attempt=$((attempt + 1))
done

if [ ! -f "$READY_FILE" ]; then
  echo
  echo "Preview failed to come up after $ATTEMPTS attempts. Full log: $LOG"
  exit 1
fi

cat <<BANNER

  CeyPay preview  ->  http://127.0.0.1:$PORT/shop/

    Shop .......... http://127.0.0.1:$PORT/shop/
    Settings ...... http://127.0.0.1:$PORT/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ceypay
    Plugins ....... http://127.0.0.1:$PORT/wp-admin/plugins.php

  Store verified: $(tr '\n' ' ' < "$READY_FILE")

  Auto-logged in as admin. Test mode is on, so the gateway uses the CeyPay
  sandbox and the modal offers "Simulate Success".

  Ctrl-C to stop. Nothing is persisted.

BANNER

wait "$SERVER_PID"
