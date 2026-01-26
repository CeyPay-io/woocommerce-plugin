<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * CeyPay Constants
 *
 * This file contains hardcoded constants for the CeyPay plugin.
 * The GA4 Measurement ID is intentionally not exposed to merchants.
 */

// Plugin Version
if ( ! defined( 'CEYPAY_VERSION' ) ) {
    define( 'CEYPAY_VERSION', '1.2.4' );
}

// GA4 Configuration (NOT exposed in admin settings)
if ( ! defined( 'CEYPAY_GA4_MEASUREMENT_ID' ) ) {
    define( 'CEYPAY_GA4_MEASUREMENT_ID', 'G-ZZVE717KCV' );
}

// GA4 Measurement Protocol API Secret (for server-side webhook events)
// Generate this in GA4 Admin > Data Streams > Measurement Protocol API secrets
if ( ! defined( 'CEYPAY_GA4_API_SECRET' ) ) {
    define( 'CEYPAY_GA4_API_SECRET', 'ch1HnD6zQ-G63zCFWJVRfw' ); // Add your API secret here for webhook tracking
}

// Master analytics toggle (can be overridden in wp-config.php to force disable)
// User-facing setting is in WooCommerce > Settings > Payments > CeyPay > Analytics
if ( ! defined( 'CEYPAY_ANALYTICS_ENABLED' ) ) {
    define( 'CEYPAY_ANALYTICS_ENABLED', true );
}
