<?php
/**
 * EWANO Wallet payment gateway for WooCommerce.
 *
 * @package EWANO_Wallet_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * EWFW_Gateway class.
 */
class EWFW_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'ewfw';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'کیف پول اِوانو', 'ewfw' );
		$this->method_description = __( 'پرداخت مستقیم از کیف پول اِوانو.', 'ewfw' );
		$this->supports           = [ 'products', 'refunds' ];

		// Load settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'کیف پول اِوانو', 'ewfw' ) );
		$this->description = $this->get_option( 'description', __( 'پرداخت مستقیم از کیف پول اِوانو.', 'ewfw' ) );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_api_ewfw_confirm', [ $this, 'confirm_handler' ] );
		add_action( 'woocommerce_api_ewfw_contract_callback', [ $this, 'contract_callback_handler' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_assets' ] );
	}

	/**
	 * Init settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = EWFW_Settings::get_fields();
	}

	/**
	 * Enqueue SDK and loader on checkout page.
	 */
	/**
	 * Enqueue SDK and loader on checkout page.
	 */
	public function enqueue_assets() {
		if ( ! is_checkout() ) {
			return;
		}

		$sdk_url = esc_url( EWFW_Settings::get_option( 'sdk_url',
			'https://static-ebcom.mci.ir/static/app/wallet-sdk/sdk/app-sdk-v1.0.0.min.js' ) );

		// SDK.
		wp_enqueue_script( 'ewfw-sdk', $sdk_url, [], EWFW_VERSION, true );

		// Loader.
		wp_enqueue_script( 'ewfw-loader', EWFW_PLUGIN_URL . 'assets/js/ewfw-sdk.js',
			[ 'jquery', 'ewfw-sdk' ], EWFW_VERSION, true );

		wp_localize_script( 'ewfw-loader', 'EWFW_Data', [
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'confirm_url' => esc_url_raw( home_url( '/?wc-api=ewfw_confirm' ) ),
			'nonce'       => wp_create_nonce( 'ewfw_nonce' ),
		] );

	}

	public function admin_enqueue_assets() {
		// Admin settings page JS for conditional fields.
		if ( is_admin() && isset( $_GET['section'] ) && 'ewfw' === $_GET['section'] ) {
			wp_enqueue_script( 'ewfw-admin', EWFW_PLUGIN_URL . 'assets/js/ewfw-admin.js',
				[ 'jquery' ], EWFW_VERSION, true );
		}
	}

	/**
	 * Payment fields on checkout.
	 */
	public function payment_fields() {
		echo '<p>' . esc_html( $this->description ) . '</p>';

		$detected_phone = $this->get_detected_phone();

		// Hidden field that always holds the actual phone number.
		echo '<input type="hidden" id="ewfw_phone" name="ewfw_phone" value="' . esc_attr( $detected_phone ) . '" />';

		echo '<div class="ewfw-phone-display" style="margin:15px 0; padding:12px; background:#f8f9fa; border-radius:8px; border:1px solid #e2e8f0;">';

		if ( ! empty( $detected_phone ) ) {
			// Phone detected — show it with edit button.
			echo '<div id="ewfw-phone-show" style="display:flex; justify-content:space-between; align-items:center;">';
			echo '<div>';
			echo '<span style="color:#718096; font-size:13px;">' . esc_html__( 'شماره موبایل کیف پول:', 'ewfw' ) . '</span><br>';
			echo '<strong id="ewfw-phone-display" style="font-size:16px; direction:ltr; display:inline-block;">' .
			     esc_html( $this->mask_phone( $detected_phone ) ) . '</strong>';
			echo '</div>';
			echo '<button type="button" id="ewfw-change-phone" class="button" style="background:none; border:none; color:#4a5568; cursor:pointer; font-size:13px; text-decoration:underline;">';
			echo '✏️ ' . esc_html__( 'تغییر', 'ewfw' );
			echo '</button>';
			echo '</div>';

			// Edit form (hidden by default).
			echo '<div id="ewfw-edit-form" style="display:none; margin-top:10px;">';
			echo '<label for="ewfw_phone_input">' . esc_html__( 'شماره جدید:', 'ewfw' ) . '<small style="color: #666666;margin-right: 3px;">' . esc_html__( '(بدون 0 و با 9 شروع می‌شود).', 'ewfw' ) . '</small></label>';
			echo '<div style="display:flex; gap:8px; margin-top:5px;">';
			echo '<input type="tel" 
                     id="ewfw_phone_input" 
                     placeholder="09121234567" 
                     pattern="09[0-9]{9}" 
                     maxlength="10"
                     value="' . esc_attr( $detected_phone ) . '" 
                     style="direction:ltr; text-align:left; flex:1;" />';
			echo '<button type="button" id="ewfw-save-phone" class="button">' . esc_html__( 'ذخیره', 'ewfw' ) . '</button>';
			echo '<button type="button" id="ewfw-cancel-edit" class="button" style="background:#e2e8f0;">' . esc_html__( 'انصراف', 'ewfw' ) . '</button>';
			echo '</div>';
			echo '</div>';

		} else {
			// No phone detected.
			echo '<div id="ewfw-no-phone">';
			echo '<label for="ewfw_phone_input_empty"><strong>' . esc_html__( 'شماره موبایل کیف پول اوانو:', 'ewfw' ) . '</strong></label>';
			echo '<p style="color:#e53e3e; font-size:13px; margin:5px 0;">' .
			     esc_html__( 'برای احراز هویت کیف پول، شماره موبایل شما الزامی است.', 'ewfw' ) . '</p>';
			echo '<input type="tel" 
                     id="ewfw_phone_input_empty" 
                     placeholder="09121234567" 
                     pattern="09[0-9]{9}" 
                     maxlength="10"
                     value="" 
                     style="direction:ltr; text-align:left; width:100%; padding:8px; margin-top:5px;" />';
			echo '</div>';
		}

		echo '</div>';

// Hidden fields for SDK.
		echo '<div id="ewfw-sdk-container" style="display:none;"></div>';
		echo '<input type="hidden" id="ewfw_reserve_id" name="ewfw_reserve_id" value="" />';
		echo '<input type="hidden" id="ewfw_transaction_id" name="ewfw_transaction_id" value="" />';

	}

	/**
	 * Get phone number from configured source or current user.
	 *
	 * @return string
	 */
	private function get_detected_phone() {
		$source = EWFW_Settings::get_option( 'phone_source', 'billing_phone' );
		$phone  = '';

		switch ( $source ) {
			case 'manual':
				break;

			case 'post_meta':
				break;

			case 'user_meta':
				if ( is_user_logged_in() ) {
					$meta_key = EWFW_Settings::get_option( 'user_meta_key', '' );
					if ( ! empty( $meta_key ) ) {
						$phone = get_user_meta( get_current_user_id(), $meta_key, true );
					}
				}
				break;

			case 'digits':
				if ( is_user_logged_in() && function_exists( 'digits_is_active' ) ) {
					$phone = get_user_meta( get_current_user_id(), 'digits_phone', true );
				}
				break;

			case 'billing_phone':
			default:
				if ( is_user_logged_in() ) {
					$phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
				}
				break;
		}

		// Fallback to billing_phone from POST (when user fills checkout form).
		if ( empty( $phone ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$billing_phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
			if ( ! empty( $billing_phone ) ) {
				$phone = $billing_phone;
			}
		}

		return EWFW_Phone::normalize_phone( $phone ?: '' );
	}

	/**
	 * Mask phone number for display.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private function mask_phone( $phone ) {
		if ( strlen( $phone ) >= 10 ) {
			return substr( $phone, 0, 4 ) . '***' . substr( $phone, 7 );
		}
		return $phone;
	}

    /**
     * Validate payment fields.
     *
     * @return bool
     */
    public function validate_fields() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $phone = isset( $_POST['ewfw_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ewfw_phone'] ) ) : '';
        $phone = EWFW_Phone::normalize_phone( $phone );

        if ( empty( $phone ) ) {
            wc_add_notice(
                    __( 'برای پرداخت با کیف پول اوانو، ثبت شماره موبایل در بخش صورتحساب الزامی است.', 'ewfw' ),
                    'error'
            );
            return false;
        }

        return true;
    }

	/**
	 * Process payment.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		try {
			$order  = wc_get_order( $order_id );
			$amount = (int) $order->get_total();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$custom_phone = isset( $_POST['ewfw_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ewfw_phone'] ) ) : '';
			$custom_phone = EWFW_Phone::normalize_phone( $custom_phone );

			if ( ! empty( $custom_phone ) ) {
				$phone = $custom_phone;
			} else {
				$phone = EWFW_Phone::get_order_phone( $order );
			}

			if ( empty( $phone ) ) {
				throw new Exception( __( 'شماره موبایل غیر مجاز است.', 'ewfw' ) );
			}
			if ( empty( $phone ) ) {
				throw new Exception( __( 'شماره موبایل غیر مجاز است.', 'ewfw' ) );
			}

			$callback_url = home_url( '/' ) . '?wc-api=ewfw_contract_callback&order_id=' . $order_id;

			$api = new EWFW_API();
			$api->client_login();

			$contract_result = $api->get_or_create_contract( $phone, $callback_url );

			// Redirect to EWANO for contract signing.
			if ( 'redirect' === $contract_result['status'] ) {
				$order->update_meta_data( '_ewfw_phone', $phone );
				$order->update_meta_data( '_ewfw_pending_contract', true );
				$order->save();

				return [
					'result'   => 'success',
					'redirect' => esc_url_raw( $contract_result['contract_url'] ),
				];
			}

			// Contract exists — continue.
			$contract_code = $contract_result['contract'];
			$token_id      = $api->get_token_id( $phone );
			$reserve_id    = $api->reserve( $contract_code, $amount );

			// Save meta.
			$order->update_meta_data( '_ewfw_contract_code', $contract_code );
			$order->update_meta_data( '_ewfw_reserve_id', $reserve_id );
			$order->update_meta_data( '_ewfw_token_id', $token_id );
			$order->update_meta_data( '_ewfw_token', $api->get_token() );
			$order->update_meta_data( '_ewfw_phone', $phone );
			$order->update_meta_data( '_ewfw_pending_contract', false );
			$order->save();

			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ) . '#ewfw-pay',
			];

		} catch ( Exception $e ) {
			wc_add_notice( esc_html( $e->getMessage() ), 'error' );
			return [ 'result' => 'failure' ];
		}
	}

	/**
	 * AJAX handler: provide SDK parameters.
	 */
	public function ajax_init_sdk() {
		check_ajax_referer( 'ewfw_nonce', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			wp_send_json_error( __( 'شماره سفارش نامعتبر است.', 'ewfw' ) );
		}

		$api = new EWFW_API();

		wp_send_json_success( [
			'tokenId'   => $order->get_meta( '_ewfw_token_id' ),
			'reserveId' => $order->get_meta( '_ewfw_reserve_id' ),
			'clientId'  => $api->get_client_id(),
		] );
	}

	/**
	 * Confirm handler after SDK closes.
	 */
	public function confirm_handler() {
		$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$reserve_id = isset( $_POST['reserve_id'] ) ? sanitize_text_field( wp_unslash( $_POST['reserve_id'] ) ) : '';

		$order = wc_get_order( $order_id );

		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			wp_send_json_error( __( 'شماره سفارش نامعتبر است.', 'ewfw' ) );
		}

		try {
			$api = new EWFW_API();
			$api->client_login();

			$status_data = $api->transaction_status( $reserve_id );
			$txn_status  = $status_data['result']['data']['status'] ?? '';

			if ( 'RESERVED' === $txn_status ) {
				$confirm = $api->confirm( $reserve_id );

				if ( 204 === ( $confirm['result']['status']['code'] ?? 0 ) ) {
					$order->payment_complete( $status_data['result']['data']['id'] ?? '' );
					$order->save();

					wp_send_json_success( [
						'redirect' => esc_url_raw( $this->get_return_url( $order ) ),
					] );
				}
			} elseif ( in_array( $txn_status, [ 'COMPLETED', 'SHIPPED' ], true ) ) {
				$order->payment_complete( $status_data['result']['data']['id'] ?? '' );
				wp_send_json_success( [ 'redirect' => esc_url_raw( $this->get_return_url( $order ) ) ] );
			}

			$order->update_status( 'failed', sprintf( __( 'پرادخت با شکست روبرو شد. وصعیت: %s', 'ewfw' ), $txn_status ) );
			wp_send_json_error( __( 'پرداخت ناموفق.', 'ewfw' ) );

		} catch ( Exception $e ) {
			$order->update_status( 'failed', $e->getMessage() );
			wp_send_json_error( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Callback after user signs contract on EWANO.
	 */
	public function contract_callback_handler() {
		$order_id     = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$contract_code = isset( $_GET['contractCode'] ) ? sanitize_text_field( wp_unslash( $_GET['contractCode'] ) ) : '';
		$verified     = isset( $_GET['verify'] ) && 'true' === $_GET['verify'];

		$order = wc_get_order( $order_id );

		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			wp_die( esc_html__( 'Invalid order.', 'ewfw' ) );
		}

		if ( ! $verified || empty( $contract_code ) ) {
			wc_add_notice( __( 'امضای قرارداد به درستی صورت نگرفت. لطفا دوباره تلاش کنید.', 'ewfw' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Save contract code.
		$order->update_meta_data( '_ewfw_contract_code', $contract_code );
		$order->update_meta_data( '_ewfw_pending_contract', false );
		$order->save();

		// Now continue with payment flow.
		$result = $this->process_payment( $order_id );

		if ( 'success' === $result['result'] && ! empty( $result['redirect'] ) ) {
			wp_safe_redirect( $result['redirect'] );
			exit;
		}

		wc_add_notice( __( 'پرداخت انجام نشد. لطفا دوباره تلاش کنید.', 'ewfw' ), 'error' );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Process refund.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount   Refund amount.
	 * @param  string $reason   Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		try {
			$api = new EWFW_API();
			$api->client_login();

			$contract_code  = $order->get_meta( '_ewfw_contract_code' );
			$transaction_id = $order->get_meta( '_ewfw_transaction_id' );

			if ( ! $contract_code || ! $transaction_id ) {
				throw new Exception( __( 'اطلاعات پرداخت دریافت نشد.', 'ewfw' ) );
			}

			$refund_id = $api->refund_submit( $contract_code, $transaction_id, (int) $amount, $order_id );
			$api->refund_confirm( $contract_code, $refund_id );

			$order->add_order_note(
				sprintf(
				/* translators: 1: refund amount, 2: EWANO refund ID */
					__( 'بازگشت %1$ ریال از اِوانو. شناسه عودت وجه: %2$s', 'ewfw' ),
					$amount,
					$refund_id
				)
			);

			return true;

		} catch ( Exception $e ) {
			$order->add_order_note( __( 'خطا در بازگشت وجه به اِوانو:', 'ewfw' ) . ' ' . $e->getMessage() );
			return new WP_Error( 'ewfw_refund_error', $e->getMessage() );
		}
	}
}

// AJAX handler for SDK init.
add_action( 'wp_ajax_ewfw_init_sdk', function () {
	$gateway = new EWFW_Gateway();
	$gateway->ajax_init_sdk();
} );
add_action( 'wp_ajax_nopriv_ewfw_init_sdk', function () {
	$gateway = new EWFW_Gateway();
	$gateway->ajax_init_sdk();
} );