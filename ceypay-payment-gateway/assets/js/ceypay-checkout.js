jQuery(document).ready(function($) {
    var pollInterval; // Global polling interval variable

    /**
     * Translated string lookup.
     *
     * Strings come from wp_localize_script() so they pass through the .pot
     * file. The English fallback keeps the modal working if a key is ever
     * missing rather than rendering "undefined" at checkout.
     *
     * @param {string} key      Key from WC_Gateway_CeyPay::get_i18n_strings().
     * @param {string} fallback English text.
     * @return {string}
     */
    function t(key, fallback) {
        var strings = (window.ceypay_params && ceypay_params.i18n) || {};
        return strings[key] || fallback;
    }

    /**
     * Substitute %s in a translated string. Translators may reorder text
     * around the placeholder, so never concatenate around it.
     *
     * @param {string} template String containing %s.
     * @param {string} value    Replacement.
     * @return {string}
     */
    function sprintf1(template, value) {
        return String(template).replace('%s', value);
    }

    // Helper function to format numbers with commas
    function formatNumberWithCommas(num) {
        var parts = num.toString().split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }

    // Helper function to convert string to title case
    function toTitleCase(str) {
        return str.replace(/\w\S*/g, function(txt) {
            return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
        });
    }

    // Keyboard support for div[role="button"] elements
    $(document).on('keydown', '[role="button"]', function(e) {
        // Enter or Space key triggers click
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    // Pre-compute branding HTML before building template
    var brandingHtml = ceypay_params.show_branding === '1'
        ? '<div class="ceypay-footer-powered"><span>' + t('powered_by', 'Powered by') + '</span><img src="' + ceypay_params.assets_url + 'images/ceypay-logo.svg" alt="CeyPay" class="ceypay-footer-logo" data-tooltip="v' + ceypay_params.version + '"></div>'
        : '';

    // Modal HTML Template
    var modalTemplate = `
        <div id="ceypay-modal" class="ceypay-modal">
            <div class="ceypay-modal__backdrop"></div>
            <div class="ceypay-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ceypay-provider-title">
                <!-- Clean Header with Back, Title, and Help -->
                <div class="ceypay-modal__header">
                    <div class="ceypay-back-btn" role="button" tabindex="0" aria-label="${t('back_to_providers', 'Back to providers')}" style="display: none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="ceypay-header-center">
                        <h2 class="ceypay-modal__title" id="ceypay-provider-title">${t('select_provider', 'Select provider')}</h2>
                        <span class="ceypay-testmode-badge ceypay-testmode-badge--modal" id="ceypay-testmode-badge" style="display: none;">
                            <svg class="ceypay-sandbox-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="3" y1="9" x2="21" y2="9"></line>
                                <line x1="9" y1="21" x2="9" y2="9"></line>
                            </svg>
                            Sandbox
                        </span>
                    </div>
                    <div class="ceypay-help-btn" role="button" tabindex="0" aria-label="Help">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="currentColor"/>
                        </svg>
                    </div>
                    <div class="ceypay-modal__close" role="button" tabindex="0" aria-label="${t('close_modal', 'Close CeyPay modal')}">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 1L13 13M1 13L13 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                <!-- Subtitle -->
                <p class="ceypay-modal__subtitle" id="ceypay-subtitle">${t('choose_method', 'Choose your preferred payment method.')}</p>
                <div id="ceypay-testmode-alert" class="ceypay-alert-box" style="display: none;">
                    <div class="ceypay-alert-content">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <span>${t('test_mode_active', 'Test mode active: No real funds will be deducted.')}</span>
                    </div>
                </div>

                <!-- Error Message -->
                <div id="ceypay-error-message" class="ceypay-error-message" style="display: none;"></div>

                <!-- Provider Selection View -->
                <div id="ceypay-view-provider" class="ceypay-modal__body">
                    <div class="ceypay-provider-list">

                        <div class="ceypay-provider-btn" role="button" tabindex="0" data-provider="BYBIT">
                            <div class="ceypay-provider-icon">
                                <img src="${ceypay_params.assets_url}images/bybit.svg" alt="Bybit">
                            </div>
                            <div class="ceypay-provider-content">
                                <span class="ceypay-provider-name">Bybit Pay</span>
                                <span class="ceypay-provider-desc">${sprintf1(t('pay_via', 'Pay with crypto via %s'), 'Bybit')}</span>
                            </div>
                            <div class="ceypay-chevron">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ceypay-provider-btn" role="button" tabindex="0" data-provider="BINANCE">
                            <div class="ceypay-provider-icon">
                                <img src="${ceypay_params.assets_url}images/binance.svg" alt="Binance Pay">
                            </div>
                            <div class="ceypay-provider-content">
                                <span class="ceypay-provider-name">Binance Pay</span>
                                <span class="ceypay-provider-desc">${sprintf1(t('pay_via', 'Pay with crypto via %s'), 'Binance')}</span>
                            </div>
                            <div class="ceypay-chevron">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ceypay-provider-btn" role="button" tabindex="0" data-provider="KUCOIN">
                            <div class="ceypay-provider-icon">
                                <img src="${ceypay_params.assets_url}images/kucoin-logo.svg" alt="KuCoin Pay">
                            </div>
                            <div class="ceypay-provider-content">
                                <span class="ceypay-provider-name">KuCoin Pay</span>
                                <span class="ceypay-provider-desc">${sprintf1(t('pay_via', 'Pay with crypto via %s'), 'KuCoin')}</span>
                            </div>
                            <div class="ceypay-chevron">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ceypay-provider-btn ceypay-provider-btn--disabled" role="button" aria-disabled="true">
                            <div class="ceypay-provider-icon">
                                <img src="${ceypay_params.assets_url}images/bitazza.svg" alt="Bitazza">
                            </div>
                            <div class="ceypay-provider-content">
                                <span class="ceypay-provider-name">Freedom Pay</span>
                                <span class="ceypay-provider-desc">${sprintf1(t('pay_via', 'Pay with crypto via %s'), 'Bitazza')}</span>
                            </div>
                            <span class="ceypay-badge">${t('coming_soon', 'Coming soon')}</span>
                        </div>
                        <div class="ceypay-provider-btn ceypay-provider-btn--disabled" role="button" aria-disabled="true">
                            <div class="ceypay-provider-icon">
                                <img src="${ceypay_params.assets_url}images/solana.svg" alt="Solana Pay">
                            </div>
                            <div class="ceypay-provider-content">
                                <span class="ceypay-provider-name">Solana Pay</span>
                                <span class="ceypay-provider-desc">${sprintf1(t('pay_via', 'Pay with crypto via %s'), 'Solana')}</span>
                            </div>
                            <span class="ceypay-badge">${t('coming_soon', 'Coming soon')}</span>
                        </div>
                        <div class="ceypay-provider-btn ceypay-provider-btn--disabled" role="button" aria-disabled="true">
                            <div class="ceypay-provider-icon">
                                <img src="${ceypay_params.assets_url}images/ton_symbol.svg" alt="TON">
                            </div>
                            <div class="ceypay-provider-content">
                                <span class="ceypay-provider-name">TON</span>
                                <span class="ceypay-provider-desc">${sprintf1(t('pay_via', 'Pay with crypto via %s'), 'TON')}</span>
                            </div>
                            <span class="ceypay-badge">${t('coming_soon', 'Coming soon')}</span>
                        </div>
                    </div>
                </div>

                <!-- QR Code View -->
                <div id="ceypay-view-qr" class="ceypay-modal__body" style="display: none;">
                    <div class="ceypay-qr">
                        <div class="ceypay-qr__loading">
                            <span class="spinner is-active"></span>
                        </div>
                        <div class="ceypay-qr__overlay">
                            <span class="ceypay-check"><svg viewBox="0 0 20 20"><path d="M4 10.5 8 14.5 16 6" /></svg></span>
                        </div>
                        <img id="ceypay-qr-image" src="" alt="${t('qr_code_alt', 'CeyPay QR code')}" style="display:none;" onload="this.style.display='block'; var w=this.closest('.ceypay-qr'); var l=w&&w.querySelector('.ceypay-qr__loading'); if(l){l.style.display='none';}" />
                        <!-- <div class="ceypay-qr__logo">
                            <img src="${ceypay_params.assets_url}images/ceypay-symbol.png" alt="CeyPay" />
                        </div> -->
                    </div>
                    <div id="ceypay-qr-price" class="ceypay-qr-price"></div>
                    <div id="ceypay-status-message" class="ceypay-status">
                        ${t('waiting_payment', 'Waiting for payment...')} <span class="ceypay-ripple"></span>
                    </div>
                </div>

                <div class="ceypay-modal__actions" id="ceypay-actions" style="display: none;">
                    <a id="ceypay-deep-link" href="#" target="_blank" class="ceypay-btn ceypay-btn--primary">${t('open_app_generic', 'Open app')}</a>
                    <div id="ceypay-simulate-success" class="ceypay-btn ceypay-btn--ghost" role="button" tabindex="0" style="display: none;">${t('simulate_success', 'Simulate success (test mode)')}</div>
                </div>

                <!-- Modal Footer -->
                <div class="ceypay-modal__footer">
                    <p class="ceypay-footer-text">
                        By continuing, you agree to<br>
                        our <a href="https://www.ceypay.io/legal/terms" target="_blank" rel="noopener noreferrer" class="ceypay-footer-link">${t('terms_of_service', 'Terms of Service')}</a> & <a href="https://www.ceypay.io/legal/privacy" target="_blank" rel="noopener noreferrer" class="ceypay-footer-link">${t('privacy_policy', 'Privacy Policy')}</a>.
                    </p>
                    ${brandingHtml}
                </div>
            </div>
        </div>
    `;

    // Append Modal to Body
    $('body').append(modalTemplate);

    // Shared tooltip helper
    function showTooltip(el, html) {
        var tip = $('<div class="ceypay-tooltip">' + html + '</div>');
        $('body').append(tip);
        requestAnimationFrame(function() {
            var rect = el.getBoundingClientRect();
            tip.css({
                top:  (rect.top  - tip.outerHeight() - 8) + 'px',
                left: (rect.left + rect.width / 2 - tip.outerWidth() / 2) + 'px'
            });
            requestAnimationFrame(function() { tip.addClass('is-visible'); });
        });
        return tip;
    }

    function hideTooltip(tipRef) {
        if (tipRef) {
            tipRef.removeClass('is-visible');
            setTimeout(function() { if (tipRef) tipRef.remove(); }, 200);
        }
    }

    // Help Button Tooltip on Hover
    var helpTooltip = null;
    $(document).on('mouseenter', '.ceypay-help-btn', function() {
        helpTooltip = showTooltip(this, 'Need some help?<br>Reach support@ceypay.io');
    });
    $(document).on('mouseleave', '.ceypay-help-btn', function() {
        hideTooltip(helpTooltip);
        helpTooltip = null;
    });

    // Footer CeyPay logo — version tooltip on hover
    var versionTooltip = null;
    $(document).on('mouseenter', '.ceypay-footer-logo', function() {
        var version = $(this).data('tooltip') || 'v' + ceypay_params.version;
        versionTooltip = showTooltip(this, version);
    });
    $(document).on('mouseleave', '.ceypay-footer-logo', function() {
        hideTooltip(versionTooltip);
        versionTooltip = null;
    });

    function renderSuccess() {
        $('.ceypay-qr').addClass('is-success');
        // Use CSS opacity instead of fadeOut to keep layout stable
        $('#ceypay-status-message').css({
            'opacity': '0',
            'visibility': 'hidden',
            'transition': 'opacity 0.3s ease, visibility 0.3s ease'
        });
        // Remove beforeunload handler (set by WooCommerce checkout) to prevent
        // "Leave site?" prompt when redirecting to the success page
        window.onbeforeunload = null;
        $(window).off('beforeunload');
    }

    function renderUserReview() {
        // Only update if not already showing user review state
        if ($('#ceypay-status-message').hasClass('is-user-review')) {
            return;
        }

        $('#ceypay-status-message')
            .addClass('is-user-review')
            .html('Payment under review <span class="ceypay-ripple ceypay-ripple--review"></span>');
    }

    // Track auto-refresh attempts per order
    var autoRefreshAttempts = 0;
    var maxAutoRefresh = 3;

    function refreshQrCode(data, isAuto) {
        var gaClientId = window.CeyPayAnalytics ? window.CeyPayAnalytics.getClientId() : '';

        // Track QR refresh event
        if (window.CeyPayAnalytics) {
            window.CeyPayAnalytics.trackQrRefreshed(data, isAuto ? 'auto' : 'manual', autoRefreshAttempts);
        }

        // Show refreshing state
        if (isAuto) {
            $('#ceypay-status-message')
                .removeClass('is-expired')
                .html('Refreshing QR... <span class="ceypay-ripple"></span>');
            $('.ceypay-qr').removeClass('is-expired');
        }

        $.ajax({
            url: ceypay_params.ajax_url,
            type: 'POST',
            data: {
                action: 'ceypay_generate_qr',
                security: ceypay_params.nonce,
                order_id: data.order_id,
                order_key: data.order_key,
                provider: data.provider,
                ga_client_id: gaClientId
            },
            success: function(response) {
                if (response.success) {
                    // Merge new data with existing
                    var newData = $.extend({}, window.ceypayOrderData, response.data);
                    window.ceypayOrderData = newData;

                    // Restore actions HTML
                    var $actions = $('#ceypay-actions');
                    var actionsHtml = '<a id="ceypay-deep-link" href="#" target="_blank" class="ceypay-btn ceypay-btn--primary">' + sprintf1(t('open_app', 'Open %s app'), toTitleCase(newData.provider)) + '</a>';
                    if (data.test_mode) {
                        actionsHtml += '<div id="ceypay-simulate-success" class="ceypay-btn ceypay-btn--ghost" role="button" tabindex="0">' + t('simulate_success', 'Simulate success (test mode)') + '</div>';
                    }
                    $actions.html(actionsHtml);

                    // Show QR view with new data
                    showQrView(newData);
                } else {
                    if (isAuto) {
                        // Auto-refresh failed, show manual button
                        showManualRefreshButton(data);
                    } else {
                        showCeyPayError(response.data.message || t('qr_refresh_error', 'Failed to refresh QR code'));
                        $('#ceypay-refresh-qr').text(t('refresh_qr', 'Refresh QR code')).prop('disabled', false);
                    }
                }
            },
            error: function() {
                if (isAuto) {
                    // Auto-refresh failed, show manual button
                    showManualRefreshButton(data);
                } else {
                    showCeyPayError(t('connection_error', 'Connection error. Please try again.'));
                    $('#ceypay-refresh-qr').text(t('refresh_qr', 'Refresh QR code')).prop('disabled', false);
                }
            }
        });
    }

    function showManualRefreshButton(data) {
        // Show expired state
        $('.ceypay-qr').addClass('is-expired');
        $('#ceypay-status-message')
            .addClass('is-expired')
            .html('QR code expired');

        // Hide deep link button
        $('#ceypay-deep-link').hide();
        $('#ceypay-simulate-success').hide();

        // Show refresh button
        var $actions = $('#ceypay-actions');
        $actions.html('<div id="ceypay-refresh-qr" class="ceypay-btn ceypay-btn--primary" role="button" tabindex="0">' + t('refresh_qr', 'Refresh QR code') + '</div>');
        $actions.show();

        // Handle manual refresh click
        $('#ceypay-refresh-qr').on('click', function() {
            var $btn = $(this);
            $btn.text(t('refreshing', 'Refreshing...')).prop('disabled', true);

            // Reset states
            $('.ceypay-qr').removeClass('is-expired');
            $('#ceypay-status-message').removeClass('is-expired');

            // Reset auto-refresh counter for new attempt cycle
            autoRefreshAttempts = 0;

            refreshQrCode(data, false);
        });
    }

    function renderExpired(data) {
        autoRefreshAttempts++;

        // Track payment expired
        if (window.CeyPayAnalytics) {
            window.CeyPayAnalytics.trackPaymentExpired(data);
        }

        if (autoRefreshAttempts <= maxAutoRefresh) {
            // Auto-refresh
            refreshQrCode(data, true);
        } else {
            // Max auto-refresh reached, show manual button
            showManualRefreshButton(data);
        }
    }

    // Close Modal Handler
    function closeModal() {
        // Track modal closed event and time spent
        if (window.CeyPayAnalytics && window.ceypayOrderData) {
            var stage = $('#ceypay-view-qr').is(':visible') ? 'qr_display' : 'provider_selection';
            window.CeyPayAnalytics.trackModalClosed(window.ceypayOrderData, stage);
            window.CeyPayAnalytics.trackTimeSpent(window.ceypayOrderData, 'abandoned', stage);
        }

        $('#ceypay-modal').removeClass('is-visible');

        // Restore body scroll when modal is closed
        $('body').css('overflow', '');

        // We continue polling in the background to catch late payments
        // and prevent double-spending if the user pays but closes the modal.

        // Unblock WooCommerce Checkout UI
        $('form.checkout, form#order_review').removeClass('processing').unblock();

        // Remove hash to prevent reopening on refresh (use replaceState to not add to history)
        history.replaceState(null, document.title, window.location.pathname + window.location.search);

        // Remove beforeunload handler to prevent "Leave site?" prompt
        window.onbeforeunload = null;
        $(window).off('beforeunload');

        // Reload the page to reset the Block Checkout state (removes the "Tick" and restores the form)
        window.location.reload();
    }

    $(document).on('click', '.ceypay-modal__close', closeModal);
    $(document).on('click', '.ceypay-modal__backdrop', closeModal);
    $(document).on('click', '.ceypay-back-btn', function() {
        // Track back clicked event
        if (window.CeyPayAnalytics && window.ceypayOrderData) {
            window.CeyPayAnalytics.trackBackClicked(window.ceypayOrderData);
        }

        // Stop polling
        if (pollInterval) clearInterval(pollInterval);

        // Reset provider buttons state
        $('.ceypay-provider-btn').removeClass('is-loading').removeAttr('aria-disabled');

        // Show provider selection with animation
        showProviderSelection(true);
    });
    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Listen for Hash Change (triggered by WooCommerce redirect to #ceypay_modal=...)
    $(window).on('hashchange', function() {
        checkHashForPayment();
    });

    // Check on load too (in case of refresh with hash)
    checkHashForPayment();

    function checkHashForPayment() {
        var hash = window.location.hash;
        if (hash.indexOf('#ceypay_modal=') === 0) {
            var base64Data = hash.replace('#ceypay_modal=', '');
            try {
                var jsonStr = atob(base64Data);
                var data = JSON.parse(jsonStr);

                // Check if order is already completed before opening modal
                if (data.order_id) {
                    $.ajax({
                        url: ceypay_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ceypay_check_order_status',
                            order_id: data.order_id,
                            order_key: data.order_key,
                            security: ceypay_params.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.is_paid) {
                                // Order already paid, clear hash and redirect to success
                                clearHash();
                                if (data.success_url) {
                                    window.onbeforeunload = null;
                                    $(window).off('beforeunload');
                                    window.location.href = data.success_url;
                                }
                            } else {
                                // Order not paid, open the modal
                                openPaymentModal(data);
                            }
                        },
                        error: function() {
                            // On error, still open modal (let it handle the state)
                            openPaymentModal(data);
                        }
                    });
                } else {
                    openPaymentModal(data);
                }
            } catch (e) {
                console.error('Error decoding CeyPay data', e);
            }
        }
    }

    function clearHash() {
        history.replaceState(null, document.title, window.location.pathname + window.location.search);
    }

    function openPaymentModal(data) {
        // Store order data globally for this session
        window.ceypayOrderData = data;
        window.ceypaySelectedProvider = null; // Track current provider for switch detection

        // Show/hide test mode badge AND alert
        if (data.test_mode) {
            $('#ceypay-testmode-badge').show();
            $('#ceypay-testmode-alert').show();
        } else {
            $('#ceypay-testmode-badge').hide();
            $('#ceypay-testmode-alert').hide();
        }

        // Track modal open event and start engagement timer
        if (window.CeyPayAnalytics) {
            window.CeyPayAnalytics.trackModalOpen(data);
            window.CeyPayAnalytics.startTimer('modal_' + data.order_id);
        }

        // Lock body scroll when modal is open
        $('body').css('overflow', 'hidden');

        if (data.qr_code_url) {
            // Resume payment (QR already generated)
            showQrView(data);
        } else {
            // Initial state: Select Provider
            showProviderSelection();
        }

        $('#ceypay-modal').addClass('is-visible');
    }

    /**
     * Swap modal views while animating the dialog's height between them.
     *
     * The dialog is height:auto so each view sizes to its own content (the
     * provider list is taller than the QR view). Without this the swap snaps
     * from one height to the other. Height cannot be transitioned from/to
     * `auto` in CSS, so measure both ends and drive it in pixels, then hand
     * control back to `auto` so the modal stays responsive afterwards.
     *
     * @param {Function} swap Mutates the DOM to show the new view.
     */
    function morphDialogHeight(swap) {
        var el = document.querySelector('.ceypay-modal__dialog');
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!el || reduced) {
            swap();
            return;
        }

        var from = el.getBoundingClientRect().height;

        swap();

        // Measure the new view's natural height, then restore the old one so
        // the browser has a definite start value to animate away from.
        el.style.height = 'auto';
        var to = Math.min(el.getBoundingClientRect().height, window.innerHeight * 0.9);
        el.style.height = from + 'px';

        // Force a reflow so the two heights are separate style resolutions,
        // otherwise the browser coalesces them and no transition runs.
        void el.offsetHeight;

        el.classList.add('is-morphing');
        el.style.height = to + 'px';

        var done = function (e) {
            if (e && e.target !== el) { return; }        // ignore bubbling from children
            if (e && e.propertyName !== 'height') { return; }
            el.removeEventListener('transitionend', done);
            clearTimeout(fallback);
            el.classList.remove('is-morphing');
            el.style.height = '';                        // back to auto
        };

        // transitionend can be missed (tab hidden, interrupted swap), which
        // would leave the height pinned. Always clear it.
        var fallback = setTimeout(done, 700);
        el.addEventListener('transitionend', done);
    }

    function showProviderSelection(animate) {
        $('#ceypay-provider-title').text('Select provider');
        $('#ceypay-subtitle').text('Choose your preferred payment method.').show();

        // Ensure test mode alert is shown if active
        if (window.ceypayOrderData && window.ceypayOrderData.test_mode) {
             $('#ceypay-testmode-alert').show();
        }

        $('#ceypay-error-message').hide();
        $('#ceypay-actions').hide();
        $('.ceypay-back-btn').hide();

        if (animate && $('#ceypay-view-qr').is(':visible')) {
            // Animate QR view out, then provider view in
            var $qrView = $('#ceypay-view-qr');
            var $providerView = $('#ceypay-view-provider');

            $qrView.addClass('ceypay-view--exit');

            setTimeout(function() {
                // Use requestAnimationFrame for smoother rendering
                requestAnimationFrame(function() {
                    morphDialogHeight(function () {
                        $qrView.hide().removeClass('ceypay-view--exit ceypay-view--enter');
                        $providerView.show().addClass('ceypay-view--enter');
                    });
                    setTimeout(function() {
                        $providerView.removeClass('ceypay-view--enter');
                    }, 250);
                });
            }, 250);
        } else {
            $('#ceypay-view-provider').show();
            $('#ceypay-view-qr').hide();
        }
    }

    function showQrView(data) {
        // Reset auto-refresh counter when showing new QR from provider selection
        if ($('#ceypay-view-provider').is(':visible')) {
            autoRefreshAttempts = 0;
        }

        // Track QR displayed event
        if (window.CeyPayAnalytics) {
            window.CeyPayAnalytics.trackQrDisplayed(data);
        }

        $('#ceypay-provider-title').text(sprintf1(t('pay_with', 'Pay with %s'), toTitleCase(data.provider)));
        $('#ceypay-subtitle').hide();
        $('#ceypay-testmode-alert').hide(); // Hide test mode alert on QR screen to save space

        var priceHtml = '<div style="text-align: center;">' + t('scan_instruction', 'Scan the QR or open your app to finish checkout.') + '</div>';
        if (data.fee_breakdown && data.fee_breakdown.netAmountUSDT && data.currency_amount) {
             var usdtAmount = parseFloat(parseFloat(data.fee_breakdown.netAmountUSDT).toFixed(8));
             var formattedCurrencyAmount = formatNumberWithCommas(parseFloat(data.currency_amount).toFixed(2));
             var currencySymbol = data.currency_code === 'LKR' ? 'රු.' : data.currency_code;
             priceHtml = '<span style="font-weight:500">Pay</span> <span class="ceypay-amount-highlight">' + formatNumberWithCommas(usdtAmount) + ' USDT</span> <span class="ceypay-amount-secondary">(' + currencySymbol + ' ' + formattedCurrencyAmount + ')</span>';
        } else if (data.amount && data.currency_amount) {
             var formattedCurrencyAmount = formatNumberWithCommas(parseFloat(data.currency_amount).toFixed(2));
             priceHtml = '<span style="font-weight:500">Pay</span> <span class="ceypay-amount-highlight">' + formatNumberWithCommas(parseFloat(data.amount).toFixed(3)) + ' ' + data.currency + '</span> <span class="ceypay-amount-secondary">(' + formattedCurrencyAmount + ' ' + data.currency_code + ')</span>';
        }
        $('#ceypay-qr-price').html(priceHtml);

        var $providerView = $('#ceypay-view-provider');
        var $qrView = $('#ceypay-view-qr');

        if ($providerView.is(':visible')) {
            // Animate provider view out, then QR view in
            $providerView.addClass('ceypay-view--exit');

            setTimeout(function() {
                // Use requestAnimationFrame for smoother rendering
                requestAnimationFrame(function() {
                    morphDialogHeight(function () {
                        $providerView.hide().removeClass('ceypay-view--exit ceypay-view--enter');
                        $qrView.show().addClass('ceypay-view--enter');
                        $('#ceypay-actions').show();
                        $('.ceypay-back-btn').show();
                    });
                    setTimeout(function() {
                        $qrView.removeClass('ceypay-view--enter');
                    }, 250);
                });
            }, 250);
        } else {
            // No animation (e.g., resuming payment)
            $providerView.hide();
            $qrView.show();
            $('#ceypay-actions').show();
            $('.ceypay-back-btn').show();
        }

        // Reset QR state for loading
        var $qrImg = $('#ceypay-qr-image');
        var $loading = $('.ceypay-qr__loading');

        $qrImg.hide().attr('src', '');
        $loading.show();

        // Set new src which triggers onload in HTML
        $qrImg.attr('src', data.qr_code_url);

        $('#ceypay-deep-link').hide();
        $('#ceypay-simulate-success').hide();
        $('#ceypay-status-message')
            .show()
            .removeClass('is-user-review is-expired')
            .css({
                'opacity': '1',
                'visibility': 'visible'
            })
            .html(t('waiting_payment', 'Waiting for payment...') + ' <span class="ceypay-ripple"></span>');
        $('.ceypay-qr').removeClass('is-success is-expired');

        if (data.deep_link) {
            $('#ceypay-deep-link').attr('href', data.deep_link).show();

            // Track deep link clicks
            $('#ceypay-deep-link').off('click.analytics').on('click.analytics', function() {
                if (window.CeyPayAnalytics) {
                    window.CeyPayAnalytics.trackDeeplinkClicked(data);
                }
            });

            // Auto-open on mobile devices
            if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                setTimeout(function() {
                    window.location.href = data.deep_link;
                }, 1000);
            }
        }

        // Update button text with provider name
        $('#ceypay-deep-link').text(sprintf1(t('open_app', 'Open %s app'), toTitleCase(data.provider)));

        if (data.test_mode) {
            $('#ceypay-simulate-success').show().off('click').on('click', function() {
                simulatePayment(data);
            });
        }

        // Start Polling
        startPolling(data);
    }

    // Helper to show error with auto-hide
    function showCeyPayError(message) {
        var errorHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ceypay-alert-icon"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg><span>' + message + '</span>';
        var $errorBox = $('#ceypay-error-message');

        // Clear previous timeout
        if (window.ceypayErrorTimeout) {
            clearTimeout(window.ceypayErrorTimeout);
        }

        $errorBox.html(errorHtml).removeClass('is-hiding').show();

        // Auto hide after 6 seconds
        window.ceypayErrorTimeout = setTimeout(function() {
            $errorBox.addClass('is-hiding');
            setTimeout(function() {
                $errorBox.hide().removeClass('is-hiding');
            }, 300); // Match CSS animation duration
        }, 6000);
    }

    // Handle Provider Selection
    $(document).on('click', '.ceypay-provider-btn', function() {
        var provider = $(this).data('provider');
        var orderId = window.ceypayOrderData.order_id;
        var orderKey = window.ceypayOrderData.order_key;
        var $btn = $(this);

        // Track provider selection or switch
        if (window.CeyPayAnalytics) {
            if (window.ceypaySelectedProvider && window.ceypaySelectedProvider !== provider) {
                // User switched providers
                window.CeyPayAnalytics.trackProviderSwitched(window.ceypaySelectedProvider, provider, window.ceypayOrderData);
            }
            window.CeyPayAnalytics.trackProviderSelected(provider, window.ceypayOrderData);
        }
        window.ceypaySelectedProvider = provider;

        // Show loading state on button
        $btn.addClass('is-loading').attr('aria-disabled', 'true');

        // Get GA client ID for server-side correlation
        var gaClientId = window.CeyPayAnalytics ? window.CeyPayAnalytics.getClientId() : '';

        $.ajax({
            url: ceypay_params.ajax_url,
            type: 'POST',
            data: {
                action: 'ceypay_generate_qr',
                security: ceypay_params.nonce,
                order_id: orderId,
                order_key: orderKey,
                provider: provider,
                ga_client_id: gaClientId
            },
            success: function(response) {
                if (response.success) {
                    $('#ceypay-error-message').hide();
                    // Merge new data with existing order data
                    var newData = $.extend({}, window.ceypayOrderData, response.data);
                    showQrView(newData);
                } else {
                    var msg = response.data.message || t('qr_generate_error', 'Error generating QR code');
                    showCeyPayError(msg);
                    $btn.removeClass('is-loading').removeAttr('aria-disabled');
                }
            },
            error: function() {
                showCeyPayError(t('connection_error', 'Connection error. Please try again.'));
                $btn.removeClass('is-loading').removeAttr('aria-disabled');
            }
        });
    });

    function startPolling(data) {
        // Clear any existing interval just in case
        if (pollInterval) clearInterval(pollInterval);

        var attempts = 0;
        var maxAttempts = 600; // ~30 minutes

        pollInterval = setInterval(function() {
            attempts++;
            if (attempts > maxAttempts) {
                clearInterval(pollInterval);

                // Track payment expired
                if (window.CeyPayAnalytics) {
                    window.CeyPayAnalytics.trackPaymentExpired(data);
                }
                return;
            }

            $.ajax({
                url: ceypay_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ceypay_check_status',
                    order_id: data.order_id,
                    order_key: data.order_key,
                    security: ceypay_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'SUCCESS' || response.data.status === 'PAID') {
                            clearInterval(pollInterval);

                            // Track payment success and time spent
                            if (window.CeyPayAnalytics) {
                                window.CeyPayAnalytics.trackPaymentSuccess(data);
                                window.CeyPayAnalytics.trackTimeSpent(data, 'completed', 'qr_display');
                            }

                            renderSuccess();
                            setTimeout(function() {
                                window.location.href = data.success_url;
                            }, 400);
                        } else if (response.data.status === 'USER_REVIEW') {
                            // Update status message to show user review state
                            renderUserReview();
                        } else if (response.data.status === 'EXPIRED') {
                            clearInterval(pollInterval);

                            // Auto-refresh or show manual refresh (analytics tracked in renderExpired)
                            renderExpired(data);
                        }
                    }
                }
            });
        }, 3000);
    }

    function simulatePayment(data) {
        $('#ceypay-simulate-success').text(t('simulating', 'Simulating...')).attr('aria-disabled', 'true');
        $.ajax({
            url: ceypay_params.ajax_url,
            type: 'POST',
            data: {
                action: 'ceypay_simulate_payment',
                order_id: data.order_id,
                order_key: data.order_key,
                security: ceypay_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderSuccess();
                    setTimeout(function() {
                        window.location.href = data.success_url;
                    }, 400);
                } else {
                    alert(t('simulation_failed', 'Simulation failed: ') + response.data.message);
                    $('#ceypay-simulate-success').text(t('simulate_success', 'Simulate success (test mode)')).removeAttr('aria-disabled');
                }
            },
            error: function() {
                alert(t('simulation_error', 'Simulation error.'));
                $('#ceypay-simulate-success').text(t('simulate_success', 'Simulate success (test mode)')).removeAttr('aria-disabled');
            }
        });
    }
});
