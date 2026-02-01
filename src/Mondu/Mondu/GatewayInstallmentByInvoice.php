<?php
/**
 * GatewayInstallmentByInvoice class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInstallmentByInvoice
 *
 * @package Mondu
 */
class GatewayInstallmentByInvoice extends MonduGateway {
	public function __construct( $register_hooks = true ) {
		$payment_instructions     = __( '', 'mondu' );
		$this->id                 = Plugin::get_payment_methods()['installment_by_invoice'];
		$this->title              = __( 'Business instalments (3, 6, 12)', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct( $register_hooks );
	}
}
