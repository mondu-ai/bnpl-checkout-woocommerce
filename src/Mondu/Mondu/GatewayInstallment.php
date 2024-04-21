<?php
/**
 * Gateway Installment
 *
 * @package Mondu\Mondu
 */

namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInstallment
 */
class GatewayInstallment extends MonduGateway {
	/**
	 * GatewayInstallment constructor.
	 */
	public function __construct() {
		$payment_instructions     = __( 'Split payments - Pay Later in Installments by Direct Debit', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['installment'];
		$this->title              = __( 'Mondu Installments', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct();
	}
}
