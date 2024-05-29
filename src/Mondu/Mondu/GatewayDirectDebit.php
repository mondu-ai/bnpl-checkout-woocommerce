<?php
/**
 * GatewayDirectDebit class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayDirectDebit
 *
 * @package Mondu
 */
class GatewayDirectDebit extends MonduGateway {
	public function __construct( $register_hooks = true ) {
		$payment_instructions     = __( 'SEPA - Pay later by direct debit', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['direct_debit'];
		$this->title              = __( 'Mondu SEPA Direct Debit', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct( $register_hooks );
	}
}
