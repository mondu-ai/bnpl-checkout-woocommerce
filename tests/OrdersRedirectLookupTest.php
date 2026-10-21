<?php
/**
 * Framework-free test for Helper::get_mondu_order_by_uuid(), the lookup behind the public
 * /mondu/v1/orders/confirm and /decline redirect endpoints.
 *
 * WooCommerce and WordPress are replaced by an in-memory order store, so the lookup can be
 * exercised without a shop, on both the HPOS and the legacy post storage path.
 * Run:  php tests/OrdersRedirectLookupTest.php   (exit code 0 = all passed)
 *
 * @package Mondu
 */

namespace Automattic\WooCommerce\Utilities {
	class OrderUtil {
		public static $hpos = true;

		public static function custom_orders_table_usage_is_enabled() {
			return self::$hpos;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WC_Order {
		private $id;
		private $payment_method;
		private $meta;

		public function __construct( $id, $payment_method, $meta ) {
			$this->id             = $id;
			$this->payment_method = $payment_method;
			$this->meta           = $meta;
		}

		public function get_id() {
			return $this->id;
		}

		public function get_payment_method() {
			return $this->payment_method;
		}

		public function get_meta( $key ) {
			return $this->meta[ $key ] ?? '';
		}
	}

	// Meta lookups compare like MySQL's default collation does: case-insensitively.
	function test_meta_matches( $order, $key, $value ) {
		return '' !== $order->get_meta( $key ) && 0 === strcasecmp( $order->get_meta( $key ), $value );
	}

	class WP_Query {
		public $posts = [];

		public function __construct( $args ) {
			foreach ( $GLOBALS['test_orders'] as $order ) {
				if ( test_meta_matches( $order, $args['meta_query'][0]['key'], $args['meta_query'][0]['value'] ) ) {
					$this->posts[] = $order->get_id();
				}
			}
		}
	}

	function wc_get_order( $id ) {
		return $GLOBALS['test_orders'][ $id ] ?? false;
	}

	function wc_get_orders( $args ) {
		return array_values( array_filter( $GLOBALS['test_orders'], function ( $order ) use ( $args ) {
			return test_meta_matches( $order, $args['meta_key'], $args['meta_value'] );
		} ) );
	}

	function wc_get_logger() {
		return new class() {
			public function log( ...$args ) {}
		};
	}

	function wc_print_r( $value, $return_value ) {
		return print_r( $value, $return_value );
	}

	function get_option( $name, $default = false ) {
		return $default;
	}

	function apply_filters( $hook, $value ) {
		return $value;
	}

	function __( $text ) {
		return $text;
	}

	require __DIR__ . '/../src/autoload.php';

	use Automattic\WooCommerce\Utilities\OrderUtil;
	use Mondu\Config\PaymentMethodsConfig;
	use Mondu\Mondu\Support\Helper;

	$mondu_gateway          = PaymentMethodsConfig::get_gateway_ids()[0];
	$GLOBALS['test_orders'] = [
		101 => new WC_Order( 101, $mondu_gateway, [ '_mondu_order_id' => 'uuid-101' ] ),
		102 => new WC_Order( 102, 'bacs', [ '_mondu_order_id' => 'uuid-102' ] ),
		103 => new WC_Order( 103, $mondu_gateway, [] ),
	];

	$failures = 0;
	$passed   = 0;

	$check = function ( $name, $expected, $order ) use ( &$failures, &$passed ) {
		$actual = $order ? $order->get_id() : false;
		if ( $expected === $actual ) {
			$passed++;
			printf( "ok    %s\n", $name );
		} else {
			$failures++;
			printf( "FAIL  %s expected=%s got=%s\n", $name, var_export( $expected, true ), var_export( $actual, true ) );
		}
	};

	foreach ( [ 'HPOS' => true, 'legacy posts' => false ] as $storage => $hpos ) {
		OrderUtil::$hpos = $hpos;

		$check( "{$storage}: Mondu order is found by its own uuid", 101, Helper::get_mondu_order_by_uuid( 'uuid-101' ) );
		$check( "{$storage}: missing uuid finds nothing", false, Helper::get_mondu_order_by_uuid( null ) );
		$check( "{$storage}: empty uuid finds nothing", false, Helper::get_mondu_order_by_uuid( '' ) );
		$check( "{$storage}: array uuid finds nothing", false, Helper::get_mondu_order_by_uuid( [ 'uuid-101' ] ) );
		$check( "{$storage}: order paid without Mondu is not returned", false, Helper::get_mondu_order_by_uuid( 'uuid-102' ) );
		// An order number alone used to be enough to reach an order; a uuid nobody holds must not be.
		$check( "{$storage}: order number in place of the uuid finds nothing", false, Helper::get_mondu_order_by_uuid( '103' ) );
		// The database finds the order, but the uuid is not exactly the stored one.
		$check( "{$storage}: uuid that only matches case-insensitively is rejected", false, Helper::get_mondu_order_by_uuid( 'UUID-101' ) );
	}

	echo "\n{$passed} passed, {$failures} failed\n";
	exit( 0 === $failures ? 0 : 1 );
}
