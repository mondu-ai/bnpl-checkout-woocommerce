<?php

namespace Mondu\Mondu;

use Mondu\Mondu\MonduGateway;
use Mondu\Plugin;

class GatewayInstallmentByInvoice extends MonduGateway {
	public function __construct() {
		$this->id                 = Plugin::PAYMENT_METHODS['installment_by_invoice'];
		$this->title              = __('Mondu Installments by Invoice', 'mondu');
		$this->description        = __('Split payments - Pay Later in Installments by Bank Transfer', 'mondu');
		$this->method_description = __('Split payments - Pay Later in Installments by Bank Transfer', 'mondu');
		$this->has_fields         = true;

		parent::__construct();
	}
}
