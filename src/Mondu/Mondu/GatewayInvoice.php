<?php
/**
 * GatewayInvoice class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInvoice
 *
 * @package Mondu
 */
class GatewayInvoice extends MonduGateway {
	public function __construct( $register_hooks = true ) {
		$payment_instructions     = __( 'Invoice - Pay later by bank transfer', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['invoice'];
		$this->title              = __( 'Mondu Invoice', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct( $register_hooks );
	}
}
