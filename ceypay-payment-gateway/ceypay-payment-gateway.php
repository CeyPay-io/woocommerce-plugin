<?php
/**
 * Plugin Name: CeyPay Payment Gateway
 * Plugin URI:  https://docs.ceypay.io/wordpress
 * Description: WooCommerce payment gateway for CeyPay IPG.
 * Version:     1.3.3
 * Author:      CeyPay
 * Author URI:  https://ceypay.io/
 * Text Domain: ceypay-payment-gateway
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.1
 * WC tested up to: 11.0
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Plugin root paths. Asset URLs must be built from these rather than with
// plugins_url( '../assets/...', __FILE__ ) from inside includes/, which yields
// an unnormalised ".../includes/../assets/..." URL. Browsers collapse that
// before WooCommerce's dependency detection can match it against the
// registered script URL, producing spurious "Unregistered script" warnings.
if ( ! defined( 'CEYPAY_PLUGIN_FILE' ) ) {
    define( 'CEYPAY_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'CEYPAY_PLUGIN_URL' ) ) {
    define( 'CEYPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CEYPAY_PLUGIN_DIR' ) ) {
    define( 'CEYPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Load shared constants.
require_once dirname( __FILE__ ) . '/includes/ceypay-constants.php';

// Make sure WooCommerce is active.
//
// These are prefixed because they sit at file scope in the main plugin file and
// therefore land in the global namespace, where an unprefixed $active_plugins
// could collide with another plugin doing the same thing.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using core WordPress filter
$ceypay_active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

if ( is_multisite() ) {
    $ceypay_network_active_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
    $ceypay_active_plugins = array_unique( array_merge( $ceypay_active_plugins, $ceypay_network_active_plugins ) );
}

if ( ! in_array( 'woocommerce/woocommerce.php', $ceypay_active_plugins, true ) ) {
    return;
}

// Review prompt. Loaded after the WooCommerce check because it counts paid
// orders, so it has nothing to do until WooCommerce is present.
require_once dirname( __FILE__ ) . '/includes/ceypay-review-prompt.php';

// ceypay:analytics-start
/**
 * Whether the optional analytics module is bundled in this build.
 *
 * The WordPress.org build ships without it, so every analytics touchpoint
 * (script dependencies, settings field, event calls) must check this first.
 *
 * Everything between the surrounding marker comments is removed by
 * ci/build-wporg.sh, so the .org build contains no reference to the module at
 * all -- not even a guarded one. A conditional require of a file absent from
 * the ZIP reads as a payload loader to a plugin reviewer (guideline 8), which
 * is why the guard itself has to go rather than just the file it guards.
 *
 * @return bool
 */
function ceypay_has_analytics() {
    return class_exists( 'CeyPay_Analytics' );
}
// ceypay:analytics-end

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

    // ceypay:analytics-start
    // Load the optional Analytics Handler. Absent from the WordPress.org build.
    $ceypay_analytics_file = dirname( __FILE__ ) . '/includes/class-ceypay-analytics.php';
    if ( file_exists( $ceypay_analytics_file ) ) {
        require_once $ceypay_analytics_file;
        CeyPay_Analytics::get_instance();
    }
    // ceypay:analytics-end

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
 * Handle dismissal of the setup notice.
 *
 * Stored per user so the notice stays dismissed across page loads, rather than
 * only for the current request.
 */
