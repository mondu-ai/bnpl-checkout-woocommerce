<?php
/**
 * GatewayInstallmentByInvoice
 *
 * @package Mondu\Mondu
 */

namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInstallmentByInvoice
 *
 * @package Mondu\Mondu
 */
class GatewayInstallmentByInvoice extends MonduGateway {
	/**
	 * GatewayInstallmentByInvoice constructor.
	 */
	public function __construct() {
		$payment_instructions     = __( 'Split payments - Pay Later in Installments by Bank Transfer', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['installment_by_invoice'];
		$this->title              = __( 'Mondu Installments by Invoice', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct();
	}
}
