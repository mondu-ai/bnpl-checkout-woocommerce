<?php

namespace Mondu\Mondu;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\GatewayFields;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Support\OrderData;
use Mondu\Plugin;
use WC_Data_Exception;
use WC_Payment_Gateway;

class MonduGateway extends WC_Payment_Gateway {

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

    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->instructions = $this->get_option('instructions');

    $this->enabled = $this->is_enabled();

    $this->mondu_request_wrapper = new MonduRequestWrapper();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

    add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
  }

  /**
   * Initialise Gateway Settings Form Fields
   */
  public function init_form_fields() {
    $this->form_fields = GatewayFields::fields($this->title);
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
    parent::payment_fields();
    $order_id = 0;

    if (is_wc_endpoint_url('order-pay')) {
      $order_id = absint(get_query_var('order-pay'));
    }

    include MONDU_VIEW_PATH . '/checkout/payment-form.php';
  }

  /**
   * Output for the order received page.
   */
  public function thankyou_page() {
    if ($this->instructions) {
      echo wp_kses_post(wpautop(wptexturize($this->instructions)));
    }
  }

  /**
   * Add content to the WC emails.
   *
   * @param WC_Order $order
   */
  public function email_instructions($order) {
    if (!Plugin::order_has_mondu($order)) {
      return;
    }

    if ($this->instructions && $this->id === $order->get_payment_method()) {
      echo wp_kses_post(wpautop(wptexturize($this->instructions)));
    }
  }

  /**
   * Get gateway icon.
   *
   * @return string
   */
  public function get_icon() {
    $icon_src  = 'https://checkout.mondu.ai/logo.svg';
    $icon_html = '<img src="' . $icon_src . '" alt="' . $this->method_title . '" width="100" />';
    return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
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

    if(!$order) {
      wc_add_notice(__('Error placing an order. Please try again.', 'mondu'), 'error');
      return;
    }

    return array(
      'result' => 'success',
      'redirect' => $this->get_return_url($order)
    );
  }

  /**
   * Check if Mondu has its credentials validated.
   *
   * @return string
   */
  private function is_enabled() {
    if (get_option('_mondu_credentials_validated') == null) $this->settings['enabled'] = 'no';

    return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
  }
}
