<?php

namespace Mondu;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Mondu\Admin\Option\Account;
use Mondu\Admin\Settings;
use Mondu\Mondu\Gateway;
use Mondu\Mondu\PaymentInfo;
use Mondu\Mondu\Api\MonduController;
use DateInterval;
use Exception;
use WC_DateTime;
use WC_Order;

class Plugin {
  const CALLBACK_SLUG = 'mondu-callback';

  const ORDER_DATA_KEY = '_monduOrderData';
  const DURATION_KEY = '_monduDuration';
  const ORDER_ID_KEY = '_monduOrderId';
  const SHIP_ORDER_REQUEST_RESPONSE = '_monduShipOrderRequestResponse';

  /**
   * @var array|bool|mixed|void
   */
  protected $global_settings;

  public function __construct() {
    $this->global_settings = get_option( Account::OPTION_NAME );

    add_action('woocommerce_after_checkout_validation', function () {
      if ($_POST['confirm-order-flag'] === "1") {
        // wc_add_notice(__('Validation checkout error!', 'mondu'), 'error');
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
     * Adds the mondu gateway to the list of gateways
     * (And remove it again if we're not in Germany)
     */
    add_filter( 'woocommerce_payment_gateways', [ Gateway::class, 'add' ] );
    add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_mondu_outside_germany' ] );

    /*
     * Adds the mondu javascript to the list of WordPress javascripts
     */
    add_action( 'wp_head', [ $this, 'add_mondu_js' ] );

    $plugin_rel_path = dirname( plugin_basename( __FILE__ ) ) . '/../../lang/';
    load_plugin_textdomain( 'mondu', false, $plugin_rel_path );

    add_action( 'woocommerce_order_status_changed', [ new Gateway(), 'order_status_changed' ], 10, 3 );


    add_action( 'rest_api_init', function () {
      $controller = new MonduController();
      $controller->register_routes();
    });

    /*
     * This one adds the payment information to a Germanized Pro Invoice
     */
    add_filter( 'woocommerce_gzdp_pdf_static_content', [
      $this,
      'add_mondu_payment_info_to_germanized_pdf'
    ], 10, 3 );

    /*
     * This one adds the payment information to a WCPDF Invoice
     */
    add_action( 'wpo_wcpdf_after_order_details', [
      $this,
      'add_mondu_payment_info_to_wcpdf_pdf'
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
    if ( $order->get_payment_method() !== 'mondu' ) {
      return;
    }
    wc_enqueue_js( "
        jQuery(document).ready(function() {
            jQuery( 'a.edit_address' ).remove();
        });
    " );
    echo '<p>' . __( 'Since this order will be paid via Mondu you won\'t be able to change the addresses.', 'mondu' ) . '</p>';
  }

  public function add_mondu_js() {
    if ( is_checkout() ) {
      if ( $this->is_sandbox() ) {
        require_once( MONDU_VIEW_PATH . '/checkout/mondu-checkout-sandbox.html' );
      } else {
        require_once( MONDU_VIEW_PATH . '/checkout/mondu-checkout.html' );
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

  public function remove_mondu_outside_germany( $available_gateways ) {
    if ( is_admin() ) {
      return $available_gateways;
    }
    if ( isset( $available_gateways['mondu'] ) && WC()->customer->get_billing_country() !== 'DE' ) {
      unset( $available_gateways['mondu'] );
    }

    return $available_gateways;
  }

  /**
   * @param string $html
   * @param $invoice
   * @param string $where
   *
   * @return string
   * @throws Exception
   */
  public function add_mondu_payment_info_to_germanized_pdf( $html, $invoice, $where ) {
    if ( $where !== 'after_table' ) {
      return $html;
    }

    if ( ! is_object( $invoice ) ) {
      return $html;
    }

    if ( ! isset( $invoice->post ) || ! $invoice->post instanceof \WP_Post ) {
      return $html;
    }

    $html = sprintf( "%s\n<br>\n%s", $html, $this->get_mondu_payment_html( $invoice->get_order_number() ) );

    return $html;
  }

  /**
   * @param $type
   * @param Order $order
   *
   * @throws Exception
   */
  public function add_mondu_payment_info_to_wcpdf_pdf( $type, Order $order ) {
    if ( $type !== 'invoice' ) {
      return;
    }

    $this->add_mondu_payment_info( $order->get_id() );
  }

  /**
   * @param $order_id
   *
   * @return false|string
   * @throws Exception
   */
  public function get_mondu_payment_html( $order_id ) {
    return PaymentInfo::get_mondu_payment_html( $order_id );
  }

  /**
   * @param $order_id
   *
   * @throws Exception
   */
  public function add_mondu_payment_info( $order_id ) {

    echo $this->get_mondu_payment_html( $order_id );
  }
}