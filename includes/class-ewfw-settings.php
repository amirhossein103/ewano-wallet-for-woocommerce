<?php
/**
 * Admin settings for EWANO Wallet gateway.
 *
 * @package EWANO_Wallet_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * EWFW_Settings class.
 */
class EWFW_Settings {

	/**
	 * Get all plugin settings.
	 *
	 * @return array
	 */
	public static function get_fields() {
		$fields = [
			'enabled' => [
				'title'   => __( 'فعال‌سازی', 'ewfw' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی پرداخت با کیف پول اوانو', 'ewfw' ),
				'default' => 'no',
			],
			'title' => [
				'title'       => __( 'عنوان', 'ewfw' ),
				'type'        => 'text',
				'description' => __( 'عنوانی که مشتری در صفحه پرداخت می‌بیند.', 'ewfw' ),
				'default'     => __( 'کیف پول اوانو', 'ewfw' ),
			],
			'description' => [
				'title'       => __( 'توضیحات', 'ewfw' ),
				'type'        => 'textarea',
				'description' => __( 'توضیحاتی که برای مشتری نمایش داده می‌شود.', 'ewfw' ),
				'default'     => __( 'پرداخت مستقیم از کیف پول اوانو با تأیید رمز یکبارمصرف.', 'ewfw' ),
			],
			'environment' => [
				'title'       => __( 'محیط', 'ewfw' ),
				'type'        => 'select',
				'description' => __( 'انتخاب محیط API.', 'ewfw' ),
				'options'     => [
					'sandbox'    => __( 'Sandbox (توسعه)', 'ewfw' ),
					'stage'      => __( 'Stage (آزمایشی)', 'ewfw' ),
					'production' => __( 'Production (عملیاتی)', 'ewfw' ),
				],
				'default'     => 'sandbox',
			],
			'client_id' => [
				'title'       => __( 'Client ID', 'ewfw' ),
				'type'        => 'text',
				'description' => __( 'شناسه کلاینت دریافتی از EWANO.', 'ewfw' ),
				'default'     => '',
			],
			'client_secret' => [
				'title'       => __( 'Client Secret', 'ewfw' ),
				'type'        => 'password',
				'description' => __( 'کلید امنیتی دریافتی از EWANO.', 'ewfw' ),
				'default'     => '',
			],

			'connection_settings' => [
				'title'       => __( 'تنظیمات اتصال', 'ewfw' ),
				'type'        => 'title',
				'description' => __( 'تنظیمات پیشرفته برای رفع مشکلات اتصال به سرور EWANO.', 'ewfw' ),
			],
			'ssl_verify' => [
				'title'       => __( 'تأیید SSL', 'ewfw' ),
				'type'        => 'select',
				'description' => __( 'در صورت خطای TLS connect error، این گزینه را روی "غیرفعال" قرار دهید. توجه: غیرفعال کردن SSL امنیت ارتباط را کاهش می‌دهد و فقط بر روش WordPress HTTP API تأثیر دارد.', 'ewfw' ),
				'options'     => [
					'enabled'  => __( 'فعال (پیش‌فرض امن)', 'ewfw' ),
					'disabled' => __( 'غیرفعال (فقط در صورت خطای TLS)', 'ewfw' ),
				],
				'default'     => 'enabled',
			],
			'request_method' => [
				'title'       => __( 'روش ارسال درخواست', 'ewfw' ),
				'type'        => 'select',
				'description' => __( 'در صورت خطای CORS یا Connection Error، گزینه "cURL مستقیم" را انتخاب کنید. پیش‌فرض: WordPress HTTP API.', 'ewfw' ),
				'options'     => [
					'wp'   => __( 'WordPress HTTP API (پیش‌فرض)', 'ewfw' ),
					'curl' => __( 'cURL مستقیم (برای رفع مشکلات اتصال)', 'ewfw' ),
				],
				'default'     => 'wp',
			],
			'phone_source' => [
				'title'       => __( 'منبع شماره موبایل', 'ewfw' ),
				'type'        => 'select',
				'description' => __( 'شماره موبایل کاربر از کجا خوانده شود.', 'ewfw' ),
				'options'     => self::get_phone_sources(),
				'default'     => 'billing_phone',
			],
			'post_meta_key' => [
				'title'             => __( 'کلید پست متا', 'ewfw' ),
				'type'              => 'text',
				'description'       => __( 'کلید متای سفارش که شماره موبایل در آن ذخیره شده است.', 'ewfw' ),
				'default'           => '',
				'custom_attributes' => [
					'data-depends-on' => 'phone_source:post_meta',
				],
			],
			'user_meta_key' => [
				'title'             => __( 'کلید یوزر متا', 'ewfw' ),
				'type'              => 'text',
				'description'       => __( 'کلید متای کاربر که شماره موبایل در آن ذخیره شده است.', 'ewfw' ),
				'default'           => '',
				'custom_attributes' => [
					'data-depends-on' => 'phone_source:user_meta',
				],
			],
			'sdk_url' => [
				'title'       => __( 'آدرس SDK', 'ewfw' ),
				'type'        => 'text',
				'description' => __( 'آدرس فایل جاوااسکریپت SDK اوانو.', 'ewfw' ),
				'default'     => 'https://static-ebcom.mci.ir/static/app/wallet-sdk/sdk/app-sdk-v1.0.0.min.js',
			],
		];

		return $fields;
	}

	/**
	 * Get available phone sources.
	 *
	 * @return array
	 */
	private static function get_phone_sources() {
		return [
			'billing_phone' => __( 'موبایل صورت حساب', 'ewfw' ),
			'manual'        => __( 'وارد کردن توسط مشتری', 'ewfw' ),
			'digits'        => __( 'از منبع دیجیتس', 'ewfw' ),
			'post_meta'     => __( 'پست متا اختصاصی', 'ewfw' ),
			'user_meta'     => __( 'یوزر متا اختصاصی', 'ewfw' ),
		];
	}

	/**
	 * Get a single option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_option( $key, $default = '' ) {
		$options = get_option( 'woocommerce_ewfw_settings', [] );
		return $options[ $key ] ?? $default;
	}
}