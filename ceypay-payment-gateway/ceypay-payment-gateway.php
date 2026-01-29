<?php
/**
 * Plugin Name: CeyPay Payment Gateway
 * Plugin URI:  https://docs.ceypay.io/wordpress
 * Description: WooCommerce payment gateway for CeyPay IPG.
 * Version:     1.2.9
 * Author:      CeyPay
 * Author URI:  https://ceypay.io/
 * Text Domain: ceypay-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Load constants (including GA4 Measurement ID)
require_once dirname( __FILE__ ) . '/includes/ceypay-constants.php';

// Make sure WooCommerce is active
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using core WordPress filter
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
    return;
}

/**
 * Add the Gateway to WooCommerce
 */
function ceypay_add_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_CeyPay';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'ceypay_add_gateway_class' );

/**
 * Initialize Gateway Class
 */
function ceypay_init_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    include_once dirname( __FILE__ ) . '/includes/class-wc-gateway-ceypay.php';

    // Load Analytics Handler
    require_once dirname( __FILE__ ) . '/includes/class-ceypay-analytics.php';
    CeyPay_Analytics::get_instance();

    // Only instantiate the gateway during CeyPay-specific AJAX actions to register hooks.
    // For WooCommerce AJAX actions (e.g., update_order_review), WC instantiates gateways itself.
    // Previously, instantiating on ALL AJAX requests caused duplicate filter registrations
    // (woocommerce_gateway_icon, woocommerce_gateway_title) which could corrupt checkout fragments.
    if ( wp_doing_ajax() ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking action name to decide instantiation
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
        if ( strpos( $action, 'ceypay_' ) === 0 ) {
            new WC_Gateway_CeyPay();
        }
    }
}
add_action( 'plugins_loaded', 'ceypay_init_gateway_class', 11 );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility
 */
function ceypay_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'ceypay_declare_hpos_compatibility' );

/**
 * Register WooCommerce Blocks Support
 */
function ceypay_register_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-ceypay-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new WC_Gateway_CeyPay_Blocks_Support() );
            }
        );
    }
}
add_action( 'woocommerce_blocks_loaded', 'ceypay_register_blocks_support' );

/**
 * Admin Notice for Missing Configuration
 */
function ceypay_admin_notices() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Hide on CeyPay settings page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking current admin page, no data processing
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

    if ( 'wc-settings' === $current_page && 'checkout' === $current_tab && 'ceypay' === $current_section ) {
        return;
    }

    $settings = get_option( 'woocommerce_ceypay_settings' );

    // Check if enabled
    if ( ! isset( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
        return;
    }

    $merchant_id = isset( $settings['merchant_id'] ) ? $settings['merchant_id'] : '';

    if ( empty( $merchant_id ) ) {
        $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ceypay' );
        ?>
        <div class="notice notice-error" style="border-left: 4px solid #1C6EF5; padding: 20px; margin-top: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center;">
                <div style="margin-right: 20px;">
                    <img src="<?php echo esc_url( plugins_url( 'assets/images/ceypay-symbol.png', __FILE__ ) ); ?>" style="height: 60px; width: auto;" alt="CeyPay">
                </div>
                <div>
                    <h3 style="margin: 0 0 10px; color: #333; font-size: 18px;"><?php esc_html_e( 'Action Needed: Complete CeyPay Setup', 'ceypay-payment-gateway' ); ?></h3>
                    <p style="margin: 0 0 15px; font-size: 14px; color: #555;">
                        <?php echo wp_kses_post( __( 'Your CeyPay payment gateway is almost ready! To start accepting crypto payments, you must configure your <strong>Merchant ID</strong>.', 'ceypay-payment-gateway' ) ); ?>
                    </p>
                    <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary button-large" style="background-color: #1C6EF5; border-color: #1C6EF5;">
                        <?php esc_html_e( 'Complete Setup Now', 'ceypay-payment-gateway' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'ceypay_admin_notices' );

/**
 * Admin Scripts to modify Gateway Badge
 */
function ceypay_admin_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
        return;
    }

    // Check if we are on the payments tab
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking current admin page, no data processing
    $admin_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
    if ( 'checkout' !== $admin_tab ) {
        return;
    }

    $settings = get_option( 'woocommerce_ceypay_settings' );
    if ( ! is_array( $settings ) ) {
        $settings = array();
    }

    // Only if enabled but missing config
    if ( isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) {
        $merchant_id = isset( $settings['merchant_id'] ) ? $settings['merchant_id'] : '';

        if ( empty( $merchant_id ) ) {
            $action_needed_text = __( 'Action Needed', 'ceypay-payment-gateway' );

            wp_add_inline_script( 'jquery-core', "
                jQuery(document).ready(function($) {
                    var \$row = $('tr[data-gateway_id=\"ceypay\"]');
                    if (\$row.length) {
                        // Find elements containing \"Active\" text
                        \$row.find('*').each(function() {
                            if ($(this).children().length === 0 && $(this).text().trim() === 'Active') {
                                $(this).text('" . esc_js( $action_needed_text ) . "');
                                $(this).css({
                                    'background-color': '#d63638',
                                    'color': '#fff',
                                    'border-color': '#d63638'
                                });
                                // Remove potential conflicting classes
                                $(this).removeClass('status-enabled active');
                            }
                        });
                    }
                });
            " );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'ceypay_admin_scripts' );
