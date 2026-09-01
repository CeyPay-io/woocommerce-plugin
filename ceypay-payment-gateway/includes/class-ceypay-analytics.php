<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * CeyPay Analytics Handler
 *
 * Handles GA4 tracking for both frontend and backend events.
 *
 * This module is OPTIONAL and is excluded from the WordPress.org build.
 * All of its configuration is defined here (rather than in ceypay-constants.php)
 * so that deleting this file removes the tracking layer completely, credentials
 * included. Nothing outside this file may reference the CEYPAY_GA4_* constants.
 */

// GA4 Configuration (NOT exposed in admin settings)
if (! defined('CEYPAY_GA4_MEASUREMENT_ID')) {
    define('CEYPAY_GA4_MEASUREMENT_ID', 'G-ZZVE717KCV');
}

// GA4 Measurement Protocol API Secret (for server-side webhook events)
// Generate this in GA4 Admin > Data Streams > Measurement Protocol API secrets
if (! defined('CEYPAY_GA4_API_SECRET')) {
    define('CEYPAY_GA4_API_SECRET', 'ch1HnD6zQ-G63zCFWJVRfw');
}

// Master analytics toggle (can be overridden in wp-config.php to force disable)
// User-facing setting is in WooCommerce > Settings > Payments > CeyPay > Analytics
if (! defined('CEYPAY_ANALYTICS_ENABLED')) {
    define('CEYPAY_ANALYTICS_ENABLED', true);
}

class CeyPay_Analytics {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Merchant ID (loaded from settings)
     */
    private $merchant_id;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $settings = get_option( 'woocommerce_ceypay_settings', array() );
        $this->merchant_id = isset( $settings['merchant_id'] ) ? $settings['merchant_id'] : '';

        // Hook to enqueue GA4 script on checkout pages only
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_ga4_script' ), 5 );

