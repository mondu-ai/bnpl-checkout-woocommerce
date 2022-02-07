<?php

namespace Billie;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Billie\Admin\Option\Account;
use Billie\Admin\Settings;
use Billie\Billie\Gateway;
use Billie\Billie\PaymentInfo;
use DateInterval;
use Exception;
use WC_DateTime;
use WC_Order;

class Plugin {
	const CALLBACK_SLUG = 'billie-callback';

	const ORDER_DATA_KEY = '_billieOrderData';
	const DURATION_KEY = '_billieDuration';
	const ORDER_ID_KEY = '_billieOrderId';
	const SESSION_ID_KEY = '_billieSessionId';
	const SHIP_ORDER_REQUEST_RESPONSE = '_billieShipOrderRequestResponse';

	/**
	 * @var array|bool|mixed|void
	 */
	protected $global_settings;

	public function __construct() {
		$this->global_settings = get_option( Account::OPTION_NAME );

        add_action('woocommerce_after_checkout_validation', function () {
            if ($_POST['confirm-order-flag'] === "1") {
                wc_add_notice(__('error_confirmation', 'billie'), 'error');
            }
        });
	}

	public function init() {

		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();

			$order = new Admin\Order();
			$order->init();
		}

		/*
		 * Adds the billie gateway to the list of gateways
		 * (And remove it again if we're not in Germany)
		 */
		add_filter( 'woocommerce_payment_gateways', [ Gateway::class, 'add' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_billie_outside_germany' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_billie_if_declined' ] );

		/*
		 * Adds the billie javascript to the list of WordPress javascripts
		 */
		add_action( 'wp_head', [ $this, 'add_billie_js' ] );

		$plugin_rel_path = dirname( plugin_basename( __FILE__ ) ) . '/../../lang/';
		load_plugin_textdomain( 'billie', false, $plugin_rel_path );

		add_action( 'woocommerce_order_status_changed', [ new Gateway(), 'order_status_changed' ], 10, 3 );

		add_action( 'woocommerce_order_refunded', [ new Gateway(), 'order_refunded' ], 10, 2 );

		/*
		 * This one adds the payment information to a Germanized Pro Invoice
		 */
		add_filter( 'woocommerce_gzdp_pdf_static_content', [
			$this,
			'add_billie_payment_info_to_germanized_pdf'
		], 10, 3 );

		/*
		 * This one adds the payment information to a WCPDF Invoice
		 */
		add_action( 'wpo_wcpdf_after_order_details', [
			$this,
			'add_billie_payment_info_to_wcpdf_pdf'
		], 10, 2 );

