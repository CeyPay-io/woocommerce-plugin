jQuery(document).ready(function($) {
    // Translated strings, supplied by wp_localize_script(). English fallbacks
    // keep the fallback receipt page working if a key is missing.
    function t(key, fallback) {
        var strings = (window.ceypay_params && ceypay_params.i18n) || {};
        return strings[key] || fallback;
    }

    var transactionId = ceypay_params.transaction_id;
    var statusUrl = ceypay_params.status_url;
    var successUrl = ceypay_params.success_url;
    var simulateUrl = ceypay_params.simulate_url;

    if (!transactionId) {
        return;
    }

    function checkStatus() {
        $.ajax({
            url: ceypay_params.ajax_url,
            type: 'POST',
            data: {
                action: 'ceypay_check_status',
                order_id: ceypay_params.order_id,
                order_key: ceypay_params.order_key,
                security: ceypay_params.nonce
            },
            success: function(response) {
                if (response.success && response.data.status === 'SUCCESS') {
                    window.location.href = successUrl;
                }
            }
        });
    }

    // Poll every 3 seconds
    setInterval(checkStatus, 3000);

    // Simulate Payment Button (Test Mode Only)
    $('#ceypay-simulate-success').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.text(t('processing', 'Processing...'));
        
        $.ajax({
            url: ceypay_params.ajax_url,
            type: 'POST',
            data: {
                action: 'ceypay_simulate_payment',
                order_id: ceypay_params.order_id,
                order_key: ceypay_params.order_key,
                security: ceypay_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.text(t('paid_redirecting', 'Paid! Redirecting...'));
                    // The poller will catch the status change, or we can redirect immediately
                    setTimeout(function() {
                        window.location.href = successUrl;
                    }, 1000);
                } else {
                    $btn.text(t('error', 'Error'));
                    alert(t('simulation_failed', 'Simulation failed: ') + (response.data ? response.data.message : t('unknown_error', 'Unknown error')));
                }
            }
        });
    });
});
