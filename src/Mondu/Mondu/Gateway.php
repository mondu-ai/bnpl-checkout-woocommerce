<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Data_Exception;
use WC_Payment_Gateway;

class Gateway extends WC_Payment_Gateway {

  /**
   * @var array|bool|mixed|void
   */
  protected $global_settings;
  /**
   * @var string|void
   */
  protected $method_name;
  /**
   * @var MonduRequestWrapper
   */
  private $mondu_request_wrapper;

  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);

    $this->id = Plugin::PAYMENT_METHODS['invoice'];
    $this->title = 'Rechnungskauf - jetzt kaufen, später bezahlen';
    $this->method_title = 'Mondu Rechnungskauf';
    $this->method_description = 'Rechnungskauf - jetzt kaufen, später bezahlen';
    $this->has_fields = true;
    $this->icon = apply_filters( 'woocommerce_gateway_icon',  MONDU_PUBLIC_PATH . '/views/mondu.svg');

    $this->init_form_fields();
    $this->init_settings();

    $this->mondu_request_wrapper = new MonduRequestWrapper();
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
    $order_data = OrderData::raw_order_data('invoice');
    update_post_meta($order_id, Plugin::ORDER_DATA_KEY, $order_data);

    $order = $this->mondu_request_wrapper->process_payment($order_id);

    return array(
      'result'   => 'success',
      'redirect' => $this->get_return_url($order)
    );
  }
}
