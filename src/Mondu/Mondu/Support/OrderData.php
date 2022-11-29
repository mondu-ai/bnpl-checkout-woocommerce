<?php

namespace Mondu\Mondu\Support;

use Mondu\Mondu\Support\Helper;
use Mondu\Plugin;
use WC_Order;

class OrderData {
  /**
   * @return array[]
   */
  public static function create_order_data($payment_method) {
    $except_keys = ['amount'];
    $order_data = self::raw_order_data($payment_method);

    return Helper::remove_keys($order_data, $except_keys);
  }

  /**
   * @param $order_id
   * @param $data_to_update
   *
   * @return array[]
   */
  public static function adjust_order_data($order_id, $data_to_update) {
    $except_keys = ['buyer', 'billing_address', 'shipping_address'];
    $order_data = get_post_meta($order_id, Plugin::ORDER_DATA_KEY, true);

    $new_order_data = array_merge($order_data, $data_to_update);
    update_post_meta($order_id, Plugin::ORDER_DATA_KEY, $new_order_data);

    return Helper::remove_keys($new_order_data, $except_keys);
  }

  /**
   * @param string $payment_method
   *
   * @return array[]
   */
  public static function raw_order_data($payment_method = 'invoice') {
    $cart = WC()->session->get('cart');
    $cart_totals = WC()->session->get('cart_totals');
    $customer = WC()->session->get('customer');

    $order_data = [
      'payment_method' => $payment_method,
      'currency' => get_woocommerce_currency(),
      'external_reference_id' => '0', // We will update this id when woocommerce order is created
      'gross_amount_cents' => round((float) $cart_totals['total'] * 100),
      'buyer' => [
        'first_name' => isset($customer['first_name']) && Helper::not_null_or_empty($customer['first_name']) ? $customer['first_name'] : null,
        'last_name' => isset($customer['last_name']) && Helper::not_null_or_empty($customer['last_name']) ? $customer['last_name'] : null,
        'company_name' => isset($customer['company']) && Helper::not_null_or_empty($customer['company']) ? $customer['company'] : null,
        'email' => isset($customer['email']) && Helper::not_null_or_empty($customer['email']) ? $customer['email'] : null,
        'phone' => isset($customer['phone']) && Helper::not_null_or_empty($customer['phone']) ? $customer['phone'] : null,
        'external_reference_id' => isset($customer['id']) && Helper::not_null_or_empty($customer['id']) ? $customer['id'] : null,
        'is_registered' => is_user_logged_in(),
      ],
      'billing_address' => [
        'address_line1' => isset($customer['address_1']) && Helper::not_null_or_empty($customer['address_1']) ? $customer['address_1'] : null,
        'address_line2' => isset($customer['address_2']) && Helper::not_null_or_empty($customer['address_2']) ? $customer['address_2'] : null,
        'city' => isset($customer['city']) && Helper::not_null_or_empty($customer['city']) ? $customer['city'] : null,
        'state' => isset($customer['state']) && Helper::not_null_or_empty($customer['state']) ? $customer['state'] : null,
        'zip_code' => isset($customer['postcode']) && Helper::not_null_or_empty($customer['postcode']) ? $customer['postcode'] : null,
        'country_code' => isset($customer['country']) && Helper::not_null_or_empty($customer['country']) ? $customer['country'] : null,
      ],
      'shipping_address' => [
        'address_line1' => isset($customer['shipping_address_1']) && Helper::not_null_or_empty($customer['shipping_address_1']) ? $customer['shipping_address_1'] : null,
        'address_line2' => isset($customer['shipping_address_2']) && Helper::not_null_or_empty($customer['shipping_address_2']) ? $customer['shipping_address_2'] : null,
        'city' => isset($customer['shipping_city']) && Helper::not_null_or_empty($customer['shipping_city']) ? $customer['shipping_city'] : null,
        'state' => isset($customer['shipping_state']) && Helper::not_null_or_empty($customer['shipping_state']) ? $customer['shipping_state'] : null,
        'zip_code' => isset($customer['shipping_postcode']) && Helper::not_null_or_empty($customer['shipping_postcode']) ? $customer['shipping_postcode'] : null,
        'country_code' => isset($customer['shipping_country']) && Helper::not_null_or_empty($customer['shipping_country']) ? $customer['shipping_country'] : null,
      ],
      'lines' => [],
      'amount' => [], # We have the amount here to avoid calculating it when updating external_reference_id (it is also removed when creating)
    ];

    $line = [
      'discount_cents' => round((float) $cart_totals['discount_total'] * 100),
      'shipping_price_cents' => round((float) ($cart_totals['shipping_total'] + $cart_totals['shipping_tax']) * 100), # Considering that is not possible to save taxes that does not belongs to products, sums shipping taxes here
      // 'tax_cents' => round((float) $cart_totals['total_tax'] * 100, 2),
      'line_items' => [],
    ];

    $net_price_cents = 0;
    $tax_cents = 0;

    foreach ($cart as $key => $cart_item) {
      /** @var WC_Product $product */
      $product = WC()->product_factory->get_product($cart_item['product_id']);
      $line_item = [
        'title' => $product->get_title(),
        'quantity' => isset($cart_item['quantity']) ? $cart_item['quantity'] : null,
        'external_reference_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
        'product_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
        'product_sku' => Helper::not_null_or_empty($product->get_slug()) ? (string) $product->get_slug() : null,
        'net_price_per_item_cents' => round((float) ($cart_item['line_subtotal'] / $cart_item['quantity']) * 100),
        'net_price_cents' => round((float) $cart_item['line_subtotal'] * 100),
        'tax_cents' => round((float) $cart_item['line_tax'] * 100),
        'item_type' => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
      ];

      $line['line_items'][] = $line_item;

      $net_price_cents += (float) $cart_item['line_subtotal'] * 100;
      $tax_cents += (float) $cart_item['line_tax'] * 100;
    }

    $amount = [
      'net_price_cents' => round($net_price_cents),
      'tax_cents' => round($tax_cents),
    ];

    $order_data['lines'][] = $line;
    $order_data['amount'] = $amount;

    return $order_data;
  }

