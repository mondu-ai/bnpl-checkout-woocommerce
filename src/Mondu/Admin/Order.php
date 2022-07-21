<?php

namespace Mondu\Admin;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\Api;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Mondu\Presenters\InvoiceInfo;
use WC_Order;

defined('ABSPATH') or die('Direct access not allowed');

class Order {

  public function init() {
    add_action('add_meta_boxes', [$this, 'add_payment_info_box']);
    add_action('admin_footer', [$this, 'invoice_cancel_button_js']);

    add_action('wp_ajax_cancel_invoice', [$this, 'cancel_invoice']);
//    add_action('add_meta_boxes', [$this, 'add_invoice_info_box']);

    // if (!class_exists(\WC_GZDP_Invoice::class)) {
    //   add_action('save_post', [$this, 'save_invoice_id']);
    // }
  }
  public function cancel_invoice() {
    $invoiceId = $_POST['invoice_id'] ?? '';
    $orderId = $_POST['order_id'] ?? '';


    $api = new Api();
    try {
      $api->cancel_invoice($orderId, $invoiceId);
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

  public function add_invoice_info_box() {
    $order = $this->check_and_get_mondu_order();

    if ($order === null) {
      return;
    }

    add_meta_box('mondu_invoice_info',
      __('Invoice info', 'mondu'),
      static function () use ($order) {
        $payment_info = new InvoiceInfo($order->get_id());
        echo $payment_info->get_mondu_invoice_html();
      },
      'shop_order',
      'normal'
   );
  }

  // public function save_invoice_id($post_id) {
  //   if (array_key_exists('mondu_invoice_id', $_POST)) {
  //     update_post_meta($post_id, '_mondu_invoice_id', esc_attr($_POST['mondu_invoice_id']));
  //   }
  // }

  private function check_and_get_mondu_order() {
    global $post;

    if (!$post instanceof \WP_Post) {
      return null;
    }

    if ($post->post_type !== 'shop_order') {
      return null;
    }

    $order = new WC_Order($post->ID);

    if ($order->get_payment_method() !== 'mondu') {
      return null;
    }

    return $order;
  }
}
