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
