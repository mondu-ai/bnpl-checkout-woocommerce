<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GatewayInstallmentByInvoice extends MonduGateway {
	public function __construct($register_hooks = true) {
		$this->id                 = PAYMENT_METHODS['installment'];
		$this->title              = __( 'Mondu Installments by Invoice', 'mondu' );
		$this->description        = __( 'Split payments - Pay Later in Installments by Bank Transfer', 'mondu' );
		$this->method_description = __( 'Split payments - Pay Later in Installments by Bank Transfer', 'mondu' );
		$this->has_fields         = true;

		parent::__construct($register_hooks);
	}
}
