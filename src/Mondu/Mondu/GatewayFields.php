<?php

namespace Mondu\Mondu;

class GatewayFields {

  /**
   * Returns the fields.
   */
  public static function fields(string $default_title, string $default_description) {
    $fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable this payment method', 'mondu'),
        'default' => 'no',
      ),
      'title' => array(
        'title' => __('Title', 'woocommerce'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'mondu'),
        'default' => $default_title,
        'desc_tip' => true,
        ),
      'description' => array(
        'title' => __('Description', 'woocommerce'),
        'type' => 'textarea',
        'description' => __('Payment method description that the customer will see on your checkout.', 'mondu'),
        'default' => $default_description,
        'desc_tip' => true,
      ),
      'instructions' => array(
        'title' => __('Instructions', 'woocommerce'),
        'type' => 'textarea',
        'description' => __('Instructions that will be added to the thank you page and emails.', 'mondu'),
        'default' => $default_description,
        'desc_tip' => true,
      ),
    );

    return $fields;
  }
}
