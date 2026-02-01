<?php
/**
 * Mondu Gateway class file.
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Config\PaymentMethodsConfig;
use Mondu\Config\TitleLanguagesConfig;
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

	private const SUPPORTED_LOCALES = [ 'de', 'en', 'nl' ];

	private const DEFAULT_CHECKOUT_ICON = 'invoice_white_rectangle.png';

	private const DEFAULT_ADMIN_ICON = 'Mondu_white_square.svg';

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
	 * Generate repeatable "language + title" rows HTML.
	 *
	 * @param string $key
	 * @param array  $data
	 * @return string
	 */
	public function generate_mondu_title_translations_html( $key, $data ) {
		$renderer = new GatewaySettingsRenderer( $this );
		return $renderer->generate_title_translations_html( $key, $data );
	}

	/**
	 * Generate repeatable "language + description" rows HTML.
	 *
	 * @param string $key
	 * @param array  $data
	 * @return string
	 */
	public function generate_mondu_description_translations_html( $key, $data ) {
		$renderer = new GatewaySettingsRenderer( $this );
		return $renderer->generate_description_translations_html( $key, $data );
	}

	/**
	 * Gateway title depending on current page locale.
	 *
	 * Uses title_translations (language + title rows). Fallback: en, then first.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->get_localized_value_from_translations( 'title_translations', 'title' );
	}

	/**
	 * Get localized description from gateway settings.
	 *
	 * Uses description_translations (language + description rows). Fallback: en, then first.
	 *
	 * @return string
	 */
	private function get_localized_description_from_settings() {
		return $this->get_localized_value_from_translations( 'description_translations', 'description' );
	}

	/**
	 * Get localized value from translations rows. Fallback: current lang, en, then first.
	 *
	 * @param string $option_key Option key (e.g. 'title_translations', 'description_translations').
	 * @param string $value_key  Row value key (e.g. 'title', 'description').
	 * @return string
	 */
	private function get_localized_value_from_translations( $option_key, $value_key ) {
		$lang   = $this->get_request_language();
		$rows   = $this->get_option( $option_key, [] );
		$rows   = is_array( $rows ) ? $rows : [];
		$by_lang = [];
		foreach ( $rows as $r ) {
			$l = isset( $r['lang'] ) ? $r['lang'] : '';
			$v = isset( $r[ $value_key ] ) ? trim( (string) $r[ $value_key ] ) : '';
			if ( $l !== '' ) {
				$by_lang[ $l ] = $v;
			}
		}
		if ( isset( $by_lang[ $lang ] ) && $by_lang[ $lang ] !== '' ) {
			return $by_lang[ $lang ];
		}
		if ( isset( $by_lang['en'] ) && $by_lang['en'] !== '' ) {
			return $by_lang['en'];
		}
		$first = reset( $by_lang );
		return $first !== false ? $first : '';
	}

	/**
	 * Determine current 2-letter language code.
	 *
	 * @return string
	 */
	private function get_request_language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		return strtolower( substr( (string) $locale, 0, 2 ) );
	}

	/**
	 * Save options; decode title_translations and description_translations JSON into arrays.
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		$opt_key  = 'woocommerce_' . $this->id . '_settings';
		$settings = get_option( $opt_key, [] );
		$settings = is_array( $settings ) ? $settings : [];

		$fields = [
			[ 'title_translations', 'title', 'sanitize_text_field' ],
			[ 'description_translations', 'description', 'sanitize_textarea_field' ],
		];
		foreach ( $fields as list( $opt_key_field, $store_key, $sanitizer ) ) {
			$field_key = $this->get_field_key( $opt_key_field );
			$raw       = isset( $_POST[ $field_key ] ) ? wp_unslash( $_POST[ $field_key ] ) : '';
			$decoded   = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$out = [];
			foreach ( $decoded as $row ) {
				$lang  = isset( $row['lang'] ) ? sanitize_text_field( $row['lang'] ) : '';
				$value = isset( $row['text'] ) ? call_user_func( $sanitizer, $row['text'] ) : '';
				if ( $lang !== '' ) {
					$out[] = [ 'lang' => $lang, $store_key => $value ];
				}
			}
			$settings[ $opt_key_field ] = $out;
		}

		update_option( $opt_key, $settings, false );
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
		$icons = PaymentMethodsConfig::get_icons_for_gateway( $this->id );
		$image_name = $icons ? $icons['checkout'] : self::DEFAULT_CHECKOUT_ICON;

		$locale     = $this->get_icon_locale();
		$icon_path  = MONDU_PLUGIN_PATH . '/assets/src/images/payment-methods/' . $locale . '/' . $image_name;

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
		$icons = PaymentMethodsConfig::get_icons_for_gateway( $this->id );
		$image_name = $icons ? $icons['admin'] : self::DEFAULT_ADMIN_ICON;

		$locale    = $this->get_icon_locale();
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
