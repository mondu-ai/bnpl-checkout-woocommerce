<?php

namespace Mondu\Mondu\Api;

use Mondu\Mondu\Gateway;
use WP_REST_Controller;
use WP_REST_Request;

class OrdersController extends WP_REST_Controller {
  public function __construct() {
    $this->namespace = 'mondu/v1/orders';
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
    $gateway = new Gateway();
    $gateway->create_order();

    return array(
      'token' => WC()->session->get('mondu_order_id')
   );
  }
}
