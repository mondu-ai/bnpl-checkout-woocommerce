<?php
/**
 * Webhooks Controller
 *
 * @package Mondu
 */

namespace Mondu\Mondu\Controllers;

use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Models\SignatureVerifier;
use Mondu\Mondu\Support\Helper;
use Mondu\Exceptions\MonduException;
use Mondu\Plugin;
use WC_Order;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Webhooks Controller
 */
class WebhooksController extends WP_REST_Controller {
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
		$this->namespace             = 'mondu/v1/webhooks';
		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/index',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle webhook
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$verifier = new SignatureVerifier();
		$params   = $request->get_json_params();
		$topic    = $params['topic'] ?? null;

		$body              = $request->get_body();
		$signature_payload = $request->get_header( 'X-MONDU-SIGNATURE' );
		$signature         = $verifier->create_hmac( $body );

		Helper::log(
			array(
				'webhook_topic' => $topic,
				'params'        => $params,
			)
		);

		try {
			if ( $signature !== $signature_payload ) {
				throw new MonduException( __( 'Signature mismatch.', 'mondu' ) );
			}

			switch ( $topic ) {
				case 'order/pending':
					$result = $this->handle_pending( $params );
					break;
				case 'order/authorized':
					$result = $this->handle_authorized( $params );
					break;
				case 'order/confirmed':
					$result = $this->handle_confirmed( $params );
					break;
				case 'order/declined':
					$result = $this->handle_declined( $params );
					break;
				case 'invoice/created':
					$result = $this->handle_invoice_created( $params );
					break;
				case 'invoice/payment':
					$result = $this->handle_invoice_payment( $params );
					break;
				case 'invoice/canceled':
					$result = $this->handle_invoice_canceled( $params );
					break;
				default:
					$result = $this->handle_not_found_topic( $params );
					break;
			}

			$res_body   = $result[0];
			$res_status = $result[1];
		} catch ( MonduException $e ) {
			$this->mondu_request_wrapper->log_plugin_event( $e, 'webhooks', array_merge( $params, array( 'signature' => $signature ) ) );
			$res_body   = array( 'message' => $e->getMessage() );
			$res_status = 400;
		} catch ( \Exception $e ) {
			$this->mondu_request_wrapper->log_plugin_event( $e, 'webhooks', $params );
			$res_body   = array( 'message' => __( 'Something happened on our end.', 'mondu' ) );
			$res_status = 200;
		}

		return new WP_REST_Response( $res_body, $res_status );
	}

	/**
	 * Handle pending
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 * @throws MonduException Exception.
	 */
	private function handle_pending( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on pending state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	/**
	 * Handle authorized
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 *
	 * @throws MonduException Exception.
	 */
	private function handle_authorized( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on authorized state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	/**
	 * Handle confirmed
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 * @throws MonduException Exception.
	 */
	private function handle_confirmed( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on confirmed state.', 'mondu' ) ) ), false );

		if ( in_array( $order->get_status(), array( 'pending', 'on-hold' ) ) === true ) {
			$order->update_status( 'wc-processing', __( 'Processing', 'woocommerce' ) );
		}

		return $this->return_success();
	}

	/**
	 * Handle declined
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 *
	 * @throws MonduException Exception.
	 */
	private function handle_declined( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on declined state.', 'mondu' ) ) ), false );

		if ( $order->get_status() === 'on-hold' ) {
			$order->update_status( 'wc-failed', __( 'Failed', 'woocommerce' ) );
		}

		return $this->return_success();
	}

	/**
	 * Handle invoice created
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 *
	 * @throws MonduException Exception.
	 */
	private function handle_invoice_created( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu invoice is on created state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	/**
	 * Handle invoice payment
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 *
	 * @throws MonduException Exception.
	 */
	private function handle_invoice_payment( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu invoice is on complete state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	/**
	 * Handle invoice canceled
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 *
	 * @throws MonduException Exception.
	 */
	private function handle_invoice_canceled( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = Helper::get_order_from_order_number_or_uuid( $woocommerce_order_number, $mondu_order_id );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu invoice is on canceled state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	/**
	 * Handle not found topic
	 *
	 * @param array $params Params.
	 *
	 * @return array
	 */
	private function handle_not_found_topic( $params ) {
		Helper::log(
			array(
				'not_found_topic' => $params,
			)
		);

		return $this->return_success();
	}

	/**
	 * Return success
	 *
	 * @return array
	 */
	private function return_success() {
		return array( array( 'message' => 'Ok' ), 200 );
	}

	/**
	 * Return not found
	 *
	 * @return array
	 */
	private function return_not_found() {
		return array( array( 'message' => __( 'Not Found', 'mondu' ) ), 404 );
	}
}
