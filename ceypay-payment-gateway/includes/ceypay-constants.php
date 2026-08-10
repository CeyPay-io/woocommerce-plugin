<?php
if (! defined('ABSPATH')) exit;
/**
 * CeyPay Constants
 *
 * Shared constants only. Anything belonging to the optional analytics module
 * lives in class-ceypay-analytics.php so that removing that one file strips
 * the entire tracking layer (see .github/workflows/deploy-wp-org.yml).
 */

// Plugin Version
if (! defined('CEYPAY_VERSION')) {
    define('CEYPAY_VERSION', '1.4.0');
}
