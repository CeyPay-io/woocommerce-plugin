jQuery(document).ready(function($) {
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
        $btn.text('Processing...');
        
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
                    $btn.text('Paid! Redirecting...');
                    // The poller will catch the status change, or we can redirect immediately
                    setTimeout(function() {
                        window.location.href = successUrl;
                    }, 1000);
                } else {
                    $btn.text('Error');
                    alert('Simulation failed: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            }
        });
    });
});
