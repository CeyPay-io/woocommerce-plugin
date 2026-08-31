<?php
if (! defined('ABSPATH')) exit;
/**
 * CeyPay Review Prompt
 *
 * Asks for a WordPress.org review, but only after the store has actually been
 * paid through CeyPay several times. Nothing here talks to a remote service:
 * the counter is a local option, and the prompt is a plain admin notice.
 *
 * The ask is deliberately late and quiet. WordPress.org guideline 11 treats
 * persistent or premature admin notices as an abuse of the dashboard, so this
 * one is dismissible, scoped to screens where a store owner is already thinking
 * about CeyPay, and never returns once it has been answered.
 */

// Real payments required before the prompt appears at all.
if (! defined('CEYPAY_REVIEW_THRESHOLD')) {
    define('CEYPAY_REVIEW_THRESHOLD', 10);
}

// How long "Maybe later" defers the prompt.
if (! defined('CEYPAY_REVIEW_SNOOZE_DAYS')) {
    define('CEYPAY_REVIEW_SNOOZE_DAYS', 14);
}

if (! defined('CEYPAY_REVIEW_URL')) {
    define('CEYPAY_REVIEW_URL', 'https://wordpress.org/support/plugin/ceypay-payment-gateway/reviews/#new-post');
}

if (! defined('CEYPAY_SUPPORT_URL')) {
    define('CEYPAY_SUPPORT_URL', 'https://wordpress.org/support/plugin/ceypay-payment-gateway/');
}

/**
 * Count a completed CeyPay payment.
 *
 * Hooked to payment completion rather than the individual call sites, because
 * an order can be settled from the return handler, the status poll or the
 * webhook, and all three funnel through WC_Order::payment_complete().
 *
 * Two things are deliberately excluded from the count: orders paid through
 * another gateway, and anything taken while the gateway is in test mode. A
 * prompt triggered by simulated payments would be asking for a review of
 * something the merchant has not actually put into service yet.
 *
 * @param int $order_id Order that was just paid.
 */
function ceypay_count_successful_payment($order_id) {
    $order = wc_get_order($order_id);
    if (! $order || 'ceypay' !== $order->get_payment_method()) {
        return;
    }

    $settings = get_option('woocommerce_ceypay_settings', array());
    if (is_array($settings) && isset($settings['testmode']) && 'yes' === $settings['testmode']) {
        return;
    }

    // payment_complete() is guarded at each call site but can still be reached
    // twice for one order (a webhook arriving while the poll is in flight), so
    // the order itself records whether it has already been counted.
    if ($order->get_meta('_ceypay_counted_for_review')) {
        return;
    }

    $order->update_meta_data('_ceypay_counted_for_review', 1);
    $order->save();

    $count = absint(get_option('ceypay_successful_payments', 0));
    update_option('ceypay_successful_payments', $count + 1, false);
}
add_action('woocommerce_payment_complete', 'ceypay_count_successful_payment');

/**
 * Handle the prompt's three answers.
 *
 * "Leave a review" redirects off-site, so it cannot both open WordPress.org and
 * call back here. Routing the click through this handler first means the prompt
 * is retired whether or not the review is actually written -- having asked once
 * and been taken up on it is enough.
 */
