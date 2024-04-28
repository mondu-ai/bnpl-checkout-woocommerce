<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GatewayInvoice extends MonduGateway {
	public function __construct($register_hooks = true) {
		$this->id                 = PAYMENT_METHODS['invoice'];
		$this->title              = __( 'Mondu Invoice', 'mondu' );
		$this->description        = __( 'Invoice - Pay later by bank transfer', 'mondu' );
		$this->method_description = __( 'Invoice - Pay later by bank transfer', 'mondu' );
		$this->has_fields         = true;

		parent::__construct($register_hooks);
	}
}
