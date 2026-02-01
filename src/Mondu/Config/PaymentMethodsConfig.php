<?php
/**
 * Single configuration for all Mondu payment methods.
 *
 * @package Mondu
 */
namespace Mondu\Config;

use Mondu\Mondu\GatewayDirectDebit;
use Mondu\Mondu\GatewayInstallment;
use Mondu\Mondu\GatewayInstallmentByInvoice;
use Mondu\Mondu\GatewayInstantPay;
use Mondu\Mondu\GatewayInvoice;

defined( 'ABSPATH' ) || exit;

/**
 * PaymentMethodsConfig
 */
class PaymentMethodsConfig {

	/**
	 * Method key => config (id, gateway_class, icons, default_titles).
	 * Order defines registration order.
	 *
	 * @var array<string, array{id: string, gateway_class: string, icons: array{checkout: string, admin: string}, default_titles: array<string, string>}>
	 */
	private const METHODS = [
		'invoice' => [
			'id'             => 'mondu_invoice',
			'gateway_class'  => GatewayInvoice::class,
			'icons'          => [
				'checkout' => 'invoice_white_rectangle.png',
				'admin'    => 'Invoice_purple_square.svg',
			],
			'default_titles' => [
				'en' => 'Invoice (30 days)',
				'de' => 'Rechnungskauf (30 Tage)',
				'fr' => 'Facture (30 jours)',
				'nl' => 'Factuur (30 dagen)',
			],
		],
		'direct_debit' => [
			'id'             => 'mondu_direct_debit',
			'gateway_class'  => GatewayDirectDebit::class,
			'icons'          => [
				'checkout' => 'sepa_white_rectangle.png',
				'admin'    => 'SEPA_purple_square.png',
			],
			'default_titles' => [
				'en' => 'SEPA direct debit',
				'de' => 'SEPA-Lastschrift',
				'fr' => 'prélèvement SEPA',
				'nl' => 'SEPA automatische incasso',
			],
		],
		'installment' => [
			'id'             => 'mondu_installment',
			'gateway_class'  => GatewayInstallment::class,
			'icons'          => [
				'checkout' => 'installments_white_rectangle.png',
				'admin'    => 'Installments_purple_square.svg',
			],
			'default_titles' => [
				'en' => 'Installments (3, 6, 12 months)',
				'de' => 'Ratenkauf (3, 6, 12 Monaten)',
				'fr' => 'Paiement échelonnés (3, 6, 12 mois)',
				'nl' => 'Betaling in termijnen (3, 6, 12 maanden)',
			],
		],
		'installment_by_invoice' => [
			'id'             => 'mondu_installment_by_invoice',
			'gateway_class'  => GatewayInstallmentByInvoice::class,
			'icons'          => [
				'checkout' => 'installments_white_rectangle.png',
				'admin'    => 'Installments_purple_square.svg',
			],
			'default_titles' => [
				'en' => 'Business instalments (3, 6, 12)',
				'de' => 'Ratenkauf (3, 6, 12 Monaten) UK',
				'fr' => 'Paiement échelonnés (3, 6, 12 mois) UK',
				'nl' => 'Betaling in termijnen (3, 6, 12 maanden) UK',
			],
		],
		'pay_now' => [
			'id'             => 'mondu_pay_now',
			'gateway_class'  => GatewayInstantPay::class,
			'icons'          => [
				'checkout' => 'instant_pay_white_rectangle.png',
				'admin'    => 'Instant_Pay_purple_square.svg',
			],
			'default_titles' => [
				'en' => 'Instant Pay',
				'de' => 'Echtzeitüberweisung',
				'fr' => 'Virement instantané',
				'nl' => 'Instant Pay',
			],
		],
	];

	/**
	 * Ids map: method_key => gateway_id (for Plugin::get_payment_methods() compatibility).
	 *
	 * @return array<string, string>
	 */
	public static function get_ids() {
		$ids = [];
		foreach ( self::METHODS as $key => $config ) {
			$ids[ $key ] = $config['id'];
		}
		return $ids;
	}

	/**
	 * All gateway ids (values only).
	 *
	 * @return array<int, string>
	 */
	public static function get_gateway_ids() {
		return array_values( self::get_ids() );
	}

	/**
	 * Gateway classes in registration order.
	 *
	 * @return array<int, string>
	 */
	public static function get_gateway_classes() {
		return array_column( self::METHODS, 'gateway_class' );
	}

	/**
	 * Config for one method by key.
	 *
	 * @param string $key Method key (e.g. 'invoice', 'pay_now').
	 * @return array{id: string, gateway_class: string, icons: array{checkout: string, admin: string}, default_titles: array<string, string>}|null
	 */
	public static function get_method( $key ) {
		return isset( self::METHODS[ $key ] ) ? self::METHODS[ $key ] : null;
	}

	/**
	 * Config for one method by gateway id (e.g. 'mondu_invoice').
	 *
	 * @param string $gateway_id Gateway id.
	 * @return array{id: string, gateway_class: string, icons: array{checkout: string, admin: string}, default_titles: array<string, string>}|null
	 */
	public static function get_method_by_gateway_id( $gateway_id ) {
		foreach ( self::METHODS as $config ) {
			if ( $config['id'] === $gateway_id ) {
				return $config;
			}
		}
		return null;
	}

	/**
	 * Icon filenames for a gateway (checkout + admin).
	 *
	 * @param string $gateway_id Gateway id.
	 * @return array{checkout: string, admin: string}|null
	 */
	public static function get_icons_for_gateway( $gateway_id ) {
		$method = self::get_method_by_gateway_id( $gateway_id );
		return $method ? $method['icons'] : null;
	}

	/**
	 * Default title per locale for a gateway.
	 *
	 * @param string $gateway_id Gateway id.
	 * @return array<string, string>
	 */
	public static function get_default_titles_for_gateway( $gateway_id ) {
		$method = self::get_method_by_gateway_id( $gateway_id );
		return $method ? $method['default_titles'] : [];
	}

	/**
	 * All methods config (for iteration).
	 *
	 * @return array<string, array{id: string, gateway_class: string, icons: array{checkout: string, admin: string}, default_titles: array<string, string>}>
	 */
	public static function get_all() {
		return self::METHODS;
	}
}
