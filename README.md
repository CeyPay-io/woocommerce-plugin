# CeyPay Payment Gateway for WooCommerce

Accept cryptocurrency payments via Binance Pay, Bybit, and Bitazza on your WooCommerce store.

[![License](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple.svg)](https://woocommerce.com/)

## Features

- **Multi-Provider Support:** Binance Pay, Bybit, Bitazza
- **Modern Checkout:** Card-grid provider selection with QR modal
- **Mobile Optimized:** Deep links for crypto wallet apps
- **Real-time Updates:** Webhook and polling support
- **Test Mode:** Built-in testing with payment simulation
- **Dark Mode:** Responsive UI with dark-mode support

## Installation

### From GitHub Releases (Recommended)

1. Download the latest `ceypay-payment-gateway.zip` from [Releases](https://github.com/CeyPay-io/woocommerce-plugin/releases)
2. In WordPress Admin: **Plugins > Add New > Upload Plugin**
3. Upload the zip file and activate

### From WordPress.org

1. Search for "CeyPay Payment Gateway" in **Plugins > Add New**
2. Click **Install Now** and **Activate**

## Configuration

1. Navigate to **WooCommerce > Settings > Payments**
2. Enable **CeyPay** and click **Manage**
3. Enter your **Merchant ID** from [CeyPay Dashboard](https://ceypay.io)
4. Configure your **Webhook Secret** for real-time payment confirmations

## Testing

The plugin includes a Test Mode for testing without real transactions:

1. Enable **Test Mode** in WooCommerce > Settings > Payments > CeyPay
2. Use the following test credentials:
   - **API Endpoint:** `sandbox-api.ceypay.io`
   - **Test Merchant ID:** `289caebb-ed95-465c-a967-68963bdd20de`
3. Test payments will only be visible to administrators
4. Use the "Simulate Success" button to complete test transactions

## Usage

1. Customer selects a provider (Bybit/Binance/KuCoin/Bitazza) at checkout
2. Modal opens with QR code or deep-link button for mobile
3. Plugin polls API and listens to webhooks for payment confirmation
4. Order is automatically marked as paid when confirmed

## Development

### Prerequisites

- WordPress 5.8+
- WooCommerce 7.0+
- PHP 7.4+

### Local Development

```bash
# Clone the repository
git clone https://github.com/CeyPay-io/woocommerce-plugin.git

# Start local mock server (for testing)
cd mock_server
python server.py
# or
php -S localhost:8080 index.php
```

### Mock Server

The `mock_server/` directory contains a local API mock for development:
- Simulates CeyPay API responses
- Supports payment status polling
- Useful for testing without real transactions

### Docker Development

```bash
docker-compose up -d
```

This starts a WordPress + WooCommerce environment for local testing.

## Project Structure

```
├── ceypay-payment-gateway/     # Plugin source code
│   ├── assets/                 # CSS, JS, images
│   ├── includes/               # PHP classes
│   └── ceypay-payment-gateway.php
├── wp-assets/                  # WordPress.org assets
├── mock_server/                # Local development server
├── docs/                       # Documentation
└── docker-compose.yml          # Local dev environment
```

## Automatic Updates

Downloads from GitHub Releases include automatic update notifications via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker).

## Documentation

- [Full Documentation](https://docs.ceypay.io/)
- [API Reference](https://docs.ceypay.io/api)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

GPLv2 or later - see [LICENSE](ceypay-payment-gateway/LICENSE)

## Support

- [Documentation](https://docs.ceypay.io/)
- [GitHub Issues](https://github.com/CeyPay-io/woocommerce-plugin/issues)
