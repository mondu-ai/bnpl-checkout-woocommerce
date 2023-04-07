<?php

namespace Mondu\Mondu;

use Mondu\Mondu\MonduGateway;
use Mondu\Plugin;

class GatewayInvoice extends MonduGateway {
  public function __construct() {
    $this->id = Plugin::PAYMENT_METHODS['invoice'];
    $this->method_title = __('Mondu Invoice', 'mondu');
    $this->method_description = __('Invoice - Pay later by bank transfer', 'mondu');
    $this->has_fields = true;

    parent::__construct();
  }
}
