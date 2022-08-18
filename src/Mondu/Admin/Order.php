<?php

namespace Mondu\Admin;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Mondu\Api;
use Mondu\Plugin;
use WC_Order;

defined('ABSPATH') or die('Direct access not allowed');

class Order {

  /** @var Api */
  private $api;

  public function init() {
    add_action('add_meta_boxes', [$this, 'add_payment_info_box']);
    add_action('admin_footer', [$this, 'invoice_cancel_button_js']);

    add_action('wp_ajax_cancel_invoice', [$this, 'cancel_invoice']);
    add_action('wp_ajax_create_invoice', [$this, 'create_invoice']);

    $this->api = new Api();
  }

  public function cancel_invoice() {
    $invoiceId = $_POST['invoice_id'] ?? '';
    $orderId = $_POST['order_id'] ?? '';

    try {
      $this->api->cancel_invoice($orderId, $invoiceId);
      update_post_meta($orderId, Plugin::INVOICE_CANCELED_KEY, true);
    } catch (ResponseException|MonduException $e) {
      wp_send_json([
        'error' => true,
        'message' => $e->getMessage()
      ]);
    }
  }

  public function create_invoice() {
    $orderId = $_POST['order_id'] ?? '';
    $requestWrapper = new MonduRequestWrapper();

    try {
        $requestWrapper->ship_order($orderId);
    } catch (ResponseException|MonduException $e) {
        wp_send_json([
            'error' => true,
            'message' => $e->getMessage()
        ]);
    }
  }

  public function invoice_cancel_button_js() {
    require_once(MONDU_VIEW_PATH . '/admin/js/adminjs.php');
  }

  public function add_payment_info_box() {
    $order = $this->check_and_get_mondu_order();

    if ($order === null) {
      return;
    }

    add_meta_box('mondu_payment_info',
      __('Mondu Order Information', 'mondu'),
      static function () use ($order) {
        $payment_info = new PaymentInfo($order->get_id());
        echo $payment_info->get_mondu_section_html();
      },
      'shop_order',
      'normal'
   );
  }

  private function check_and_get_mondu_order() {
    global $post;

    if (!$post instanceof \WP_Post) {
      return null;
    }

    if ($post->post_type !== 'shop_order') {
      return null;
    }

    $order = new WC_Order($post->ID);

    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    return $order;
  }
}
