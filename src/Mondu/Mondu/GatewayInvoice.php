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
		$payment_instructions     = __( '', 'mondu' );
		$this->id                 = Plugin::get_payment_methods()['invoice'];
		$this->title              = __( 'Invoice (30 days)', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct( $register_hooks );
	}
}
