<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\Support\Helper;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Order;
use WC_Order_Refund;

class MonduRequestWrapper {
	private $api;

	public function __construct() {
		$this->api = new Api();
	}

	/**
	 * Create Order
	 *
	 * @param $lang
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function create_order( $lang = null ) {
		$payment_method = WC()->session->get('chosen_payment_method');
		if ( !in_array($payment_method, Plugin::PAYMENT_METHODS, true) ) {
			return;
		}
		$payment_method = array_search($payment_method, Plugin::PAYMENT_METHODS, true);

		$order_data = OrderData::create_order_data($payment_method, $lang);
		$response   = $this->wrap_with_mondu_log_event('create_order', [ $order_data ]);
		$order      = $response['order'];
		WC()->session->set('mondu_order_id', $order['uuid']);
		return $order;
	}

	/**
	 * Create Order Pay Page
	 *
	 * @param $data
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function create_order_pay_page( $data ) {
		$payment_method = $data['payment_method'];
		if ( !in_array($payment_method, Plugin::PAYMENT_METHODS, true) ) {
			return;
		}
		$payment_method = array_search($payment_method, Plugin::PAYMENT_METHODS, true);

		$order      = wc_get_order($data['order_id']);
		$order_data = OrderData::raw_order_data_from_wc_order($order);

		$response = $this->wrap_with_mondu_log_event('create_order', array( $order_data ));
		$order    = $response['order'];
		WC()->session->set('mondu_order_id', $order['uuid']);
		update_post_meta($data['order_id'], Plugin::ORDER_ID_KEY, $order['uuid']);
		return $order;
	}

	/**
	 * Get Mondu Order
	 *
	 * @param $order_id
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function get_order( $order_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$response       = $this->wrap_with_mondu_log_event('get_order', array( $mondu_order_id ));
		return isset($response['order']) ? $response['order'] : null;
	}

	/**
	 * Update external info
	 *
	 * @param $order_id
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function update_external_info( $order_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$params         = [ 'external_reference_id' => $order->get_order_number() ];
		$response       = $this->wrap_with_mondu_log_event('update_external_info', array( $mondu_order_id, $params ));
		return $response['order'];
	}

	/**
	 * Adjust Order
	 *
	 * @param $order_id
	 * @param $data_to_update
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function adjust_order( $order_id, $data_to_update ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$response       = $this->wrap_with_mondu_log_event('adjust_order', array( $mondu_order_id, $data_to_update ));
		return $response['order'];
	}

	/**
	 * Cancel Order
	 *
	 * @param $order_id
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function cancel_order( $order_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$response       = $this->wrap_with_mondu_log_event('cancel_order', array( $mondu_order_id ));
		return $response['order'];
	}

	/**
	 * Ship Order
	 *
	 * @param $order_id
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function ship_order( $order_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$invoice_data   = OrderData::invoice_data_from_wc_order($order);
		$response       = $this->wrap_with_mondu_log_event('ship_order', array( $mondu_order_id, $invoice_data ));
		$invoice        = $response['invoice'];
		add_post_meta($order_id, Plugin::INVOICE_ID_KEY, $invoice['uuid']);
		return $invoice;
	}

	/**
	 * Get invoices
	 *
	 * @param $order_id
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function get_invoices( $order_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$response       = $this->wrap_with_mondu_log_event('get_invoices', array( $mondu_order_id ));
		return $response['invoices'];
	}

	/**
	 * Get invoice
	 *
	 * @param $order_id
	 * @return mixed|void
	 * @throws ResponseException
	 */
	public function get_invoice( $order_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_order_id   = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
		$mondu_invoice_id = get_post_meta($order_id, Plugin::INVOICE_ID_KEY, true);
		$response         = $this->wrap_with_mondu_log_event('get_invoice', array( $mondu_order_id, $mondu_invoice_id ));
		return $response['invoice'];
	}


	/**
	 * Get Merchant Payment Methods
	 *
	 * @return array
	 */
	public function get_merchant_payment_methods() {
		$merchant_payment_methods = get_transient('mondu_merchant_payment_methods');
		if ( false === $merchant_payment_methods ) {
			try {
				$response = $this->wrap_with_mondu_log_event('get_payment_methods');

				if ( !$response ) {
					return [];
				}

				# return only an array with the identifier (invoice, direct_debit, installment)
				$merchant_payment_methods = array_map(function( $payment_method ) {
					return $payment_method['identifier'];
				}, $response['payment_methods']);
				set_transient('mondu_merchant_payment_methods', $merchant_payment_methods, 1 * 60);
				return $merchant_payment_methods;
			} catch ( \Exception $e ) {
				$merchant_payment_methods = array_keys(Plugin::PAYMENT_METHODS);
				set_transient('mondu_merchant_payment_methods', $merchant_payment_methods, 10 * 60);
				return $merchant_payment_methods;
			}
		}
		return $merchant_payment_methods;
	}


