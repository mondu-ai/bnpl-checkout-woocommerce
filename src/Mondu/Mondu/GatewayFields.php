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
			'title_translations' => [
				'title'       => __( 'Titles by language', 'mondu' ),
				'type'        => 'mondu_title_translations',
				'description' => __( 'Add a row per language. The title for the current page language is used at checkout.', 'mondu' ),
				'default'     => [],
				'desc_tip'    => true,
			],
			'description_translations' => [
				'title'       => __( 'Descriptions by language', 'mondu' ),
				'type'        => 'mondu_description_translations',
				'description' => __( 'Add a row per language. The description for the current page language is used at checkout.', 'mondu' ),
				'default'     => [],
				'desc_tip'    => true,
			],
		];
	}
}
