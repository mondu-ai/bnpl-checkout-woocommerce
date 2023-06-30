<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\GatewayFields;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Support\Helper;
use Mondu\Plugin;
use WC_Data_Exception;
use WC_Order;
use WC_Payment_Gateway;

class MonduGateway extends WC_Payment_Gateway {

	protected $global_settings;

	protected $method_name;

	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	public function __construct() {
		$this->global_settings = get_option(Plugin::OPTION_NAME);

		$this->init_form_fields();
		$this->init_settings();

		$this->instructions = $this->description;
		$this->enabled      = $this->is_enabled();

		$this->mondu_request_wrapper = new MonduRequestWrapper();

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ]);
		add_action('woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ]);
		add_action('woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3);
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = GatewayFields::fields($this->title);
	}

	/**
	 * Add method
	 *
	 * @param array $methods
	 * @return array
	 */
	public static function add( array $methods ) {
		array_unshift($methods, static::class);

		return $methods;
	}

	/**
	 * Include payment fields on order pay page
	 *
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();
		include MONDU_VIEW_PATH . '/checkout/payment-form.php';
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)));
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order
	 */
	public function email_instructions( $order ) {
		if ( !Plugin::order_has_mondu($order) ) {
			return;
		}

		if ( $this->instructions && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)));
		}
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '<img src="https://checkout.mondu.ai/logo.svg" alt="' . $this->method_title . '" width="100" />';

		/**
		 * Mondu payment icon
		 *
		 * @since 1.3.2
		 */
		return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
	}

	/**
	 * Process payment
	 *
	 * @param $order_id
	 * @return array|void
	 * @throws ResponseException
	 */
	public function process_payment( $order_id ) {
		$order       = wc_get_order($order_id);
		$success_url = get_site_url() . '/?rest_route=/mondu/v1/orders/confirm&external_reference_id=' . $order_id . '&return_url=' . urlencode( $this->get_return_url( $order ) ) ;
		$mondu_order = $this->mondu_request_wrapper->create_order( $order, $success_url, null );

		if ( !$mondu_order ) {
			wc_add_notice(__('Error placing an order. Please try again.', 'mondu'), 'error');
			return;
		}

		return [
			'result'   => 'success',
			'redirect' => $mondu_order['hosted_checkout_url'],
		];
	}

	/**
	 * Check if Mondu has its credentials validated.
	 *
	 * @return string
	 */
	private function is_enabled() {
		if ( null === get_option('_mondu_credentials_validated') ) {
			$this->settings['enabled'] = 'no';
		}

		return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}
}
