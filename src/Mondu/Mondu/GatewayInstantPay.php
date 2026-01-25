<?php
/**
 * GatewayInstantPay class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Plugin;

/**
 * Class GatewayInstantPay
 *
 * @package Mondu
 */
class GatewayInstantPay extends MonduGateway {
	public function __construct( $register_hooks = true ) {
		$payment_instructions     = __( '', 'mondu' );
		$this->id                 = Plugin::PAYMENT_METHODS['pay_now'];
		$this->title              = __( 'Instant Pay', 'mondu' );
		$this->description        = $payment_instructions;
		$this->method_description = $payment_instructions;
		$this->has_fields         = true;

		parent::__construct( $register_hooks );
	}
}
