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

  /** @var Api */
  private $api;

  public function __construct() {
    $this->api = new Api();
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_order() {
    $payment_method = WC()->session->get('chosen_payment_method');
    if (!in_array($payment_method, Plugin::PAYMENT_METHODS)) {
      return;
    }
    $payment_method = array_search($payment_method, Plugin::PAYMENT_METHODS);

    $order_data = OrderData::create_order_data($payment_method);
    $response = $this->wrap_with_mondu_log_event('create_order', array($order_data));
    $order = $response['order'];
    WC()->session->set('mondu_order_id', $order['uuid']);
    return $order;
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_order_pay_page($data) {
    $payment_method = $data['payment_method'];
    if (!in_array($payment_method, Plugin::PAYMENT_METHODS)) {
      return;
    }
    $payment_method = array_search($payment_method, Plugin::PAYMENT_METHODS);

    $order = wc_get_order($data['order_id']);
    $order_data = OrderData::raw_order_data_from_wc_order($order);

    $response = $this->wrap_with_mondu_log_event('create_order', array($order_data));
    $order = $response['order'];
    WC()->session->set('mondu_order_id', $order['uuid']);
    update_post_meta($data['order_id'], Plugin::ORDER_ID_KEY, $order['uuid']);
    return $order;
  }

  /**
   * @param $order_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_order($order_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $response = $this->wrap_with_mondu_log_event('get_order', array($mondu_order_id));
    return @$response['order'];
  }

  /**
   * @param $order_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function update_external_info($order_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $params = ['external_reference_id' => $order->get_order_number()];
    $response = $this->wrap_with_mondu_log_event('update_external_info', array($mondu_order_id, $params));
    return $response['order'];
  }

  /**
   * @param $order_id
   * @param $data_to_update
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function adjust_order($order_id, $data_to_update) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $response = $this->wrap_with_mondu_log_event('adjust_order', array($mondu_order_id, $data_to_update));
    return $response['order'];
  }

  /**
   * @param $order_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function cancel_order($order_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $response = $this->wrap_with_mondu_log_event('cancel_order', array($mondu_order_id));
    return $response['order'];
  }

  /**
   * @param $order_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function ship_order($order_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $invoice_data = OrderData::invoice_data_from_wc_order($order);
    $response = $this->wrap_with_mondu_log_event('ship_order', array($mondu_order_id, $invoice_data));
    $invoice = $response['invoice'];
    add_post_meta($order_id, Plugin::INVOICE_ID_KEY, $invoice['uuid']);
    return $invoice;
  }

  /**
   * @param $order_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_invoices($order_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $response = $this->wrap_with_mondu_log_event('get_invoices', array($mondu_order_id));
    return $response['invoices'];
  }

  /**
   * @param $invoice_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_invoice($order_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $mondu_invoice_id = get_post_meta($order_id, Plugin::INVOICE_ID_KEY, true);
    $response = $this->wrap_with_mondu_log_event('get_invoice', array($mondu_order_id, $mondu_invoice_id));
    return $response['invoice'];
  }

  /**
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_merchant_payment_methods(): array {
    $merchant_payment_methods = get_transient('mondu_merchant_payment_methods');
    if ($merchant_payment_methods === false) {
      try {
        $response = $this->wrap_with_mondu_log_event('get_payment_methods');

        # return only an array with the identifier (invoice, direct_debit, installment)
        $merchant_payment_methods = array_map(function($payment_method) {
          return $payment_method['identifier'];
        }, $response['payment_methods']);
        set_transient('mondu_merchant_payment_methods', $merchant_payment_methods, 1 * 60);
        return $merchant_payment_methods;
      } catch (\Exception $e) {
        return array_keys(Plugin::PAYMENT_METHODS);
      }
    }
    return $merchant_payment_methods;
  }

  /**
   * @param int $order_id
   *
   * @throws MonduException
   * @throws ResponseException
   * @throws WC_Data_Exception
   */
  public function process_payment($order_id) {
    $order = new WC_Order($order_id);

    if(!$this->confirm_order_status($order_id)) {
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

  public function confirm_order_status($order_id) {
    $order = $this->get_order($order_id);

    if(!$order) return false;

    $confirm_order_status = apply_filters('mondu_confirm_order_statuses', ['confirmed', 'pending']);
    if(!in_array($order['state'], $confirm_order_status)) return false;

    return true;
  }

  /**
   * @param $order
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function update_order_if_changed_some_fields($order) {
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    # This method should not be called before ending the payment process
    if (isset(WC()->session) && WC()->session->get('mondu_order_id')) {
      return;
    }

    if (array_intersect(array('total', 'discount_total', 'discount_tax', 'cart_tax', 'total_tax', 'shipping_tax', 'shipping_total'), array_keys($order->get_changes()))) {
      $data_to_update = OrderData::order_data_from_wc_order($order);
      $this->adjust_order($order->get_id(), $data_to_update);
    }
  }

  /**
   * @param $order_id
   * @param $from_status
   * @param $to_status
   *
   * @throws MonduException
   */
  public function order_status_changed($order_id, $from_status, $to_status) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    Helper::log(array('order_id' => $order_id, 'from_status' => $from_status, 'to_status' => $to_status));

    if ($to_status === 'cancelled') {
      $this->cancel_order($order_id);
    }
    if ($to_status === 'completed') {
      $this->ship_order($order_id);
    }
  }

  /**
   * @param $order_id
   * @param $refund_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function order_refunded($order_id, $refund_id) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    $refund = new WC_Order_Refund($refund_id);
    $mondu_invoice_id = get_post_meta($order->get_id(), PLUGIN::INVOICE_ID_KEY, true);

    if (!$mondu_invoice_id) {
      throw new ResponseException(__('Mondu: Can not create a credit note without an invoice', 'mondu'));
    }

    $refund_total = $refund->get_total();
    $credit_note = [
      'gross_amount_cents' => abs(round((float) $refund_total * 100)),
      'external_reference_id' => (string) $refund->get_id()
    ];

    $this->wrap_with_mondu_log_event('create_credit_note', array($mondu_invoice_id, $credit_note));
  }

  /**
   * @param $mondu_order_id
   * @param $mondu_invoice_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function cancel_invoice($mondu_order_id, $mondu_invoice_id) {
    $this->wrap_with_mondu_log_event('cancel_invoice', array($mondu_order_id, $mondu_invoice_id));
  }

  /**
   * @param $topic
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function register_webhook(string $topic) {
    $response = $this->wrap_with_mondu_log_event('register_webhook', array($topic));

    return $response['webhooks'];
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_webhooks() {
    $response = $this->wrap_with_mondu_log_event('get_webhooks');

    return $response['webhooks'];
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function webhook_secret() {
    $response = $this->wrap_with_mondu_log_event('webhook_secret');

    return $response['webhook_secret'];
  }

  /**
   * @param $exception
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function log_plugin_event(\Exception $exception, string $event, $body = null) {
    global $wp_version;
    $params = [
      'plugin' => 'woocommerce',
      'version' => MONDU_PLUGIN_VERSION,
      'language_version' => 'PHP ' . phpversion(),
      'shop_version' => $wp_version,
      'origin_event' => strtoupper($event),
      'response_body' => $body,
      'response_status' => (string) $exception->getCode(),
      'error_message' => $exception->getMessage(),
      'error_trace' => $exception->getTraceAsString()
    ];
    $this->api->log_plugin_event($params);
  }

  private function wrap_with_mondu_log_event(string $action, array $params = []) {
    try {
      return call_user_func_array(array($this->api, $action), $params);
    } catch (ResponseException $e) {
      $this->log_plugin_event($e, $action, $e->getBody());
      throw $e;
    } catch (\Exception $e) {
      $this->log_plugin_event($e, $action);
      throw $e;
    }
  }
}
