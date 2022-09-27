<?php

namespace Mondu;

use Mondu\Admin\Settings;
use Mondu\Mondu\Gateway;
use Mondu\Mondu\GatewayDirectDebit;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Controllers\OrdersController;
use Mondu\Mondu\Controllers\WebhooksController;
use Mondu\Mondu\Presenters\PaymentInfo;
use Exception;
use WC_Customer;
use WC_Order;

class Plugin {
  const ADJUST_ORDER_TRIGGERED_KEY = '_mondu_adjust_order_triggered';
  const ORDER_DATA_KEY = '_mondu_order_data';
  const ORDER_ID_KEY = '_mondu_order_id';
  const INVOICE_ID_KEY = '_mondu_invoice_id';
  const FAILURE_REASON_KEY = '_mondu_failure_reason';
  const INVOICE_PAID_KEY = '_mondu_invoice_paid';
  const INVOICE_CANCELED_KEY = '_mondu_invoice_canceled';
  const SHIP_ORDER_REQUEST_RESPONSE = '_mondu_ship_order_request_response';

  const OPTION_NAME = 'mondu_account';

  const PAYMENT_METHODS = [
    'invoice' => 'mondu_invoice',
    'direct_debit' => 'mondu_direct_debit',
  ];

  /**
   * @var array|bool|mixed|void
   */
  protected $global_settings;
  /**
   * @var MonduRequestWrapper
   */
  private $mondu_request_wrapper;

  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);

    $this->mondu_request_wrapper = new MonduRequestWrapper();

    # This is for trigger the open checkout plugin
    add_action('woocommerce_after_checkout_validation', function () {
      if ($_POST['confirm-order-flag'] === '1') {
        wc_add_notice(__('Validation checkout error!', 'mondu'), 'error');
      }
    });
  }

  public function init() {
    if (is_admin()) {
      $settings = new Settings();
      $settings->init();

      $order = new Admin\Order();
      $order->init();
    }

    /*
     * Adds the mondu gateway to the list of gateways
     * (And remove it again if we're not in Germany)
     */
    add_filter('woocommerce_payment_gateways', [Gateway::class, 'add']);
    add_filter('woocommerce_payment_gateways', [GatewayDirectDebit::class, 'add']);
    add_filter('woocommerce_available_payment_gateways', [$this, 'remove_mondu_outside_germany']);

    /*
     * Show action links on the plugin screen.
     */
		add_filter('plugin_action_links_' . MONDU_PLUGIN_BASENAME, [$this, 'add_action_links']);
    /*
     * Adds meta information about the Mondu Plugin
     */
		add_filter('plugin_row_meta', [$this, 'add_row_meta'], 10, 2);

    /*
     * Adds the mondu javascript to the list of WordPress javascripts
     */
    add_action('wp_head', [$this, 'add_mondu_js']);

    /*
     * These deal with order and status changes
     */
    add_action('woocommerce_order_status_changed', [$this->mondu_request_wrapper, 'order_status_changed'], 10, 3);
    add_action('woocommerce_before_order_object_save', [$this->mondu_request_wrapper, 'update_order_if_changed_some_fields'], 10, 1);
    add_action('woocommerce_order_refunded', [$this->mondu_request_wrapper, 'order_refunded'], 10, 2);

    add_action('rest_api_init', function () {
      $orders = new OrdersController();
      $orders->register_routes();
      $webhooks = new WebhooksController();
      $webhooks->register_routes();
    });

    add_action('woocommerce_checkout_order_processed', function($order_id) {
      $mondu_order_id = WC()->session->get('mondu_order_id');

      WC()->session->set('woocommerce_order_id', $order_id);
      update_post_meta($order_id, Plugin::ORDER_ID_KEY, $mondu_order_id);
    }, 10, 3);

    /*
     * Does not allow to change address
     */
    add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'change_address_warning'], 10, 1);

    /*
     * These methods add the Mondu invoice's info to a WCPDF Invoice
     */
    add_action('wpo_wcpdf_after_order_details', [$this, 'wcpdf_add_mondu_payment_info_to_pdf'], 10, 2);
    add_action('wpo_wcpdf_after_order_data', [$this, 'wcpdf_add_status_to_invoice_when_order_is_cancelled'], 10, 2 );
    add_action('wpo_wcpdf_after_order_data', [$this, 'wcpdf_add_paid_to_invoice_when_invoice_is_paid'], 10, 2 );
    add_action('wpo_wcpdf_after_order_data', [$this, 'wcpdf_add_status_to_invoice_when_invoice_is_cancelled'], 10, 2 );
    add_action('wpo_wcpdf_meta_box_after_document_data', [$this, 'wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid'], 10, 2 );
    add_action('wpo_wcpdf_meta_box_after_document_data', [$this, 'wcpdf_add_status_to_invoice_admin_when_invoice_is_cancelled'], 10, 2 );
  }

  public function change_address_warning(WC_Order $order) {
    if (!in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return;
    }

    wc_enqueue_js("
      jQuery(document).ready(function() {
        jQuery('a.edit_address').remove();
      });
    ");
    echo '<p>' . __('Since this order will be paid via Mondu you will not be able to change the addresses.', 'mondu') . '</p>';
  }

  public function add_mondu_js() {
    if (is_checkout()) {
      if ($this->is_sandbox()) {
        require_once(MONDU_VIEW_PATH . '/checkout/mondu-checkout-sandbox.html');
      } else {
        require_once(MONDU_VIEW_PATH . '/checkout/mondu-checkout.html');
      }
    }
  }

  public function remove_mondu_outside_germany($available_gateways) {
		if (is_admin() || !is_checkout()) {
			return $available_gateways;
		}

    $mondu_payments = $this->mondu_request_wrapper->get_merchant_payment_methods();

    foreach (Plugin::PAYMENT_METHODS as $payment_method => $woo_payment_method) {
      if (
        $this->is_outside_germany() ||
        !in_array($payment_method, $mondu_payments)
      ) {
        if (isset($available_gateways[Plugin::PAYMENT_METHODS[$payment_method]])) {
          unset($available_gateways[Plugin::PAYMENT_METHODS[$payment_method]]);
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
	public static function add_action_links($links) {
		$action_links = array(
			'settings' => '<a href="' . admin_url('admin.php?page=mondu-settings-account') . '" aria-label="' . esc_attr__('View Mondu settings', 'mondu') . '">' . esc_html__('Settings', 'mondu') . '</a>',
		);

		return array_merge($action_links, $links);
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file  Plugin Base file.
	 *
	 * @return array
	 */
	public static function add_row_meta($links, $file) {
    if ($file != MONDU_PLUGIN_BASENAME) {
			return $links;
		}

    $row_meta = [
			'docs' => '<a target="_blank" href="' . esc_url('https://docs.mondu.ai/docs/woocommerce-installation-guide') . '" aria-label="' . esc_attr__( 'View Mondu documentation', 'mondu' ) . '">' . esc_html__( 'Docs', 'mondu' ) . '</a>',
    ];

		return array_merge($links, $row_meta);
  }

  /**
   * @param $template_type
   * @param $order
   *
   * @throws Exception
   */
  public function wcpdf_add_mondu_payment_info_to_pdf($template_type, $order) {
    if ($template_type == 'invoice') {
      $payment_info = new PaymentInfo($order->get_id());
      return $payment_info->get_mondu_payment_html();
    }
  }

  /**
   * @param $template_type
   * @param $order
   *
   * @throws Exception
   */
  public function wcpdf_add_status_to_invoice_when_order_is_cancelled($template_type, $order) {
    if ($order->get_status() == 'cancelled' && $template_type == 'invoice') {
      ?>
        <tr class="order-status">
          <th>Order status:</th>
          <td>Cancelled</td>
        </tr>
      <?php
    }
  }

  /**
   * @param $template_type
   * @param $order
   *
   * @throws Exception
   */
  public function wcpdf_add_paid_to_invoice_when_invoice_is_paid($template_type, $order) {
    $invoice_paid = get_post_meta($order->get_id(), Plugin::INVOICE_PAID_KEY, true);

    if ($invoice_paid == true && $template_type == 'invoice') {
      ?>
        <tr class="invoice-status">
          <th>Mondu Invoice paid:</th>
          <td>True</td>
        </tr>
      <?php
    }
  }

  /**
   * @param $template_type
   * @param $order
   *
   * @throws Exception
   */
  public function wcpdf_add_status_to_invoice_when_invoice_is_cancelled($template_type, $order) {
    $invoice_canceled = get_post_meta($order->get_id(), Plugin::INVOICE_CANCELED_KEY, true);

    if ($invoice_canceled == true && $template_type == 'invoice') {
      ?>
        <tr class="invoice-status">
          <th>Mondu Invoice status:</th>
          <td>Cancelled</td>
        </tr>
      <?php
    }
  }

  /**
   * @param $document
   * @param $order
   *
   * @throws Exception
   */
  public function wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid($document, $order) {
    $invoice_paid = get_post_meta($order->get_id(), Plugin::INVOICE_PAID_KEY, true);

    if ($invoice_paid == true && $document->get_type() == 'invoice') {
      ?>
        <div class="invoice-number">
          <p>
            <span><strong>Mondu Invoice Paid:</strong></span>
            <span>True</span>
          </p>
        </div>
      <?php
    }
  }

  /**
   * @param $document
   * @param $order
   *
   * @throws Exception
   */
  public function wcpdf_add_status_to_invoice_admin_when_invoice_is_cancelled($document, $order) {
    $invoice_canceled = get_post_meta($order->get_id(), Plugin::INVOICE_CANCELED_KEY, true);

    if ($invoice_canceled == true && $document->get_type() == 'invoice') {
      ?>
        <div class="invoice-number">
          <p>
            <span><strong>Mondu Invoice Status:</strong></span>
            <span>Cancelled</span>
          </p>
        </div>
      <?php
    }
  }

  /**
   * @return bool
   */
  private function is_sandbox() {
    $sandbox_env = true;
    if (
      is_array($this->global_settings) &&
      isset($this->global_settings['field_sandbox_or_production']) &&
      $this->global_settings['field_sandbox_or_production'] === 'production'
   ) {
      $sandbox_env = false;
    }

    return $sandbox_env;
  }

  /**
   * @return bool
   */
  private function is_outside_germany() {
    $customer = isset(WC()->customer) ? WC()->customer : new WC_Customer(get_current_user_id());
    if ($customer->get_billing_country() == 'DE' || $customer->get_billing_country() == 'AT') {
      return false;
    }
    return true;
  }
}
