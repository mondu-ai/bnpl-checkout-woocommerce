<?php
/**
 * GatewayInstallmentByInvoice
 *
 * @package Mondu\Mondu
 */

namespace Mondu\Mondu;

/**
 * Class GatewayFields
 */
class GatewayFields {
	/**
	 * Returns the fields.
	 *
	 * @param string $payment_method The payment method.
	 *
	 * @return array
	 */
	public static function fields( $payment_method ) {
		return array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => /* translators: %s: Payment Method */ sprintf( __( 'Enable %s payment method', 'mondu' ), $payment_method ),
				'default' => 'no',
			),
		);
	}
}
