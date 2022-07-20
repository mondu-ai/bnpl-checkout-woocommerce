<?php

namespace Mondu\Mondu\Models;

use Mondu\Mondu\Support\Helper;
use Mondu\Plugin;
use WC_Order;

class Order {
  public function __construct($product_id) {
    /** @var WC_Product $product */
    $product = WC()->product_factory->get_product($product_id);
  }
}