  /**
   * @param $order
   *
   * @return array[]
   */
  public static function order_data_from_wc_order(WC_Order $order) {
    $order_data = [
      'currency' => get_woocommerce_currency(),
      'external_reference_id' => $order->get_order_number(),
      'lines' => [],
      'amount' => [],
    ];

    $line = [
      'discount_cents' => round($order->get_discount_total() * 100),
      'shipping_price_cents' => round((float) ($order->get_shipping_total() + $order->get_shipping_tax()) * 100), # Considering that is not possible to save taxes that does not belongs to products, sums shipping taxes here
      'line_items' => [],
    ];

    $net_price_cents = 0;
    $tax_cents = 0;

    foreach ($order->get_items() as $item_id => $item) {
      $product = $item->get_product();

      $line_item = [
        'title' => $product->get_title(),
        'quantity' => $item->get_quantity(),
        'external_reference_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
        'product_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
        'product_sku' => Helper::not_null_or_empty($product->get_slug()) ? (string) $product->get_slug() : null,
        'net_price_per_item_cents' => round((float) ($item->get_subtotal() / $item->get_quantity()) * 100),
        'net_price_cents' => round((float) $item->get_subtotal() * 100),
        'tax_cents' => round((float) $item->get_total_tax() * 100),
        'item_type' => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
      ];

      $line['line_items'][] = $line_item;

      $net_price_cents += (float) $item->get_subtotal() * 100;
      $tax_cents += (float) $item->get_total_tax() * 100;
    }

    $amount = [
      'gross_amount_cents' => round((float) $order->get_total() * 100),
      'net_price_cents' => round($net_price_cents),
      'tax_cents' => round($tax_cents),
    ];

    $order_data['lines'][] = $line;
    $order_data['amount'] = $amount;

    return $order_data;
  }

  /**
   * @param $order
   *
   * @return array[]
   */
  public static function invoice_data_from_wc_order(WC_Order $order) {
    $invoice_data = [
      'external_reference_id' => $order->get_order_number(),
      'invoice_url' => Helper::create_invoice_url($order->get_id()),
      'gross_amount_cents' => round((float) $order->get_total() * 100),
      'tax_cents' => round((float) ($order->get_total_tax() - $order->get_shipping_tax()) * 100), # Considering that is not possible to save taxes that does not belongs to products, removes shipping taxes here
      'discount_cents' => round($order->get_discount_total() * 100),
      'shipping_price_cents' => round((float) ($order->get_shipping_total() + $order->get_shipping_tax()) * 100), # Considering that is not possible to save taxes that does not belongs to products, sum shipping taxes here
      'line_items' => [],
    ];

    if ($order->get_shipping_method()) {
      $invoice_data['shipping_info']['shipping_method'] = $order->get_shipping_method();
    }

    if ($order->get_shipping_method()) {
      $invoice_data['shipping_info'] = [
        'shipping_method' => $order->get_shipping_method()
      ];
    }

    foreach ($order->get_items() as $item_id => $item) {
      $product = $item->get_product();

      $line_item = [
        'external_reference_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
        'quantity' => $item->get_quantity(),
      ];

      $invoice_data['line_items'][] = $line_item;
    }

    return $invoice_data;
  }
}
