/**
 * CeyPay Analytics Module
 *
 * Centralized tracking functions for GA4 events.
 * Uses the global gtag() function loaded via ceypay-gtag script.
 */
(function(window) {
    'use strict';

    // Ensure ceypay_analytics_params is available
    var params = window.ceypay_analytics_params || {};

    /**
     * CeyPay Analytics Object
     */
    var CeyPayAnalytics = {
        /**
         * Check if analytics is enabled and gtag is available
         * @returns {boolean}
         */
        isEnabled: function() {
            return params.enabled &&
                   params.measurement_id &&
                   typeof window.gtag === 'function';
        },

        /**
         * Get GA client ID from cookie for server-side correlation
         * @returns {string}
         */
        getClientId: function() {
            // Try standard GA4 cookie format: GA1.1.123456789.1234567890
            var match = document.cookie.match(/_ga=GA\d+\.\d+\.(\d+\.\d+)/);
            if (match) return match[1];

            // Try alternate format without GA prefix: _ga=123456789.1234567890
            match = document.cookie.match(/_ga=(\d+\.\d+)/);
            if (match) return match[1];

            // Fallback: generate a temporary client ID for this session
            if (!window._ceypayTempClientId) {
                window._ceypayTempClientId = Math.floor(Math.random() * 1000000000) + '.' + Math.floor(Date.now() / 1000);
            }
            return window._ceypayTempClientId;
        },

        /**
         * Get common parameters for all events
         * @param {object} additionalParams - Additional event-specific parameters
         * @returns {object}
         */
        getEventParams: function(additionalParams) {
            var baseParams = {
                merchant_id: params.merchant_id || ''
            };
            return Object.assign({}, baseParams, additionalParams || {});
        },

        /**
         * Track a GA4 event
         * @param {string} eventName - The event name
         * @param {object} eventParams - Event parameters
         */
        track: function(eventName, eventParams) {
            if (!this.isEnabled()) {
                return;
            }

            var fullParams = this.getEventParams(eventParams);

            try {
                window.gtag('event', eventName, fullParams);
            } catch (e) {
                // Silent fail
            }
        },

        // ==========================================
        // Specific Event Tracking Methods
        // ==========================================

        /**
         * Track modal open event
         * @param {object} data - Modal data containing order info
         */
        trackModalOpen: function(data) {
            this.track('ceypay_modal_open', {
                order_id: String(data.order_id || ''),
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        /**
         * Track provider selection event
         * @param {string} provider - Selected provider (BYBIT, BINANCE, etc.)
         * @param {object} data - Order data
         */
        trackProviderSelected: function(provider, data) {
            this.track('ceypay_provider_selected', {
                order_id: String(data.order_id || ''),
                provider: provider,
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        /**
         * Track QR code displayed event
         * @param {object} data - QR data including provider and order info
         */
        trackQrDisplayed: function(data) {
            this.track('ceypay_qr_displayed', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        /**
         * Track deep link click event
         * @param {object} data - Order and provider data
         */
        trackDeeplinkClicked: function(data) {
            this.track('ceypay_deeplink_clicked', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        /**
         * Track payment success event (detected via polling)
         * @param {object} data - Order and payment data
         */
        trackPaymentSuccess: function(data) {
            this.track('ceypay_payment_success', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        /**
         * Track payment failed event
         * @param {object} data - Order and payment data
         * @param {string} reason - Failure reason
         */
        trackPaymentFailed: function(data, reason) {
            this.track('ceypay_payment_failed', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || '',
                failure_reason: reason || ''
            });
        },

        /**
         * Track payment expired event
         * @param {object} data - Order and payment data
         */
        trackPaymentExpired: function(data) {
            this.track('ceypay_payment_expired', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        /**
         * Track modal closed event
         * @param {object} data - Order data
         * @param {string} stage - Current stage when closed (provider_selection, qr_display)
         */
        trackModalClosed: function(data, stage) {
            this.track('ceypay_modal_closed', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || '',
                closed_at_stage: stage || 'unknown'
            });
        },

        /**
         * Track back button click event
         * @param {object} data - Order data
         */
        trackBackClicked: function(data) {
            this.track('ceypay_back_clicked', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                amount: parseFloat(data.amount) || 0,
                currency: data.currency || ''
            });
        },

        // ==========================================
        // Engagement Tracking Methods
        // ==========================================

        /**
         * Internal timer storage
         */
        _timers: {},

        /**
         * Start a timer for engagement tracking
         * @param {string} timerId - Unique identifier for the timer
         */
        startTimer: function(timerId) {
            this._timers[timerId] = Date.now();
        },

        /**
         * Get elapsed time in seconds and clear timer
         * @param {string} timerId - Timer identifier
         * @returns {number} Elapsed seconds or 0 if timer not found
         */
        getElapsedTime: function(timerId) {
            if (!this._timers[timerId]) {
                return 0;
            }
            var elapsed = Math.round((Date.now() - this._timers[timerId]) / 1000);
            delete this._timers[timerId];
            return elapsed;
        },

        /**
         * Track time spent on modal
         * @param {object} data - Order data
         * @param {string} exitReason - Why the modal was closed (completed, abandoned, expired)
         * @param {string} stage - Stage when exited (provider_selection, qr_display)
         */
        trackTimeSpent: function(data, exitReason, stage) {
            var duration = this.getElapsedTime('modal_' + data.order_id);
            if (duration > 0) {
                this.track('ceypay_time_spent', {
                    order_id: String(data.order_id || ''),
                    provider: data.provider || '',
                    duration_seconds: duration,
                    exit_reason: exitReason || 'unknown',
                    exit_stage: stage || 'unknown'
                });
            }
        },

        /**
         * Track QR code refresh event
         * @param {object} data - Order data
         * @param {string} refreshType - Type of refresh (auto, manual)
         * @param {number} refreshCount - Number of refreshes so far
         */
        trackQrRefreshed: function(data, refreshType, refreshCount) {
            this.track('ceypay_qr_refreshed', {
                order_id: String(data.order_id || ''),
                provider: data.provider || '',
                refresh_type: refreshType || 'manual',
                refresh_count: parseInt(refreshCount) || 1
            });
        },

        /**
         * Track provider switch event (when user changes selection)
         * @param {string} fromProvider - Previous provider
         * @param {string} toProvider - New provider
         * @param {object} data - Order data
         */
        trackProviderSwitched: function(fromProvider, toProvider, data) {
            this.track('ceypay_provider_switched', {
                order_id: String(data.order_id || ''),
                from_provider: fromProvider || '',
                to_provider: toProvider || ''
            });
        }
    };

    // Expose to global scope
    window.CeyPayAnalytics = CeyPayAnalytics;

})(window);
