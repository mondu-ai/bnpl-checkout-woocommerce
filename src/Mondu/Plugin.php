<?php

namespace Mondu;

use Mondu\Admin\Settings;
use Mondu\Mondu\GatewayInvoice;
use Mondu\Mondu\GatewayDirectDebit;
use Mondu\Mondu\GatewayInstallment;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Controllers\OrdersController;
use Mondu\Mondu\Controllers\WebhooksController;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Mondu\Support\Helper;
use Exception;
use WC_Customer;
use WP_Error;
use WC_Order;

class Plugin {
	const ORDER_DATA_KEY      = '_mondu_order_data';
	const ORDER_ID_KEY        = '_mondu_order_id';
	const INVOICE_ID_KEY      = '_mondu_invoice_id';
	const FAILURE_REASON_KEY  = '_mondu_failure_reason';
	const OPTION_NAME         = 'mondu_account';
	const PAYMENT_METHODS     = [
		'invoice'      => 'mondu_invoice',
		'direct_debit' => 'mondu_direct_debit',
		'installment'  => 'mondu_installment',
	];
	const AVAILABLE_COUNTRIES = [ 'DE', 'AT', 'NL', 'FR', 'BE', 'GB' ];

	protected $global_settings;

	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	public function __construct() {
		$this->global_settings = get_option(self::OPTION_NAME);

		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	public function init() {
		if ( !class_exists('WooCommerce') ) {
			# This file is required to deactivate the plugin.
			# WordPress is not fully loaded when we are activating the plugin.
			include_once ABSPATH . '/wp-admin/includes/plugin.php';

			if ( is_multisite() ) {
				add_action('network_admin_notices', array( $this, 'woocommerce_notice' ));
			} else {
				add_action('admin_notices', array( $this, 'woocommerce_notice' ));
			}
			deactivate_plugins(MONDU_PLUGIN_BASENAME);
			return;
		}

		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();

			$order = new Admin\Order();
			$order->init();
		}

		/*
		 * Load translations
		 */
		add_action('init', [ $this, 'load_textdomain' ]);

		add_filter('mondu_order_locale', [ $this, 'get_mondu_order_locale' ], 1);

		/*
		 * Adds the mondu gateway to the list of gateways
		 * (And remove it again if we're not in Germany)
		 */
		add_filter('woocommerce_payment_gateways', [ GatewayInvoice::class, 'add' ]);
		add_filter('woocommerce_payment_gateways', [ GatewayDirectDebit::class, 'add' ]);
		add_filter('woocommerce_payment_gateways', [ GatewayInstallment::class, 'add' ]);
		add_filter('woocommerce_available_payment_gateways', [ $this, 'remove_mondu_outside_germany' ]);

		/*
		 * Show action links on the plugin screen.
		 */
		add_filter('plugin_action_links_' . MONDU_PLUGIN_BASENAME, [ $this, 'add_action_links' ]);
		/*
		 * Adds meta information about the Mondu Plugin
		 */
		add_filter('plugin_row_meta', [ $this, 'add_row_meta' ], 10, 2);

		/*
		 * Adds the mondu javascript to the list of WordPress javascripts
		 */
		add_action('wp_enqueue_scripts', [ $this, 'add_mondu_scripts' ]);
		/*
		 * Adds the mondu HTML
		 */
		add_action('wp_head', [ $this, 'add_mondu_html' ]);

		/*
		 * These deal with order and status changes
		 */
		add_action('woocommerce_order_status_changed', [ $this->mondu_request_wrapper, 'order_status_changed' ], 10, 3);
		add_action('woocommerce_before_order_object_save', [ $this->mondu_request_wrapper, 'update_order_if_changed_some_fields' ], 10, 1);
		add_action('woocommerce_order_refunded', [ $this->mondu_request_wrapper, 'order_refunded' ], 10, 2);

		add_action('rest_api_init', function () {
			$orders = new OrdersController();
			$orders->register_routes();
			$webhooks = new WebhooksController();
			$webhooks->register_routes();
		});

		add_action('woocommerce_checkout_order_processed', function( $order_id ) {
			$mondu_order_id = WC()->session->get('mondu_order_id');

			update_post_meta($order_id, Plugin::ORDER_ID_KEY, $mondu_order_id);
		}, 10, 3);

		/*
		 * Validates required fields
		 */
		add_action('woocommerce_after_checkout_validation', [ $this, 'validate_required_fields' ], 10, 2);

		/*
		 * Does not allow to change address
		 */
		add_action('woocommerce_admin_order_data_after_billing_address', [ $this, 'change_address_warning' ], 10, 1);

		/*
		 * These methods add the Mondu invoice's info to a WCPDF Invoice
		 */
		if ( class_exists('WPO_WCPDF') ) {
			add_action('wpo_wcpdf_after_order_details', [ $this, 'wcpdf_add_mondu_payment_info_to_pdf' ], 10, 2);
			add_action('wpo_wcpdf_after_order_data', [ $this, 'wcpdf_add_status_to_invoice_when_order_is_canceled' ], 10, 2);
			add_action('wpo_wcpdf_after_order_data', [ $this, 'wcpdf_add_paid_to_invoice_when_invoice_is_paid' ], 10, 2);
			add_action('wpo_wcpdf_after_order_data', [ $this, 'wcpdf_add_status_to_invoice_when_invoice_is_canceled' ], 10, 2);
			add_action('wpo_wcpdf_meta_box_after_document_data', [ $this, 'wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid' ], 10, 2);
			add_action('wpo_wcpdf_meta_box_after_document_data', [ $this, 'wcpdf_add_status_to_invoice_admin_when_invoice_is_canceled' ], 10, 2);
			add_action('wpo_wcpdf_reload_text_domains', [ $this, 'wcpdf_add_mondu_payment_language_switch' ], 10, 1);
		}
	}