		/*
		 * This one adds the context to the normal woocommerce log files
		 */
		add_filter( 'woocommerce_format_log_entry', static function ( $entry, $log_data ) {
			if ( is_array( $log_data ) && isset( $log_data['context'] ) ) {
				$entry .= ' ' . json_encode( $log_data['context'] );
			}

			return $entry;
		}, 0, 2 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'change_address_warning' ], 10, 1 );

	}

	public function change_address_warning( WC_Order $order ) {
		if ( $order->get_payment_method() !== 'billie' ) {
			return;
		}
		wc_enqueue_js( "
        jQuery(document).ready(function() {
            jQuery( 'a.edit_address' ).remove();
        });
    " );
		echo '<p>' . __( 'Since this order will be paid via Billie you won\'t be able to change the addresses.', 'billie' ) . '</p>';
	}

	public function add_billie_js() {
		if ( is_checkout() ) {
			if ( $this->is_sandbox() ) {
				require_once( BILLIE_VIEW_PATH . '/checkout/billie-checkout-sandbox.html' );
			} else {
				require_once( BILLIE_VIEW_PATH . '/checkout/billie-checkout.html' );
			}
		}
	}

	/**
	 * @return bool
	 */
	private function is_sandbox() {
		$isSandbox = true;
		if (
			is_array( $this->global_settings ) &&
			isset( $this->global_settings['field_sandbox_or_production'] ) &&
			$this->global_settings['field_sandbox_or_production'] === 'production'
		) {
			$isSandbox = false;
		}

		return $isSandbox;
	}

	public function remove_billie_outside_germany( $available_gateways ) {
		if ( is_admin() ) {
			return $available_gateways;
		}
		if ( isset( $available_gateways['billie'] ) && WC()->customer->get_billing_country() !== 'DE' ) {
			unset( $available_gateways['billie'] );
		}

		return $available_gateways;
	}

	public function remove_billie_if_declined( $available_gateways ) {
		if ( is_admin() ) {
			return $available_gateways;
		}

		if ( ! isset( $available_gateways['billie'] ) ) {
			return $available_gateways;
		}

		$declined_reason = trim( (string) WC()->session->get( 'billie_decline_reason' ) );

		if ( $declined_reason === '' ) {
			return $available_gateways;
		}

		$reasons_to_remove = [
			'risk_policy',
			'debtor_limit_exceeded'
		];

		if ( in_array( $declined_reason, $reasons_to_remove ) ) {
			unset( $available_gateways['billie'] );
		}

		return $available_gateways;
	}


	public static function get_callback_url( $type = 'transaction' ) {
		$url = get_site_url( null, '/' );
		$url = add_query_arg( self::CALLBACK_SLUG, 'true', $url );
		if ( $type !== 'transaction' ) {
			$url = add_query_arg( 'type', $type, $url );
		}

		return $url;
	}

	public function add_callback_url() {
		add_rewrite_rule( '^' . self::CALLBACK_SLUG . '/?$', 'index.php?' . self::CALLBACK_SLUG . '=true', 'top' );
		add_filter( 'query_vars', [ $this, 'add_rewrite_var' ] );
		add_action( 'template_redirect', [ $this, 'catch_billie_callback' ] );
	}

	public function add_rewrite_var( $vars ) {
		$vars[] = self::CALLBACK_SLUG;

		return $vars;
	}

	public function catch_billie_callback() {
		if ( get_query_var( self::CALLBACK_SLUG ) ) {
			if ( $this->is_callback_billie_success() ) {
				return $this->process_billie_success();
			}
			if ( $this->is_callback_billie_error() ) {
				return $this->process_billie_error();
			}
		}

		return null;
	}

	/**
	 * @param string $html
	 * @param $invoice
	 * @param string $where
	 *
	 * @return string
	 * @throws Exception
	 */
	public function add_billie_payment_info_to_germanized_pdf( $html, $invoice, $where ) {
		if ( $where !== 'after_table' ) {
			return $html;
		}

		if ( ! is_object( $invoice ) ) {
			return $html;
		}

		if ( ! isset( $invoice->post ) || ! $invoice->post instanceof \WP_Post ) {
			return $html;
		}

		$html = sprintf( "%s\n<br>\n%s", $html, $this->get_billie_payment_html( $invoice->get_order_number() ) );

		return $html;
	}

	/**
	 * @param $type
	 * @param Order $order
	 *
	 * @throws Exception
	 */
	public function add_billie_payment_info_to_wcpdf_pdf( $type, Order $order ) {
		if ( $type !== 'invoice' ) {
			return;
		}

		$this->add_billie_payment_info( $order->get_id() );
	}

	/**
	 * @param $order_id
	 *
	 * @return false|string
	 * @throws Exception
	 */
	public function get_billie_payment_html( $order_id ) {
		return PaymentInfo::get_billie_payment_html( $order_id );
	}

	/**
	 * @param $order_id
	 *
	 * @throws Exception
	 */
	public function add_billie_payment_info( $order_id ) {

		echo $this->get_billie_payment_html( $order_id );
	}

	/**
	 * @return bool
	 */
	private function is_callback_billie_success() {
		return isset( $_GET['type'] ) && $_GET['type'] === 'ajax-billie-success';
	}


	/**
	 * @return bool
	 */
	private function is_callback_billie_error() {
		return isset( $_GET['type'] ) && $_GET['type'] === 'ajax-billie-error';
	}

	private function process_billie_success() {
		$gateway = new Gateway();

		return $gateway->process_success( $_POST );
	}

	private function get_bank( $bic ) {
		return PaymentInfo::get_bank( $bic );
	}

	private function process_billie_error() {
		$gateway = new Gateway();

		return $gateway->process_error( $_POST );
	}
}
