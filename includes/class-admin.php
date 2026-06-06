<?php
/**
 * Admin page for SILC Fake Order Generator.
 *
 * @package SILC_FOG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SILC_FOG_Admin
 */
class SILC_FOG_Admin {

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu page.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Fake Order Generator', 'silc-fake-order-generator' ),
			__( 'Fake Orders', 'silc-fake-order-generator' ),
			'manage_woocommerce',
			'silc-fake-order-generator',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_silc-fake-order-generator' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'silc-fog-admin',
			SILC_FOG_URL . 'assets/css/admin.css',
			array(),
			SILC_FOG_VERSION
		);

		wp_enqueue_script(
			'silc-fog-admin',
			SILC_FOG_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			SILC_FOG_VERSION,
			true
		);

		wp_localize_script(
			'silc-fog-admin',
			'silc_fog_data',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'silc_fog_nonce' ),
				'batch_size' => 5,
			)
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'silc-fake-order-generator' ) );
		}

		$shipping_methods = $this->get_shipping_methods();
		$payment_gateways = $this->get_payment_gateways();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SILC Fake Order Generator', 'silc-fake-order-generator' ); ?></h1>

			<form id="silc-fog-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>
						<!-- Start Date -->
						<tr>
							<th scope="row">
								<label for="fog_start_date"><?php esc_html_e( 'Start Date', 'silc-fake-order-generator' ); ?></label>
							</th>
							<td>
								<input type="date" id="fog_start_date" name="fog_start_date" class="regular-text" required />
								<p class="description"><?php esc_html_e( 'Earliest order date.', 'silc-fake-order-generator' ); ?></p>
							</td>
						</tr>

						<!-- End Date -->
						<tr>
							<th scope="row">
								<label for="fog_end_date"><?php esc_html_e( 'End Date', 'silc-fake-order-generator' ); ?></label>
							</th>
							<td>
								<input type="date" id="fog_end_date" name="fog_end_date" class="regular-text" required />
								<p class="description"><?php esc_html_e( 'Latest order date. Must be after Start Date.', 'silc-fake-order-generator' ); ?></p>
							</td>
						</tr>

						<!-- Number of Orders -->
						<tr>
							<th scope="row">
								<label for="fog_num_orders"><?php esc_html_e( 'Number of Orders', 'silc-fake-order-generator' ); ?></label>
							</th>
							<td>
								<input type="number" id="fog_num_orders" name="fog_num_orders" class="small-text" min="1" max="10000" value="50" required />
								<p class="description"><?php esc_html_e( 'Total orders to generate.', 'silc-fake-order-generator' ); ?></p>
							</td>
						</tr>

						<!-- Items per order range -->
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Items per Order', 'silc-fake-order-generator' ); ?></label>
							</th>
							<td>
								<label for="fog_min_items"><?php esc_html_e( 'Min:', 'silc-fake-order-generator' ); ?></label>
								<input type="number" id="fog_min_items" name="fog_min_items" class="small-text" min="1" max="5" value="1" />
								<label for="fog_max_items"><?php esc_html_e( 'Max:', 'silc-fake-order-generator' ); ?></label>
								<input type="number" id="fog_max_items" name="fog_max_items" class="small-text" min="1" max="5" value="3" />
								<p class="description"><?php esc_html_e( 'Random number of distinct items per order (1-5).', 'silc-fake-order-generator' ); ?></p>
							</td>
						</tr>

						<!-- Shipping Methods -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Shipping Methods', 'silc-fake-order-generator' ); ?>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Shipping Methods', 'silc-fake-order-generator' ); ?></legend>
									<?php if ( ! empty( $shipping_methods ) ) : ?>
										<?php foreach ( $shipping_methods as $method_id => $method_title ) : ?>
											<label>
												<input type="checkbox" name="fog_shipping_methods[]" value="<?php echo esc_attr( $method_id ); ?>" checked />
												<?php echo esc_html( $method_title ); ?>
											</label><br />
										<?php endforeach; ?>
									<?php else : ?>
										<p class="description"><?php esc_html_e( 'No shipping methods found. Please set up shipping zones and methods in WooCommerce.', 'silc-fake-order-generator' ); ?></p>
									<?php endif; ?>
								</fieldset>
							</td>
						</tr>

						<!-- Payment Methods -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Payment Methods', 'silc-fake-order-generator' ); ?>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Payment Methods', 'silc-fake-order-generator' ); ?></legend>
									<?php if ( ! empty( $payment_gateways ) ) : ?>
										<?php foreach ( $payment_gateways as $gateway_id => $gateway_title ) : ?>
											<label>
												<input type="checkbox" name="fog_payment_methods[]" value="<?php echo esc_attr( $gateway_id ); ?>" checked />
												<?php echo esc_html( $gateway_title ); ?>
											</label><br />
										<?php endforeach; ?>
									<?php else : ?>
										<p class="description"><?php esc_html_e( 'No active payment gateways found.', 'silc-fake-order-generator' ); ?></p>
									<?php endif; ?>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" id="silc-fog-start" class="button button-primary">
						<?php esc_html_e( 'Generate Orders', 'silc-fake-order-generator' ); ?>
					</button>
				</p>
			</form>

			<!-- Progress area (hidden initially) -->
			<div id="silc-fog-progress-wrap" style="display:none;">
				<h2><?php esc_html_e( 'Generation Progress', 'silc-fake-order-generator' ); ?></h2>
				<div class="silc-fog-progress-bar">
					<div class="silc-fog-progress-bar-fill" id="silc-fog-progress-fill" style="width:0%;"></div>
				</div>
				<p>
					<span id="silc-fog-progress-text">0 / 0</span>
					&mdash;
					<span id="silc-fog-progress-status"><?php esc_html_e( 'Starting...', 'silc-fake-order-generator' ); ?></span>
				</p>
				<p>
					<button type="button" id="silc-fog-cancel" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'silc-fake-order-generator' ); ?>
					</button>
				</p>
				<div id="silc-fog-log"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get active shipping methods from all shipping zones.
	 *
	 * @return array<string, string> Method ID => Title.
	 */
	private function get_shipping_methods(): array {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}

		$methods = array();

		try {
			$zones = WC_Shipping_Zones::get_zones();
			// Include the "Rest of the World" zone (index 0).
			$zones[0] = WC_Shipping_Zones::get_zone( 0 );

			foreach ( $zones as $zone ) {
				if ( is_numeric( $zone ) ) {
					$zone_obj = WC_Shipping_Zones::get_zone( (int) $zone );
				} elseif ( is_array( $zone ) && isset( $zone['zone_id'] ) ) {
					$zone_obj = WC_Shipping_Zones::get_zone( (int) $zone['zone_id'] );
				} elseif ( $zone instanceof WC_Shipping_Zone ) {
					$zone_obj = $zone;
				} else {
					continue;
				}

				$zone_methods = $zone_obj->get_shipping_methods( true );
				foreach ( $zone_methods as $method ) {
					$methods[ $method->get_rate_id() ] = $method->get_title() . ' (' . $zone_obj->get_zone_name() . ')';
				}
			}
		} catch ( Exception $e ) {
			// Fail gracefully.
		}

		return $methods;
	}

	/**
	 * Get active payment gateways.
	 *
	 * @return array<string, string> Gateway ID => Title.
	 */
	private function get_payment_gateways(): array {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}

		$gateways = array();
		$available = WC()->payment_gateways()->get_available_payment_gateways();

		foreach ( $available as $gateway ) {
			$gateways[ $gateway->id ] = $gateway->get_title();
		}

		return $gateways;
	}
}
