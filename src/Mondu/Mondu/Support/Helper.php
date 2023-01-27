<?php

namespace Mondu\Mondu\Support;

use WC_Logger_Interface;

class Helper {
  /**
   * @param $value
   *
   * @return bool
   */
  public static function not_null_or_empty($value) {
    return $value !== NULL && $value !== '';
  }

  /**
   * @param $array
   * @param $keys
   *
   * @return array[]
   */
  public static function remove_keys($array, $keys) {
    return array_filter(
      $array,
      fn ($key) => !in_array($key, $keys),
      ARRAY_FILTER_USE_KEY,
   );
  }

  /**
   * @param $order
   *
   * @return string
   */
  public static function create_invoice_url($order) {
    if (has_action('generate_wpo_wcpdf')) {
      $invoice_url = add_query_arg(
        '_wpnonce',
        wp_create_nonce('generate_wpo_wcpdf'),
        add_query_arg(
          array(
            'action' => 'generate_wpo_wcpdf',
            'document_type' => 'invoice',
            'order_ids' => $order->get_id(),
            'my-account' => true,
          ),
          admin_url('admin-ajax.php')
        )
      );
    } else {
      $invoice_url = $order->get_view_order_url();
    }

    return apply_filters('mondu_invoice_url', $invoice_url);
  }

  public static function log(array $message, string $level = 'DEBUG') {
    $logger = wc_get_logger();
    $logger->log($level, wc_print_r($message, true), array('source' => 'mondu'));
  }
}
