<?php
/**
 * GatewayFields class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

/**
 * Class GatewayFields
 *
 * @package Mondu
 */
class GatewayFields {

	/**
	 * Returns the fields.
	 */
	public static function fields( $payment_method ) {
		return [
			'enabled' => [
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => /* translators: %s: Payment Method */ sprintf( __( 'Enable %s payment method', 'mondu' ), $payment_method ),
				'default' => 'no',
			],
		];
	}
}
