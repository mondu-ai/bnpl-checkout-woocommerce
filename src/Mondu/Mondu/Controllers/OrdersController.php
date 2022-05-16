<?php

namespace Mondu\Mondu\Controllers;

use Mondu\Mondu\MonduRequestWrapper;
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
    $this->mondu_request_wrapper->create_order();

    return array(
      'token' => WC()->session->get('mondu_order_id')
   );
  }
}
