=== CeyPay Payment Gateway ===
Contributors: ceypay, ceyloncash, kasuncfdo, xbuddhi
Tags: woocommerce, payment gateway, cryptocurrency, qr payment, checkout
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept crypto payments via Binance Pay, Bybit Pay and KuCoin Pay on your WooCommerce store with a seamless QR checkout.

== Description ==

**CeyPay Payment Gateway** allows your WooCommerce store to accept cryptocurrency payments effortlessly. We support major providers including **Binance Pay**, **Bybit Pay** and **KuCoin Pay**, letting your customers pay from their existing exchange balance.

The plugin features a polished, responsive checkout modal that keeps customers on your site while they scan the QR code or use deep links on mobile devices.

**[View Full Documentation](https://docs.ceypay.io/wordpress)**

### Key Features

*   **Multi-Provider Support:** Let customers choose between Binance Pay, Bybit Pay and KuCoin Pay at checkout, with more providers on the way.
*   **Seamless UX:** Provider selection and QR payment happen in a modal on your checkout page, so customers never leave your store.
*   **Mobile Optimized:** Automatically detects mobile devices and triggers deep links to open the relevant crypto app.
*   **Real-time Status:** Built-in polling and webhook support ensure orders are marked as "Processing" instantly upon payment.
*   **Developer Friendly:** Includes a robust Test Mode with a "Simulate Success" button for easy integration testing.
*   **Dark Mode Compatible:** The UI adapts beautifully to dark-themed websites.

### Security

*   Secure server-to-server communication.
*   Webhook signature verification to prevent spoofing.
*   Nonce verification for all AJAX requests.

== Installation ==

1.  **Download** the plugin zip file.
2.  Go to your WordPress Admin Dashboard: **Plugins > Add New**.
3.  Click **Upload Plugin** and select the `ceypay-payment-gateway.zip` file.
4.  Click **Install Now** and then **Activate**.
5.  Navigate to **WooCommerce > Settings > Payments**.
6.  Enable **CeyPay** and click **Manage**.
7.  Enter your **Merchant ID** (provided by CeyPay).
8.  (Optional) Enable **Test Mode** for development.

== Frequently Asked Questions ==

= Do I need a CeyPay merchant account? =
Yes, you need a valid Merchant ID from CeyPay to process live payments.

= Which cryptocurrencies are supported? =
Support depends on the exchange (Binance, Bybit, KuCoin). Generally, major coins like USDT, BTC, and ETH are supported.

= Does this plugin store private keys? =
No! This plugin only facilitates the payment request. All crypto transactions happen securely within the provider's app or platform.

= What happens if a customer closes the popup? =
The order remains "Pending Payment". If they paid before closing, our webhook handler will still update the order status in the background.

= Where can I find detailed documentation? =
Visit our full documentation at [docs.ceypay.io](https://docs.ceypay.io/wordpress) for setup guides, troubleshooting, and API reference.

== Screenshots ==

1.  **Provider selection:** Customers choose Bybit Pay, Binance Pay or KuCoin Pay without leaving the checkout page.
2.  **QR checkout:** The payment modal shows the amount in USDT and updates as the payment is confirmed.
3.  **On mobile:** The modal opens as a bottom drawer, within reach of your thumb.
4.  **Settings:** Enable the gateway, switch on test mode and enter your Merchant ID in WooCommerce settings.

== External Services ==

This plugin connects to one external service, required to process payments. It does not load third-party fonts or scripts, and it does not collect analytics.

= CeyPay Payment API (Required) =

When a customer initiates a payment, this plugin sends order information to the CeyPay payment processing API to generate QR codes and process transactions. This is required for the plugin to function.

* Service provider: CeyPay
* Service URL: https://api.ceypay.io/ (production) and https://sandbox-api.ceypay.io/ (test mode)
* Privacy policy: https://ceypay.io/privacy
* Terms of service: https://ceypay.io/legal/terms
* Data sent: Merchant ID, order number, order total and currency, the name of each item in the order, and the customer's billing details (first and last name, email address, phone number, street address, city, postal code and country)
* When: Every time a customer selects a payment provider at checkout, and when the resulting payment status is polled
* Also sent: The store's webhook callback URL, so CeyPay can notify the site when payment completes

The payment modal displays the notice "By continuing, you agree to our Terms of Service & Privacy Policy", linking to the two documents listed above. These are the terms governing the payment the customer is about to make through CeyPay, and the notice appears only inside the modal, after the customer has chosen CeyPay at checkout. It is a disclosure required for the transaction, not promotion of the service. The optional "Powered by CeyPay" credit is separate: it is off by default and shown only if the store owner enables it in the gateway settings.

== Privacy Policy ==

For a summary of how this plugin handles user data, please refer to the "External Services" section above.

== Changelog ==

= 2026-8-24 - v1.3.3 =
* documents the payment modal's Terms of Service and Privacy Policy notice under External Services
* removes unused tracking code from the distributed build

= 2026-8-11 - v1.3.2 =
* adds full translation support for the checkout modal
* adds a Settings link on the Plugins screen
* the checkout modal now opens as a bottom drawer on phones, within reach of your thumb
* the provider list now scrolls inside the modal instead of stretching it, restoring the modal's original proportions on desktop
* the support address in the modal is now a working email link, and the help bubble stays open long enough to use it, including on touch screens
* fix: payment status checks now use the dedicated status endpoint and validate the response code, so failed checks no longer leave orders stuck
* fix: the modal no longer overflows the screen on a phone held sideways
* fix: the help bubble no longer opens partly off the edge of narrow screens

= 2026-8-10 - v1.3.1 =
* security: checkout endpoints now verify the order key, so order details can no longer be read and payment can no longer be confirmed by guessing an order ID
* security: payment confirmation uses the transaction recorded against the order instead of a value supplied by the browser
* security: refund IDs are rejected instead of triggering a server error
* security: test-mode payment simulation now requires an administrator
* fix: fatal error that could take down stores using the Blocks checkout
* fix: JavaScript error on every QR code display
* fix: WooCommerce "unregistered script" warnings caused by unnormalised asset URLs
* smoother modal resizing between provider selection and QR display
* the setup notice is now dismissible
* removes unused debug code
* tested with WordPress 7.0 and WooCommerce 11.0

= 2026-2-24 - v1.3.0 =
* adds branding toggle to show "Powered by CeyPay" in the checkout modal
* adds error reporting and in-memory storage for debugging
* improves AJAX handling to prevent "Leave site?" prompts

= 2026-1-29 - v1.2.9 =
* enhances tooltips and adds versioning support

= 2026-1-28 - v1.2.8 =
* enhances sandbox mode with improved test mode badge styles
* updates plugin metadata and banners

= 2026-1-27 - v1.2.7 =
* adds test mode badges and alerts to checkout and payment modal

= 2026-1-26 - v1.2.5 =
* records the payment reference in the native WooCommerce transaction ID field
* adds HPOS compatibility declaration

= 2026-1-26 - v1.2.4 =
* adds High-Performance Order Storage (HPOS) compatibility
* improves plugin metadata and banner information

= 2026-1-26 - v1.2.3 =
* initial stable release with production-ready features
* enhances provider selection and checkout experience

= 2026-1-26 - v1.2.1 =
* adds KuCoin Pay as a coming soon provider in the modal and classic block integration
* redesigns the classic block provider chips to a minimal, pill-style layout

= 2026-1-26 - v1.0.3 =
* fixes direct file access protection format for Plugin Check compliance

= 2026-1-26 - v1.0.2 =
* fixes direct file access protection placement for Plugin Check compliance

= 2026-1-26 - v1.0.1 =
* improves security with proper input sanitization and escaping
* adds WordPress coding standards compliance
* fixes internationalization with translator comments

= 2026-1-26 - v1.0.0 =
* initial release
* adds support for Binance Pay, Bybit Pay and Bitazza
* implements QR code modal with polling and webhooks
* adds Test Mode with simulation capabilities
