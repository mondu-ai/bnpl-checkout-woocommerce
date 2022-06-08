<?php

namespace Mondu\Mondu\Support;

class Helper {
  /**
   * @param $value
   *
   * @return bool
   */
  public static function null_or_empty($value) {
    return $value === NULL || $value === '';
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
   * @param $order_id
   *
   * @return string
   */
  public static function create_invoice_url($order_id) {
    return add_query_arg(
      '_wpnonce',
      wp_create_nonce('generate_wpo_wcpdf'),
      add_query_arg(
        array(
          'action' => 'generate_wpo_wcpdf',
          'document_type' => 'invoice',
          'order_ids' => $order_id,
          'my-account' => true,
        ),
        admin_url('admin-ajax.php')
      )
    );
  }
}
