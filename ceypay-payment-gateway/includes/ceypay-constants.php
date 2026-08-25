<?php
if (! defined('ABSPATH')) exit;
/**
 * CeyPay Constants
 *
 * Shared constants only. Anything belonging to the optional tracking module
 * lives in that module's own file, and every call site is fenced with the
 * marker comment pair that ci/build-wporg.sh strips, so the WordPress.org build
 * loses both the module and the code that loads it.
 *
 * Do not write those markers literally here: this file sits inside the strip's
 * search path, and a start/end pair in a docblock would have the middle of the
 * block removed, leaving an unterminated comment.
 */

// Plugin Version
if (! defined('CEYPAY_VERSION')) {
    define('CEYPAY_VERSION', '1.3.3');
}
