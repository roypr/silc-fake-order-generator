<?php
/**
 * Plugin Name: SILC Fake Order Generator
 * Plugin URI:  https://silc.com/
 * Description: Generate fake WooCommerce orders for testing purposes. Supports batching, random customers, products, variations, shipping, payment methods, and coupons.
 * Version:     1.1.0
 * Author:      SILC
 * Text Domain: silc-fake-order-generator
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * WC requires at least: 8.0
 * License:     GPL v2 or later
 */

// Guard against direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SILC_FOG_VERSION', '1.1.0' );
define( 'SILC_FOG_PATH', plugin_dir_path( __FILE__ ) );
define( 'SILC_FOG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the plugin after WooCommerce is loaded.
 */
add_action( 'plugins_loaded', function () {
	// Ensure WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once SILC_FOG_PATH . 'includes/class-plugin.php';
	require_once SILC_FOG_PATH . 'includes/class-admin.php';
	require_once SILC_FOG_PATH . 'includes/class-ajax.php';

	SILC_FOG_Plugin::get_instance();
	SILC_FOG_Admin::get_instance();
	SILC_FOG_Ajax::get_instance();
} );
