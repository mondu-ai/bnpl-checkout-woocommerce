<?php


namespace Mondu\Mondu\Api;

use Mondu\Mondu\Gateway;
use WP_REST_Controller;

class MonduController extends WP_REST_Controller {

  public function __construct() {
    $this->namespace = 'mondu/v1';
  }

  // Register our routes.
  public function register_routes() {

    $this->register_rest_route( $this->namespace, '/create_order', array(
      array(
        'methods'  => 'POST',
        'callback' => array( $this, 'create_order' ),
        'permission_callback' => '__return_true'
      ),
    ) );
    $this->register_rest_route( $this->namespace, '/checkout_callback', array(
      array(
        'methods'  => 'POST',
        'callback' => array( $this, 'checkout_callback' ),
        'permission_callback' => '__return_true'
      ),
    ) );
  }

  public function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
    if ( empty( $namespace ) ) {
       _doing_it_wrong( 'register_rest_route', __( 'Routes must be namespaced with plugin or theme name and version.' ), '4.4.0' );
        return false;
    } elseif ( empty( $route ) ) {
        _doing_it_wrong( 'register_rest_route', __( 'Route must be specified.' ), '4.4.0' );
        return false;
    }
 
    $clean_namespace = trim( $namespace, '/' );
 
    if ( $clean_namespace !== $namespace ) {
        _doing_it_wrong( __FUNCTION__, __( 'Namespace must not start or end with a slash.' ), '5.4.2' );
    }
 
    if ( ! did_action( 'rest_api_init' ) ) {
        _doing_it_wrong(
            'register_rest_route',
            sprintf(
                /* translators: %s: rest_api_init */
                __( 'REST API routes must be registered on the %s action.' ),
                '<code>rest_api_init</code>'
            ),
            '5.1.0'
        );
    }
 
    if ( isset( $args['args'] ) ) {
        $common_args = $args['args'];
        unset( $args['args'] );
    } else {
        $common_args = array();
    }
 
    if ( isset( $args['callback'] ) ) {
        $args = array( $args );
    }
 
    $defaults = array(
        'methods'  => 'GET',
        'callback' => null,
        'args'     => array(),
    );
 
    foreach ( $args as $key => &$arg_group ) {
        if ( ! is_numeric( $key ) ) {
            continue;
        }
 
        $arg_group         = array_merge( $defaults, $arg_group );
        $arg_group['args'] = array_merge( $common_args, $arg_group['args'] );
 
        if ( ! isset( $arg_group['permission_callback'] ) ) {
            _doing_it_wrong(
                __FUNCTION__,
                sprintf(
                    __( 'The REST API route definition for %1$s is missing the required %2$s argument. For REST API routes that are intended to be public, use %3$s as the permission callback.' ),
                    '<code>' . $clean_namespace . '/' . trim( $route, '/' ) . '</code>',
                    '<code>permission_callback</code>',
                    '<code>__return_true</code>'
                ),
                '5.5.0'
            );
        }
    }
 
    $full_route = '/' . $clean_namespace . '/' . trim( $route, '/' );
    rest_get_server()->register_route( $clean_namespace, $full_route, $args, $override );
    return true;
  }

  public function create_order( $request ) {
  
    $gateway = new Gateway();
    $gateway->create_order();
   
    return array(
      'token' => WC()->session->get( 'mondu_order_id' )
    );
    
    // $data = array();

    // if ( empty( $args ) ) {
    //   return rest_ensure_response( $data );
    // }

    // return rest_ensure_response( $data );
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

    return array(
      'test' => $type
    );
  }
}
