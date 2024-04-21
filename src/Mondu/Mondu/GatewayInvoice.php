<?php
/**
 * Gateway Invoice
 *
 * @package Mondu\Mondu
 */

namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInvoice
 */
class GatewayInvoice extends MonduGateway {
	/**
	 * GatewayDirectDebit constructor.
	 */
	public function __construct() {
		$payment_instructions     = __( 'Invoice - Pay later by bank transfer', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['invoice'];
		$this->title              = __( 'Mondu Invoice', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct();
	}
}
