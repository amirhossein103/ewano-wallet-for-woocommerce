<?php
/**
 * Handles phone number extraction, conversion, and validation.
 *
 * @package EWANO_Wallet_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * EWFW_Phone class.
 */
class EWFW_Phone {

	/**
	 * Get user phone number from order or user.
	 *
	 * @param WC_Order $order Order object.
	 * @return string Sanitized 10-digit phone number starting with 9, or empty string.
	 */
	public static function get_order_phone( $order ) {
		$source = EWFW_Settings::get_option( 'phone_source', 'billing_phone' );

		switch ( $source ) {
			case 'digits':
				$phone = self::get_digits_phone( $order );
				break;
			case 'manual':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$phone = isset( $_POST['ewfw_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ewfw_phone'] ) ) : '';
				break;
			case 'post_meta':
				$phone = self::get_post_meta_phone( $order );
				break;
			case 'user_meta':
				$phone = self::get_user_meta_phone( $order );
				break;
			case 'billing_phone':
			default:
				$phone = $order->get_billing_phone();
				break;
		}

		// Allow other plugins to modify phone.
		$phone = apply_filters( 'ewfw_get_user_phone', $phone, $order );

		return self::normalize_phone( $phone );
	}

	/**
	 * Get phone from Digits plugin.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private static function get_digits_phone( $order ) {
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			$phone = get_user_meta( $user_id, 'digits_phone', true );
			if ( ! empty( $phone ) ) {
				return $phone;
			}
		}
		return $order->get_billing_phone();
	}

	/**
	 * Get phone from post meta.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private static function get_post_meta_phone( $order ) {
		$meta_key = EWFW_Settings::get_option( 'post_meta_key', '' );
		if ( empty( $meta_key ) ) {
			return '';
		}
		return $order->get_meta( $meta_key, true );
	}

	/**
	 * Get phone from user meta.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private static function get_user_meta_phone( $order ) {
		$user_id  = $order->get_user_id();
		$meta_key = EWFW_Settings::get_option( 'user_meta_key', '' );

		if ( ! $user_id || empty( $meta_key ) ) {
			return '';
		}
		return get_user_meta( $user_id, $meta_key, true );
	}

	/**
	 * Normalize phone number: convert Persian/Arabic digits, remove non-digits,
	 * strip country code, and ensure 10-digit format starting with 9.
	 *
	 * @param string $phone Raw phone number.
	 * @return string Clean 10-digit phone starting with 9, or empty string.
	 */
	public static function normalize_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}

		// 1. Convert Persian and Arabic digits to English.
		$persian = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
		$arabic  = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
		$english = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];

		$phone = str_replace( $persian, $english, $phone );
		$phone = str_replace( $arabic, $english, $phone );

		// 2. Remove all non-digit characters (this also removes '+').
		$phone = preg_replace( '/\D/', '', $phone );

		// 3. Remove leading country code if present (98, 0098).
		if ( preg_match( '/^98\d{10}$/', $phone ) ) {
			$phone = substr( $phone, 2 );
		} elseif ( preg_match( '/^0098\d{10}$/', $phone ) ) {
			$phone = substr( $phone, 4 );
		}

		// 4. Remove leading zero if length > 10.
		if ( strlen( $phone ) > 10 && '0' === $phone[0] ) {
			$phone = substr( $phone, 1 );
		}

		// 5. Must be exactly 10 digits starting with 9.
		if ( ! preg_match( '/^9\d{9}$/', $phone ) ) {
			return '';
		}

		return $phone;
	}
}