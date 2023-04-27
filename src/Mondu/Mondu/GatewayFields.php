<?php

namespace Mondu\Mondu;

class GatewayFields {

  /**
   * Returns the fields.
   */
  public static function fields() {
    $fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable this payment method', 'mondu'),
        'default' => 'no',
      ),
    );

    return $fields;
  }
}
