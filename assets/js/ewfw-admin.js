/**
 * EWANO Wallet admin settings - conditional fields.
 *
 * @package EWANO_Wallet_For_WooCommerce
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        const phoneSourceSelect = $('#woocommerce_ewfw_phone_source');
        const postMetaRow = $('#woocommerce_ewfw_post_meta_key').closest('tr');
        const userMetaRow = $('#woocommerce_ewfw_user_meta_key').closest('tr');

        /**
         * Toggle meta key fields based on selected phone source.
         */
        function toggleMetaFields() {
            const selected = phoneSourceSelect.val();

            // Hide all first.
            postMetaRow.hide();
            userMetaRow.hide();

            if (selected === 'post_meta') {
                postMetaRow.show();
            } else if (selected === 'user_meta') {
                userMetaRow.show();
            }
        }

        if (phoneSourceSelect.length) {
            toggleMetaFields();
            phoneSourceSelect.on('change', toggleMetaFields);
        }
    });
})(jQuery);