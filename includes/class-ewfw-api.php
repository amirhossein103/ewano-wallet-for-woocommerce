<?php
/**
 * EWANO REST API handler.
 *
 * @package EWANO_Wallet_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * EWFW_API class.
 */
class EWFW_API {

	/**
	 * @var string Base URL.
	 */
	private $base_url;

	/**
	 * @var string Client ID.
	 */
	private $client_id;

	/**
	 * @var string Client Secret.
	 */
	private $client_secret;

	/**
	 * @var string Bearer token.
	 */
	private $token;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$env = EWFW_Settings::get_option( 'environment', 'sandbox' );

		$this->client_id     = EWFW_Settings::get_option( 'client_id' );
		$this->client_secret = EWFW_Settings::get_option( 'client_secret' );

		switch ( $env ) {
			case 'production':
				$this->base_url = 'https://ebcom.mci.ir';
				break;
			case 'stage':
				$this->base_url = 'https://stage-ebcom.mci.ir';
				break;
			case 'sandbox':
			default:
				$this->base_url = 'https://sandbox-ebcom.mci.ir';
				break;
		}
	}

	/**
	 * Send HTTP request to EWANO API.
	 *
	 * Uses WordPress HTTP API by default. Falls back to direct cURL
	 * if "Non-standard Request" is enabled in plugin settings.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $headers  Request headers.
	 * @param array  $body     Request body.
	 * @return array
	 * @throws Exception On failure with Persian message.
	 */
	private function request( $method, $endpoint, $headers = [], $body = null ) {
		$use_curl = EWFW_Settings::get_option( 'request_method', 'wp' ) === 'curl';

		if ( $use_curl ) {
			return $this->request_via_curl( $method, $endpoint, $headers, $body );
		}

		return $this->request_via_wp( $method, $endpoint, $headers, $body );
	}

	/**
	 * Send request via WordPress HTTP API.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $headers  Request headers.
	 * @param array  $body     Request body.
	 * @return array
	 * @throws Exception On failure.
	 */
	private function request_via_wp( $method, $endpoint, $headers = [], $body = null ) {
		$url = $this->base_url . $endpoint;

		$args = [
			'method'    => $method,
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => EWFW_Settings::get_option( 'ssl_verify', 'enabled' ) === 'enabled',
		];

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'خطا در ارتباط با سرور پرداخت. لطفاً دوباره تلاش کنید.', 'ewfw' ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( __( 'پاسخ نامعتبر از سرور پرداخت. لطفاً با مدیر سایت تماس بگیرید.', 'ewfw' ) );
		}

		$this->handle_ewano_error( $data, $http_code );

		return [
			'http_code' => $http_code,
			'data'      => $data,
		];
	}

	/**
	 * Send request via direct cURL (fallback).
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $headers  Request headers.
	 * @param array  $body     Request body.
	 * @return array
	 * @throws Exception On failure.
	 */
	private function request_via_curl( $method, $endpoint, $headers = [], $body = null ) {
		$url = $this->base_url . $endpoint;

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYSTATUS, false );
		curl_setopt( $ch, CURLOPT_SSLVERSION, 0 );

		$curl_headers = [];
		foreach ( $headers as $key => $value ) {
			$curl_headers[] = "$key: $value";
		}
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $curl_headers );

		if ( null !== $body ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		}

		$response_body = curl_exec( $ch );
		$http_code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error         = curl_error( $ch );
		curl_close( $ch );

		if ( $error ) {
			throw new Exception( __( 'خطا در ارتباط با سرور پرداخت. لطفاً دوباره تلاش کنید.', 'ewfw' ) );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( __( 'پاسخ نامعتبر از سرور پرداخت. لطفاً با مدیر سایت تماس بگیرید.', 'ewfw' ) );
		}

		$this->handle_ewano_error( $data, $http_code );

		return [
			'http_code' => $http_code,
			'data'      => $data,
		];
	}

	/**
	 * Translate EWANO error codes to Persian messages.
	 *
	 * @param array $data      Response data.
	 * @param int   $http_code HTTP status code.
	 * @throws Exception If error detected.
	 */
	private function handle_ewano_error( $data, $http_code ) {
		$status       = $data['status'] ?? [];
		$result       = $data['result'] ?? [];
		$result_status = $result['status'] ?? [];

		$status_code = $result_status['code'] ?? $status['code'] ?? $http_code;

		// Success codes — no error.
		if ( in_array( $status_code, [ 200, 201, 204, 302, 1059 ] ) ) {
			return;
		}

		// Map of known EWANO error codes to Persian messages.
		$error_messages = [
			// Accounting errors (Table 32).
			2401 => __( 'خطای داخلی سرور پرداخت. لطفاً با پشتیبانی تماس بگیرید.', 'ewfw' ),
			2402 => __( 'درخواست توسط سرور پرداخت رد شد.', 'ewfw' ),
			2411 => __( 'موجودی کیف پول یافت نشد.', 'ewfw' ),
			2412 => __( 'موجودی با شرایط مشابه قبلاً ایجاد شده است.', 'ewfw' ),
			2413 => __( 'حداکثر موجودی کیف پول فرا رسیده است.', 'ewfw' ),
			2421 => __( 'حساب کاربری در کیف پول یافت نشد.', 'ewfw' ),
			2422 => __( 'حساب کاربری قبلاً وجود دارد.', 'ewfw' ),
			2431 => __( 'موجودی حساب یافت نشد.', 'ewfw' ),
			2432 => __( 'موجودی حساب قبلاً وجود دارد.', 'ewfw' ),
			2433 => __( 'امکان به‌روزرسانی موجودی حساب وجود ندارد.', 'ewfw' ),
			2434 => __( 'محاسبه موجودی حساب ممکن نیست.', 'ewfw' ),
			2435 => __( 'موجودی کیف پول کافی نیست. لطفاً کیف پول خود را شارژ کنید.', 'ewfw' ),
			2441 => __( 'تراکنش یافت نشد.', 'ewfw' ),
			2442 => __( 'محاسبه موجودی برای تراکنش ممکن نیست.', 'ewfw' ),
			2443 => __( 'حداکثر تعداد تراکنش فرا رسیده است.', 'ewfw' ),
			2498 => __( 'اطلاعات درخواستی یافت نشد.', 'ewfw' ),
			2499 => __( 'درخواست نامعتبر است. لطفاً مجدداً تلاش کنید.', 'ewfw' ),
			2501 => __( 'خطای عمومی سرور پرداخت. لطفاً با پشتیبانی تماس بگیرید.', 'ewfw' ),

			// Contract / Access errors.
			1002 => __( 'دسترسی غیرمجاز. لطفاً تنظیمات افزونه را بررسی کنید.', 'ewfw' ),
			1009 => __( 'این شماره موبایل کیف پول اوانو ندارد. لطفاً ابتدا کیف پول خود را فعال کنید.', 'ewfw' ),
			1059 => __( 'دسترسی به کیف پول تأیید نشده است. لطفاً ابتدا قرارداد را امضا کنید.', 'ewfw' ),
			1102 => __( 'درخواست رد شد. شناسه کلاینت یا scope نامعتبر است.', 'ewfw' ),
			1105 => __( 'این کاربر کیف پول ندارد.', 'ewfw' ),
			1106 => __( 'کاربر یافت نشد.', 'ewfw' ),

			// General errors.
			401  => __( 'احراز هویت ناموفق. لطفاً تنظیمات افزونه را بررسی کنید.', 'ewfw' ),
			403  => __( 'دسترسی غیرمجاز. لطفاً با مدیر سایت تماس بگیرید.', 'ewfw' ),
			404  => __( 'سرویس مورد نظر در سرور پرداخت یافت نشد.', 'ewfw' ),
			500  => __( 'خطای داخلی سرور پرداخت. لطفاً بعداً تلاش کنید.', 'ewfw' ),
			502  => __( 'سرور پرداخت موقتاً در دسترس نیست.', 'ewfw' ),
			503  => __( 'سرور پرداخت در حال به‌روزرسانی است.', 'ewfw' ),
			520  => __( 'خطای ارائه‌دهنده سرویس. لطفاً با پشتیبانی تماس بگیرید.', 'ewfw' ),
		];

		if ( isset( $error_messages[ $status_code ] ) ) {
			throw new Exception( esc_html( $error_messages[ $status_code ] ) );
		}

		// Unknown error — show code and message if available.
		$message = $result_status['message'] ?? $status['message'] ?? '';

		if ( ! empty( $message ) ) {
			throw new Exception(
				sprintf(
				/* translators: %s: error message from server */
					__( 'خطای سرور پرداخت: %s', 'ewfw' ),
					esc_html( $message )
				)
			);
		}

		// Fallback.
		throw new Exception(
			sprintf(
			/* translators: %d: error code */
				__( 'خطای نامشخص از سرور پرداخت (کد: %d). لطفاً با پشتیبانی تماس بگیرید.', 'ewfw' ),
				absint( $status_code )
			)
		);
	}

	/**
	 * Get scope string for login.
	 *
	 * @return string
	 */
	private function get_scope() {
		return implode( ' ', [
			'GET:/services/auth/v1.0/token/refresh/{refreshToken}*',
			'GET:/services/account/thirdparty/v1.0/contract',
			'POST:/services/account/thirdparty/v1.0/contract',
			'GET:/services/account/thirdparty/v1.0/contract/{id}/request',
			'GET:/services/account/thirdparty/contract/v1.0/{id}/transactions*',
			'GET:/services/account/thirdparty/contract/v1.0/transactions*',
			'POST:/services/account/thirdparty/contract/v1.0/{id}/refund/{relTransactionId}/submit',
			'PUT:/services/account/thirdparty/contract/v1.0/{id}/refund/{transactionId}/confirm',
			'POST:/services/account/thirdparty/contract/v1.0/{contractId}/reserve',
			'PUT:/services/account/thirdparty/contract/v1.0/{reserveId}/confirm',
			'PUT:/services/payment/thirdparty/wallet/v1.0/{reserveId}/reverse',
			'GET:/services/account/thirdparty/contract/v1.0/{reserveId}/transaction',
		] );
	}

	/**
	 * Client login to get bearer token.
	 *
	 * @return string Bearer token.
	 * @throws Exception On failure.
	 */
	public function client_login() {
		$res = $this->request( 'GET', '/services/auth/v1.0/client/login', [
			'clientId'     => $this->client_id,
			'clientSecret' => $this->client_secret,
			'scope'        => $this->get_scope(),
		] );

		if ( empty( $res['data']['result']['data']['token'] ) ) {
			throw new Exception( __( 'احراز هویت اِنوانو رد شد.', 'ewfw' ) . ' ' . wp_json_encode( $res['data'] ) );
		}

		$this->token = $res['data']['result']['data']['token'];
		return $this->token;
	}

	/**
	 * Ensure token exists.
	 *
	 * @return void
	 */
	private function ensure_token() {
		if ( ! $this->token ) {
			$this->client_login();
		}
	}

	/**
	 * Check or create contract.
	 *
	 * @param string $msisdn       Phone number.
	 * @param string $callback_url Callback URL for contract signing.
	 * @return array Status and contract code or redirect URL.
	 * @throws Exception On failure.
	 */
	public function get_or_create_contract( $msisdn, $callback_url = '' ) {
		$this->ensure_token();

		$res = $this->request( 'GET', '/services/account/thirdparty/v1.0/contract', [
			'Authorization' => 'Bearer ' . $this->token,
			'msisdn'        => $msisdn,
		] );

		// Contract exists.
		if ( ! empty( $res['data']['result']['data']['code'] ) ) {
			return [
				'status'   => 'exists',
				'contract' => $res['data']['result']['data']['code'],
			];
		}
		$status_code = $res['data']['result']['status']['code'] ?? 0;

		// No wallet.
		if ( 1009 === $status_code ) {
			throw new Exception( __( 'کیف پول اِوانو برای این شماره فعال نیست', 'ewfw' ) );
		}

		// No contract – need redirect.
		if ( 1059 === $status_code ) {
			return $this->reserve_contract( $msisdn, $callback_url );
		}

		throw new Exception( __( 'بررسی قرارداد موفق نبود.', 'ewfw' ) . ' ' . wp_json_encode( $res['data'] ) );
	}

	/**
	 * Reserve a contract (user must sign in EWANO).
	 *
	 * @param string $msisdn       Phone number.
	 * @param string $callback_url Return URL after signing.
	 * @return array Redirect URL.
	 * @throws Exception On failure.
	 */
	private function reserve_contract( $msisdn, $callback_url ) {
		$res = $this->request( 'POST', '/services/account/thirdparty/v1.0/contract', [
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
		], [
			'msisdn'      => $msisdn,
			'callbackUrl' => $callback_url,
		] );

		if ( ! empty( $res['data']['result']['data']['contractUrl'] ) ) {
			return [
				'status'       => 'redirect',
				'contract_url' => $this->base_url . $res['data']['result']['data']['contractUrl'],
			];
		}

		throw new Exception( __( 'خطا در رزرو قرارداد.', 'ewfw' ) . ' ' . wp_json_encode( $res['data'] ) );
	}

	/**
	 * Get SDK token ID (UUID).
	 *
	 * @param string $msisdn Phone number.
	 * @return string UUID token ID.
	 * @throws Exception On failure.
	 */
	public function get_token_id( $msisdn ) {
		$res = $this->request( 'GET', '/services/auth/v1.0/user/sdk/login/token-id', [
			'clientId'     => $this->client_id,
			'clientSecret' => $this->client_secret,
			'msisdn'        => $msisdn,
		] );

		if ( ! empty( $res['data']['result']['data']['id'] ) ) {
			return $res['data']['result']['data']['id'];
		}

		throw new Exception( __( 'توکن احراز هویت دریافت نشد.', 'ewfw' ) . ' ' . wp_json_encode( $res['data'] ) );
	}

	/**
	 * Reserve amount.
	 *
	 * @param string $contract_code Contract UUID.
	 * @param int    $amount        Amount in Rials.
	 * @return string Reserve ID.
	 * @throws Exception On failure.
	 */
	public function reserve( $contract_code, $amount ) {
		$this->ensure_token();

		$res = $this->request( 'POST', "/services/account/thirdparty/contract/v1.0/{$contract_code}/reserve", [
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
		], [ 'amount' => $amount ] );

		if ( ! empty( $res['data']['result']['data']['id'] ) ) {
			return $res['data']['result']['data']['id'];
		}

		throw new Exception( __( 'خطا در رزور برداشتِ مبلغ.', 'ewfw' ) . ' ' . wp_json_encode( $res['data'] ) );
	}

	/**
	 * Confirm withdrawal.
	 *
	 * @param string $reserve_id Reserve ID.
	 * @return array Response data.
	 * @throws Exception On failure.
	 */
	public function confirm( $reserve_id ) {
		$this->ensure_token();

		$res = $this->request( 'PUT', "/services/account/thirdparty/contract/v1.0/{$reserve_id}/confirm", [
			'Authorization' => 'Bearer ' . $this->token,
		] );

		return $res['data'];
	}

	/**
	 * Get transaction status.
	 *
	 * @param string $reserve_id Reserve ID.
	 * @return array Response data.
	 * @throws Exception On failure.
	 */
	public function transaction_status( $reserve_id ) {
		$this->ensure_token();

		$res = $this->request( 'GET', "/services/account/thirdparty/contract/v1.0/{$reserve_id}/transaction", [
			'Authorization' => 'Bearer ' . $this->token,
		] );

		return $res['data'];
	}

	/**
	 * Reverse a reserve.
	 *
	 * @param string $reserve_id Reserve ID.
	 * @return array Response data.
	 * @throws Exception On failure.
	 */
	public function reverse( $reserve_id ) {
		$this->ensure_token();

		$res = $this->request( 'PUT', "/services/payment/thirdparty/wallet/v1.0/{$reserve_id}/reverse", [
			'Authorization' => 'Bearer ' . $this->token,
		] );

		return $res['data'];
	}

	/**
	 * Submit refund.
	 *
	 * @param string $contract_code  Contract UUID.
	 * @param string $transaction_id Transaction ID from EWANO.
	 * @param int    $amount         Amount to refund in Rials.
	 * @param int    $order_id       WooCommerce order ID.
	 * @return string Refund ID.
	 * @throws Exception On failure.
	 */
	public function refund_submit( $contract_code, $transaction_id, $amount, $order_id ) {
		$this->ensure_token();

		$res = $this->request( 'POST', "/services/account/thirdparty/contract/v1.0/{$contract_code}/refund/{$transaction_id}/submit", [
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
		], [
			'amount'  => $amount,
			'orderId' => wp_generate_uuid4(),
		] );

		if ( ! empty( $res['data']['result']['data']['id'] ) ) {
			return $res['data']['result']['data']['id'];
		}

		throw new Exception( __( 'خطا در بازگشت وجه', 'ewfw' ) . ' ' . wp_json_encode( $res['data'] ) );
	}

	/**
	 * Confirm refund.
	 *
	 * @param string $contract_code Contract UUID.
	 * @param string $refund_id     Refund transaction ID.
	 * @return array Response data.
	 * @throws Exception On failure.
	 */
	public function refund_confirm( $contract_code, $refund_id ) {
		$this->ensure_token();

		$res = $this->request( 'PUT', "/services/account/thirdparty/contract/v1.0/{$contract_code}/refund/{$refund_id}/confirm", [
			'Authorization' => 'Bearer ' . $this->token,
		] );

		return $res['data'];
	}

	/**
	 * Get bearer token.
	 *
	 * @return string|null
	 */
	public function get_token() {
		return $this->token;
	}

	/**
	 * Get client ID.
	 *
	 * @return string
	 */
	public function get_client_id() {
		return $this->client_id;
	}
}