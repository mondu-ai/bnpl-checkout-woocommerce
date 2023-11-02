<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GatewayDirectDebit extends MonduGateway {
	public function __construct() {
		$this->id                 = PAYMENT_METHODS['direct_debit'];
		$this->title              = __( 'Mondu SEPA Direct Debit', 'mondu' );
		$this->description        = __( 'SEPA - Pay later by direct debit', 'mondu' );
		$this->method_description = __( 'SEPA - Pay later by direct debit', 'mondu' );
		$this->has_fields         = true;

		parent::__construct();
	}
}
