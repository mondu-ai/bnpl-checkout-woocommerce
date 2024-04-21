<?php
/**
 * Gateway Direct Debit
 *
 * @package Mondu\Mondu
 */

namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayDirectDebit
 */
class GatewayDirectDebit extends MonduGateway {
	/**
	 * GatewayDirectDebit constructor.
	 */
	public function __construct() {
		$payment_instructions     = __( 'SEPA - Pay later by direct debit', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['direct_debit'];
		$this->title              = __( 'Mondu SEPA Direct Debit', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct();
	}
}
