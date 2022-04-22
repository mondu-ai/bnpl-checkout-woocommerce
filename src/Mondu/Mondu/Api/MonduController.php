<?php

namespace Mondu\Mondu\Api;

use Mondu\Mondu\Gateway;
use WP_REST_Controller;

class MonduController extends WP_REST_Controller {
  public function __construct() {
    $this->namespace = 'mondu/v1';
  }

  // Register our routes
  public function register_routes() {
    register_rest_route( $this->namespace, '/create_order', array(
      array(
        'methods'  => 'POST',
        'callback' => array( $this, 'create_order' ),
        'permission_callback' => '__return_true'
      ),
    ) );
    register_rest_route( $this->namespace, '/checkout_callback', array(
      array(
        'methods'  => 'POST',
        'callback' => array( $this, 'checkout_callback' ),
        'permission_callback' => '__return_true'
      ),
    ) );
  }

  public function create_order( $request ) {
    $gateway = new Gateway();
    $gateway->create_order();

    return array(
      'token' => WC()->session->get( 'mondu_order_id' )
    );
  }

  public function checkout_callback( $request ) {
    $type = $request['type'];
    $gateway = new Gateway();

    if ( $type == 'success' ) {
      return $gateway->process_success( $_POST );
    }
    if ( $type == 'error' ) {
      return $gateway->process_error( $_POST );
    }
  }
}
