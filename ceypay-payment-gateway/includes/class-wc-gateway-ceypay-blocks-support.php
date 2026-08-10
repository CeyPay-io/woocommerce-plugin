<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- WooCommerce blocks naming convention
final class WC_Gateway_CeyPay_Blocks_Support extends AbstractPaymentMethodType {
    protected $name = 'ceypay';
    protected $settings = array();

    public function initialize() {
        $settings = get_option( 'woocommerce_ceypay_settings', array() );
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function is_active() {
        return 'yes' === $this->get_setting( 'enabled' );
    }

    public function get_payment_method_script_handles() {
        $checkout_deps = array( 'jquery' );

        // Register Analytics Script first. Absent from the WordPress.org build.
        if ( ceypay_has_analytics() ) {
            wp_register_script( 'ceypay-analytics', CEYPAY_PLUGIN_URL . 'assets/js/ceypay-analytics.js', array(), CEYPAY_VERSION, true );

            $analytics = CeyPay_Analytics::get_instance();
            wp_localize_script( 'ceypay-analytics', 'ceypay_analytics_params', $analytics->get_frontend_tracking_data() );

            $checkout_deps[] = 'ceypay-analytics';
        }

        // Register Legacy Checkout Script (for Modal functionality)
        wp_register_script( 'ceypay-checkout', CEYPAY_PLUGIN_URL . 'assets/js/ceypay-checkout.js', $checkout_deps, CEYPAY_VERSION, true );
        wp_localize_script( 'ceypay-checkout', 'ceypay_params', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'ceypay_status_check' ),
            'assets_url'    => CEYPAY_PLUGIN_URL . 'assets/',
            'version'       => CEYPAY_VERSION,
            'show_branding' => 'yes' === $this->get_setting( 'show_branding' ) ? '1' : '0',
        ) );

        // Enqueue Styles
        wp_enqueue_style( 'ceypay-css', CEYPAY_PLUGIN_URL . 'assets/css/ceypay.css', array(), CEYPAY_VERSION );

        // Register Block Integration Script
        wp_register_script(
            'ceypay-blocks-integration',
            CEYPAY_PLUGIN_URL . 'assets/js/ceypay-blocks.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
                'ceypay-checkout' // Depend on legacy script (which pulls in analytics when bundled)
            ],
            CEYPAY_VERSION,
            true
        );

        return [ 'ceypay-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title', 'CeyPay' ),
            'testmode'    => 'yes' === $this->get_setting( 'testmode' ),
            'description' => __( 'Pay securely with CeyPay using digital currency balance on your favorite CEX.', 'ceypay-payment-gateway' ),
            'supports'    => $this->get_supported_features(),
            // Selectable providers, then the announced ones the block renders as
            // "Coming soon". Keep in sync with the modal in ceypay-checkout.js.
            'icons'       => [
                'src'     => CEYPAY_PLUGIN_URL . 'assets/images/ceypay-symbol.png',
                'binance' => CEYPAY_PLUGIN_URL . 'assets/images/binance.svg',
                'bybit'   => CEYPAY_PLUGIN_URL . 'assets/images/bybit.svg',
                'kucoin'  => CEYPAY_PLUGIN_URL . 'assets/images/kucoin-logo.svg',
                'bitazza' => CEYPAY_PLUGIN_URL . 'assets/images/bitazza.svg',
                'ton'     => CEYPAY_PLUGIN_URL . 'assets/images/ton_symbol.svg',
                'solana'  => CEYPAY_PLUGIN_URL . 'assets/images/solana.svg',
            ],
        ];
    }

    /**
     * Overrides AbstractPaymentMethodType::get_setting(), which is protected.
     * Declaring this private is a PHP fatal error, so the visibility must stay
     * at protected or wider.
     */
    protected function get_setting( $key, $default = '' ) {
        if ( ! is_array( $this->settings ) ) {
            return $default;
        }

        return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
    }
}
