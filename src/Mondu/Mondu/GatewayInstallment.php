<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Data_Exception;
use WC_Payment_Gateway;

class GatewayInstallment extends WC_Payment_Gateway {

  /**
   * @var array|bool|mixed|void
   */
  protected $global_settings;
  /**
   * @var string|void
   */
  protected $method_name;

  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);

    $this->id = Plugin::PAYMENT_METHODS['installment'];
    $this->title = __('Ratenzahlung - Bequem in Raten per Bankeinzug zahlen', 'mondu');
    $this->method_title = __('Mondu Ratenzahlung', 'mondu');
    $this->method_description = __('Ratenzahlung - Bequem in Raten per Bankeinzug zahlen', 'mondu');
    $this->has_fields = true;
    $this->icon = apply_filters( 'woocommerce_gateway_icon',  MONDU_PUBLIC_PATH . '/views/mondu.svg', $this->id);
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

    $this->mondu_request_wrapper = new MonduRequestWrapper();
  }

  /**
   * Initialise Gateway Settings Form Fields
   */
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable this payment method', 'mondu'),
        'default' => 'no',
      ),
      'title' => array(
        'title'       => __( 'Title', 'woocommerce' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
        'default'     => $this->title,
        'desc_tip'    => true,
        ),
      'description' => array(
        'title'       => __( 'Description', 'woocommerce' ),
        'type'        => 'textarea',
        'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
        'default'     => $this->method_description,
        'desc_tip'    => true,
      ),
      'instructions' => array(
        'title'       => __( 'Instructions', 'woocommerce' ),
        'type'        => 'textarea',
        'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
        'default'     => '',
        'desc_tip'    => true,
      ),
    );

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
  }

  /**
   * @param array $methods
   *
   * @return array
   *
   * This adds Mondu as a payment method at the top of the method list
   */
  public static function add(array $methods) {
    array_unshift($methods, static::class);

    return $methods;
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function payment_fields() {
    $description = $this->get_description();
    if ( $description ) {
            echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
    }
  
    include MONDU_VIEW_PATH . '/checkout/payment-form.php';
  }

  /**
   * @param int $order_id
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   * @throws WC_Data_Exception
   */
  public function process_payment($order_id) {
    // This is just to have an updated data saved for future references
    // It is not possible to do it in Mondu's order creation because we do not have an order_id
    $order_data = OrderData::raw_order_data('installment');
    update_post_meta($order_id, Plugin::ORDER_DATA_KEY, $order_data);

    $order = $this->mondu_request_wrapper->process_payment($order_id);
    if(!$order) {
      wc_add_notice(__('Error placing an order. Please try again.', 'mondu'), 'error');
      return;
    }
    return array(
      'result' => 'success',
      'redirect' => $this->get_return_url($order)
    );
  }
}
