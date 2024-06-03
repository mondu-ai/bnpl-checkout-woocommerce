<?php
/**
 * Mondu Gateway class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

/**
 * Mondu Gateway
 *
 * @package Mondu
 */
class MonduGateway extends WC_Payment_Gateway {

	/**
	 * Mondu Global Settings
	 *
	 * @var MonduRequestWrapper
	 */
	protected $global_settings;

	/**
	 * Mondu Method Name
	 *
	 * @var MonduRequestWrapper
	 */
	protected $method_name;

	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	/**
	 * MonduGateway constructor.
	 *
	 * @param bool $register_hooks
	 */
	public function __construct( $register_hooks = true ) {
		$this->global_settings = get_option( Plugin::OPTION_NAME );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->is_enabled();

		$this->mondu_request_wrapper = new MonduRequestWrapper();

		if ( $register_hooks ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
			add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
			add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
		}


		$this->supports = [
			'refunds',
			'products',
		];
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = GatewayFields::fields( $this->title );
	}

	/**
	 * Add method
	 *
	 * @param array $methods
	 * @return array
	 */
	public static function add( array $methods ) {
		array_unshift( $methods, static::class );

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
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order
	 */
	public function email_instructions( $order ) {
		if ( !Plugin::order_has_mondu( $order ) ) {
			return;
		}

		if ( $this->description && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
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
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Process payment
	 *
	 * @param $order_id
	 * @return array|void
	 * @throws ResponseException
	 */
	public function process_payment( $order_id ) {
		$order       = wc_get_order( $order_id );
		$success_url = $this->get_return_url( $order );
		$mondu_order = $this->mondu_request_wrapper->create_order( $order, $success_url );

		if ( !$mondu_order ) {
			wc_add_notice( __( 'Error placing an order. Please try again.', 'mondu' ), 'error' );
			return;
		}

		return [
			'result'   => 'success',
			'redirect' => $mondu_order['hosted_checkout_url'],
		];
	}

	/**
	 * @param WC_Order $order
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		$can_refund_parent = parent::can_refund_order( $order );

		if ( !$can_refund_parent ) {
			return false;
		}

		return (bool) $order->get_meta( Plugin::INVOICE_ID_KEY );
	}

	/**
	 * @param $order_id
	 * @param $amount
	 * @param $reason
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( !$order instanceof WC_Order ) {
			return false;
		}

		$mondu_invoice_id = $order->get_meta( Plugin::INVOICE_ID_KEY );

		if ( !$mondu_invoice_id ) {
			return false;
		}
		
		$order_refunds = $order->get_refunds();
		/** @noinspection PhpIssetCanBeReplacedWithCoalesceInspection */
		$refund = isset($order_refunds[0]) ? $order_refunds[0] : null;

		if ( !$refund ) {
			return false;
		}

		try {
			$result = $this->mondu_request_wrapper->create_credit_note($mondu_invoice_id, OrderData::create_credit_note($refund));
		} catch ( ResponseException $e ) {
			return new WP_Error('error', $e->getMessage() );
		}

		if ( isset($result['credit_note']) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Mondu has its credentials validated.
	 *
	 * @return string
	 */
	private function is_enabled() {
		if ( null === get_option( '_mondu_credentials_validated' ) ) {
			$this->settings['enabled'] = 'no';
		}

		return !empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}
}
