=== CeyPay Payment Gateway ===
Contributors: CeyPay
Tags: woocommerce, payment gateway, crypto, bybit pay, binance pay
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept crypto payments via Binance Pay, Bybit, and Bitazza on your WooCommerce store with a seamless QR checkout.

== Description ==

**CeyPay Payment Gateway** allows your WooCommerce store to accept cryptocurrency payments effortlessly. We support major providers including **Binance Pay**, **Bybit**, and **Bitazza**, offering your customers a flexible and modern payment experience.

The plugin features a polished, responsive checkout modal that keeps customers on your site while they scan the QR code or use deep links on mobile devices.

**[View Full Documentation](https://docs.ceypay.io/)**

### Key Features

*   **Multi-Provider Support:** Let customers choose between Binance Pay, Bybit, or Bitazza at checkout.
*   **Seamless UX:** A modern, card-grid style provider selection and a clean, modal-based QR checkout.
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
7.  Enter your **Merchant ID** and **Webhook Secret** (provided by CeyPay).
8.  (Optional) Enable **Test Mode** for development.

== Frequently Asked Questions ==

= Do I need a CeyPay merchant account? =
Yes, you need a valid Merchant ID from CeyPay to process live payments.

= Which cryptocurrencies are supported? =
Support depends on the provider (Binance, Bybit, Bitazza). Generally, major coins like USDT, BTC, and ETH are supported.

= Does this plugin store private keys? =
No! This plugin only facilitates the payment request. All crypto transactions happen securely within the provider's app or platform.

= What happens if a customer closes the popup? =
The order remains "Pending Payment". If they paid before closing, our webhook handler will still update the order status in the background.

= Where can I find detailed documentation? =
Visit our full documentation at [docs.ceypay.io](https://docs.ceypay.io/) for setup guides, troubleshooting, and API reference.

== Screenshots ==

1.  **Checkout Selection:** Modern card-grid layout for selecting payment providers.
2.  **Payment Modal:** Clean QR code display with status indicator.
3.  **Mobile Experience:** "Open App" button for seamless mobile payments.
4.  **Settings Panel:** Easy configuration in WooCommerce settings.

== Privacy Policy ==

This plugin connects to external services to process payments and optionally collect anonymous usage analytics.

= CeyPay API =

When a customer initiates a payment, this plugin sends order information (amount, currency, order ID) to the CeyPay payment processing API to generate QR codes and process transactions. This is required for the plugin to function.

* Service provider: CeyPay
* Privacy policy: https://ceypay.io/privacy
* Data sent: Order total, currency, merchant ID, transaction status

= Google Analytics (Optional) =

If you enable the "Usage Analytics" option in settings, this plugin sends anonymous payment flow events to Google Analytics to help improve the plugin. This is **disabled by default** and requires explicit opt-in.

* Service provider: Google LLC
* Privacy policy: https://policies.google.com/privacy
* Data sent: Anonymous events (e.g., provider selection, payment completion rates)
* No personal customer data or transaction amounts are collected

To disable analytics, go to WooCommerce > Settings > Payments > CeyPay and uncheck "Enable usage analytics".

== Changelog ==

= 1.2.1 =
*   Added KuCoin Pay as a coming soon provider in the modal and classic block integration.
*   Redesigned the classic block provider chips to a minimal, pill-style layout for consistency.

= 1.0.3 =
*   Fixed direct file access protection format for Plugin Check compliance.

= 1.0.2 =
*   Fixed direct file access protection placement for Plugin Check compliance.

= 1.0.1 =
*   Improved security with proper input sanitization and escaping.
*   Added WordPress coding standards compliance.
*   Fixed internationalization with translator comments.

= 1.0.0 =
*   Initial release.
*   Added support for Binance Pay, Bybit, and Bitazza.
*   Implemented QR code modal with polling and webhooks.
*   Added Test Mode with simulation capabilities.
