<?php

namespace Mondu\Mondu;

use Mondu\Mondu\MonduGateway;
use Mondu\Plugin;

class GatewayDirectDebit extends MonduGateway {
	public function __construct() {
		$payment_instructions     = __('SEPA - Pay later by direct debit', 'mondu');
		$this->id                 = Plugin::PAYMENT_METHODS['direct_debit'];
		$this->title              = __('Mondu SEPA Direct Debit', 'mondu');
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct();
	}
}
