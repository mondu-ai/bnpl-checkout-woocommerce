<?php

namespace Mondu\Mondu\Support;

use Mondu\Mondu\Support\Helper;
use Mondu\Plugin;
use WC_Order;

class OrderData {
  /**
   * @return array[]
   */
  public static function create_order_data() {
    $except_keys = ['amount'];
    $order_data = self::raw_order_data();

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
   * @return array[]
   */
  public static function raw_order_data() {
    $cart = WC()->session->get('cart');
    $cart_totals = WC()->session->get('cart_totals');
    $customer = WC()->session->get('customer');

    $logger  = wc_get_logger();
    $logger->debug('customer', [
      'logged' => is_user_logged_in(),
      'user' => get_current_user_id(),
      // 'customer' => $customer,
    ]);

    $order_data = [
      'currency' => get_woocommerce_currency(),
      'external_reference_id' => '0', // We will update this id when woocommerce order is created
      'buyer' => [
        'first_name' => isset($customer['first_name']) ? $customer['first_name'] : null,
        'last_name' => isset($customer['last_name']) ? $customer['last_name'] : null,
        'company_name' => isset($customer['company']) ? $customer['company'] : null,
        'email' => isset($customer['email']) ? $customer['email'] : null,
        'phone' => isset($customer['phone']) ? $customer['phone'] : null,
        'external_reference_id' => isset($customer['id']) ? $customer['id'] : null,
        'is_registered' => is_user_logged_in(),
      ],
      'billing_address' => [
        'address_line1' => isset($customer['address_1']) ? $customer['address_1'] : null,
        'address_line2' => isset($customer['address_2']) ? $customer['address_2'] : null,
        'city' => isset($customer['city']) ? $customer['city'] : null,
        'state' => isset($customer['state']) ? $customer['state'] : null,
        'zip_code' => isset($customer['postcode']) ? $customer['postcode'] : null,
        'country_code' => isset($customer['country']) ? $customer['country'] : null,
      ],
      'shipping_address' => [
        'address_line1' => isset($customer['shipping_address_1']) ? $customer['shipping_address_1'] : null,
        'address_line2' => isset($customer['shipping_address_2']) ? $customer['shipping_address_2'] : null,
        'city' => isset($customer['shipping_city']) ? $customer['shipping_city'] : null,
        'state' => isset($customer['shipping_state']) ? $customer['shipping_state'] : null,
        'zip_code' => isset($customer['shipping_postcode']) ? $customer['shipping_postcode'] : null,
        'country_code' => isset($customer['shipping_country']) ? $customer['shipping_country'] : null,
      ],
      'lines' => [],
      'amount' => [], # We have the amount here to avoid calculating it when updating external_reference_id (it is also removed when creating)
    ];

    $line = [
      'discount_cents' => round ((float) $cart_totals['discount_total'] * 100, 2),
      'shipping_price_cents' => round ((float) ($cart_totals['shipping_total'] + $cart_totals['shipping_tax']) * 100, 2), # Considering that is not possible to save taxes that does not belongs to products, sums shipping taxes here
      // 'tax_cents' => round ((float) $cart_totals['total_tax'] * 100, 2),
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
        'external_reference_id' => isset($cart_item['product_id']) ? (string) $cart_item['product_id'] : null,
        'product_id' => isset($cart_item['product_id']) ? (string) $cart_item['product_id'] : null,
        'product_sku' => isset($cart_item['product_sku']) ? $cart_item['product_sku'] : null,
        'net_price_per_item_cents' => round ((float) ($cart_item['line_total'] / $cart_item['quantity']) * 100, 2),
        'net_price_cents' => round ((float) $cart_item['line_total'] * 100, 2),
        'tax_cents' => round ((float) $cart_item['line_tax'] * 100, 2),
        'item_type' => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
      ];

      $line['line_items'][] = $line_item;

      $net_price_cents += (float) $cart_item['line_total'] * 100;
      $tax_cents += (float) $cart_item['line_tax'] * 100;
    }

    $amount = [
      'net_price_cents' => round ($net_price_cents, 2),
      'tax_cents' => round ($tax_cents, 2),
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
      'external_reference_id' => (string) $order->get_id(),
      'lines' => [],
      'amount' => [],
    ];

    $line = [
      'discount_cents' => round ($order->get_discount_total() * 100, 2),
      'shipping_price_cents' => round ((float) ($order->get_shipping_total() + $order->get_shipping_tax()) * 100, 2), # Considering that is not possible to save taxes that does not belongs to products, sums shipping taxes here
      'line_items' => [],
    ];

    $net_price_cents = 0;
    $tax_cents = 0;

    foreach($order->get_items() as $item_id => $item) {
      $product = $item->get_product();

      $line_item = [
        'title' => $product->get_title(),
        'quantity' => $item->get_quantity(),
        'external_reference_id' => Helper::null_or_empty($product->get_id()) ? null : (string) $product->get_id(),
        'product_id' => Helper::null_or_empty($product->get_id()) ? null : (string) $product->get_id(),
        'product_sku' => Helper::null_or_empty($product->get_slug()) ? null : (string) $product->get_slug(),
        'net_price_per_item_cents' => round ((float) ($item->get_total() / $item->get_quantity()) * 100, 2),
        'net_price_cents' => round ((float) $item->get_total() * 100, 2),
        'tax_cents' => round ((float) $item->get_total_tax() * 100, 2),
        'item_type' => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
      ];

      $line['line_items'][] = $line_item;

      $net_price_cents += (float) $item->get_total() * 100;
      $tax_cents += (float) $item->get_total_tax() * 100;
    }

    $amount = [
      'net_price_cents' => round ($net_price_cents, 2),
      'tax_cents' => round ($tax_cents, 2),
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
      'external_reference_id' => (string) $order->get_id(),
      'invoice_url' => Helper::create_invoice_url($order->get_id()),
      'gross_amount_cents' => round ((float) $order->get_total() * 100, 2),
      'tax_cents' => round ((float) ($order->get_total_tax() - $order->get_shipping_tax()) * 100, 2), # Considering that is not possible to save taxes that does not belongs to products, removes shipping taxes here
      'discount_cents' => round ($order->get_discount_total() * 100, 2),
      'shipping_price_cents' => round ((float) ($order->get_shipping_total() + $order->get_shipping_tax()) * 100, 2), # Considering that is not possible to save taxes that does not belongs to products, sum shipping taxes here
      'line_items' => [],
    ];

    if ($order->get_shipping_method()) {
      $invoice_data['shipping_info']['shipping_method'] = $order->get_shipping_method();
    }

    if($order->get_shipping_method()) {
      $invoice_data['shipping_info'] = [
        'shipping_method' => $order->get_shipping_method()
      ];
    }

    foreach($order->get_items() as $item_id => $item) {
      $product = $item->get_product();

      $line_item = [
        'external_reference_id' => Helper::null_or_empty($product->get_id()) ? null : (string) $product->get_id(),
        'quantity' => $item->get_quantity(),
      ];

      $invoice_data['line_items'][] = $line_item;
    }

    return $invoice_data;
  }
}
