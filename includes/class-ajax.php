<?php
/**
 * AJAX handlers for SILC Fake Order Generator.
 *
 * @package SILC_FOG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SILC_FOG_Ajax
 */
class SILC_FOG_Ajax {

	/**
	 * Default batch size.
	 */
	const BATCH_SIZE = 5;

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
		add_action( 'wp_ajax_silc_fog_generate_batch', array( $this, 'handle_generate_batch' ) );
	}

	/**
	 * Handle batch generation AJAX request.
	 *
	 * @return void
	 */
	public function handle_generate_batch(): void {
		// Verify nonce and capabilities.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'silc_fog_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'silc-fake-order-generator' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'silc-fake-order-generator' ) ) );
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$params = array(
			'start_date'        => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
			'end_date'          => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
			'num_orders'        => absint( $_POST['num_orders'] ?? 0 ),
			'batch_index'       => absint( $_POST['batch_index'] ?? 0 ),
			'min_items'         => absint( $_POST['min_items'] ?? 1 ),
			'max_items'         => absint( $_POST['max_items'] ?? 3 ),
			'shipping_methods'  => isset( $_POST['shipping_methods'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['shipping_methods'] ) ) : array(),
			'payment_methods'   => isset( $_POST['payment_methods'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['payment_methods'] ) ) : array(),
			'statuses'          => isset( $_POST['statuses'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['statuses'] ) ) : array(),
		);

		// Validation.
		if ( empty( $params['start_date'] ) || empty( $params['end_date'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Start and end dates are required.', 'silc-fake-order-generator' ) ) );
		}

		if ( $params['num_orders'] < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Number of orders must be at least 1.', 'silc-fake-order-generator' ) ) );
		}

		if ( strtotime( $params['start_date'] ) > strtotime( $params['end_date'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Start date must be before end date.', 'silc-fake-order-generator' ) ) );
		}

		$min_items = max( 1, min( $params['min_items'], 5 ) );
		$max_items = max( $min_items, min( $params['max_items'], 5 ) );

		$statuses = array_values( array_intersect( SILC_FOG_Plugin::get_supported_statuses(), $params['statuses'] ) );
		if ( empty( $statuses ) ) {
			$statuses = SILC_FOG_Plugin::get_supported_statuses();
		}

		// Calculate how many orders to create in this batch.
		$remaining  = $params['num_orders'] - ( $params['batch_index'] * self::BATCH_SIZE );
		$batch_size = min( self::BATCH_SIZE, $remaining );

		if ( $batch_size <= 0 ) {
			wp_send_json_success(
				array(
					'done'  => true,
					'created' => 0,
					'message' => __( 'All orders generated.', 'silc-fake-order-generator' ),
				)
			);
		}

		// Pre-fetch data needed for generation.
		$customer_ids      = $this->get_customer_ids();
		$simple_product_ids = $this->get_simple_product_ids();
		$variation_ids      = $this->get_variation_ids();

		if ( empty( $customer_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No customers found. Please create some customers first.', 'silc-fake-order-generator' ) ) );
		}

		if ( empty( $simple_product_ids ) && empty( $variation_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No products found. Please add some products first.', 'silc-fake-order-generator' ) ) );
		}

		$created = 0;
		$errors  = array();

		for ( $i = 0; $i < $batch_size; $i++ ) {
			$result = $this->create_single_order(
				$customer_ids,
				$simple_product_ids,
				$variation_ids,
				$params['start_date'],
				$params['end_date'],
				$min_items,
				$max_items,
				$params['shipping_methods'],
				$params['payment_methods'],
				$statuses
			);

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$created++;
			}
		}

		$new_index  = $params['batch_index'] + 1;
		$total_done = $new_index * self::BATCH_SIZE;
		if ( $total_done > $params['num_orders'] ) {
			$total_done = $params['num_orders'];
		}

		$response = array(
			'done'          => $total_done >= $params['num_orders'],
			'batch_index'   => $new_index,
			'created'       => $created,
			'total_created' => $total_done,
			'errors'        => $errors,
		);

		wp_send_json_success( $response );
	}

	/**
	 * Create a single fake order.
	 *
	 * @param array  $customer_ids      List of customer user IDs.
	 * @param array  $simple_product_ids List of simple product IDs.
	 * @param array  $variation_ids     List of variation product IDs.
	 * @param string $start_date        Start date string (Y-m-d).
	 * @param string $end_date          End date string (Y-m-d).
	 * @param int    $min_items         Minimum items per order.
	 * @param int    $max_items         Maximum items per order.
	 * @param array  $shipping_methods  Selected shipping method rate IDs.
	 * @param array  $payment_methods   Selected payment gateway IDs.
	 * @param array  $statuses          Unprefixed statuses allowed for randomization.
	 *
	 * @return WC_Order|WP_Error
	 */
	private function create_single_order(
		array $customer_ids,
		array $simple_product_ids,
		array $variation_ids,
		string $start_date,
		string $end_date,
		int $min_items,
		int $max_items,
		array $shipping_methods,
		array $payment_methods,
		array $statuses
	) {
		try {
			// 1. Pick a random customer.
			$customer_id = $customer_ids[ array_rand( $customer_ids ) ];
			$customer    = new WC_Customer( $customer_id );

			// 2. Create the order.
			$order = wc_create_order( array( 'customer_id' => $customer_id ) );
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			// 3. Generate a random order date between start and end.
			$order_date = $this->random_date( $start_date, $end_date );
			$order->set_date_created( $order_date );

			// 4. New orders start as pending. The randomized target status is
			// applied after items/totals are saved so WooCommerce's normal
			// status-transition pipeline runs exactly like a real order.
			$target_status = $statuses[ array_rand( $statuses ) ];
			$order->set_status( 'pending' );

			// 4b. WooCommerce Analytics filters reports by date_paid (and
			// date_completed) by default. Backdate those properties for statuses
			// that imply payment so generated orders fall inside the requested
			// date range in wc_order_stats.
			if ( in_array( $target_status, array( 'processing', 'completed', 'refunded' ), true ) ) {
				$order->set_date_paid( $order_date );
			}
			if ( 'completed' === $target_status ) {
				$order->set_date_completed( $order_date );
			}

			// 5. Add random items.
			$num_items = wp_rand( $min_items, $max_items );
			$used_products = array(); // Track already-used product IDs to avoid duplicates.

			for ( $j = 0; $j < $num_items; $j++ ) {
				$product = $this->get_random_product( $simple_product_ids, $variation_ids, $used_products );
				if ( ! $product ) {
					continue;
				}

				$used_products[] = $product->get_id();
				$qty             = wp_rand( 1, 5 );

				$item_id = $order->add_product( $product, $qty );

				if ( $product->is_type( 'variation' ) ) {
					// Set variation attributes on the line item for proper display.
					$variation_attributes = $product->get_variation_attributes();
					if ( ! empty( $variation_attributes ) ) {
						$item = $order->get_item( $item_id );
						if ( $item ) {
							foreach ( $variation_attributes as $attr_name => $attr_value ) {
								$item->add_meta_data( $attr_name, $attr_value, true );
							}
							$item->save();
						}
					}
				}
			}

			// 6. Set billing address from customer data.
			$billing_address = array(
				'first_name' => $customer->get_billing_first_name() ?: $customer->get_display_name(),
				'last_name'  => $customer->get_billing_last_name() ?: '',
				'company'    => $customer->get_billing_company() ?: '',
				'address_1'  => $customer->get_billing_address_1() ?: $this->random_address_line(),
				'address_2'  => $customer->get_billing_address_2() ?: '',
				'city'       => $customer->get_billing_city() ?: $this->random_city(),
				'state'      => $customer->get_billing_state() ?: '',
				'postcode'   => $customer->get_billing_postcode() ?: $this->random_postcode(),
				'country'    => $customer->get_billing_country() ?: 'US',
				'email'      => $customer->get_billing_email() ?: $customer->get_email(),
				'phone'      => $customer->get_billing_phone() ?: '',
			);

			// Remove empty values gracefully - Order expects all keys.
			$order->set_billing_address( array_filter( $billing_address ) );

			// 7. Shipping same as billing.
			$shipping_address = array(
				'first_name' => $customer->get_shipping_first_name() ?: $billing_address['first_name'],
				'last_name'  => $customer->get_shipping_last_name() ?: $billing_address['last_name'],
				'company'    => $customer->get_shipping_company() ?: $billing_address['company'],
				'address_1'  => $customer->get_shipping_address_1() ?: $billing_address['address_1'],
				'address_2'  => $customer->get_shipping_address_2() ?: $billing_address['address_2'],
				'city'       => $customer->get_shipping_city() ?: $billing_address['city'],
				'state'      => $customer->get_shipping_state() ?: $billing_address['state'],
				'postcode'   => $customer->get_shipping_postcode() ?: $billing_address['postcode'],
				'country'    => $customer->get_shipping_country() ?: $billing_address['country'],
			);
			$order->set_shipping_address( array_filter( $shipping_address ) );

			// 8. Set random payment method.
			if ( ! empty( $payment_methods ) ) {
				$payment_method_id = $payment_methods[ array_rand( $payment_methods ) ];
				$gateways = WC()->payment_gateways()->payment_gateways();
				if ( isset( $gateways[ $payment_method_id ] ) ) {
					$order->set_payment_method( $gateways[ $payment_method_id ] );
				} else {
					$order->set_payment_method( $payment_method_id );
					$order->set_payment_method_title( ucfirst( str_replace( '_', ' ', $payment_method_id ) ) );
				}
			}

			// 9. Add shipping method if selected.
			if ( ! empty( $shipping_methods ) ) {
				$shipping_rate_id = $shipping_methods[ array_rand( $shipping_methods ) ];
				$shipping_cost    = round( wp_rand( 500, 3000 ) / 100, 2 ); // Random $5.00 - $30.00

				try {
					$shipping_item = new WC_Order_Item_Shipping();
					$shipping_item->set_method_id( $shipping_rate_id );
					$shipping_item->set_method_title( __( 'Shipping', 'silc-fake-order-generator' ) );
					$shipping_item->set_total( $shipping_cost );
					$order->add_item( $shipping_item );
				} catch ( Exception $e ) {
					// Silently skip shipping if there's an issue.
				}
			}

			// 10. Calculate totals.
			$order->calculate_totals( false ); // false = no taxes for simplicity.

			// 11. Save the order before transitioning status.
			$order->save();

			// 12. Apply the randomized status through WC's status pipeline.
			$this->apply_order_status( $order, $target_status );

			// 13. Push the order into the WooCommerce Analytics lookup tables
			// immediately. The async pipeline can miss generated orders (e.g. when
			// the target status equals the initial status and update_status()
			// short-circuits without firing woocommerce_update_order), so sync
			// explicitly to guarantee the order is reflected in order_stats.
			$this->sync_order_analytics( $order->get_id() );

			return $order;

		} catch ( Exception $e ) {
			return new WP_Error( 'order_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Apply a generated order's final status through WooCommerce's normal
	 * status-transition pipeline.
	 *
	 * Calling update_status() (rather than set_status()/save()) ensures other
	 * plugins, emails, stock adjustments, and WooCommerce reporting see the
	 * same hooks they would for a real order status change.
	 *
	 * @param WC_Order $order  Generated order.
	 * @param string   $status Unprefixed target status slug.
	 *
	 * @return void
	 */
	private function apply_order_status( WC_Order $order, string $status ): void {
		$order->update_status(
			$status,
			__( 'Order status set by SILC Fake Order Generator.', 'silc-fake-order-generator' ),
			false
		);
	}

	/**
	 * Synchronize an order into the WooCommerce Analytics lookup tables
	 * (wc_order_stats and friends).
	 *
	 * WooCommerce normally does this asynchronously via Action Scheduler, but
	 * generated orders can miss that pipeline (e.g. when the target status
	 * equals the initial status and update_status() short-circuits without
	 * firing woocommerce_update_order). Syncing here guarantees the order is
	 * reflected in Analytics immediately.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function sync_order_analytics( int $order_id ): void {
		$scheduler = '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler';

		// WooCommerce >= 10.5 exposes a public single-order import entry point
		// that updates every lookup table and invalidates the reports cache.
		if ( class_exists( $scheduler ) && method_exists( $scheduler, 'import' ) ) {
			$scheduler::import( $order_id );
			return;
		}

		// Fallback for older WooCommerce Admin: sync the order stats directly.
		$stats_store = '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore';
		if ( class_exists( $stats_store ) && method_exists( $stats_store, 'sync_order' ) ) {
			$stats_store::sync_order( $order_id );
		}
	}

	/**
	 * Get all customer user IDs who can purchase (have billing data or are customers).
	 *
	 * @return int[]
	 */
	private function get_customer_ids(): array {
		$customers = get_users(
			array(
				'role__in' => array( 'customer', 'subscriber' ),
				'fields'   => 'ID',
				'number'   => 200,
			)
		);

		// If very few customers found, also include admins with billing data.
		if ( count( $customers ) < 5 ) {
			$extra = get_users(
				array(
					'role__in' => array( 'administrator', 'editor', 'author' ),
					'fields'   => 'ID',
					'number'   => 20,
				)
			);
			$customers = array_unique( array_merge( $customers, $extra ) );
		}

		return $customers;
	}

	/**
	 * Get published simple product IDs.
	 *
	 * @return int[]
	 */
	private function get_simple_product_ids(): array {
		$products = wc_get_products(
			array(
				'type'  => 'simple',
				'limit' => 200,
				'return' => 'ids',
				'status' => 'publish',
			)
		);
		return $products;
	}

	/**
	 * Get published variation IDs.
	 *
	 * @return int[]
	 */
	private function get_variation_ids(): array {
		// First get variable product IDs.
		$variable_ids = wc_get_products(
			array(
				'type'   => 'variable',
				'limit'  => 50,
				'return' => 'ids',
				'status' => 'publish',
			)
		);

		if ( empty( $variable_ids ) ) {
			return array();
		}

		$all_variations = array();
		foreach ( $variable_ids as $variable_id ) {
			$product = wc_get_product( $variable_id );
			if ( ! $product ) {
				continue;
			}
			$variations = $product->get_children();
			foreach ( $variations as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation && 'publish' === $variation->get_status() ) {
					$all_variations[] = $variation_id;
				}
			}
		}

		return $all_variations;
	}

	/**
	 * Get a random product (simple or variation).
	 *
	 * @param array $simple_product_ids Simple product IDs.
	 * @param array $variation_ids      Variation IDs.
	 * @param array $exclude_ids        Product IDs to exclude (already used in this order).
	 *
	 * @return WC_Product|null
	 */
	private function get_random_product( array $simple_product_ids, array $variation_ids, array $exclude_ids = array() ): ?\WC_Product {
		$candidates = array();

		// Filter out excluded IDs.
		$available_simple = array_diff( $simple_product_ids, $exclude_ids );
		$available_vars   = array_diff( $variation_ids, $exclude_ids );

		foreach ( $available_simple as $id ) {
			$candidates[] = array( 'type' => 'simple', 'id' => $id );
		}
		foreach ( $available_vars as $id ) {
			$candidates[] = array( 'type' => 'variation', 'id' => $id );
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		$chosen  = $candidates[ array_rand( $candidates ) ];
		$product = wc_get_product( $chosen['id'] );

		return $product ? $product : null;
	}

	/**
	 * Generate a random date string between two dates.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 *
	 * @return string Date string compatible with strtotime.
	 */
	private function random_date( string $start_date, string $end_date ): string {
		// Interpret the user-supplied dates in the site's timezone so the
		// generated order dates stay within the selected local date range.
		$start    = wc_string_to_datetime( $start_date );
		$end      = wc_string_to_datetime( $end_date );
		$start_ts = $start->getTimestamp();
		$end_ts   = $end->getTimestamp();

		if ( $start_ts >= $end_ts ) {
			return $start_date;
		}

		$random_ts = wp_rand( $start_ts, $end_ts );

		// Format in the site's timezone: set_date_created() parses plain date
		// strings as site-local time, so gmdate() here would shift orders to a
		// different day on non-UTC sites.
		return wp_date( 'Y-m-d H:i:s', $random_ts );
	}

	/**
	 * Generate a random address line for fallback.
	 *
	 * @return string
	 */
	private function random_address_line(): string {
		return wp_rand( 100, 9999 ) . ' ' . $this->random_street_name();
	}

	/**
	 * Random street name.
	 *
	 * @return string
	 */
	private function random_street_name(): string {
		$names = array( 'Main St', 'Oak Ave', 'Elm St', '2nd St', 'Park Ave', 'Broadway', 'Maple Dr', 'Cedar Ln', 'Lake View', 'Sunset Blvd' );
		return $names[ array_rand( $names ) ];
	}

	/**
	 * Random city name.
	 *
	 * @return string
	 */
	private function random_city(): string {
		$cities = array( 'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Portland' );
		return $cities[ array_rand( $cities ) ];
	}

	/**
	 * Random postcode.
	 *
	 * @return string
	 */
	private function random_postcode(): string {
		return sprintf( '%05d', wp_rand( 10001, 99999 ) );
	}
}
