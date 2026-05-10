<?php
/**
 * Plugin Name:       پرداخت از کیف پول اِوانو برای ووکامرس
 * Plugin URI:        https://github.com/amirhossein103/ewano-wallet-for-woocommerce
 * Description:       پرداخت مستقیم از کیف پول اوانو با پشتیبانی از کسر موجودی و پرداخت مابقی
 * Version:           1.0.0
 * Author:            Amirhossein Lalehei
 * Author URI:        https://github.com/amirhossein103/
 * Text Domain:       ewfw
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.5
 * Requires Plugins:  woocommerce
 *
 * @package EWANO_Wallet_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

// Constants.
define( 'EWFW_VERSION', '1.0.0' );
define( 'EWFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EWFW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EWFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes.
spl_autoload_register( function ( $class ) {
	$prefix = 'EWFW_';
	$dir    = EWFW_PLUGIN_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$class_name = substr( $class, strlen( $prefix ) );
	$file       = $dir . 'class-ewfw-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Load textdomain.
add_action( 'init', function () {
	load_plugin_textdomain( 'ewfw', false, dirname( EWFW_PLUGIN_BASENAME ) . '/languages' );
} );

// Register payment gateway.
add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
	$gateways[] = 'EWFW_Gateway';
	return $gateways;
} );

// Declare compatibility with WooCommerce features.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
	}
} );