<?php

namespace Mondu\Mondu;

use Mondu\Mondu\MonduGateway;
use Mondu\Plugin;

class GatewayDirectDebit extends MonduGateway {
	public function __construct() {
		$this->id                 = Plugin::PAYMENT_METHODS['direct_debit'];
		$this->title              = __('Mondu SEPA Direct Debit', 'mondu');
		$this->description        = __('SEPA - Pay later by direct debit', 'mondu');
		$this->method_description = __('SEPA - Pay later by direct debit', 'mondu');
		$this->has_fields         = true;

		parent::__construct();
	}
}