        // Hook to enqueue Facebook Pixel
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fb_pixel_scripts' ) );
        add_action( 'wp_head', array( $this, 'output_fb_pixel_noscript' ) );
    }

    /**
     * Check if analytics is enabled
     *
     * @return bool True if analytics is enabled, false otherwise
     */
    public static function is_enabled() {
        // First check the constant (allows complete disable via wp-config.php)
        if ( ! defined( 'CEYPAY_ANALYTICS_ENABLED' ) || ! CEYPAY_ANALYTICS_ENABLED ) {
            return false;
        }

        // Then check the user setting
        $settings = get_option( 'woocommerce_ceypay_settings', array() );
        if ( isset( $settings['enable_analytics'] ) && 'yes' !== $settings['enable_analytics'] ) {
            return false;
        }

        return true;
    }

    /**
     * Conditionally enqueue GA4 gtag.js on checkout pages
     */
    public function maybe_enqueue_ga4_script() {
        if ( ! self::is_enabled() ) {
            return;
        }

        // Only load on checkout and cart pages
        if ( ! function_exists( 'is_checkout' ) || ( ! is_checkout() && ! is_cart() ) ) {
            return;
        }

        // Check if CeyPay gateway is enabled
        $settings = get_option( 'woocommerce_ceypay_settings', array() );
        if ( empty( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
            return;
        }

        $this->enqueue_ga4_script();
    }

    /**
     * Enqueue GA4 gtag.js script
     */
    private function enqueue_ga4_script() {
        $measurement_id = defined( 'CEYPAY_GA4_MEASUREMENT_ID' ) ? CEYPAY_GA4_MEASUREMENT_ID : '';

        if ( empty( $measurement_id ) ) {
            return;
        }

        // Register and enqueue gtag.js
        wp_enqueue_script(
            'ceypay-gtag',
            'https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $measurement_id ),
            array(),
            CEYPAY_VERSION,
            false // Load in header for analytics
        );

        // Add inline script to initialize gtag
        wp_add_inline_script(
            'ceypay-gtag',
            "window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '" . esc_js( $measurement_id ) . "', {
                'send_page_view': false,
                'cookie_flags': 'SameSite=None;Secure'
            });"
        );
    }

    /**
     * Get data for frontend tracking
     *
     * @return array Data to pass to JavaScript
     */
    public function get_frontend_tracking_data() {
        return array(
            'enabled'        => self::is_enabled(),
            'measurement_id' => defined( 'CEYPAY_GA4_MEASUREMENT_ID' ) ? CEYPAY_GA4_MEASUREMENT_ID : '',
            'merchant_id'    => $this->merchant_id,
        );
    }

    /**
     * Send server-side event via GA4 Measurement Protocol
     *
     * @param string $event_name Event name
     * @param array  $params     Event parameters
     * @param string $client_id  GA client ID (from cookie or generated)
     */
    public function send_server_event( $event_name, $params = array(), $client_id = null ) {
        if ( ! self::is_enabled() ) {
            return;
        }

        $measurement_id = defined( 'CEYPAY_GA4_MEASUREMENT_ID' ) ? CEYPAY_GA4_MEASUREMENT_ID : '';
        $api_secret = defined( 'CEYPAY_GA4_API_SECRET' ) ? CEYPAY_GA4_API_SECRET : '';

        if ( empty( $measurement_id ) || empty( $api_secret ) ) {
            return;
        }

        // Generate a client_id if not provided (for server-only events)
        if ( empty( $client_id ) ) {
            $client_id = $this->generate_client_id();
        }

        // Ensure merchant_id is included
        $params['merchant_id'] = $this->merchant_id;

        $payload = array(
            'client_id' => $client_id,
            'events'    => array(
                array(
                    'name'   => $event_name,
                    'params' => $params,
                ),
            ),
        );

        $url = sprintf(
            'https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s',
            $measurement_id,
            $api_secret
        );

        // Send non-blocking request
        wp_remote_post( $url, array(
            'body'     => wp_json_encode( $payload ),
            'headers'  => array( 'Content-Type' => 'application/json' ),
            'blocking' => false,
            'timeout'  => 5,
        ) );
    }

    /**
     * Generate a client ID for server-side events
     *
     * @return string Generated client ID
     */
    private function generate_client_id() {
        // Format: random_number.timestamp
        return wp_rand( 100000000, 999999999 ) . '.' . time();
    }

    /**
     * Track webhook success event
     *
     * @param int    $order_id   WooCommerce order ID
     * @param string $provider   Payment provider
     * @param float  $amount     Payment amount
     * @param string $currency   Currency code
     * @param string $client_id  Optional GA client ID
     */
    public function track_webhook_success( $order_id, $provider, $amount, $currency, $client_id = null ) {
        $this->send_server_event( 'ceypay_webhook_success', array(
            'order_id' => strval( $order_id ),
            'provider' => $provider,
            'amount'   => floatval( $amount ),
            'currency' => $currency,
        ), $client_id );
    }

    /**
     * Track webhook failure event
     *
     * @param int    $order_id   WooCommerce order ID
     * @param string $provider   Payment provider
     * @param float  $amount     Payment amount
     * @param string $currency   Currency code
     * @param string $client_id  Optional GA client ID
     */
    public function track_webhook_failed( $order_id, $provider, $amount, $currency, $client_id = null ) {
        $this->send_server_event( 'ceypay_webhook_failed', array(
            'order_id' => strval( $order_id ),
            'provider' => $provider,
            'amount'   => floatval( $amount ),
            'currency' => $currency,
        ), $client_id );
    }

    /**
     * Conditionally enqueue Facebook Pixel Scripts
     */
    public function enqueue_fb_pixel_scripts() {
        if ( ! self::is_enabled() ) {
            return;
        }

        // Hardcoded Pixel ID
        $pixel_id = '1437220651304250';

        if ( empty( $pixel_id ) ) {
            return;
        }

        wp_enqueue_script( 'ceypay-fb-events', 'https://connect.facebook.net/en_US/fbevents.js', array(), CEYPAY_VERSION, false );

        // Base Code (Init)
        $init_code = "
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];}(window, document,'script');
        fbq('init', '" . esc_js( $pixel_id ) . "');
        fbq('track', 'PageView');
        ";
        wp_add_inline_script( 'ceypay-fb-events', $init_code, 'before' );

        // Track Events
        $event_code = "";

        if ( function_exists( 'is_checkout' ) ) {
            // Track InitiateCheckout on checkout page (but not order received)
            if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
                 $event_code .= "fbq('track', 'InitiateCheckout');";
            }

            // Track Purchase on Order Received page
            if ( is_wc_endpoint_url( 'order-received' ) ) {
                $order_id = absint( get_query_var( 'order-received' ) );
                if ( $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $amount = $order->get_total();
                        $currency = $order->get_currency();
                        $event_code .= "fbq('track', 'Purchase', {value: " . esc_js( $amount ) . ", currency: '" . esc_js( $currency ) . "'}, {eventID: '" . esc_js( $order_id ) . "'});";
                    }
                }
            }
        }

        if ( ! empty( $event_code ) ) {
            wp_add_inline_script( 'ceypay-fb-events', $event_code );
        }
    }

    /**
     * Output Facebook Pixel Noscript
     */
    public function output_fb_pixel_noscript() {
        if ( ! self::is_enabled() ) {
            return;
        }
        $pixel_id = '1437220651304250';
        ?>
        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pixel_id ); ?>&ev=PageView&noscript=1"
        /></noscript>
        <?php
    }
}
