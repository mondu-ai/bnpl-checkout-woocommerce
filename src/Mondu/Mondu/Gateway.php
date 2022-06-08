<?php

namespace Mondu\Mondu;

use Mondu\Admin\Option\Account;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Checkout;
use WC_Data_Exception;
use WC_Order;
use WC_Order_Refund;
use WC_Payment_Gateway;
use WC_Product;

class Gateway extends WC_Payment_Gateway {

  /**
   * @var array|bool|mixed|void
   */
  protected $global_settings;
  /**
   * @var string|void
   */
  protected $method_name;

  private $logger;

  /** @var Api */
  private $api;

  public function __construct() {
    $this->global_settings = get_option(Account::OPTION_NAME);

    $this->id = 'mondu';
    $this->has_fields = true;
    $this->title = Plugin::TITLE;
    $this->personal_data_url = Plugin::PERSONAL_DATA_URL;
    $this->logo_url = plugins_url('/woocommerce-mondu/views/mondu.svg');

    $this->init_form_fields();
    $this->init_settings();

    if (isset($this->settings['title'])) {
      $this->title = $this->settings['title'];
    }

    if (isset($this->settings['logo_url'])) {
      $this->logo_url = $this->settings['logo_url'];
    }

    $this->api = new Api();
    $this->mondu_request_wrapper = new MonduRequestWrapper();
    $this->logger = wc_get_logger();
  }

  public function init_form_fields() {
    $this->form_fields = [
      'enabled'      => [
        'title'   => __('Enable/Disable', 'woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable this payment method', 'mondu'),
        'default' => 'no',
     ],
      'title'        => [
        'title'       => __('Title', 'woocommerce'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
        'default'     => __('Rechnungskauf - jetzt kaufen, später bezahlen', 'mondu'),
        'desc_tip'    => true,
     ],
      'logo_url'     => [
        'title'       => __('Logo url', 'woocommerce'),
        'type'        => 'text',
        'description' => __('The logo the user will see during checkout.', 'woocommerce'),
        'default'     => __('https://mondu.ai/wp-content/uploads/2022/03/logo.svg', 'mondu'),
        'desc_tip'    => true,
     ],
   ];

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
      $this,
      'process_admin_options'
   ]);
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function payment_fields() {
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
    $order = new WC_Order($order_id);

    // This is just to have an updated data saved for future references
    // It is not possible to do it in Mondu's order creation because we do not have an order_id
    $order_data = OrderData::raw_order_data();
    update_post_meta($order_id, Plugin::ORDER_DATA_KEY, $order_data);

    // Update Mondu order's external reference id
    $this->mondu_request_wrapper->update_external_info($order_id);

    $order->update_status('wc-processing', __('Processing', 'woocommerce'));

    WC()->cart->empty_cart();
    /*
     * We remove the orders id here,
     * otherwise we might try to use the same session id for the next order
     */
    WC()->session->set('mondu_order_id', null);
    WC()->session->set('woocommerce_order_id', null);

    return array(
      'result'   => 'success',
      'redirect' => $this->get_return_url($order)
   );
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
   * @param $order
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function update_order_if_changed_some_fields($order) {
    if ($order->get_payment_method() !== 'mondu') {
      return;
    }

    # This method should not be called before ending the payment process
    if (isset(WC()->session) && WC()->session->get('mondu_order_id'))
      return;

    if (array_intersect(array('total', 'discount_total', 'discount_tax', 'cart_tax', 'total_tax', 'shipping_tax', 'shipping_total'), array_keys($order->get_changes()))) {
      $data_to_update = OrderData::order_data_from_wc_order($order);
      $this->mondu_request_wrapper->adjust_order($order->get_id(), $data_to_update);
    }
  }

  /**
   * @param $order_id
   * @param $from_status
   * @param $to_status
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function order_status_changed($order_id, $from_status, $to_status) {
    $order = new WC_Order($order_id);
    if ($order->get_payment_method() !== 'mondu') {
      return;
    }

    if ($to_status === 'cancelled') {
      $this->mondu_request_wrapper->cancel_order($order_id);
    }
    // if ($to_status === 'refunded') {
    //   $this->mondu_request_wrapper->cancel_order($order);
    // }
    if ($to_status === 'completed') {
      $this->mondu_request_wrapper->ship_order($order_id);
    }
  }

  /**
   * @param $order_id
   * @param $refund_id
   *
   * @throws MonduException
   * @throws ResponseException
   */
  // public function order_refunded($order_id, $refund_id) {
  //   $order         = new WC_Order($order_id);
  //   $refund        = new WC_Order_Refund($refund_id);
  //   $mondu_order_id = get_post_meta($order->get_id(), Plugin::ORDER_ID_KEY, true);

  //   $order_total      = $order->get_total();
  //   $order_total_tax  = $order->get_total_tax();
  //   $refund_total     = $refund->get_total();
  //   $refund_total_tax = $refund->get_total_tax();
  //   $new_amount       = [
  //     'net'   => ($order_total - $order_total_tax) + ($refund_total - $refund_total_tax),
  //     'gross' => ($order_total) + ($refund_total),
  //     'tax'   => ($order_total_tax) + ($refund_total_tax),
  //  ];

  //   $this->api->updateOrder($mondu_order_id, ['amount' => $new_amount]);
  // }
}
