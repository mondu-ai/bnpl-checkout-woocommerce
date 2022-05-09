<?php

namespace Mondu\Mondu;

use Mondu\Admin\Option\Account;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\OrderData;
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
    $this->logger = wc_get_logger();

    $this->global_settings = get_option( Account::OPTION_NAME );

    $this->id                 = 'mondu';
    $this->icon               = '';
    $this->has_fields         = true;
    $this->title              = __( 'Pay After Delivery', 'mondu' );
    $this->method_title       = __( 'Mondu', 'mondu' );
    $this->method_description = __( 'Mondu Description', 'mondu' );

    $this->init_form_fields();
    $this->init_settings();

    if ( isset( $this->settings['title'] ) ) {
      $this->title = $this->settings['title'];
    }

    if ( isset( $this->settings['description'], $this->settings['payment_term'] ) ) {
      $this->method_description = str_replace( '{Zahlungsziel}', $this->settings['payment_term'], $this->settings['description'] );
    }

    $this->api = new Api();

    $this->logger = wc_get_logger();
  }

  public function init_form_fields() {
    $this->form_fields = [
      'enabled'      => [
        'title'   => __( 'Enable/Disable', 'woocommerce' ),
        'type'    => 'checkbox',
        'label'   => __( 'Enable this payment method', 'mondu' ),
        'default' => 'no',
      ],
      'title'        => [
        'title'       => __( 'Title', 'woocommerce' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
        'default'     => __( 'Mondu Rechnungskauf', 'mondu' ),
        'desc_tip'    => true,
      ],
      'payment_term' => [
        'title'       => __( 'Payment Term', 'mondu' ),
        'type'        => 'integer',
        'description' => __( 'Based upon your Mondu contract, your customers will have between 7 and 120 days (payment term) to pay your invoices.', 'mondu' ),
        'default'     => 7,
        'desc_tip'    => true,
      ],
      'hide_logo'    => [
        'title' => __( 'Hide Logo', 'mondu' ),
        'type'  => 'checkbox',
        'label' => __( 'Hide Mondu Logo', 'mondu' ),
      ],
      'description'  => [
        'title'   => __( 'Customer Message', 'mondu' ),
        'type'    => 'textarea',
        'default' => 'Bezahlen Sie bequem und sicher auf Rechnung - innerhalb von {Zahlungsziel} Tagen nach Erhalt der Ware.'
      ],
    ];

    add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, static function ( $settings ) {
      if ( isset( $settings['payment_term'] ) ) {
        if ( ! is_numeric( $settings['payment_term'] ) ) {
          $settings['payment_term'] = 7;
        } elseif ( $settings['payment_term'] < 7 ) {
          $settings['payment_term'] = 7;
        } elseif ( $settings['payment_term'] > 120 ) {
          $settings['payment_term'] = 120;
        }
      }

      return $settings;
    } );

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
      $this,
      'process_admin_options'
    ] );
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
  public function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );

    // This is just to have an updated data saved for future references
    // It is not possible to do it in Mondu's order creation because we do not have an order_id
    $order_data = OrderData::raw_order_data();
    update_post_meta( $order_id, Plugin::ORDER_DATA_KEY, $order_data );

    // Update Mondu order's external reference id
    $data_to_update = ['external_reference_id' => (string) $order_id ];
    $adjust_order_data = OrderData::adjust_order_data( $order_id, $data_to_update );
    $response = $this->adjust_order( $order_id, $adjust_order_data );

    $order->update_status( 'wc-processing', __( 'Processing', 'woocommerce' ) );

    WC()->cart->empty_cart();
    /*
     * We remove the orders id here,
     * otherwise we might try to use the same session id for the next order
     */
    WC()->session->set( 'mondu_order_id', null );
    WC()->session->set( 'woocommerce_order_id', null );

    return array(
      'result'   => 'success',
      'redirect' => $this->get_return_url( $order )
    );
  }

  /**
   * @param array $methods
   *
   * @return array
   *
   * This adds Mondu as a payment method at the top of the method list
   */
  public static function add( array $methods ) {
    array_unshift( $methods, static::class );

    return $methods;
  }

  /**
   * @param array $post
   */
  public function process_success( array $post ) {
    // WC()->session->set( 'mondu_order_data', $post );

    echo json_encode( [ 'process' => 'success' ] );
    exit;
  }

  /**
   * @param array $post
   */
  public function process_error( array $post ) {
    if ( isset( $post['decline_reason'] ) ) {
      WC()->session->set( 'mondu_decline_reason', trim( $post['decline_reason'] ) );
    }
    echo json_encode( [ 'process' => 'error' ] );
    exit;
  }

  /**
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_order() {
    $payment_method = WC()->session->get( 'chosen_payment_method' );
    if ($payment_method !== 'mondu') {
      return;
    }

    $order_data = OrderData::create_order_data();
    $response = $this->api->create_order( $order_data );

    $mondu_order_id = $response['order']['uuid'];
    WC()->session->set( 'mondu_order_id', $mondu_order_id );
  }

  /**
   * @param $order_id
   * @param $data_to_update
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function adjust_order( $order_id, $data_to_update = null ) {
    $mondu_order_id = get_post_meta( $order_id, Plugin::ORDER_ID_KEY, true );
    $response = $this->api->adjust_order( $mondu_order_id, $data_to_update );
  }

  /**
   * @param $order
   *
   * @throws MonduException
   * @throws ResponseException
   */
  public function update_order_if_changed_some_fields( $order ) {
    # This method should not be called before ending the payment process
    if ( isset( WC()->session ) && WC()->session->get( 'mondu_order_id' ) )
      return;

    if ( array_intersect( array( 'total', 'discount_total', 'discount_tax', 'cart_tax', 'total_tax', 'shipping_tax', 'shipping_total' ), array_keys( $order->get_changes() ) ) ) {
      $data_to_update = OrderData::order_data_from_wc_order( $order );
      $response = $this->adjust_order( $order->get_id(), $data_to_update );
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
  public function order_status_changed( $order_id, $from_status, $to_status ) {
    $order = new WC_Order( $order_id );
    if ( $order->get_payment_method() !== $this->id ) {
      return;
    }
    if ( $to_status === 'cancelled' ) {
      $this->cancel_order( $order );
    }
    if ( $to_status === 'completed' ) {
      $this->complete_order( $order );
    }
  }

  /**
   * @param WC_Order $order
   *
   * @throws MonduException
   * @throws ResponseException
   */
  private function cancel_order( WC_Order $order ) {
    $mondu_order_id = get_post_meta( $order->get_id(), Plugin::ORDER_ID_KEY, true );

    $this->api->cancel_order( $mondu_order_id );
  }

  /**
   * @param WC_Order $order
   *
   * @throws MonduException
   * @throws ResponseException
   */
  private function complete_order( WC_Order $order ) {
    $mondu_order_id = get_post_meta( $order->get_id(), Plugin::ORDER_ID_KEY, true );

    $params = OrderData::invoice_data_from_wc_order( $order );

    $this->api->ship_order( $mondu_order_id, $params );
  }
}
