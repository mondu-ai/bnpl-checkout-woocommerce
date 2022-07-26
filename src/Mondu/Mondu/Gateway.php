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
    $this->global_settings = get_option(Plugin::OPTION_NAME);

    $this->id = 'mondu';
    $this->title = 'Rechnungskauf - jetzt kaufen, später bezahlen';
    $this->method_title = 'Mondu Rechnungskauf';
    $this->method_description = 'Rechnungskauf - jetzt kaufen, später bezahlen';
    $this->has_fields = true;

    $this->init_form_fields();
    $this->init_settings();

    $this->api = new Api();
    $this->mondu_request_wrapper = new MonduRequestWrapper();
    $this->logger = wc_get_logger();
  }

  public function init_form_fields() {
    $this->form_fields = [
      'enabled' => [
        'title'   => __('Enable/Disable', 'woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable this payment method', 'mondu'),
        'default' => 'no',
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
   public function order_refunded($order_id, $refund_id) {
      $order = new WC_Order($order_id);

      if ($order->get_payment_method() !== 'mondu') {
         return;
      }

      $refund = new WC_Order_Refund($refund_id);
      $mondu_invoice_id = get_post_meta($order->get_id(), PLUGIN::INVOICE_ID_KEY, true);

      if(!$mondu_invoice_id) {
         throw new ResponseException('Mondu: Can\'t create a credit note without an invoice');
      }

      $refund_total = $refund->get_total();
      $credit_note = [
         'gross_amount_cents' => abs(round ((float) $refund_total * 100)),
         'external_reference_id' => (string) $refund->get_id()
      ];

      $this->api->create_credit_note($mondu_invoice_id, $credit_note);
   }
}
