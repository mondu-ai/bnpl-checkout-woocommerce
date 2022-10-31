<?php

namespace Mondu\Admin;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Plugin;
use WC_Order;

defined('ABSPATH') or die('Direct access not allowed');

class Order {
  /** @var MonduRequestWrapper */
  private $mondu_request_wrapper;

  public function init() {
    add_action('add_meta_boxes', [$this, 'add_payment_info_box']);
    add_action('save_post', [$this, 'save_metabox_callback']);

    add_action('wp_ajax_cancel_invoice', [$this, 'cancel_invoice']);
    add_action('wp_ajax_create_invoice', [$this, 'create_invoice']);

    $this->mondu_request_wrapper = new MonduRequestWrapper();
  }

  public function add_payment_info_box() {
    $order = $this->check_and_get_wc_order();

    if ($order === null) {
      return;
    }

    add_meta_box('mondu_payment_info',
      __('Mondu Order Information', 'mondu'),
      function () use ($order) {
        echo $this->render_meta_box_content($order);
      },
      'shop_order',
      'normal'
   );
  }

  /**
   * Save the meta when the post is saved.
   *
   * @param int $post_id The ID of the post being saved.
   */
  public function save_metabox_callback($post_id) {
    /*
     * We need to verify this came from the our screen and with proper authorization,
     * because save_post can be triggered at other times.
     */

    // Check if our nonce is set.
    if (!isset($_POST['mondu_cancel_invoice_nonce'])) {
      return $post_id;
    }

    $nonce = $_POST['mondu_cancel_invoice_nonce'];

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($nonce, 'mondu_cancel_invoice')) {
      return $post_id;
    }

    /*
     * If this is an autosave, our form has not been submitted,
     * so we don't want to do anything.
     */
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return $post_id;
    }

    // Check the user's permissions.
    if ('page' == $_POST['post_type']) {
      if (!current_user_can('edit_page', $post_id)) {
        return $post_id;
      }
    } else {
      if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
      }
    }

    /* OK, it's safe for us to save the data now. */

    $this->cancel_invoice();
  }

  public function render_meta_box_content($order) {
    wp_nonce_field('mondu_cancel_invoice', 'mondu_cancel_invoice_nonce');

    $payment_info = new PaymentInfo($order->get_id());
    echo $payment_info->get_mondu_section_html();
  }

  public function cancel_invoice() {
    $order = $this->check_and_get_wc_order();

    if ($order === null) {
      return;
    }

    $invoice_id = $_POST['mondu_invoice_id'] ?? '';
    $order_id = $_POST['mondu_order_id'] ?? '';

    try {
      $this->mondu_request_wrapper->cancel_invoice($order_id, $invoice_id);
      update_post_meta($order->get_id(), Plugin::INVOICE_CANCELED_KEY, true);
    } catch (ResponseException|MonduException $e) {
      wp_send_json([
        'error' => true,
        'message' => $e->getMessage()
      ]);
    }
  }

  public function create_invoice() {
    $order = $this->check_and_get_wc_order();

    if ($order === null) {
      return;
    }

    try {
      $this->mondu_request_wrapper->ship_order($order->get_id());
    } catch (ResponseException|MonduException $e) {
      wp_send_json([
        'error' => true,
        'message' => $e->getMessage()
      ]);
    }
  }

  private function check_and_get_wc_order() {
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
