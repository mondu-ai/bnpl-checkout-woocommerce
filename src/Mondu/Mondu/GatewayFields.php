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
      'instructions' => array(
        'title' => __('Instructions', 'woocommerce'),
        'type' => 'textarea',
        'description' => __('Instructions that will be added to the thank you page and emails.', 'mondu'),
        'default' => '',
        'desc_tip' => true,
      ),
    );

    return $fields;
  }
}
