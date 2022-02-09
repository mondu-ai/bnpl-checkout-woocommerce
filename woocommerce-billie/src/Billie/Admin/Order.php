<?php


namespace Billie\Admin;


use Billie\Billie\PaymentInfo;
use WC_Order;

class Order {

  public function init() {
    add_action( 'add_meta_boxes', [ $this, 'add_payment_info_box' ] );

    if ( ! class_exists( \WC_GZDP_Invoice::class ) ) {
      add_action( 'add_meta_boxes', [ $this, 'add_invoice_box' ] );
      add_action( 'save_post', [ $this, 'save_invoice_id' ] );
    }
  }

  public function add_payment_info_box() {
    $order = $this->check_and_get_billie_order();

    if ( $order === null ) {
      return;
    }

    add_meta_box( 'billie_payment_info',
      __( 'Payment info', 'billie' ),
      static function () use ( $order ) {
        echo PaymentInfo::get_billie_payment_html( $order->get_id(), true );
      },
      'shop_order',
      'normal'
    );
  }

  public function add_invoice_box() {
    $order = $this->check_and_get_billie_order();

    if ( $order === null ) {
      return;
    }

    add_meta_box( 'billie_invoice_box',
      __( 'Invoice', 'billie' ),
      static function () use ( $order ) {
        $invoice_id = PaymentInfo::get_invoice_id( $order );
        $invoice_status = $order->get_status();
        include BILLIE_VIEW_PATH . '/admin/order-invoice.php';
      },
      'shop_order',
      'normal'
    );
  }

  public function save_invoice_id( $post_id ) {
    if ( array_key_exists( 'billie_invoice_id', $_POST ) ) {
      update_post_meta( $post_id, '_billie_invoice_id', esc_attr( $_POST['billie_invoice_id'] ) );
    }
  }

  private function check_and_get_billie_order() {
    global $post;

    if ( ! $post instanceof \WP_Post ) {
      return null;
    }

    if ( $post->post_type !== 'shop_order' ) {
      return null;
    }

    $order = new WC_Order( $post->ID );

    if ( $order->get_payment_method() !== 'billie' ) {
      return null;
    }

    return $order;
  }
}
