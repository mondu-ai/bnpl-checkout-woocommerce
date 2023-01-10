<?php

namespace Mondu\Mondu\Controllers;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;

class OrdersController extends WP_REST_Controller {
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
      $this->validate_checkout();
      if (wc_notice_count('error') === 0) {
        $this->mondu_request_wrapper->create_order();
        return array(
          'token' => WC()->session->get('mondu_order_id')
        );
      } else {
        throw new MonduException(__('Error processing checkout. Please try again.', 'mondu'));
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
    call_user_func_array(array(WC()->checkout(), 'update_session'), array($posted_data));
    call_user_func_array(array(WC()->checkout(), 'validate_checkout'), array($posted_data, $errors));
    do_action('woocommerce_after_checkout_validation', $posted_data, $errors);

    foreach ($errors->errors as $code => $messages) {
      $data = $errors->get_error_data($code);
      foreach ($messages as $message) {
        wc_add_notice($message, 'error', $data);
      }
    }
  }
}
