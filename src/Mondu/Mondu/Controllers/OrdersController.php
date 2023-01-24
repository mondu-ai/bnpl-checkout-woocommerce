<?php

namespace Mondu\Mondu\Controllers;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use ReflectionMethod;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;

class OrdersController extends WP_REST_Controller {
  /**
   * @var MonduRequestWrapper
   */
  private $mondu_request_wrapper;

  public function __construct() {
    $this->namespace = 'mondu/v1/orders';
    $this->mondu_request_wrapper = new MonduRequestWrapper();
  }

  // Register our routes
  public function register_routes() {
    register_rest_route($this->namespace, '/create', array(
      array(
        'methods' => 'POST',
        'callback' => array($this, 'create'),
        'permission_callback' => '__return_true'
     ),
   ));
  }

  public function create(WP_REST_Request $request) {
    try {
      $data = $request->get_params();
      // We need to distinguish order pay page and checkout page
      if (array_key_exists('orderpay', $data) && $data['orderpay'] == 'true') {
        // We only need to check the terms and condition box
        if (empty($data['terms']) && ! empty($data['terms-field'])) {
          wc_add_notice(__('Please read and accept the terms and conditions to proceed with your order.', 'woocommerce'), 'error');
          throw new MonduException(__('Error processing checkout. Please try again.', 'mondu'));
        }
        $order = $this->mondu_request_wrapper->create_order_pay_page($data);
        return array(
          'token' => $order['uuid']
        );
      } else {
        $this->validate_checkout();
        if (wc_notice_count('error') === 0) {
          $order = $this->mondu_request_wrapper->create_order();
          return array(
            'token' => $order['uuid']
          );
        } else {
          throw new MonduException(__('Error processing checkout. Please try again.', 'mondu'));
        }
      }
    } catch (ResponseException | MonduException $e) {
      return array(
        'token' => null,
        'errors' => wc_print_notices(true)
      );
    }
  }

  private function validate_checkout() {
    $errors = new WP_Error();

    $posted_data = WC()->checkout()->get_posted_data();

    $validate_checkout_method = new ReflectionMethod(get_class(WC()->checkout()), 'validate_checkout');
    $update_session_method = new ReflectionMethod(get_class(WC()->checkout()), 'update_session');

    $update_session_method->setAccessible(true);
    $validate_checkout_method->setAccessible(true);

    $update_session_method->invoke(WC()->checkout(), $posted_data);
    $validate_checkout_method->invoke(WC()->checkout(), $posted_data, $errors);

    foreach ($errors->errors as $code => $messages) {
      $data = $errors->get_error_data($code);
      foreach ($messages as $message) {
        wc_add_notice($message, 'error', $data);
      }
    }
  }
}
