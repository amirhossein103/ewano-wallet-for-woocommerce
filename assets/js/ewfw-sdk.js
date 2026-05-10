/**
 * EWANO Wallet SDK Loader.
 *
 * @package EWANO_Wallet_For_WooCommerce
 */
(function ($) {
    'use strict';

    // ========================================================================
    // Phone Number Handling
    // ========================================================================

    /**
     * Convert Persian/Arabic digits to English and normalize phone number.
     *
     * @param {string} phone Raw input.
     * @return {string} Clean 10-digit phone starting with 9, or empty string.
     */
    function normalizePhone(phone) {
        if (!phone) return '';

        // 1. Replace Persian and Arabic digits with English.
        var persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        var arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        var english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        for (var i = 0; i < 10; i++) {
            phone = phone.split(persian[i]).join(english[i]);
            phone = phone.split(arabic[i]).join(english[i]);
        }

        // 2. Remove all non-digit characters.
        var cleaned = phone.replace(/\D/g, '');

        // 3. Remove leading country code (98, 0098).
        if (/^98\d{10}$/.test(cleaned)) {
            cleaned = cleaned.substring(2);
        } else if (/^0098\d{10}$/.test(cleaned)) {
            cleaned = cleaned.substring(4);
        }

        // 4. Remove leading zero if length > 10.
        if (cleaned.length > 10 && cleaned.charAt(0) === '0') {
            cleaned = cleaned.substring(1);
        }

        // 5. Validate final 10-digit number starting with 9.
        return (/^9\d{9}$/.test(cleaned)) ? cleaned : '';
    }

    /**
     * Mask phone number for display.
     *
     * @param {string} phone Phone number.
     * @return {string}
     */
    function maskPhone(phone) {
        if (phone && phone.length >= 10) {
            return phone.substring(0, 4) + '***' + phone.substring(7);
        }
        return phone || '';
    }

    // ====================================================================
    // DOM Helpers (supports dynamic WooCommerce fragments)
    // ====================================================================
    function getElements() {
        return {
            hiddenInput:  $('#ewfw_phone'),
            phoneDisplay: $('#ewfw-phone-display'),
            editForm:     $('#ewfw-edit-form'),
            changeBtn:    $('#ewfw-change-phone'),
            phoneInput:   $('#ewfw_phone_input'),
            emptyInput:   $('#ewfw_phone_input_empty'),
            noPhoneBlock: $('#ewfw-no-phone'),
        };
    }

    // ====================================================================
    // Event Delegation
    // ====================================================================
    $(document).on('input', '#ewfw_phone_input_empty', function () {
        var els = getElements();
        var phone = normalizePhone($(this).val());
        els.hiddenInput.val(phone);
        if (phone) $(this).val(phone);
    });

    $(document).on('input', '#ewfw_phone_input', function () {
        var phone = normalizePhone($(this).val());
        if (phone) $(this).val(phone);
    });

    $(document).on('click', '#ewfw-change-phone', function () {
        var els = getElements();
        els.editForm.slideDown(200);
        $(this).hide();
        els.phoneInput.val(els.hiddenInput.val()).focus();
    });

    $(document).on('click', '#ewfw-save-phone', function () {
        var els = getElements();
        var phone = normalizePhone(els.phoneInput.val());

        if (phone) {
            els.phoneInput.val(phone);
            els.hiddenInput.val(phone);
            els.phoneDisplay.text(maskPhone(phone));
            els.editForm.slideUp(200);
            els.changeBtn.show();
        } else {
            alert(EWFW_Data.messages.phone_invalid);
        }
    });

    $(document).on('click', '#ewfw-cancel-edit', function () {
        var els = getElements();
        els.phoneInput.val(els.hiddenInput.val());
        els.editForm.slideUp(200);
        els.changeBtn.show();
    });

    // ====================================================================
    // Payment SDK Controller
    // ====================================================================
    const EWFW = {
        orderId: null,
        tokenId: null,
        reserveId: null,
        clientId: null,
        sdkRetries: 0,
        maxSdkRetries: 10, // Prevent infinite loop

        /**
         * Start payment process.
         *
         * @param {string} orderId WooCommerce order ID.
         */
        init: function (orderId) {
            this.orderId = orderId;
            this.showLoading(EWFW_Data.messages.loading);
            this.fetchParams();
        },

        /**
         * Fetch SDK parameters from server.
         */
        fetchParams: function () {
            const self = this;

            $.ajax({
                url: EWFW_Data.ajax_url,
                method: 'POST',
                data: {
                    action: 'ewfw_init_sdk',
                    order_id: this.orderId,
                    nonce: EWFW_Data.nonce,
                },
                success: function (response) {
                    if (response.success && response.data) {
                        self.tokenId = response.data.tokenId;
                        self.reserveId = response.data.reserveId;
                        self.clientId = response.data.clientId;
                        self.startSDK();
                    } else {
                        self.showError(response.data || EWFW_Data.messages.payment_failed);
                    }
                },
                error: function () {
                    self.showError(EWFW_Data.messages.server_error);
                },
            });
        },

        /**
         * Initialize EWANO SDK (with retry limit).
         */
        startSDK: function () {
            const self = this;

            if (typeof window.ewano !== 'undefined' && typeof window.ewano.sdk !== 'undefined') {
                // SDK ready.
                self.sdkRetries = 0;
                $('form.checkout').hide();
                $('#ewfw-sdk-container').show();

                window.ewano.sdk.init({
                    clientId: self.clientId,
                    tokenId: self.tokenId,
                    reserveId: self.reserveId,
                    onStateChanged: function (data) {
                        self.handleStateChange(data);
                    },
                });
                return;
            }

            // SDK not yet loaded – retry with limit.
            self.sdkRetries++;
            if (self.sdkRetries <= self.maxSdkRetries) {
                self.showLoading(EWFW_Data.messages.sdk_retry + ' (' + self.sdkRetries + '/' + self.maxSdkRetries + ')');
                setTimeout(function () { self.startSDK(); }, 500);
            } else {
                self.showError(EWFW_Data.messages.payment_failed);
            }
        },

        /**
         * Handle SDK state changes.
         *
         * @param {object} data State data from SDK.
         */
        handleStateChange: function (data) {
            if (data && data.id) {
                $('#ewfw_transaction_id').val(data.id);
            }

            if (data && data.open === false) {
                // SDK window closed, confirm payment.
                this.confirmPayment();
            }

            if (data && data.type === 'ERROR') {
                this.showError(EWFW_Data.messages.payment_failed);
            }
        },

        /**
         * Confirm payment after SDK closes.
         */
        confirmPayment: function () {
            const self = this;
            this.showLoading('⏳');

            $.ajax({
                url: EWFW_Data.confirm_url,
                method: 'POST',
                data: {
                    order_id: self.orderId,
                    reserve_id: self.reserveId,
                    nonce: EWFW_Data.confirm_nonce,
                },
                success: function (response) {
                    if (response.success) {
                        self.showSuccess();
                        setTimeout(function () {
                            window.location.href = response.data.redirect;
                        }, 1500);
                    } else {
                        self.showError(response.data || EWFW_Data.messages.confirm_failed);
                    }
                },
                error: function () {
                    self.showError(EWFW_Data.messages.server_error);
                },
            });
        },

        /**
         * Show loading indicator.
         *
         * @param {string} msg Message to display.
         */
        showLoading: function (msg) {
            $('.woocommerce-error, .woocommerce-message').remove();
            $('form.checkout, .woocommerce-checkout-payment').prepend(
                '<div class="ewfw-loading" style="padding:15px;text-align:center;">' +
                '<p>' + msg + '</p>' +
                '</div>'
            );
        },

        /**
         * Show error message.
         *
         * @param {string} message Error message.
         */
        showError: function (message) {
            $('.ewfw-loading').remove();
            $('#ewfw-sdk-container').hide();
            $('form.checkout, .woocommerce-checkout-payment').show().prepend(
                '<div class="woocommerce-error" role="alert">' +
                '<strong>❌ ' + message + '</strong><br>' +
                '<small>' + EWFW_Data.messages.retry_prompt + '</small>' +
                '</div>'
            );
        },

        /**
         * Show success message.
         */
        showSuccess: function () {
            $('.ewfw-loading').remove();
            $('form.checkout, .woocommerce-checkout-payment').prepend(
                '<div class="woocommerce-message" role="alert">' +
                '✅ ' + EWFW_Data.messages.success_message +
                '</div>'
            );
        },
    };

    // ====================================================================
    // Auto-init on order pay page (robust method)
    // ====================================================================
    $(document).ready(function () {
        if (window.location.hash === '#ewfw-pay') {
            // Extract order ID from the URL: typical pattern .../order-pay/12345/...
            var match = window.location.pathname.match(/\/order-pay\/(\d+)/);
            if (match && match[1]) {
                EWFW.init(match[1]);
            }
        }
    });
})(jQuery);