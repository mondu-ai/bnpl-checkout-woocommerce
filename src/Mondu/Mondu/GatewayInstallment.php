<?php
/**
 * GatewayInstallment class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInstallment
 *
 * @package Mondu
 */
class GatewayInstallment extends MonduGateway {
	public function __construct( $register_hooks = true ) {
		$payment_instructions     = __( 'Split payments - Pay Later in Installments by Direct Debit', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['installment'];
		$this->title              = __( 'Mondu Installments', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct( $register_hooks );
	}
}
