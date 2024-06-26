<?php
/**
 * Orders Controller
 *
 * @package Mondu
 */
namespace Mondu\Mondu\Controllers;

use Mondu\Mondu\Support\Helper;
use Mondu\Mondu\MonduRequestWrapper;
use WP_REST_Controller;
use WP_REST_Request;

/**
 * Orders Controller
 *
 * @package Mondu
 */
class OrdersController extends WP_REST_Controller {
	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace             = 'mondu/v1/orders';
		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/confirm', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'confirm' ],
				'permission_callback' => '__return_true',
			],
		] );
		register_rest_route( $this->namespace, '/decline', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'decline' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	/**
	 * Confirm order
	 *
	 * @param WP_REST_Request $request Request
	 */
	public function confirm( WP_REST_Request $request ) {
		$params         = $request->get_params();
		$order_number   = $params['external_reference_id'];
		$mondu_order_id = $params['order_uuid'];
		$return_url     = urldecode( $params['return_url'] );
		$order          = Helper::get_order_from_order_number_or_uuid( $order_number, $mondu_order_id );

		try {
			if ( !$order ) {
				throw new \Exception(__('Order not found'));
			}

			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			if ( in_array( $order->get_status(), [ 'pending', 'failed' ], true ) ) {
				$order->update_status( 'wc-on-hold', __('On hold', 'woocommerce' ) );

				$this->mondu_request_wrapper->confirm_order( $order->get_id(), $mondu_order_id );
			}
		} catch ( \Exception $e ) {
			Helper::log( [
				'error_confirming_order' => $params,
			] );
		}

		wp_safe_redirect( $return_url );
		exit;
	}

	/**
	 * Decline order
	 *
	 * @param WP_REST_Request $request Request
	 */
	public function decline( WP_REST_Request $request ) {
		$params       = $request->get_params();
		$order_number = $params['external_reference_id'];
		$return_url   = urldecode( $params['return_url'] );
		$order        = Helper::get_order_from_order_number( $order_number );

		$order->add_order_note( esc_html( sprintf( __( 'Order was declined by Mondu.', 'mondu' ) ) ), false );

		if ( $order->get_status() === 'pending' ) {
			$order->update_status('wc-failed', __('Failed', 'woocommerce'));
		}

		wp_safe_redirect( $return_url );
		exit;
	}
}
