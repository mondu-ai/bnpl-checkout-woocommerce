<?php

namespace Mondu\Mondu\Models;

use Mondu\Mondu\Support\Helper;
use Mondu\Plugin;
use WC_Order;

class Product {
  private $product;

  public function __construct($product_id) {
    /** @var WC_Product $product */
    $this->product = WC()->product_factory->get_product($product_id);
  }

  private function get_id() {
    $this->product->get_id();
  }

  private function get_sku() {
    $this->product->get_sku();
  }

  private function get_title() {
    $this->product->get_title();
  }
}
