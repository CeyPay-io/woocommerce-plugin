<?php
if (! defined('ABSPATH')) exit;
/**
 * CeyPay Constants
 *
 * GA4 credentials are read from WP options or wp-config.php defines,
 * never hardcoded. Seed values via:
 *   wp option update ceypay_ga4_api_secret 'YOUR_SECRET'
 * or in wp-config.php:
 *   define('CEYPAY_GA4_API_SECRET', 'YOUR_SECRET');
 *   define('CEYPAY_GA4_MEASUREMENT_ID', 'G-XXXXXXXX');
 */

// Plugin Version
if (! defined('CEYPAY_VERSION')) {
    define('CEYPAY_VERSION', '1.3.1');
}

// GA4 Configuration — read from wp-config.php define or WP option; never hardcoded
if (! defined('CEYPAY_GA4_MEASUREMENT_ID')) {
    define('CEYPAY_GA4_MEASUREMENT_ID', get_option('ceypay_ga4_measurement_id', 'G-ZZVE717KCV'));
}

// GA4 Measurement Protocol API Secret — must be seeded via WP option or wp-config.php
// Generate in: GA4 Admin > Data Streams > Measurement Protocol API secrets
if (! defined('CEYPAY_GA4_API_SECRET')) {
    define('CEYPAY_GA4_API_SECRET', get_option('ceypay_ga4_api_secret', ''));
}

// Master analytics toggle (can be overridden in wp-config.php to force disable)
// User-facing setting is in WooCommerce > Settings > Payments > CeyPay > Analytics
if (! defined('CEYPAY_ANALYTICS_ENABLED')) {
    define('CEYPAY_ANALYTICS_ENABLED', true);
}
