<?php

namespace Mondu\Mondu;

use WC_Tax;

class OrderData {
  /**
   * @return array[]
   */
  public static function createOrderData() {
    $cart = WC()->session->get( 'cart' );
    $cart_totals = WC()->session->get( 'cart_totals' );
    $customer = WC()->session->get( 'customer' );

    $orderData = [
      'currency' => get_woocommerce_currency(),
      'buyer' => [
        'first_name' => isset( $customer['first_name'] ) ? $customer['first_name'] : null,
        'last_name' => isset( $customer['last_name'] ) ? $customer['last_name'] : null,
        'company_name' => isset( $customer['company'] ) ? $customer['company'] : null,
        'email' => isset( $customer['email'] ) ? $customer['email'] : null,
        'phone' => isset( $customer['phone'] ) ? $customer['phone'] : null,
      ],
      'billing_address' => [
        'address_line1' => isset( $customer['address_1'] ) ? $customer['address_1'] : null,
        'address_line2' => isset( $customer['address_2'] ) ? $customer['address_2'] : null,
        'city' => isset( $customer['city'] ) ? $customer['city'] : null,
        'state' => isset( $customer['state'] ) ? $customer['state'] : null,
        'zip_code' => isset( $customer['postcode'] ) ? $customer['postcode'] : null,
        'country_code' => isset( $customer['country'] ) ? $customer['country'] : null,
      ],
      'shipping_address' => [
        'address_line1' => isset( $customer['shipping_address_1'] ) ? $customer['shipping_address_1'] : null,
        'address_line2' => isset( $customer['shipping_address_2'] ) ? $customer['shipping_address_2'] : null,
        'city' => isset( $customer['shipping_city'] ) ? $customer['shipping_city'] : null,
        'state' => isset( $customer['shipping_state'] ) ? $customer['shipping_state'] : null,
        'zip_code' => isset( $customer['shipping_postcode'] ) ? $customer['shipping_postcode'] : null,
        'country_code' => isset( $customer['shipping_country'] ) ? $customer['shipping_country'] : null,
      ],
      'lines' => [],
    ];

    $line = [
      'discount_cents' => round( (float) $cart_totals['discount_total'] * 100, 2 ),
      'shipping_price_cents' => round( (float) $cart_totals['shipping_total'] * 100, 2 ),
      'line_items' => [],
    ];

    foreach ( $cart as $key => $cartItem ) {
      /** @var WC_Product $product */
      $product  = WC()->product_factory->get_product( $cartItem['product_id'] );

      $lineItem = [
        'title' => $product->get_title(),
        'quantity' => isset( $cartItem['quantity'] ) ? $cartItem['quantity'] : null,
        'external_reference_id' => isset( $cartItem['product_id'] ) ? (string) $cartItem['product_id'] : null,
        'product_id' => isset( $cartItem['product_id'] ) ? (string) $cartItem['product_id'] : null,
        'product_sku' => isset( $cartItem['product_sku'] ) ? $cartItem['product_sku'] : null,
        'net_price_per_item_cents' => round( (float) $cartItem['line_total'] * 100, 2 ),
        'gross_amount_cents' => round( ( (float) $cartItem['line_total'] + (float) $cartItem['line_tax'] ) * 100, 2 ),
        'tax_cents' => round( (float) $cartItem['line_tax'] * 100, 2 ),
        'item_type' => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
      ];

      // print_r($cartItem);

      $line['line_items'][] = $lineItem;
    }

    $orderData['lines'][] = $line;

    return $orderData;
  }

  private static function formatCents( float $value ) {
    if ( !isset( $value ) || $value == 0 ) {
      return null;
    }

    return round( $value * 100, 2 );
  }
}
