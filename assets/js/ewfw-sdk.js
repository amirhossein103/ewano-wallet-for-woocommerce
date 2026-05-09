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

        var cleaned = phone.replace(/\D/g, '');

        var persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        var arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        var english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        for (var i = 0; i < 10; i++) {
            cleaned = cleaned.split(persian[i]).join(english[i]);
            cleaned = cleaned.split(arabic[i]).join(english[i]);
        }

        // Remove leading country code (+98, 98, 0098).
        cleaned = cleaned.replace(/^(0{0,2}98|\+98)/, '');

        // Remove leading zero if longer than 10 digits.
        if (cleaned.length > 10 && cleaned.charAt(0) === '0') {
            cleaned = cleaned.substring(1);
        }

        return /^9\d{9}$/.test(cleaned) ? cleaned : '';
    }

    /**
     * Mask phone number for display (e.g., 0914***7346).
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

    /**
     * Get DOM elements (they may be added dynamically by WooCommerce AJAX).
     */
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
    // Event Delegation (works with dynamically loaded elements)
    // ====================================================================

    // Real-time normalization for empty phone input.
    $(document).on('input', '#ewfw_phone_input_empty', function () {
        var els = getElements();
        var phone = normalizePhone($(this).val());
        els.hiddenInput.val(phone);
        if (phone) $(this).val(phone);
    });

    // Real-time normalization for edit form input.
    $(document).on('input', '#ewfw_phone_input', function () {
        var phone = normalizePhone($(this).val());
        if (phone) $(this).val(phone);
    });

    // "Change" button click.
    $(document).on('click', '#ewfw-change-phone', function () {
        var els = getElements();
        els.editForm.slideDown(200);
        $(this).hide();
        els.phoneInput.val(els.hiddenInput.val()).focus();
    });

    // "Save" button click.
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
            alert('شماره موبایل باید ۱۰ رقم و با ۹ شروع شود.');
        }
    });

    // "Cancel" button click.
    $(document).on('click', '#ewfw-cancel-edit', function () {
        var els = getElements();
        els.phoneInput.val(els.hiddenInput.val());
        els.editForm.slideUp(200);
        els.changeBtn.show();
    });

    const EWFW = {
        orderId: null,
        tokenId: null,
        reserveId: null,
        clientId: null,

        /**
         * Start payment process.
         *
         * @param {string} orderId WooCommerce order ID.
         */
        init: function (orderId) {
            this.orderId = orderId;
            this.showLoading();
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
                    if (response.success) {
                        self.tokenId = response.data.tokenId;
                        self.reserveId = response.data.reserveId;
                        self.clientId = response.data.clientId;
                        self.log('Parameters loaded.');
                        self.startSDK();
                    } else {
                        self.showError(response.data || 'شکست در بارگذاری داده‌های پرداخت.');
                    }
                },
                error: function () {
                    self.showError('خطا در برقراری ارتباط با سرور.');
                },
            });
        },

        /**
         * Initialize EWANO SDK.
         */
        startSDK: function () {
            const self = this;

            if (typeof window.ewano === 'undefined' || typeof window.ewano.sdk === 'undefined') {
                self.log('SDK not ready, retrying...');
                setTimeout(function () { self.startSDK(); }, 500);
                return;
            }

            self.log('Launching SDK...');
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
        },

        /**
         * Handle SDK state changes.
         *
         * @param {object} data State data from SDK.
         */
        handleStateChange: function (data) {
            this.log('State: ' + JSON.stringify(data));

            if (data && data.id) {
                $('#ewfw_transaction_id').val(data.id);
            }

            if (data && data.open === false) {
                this.log('SDK closed. Confirming payment...');
                this.confirmPayment();
            }

            if (data && data.type === 'ERROR') {
                this.showError('پرداخت ناموفق.');
            }
        },

        /**
         * Confirm payment after SDK closes.
         */
        confirmPayment: function () {
            const self = this;

            this.showLoading();

            $.ajax({
                url: EWFW_Data.confirm_url,
                method: 'POST',
                data: {
                    order_id: this.orderId,
                    reserve_id: this.reserveId,
                },
                success: function (response) {
                    if (response.success) {
                        self.showSuccess();
                        setTimeout(function () {
                            window.location.href = response.data.redirect;
                        }, 1500);
                    } else {
                        self.showError(response.data || 'تایید پرداخت شکست خورد.');
                    }
                },
                error: function () {
                    self.showError('خطا در برقراری ارتباط با سرور.');
                },
            });
        },

        /**
         * Show loading indicator.
         */
        showLoading: function () {
            $('.woocommerce-error, .woocommerce-message').remove();
            $('form.checkout').prepend(
                '<div class="ewfw-loading" style="padding:15px;text-align:center;">' +
                '<p>⏳ ' + 'ارتباط با سرور اِوانو...' + '</p>' +
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
            $('form.checkout').show().prepend(
                '<div class="woocommerce-error" role="alert">' +
                '<strong>❌ ' + message + '</strong><br>' +
                '<small>لطفا دوباره تلاش کنید و یا یک روش پرداخت دیگر را انتخاب کنید.</small>' +
                '</div>'
            );
        },

        /**
         * Show success message.
         */
        showSuccess: function () {
            $('.ewfw-loading').remove();
            $('form.checkout').prepend(
                '<div class="woocommerce-message" role="alert">' +
                '✅ پرداخت با موفقیت ثبت شد! درحال بازگشت...' +
                '</div>'
            );
        },

        /**
         * Console log.
         *
         * @param {string} msg Message.
         */
        log: function (msg) {
            console.log('[EWFW]', msg);
        },
    };

    // Auto-init on order pay page.
    $(document).ready(function () {
        if (window.location.hash === '#ewfw-pay') {
            console.log('hash detected')
            const urlParts = window.location.pathname.split('/');
            const payIndex = urlParts.indexOf('pay');
            if (payIndex !== -1 && urlParts[payIndex + 1]) {
                console.log('initialized')
                EWFW.init(urlParts[payIndex + 1]);
            }
        }
    });
})(jQuery);
