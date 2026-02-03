<?php
/**
 * Class Plugin
 *
 * @package Mondu
 */

namespace Mondu;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Exception;
use Mondu\Admin\Settings;
use Mondu\Config\PaymentMethodsConfig;
use Mondu\Mondu\Blocks\MonduBlocksSupport;
use Mondu\Mondu\Controllers\OrdersController;
use Mondu\Mondu\Controllers\WebhooksController;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Mondu\Support\Helper;
use WC_Customer;
use WC_Order;
use WP_Error;

/**
 * Class Plugin
 *
 * @package Mondu
 */
class Plugin {

	/**
	 * Order ID Key
	 */
	const ORDER_ID_KEY = '_mondu_order_id';

	/**
	 * Invoice ID Key
	 */
	const INVOICE_ID_KEY = '_mondu_invoice_id';

	/**
	 * Option Name
	 */
	const OPTION_NAME = 'mondu_account';

	/**
	 * Payment method key => gateway id. Use get_payment_methods().
	 *
	 * @return array<string, string>
	 */
	public static function get_payment_methods() {
		return PaymentMethodsConfig::get_ids();
	}

	/**
	 * Global Settings
	 *
	 * @var mixed
	 */
	protected $global_settings;

	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	public function __construct() {
		$this->global_settings = get_option( self::OPTION_NAME );

		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		if ( !class_exists( 'WooCommerce' ) ) {
			# This file is required to deactivate the plugin.
			# WordPress is not fully loaded when we are activating the plugin.
			include_once ABSPATH . '/wp-admin/includes/plugin.php';

			if ( is_multisite() ) {
				add_action( 'network_admin_notices', [ $this, 'woocommerce_notice' ] );
			} else {
				add_action( 'admin_notices', [ $this, 'woocommerce_notice' ] );
			}
			deactivate_plugins( MONDU_PLUGIN_BASENAME );
			return;
		}

		self::ensure_mondu_gateway_title_defaults();

		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();

			$order = new Admin\Order();
			$order->init();

			add_action( 'admin_head', [ $this, 'add_admin_payment_icon_styles' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_gateway_titles_script' ] );
		}

		/*
		 * Load translations
		 */
		add_action( 'init', [ $this, 'load_textdomain' ] );

		add_filter( 'mondu_order_locale', [ $this, 'get_mondu_order_locale' ], 1 );

		/*
		 * Adds the mondu gateway to the list of gateways
		 * (And remove it again if we're not in Germany)
		 */
		foreach ( PaymentMethodsConfig::get_gateway_classes() as $gateway_class ) {
			add_filter( 'woocommerce_payment_gateways', [ $gateway_class, 'add' ] );
		}
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_gateway_if_country_unavailable' ] );

		/*
		 * Show action links on the plugin screen.
		 */
		add_filter('plugin_action_links_' . MONDU_PLUGIN_BASENAME, [ $this, 'add_action_links' ]);
		/*
		 * Adds meta information about the Mondu Plugin
		 */
		add_filter('plugin_row_meta', [ $this, 'add_row_meta' ], 10, 2);

		/*
		 * These deal with order and status changes
		 */
		add_action( 'woocommerce_order_status_changed', [ $this->mondu_request_wrapper, 'order_status_changed' ], 10, 3 );
		add_action( 'woocommerce_before_order_object_save', [ $this->mondu_request_wrapper, 'update_order_if_changed_some_fields' ] );
		add_action( 'woocommerce_blocks_loaded', function () {
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				add_action(
					'woocommerce_blocks_payment_method_type_registration',
					function ( PaymentMethodRegistry $payment_method_registry ) {
						$payment_method_registry->register( new MonduBlocksSupport() );
					}
				);
			}
		});

		add_action('rest_api_init', function () {
			$orders = new OrdersController();
			$orders->register_routes();
			$webhooks = new WebhooksController();
			$webhooks->register_routes();
		});

		/*
		 * Validates required fields
		 */
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_required_fields' ], 10, 2 );

		/*
		 * Does not allow to change address
		 */
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'change_address_warning' ] );

		/*
		 * These methods add the Mondu invoice's info to a WCPDF Invoice
		 */
		if ( class_exists('WPO_WCPDF') ) {
			add_action( 'wpo_wcpdf_after_order_details', [ $this, 'wcpdf_add_mondu_payment_info_to_pdf' ], 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', [ $this, 'wcpdf_add_status_to_invoice_when_order_is_canceled' ], 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', [ $this, 'wcpdf_add_paid_to_invoice_when_invoice_is_paid' ], 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', [ $this, 'wcpdf_add_status_to_invoice_when_invoice_is_canceled' ], 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_after_document_data', [ $this, 'wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid' ], 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_after_document_data', [ $this, 'wcpdf_add_status_to_invoice_admin_when_invoice_is_canceled' ], 10, 2 );
			add_action( 'wpo_wcpdf_reload_text_domains', [ $this, 'wcpdf_add_mondu_payment_language_switch' ] );
		}

		if ( class_exists('BM') ) {
			add_filter( 'bm_filter_price', '__return_false' );
		}
	}

	/**
	 * Ensure Mondu gateways have per-locale title defaults saved in options.
	 *
	 * This is idempotent: it only fills missing/empty values and never overwrites
	 * admin-defined titles.
	 *
	 * @return void
	 */
	public static function ensure_mondu_gateway_title_defaults() {
		foreach ( PaymentMethodsConfig::get_gateway_ids() as $gid ) {
			self::migrate_gateway_title_translations( $gid );
			self::migrate_gateway_description_translations( $gid );
		}

		$version_key = '_mondu_gateway_title_defaults_version';
		$done_for    = get_option( $version_key );

		if ( defined( 'MONDU_PLUGIN_VERSION' ) && $done_for === MONDU_PLUGIN_VERSION ) {
			return;
		}

		self::set_mondu_gateway_title_defaults();

		if ( defined( 'MONDU_PLUGIN_VERSION' ) ) {
			update_option( $version_key, MONDU_PLUGIN_VERSION, false );
		}
	}

	/**
	 * Fill per-locale title defaults into Woo gateway settings.
	 *
	 * @return void
	 */
	public static function set_mondu_gateway_title_defaults() {
		foreach ( PaymentMethodsConfig::get_all() as $config ) {
			self::fill_gateway_title_defaults( $config['id'], $config['default_titles'] );
		}
	}

	/**
	 * Migrate title_en/de/fr/nl to title_translations when present.
	 *
	 * @param string $gateway_id
	 * @return void
	 */
	private static function migrate_gateway_title_translations( $gateway_id ) {
		self::migrate_legacy_translations( $gateway_id, 'title' );
	}

	/**
	 * Migrate description_en/de/fr/nl to description_translations when present.
	 *
	 * @param string $gateway_id
	 * @return void
	 */
	private static function migrate_gateway_description_translations( $gateway_id ) {
		self::migrate_legacy_translations( $gateway_id, 'description' );
	}

	/**
	 * Migrate legacy {field}_en/de/fr/nl to {field}_translations.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param string $field      Field name ('title' or 'description').
	 * @return void
	 */
	private static function migrate_legacy_translations( $gateway_id, $field ) {
		$option_key = 'woocommerce_' . $gateway_id . '_settings';
		$settings   = get_option( $option_key, [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$translations_key = $field . '_translations';
		$rows = isset( $settings[ $translations_key ] ) && is_array( $settings[ $translations_key ] ) ? $settings[ $translations_key ] : [];
		if ( ! empty( $rows ) ) {
			return;
		}

		$legacy = [];
		foreach ( [ 'en', 'de', 'fr', 'nl' ] as $lang ) {
			$k = $field . '_' . $lang;
			if ( isset( $settings[ $k ] ) && $settings[ $k ] !== '' ) {
				$legacy[] = [ 'lang' => $lang, $field => (string) $settings[ $k ] ];
			}
		}

		if ( empty( $legacy ) ) {
			return;
		}

		$settings[ $translations_key ] = $legacy;
		foreach ( [ 'en', 'de', 'fr', 'nl' ] as $lang ) {
			unset( $settings[ $field . '_' . $lang ] );
		}
		update_option( $option_key, $settings, false );
	}

	/**
	 * @param string $gateway_id
	 * @param array{en:string,de:string,fr:string,nl:string} $titles
	 * @return void
	 */
	private static function fill_gateway_title_defaults( $gateway_id, array $titles ) {
		$option_key = 'woocommerce_' . $gateway_id . '_settings';
		$settings   = get_option( $option_key, [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$rows = isset( $settings['title_translations'] ) && is_array( $settings['title_translations'] ) ? $settings['title_translations'] : [];
		if ( ! empty( $rows ) ) {
			return;
		}

		$default_rows = [];
		foreach ( [ 'en', 'de', 'fr', 'nl' ] as $lang ) {
			$default_rows[] = [
				'lang'  => $lang,
				'title' => isset( $titles[ $lang ] ) ? $titles[ $lang ] : '',
			];
		}

		$settings['title_translations'] = $default_rows;
		update_option( $option_key, $settings, false );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_textdomain() {
		$plugin_rel_path = dirname( plugin_basename( __FILE__ ) ) . '/../../languages/';
		load_plugin_textdomain( 'mondu', false, $plugin_rel_path );
	}

	/**
	 * Enqueue script for repeatable title-translations on gateway settings.
	 */
	public function enqueue_gateway_titles_script() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$sec  = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( $page !== 'wc-settings' || $tab !== 'checkout' || $sec === '' ) {
			return;
		}
		if ( ! in_array( $sec, PaymentMethodsConfig::get_gateway_ids(), true ) ) {
			return;
		}

		$handle = 'mondu-admin-gateway-titles';
		$src    = MONDU_PUBLIC_PATH . 'assets/src/js/admin-gateway-titles.js';
		$deps   = [];
		$ver    = defined( 'MONDU_PLUGIN_VERSION' ) ? MONDU_PLUGIN_VERSION : '1.0.0';
		wp_enqueue_script( $handle, $src, $deps, $ver, true );
	}

	/**
	 * Add admin styles for payment method icons.
	 */
	public function add_admin_payment_icon_styles() {
		?>
		<style>
			.wc_gateways img[src*="payment-methods"],
			.woocommerce table.wc_gateways img[src*="payment-methods"],
			table.wc_gateways td img[src*="payment-methods"],
			img.payment-icon[src*="payment-methods"] {
				max-height: 50px !important;
				height: 50px !important;
				width: auto !important;
			}
		</style>
		<?php
	}

	/**
	 * Check if the order has Mondu data
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	public static function order_has_mondu( WC_Order $order ) {
		if ( ! in_array( $order->get_payment_method(), PaymentMethodsConfig::get_gateway_ids(), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Change address warning
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	public function change_address_warning( WC_Order $order ) {
		if ( !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$order_data   = $payment_info->get_order_data();
		if ( $order_data && 'declined' === $order_data['state'] ) {
			return;
		}

		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				document.querySelectorAll('a.edit_address').forEach(function(el) { el.remove(); });
			});
		</script>
		<p><?php esc_html_e( 'Since this order will be paid via Mondu you will not be able to change the addresses.', 'mondu' ); ?></p>
		<?php
	}

    /**
     * Remove gateway if country unavailable
     *
     * @param array $available_gateways
     * @return array
     * @throws Exception
     */
	public function remove_gateway_if_country_unavailable( $available_gateways ) {
		if ( is_admin() || !is_checkout() ) {
			return $available_gateways;
		}

		$mondu_payments = $this->mondu_request_wrapper->get_merchant_payment_methods();

		$payment_methods = self::get_payment_methods();
		foreach ( $payment_methods as $payment_method => $woo_payment_method ) {
			$customer = $this->get_wc_customer();
			if ( ! in_array( $payment_method, $mondu_payments, true ) ) {
				if ( isset( $available_gateways[ $woo_payment_method ] ) ) {
					unset( $available_gateways[ $woo_payment_method ] );
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	public static function add_action_links( $links ) {
		$action_links = [
			'settings' => '<a href="' . admin_url( 'admin.php?page=mondu-settings-account' ) . '" aria-label="' . esc_attr__( 'View Mondu settings', 'mondu' ) . '">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
		];

		return array_merge( $action_links, $links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file   Plugin Base file.
	 *
	 * @return array
	 */
	public static function add_row_meta( $links, $file ) {
		if ( MONDU_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$row_meta = [
			'docs'  => '<a target="_blank" href="' . esc_url( 'https://docs.mondu.ai/docs/woocommerce-installation-guide' ) . '" aria-label="' . esc_attr__('View Mondu documentation', 'mondu') . '">' . esc_html__( 'Docs', 'mondu' ) . '</a>',
			'intro' => '<a target="_blank" href="' . esc_url( esc_attr__( 'https://mondu.ai/introduction-to-paying-with-mondu', 'mondu' ) ) . '" aria-label="' . esc_attr__( 'View introduction to paying with Mondu', 'mondu' ) . '">' . esc_html__( 'Mondu introduction', 'mondu' ) . '</a>',
			'faq'   => '<a target="_blank" href="' . esc_url( esc_attr__( 'https://mondu.ai/faq', 'mondu' ) ) . '" aria-label="' . esc_attr__('View FAQ', 'mondu') . '">' . esc_html__( 'FAQ', 'mondu' ) . '</a>',
		];

		return array_merge( $links, $row_meta );
	}

	/**
	 * Validate Required fields
	 *
	 * @param array $fields
	 * @param WP_Error $errors
	 */
	public function validate_required_fields( array $fields, WP_Error $errors ) {
		if ( ! in_array( $fields['payment_method'], PaymentMethodsConfig::get_gateway_ids(), true ) ) {
			return;
		}

		if ( !Helper::not_null_or_empty($fields['billing_company']) && !Helper::not_null_or_empty($fields['shipping_company']) ) {
			/* translators: %s: Company */
			$errors->add('validation', sprintf(__('%s is a required field for Mondu payments.', 'mondu'), '<strong>' . __('Company', 'mondu') . '</strong>'));
		}
	}

	/**
	 * WCPDF Mondu template type
	 *
	 * @param $template_type
	 * @return bool
	 */
	public function wcpdf_mondu_template_type( $template_type ) {

		/**
		 * Extend allowed templates
		 *
		 * @since 1.3.2
		 */
		$allowed_templates = apply_filters( 'mondu_wcpdf_template_type', [ 'invoice' ] );
		if ( in_array( $template_type, $allowed_templates, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * WCPDF add Mondu payment info
	 *
	 * @param $template_type
	 * @param $order
	 */
	public function wcpdf_add_mondu_payment_info_to_pdf( $template_type, $order ) {
		if ( !$this->wcpdf_mondu_template_type( $template_type ) || !$this->order_has_mondu( $order ) ) {
			return;
		}

		try {
			$payment_info = new PaymentInfo( $order->get_id() );
			$payment_info->get_mondu_wcpdf_section_html();
		} catch ( \Exception $e ) {
			Support\Helper::log([
				'message'  => 'Error adding Mondu payment info to PDF',
				'order_id' => $order->get_id(),
				'error'    => $e->getMessage(),
			], 'ERROR');
		}
	}

	/**
	 * WCPDF add status canceled
	 *
	 * @param $template_type
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_status_to_invoice_when_order_is_canceled( $template_type, $order ) {
		if ( !$this->wcpdf_mondu_template_type( $template_type ) || !$this->order_has_mondu( $order ) ) {
			return;
		}

		$payment_info = new PaymentInfo( $order->get_id() );
		$order_data   = $payment_info->get_order_data();

		if ( 'cancelled' === $order->get_status() || 'canceled' === $order_data['state'] ) {
			?>
				<tr class="order-status">
					<th><?php esc_html_e( 'Order state', 'mondu' ); ?>:</th>
					<td><?php esc_html_e( 'Canceled', 'mondu' ); ?></td>
				</tr>
			<?php
		}
	}

	/**
	 * WCPDF add status paid
	 *
	 * @param $template_type
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_paid_to_invoice_when_invoice_is_paid( $template_type, $order ) {
		if ( !$this->wcpdf_mondu_template_type( $template_type ) || !$this->order_has_mondu( $order ) ) {
			return;
		}

		$payment_info = new PaymentInfo( $order->get_id() );
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && $invoice_data[0]['paid_out'] ) {
			?>
				<tr class="invoice-status">
					<th><?php esc_html_e( 'Mondu Invoice paid', 'mondu' ); ?>:</th>
					<td><?php esc_html_e( 'Yes', 'mondu' ); ?></td>
				</tr>
			<?php
		}
	}

	/**
	 * WCPDF add status canceled invoice
	 *
	 * @param $template_type
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_status_to_invoice_when_invoice_is_canceled( $template_type, $order ) {
		if ( !$this->wcpdf_mondu_template_type( $template_type ) || !$this->order_has_mondu( $order ) ) {
			return;
		}

		$payment_info = new PaymentInfo( $order->get_id() );
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && 'canceled' === $invoice_data[0]['state'] ) {
			?>
				<tr class="invoice-status">
					<th><?php esc_html_e( 'Mondu Invoice state', 'mondu' ); ?>:</th>
					<td><?php esc_html_e( 'Canceled', 'mondu' ); ?></td>
				</tr>
			<?php
		}
	}

	/**
	 * WCPDF add status paid invoice admin
	 *
	 * @param $document
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid( $document, $order ) {
		if ( $document->get_type() !== 'invoice' || !$this->order_has_mondu( $order ) ) {
			return;
		}

		$payment_info = new PaymentInfo( $order->get_id() );
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && $invoice_data[0]['paid_out'] ) {
			?>
				<div class="invoice-number">
					<p>
					<span><strong><?php esc_html_e( 'Mondu Invoice paid', 'mondu' ); ?>:</strong></span>
					<span><?php esc_html_e( 'Yes', 'mondu' ); ?></span>
					</p>
				</div>
			<?php
		}
	}

	/**
	 * WCPDF add status canceled invoice admin
	 *
	 * @param $document
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_status_to_invoice_admin_when_invoice_is_canceled( $document, $order ) {
		if ( $document->get_type() !== 'invoice' || !$this->order_has_mondu( $order ) ) {
			return;
		}

		$payment_info = new PaymentInfo( $order->get_id() );
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && 'canceled' === $invoice_data[0]['state'] ) {
			?>
				<div class="invoice-number">
					<p>
					<span><strong><?php esc_html_e( 'Mondu Invoice state', 'mondu' ); ?>:</strong></span>
					<span><?php esc_html_e( 'Canceled', 'mondu' ); ?></span>
					</p>
				</div>
			<?php
		}
	}

	/**
	 * WCPDF add Mondu payment language switch
	 *
	 * @param $locale
	 */
	public function wcpdf_add_mondu_payment_language_switch() {
		unload_textdomain( 'mondu' );
		$this->load_textdomain();
	}

	/**
	 * WooCommerce notice
	 */
	public function woocommerce_notice() {
		$class   = 'notice notice-error';
		$message = __( 'Mondu requires WooCommerce to be activated.', 'mondu' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Get the WC Customer object
	 *
	 * @return WC_Customer
	 * @throws Exception
	 */
	private function get_wc_customer() {
		return isset( WC()->customer ) ? WC()->customer : new WC_Customer( get_current_user_id() );
	}

	/**
	 * Get the Mondu order locale
	 *
	 * @return mixed
	 */
	public function get_mondu_order_locale() {
		/**
		 * WPML current language
		 *
		 * @since 1.3.2
		 */
		return apply_filters( 'wpml_current_language', get_locale() );
	}
}
