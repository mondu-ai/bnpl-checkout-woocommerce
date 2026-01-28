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

	private const PAYMENT_METHOD_IMAGES = [
		'mondu_invoice'                => 'invoice_white_rectangle.png',
		'mondu_direct_debit'           => 'sepa_white_rectangle.png',
		'mondu_installment'            => 'installments_white_rectangle.png',
		'mondu_installment_by_invoice' => 'installments_white_rectangle.png',
		'mondu_pay_now'                => 'instant_pay_white_rectangle.png',
	];

	private const PAYMENT_METHOD_ADMIN_IMAGES = [
		'mondu_invoice'                => 'Invoice_purple_square.svg',
		'mondu_direct_debit'           => 'SEPA_purple_square.png',
		'mondu_installment'            => 'Installments_purple_square.svg',
		'mondu_installment_by_invoice' => 'Installments_purple_square.svg',
		'mondu_pay_now'                => 'Instant Pay_purple_square.svg',
	];

	private const SUPPORTED_LOCALES = [ 'de', 'en', 'nl' ];

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

		$this->description        = $this->get_localized_description_from_settings();
		$this->method_description = $this->description;
		$this->method_title       = (string) $this->title;

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

		$this->icon = $this->get_admin_icon_url();
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = GatewayFields::fields( $this->title );
	}

	/**
	 * Gateway title depending on current page locale.
	 *
	 * Uses per-locale admin setting for the current language.
	 *
	 * @return string
	 */
	public function get_title() {
		$lang = $this->get_request_language();
		$key  = 'title_' . $lang;
		return trim( (string) $this->get_option( $key, '' ) );
	}

	/**
	 * Get localized description from gateway settings.
	 *
	 * @return string
	 */
	private function get_localized_description_from_settings() {
		$lang = $this->get_request_language();
		$key  = 'description_' . $lang;
		return (string) $this->get_option( $key, '' );
	}

	/**
	 * Determine current 2-letter language code.
	 *
	 * @return string One of: en, de, fr, nl
	 */
	private function get_request_language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$lang   = strtolower( substr( (string) $locale, 0, 2 ) );

		if ( in_array( $lang, [ 'en', 'de', 'fr', 'nl' ], true ) ) {
			return $lang;
		}

		return 'en';
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
		$icon_url = $this->get_payment_method_icon_url();
		$icon_html = '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $this->method_title ) . '" style="max-height: 40px; position: relative; top: 5px;" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Get payment method icon URL based on locale.
	 *
	 * @return string
	 */
	public function get_payment_method_icon_url() {
		$locale = $this->get_icon_locale();
		$image_name = isset( self::PAYMENT_METHOD_IMAGES[ $this->id ] ) 
			? self::PAYMENT_METHOD_IMAGES[ $this->id ] 
			: 'invoice_white_rectangle.png';

		$icon_path = MONDU_PLUGIN_PATH . '/assets/src/images/payment-methods/' . $locale . '/' . $image_name;
		
		if ( file_exists( $icon_path ) ) {
			return MONDU_PUBLIC_PATH . 'assets/src/images/payment-methods/' . $locale . '/' . $image_name;
		}

		return 'https://checkout.mondu.ai/logo.svg';
	}

	/**
	 * Get admin icon URL (square icons for admin panel).
	 *
	 * @return string
	 */
	public function get_admin_icon_url() {
		$locale = $this->get_icon_locale();
		$image_name = isset( self::PAYMENT_METHOD_ADMIN_IMAGES[ $this->id ] )
			? self::PAYMENT_METHOD_ADMIN_IMAGES[ $this->id ]
			: 'Mondu_white_square.svg';

		$icon_path = MONDU_PLUGIN_PATH . '/assets/src/images/payment-methods/' . $locale . '/' . $image_name;

		if ( file_exists( $icon_path ) ) {
			return MONDU_PUBLIC_PATH . 'assets/src/images/payment-methods/' . $locale . '/' . rawurlencode( $image_name );
		}

		return $this->get_payment_method_icon_url();
	}

	/**
	 * Get locale for icon (de, en, nl).
	 *
	 * @return string
	 */
	private function get_icon_locale() {
		$wp_locale = get_locale();
		$lang = substr( $wp_locale, 0, 2 );

		if ( in_array( $lang, self::SUPPORTED_LOCALES, true ) ) {
			return $lang;
		}

		return 'en';
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
