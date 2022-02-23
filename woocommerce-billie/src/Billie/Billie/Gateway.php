<?php


namespace Billie\Billie;


use Billie\Admin\Option\Account;
use Billie\Exceptions\BillieException;
use Billie\Exceptions\ResponseException;
use Billie\Plugin;
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

    // echo 'fooooooooi';
    $this->global_settings = get_option( Account::OPTION_NAME );

    $this->id                 = 'billie';
    $this->icon               = '';
    $this->has_fields         = true;
    $this->title              = __( 'Pay After Delivery', 'billie' );
    $this->method_title       = __( 'Billie', 'billie' );
    $this->method_description = __( 'Billie Description', 'billie' );

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
        'label'   => __( 'Enable this payment method', 'billie' ),
        'default' => 'no',
      ],
      'title'        => [
        'title'       => __( 'Title', 'woocommerce' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
        'default'     => __( 'Billie Rechnungskauf', 'billie' ),
        'desc_tip'    => true,
      ],
      'payment_term' => [
        'title'       => __( 'Payment Term', 'billie' ),
        'type'        => 'integer',
        'description' => __( 'Based upon your Billie contract, your customers will have between 7 and 120 days (payment term) to pay your invoices.', 'billie' ),
        'default'     => 7,
        'desc_tip'    => true,
      ],
      'hide_logo'    => [
        'title' => __( 'Hide Logo', 'billie' ),
        'type'  => 'checkbox',
        'label' => __( 'Hide Billie Logo', 'billie' ),
      ],
      'description'  => [
        'title'   => __( 'Customer Message', 'billie' ),
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
   * @throws BillieException
   * @throws ResponseException
   */
  public function payment_fields() {
    include BILLIE_VIEW_PATH . '/checkout/payment-form.php';
  }

  /**
   * @param int $order_id
   *
   * @return array
   * @throws BillieException
   * @throws ResponseException
   * @throws WC_Data_Exception
   */
  public function process_payment( $order_id ) {
    echo 'processing payment';

    $order = new WC_Order( $order_id );

    $duration = ( is_array( $this->settings ) && isset( $this->settings['payment_term'] ) && is_numeric( $this->settings['payment_term'] ) ) ? $this->settings['payment_term'] : 7;

    update_post_meta( $order->get_id(), Plugin::DURATION_KEY, $duration );

    // $billie_session_id = $this->getBillieSessionId( WC()->session->get_customer_id() );
    update_post_meta( $order->get_id(), Plugin::SESSION_ID_KEY, $billie_session_id );

    // $billie_order_data = $this->api->checkoutSessionConfirm( $billie_session_id, WC()->session->get( 'billie_order_data' ) );

    $this->logger->debug( 'billie_order_data', $billie_order_data );

    update_post_meta( $order->get_id(), Plugin::ORDER_DATA_KEY, $billie_order_data );

    update_post_meta( $order->get_id(), Plugin::ORDER_ID_KEY, $billie_order_data['uuid'] );

    $this->api->updateOrder( $billie_order_data['uuid'], [
      'order_id' => $order->get_order_number(),
    ] );

    $use_shipping_address = ( $order->get_formatted_billing_address() !== $order->get_formatted_shipping_address() );

    $billing_company = null;

    if ( isset( $billie_order_data['debtor_company']['name'] ) ) {
      $billing_company = sanitize_text_field( $billie_order_data['debtor_company']['name'] );
    }

    if ( $billing_company !== null ) {
      $order->set_billing_company( $billing_company );
      $this->logger->debug( 'setting billing company', [ 'company' => $billing_company ] );
    }

    /*
     * Rechnungsadresse übernehmen
     */
    $billing_address_1 = null;
    $billing_postcode  = null;
    $billing_city      = null;
    $billing_country   = null;

    if ( isset( $billie_order_data['billing_address']['street'], $billie_order_data['billing_address']['house_number'] ) ) {
      $billing_address_1 = implode( ' ', [
        sanitize_text_field( $billie_order_data['billing_address']['street'] ),
        sanitize_text_field( $billie_order_data['billing_address']['house_number'] ),
      ] );
    }

    if ( isset( $billie_order_data['billing_address']['postal_code'] ) ) {
      $billing_postcode = sanitize_text_field( $billie_order_data['billing_address']['postal_code'] );
    }

    if ( isset( $billie_order_data['billing_address']['city'] ) ) {
      $billing_city = sanitize_text_field( $billie_order_data['billing_address']['city'] );
    }
    if ( isset( $billie_order_data['billing_address']['country'] ) ) {
      $billing_country = sanitize_text_field( $billie_order_data['billing_address']['country'] );
    }

    if ( $billing_address_1 !== null ) {
      $order->set_billing_address_1( $billing_address_1 );
      $this->logger->debug( 'setting billing address 1', [ 'address_1' => $billing_address_1 ] );
    }

    if ( $billing_postcode !== null ) {
      $order->set_billing_postcode( $billing_postcode );
      $this->logger->debug( 'setting billing postcode', [ 'postcode' => $billing_postcode ] );
    }

    if ( $billing_city !== null ) {
      $order->set_billing_city( $billing_city );
      $this->logger->debug( 'setting billing city', [ 'city' => $billing_city ] );
    }

    if ( $billing_country !== null ) {
      $order->set_billing_country( $billing_country );
      $this->logger->debug( 'setting billing country', [ 'country' => $billing_country ] );
    }


    if ( ! $use_shipping_address ) {
      if ( $billing_company !== null ) {
        $order->set_shipping_company( $billing_company );
      }

      if ( $billing_address_1 !== null ) {
        $order->set_shipping_address_1( $billing_address_1 );
        $this->logger->debug( 'setting shipping address 1 to billing address 1', [ 'address_1' => $billing_address_1 ] );
      }

      if ( $billing_postcode !== null ) {
        $order->set_shipping_postcode( $billing_postcode );
        $this->logger->debug( 'setting shipping postcode to billing postcode', [ 'postcode' => $billing_postcode ] );
      }

      if ( $billing_city !== null ) {
        $order->set_shipping_city( $billing_city );
        $this->logger->debug( 'setting shipping city to billing city', [ 'city' => $billing_city ] );
      }

      if ( $billing_country !== null ) {
        $order->set_shipping_country( $billing_country );
        $this->logger->debug( 'setting shipping country to billing country', [ 'country' => $billing_country ] );
      }
    } else {
      /*
             * Lieferadresse übernehmen
             */
      $shipping_address_1 = null;
      $shipping_postcode  = null;
      $shipping_city      = null;
      $shipping_country   = null;

      if ( isset( $billie_order_data['delivery_address']['street'], $billie_order_data['delivery_address']['house_number'] ) ) {
        $shipping_address_1 = implode( ' ', [
          sanitize_text_field( $billie_order_data['delivery_address']['street'] ),
          sanitize_text_field( $billie_order_data['delivery_address']['house_number'] ),
        ] );
      }

      if ( isset( $billie_order_data['delivery_address']['postal_code'] ) ) {
        $shipping_postcode = sanitize_text_field( $billie_order_data['delivery_address']['postal_code'] );
      }

      if ( isset( $billie_order_data['delivery_address']['city'] ) ) {
        $shipping_city = sanitize_text_field( $billie_order_data['delivery_address']['city'] );
      }
      if ( isset( $billie_order_data['delivery_address']['country'] ) ) {
        $shipping_country = sanitize_text_field( $billie_order_data['delivery_address']['country'] );
      }

      if ( $shipping_address_1 !== null ) {
        $order->set_shipping_address_1( $shipping_address_1 );
        $this->logger->debug( 'setting shipping address 1', [ 'address_1' => $shipping_address_1 ] );
      }

      if ( $shipping_postcode !== null ) {
        $order->set_shipping_postcode( $shipping_postcode );
        $this->logger->debug( 'setting shipping postcode', [ 'postcode' => $shipping_postcode ] );
      }

      if ( $shipping_city !== null ) {
        $order->set_shipping_city( $shipping_city );
        $this->logger->debug( 'setting shipping city', [ 'city' => $shipping_city ] );
      }

      if ( $shipping_country !== null ) {
        $order->set_shipping_country( $shipping_country );
        $this->logger->debug( 'setting shipping country', [ 'country' => $shipping_country ] );
      }
    }

    $order->update_status( 'wc-processing', __( 'Processing', 'woocommerce' ) );

    WC()->cart->empty_cart();
    /*
     * We remove the billie session id here,
     * otherwise we might try to use the same session id for the next order, which will trigger an
     * authorization error
     */
    WC()->session->set( 'billieSession', null );

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
   * This adds Billie as a payment method at the top of the method list
   */
  public static function add( array $methods ) {
    array_unshift( $methods, static::class );

    return $methods;
  }

  /**
   * @return array[]
   */
  private function createBillieOrderData() {

    $cart        = WC()->session->get( 'cart' );
    $cart_totals = WC()->session->get( 'cart_totals' );

    $duration = ( is_array( $this->settings ) && isset( $this->settings['payment_term'] ) && is_numeric( $this->settings['payment_term'] ) ) ? $this->settings['payment_term'] : 7;

    $billieOrderData = [
      'duration'   => $duration,
      'amount'     => [
        'net'   => round( (float) $cart_totals['cart_contents_total'] + (float) $cart_totals['shipping_total'], 2 ),
        'gross' => round( (float) $cart_totals['total'], 2 ),
        'tax'   => round( $cart_totals['total_tax'], 2 ),
      ],
      'line_items' => []
    ];

    foreach ( $cart as $key => $cartItem ) {
      /** @var WC_Product $product */
      $product                         = WC()->product_factory->get_product( $cartItem['product_id'] );
      $lineItem                        = [
        'external_id' => isset( $cartItem['product_id'] ) ? $cartItem['product_id'] : null,
        'title'       => $product->get_title(),
        'quantity'    => isset( $cartItem['quantity'] ) ? $cartItem['quantity'] : null,
        'amount'      => [
          'net'   => round( (float) $cartItem['line_total'], 2 ),
          'gross' => round( (float) $cartItem['line_total'] + (float) $cartItem['line_tax'], 2 ),
          'tax'   => round( (float) $cartItem['line_tax'], 2 ),
        ]
      ];
      $billieOrderData['line_items'][] = $lineItem;
    }

    return $billieOrderData;
  }

  /**
   * @param array $post
   */
  public function process_success( array $post ) {
    WC()->session->set( 'billie_order_data', $post );

    echo json_encode( [ 'process' => 'success' ] );
    exit;
  }

  /**
   * @param array $post
   */
  public function process_error( array $post ) {
    if ( isset( $post['decline_reason'] ) ) {
      WC()->session->set( 'billie_decline_reason', trim( $post['decline_reason'] ) );
    }
    echo json_encode( [ 'process' => 'error' ] );
    exit;
  }


  /**
   * @param $order_id
   * @param $from_status
   * @param $to_status
   *
   * @throws BillieException
   * @throws ResponseException
   */
  public function order_status_changed( $order_id, $from_status, $to_status ) {
    $order = new WC_Order( $order_id );
    if ( $order->get_payment_method() !== $this->id ) {
      return;
    }

    if ( $to_status === 'cancelled' ) {
      $this->cancelOrder( $order );
    }

    if ( $to_status === 'refunded' ) {
      $this->cancelOrder( $order );
    }

    if ( $to_status === 'completed' ) {
      $this->completeOrder( $order );
    }

  }

  /**
   * @param $order_id
   * @param $refund_id
   *
   * @throws BillieException
   * @throws ResponseException
   */
  public function order_refunded( $order_id, $refund_id ) {
    $order         = new WC_Order( $order_id );
    $refund        = new WC_Order_Refund( $refund_id );
    $billieOrderId = get_post_meta( $order->get_id(), Plugin::ORDER_ID_KEY, true );

    $order_total      = $order->get_total();
    $order_total_tax  = $order->get_total_tax();
    $refund_total     = $refund->get_total();
    $refund_total_tax = $refund->get_total_tax();
    $new_amount       = [
      'net'   => ( $order_total - $order_total_tax ) + ( $refund_total - $refund_total_tax ),
      'gross' => ( $order_total ) + ( $refund_total ),
      'tax'   => ( $order_total_tax ) + ( $refund_total_tax ),
    ];

    $this->api->updateOrder( $billieOrderId, [ 'amount' => $new_amount ] );
  }

  /**
   * @throws BillieException
   * @throws ResponseException
   */
  public function create_order() {
    $payment_method = WC()->session->get( 'chosen_payment_method' );
    if ($payment_method !== 'billie') {
      return;
    }

    $params = OrderData::createOrderData();

    $response = $this->api->createOrder( $params );

    WC()->session->set( 'mondu_order_id', $response['order']['uuid'] );
  }

  /**
   * @param WC_Order $order
   *
   * @throws BillieException
   * @throws ResponseException
   */
  private function cancelOrder( WC_Order $order ) {
    $billieOrderId = get_post_meta( $order->get_id(), Plugin::ORDER_ID_KEY, true );

    $this->api->cancelOrder( $billieOrderId );
  }

  /**
   * @param WC_Order $order
   *
   * @throws BillieException
   * @throws ResponseException
   */
  private function completeOrder( WC_Order $order ) {
    $billieOrderId = get_post_meta( $order->get_id(), Plugin::ORDER_ID_KEY, true );

    $billie_order_data = [
      'invoice_number' => PaymentInfo::get_invoice_id( $order ),
      'invoice_url'    => $this->get_return_url( $order )
    ];

    $response = $this->api->shipOrder( $billieOrderId, $billie_order_data );

    update_post_meta( $order->get_id(), Plugin::SHIP_ORDER_REQUEST_RESPONSE, $response );
  }
}
