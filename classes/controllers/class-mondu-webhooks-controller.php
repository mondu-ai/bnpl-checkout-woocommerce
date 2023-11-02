<?php

class WebhooksController extends WP_REST_Controller {
	public function __construct() {
		$amespace = 'mondu/v1/webhooks';

		register_rest_route(
			$amespace,
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

	public function index( WP_REST_Request $request ) {
		$verifier          = new SignatureVerifier();
		$params            = $request->get_json_params();
		$signature_payload = $request->get_header( 'X-MONDU-SIGNATURE' );
		$signature         = $verifier->create_hmac( $params );
		$topic             = isset( $params['topic'] ) ? $params['topic'] : null;

		Mondu_WC()->log(
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
			Mondu_WC()->mondu_request_wrapper->log_plugin_event( $e, 'webhooks', array_merge( $params, array( 'signature' => $signature ) ) );
			$res_body   = array( 'message' => $e->getMessage() );
			$res_status = 400;
		} catch ( Exception $e ) {
			Mondu_WC()->mondu_request_wrapper->log_plugin_event( $e, 'webhooks', $params );
			$res_body   = array( 'message' => __( 'Something happened on our end.', 'mondu' ) );
			$res_status = 200;
		}

		return new WP_REST_Response( $res_body, $res_status );
	}

	private function handle_pending( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on pending state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	private function handle_authorized( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on authorized state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	private function handle_confirmed( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu order is on confirmed state.', 'mondu' ) ) ), false );

		if ( in_array( $order->get_status(), array( 'pending', 'on-hold' ) ) ) {
			$order->update_status( 'wc-processing', __( 'Processing', 'woocommerce' ) );
		}

		return $this->return_success();
	}

	private function handle_declined( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];
		$mondu_order_id           = $params['order_uuid'];

		if ( ! $woocommerce_order_number || ! $mondu_order_id ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		return $this->return_success();
	}

	private function handle_invoice_created( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];

		if ( ! $woocommerce_order_number ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu invoice is on created state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	private function handle_invoice_payment( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];

		if ( ! $woocommerce_order_number ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu invoice is on complete state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	private function handle_invoice_canceled( $params ) {
		$woocommerce_order_number = $params['external_reference_id'];

		if ( ! $woocommerce_order_number ) {
			throw new MonduException( __( 'Required params missing.', 'mondu' ) );
		}

		$order = get_order_from_order_number( $woocommerce_order_number );

		if ( ! $order ) {
			return $this->return_not_found();
		}

		$order->add_order_note( esc_html( sprintf( __( 'Mondu invoice is on canceled state.', 'mondu' ) ) ), false );

		return $this->return_success();
	}

	private function handle_not_found_topic( $params ) {
		Mondu_WC()->log(
			array(
				'not_found_topic' => $params,
			)
		);

		return $this->return_success();
	}

	private function return_success() {
		return array( array( 'message' => 'Ok' ), 200 );
	}

	private function return_not_found() {
		return array( array( 'message' => __( 'Not Found', 'mondu' ) ), 404 );
	}
}
