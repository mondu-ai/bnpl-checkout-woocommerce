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
			'title_en' => [
				'title'       => __( 'Title (English)', 'mondu' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers when the page language is English.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'title_de' => [
				'title'       => __( 'Title (German)', 'mondu' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers when the page language is German.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'title_fr' => [
				'title'       => __( 'Title (French)', 'mondu' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers when the page language is French.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'title_nl' => [
				'title'       => __( 'Title (Dutch)', 'mondu' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers when the page language is Dutch.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'description_en' => [
				'title'       => __( 'Description (English)', 'mondu' ),
				'type'        => 'textarea',
				'description' => __( 'Shown to customers when the page language is English.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'description_de' => [
				'title'       => __( 'Description (German)', 'mondu' ),
				'type'        => 'textarea',
				'description' => __( 'Shown to customers when the page language is German.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'description_fr' => [
				'title'       => __( 'Description (French)', 'mondu' ),
				'type'        => 'textarea',
				'description' => __( 'Shown to customers when the page language is French.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'description_nl' => [
				'title'       => __( 'Description (Dutch)', 'mondu' ),
				'type'        => 'textarea',
				'description' => __( 'Shown to customers when the page language is Dutch.', 'mondu' ),
				'default'     => '',
				'desc_tip'    => true,
			],
		];
	}
}