	public function load_textdomain() {
		$plugin_rel_path = dirname(plugin_basename(__FILE__)) . '/../../languages/';
		load_plugin_textdomain('mondu', false, $plugin_rel_path);
	}

	public static function order_has_mondu( WC_Order $order ) {
		if ( !in_array($order->get_payment_method(), self::PAYMENT_METHODS, true) ) {
			return false;
		}

		// Check if we actually have a payment as well
		$mondu_order_id = get_post_meta($order->get_id(), self::ORDER_ID_KEY, true);
		if ( !$mondu_order_id ) {
			return false;
		}

		return true;
	}

	public function change_address_warning( WC_Order $order ) {
		if ( !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$order_data   = $payment_info->get_order_data();
		if ( $order_data && 'declined' === $order_data['state'] ) {
			return;
		}

		wc_enqueue_js("
            jQuery(document).ready(function() {
                jQuery('a.edit_address').remove();
            });
        ");
		echo '<p>' . esc_html__('Since this order will be paid via Mondu you will not be able to change the addresses.', 'mondu') . '</p>';
	}

	public function add_mondu_scripts() {
		if ( is_checkout() ) {
			if ( $this->is_sandbox() ) {
				wp_enqueue_script( 'mondu', 'https://checkout.demo.mondu.ai/widget.js', null, '1.0.0', true );
			} else {
				wp_enqueue_script( 'mondu', 'https://checkout.mondu.ai/widget.js', null, '1.0.0', true );
			}
		}
	}

	public function add_mondu_html() {
		if ( is_checkout() ) {
			require_once MONDU_VIEW_PATH . '/checkout/mondu-checkout.html';
		}
	}

	public function remove_mondu_outside_germany( $available_gateways ) {
		if ( is_admin() || !is_checkout() ) {
			return $available_gateways;
		}

		$mondu_payments = $this->mondu_request_wrapper->get_merchant_payment_methods();

		foreach ( self::PAYMENT_METHODS as $payment_method => $woo_payment_method ) {
			$customer = $this->get_wc_customer();
			if ( !$this->is_country_available($customer->get_billing_country())
				|| !in_array($payment_method, $mondu_payments, true)
			) {
				if ( isset($available_gateways[ self::PAYMENT_METHODS[ $payment_method ] ]) ) {
					unset($available_gateways[ self::PAYMENT_METHODS[ $payment_method ] ]);
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
		$action_links = array(
			'settings' => '<a href="' . admin_url('admin.php?page=mondu-settings-account') . '" aria-label="' . esc_attr__('View Mondu settings', 'mondu') . '">' . esc_html__('Settings', 'woocommerce') . '</a>',
		);

		return array_merge($action_links, $links);
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
			'docs'  => '<a target="_blank" href="' . esc_url('https://docs.mondu.ai/docs/woocommerce-installation-guide') . '" aria-label="' . esc_attr__('View Mondu documentation', 'mondu') . '">' . esc_html__('Docs', 'mondu') . '</a>',
			'intro' => '<a target="_blank" href="' . esc_url(esc_attr__('https://mondu.ai/introduction-to-paying-with-mondu', 'mondu')) . '" aria-label="' . esc_attr__('View introduction to paying with Mondu', 'mondu') . '">' . esc_html__('Mondu introduction', 'mondu') . '</a>',
			'faq'   => '<a target="_blank" href="' . esc_url(esc_attr__('https://mondu.ai/faq', 'mondu')) . '" aria-label="' . esc_attr__('View FAQ', 'mondu') . '">' . esc_html__('FAQ', 'mondu') . '</a>',
		];

		return array_merge($links, $row_meta);
	}

	/**
	 * Validate Required fields
	 *
	 * @param array $fields
	 * @param WP_Error $errors
	 */
	public function validate_required_fields( array $fields, WP_Error $errors ) {
		if ( !in_array($fields['payment_method'], self::PAYMENT_METHODS, true) ) {
			return;
		}

		if ( !Helper::not_null_or_empty($fields['billing_company']) ) {
			/* translators: %s: Company */
			$errors->add('validation', sprintf(__('%s is a required field for Mondu payments.', 'mondu'), '<strong>' . __('Company', 'mondu') . '</strong>'));
		}

		if ( !$this->is_country_available($fields['billing_country']) ) {
			/* translators: %s: Billing country */
			$errors->add('validation', sprintf(__('%s not available for Mondu Payments.', 'mondu'), '<strong>' . __('Billing country', 'mondu') . '</strong>'));
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
		$allowed_templates = apply_filters('mondu_wcpdf_template_type', [ 'invoice' ]);
		if ( in_array($template_type, $allowed_templates, true) ) {
			return true;
		}

		return false;
	}

	/**
	 * WCPDF add Mondu payment info
	 *
	 * @param $template_type
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_mondu_payment_info_to_pdf( $template_type, $order ) {
		if ( !$this->wcpdf_mondu_template_type($template_type) || !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		echo esc_html($payment_info->get_mondu_wcpdf_section_html(true));
	}

	/**
	 * WCPDF add status canceled
	 *
	 * @param $template_type
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_status_to_invoice_when_order_is_canceled( $template_type, $order ) {
		if ( !$this->wcpdf_mondu_template_type($template_type) || !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$order_data   = $payment_info->get_order_data();

		if ( 'cancelled' === $order->get_status() || 'canceled' === $order_data['state'] ) {
			?>
				<tr class="order-status">
					<th><?php esc_html_e('Order state', 'mondu'); ?>:</th>
					<td><?php esc_html_e('Canceled', 'mondu'); ?></td>
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
		if ( !$this->wcpdf_mondu_template_type($template_type) || !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && $invoice_data[0]['paid_out'] ) {
			?>
				<tr class="invoice-status">
					<th><?php esc_html_e('Mondu Invoice paid', 'mondu'); ?>:</th>
					<td><?php esc_html_e('Yes', 'mondu'); ?></td>
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
		if ( !$this->wcpdf_mondu_template_type($template_type) || !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && 'canceled' === $invoice_data[0]['state'] ) {
			?>
				<tr class="invoice-status">
					<th><?php esc_html_e('Mondu Invoice state', 'mondu'); ?>:</th>
					<td><?php esc_html_e('Canceled', 'mondu'); ?></td>
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
		if ( $document->get_type() !== 'invoice' || !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && $invoice_data[0]['paid_out'] ) {
			?>
				<div class="invoice-number">
					<p>
					<span><strong><?php esc_html_e('Mondu Invoice paid', 'mondu'); ?>:</strong></span>
					<span><?php esc_html_e('Yes', 'mondu'); ?></span>
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
		if ( $document->get_type() !== 'invoice' || !$this->order_has_mondu($order) ) {
			return;
		}

		$payment_info = new PaymentInfo($order->get_id());
		$invoice_data = $payment_info->get_invoices_data();

		if ( $invoice_data && 'canceled' === $invoice_data[0]['state'] ) {
			?>
				<div class="invoice-number">
					<p>
					<span><strong><?php esc_html_e('Mondu Invoice state', 'mondu'); ?>:</strong></span>
					<span><?php esc_html_e('Canceled', 'mondu'); ?></span>
					</p>
				</div>
			<?php
		}
	}

	public function wcpdf_add_mondu_payment_language_switch( $locale ) {
		unload_textdomain('mondu');
		$this->load_textdomain();
	}

	public function woocommerce_notice() {
		$class   = 'notice notice-error';
		$message = __('Mondu requires WooCommerce to be activated.', 'mondu');

		printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
	}

	private function is_sandbox() {
		$sandbox_env = true;
		if ( is_array($this->global_settings)
			&& isset($this->global_settings['field_sandbox_or_production'])
			&& 'production' === $this->global_settings['field_sandbox_or_production']
		) {
			$sandbox_env = false;
		}

		return $sandbox_env;
	}

	private function is_country_available( $country ) {
		return in_array($country, self::AVAILABLE_COUNTRIES, true);
	}

	private function get_wc_customer() {
		return isset(WC()->customer) ? WC()->customer : new WC_Customer(get_current_user_id());
	}

	public function get_mondu_order_locale() {
		/**
		 * WPML current language
		 *
		 * @since 1.3.2
		 */
		return apply_filters('wpml_current_language', get_locale());
	}
}
