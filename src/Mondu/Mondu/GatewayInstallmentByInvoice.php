<?php

namespace Mondu\Mondu;

use Mondu\Mondu\MonduGateway;
use Mondu\Plugin;

class GatewayInstallmentByInvoice extends MonduGateway {
	public function __construct() {
		$payment_instructions     = __('Split payments - Pay Later in Installments by Bank Transfer', 'mondu');
		$this->id                 = Plugin::PAYMENT_METHODS['installment_by_invoice'];
		$this->title              = __('Mondu Installments by Invoice', 'mondu');
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct();
	}
}
