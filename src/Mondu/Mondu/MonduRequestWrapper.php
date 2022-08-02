<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
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
    $response = $this->api->create_order($order_data);
    $order = $response['order'];
    WC()->session->set('mondu_order_id', $order['uuid']);
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
    $response = $this->api->get_order($mondu_order_id);
    return $response['order'];
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
    $params = ['external_reference_id' => (string) $order_id];
    $response = $this->api->update_external_info($mondu_order_id, $params);
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
    $response = $this->api->adjust_order($mondu_order_id, $data_to_update);
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
    $response = $this->api->cancel_order($mondu_order_id);
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
    $response = $this->api->ship_order($mondu_order_id, $invoice_data);
    $invoice = $response['invoice'];
    update_post_meta($order_id, Plugin::INVOICE_ID_KEY, $invoice['uuid']);
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
    $response = $this->api->get_invoices($mondu_order_id);
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

    $response = $this->api->get_invoice($mondu_order_id, $mondu_invoice_id);

    return @$response['invoice'];
  }

  /**
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_merchant_payment_methods(): array {
    $response = $this->api->get_payment_methods();

    # return only an array with the identifier (invoice or direct_debit)
    $merchant_payment_methods = array_map(function($payment_method) {
      return $payment_method['identifier'];
    }, @$response['payment_methods']);

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

    // Update Mondu order's external reference id
    $this->update_external_info($order_id);

    $order->update_status('wc-processing', __('Processing', 'woocommerce'));

    WC()->cart->empty_cart();
    /*
     * We remove the orders id here,
     * otherwise we might try to use the same session id for the next order
     */
    WC()->session->set('mondu_order_id', null);
    WC()->session->set('woocommerce_order_id', null);

    return $order;
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
   * @throws ResponseException
   */
  public function order_status_changed($order_id, $from_status, $to_status) {
    $order = new WC_Order($order_id);
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

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

    if(!$mondu_invoice_id) {
      throw new ResponseException('Mondu: Can\'t create a credit note without an invoice');
    }

    $refund_total = $refund->get_total();
    $credit_note = [
      'gross_amount_cents' => abs(round ((float) $refund_total * 100)),
      'external_reference_id' => (string) $refund->get_id()
    ];

    $this->api->create_credit_note($mondu_invoice_id, $credit_note);
   }
}
