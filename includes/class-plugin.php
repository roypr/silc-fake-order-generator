<?php
/**
 * Main plugin class for SILC Fake Order Generator.
 *
 * @package SILC_FOG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SILC_FOG_Plugin
 */
class SILC_FOG_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get order statuses supported by the generator.
	 *
	 * The returned values are unprefixed WooCommerce status slugs so they can
	 * be passed directly to WC_Order::update_status() and related APIs.
	 *
	 * @return string[]
	 */
	public static function get_supported_statuses(): array {
		return array(
			'pending',
			'processing',
			'on-hold',
			'completed',
			'cancelled',
			'refunded',
			'failed',
		);
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init(): void {
		// Placeholder for future init logic.
	}
}