function ceypay_handle_review_notice_action() {
    if (! isset($_GET['ceypay_review_action'])) {
        return;
    }

    if (! current_user_can('manage_options')) {
        return;
    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (! wp_verify_nonce($nonce, 'ceypay_review_action')) {
        return;
    }

    $action  = sanitize_key(wp_unslash($_GET['ceypay_review_action']));
    $user_id = get_current_user_id();

    if ('later' === $action) {
        update_user_meta($user_id, 'ceypay_review_notice_snooze_until', time() + (CEYPAY_REVIEW_SNOOZE_DAYS * DAY_IN_SECONDS));
    } else {
        update_user_meta($user_id, 'ceypay_review_notice_dismissed', 1);
    }

    // Both off-site destinations are plugin constants, never user input, so the
    // open redirect that wp_safe_redirect() guards against cannot arise here.
    if ('review' === $action) {
        wp_redirect(CEYPAY_REVIEW_URL);
        exit;
    }

    if ('support' === $action) {
        wp_redirect(CEYPAY_SUPPORT_URL);
        exit;
    }

    wp_safe_redirect(remove_query_arg(array('ceypay_review_action', '_wpnonce')));
    exit;
}
add_action('admin_init', 'ceypay_handle_review_notice_action');

/**
 * Show the review prompt.
 *
 * Checks run cheapest first so the payment counter is only read on the handful
 * of screens that could display the notice, rather than on every admin page.
 */
function ceypay_review_notice() {
    if (! current_user_can('manage_options')) {
        return;
    }

    // Screens where the merchant is already looking at their store or at
    // CeyPay. Deliberately excludes the post editor and the wider admin.
    $screen  = get_current_screen();
    $screens = array(
        'dashboard',
        'plugins',
        'woocommerce_page_wc-admin',
        'woocommerce_page_wc-settings',
        'woocommerce_page_wc-orders', // HPOS orders list
        'edit-shop_order',            // legacy orders list
    );
    if (! $screen || ! in_array($screen->id, $screens, true)) {
        return;
    }

    $user_id = get_current_user_id();

    if (get_user_meta($user_id, 'ceypay_review_notice_dismissed', true)) {
        return;
    }

    $snoozed_until = absint(get_user_meta($user_id, 'ceypay_review_notice_snooze_until', true));
    if ($snoozed_until && time() < $snoozed_until) {
        return;
    }

    $count = absint(get_option('ceypay_successful_payments', 0));
    if ($count < CEYPAY_REVIEW_THRESHOLD) {
        return;
    }

    $review_url  = wp_nonce_url(add_query_arg('ceypay_review_action', 'review'), 'ceypay_review_action');
    $later_url   = wp_nonce_url(add_query_arg('ceypay_review_action', 'later'), 'ceypay_review_action');
    $support_url = wp_nonce_url(add_query_arg('ceypay_review_action', 'support'), 'ceypay_review_action');
    ?>
    <div class="notice notice-info is-dismissible" style="border-left: 4px solid #1C6EF5; padding: 20px; margin-top: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center;">
            <div style="margin-right: 20px;">
                <img src="<?php echo esc_url(CEYPAY_PLUGIN_URL . 'assets/images/ceypay-symbol.png'); ?>" style="height: 60px; width: auto;" alt="CeyPay">
            </div>
            <div>
                <h3 style="margin: 0 0 10px; color: #333; font-size: 18px;">
                    <?php
                    printf(
                        /* translators: %s: number of payments taken through CeyPay. */
                        esc_html__('You have taken %s payments with CeyPay', 'ceypay-payment-gateway'),
                        esc_html(number_format_i18n($count))
                    );
                    ?>
                </h3>
                <p style="margin: 0 0 15px; font-size: 14px; color: #555;">
                    <?php esc_html_e('If the plugin has been good to your store, a short review on WordPress.org helps other merchants find it. If something is not working, tell us instead and we will fix it.', 'ceypay-payment-gateway'); ?>
                </p>
                <a href="<?php echo esc_url($review_url); ?>" class="button button-primary button-large" style="background-color: #1C6EF5; border-color: #1C6EF5;" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Leave a review', 'ceypay-payment-gateway'); ?>
                </a>
                <a href="<?php echo esc_url($support_url); ?>" style="margin-left: 12px; font-size: 13px;">
                    <?php esc_html_e('I need help', 'ceypay-payment-gateway'); ?>
                </a>
                <a href="<?php echo esc_url($later_url); ?>" style="margin-left: 12px; font-size: 13px;">
                    <?php esc_html_e('Maybe later', 'ceypay-payment-gateway'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
add_action('admin_notices', 'ceypay_review_notice');
