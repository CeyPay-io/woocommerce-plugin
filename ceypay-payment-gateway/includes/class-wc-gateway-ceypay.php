<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- WooCommerce payment gateway naming convention
class WC_Gateway_CeyPay extends WC_Payment_Gateway {

    /**
     * Test mode setting.
     * @var bool
     */
    public $testmode;

    /**
     * Merchant ID setting.
     * @var string
     */
    public $merchant_id;

    /**
     * API URL.
     * @var string
     */
    public $api_url;

    public function __construct() {
        $this->id                 = 'ceypay';
        $this->icon               = plugins_url( '../assets/images/ceypay-symbol.png', __FILE__ );
        $this->has_fields         = true; // Enable fields for provider selection
        $this->method_title       = __( 'CeyPay', 'ceypay-payment-gateway' );
        $this->method_description = __( 'Enable customers to pay using digital currency balances from supported centralized exchanges with CeyPay.', 'ceypay-payment-gateway' );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title          = 'CeyPay'; // Hardcoded as title field is removed
        $this->description    = __( 'Pay securely with CeyPay using digital currency balance on your favorite CEX.', 'ceypay-payment-gateway' );
        $this->enabled        = $this->get_option( 'enabled' );
        $this->testmode       = 'yes' === $this->get_option( 'testmode' );
        $this->merchant_id    = $this->get_option( 'merchant_id' );

        $this->api_url        = $this->testmode ? 'https://nisal.ceyl.one/ceypay/mock-api/' : 'https://api.ceypay.io/';

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_wc_gateway_ceypay', array( $this, 'webhook_handler' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_styles' ) );

        // Filters
        add_filter( 'woocommerce_gateway_icon', array( $this, 'custom_gateway_icon' ), 10, 2 );

        // Register AJAX hooks
        $this->register_ajax_hooks();
    }

    /**
     * Enqueue styles for checkout page
     */
    public function enqueue_checkout_styles() {
        if ( is_checkout() || is_cart() ) {
            wp_enqueue_style( 'ceypay-css', plugins_url( '../assets/css/ceypay.css', __FILE__ ), array(), CEYPAY_VERSION );
        }
    }

    /**
     * Custom gateway icon for checkout page
     *
     * @param string $icon HTML for the gateway icon
     * @param string $gateway_id The gateway ID
     * @return string Modified icon HTML
     */
    public function custom_gateway_icon( $icon, $gateway_id ) {
        // Only modify icon for this gateway on checkout page
        if ( $gateway_id === $this->id && is_checkout() ) {
            $blue_pill_url = plugins_url( '../assets/images/ceypay-pill-bybit.png', __FILE__ );
            $icon = '<img src="' . esc_url( $blue_pill_url ) . '" alt="' . esc_attr( $this->get_title() ) . '" style="width: 100px; height: auto;" />';
        }
        return $icon;
    }

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available() {
        $is_available = parent::is_available();

        if ( $this->testmode && ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return $is_available;
    }

    /**
     * Check if the gateway needs setup.
     *
     * @return bool
     */
    public function needs_setup() {
        if ( ! $this->merchant_id ) {
            return true;
        }
        return false;
    }

    /**
     * Register AJAX hooks
     */
    public function register_ajax_hooks() {
        add_action( 'wp_ajax_ceypay_check_status', array( $this, 'ajax_check_status' ) );
        add_action( 'wp_ajax_nopriv_ceypay_check_status', array( $this, 'ajax_check_status' ) );
        add_action( 'wp_ajax_ceypay_simulate_payment', array( $this, 'ajax_simulate_payment' ) );
        add_action( 'wp_ajax_ceypay_generate_qr', array( $this, 'ajax_generate_qr' ) );
        add_action( 'wp_ajax_nopriv_ceypay_generate_qr', array( $this, 'ajax_generate_qr' ) );
        add_action( 'wp_ajax_ceypay_check_order_status', array( $this, 'ajax_check_order_status' ) );
        add_action( 'wp_ajax_nopriv_ceypay_check_order_status', array( $this, 'ajax_check_order_status' ) );
    }

    /**
     * AJAX: Generate QR Code
     */
    public function ajax_generate_qr() {
        check_ajax_referer( 'ceypay_status_check', 'security' );

        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        $provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
        $ga_client_id = isset( $_POST['ga_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ga_client_id'] ) ) : '';

        if ( ! $order_id || ! $provider ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => 'Order not found' ) );
        }

        // Store GA client ID for server-side event correlation
        if ( ! empty( $ga_client_id ) ) {
            $order->update_meta_data( '_ceypay_ga_client_id', $ga_client_id );
        }

        // Clear any previous payment status (e.g., EXPIRED) when generating new QR
        $order->delete_meta_data( '_ceypay_payment_status' );
        $order->save();

        // Validate provider
        $allowed_providers = array( 'BINANCE', 'BYBIT', 'BITAZZA', 'KUCOIN' );
        if ( ! in_array( $provider, $allowed_providers ) ) {
            wp_send_json_error( array( 'message' => 'Invalid provider' ) );
        }

        // Construct payload
        $payload = array(
            'merchantId'      => $this->merchant_id,
            'amount'          => (float) $order->get_total(),
            'goods'           => array(),
            'webhookUrl'      => WC()->api_request_url( 'WC_Gateway_CeyPay' ),
            'currency'        => $order->get_currency(),
            'provider'        => $provider,
            'merchantTradeNo' => (string) $order->get_order_number(),
            'customerBilling' => array(
                'firstName'  => $order->get_billing_first_name(),
                'lastName'   => $order->get_billing_last_name(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
                'address'    => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
                'city'       => $order->get_billing_city(),
                'postalCode' => $order->get_billing_postcode(),
                'country'    => WC()->countries->countries[ $order->get_billing_country() ] ?? null
            )
        );

        // Populate goods
        foreach ( $order->get_items() as $item ) {
            $payload['goods'][] = array(
                'name'        => $item->get_name(),
                'description' => $item->get_name(),
                'mccCode'     => '5818'
            );
        }


        // Send to API
        $response = wp_remote_post( trailingslashit( $this->api_url ) . 'payment/create', array(
            'body'    => json_encode( $payload ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 45
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Connection error: ' . $response->get_error_message() ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $body = json_decode( $raw_body, true );

        if ( $response_code !== 200 && $response_code !== 201 ) {
             $error_message = 'Payment error. Please try again.';
             if ( isset( $body['message'] ) ) {
                 if ( is_array( $body['message'] ) ) {
                     $error_message = implode( '<br>', $body['message'] );
                 } else {
                     $error_message = $body['message'];
                 }
             } elseif ( isset( $body['error'] ) ) {
                 $error_message = $body['error'];
             }
             wp_send_json_error( array( 'message' => $error_message ) );
        }

        if ( isset( $body['qrContent'] ) ) {
             // Store in order meta
             $order->update_meta_data( '_ceypay_qr_code_url', $body['qrContent'] );
             if ( isset( $body['checkoutLink'] ) ) {
                 $order->update_meta_data( '_ceypay_deep_link', $body['checkoutLink'] );
             }
             if ( isset( $body['id'] ) ) {
                 $order->update_meta_data( '_ceypay_transaction_id', $body['id'] );
             }
             $order->update_meta_data( '_ceypay_provider', $provider );
             $order->save();

             wp_send_json_success( array(
                 'qr_code_url'    => $body['qrContent'],
                 'deep_link'      => isset( $body['checkoutLink'] ) ? $body['checkoutLink'] : '',
                 'transaction_id' => isset( $body['id'] ) ? $body['id'] : '',
                 'provider'       => $provider,
                 'amount'    => isset( $body['amount'] ) ? $body['amount'] : 0,
                 'currency'  => isset( $body['currency'] ) ? $body['currency'] : '',
                 'currency_amount'=> $order->get_total(),
                 'currency_code'  => $order->get_currency(),
                 'fee_breakdown'  => isset( $body['feeBreakdown'] ) ? $body['feeBreakdown'] : null
             ) );
        } else {
             wp_send_json_error( array( 'message' => 'Invalid response from payment provider.' ) );
        }
    }

    /**
     * Plugin Options
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'ceypay-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable CeyPay Payment', 'ceypay-payment-gateway' ),
                'default' => 'yes'
            ),
            'testmode' => array(
                'title'   => __( 'Test Mode', 'ceypay-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Test Mode', 'ceypay-payment-gateway' ),
                'default' => 'no',
                'description' => __( 'Place the gateway in test mode using test API keys.', 'ceypay-payment-gateway' ),
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => __( 'Merchant ID', 'ceypay-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Your CeyPay Merchant ID.', 'ceypay-payment-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'enable_analytics' => array(
                'title'       => __( 'Analytics', 'ceypay-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable usage analytics', 'ceypay-payment-gateway' ),
                'description' => __( 'Help improve CeyPay by sharing anonymous payment flow data (e.g., provider selection, QR interactions, completion rates). No personal information, transaction amounts, or customer data is collected. Data is sent to Google Analytics.', 'ceypay-payment-gateway' ),
                'default'     => 'no',
                'desc_tip'    => false,
            ),
        );
    }

    /**
     * Payment Fields
     */
    public function payment_fields() {
        if ( $this->description ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped with wp_kses_post
            echo wp_kses_post( wpautop( $this->description ) );
        }

        // Enqueue styles and scripts
        wp_enqueue_style( 'ceypay-css', plugins_url( '../assets/css/ceypay.css?v=' . CEYPAY_VERSION, __FILE__ ), array(), CEYPAY_VERSION );

        // Enqueue analytics script BEFORE checkout script (dependency)
        wp_enqueue_script( 'ceypay-analytics', plugins_url( '../assets/js/ceypay-analytics.js?v=' . CEYPAY_VERSION, __FILE__ ), array(), CEYPAY_VERSION, true );

        // Localize analytics data
        if ( class_exists( 'CeyPay_Analytics' ) ) {
            $analytics = CeyPay_Analytics::get_instance();
            wp_localize_script( 'ceypay-analytics', 'ceypay_analytics_params', $analytics->get_frontend_tracking_data() );
        }

        wp_enqueue_script( 'ceypay-checkout', plugins_url( '../assets/js/ceypay-checkout.js?v=' . CEYPAY_VERSION, __FILE__ ), array( 'jquery', 'ceypay-analytics' ), CEYPAY_VERSION, true );
        wp_localize_script( 'ceypay-checkout', 'ceypay_params', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'ceypay_status_check' ),
            'assets_url' => plugins_url( '../assets/', __FILE__ ),
        ) );

        echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        // Provider selection moved to modal
        echo '<div class="clear"></div></fieldset>';
    }

    /**
     * Validate Fields
     */
    public function validate_fields() {
        // Allow empty provider for Blocks (defaults to BINANCE in process_payment)
        // if ( empty( $_POST['ceypay_provider'] ) ) {
        //    wc_add_notice( __( 'Please select a payment provider.', 'ceypay-payment-gateway' ), 'error' );
        //    return false;
        // }
        return true;
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Update status to PENDING
        $order->update_status( 'pending', __( 'Awaiting CeyPay payment provider selection.', 'ceypay-payment-gateway' ) );

        // Prepare data for Modal (Initial State: No QR yet)
        $modal_data = array(
            'order_id'       => $order_id,
            'amount'         => $order->get_total(),
            'currency'       => $order->get_currency(),
            'success_url'    => $this->get_return_url( $order ),
            'test_mode'      => $this->testmode,
            'step'           => 'select_provider' // New flag to indicate selection step
        );

        // Encode data for hash
        $hash_payload = base64_encode( json_encode( $modal_data ) );

        // Return hash redirect to trigger modal
        return array(
            'result'   => 'success',
            'redirect' => wc_get_checkout_url() . '#ceypay_modal=' . $hash_payload,
        );
    }

    /**
     * Output for the receipt page.
     */
    public function receipt_page( $order_id ) {
        // Fallback for direct access or if modal fails
        $order = wc_get_order( $order_id );

        $qr_code_url = $order->get_meta( '_ceypay_qr_code_url' );
        $deep_link = $order->get_meta( '_ceypay_deep_link' );
        $provider = $order->get_meta( '_ceypay_provider' );
        $transaction_id = $order->get_meta( '_ceypay_transaction_id' );

        if ( $qr_code_url ) {
            // Enqueue polling script (reusing the checkout script logic if needed, but here we use the old one for fallback)
            // Actually, let's just output the same structure as before for fallback.

            wp_enqueue_script( 'ceypay-poll', plugins_url( '../assets/js/ceypay-poll.js', __FILE__ ), array( 'jquery' ), CEYPAY_VERSION, true );
            wp_localize_script( 'ceypay-poll', 'ceypay_params', array(
                'ajax_url'       => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'ceypay_status_check' ),
                'transaction_id' => $transaction_id,
                'order_id'       => $order_id,
                'success_url'    => $this->get_return_url( $order ),
                'status_url'     => trailingslashit( $this->api_url ) . 'payment/' . $transaction_id,
            ) );

            echo '<div class="ceypay-payment-instructions" style="text-align:center; margin: 20px 0; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">';
            /* translators: %s: Payment provider name (e.g., Binance, Bybit) */
            echo '<h2>' . esc_html( sprintf( __( 'Pay with %s', 'ceypay-payment-gateway' ), $provider ) ) . '</h2>';
            echo '<p>' . esc_html__( 'Please scan the QR code below to complete your payment.', 'ceypay-payment-gateway' ) . '</p>';
            echo '<div style="background: white; padding: 10px; display: inline-block; border: 1px solid #ddd; border-radius: 4px;">';
            echo '<img src="' . esc_url( $qr_code_url ) . '" alt="Payment QR Code" style="max-width: 250px; display: block;"/>';
            echo '</div>';

            echo '<div style="margin-top: 20px;">';
            echo '<p class="ceypay-status-text" style="font-weight: bold; color: #666;">' . esc_html__( 'Waiting for payment...', 'ceypay-payment-gateway' ) . ' <span class="spinner is-active" style="float:none; margin: 0 0 -3px 5px;"></span></p>';
            echo '</div>';

            if ( $deep_link ) {
                /* translators: %s: Payment provider name (e.g., Binance, Bybit) */
                echo '<p><a href="' . esc_url( $deep_link ) . '" class="button alt" target="_blank" style="margin-top: 10px;">' . esc_html( sprintf( __( 'Open %s App', 'ceypay-payment-gateway' ), $provider ) ) . '</a></p>';
            }

            echo '</div>';
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page( $order_id ) {
        // Do nothing on thank you page, as payment is already done.
    }

    /**
     * AJAX: Check Payment Status
     */
    public function ajax_check_status() {
        check_ajax_referer( 'ceypay_status_check', 'security' );

        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';

        if ( empty( $transaction_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing transaction ID' ) );
        }

        // Call API to check status
        $response = wp_remote_get( trailingslashit( $this->api_url ) . 'payment/' . $transaction_id, array(
            'timeout' => 15
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = isset( $body['status'] ) ? $body['status'] : 'PENDING';



        // Also check local order status as fallback (in case webhook updated it first)
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                // Check if order is already paid
                if ( $order->has_status( array( 'processing', 'completed' ) ) ) {
                    $status = 'PAID';
                }
                // Check if USER_REVIEW or EXPIRED status was set by webhook
                $ceypay_status = $order->get_meta( '_ceypay_payment_status' );
                if ( 'PAID' !== $status && 'SUCCESS' !== $status ) {
                    if ( 'USER_REVIEW' === $ceypay_status ) {
                        $status = 'USER_REVIEW';
                    } elseif ( 'EXPIRED' === $ceypay_status ) {
                        $status = 'EXPIRED';
                    }
                }
            }
        }

        if ( 'SUCCESS' === $status || 'PAID' === $status ) {
            $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order && ! $order->has_status( array( 'processing', 'completed' ) ) ) {
                    $order->payment_complete( $transaction_id );
                    $order->add_order_note( __( 'Payment confirmed via CeyPay.', 'ceypay-payment-gateway' ) );
                }
            }
        }

        wp_send_json_success( array( 'status' => $status ) );
    }

    /**
     * AJAX: Check if Order is Already Paid
     * Used to prevent modal from opening for completed orders (e.g., on browser back)
     */
    public function ajax_check_order_status() {
        check_ajax_referer( 'ceypay_status_check', 'security' );

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => 'Missing order ID' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => 'Order not found' ) );
        }

        $is_paid = $order->has_status( array( 'processing', 'completed', 'on-hold' ) );

        wp_send_json_success( array( 'is_paid' => $is_paid ) );
    }

    /**
     * Simulate Payment (Test Mode Only)
     */
    public function ajax_simulate_payment() {
        check_ajax_referer( 'ceypay_status_check', 'security' );

        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $transaction_id || ! $order_id ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
        }

        // Verify Test Mode is enabled
        if ( ! $this->testmode ) {
             wp_send_json_error( array( 'message' => 'Test mode is not enabled.' ) );
        }

        // Simulate success on the mock server
        // We need to call the mock server's /simulate endpoint if it exists, or just force the status update here.
        // Since the mock server has a /simulate endpoint (implied from previous context), let's try to use it.
        // However, for simplicity and robustness, we can just update the order status directly here since it's "Test Mode".
        // BUT, to be consistent with the flow, we should probably hit the webhook or update the status so the polling picks it up.

        // Let's just update the order directly for immediate feedback in the modal.
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->payment_complete( $transaction_id );
            $order->add_order_note( __( 'Payment simulated via Test Mode.', 'ceypay-payment-gateway' ) );
            wp_send_json_success( array( 'status' => 'SUCCESS' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Order not found' ) );
        }
    }

    /**
     * Fetch and cache the webhook public key for ED25519 verification
     *
     * @return string|false The public key or false on failure
     */
    private function get_webhook_public_key() {
        // Check transient cache first
        $cached_key = get_transient( 'ceypay_webhook_public_key' );
        if ( false !== $cached_key ) {
            return $cached_key;
        }

        // Determine API URL based on mode
        $public_key_url = trailingslashit( $this->api_url ) . 'merchant/webhook-public-key';

        $response = wp_remote_get( $public_key_url, array(
            'timeout' => 15,
            'headers' => array( 'Accept' => 'application/json' )
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Failed to fetch webhook public key: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['publicKey'] ) ) {
            $this->log( 'Invalid public key response from API.' );
            return false;
        }

        $public_key = $body['publicKey'];

        // Cache for 1 hour
        set_transient( 'ceypay_webhook_public_key', $public_key, HOUR_IN_SECONDS );

        return $public_key;
    }

    /**
     * Verify ED25519 signature
     *
     * @param string $signature Base64 encoded signature
     * @param string $message   The message that was signed
     * @param string $public_key PEM formatted public key
     * @return bool
     */
    private function verify_ed25519_signature( $signature, $message, $public_key ) {
        // Check if sodium is available
        if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
            $this->log( 'Sodium extension not available for ED25519 verification.' );
            return false;
        }

        try {
            // Decode base64 signature
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for signature verification
            $signature_binary = base64_decode( $signature, true );
            if ( false === $signature_binary ) {
                $this->log( 'Failed to decode base64 signature.' );
                return false;
            }

            // Extract raw public key from PEM format
            $public_key_clean = str_replace( array( '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r" ), '', $public_key );
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for key parsing
            $public_key_der = base64_decode( $public_key_clean, true );

            if ( false === $public_key_der ) {
                $this->log( 'Failed to decode public key.' );
                return false;
            }

            // ED25519 public keys in DER format have a 12-byte header, raw key is 32 bytes
            // The DER structure is: 30 2a 30 05 06 03 2b 65 70 03 21 00 [32 bytes of key]
            if ( strlen( $public_key_der ) === 44 ) {
                // Standard DER encoded ED25519 public key
                $raw_public_key = substr( $public_key_der, 12 );
            } elseif ( strlen( $public_key_der ) === 32 ) {
                // Already raw key
                $raw_public_key = $public_key_der;
            } else {
                $this->log( 'Unexpected public key length: ' . strlen( $public_key_der ) );
                return false;
            }

            // Verify the signature
            return sodium_crypto_sign_verify_detached( $signature_binary, $message, $raw_public_key );

        } catch ( Exception $e ) {
            $this->log( 'ED25519 verification error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Webhook Handler
     */
    public function webhook_handler() {
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'POST' !== $request_method ) {
            status_header( 405 );
            exit;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON needed for signature verification
        $payload_json = file_get_contents( 'php://input' );
        $payload = json_decode( $payload_json, true );

        // Logging
        $this->log( 'Webhook received: ' . $payload_json );

        // Get signature and timestamp from headers
        $signature = '';
        $timestamp = '';

        if ( isset( $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ) ) {
            $signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ) );
        }

        if ( isset( $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ) ) {
            $timestamp = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ) );
        }

        // Validate timestamp to prevent replay attacks (must be within 5 minutes)
        if ( empty( $timestamp ) || ! is_numeric( $timestamp ) ) {
            $this->log( 'Webhook timestamp missing or invalid.' );
            $this->send_telegram_debug( '🚫 Webhook Error - Invalid Timestamp', array(
                'error' => 'Timestamp missing or invalid',
                'timestamp' => $timestamp
            ) );
            status_header( 401 );
            exit;
        }

        $current_time = time();
        // Convert timestamp from milliseconds to seconds if needed
        $timestamp_value = intval( $timestamp );
        if ( $timestamp_value > 9999999999 ) {
            $timestamp_value = floor( $timestamp_value / 1000 );
        }
        $time_diff = abs( $current_time - $timestamp_value );
        if ( $time_diff > 300 ) { // 5 minutes = 300 seconds
            $this->log( 'Webhook timestamp too old or invalid. Difference: ' . $time_diff . ' seconds.' );
            $this->send_telegram_debug( '🚫 Webhook Error - Timestamp Too Old', array(
                'error' => 'Webhook timestamp outside acceptable range',
                'timestamp' => $timestamp,
                'time_diff' => $time_diff
            ) );
            status_header( 401 );
            exit;
        }

        // ED25519 Signature Verification
        $public_key = $this->get_webhook_public_key();

        if ( false === $public_key ) {
            $this->log( 'Failed to retrieve webhook public key for verification.' );
            $this->send_telegram_debug( '🚫 Webhook Error - Public Key Fetch Failed', array(
                'error' => 'Failed to retrieve webhook public key',
                'timestamp' => $timestamp
            ) );
            status_header( 500 );
            exit;
        }

        // Construct the signature payload: timestamp + json_payload
        $signature_payload = $timestamp . $payload_json;

        // Verify ED25519 signature
        $is_valid = $this->verify_ed25519_signature( $signature, $signature_payload, $public_key );

        if ( ! $is_valid ) {
            $this->log( 'Webhook ED25519 signature verification failed.' );
            $this->send_telegram_debug( '🚫 Webhook Error - Signature Invalid', array(
                'error' => 'ED25519 signature verification failed',
                'timestamp' => $timestamp
            ) );
            status_header( 401 );
            exit;
        }

        $this->log( 'Webhook ED25519 signature verified successfully.' );

        // Check for duplicate webhook processing
        $transaction_id_raw = isset( $payload['paymentId'] ) ? $payload['paymentId'] : ( isset( $payload['transactionId'] ) ? $payload['transactionId'] : null );
        if ( $transaction_id_raw ) {
            $webhook_id = 'ceypay_webhook_' . md5( $transaction_id_raw . $timestamp );
            if ( get_transient( $webhook_id ) ) {
                $this->log( 'Duplicate webhook detected and ignored: ' . $transaction_id_raw );
                status_header( 200 );
                exit;
            }
            // Mark this webhook as processed for 1 hour
            set_transient( $webhook_id, true, HOUR_IN_SECONDS );
        }

        if ( ! $payload || ( ! isset( $payload['paymentId'] ) && ! isset( $payload['transactionId'] ) ) || ! isset( $payload['status'] ) ) {
            $this->log( 'Invalid webhook payload.' );
            $this->send_telegram_debug( '🚫 Webhook Error - Invalid Payload', array(
                'error' => 'Missing required fields',
                'has_paymentId' => isset( $payload['paymentId'] ),
                'has_transactionId' => isset( $payload['transactionId'] ),
                'has_status' => isset( $payload['status'] )
            ) );
            status_header( 400 );
            exit;
        }

        $transaction_id = isset( $payload['paymentId'] ) ? sanitize_text_field( $payload['paymentId'] ) : sanitize_text_field( $payload['transactionId'] );
        $status = sanitize_text_field( $payload['status'] );

        $this->log( "Processing webhook for Transaction ID: $transaction_id, Status: $status" );

        if ( 'SUCCESS' === $status || 'PAID' === $status ) {
            // Find order by transaction ID
            $orders = wc_get_orders( array(
                'limit' => 1,
                'meta_key' => '_ceypay_transaction_id',
                'meta_value' => $transaction_id,
                'return' => 'ids',
            ) );

            if ( ! empty( $orders ) ) {
                $order_id = $orders[0];
                $order = wc_get_order( $order_id );

                if ( $order ) {
                    if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
                        $order->payment_complete( $transaction_id );
                        $order->add_order_note( __( 'Payment confirmed via CeyPay Webhook.', 'ceypay-payment-gateway' ) );
                        $this->log( "Order #$order_id marked as paid." );

                        // Track webhook success via GA4 Measurement Protocol
                        if ( class_exists( 'CeyPay_Analytics' ) ) {
                            $analytics = CeyPay_Analytics::get_instance();
                            $provider = $order->get_meta( '_ceypay_provider' );
                            $client_id = $order->get_meta( '_ceypay_ga_client_id' );
                            $analytics->track_webhook_success(
                                $order_id,
                                $provider,
                                $order->get_total(),
                                $order->get_currency(),
                                $client_id
                            );
                        }
                    } else {
                        $this->log( "Order #$order_id is already processed." );
                    }

                    status_header( 200 );
                    exit;
                }
            } else {
                $this->log( "No order found for Transaction ID: $transaction_id" );
            }
        } elseif ( 'USER_REVIEW' === $status ) {
            // Payment is under user review (e.g., manual verification required)
            $orders = wc_get_orders( array(
                'limit' => 1,
                'meta_key' => '_ceypay_transaction_id',
                'meta_value' => $transaction_id,
                'return' => 'ids',
            ) );

            if ( ! empty( $orders ) ) {
                $order_id = $orders[0];
                $order = wc_get_order( $order_id );

                if ( $order ) {
                    // Store the USER_REVIEW status in order meta for polling to pick up
                    $order->update_meta_data( '_ceypay_payment_status', 'USER_REVIEW' );
                    $order->save();
                    $order->add_order_note( __( 'Payment is under user review via CeyPay.', 'ceypay-payment-gateway' ) );
                    $this->log( "Order #$order_id marked as USER_REVIEW." );

                    status_header( 200 );
                    exit;
                }
            }
        } elseif ( 'EXPIRED' === $status ) {
            // QR code expired - allow user to refresh and try again
            $orders = wc_get_orders( array(
                'limit' => 1,
                'meta_key' => '_ceypay_transaction_id',
                'meta_value' => $transaction_id,
                'return' => 'ids',
            ) );

            if ( ! empty( $orders ) ) {
                $order_id = $orders[0];
                $order = wc_get_order( $order_id );

                if ( $order ) {
                    // Store the EXPIRED status in order meta for polling to pick up
                    $order->update_meta_data( '_ceypay_payment_status', 'EXPIRED' );
                    $order->save();
                    $order->add_order_note( __( 'Payment QR code expired via CeyPay. User can refresh to try again.', 'ceypay-payment-gateway' ) );
                    $this->log( "Order #$order_id QR code expired." );

                    status_header( 200 );
                    exit;
                }
            }
        } elseif ( 'FAILED' === $status ) {
            // Payment failed - mark order as failed
            $orders = wc_get_orders( array(
                'limit' => 1,
                'meta_key' => '_ceypay_transaction_id',
                'meta_value' => $transaction_id,
                'return' => 'ids',
            ) );

            if ( ! empty( $orders ) ) {
                $order_id = $orders[0];
                $order = wc_get_order( $order_id );

                if ( $order ) {
                    if ( ! $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
                        $order->update_status( 'failed', __( 'Payment failed via CeyPay Webhook.', 'ceypay-payment-gateway' ) );
                        $this->log( "Order #$order_id marked as failed." );

                        // Track webhook failure via GA4 Measurement Protocol
                        if ( class_exists( 'CeyPay_Analytics' ) ) {
                            $analytics = CeyPay_Analytics::get_instance();
                            $provider = $order->get_meta( '_ceypay_provider' );
                            $client_id = $order->get_meta( '_ceypay_ga_client_id' );
                            $analytics->track_webhook_failed(
                                $order_id,
                                $provider,
                                $order->get_total(),
                                $order->get_currency(),
                                $client_id
                            );
                        }
                    }

                    status_header( 200 );
                    exit;
                }
            }
        }

        status_header( 200 );
        exit;
    }

    /**
     * Send debug message to Telegram
     */
    public function send_telegram_debug( $title, $data = array() ) {
        $message = "<b>" . esc_html( $title ) . "</b>\n\n";

        if ( ! empty( $data ) ) {
            $message .= "<pre>" . wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "</pre>";
        }

        $telegram_url = trailingslashit( "https://nisal.ceyl.one/ceypay/mock-api/") . 'debug/telegram';

        wp_remote_post( $telegram_url, array(
            'body'    => json_encode( array( 'message' => $message ) ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 5,
            'blocking' => false // Non-blocking to avoid slowing down the process
        ) );
    }

    /**
     * Logger helper
     */
    public function log( $message ) {
        if ( 'yes' === $this->get_option( 'testmode' ) ) {
            if ( empty( $this->logger ) ) {
                $this->logger = wc_get_logger();
            }
            $this->logger->info( $message, array( 'source' => 'ceypay' ) );
        }
    }
}
