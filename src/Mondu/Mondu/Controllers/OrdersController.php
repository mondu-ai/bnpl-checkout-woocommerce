<?php

namespace Mondu\Mondu\Controllers;

use Mondu\Mondu\Support\Helper;
use Mondu\Mondu\MonduRequestWrapper;
use WP_REST_Controller;
use WP_REST_Request;

class OrdersController extends WP_REST_Controller {
	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	public function __construct() {
		$this->namespace             = 'mondu/v1/orders';
		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	// Register our routes
	public function register_routes() {
		register_rest_route($this->namespace, '/confirm', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'confirm' ],
				'permission_callback' => '__return_true',
			],
		]);
	}

	public function confirm( WP_REST_Request $request ) {
		$params         = $request->get_params();
		$order_number   = $params['external_reference_id'];
		$mondu_order_id = $params['order_uuid'];
		$return_url     = urldecode( $params['return_url'] );
		$order          = Helper::get_order_from_order_number( $order_number );

		try {
			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			if ( $order->get_status() == 'pending' ) {
				$order->update_status('wc-on-hold', __('On hold', 'woocommerce'));
			}

			$this->mondu_request_wrapper->confirm_order($order->get_id(), $mondu_order_id);
		} catch ( \Exception $e ) {
			Helper::log([
				'error_confirming_order' => $params,
			]);
		}

		wp_safe_redirect( $return_url );
		exit;
	}
}