	/**
	 * Process Payment
	 *
	 * @param $order_id
	 * @return void|WC_Order
	 * @throws ResponseException
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order($order_id);

		if ( !$this->confirm_order_status($order_id) ) {
			WC()->session->set('mondu_order_id', null);
			return;
		}
		// Update Mondu order's external reference id
		$this->update_external_info($order_id);

		$order->update_status('wc-processing', __('Processing', 'woocommerce'));

		WC()->cart->empty_cart();
		/*
		 * We remove the orders id here,
		 * otherwise we might try to use the same session id for the next order
		 */
		WC()->session->set('mondu_order_id', null);

		return $order;
	}

	public function confirm_order_status( $order_id ) {
		$order = $this->get_order($order_id);

		if ( !$order ) {
			return false;
		}

		/**
		 * Confirmed order statuses
		 *
		 * @since 1.3.2
		 */
		$confirm_order_status = apply_filters('mondu_confirm_order_statuses', [ 'confirmed', 'pending' ]);
		if ( !in_array($order['state'], $confirm_order_status, true) ) {
			return false;
		}

		return true;
	}

	/**
	 * Update Order If fields were changed
	 *
	 * @param $order
	 * @return void
	 * @throws ResponseException
	 */
	public function update_order_if_changed_some_fields( $order ) {
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		# This method should not be called before ending the payment process
		if ( isset(WC()->session) && WC()->session->get('mondu_order_id') ) {
			return;
		}

		if ( array_intersect(array( 'total', 'discount_total', 'discount_tax', 'cart_tax', 'total_tax', 'shipping_tax', 'shipping_total' ), array_keys($order->get_changes())) ) {
			$data_to_update = OrderData::order_data_from_wc_order($order);
			$this->adjust_order($order->get_id(), $data_to_update);
		}
	}

	/**
	 * Handle Order status change
	 *
	 * @param $order_id
	 * @param $from_status
	 * @param $to_status
	 * @return void
	 * @throws ResponseException
	 */
	public function order_status_changed( $order_id, $from_status, $to_status ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		Helper::log(array(
			'order_id'    => $order_id,
			'from_status' => $from_status,
			'to_status'   => $to_status,
		));

		if ( 'cancelled' === $to_status ) {
			$this->cancel_order($order_id);
		}
		if ( 'completed' === $to_status ) {
			$this->ship_order($order_id);
		}
	}

	/**
	 * Handle Order Refunded
	 *
	 * @param $order_id
	 * @param $refund_id
	 * @return void
	 * @throws ResponseException
	 */
	public function order_refunded( $order_id, $refund_id ) {
		$order = new WC_Order($order_id);
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		$mondu_invoice_id = get_post_meta($order->get_id(), Plugin::INVOICE_ID_KEY, true);
		if ( !$mondu_invoice_id ) {
			return;
		}

		$refund      = new WC_Order_Refund($refund_id);
		$credit_note = OrderData::create_credit_note($refund);

		$this->wrap_with_mondu_log_event('create_credit_note', array( $mondu_invoice_id, $credit_note ));
	}


	/**
	 * Cancel Invoice
	 *
	 * @param $mondu_order_id
	 * @param $mondu_invoice_id
	 * @return void
	 * @throws ResponseException
	 */
	public function cancel_invoice( $mondu_order_id, $mondu_invoice_id ) {
		$this->wrap_with_mondu_log_event('cancel_invoice', array( $mondu_order_id, $mondu_invoice_id ));
	}

	/**
	 * Register Webhook
	 *
	 * @param string $topic
	 * @return mixed
	 * @throws ResponseException
	 */
	public function register_webhook( $topic ) {
		$response = $this->wrap_with_mondu_log_event('register_webhook', array( $topic ));

		return isset($response['webhooks']) ? $response['webhooks'] : null;
	}

	/**
	 * Get Webhooks
	 *
	 * @return mixed
	 * @throws ResponseException
	 */
	public function get_webhooks() {
		$response = $this->wrap_with_mondu_log_event('get_webhooks');

		return $response['webhooks'];
	}

	/**
	 * Webhook Secret
	 *
	 * @return mixed
	 * @throws ResponseException
	 */
	public function webhook_secret() {
		$response = $this->wrap_with_mondu_log_event('webhook_secret');

		return $response['webhook_secret'];
	}

	/**
	 * Log Plugin event
	 *
	 * @param \Exception $exception
	 * @param string $event
	 * @param $body
	 * @return void
	 */
	public function log_plugin_event( \Exception $exception, $event, $body = null ) {
		global $wp_version;
		$params = [
			'plugin'           => 'woocommerce',
			'version'          => MONDU_PLUGIN_VERSION,
			'language_version' => 'PHP ' . phpversion(),
			'shop_version'     => $wp_version,
			'origin_event'     => strtoupper($event),
			'response_body'    => $body,
			'response_status'  => (string) $exception->getCode(),
			'error_message'    => $exception->getMessage(),
			'error_trace'      => $exception->getTraceAsString(),
		];
		$this->api->log_plugin_event($params);
	}

	private function wrap_with_mondu_log_event( $action, array $params = [] ) {
		try {
			return call_user_func_array(array( $this->api, $action ), $params);
		} catch ( ResponseException $e ) {
			$this->log_plugin_event($e, $action, $e->getBody());
			throw $e;
		} catch ( \Exception $e ) {
			$this->log_plugin_event($e, $action);
			throw $e;
		}
	}
}
