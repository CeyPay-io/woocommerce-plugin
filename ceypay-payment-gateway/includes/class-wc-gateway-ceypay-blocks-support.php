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
        // Register Analytics Script first
        wp_register_script( 'ceypay-analytics', plugins_url( '../assets/js/ceypay-analytics.js', __FILE__ ), array(), CEYPAY_VERSION, true );

        // Localize analytics data
        if ( class_exists( 'CeyPay_Analytics' ) ) {
            $analytics = CeyPay_Analytics::get_instance();
            wp_localize_script( 'ceypay-analytics', 'ceypay_analytics_params', $analytics->get_frontend_tracking_data() );
        }

        // Register Legacy Checkout Script (for Modal functionality)
        wp_register_script( 'ceypay-checkout', plugins_url( '../assets/js/ceypay-checkout.js', __FILE__ ), array( 'jquery', 'ceypay-analytics' ), CEYPAY_VERSION, true );
        wp_localize_script( 'ceypay-checkout', 'ceypay_params', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'ceypay_status_check' ),
            'assets_url'    => plugins_url( '../assets/', __FILE__ ),
            'version'       => CEYPAY_VERSION,
            'show_branding' => 'yes' === $this->get_setting( 'show_branding' ) ? '1' : '0',
        ) );

        // Enqueue Styles
        wp_enqueue_style( 'ceypay-css', plugins_url( '../assets/css/ceypay.css', __FILE__ ), array(), CEYPAY_VERSION );

        // Register Block Integration Script
        wp_register_script(
            'ceypay-blocks-integration',
            plugins_url( '../assets/js/ceypay-blocks.js', __FILE__ ),
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
                'ceypay-checkout' // Depend on legacy script (which depends on analytics)
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
            'icons'       => [
                'src'     => plugins_url( '../assets/images/ceypay-symbol.png', __FILE__ ),
                'binance' => plugins_url( '../assets/images/binance.svg', __FILE__ ),
                'bybit'   => plugins_url( '../assets/images/bybit.svg', __FILE__ ),
                'kucoin'  => plugins_url( '../assets/images/kucoin-logo.svg', __FILE__ ),
                'bitazza' => plugins_url( '../assets/images/bitazza.svg', __FILE__ ),
                'ton'     => plugins_url( '../assets/images/ton_symbol.svg', __FILE__ ),
                'solana'  => plugins_url( '../assets/images/solana.svg', __FILE__ ),
            ],
        ];
    }

    private function get_setting( $key, $default = '' ) {
        if ( ! is_array( $this->settings ) ) {
            return $default;
        }

        return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
    }
}
