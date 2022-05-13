<?php

namespace Mondu\Admin;

use Mondu\Mondu\Presenters\PaymentInfo;
use WC_Order;

class Order {

  public function init() {
    add_action('add_meta_boxes', [$this, 'add_payment_info_box']);
  }

  public function add_payment_info_box() {
    $order = $this->check_and_get_mondu_order();

    if ($order === null) {
      return;
    }

    add_meta_box('mondu_payment_info',
      __('Payment info', 'mondu'),
      static function () use ($order) {
        $payment_info = new PaymentInfo($order->get_id());
        echo $payment_info->get_mondu_payment_html();
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

    if ($order->get_payment_method() !== 'mondu') {
      return null;
    }

    return $order;
  }
}
