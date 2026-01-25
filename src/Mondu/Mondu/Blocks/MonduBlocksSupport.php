<?php
/**
 * Mondu Blocks Support
 *
 * @package Mondu
 */
namespace Mondu\Mondu\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Mondu\Mondu\GatewayDirectDebit;
use Mondu\Mondu\GatewayInstallment;
use Mondu\Mondu\GatewayInstallmentByInvoice;
use Mondu\Mondu\GatewayInstantPay;
use Mondu\Mondu\GatewayInvoice;
use Mondu\Mondu\MonduGateway;
use Mondu\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Mondu Blocks Support
 *
 * @package Mondu
 */
final class MonduBlocksSupport extends AbstractPaymentMethodType {
	/**
	 * @var string
	 */
	protected $name = 'mondu_blocks';

	/**
	 * @var MonduGateway[]
	 */
	protected $gateways;

	/**
	 * Initialize
	 */
	public function initialize() {
		$this->gateways = [
			new GatewayInvoice( false ),
			new GatewayDirectDebit( false ),
			new GatewayInstallment( false ),
			new GatewayInstallmentByInvoice( false ),
			new GatewayInstantPay( false ),
		];
	}

	/**
	 * Get payment method script handles
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'mondu-blocks-integration',
			MONDU_PUBLIC_PATH . 'assets/src/js/mondublocks.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			MONDU_PLUGIN_VERSION,
			true
		);

		wp_set_script_translations( 'mondu-blocks-integration', 'mondu', MONDU_PLUGIN_PATH . '/languages' );

		return [ 'mondu-blocks-integration' ];
	}

	/**
	 * Get payment method data
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateways = array_reduce($this->gateways, function ( $carry, $item ) {
			$carry[ $item->id ] = [
				'title'       => $item->get_title(),
				'description' => $item->description,
				'supports'    => $item->supports,
				'enabled'     => $item->enabled,
				'icon'        => $item->get_payment_method_icon_url(),
			];
			return $carry;
		}, []);

		return [
			'gateways'        => $gateways
		];
	}
}
