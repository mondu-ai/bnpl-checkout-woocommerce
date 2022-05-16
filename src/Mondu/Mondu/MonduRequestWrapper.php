<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Order;
use WC_Order_Refund;

class MonduRequestWrapper {

  private $logger;

  /** @var Api */
  private $api;

  public function __construct() {
    $this->api = new Api();
    $this->logger = wc_get_logger();
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_order() {
    $payment_method = WC()->session->get('chosen_payment_method');
    if ($payment_method !== 'mondu') {
      return;
    }

    $order_data = OrderData::create_order_data();
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
    if ($order->get_payment_method() !== 'mondu') {
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
    if ($order->get_payment_method() !== 'mondu') {
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
    if ($order->get_payment_method() !== 'mondu') {
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
    if ($order->get_payment_method() !== 'mondu') {
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
    if ($order->get_payment_method() !== 'mondu') {
      return;
    }

    $mondu_order_id = get_post_meta($order_id, Plugin::ORDER_ID_KEY, true);
    $invoice_data = OrderData::invoice_data_from_wc_order($order);
    $response = $this->api->ship_order($mondu_order_id, $invoice_data);
    $invoice = $response['invoice'];
    update_post_meta($order_id, Plugin::INVOICE_ID_KEY, $invoice['uuid']);
    return $invoice;
  }
}