function ceypay_dismiss_admin_notice() {
    if ( ! isset( $_GET['ceypay_dismiss_setup_notice'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'ceypay_dismiss_setup_notice' ) ) {
        return;
    }

    update_user_meta( get_current_user_id(), 'ceypay_setup_notice_dismissed', 1 );

    wp_safe_redirect( remove_query_arg( array( 'ceypay_dismiss_setup_notice', '_wpnonce' ) ) );
    exit;
}
add_action( 'admin_init', 'ceypay_dismiss_admin_notice' );

/**
 * Admin Notice for Missing Configuration
 *
 * Scoped to the Plugins screen and WooCommerce settings, and dismissible, so it
 * does not follow the user around the admin (WordPress.org guideline 11).
 */
function ceypay_admin_notices() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( get_user_meta( get_current_user_id(), 'ceypay_setup_notice_dismissed', true ) ) {
        return;
    }

    // Limit to the screens where acting on this notice makes sense. This fires
    // only when the gateway is enabled with no Merchant ID -- checkout is
    // broken -- so the Dashboard is included deliberately: that is where an
    // admin lands on login, and a store silently unable to take payment is
    // worth interrupting for. The notice is dismissible, so it does not nag.
    $screen = get_current_screen();
    $screens = array(
        'dashboard',                    // where admins land on login
        'plugins',                      // right after activating
        'woocommerce_page_wc-admin',    // WooCommerce Home
        'woocommerce_page_wc-settings', // WooCommerce Settings
    );
    if ( ! $screen || ! in_array( $screen->id, $screens, true ) ) {
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

    $settings = get_option( 'woocommerce_ceypay_settings', array() );
    if ( ! is_array( $settings ) ) {
        $settings = array();
    }

    // Check if enabled
    if ( ! isset( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
        return;
    }

    $merchant_id = isset( $settings['merchant_id'] ) ? $settings['merchant_id'] : '';

    if ( empty( $merchant_id ) ) {
        $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ceypay' );
        $dismiss_url  = wp_nonce_url(
            add_query_arg( 'ceypay_dismiss_setup_notice', '1' ),
            'ceypay_dismiss_setup_notice'
        );
        ?>
        <div class="notice notice-error is-dismissible" style="border-left: 4px solid #1C6EF5; padding: 20px; margin-top: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);">
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
                    <a href="<?php echo esc_url( $dismiss_url ); ?>" style="margin-left: 12px; font-size: 13px;">
                        <?php esc_html_e( 'Dismiss', 'ceypay-payment-gateway' ); ?>
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

    $settings = get_option( 'woocommerce_ceypay_settings', array() );
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

/**
 * Register a CeyPay entry in the WooCommerce admin sidebar.
 *
 * Without this the gateway is only reachable by digging into
 * WooCommerce > Settings > Payments > CeyPay. This surfaces it alongside the
 * other WooCommerce sections, which is where merchants look for it.
 *
 * Deliberately uses add_menu_page() rather than wc_admin_register_page():
 * the latter registers a route inside the WooCommerce Admin React app and
 * rewrites the target into `page=wc-admin&path=...`, which mangles a link to a
 * classic settings screen. Our settings live in the classic Payments tab, so
 * pointing a plain menu entry straight at it is both correct and free of any
 * dependency on WooCommerce Admin being enabled.
 *
 * Position 56 places it directly beneath WooCommerce's own Payments entry.
 */
function ceypay_register_admin_page() {
    // The CeyPay symbol. Filled with the admin menu's own icon grey rather
    // than brand blue, so it sits correctly alongside the other menu icons --
    // WordPress does not recolour data-URI icons, it only sizes them.
    // The class/<style> block from the source file is inlined as a fill
    // attribute because WordPress strips <style> from data-URI backgrounds.
    $icon = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 585.19 585.19">'
        . '<path fill="#a7aaad" d="M290.94,0C143.44,0,21.31,107.94,0,248.71h89.84'
        . 'c20.22-92,102.51-160.93,201.1-160.93s180.88,68.93,201.1,160.93h-91.95'
        . 'c-17.45-42.92-59.73-73.15-109.15-73.15-65,0-117.7,52.4-117.7,117.04'
        . 's52.7,117.04,117.7,117.04c49.39,0,91.68-30.27,109.15-73.15h91.95'
        . 'c-20.22,92-102.51,160.93-201.1,160.93s-180.88-68.93-201.1-160.93H0'
        . 'c21.31,140.76,143.44,248.71,290.94,248.71,162.51,0,294.25-131,294.25-292.6'
        . 'S453.45,0,290.94,0h0Z"/>'
        . '</svg>'
    );

    add_menu_page(
        __( 'CeyPay', 'ceypay-payment-gateway' ),
        __( 'CeyPay', 'ceypay-payment-gateway' ),
        'manage_woocommerce',
        'admin.php?page=wc-settings&tab=checkout&section=ceypay',
        '',
        $icon,
        56
    );
}
add_action( 'admin_menu', 'ceypay_register_admin_page', 20 );

/**
 * Add a Settings link on the Plugins screen.
 *
 * Standard convention -- it saves merchants hunting through WooCommerce
 * settings right after activating.
 *
 * @param array $links Existing action links.
 * @return array
 */
function ceypay_plugin_action_links( $links ) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ceypay' ) ),
        esc_html__( 'Settings', 'ceypay-payment-gateway' )
    );

    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ceypay_plugin_action_links' );
