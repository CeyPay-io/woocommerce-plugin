# Tests

Integration tests that run the **WordPress.org build** of the plugin against a
real WordPress + WooCommerce install using [WordPress Playground](https://wordpress.github.io/wordpress-playground/).

These exist because the two builds differ. `ci/build-wporg.sh` strips the
analytics module and the remote font import, and static checks (`php -l`,
`node --check`, grep) cannot tell you whether the result still *works*.

## This is not a replacement for `docker-compose.yml`

The two environments test **different builds** and neither supersedes the other.
Do not delete one assuming the other covers it.

| | `docker compose up` | `tests/run-playground-tests.sh` |
|---|---|---|
| Runs | the **source** tree — the GitHub build, analytics module included | the **stripped WordPress.org build** |
| State | persistent volumes | disposable, clean every run |
| Stack | real MySQL 8 + PHP + Apache | PHP-WASM + SQLite |
| Mock API | `mockapi` service on :5000, wired up automatically | not available |
| Purpose | day-to-day development and debugging | verifying the artifact that ships, and CI |

Playground deliberately cannot exercise the analytics module, because that code
is not in the build it tests. Use Docker for that.

## Running locally

```bash
bash tests/run-playground-tests.sh
```

Requires Node 20.18+, `npx`, `curl` and `unzip`. WooCommerce is downloaded once
and cached in `$TMPDIR/ceypay-playground-tests`. Pin a different version with
`WC_VERSION=10.9.0 bash tests/run-playground-tests.sh`.

## What is covered

`blueprints/smoke.json` — build integrity:

- The gateway registers, is available at checkout, and reports as configured.
- The analytics class, both GA4 constants, and the analytics setting are absent.
- **The checkout script still enqueues** after `ceypay-analytics` was removed
  from its dependency array. An unmet dependency silently prevents
  `ceypay-checkout.js` from loading, which would break the payment modal with no
  error anywhere.
- **The Blocks integration constructs without a fatal.** `get_setting()` must
  not narrow the visibility it inherits from `AbstractPaymentMethodType`;
  declaring it `private` is a PHP fatal that white-screens the whole site as
  soon as WooCommerce Blocks loads. This shipped in v1.3.0 and was only caught
  by booting the plugin for real.
- Unreleased providers are absent from the Blocks icon list.

`blueprints/ajax-ownership.json` — the AJAX authorization boundary:

Exercises `WC_Gateway_CeyPay::get_verified_order()` against six cases. Only a
valid order key may resolve; a forged key, a missing key, a nonexistent order
ID, an empty order ID, and **another order's key** must all be rejected. That
last case is the one that proves order IDs cannot be enumerated to read billing
details or confirm payment on someone else's order.

## Writing new cases

Two Playground quirks to know:

1. `runPHP` stdout does not surface through `run-blueprint`. Write results to a
   file inside the mounted plugin directory and read it from the host.
2. `wp_send_json_*()` calls a bare `die` outside an AJAX context, killing the
   PHP process instead of raising anything catchable. Add
   `add_filter('wp_doing_ajax', '__return_true')` first — that is also the real
   code path — then filter `wp_die_handler` to throw.
