<?php


namespace Billie\Billie;


use Billie\Plugin;
use DateInterval;
use Exception;
use WC_Order;

class PaymentInfo {

  /**
   * @param $order_id
   *
   * @return null|array
   * @throws Exception
   */
  public static function get_billie_payment_info( $order_id ) {
    $order = new WC_Order( $order_id );

    if ( $order->get_payment_method() !== 'billie' ) {
      return null;
    }

    $billie_order_data = get_post_meta( $order_id, Plugin::ORDER_DATA_KEY, true );

    $date_completed    = null;
    $payment_goal_date = null;

    if ( function_exists( 'wc_gzdp_get_order_last_invoice' ) ) {

      /** @var \WC_GZDP_Invoice|null $invoice */
      $invoice = wc_gzdp_get_order_last_invoice( $order );

      if ( $invoice !== null ) {
        $date_completed = new \DateTime( $invoice->get_date( 'c' ) );
      }
    }

    if ( $date_completed === null ) {
      $date_completed = $order->get_date_completed();
    }

    if ( $date_completed !== null ) {
      $payment_goal_date = clone $date_completed;
      $billie_duration   = get_post_meta( $order_id, Plugin::DURATION_KEY, true );

      if ( (int) $billie_duration < 7 ) {
        $billie_duration = 7;
      }

      $duration_interval = new DateInterval( sprintf( 'P%sD', $billie_duration ) );

      $payment_goal_date->add( $duration_interval );
    }

    $billie_bank = self::get_bank( $billie_order_data['bank_account']['bic'] );

    return [
      'paymentGoal' => ( $payment_goal_date !== null ) ? $payment_goal_date->format( 'd.m.Y' ) : null,
      'recipient'   => get_bloginfo( 'name' ),
      'subject'     => self::get_invoice_id( $order ),
      'iban'        => $billie_order_data['bank_account']['iban'],
      'bic'         => $billie_order_data['bank_account']['bic'],
      'bank'        => $billie_bank,
    ];
  }

  /**
   * @param      $order_id
   * @param bool $metabox
   *
   * @return string
   * @throws Exception
   */
  public static function get_billie_payment_html( $order_id, $metabox = false ) {
    $payment_info = self::get_billie_payment_info( $order_id );

    if ( $payment_info === null ) {
      return '';
    }

    ob_start();

    ?>
        <section class="woocommerce-order-details billie-payment">
            <p>
        <?php
        if ( ! $metabox && $payment_info['paymentGoal'] !== null ) {
          printf( __( 'Bitte überweisen Sie den Rechnungsbetrag bis zum %s unter Angabe der Rechnungsnummer auf folgendes Konto:', 'billie' ),
            $payment_info['paymentGoal'] );
        }
        ?><br>

        <?php
        printf( __( 'Kontoinhaber: %s', 'billie' ), $payment_info['recipient'] );
        ?><br>

        <?php
        printf( __( 'IBAN: %s', 'billie' ), $payment_info['iban'] );
        ?><br>

        <?php
        printf( __( 'BIC: %s', 'billie' ), $payment_info['bic'] );
        ?><br>

        <?php
        printf( __( 'Bank: %s', 'billie' ), $payment_info['bank'] );
        ?><br>

        <?php
        if ( $payment_info['paymentGoal'] !== null ) {
          printf( __( 'Fälligkeitsdatum: %s', 'billie' ), $payment_info['paymentGoal'] );
        } else {
          echo __( 'Bestellung noch nicht versendet', 'billie' );
        }
        echo "<br>";
        ?>

        <?php
        printf( __( 'Verwendungszweck: %s', 'billie' ), $payment_info['subject'] );
        ?>
            </p>
        </section>
    <?php

    return ob_get_clean();
  }

  public static function get_bank( $bic ) {
    $bic            = strtoupper( $bic );
    $transient_name = sprintf( 'bank_%s', $bic );
    $bank           = get_transient( $transient_name );

    if ( $bank !== false && trim( $bank ) !== '' ) {
      return $bank;
    }

    if ( ! file_exists( MONDU_RESOURCES_PATH . '/bic-de.csv' ) ) {
      return $bic;
    }

    $bic_csv = fopen( MONDU_RESOURCES_PATH . '/bic-de.csv', 'rb' );

    $bank = null;

    while ( $bank === null && $line = fgetcsv( $bic_csv ) ) {
      if ( is_array( $line ) && count( $line ) > 1 && strtoupper( trim( $line[0] ) ) === $bic ) {
        $bank = strtoupper( trim( $line[1] ) );
      }
    }

    if ( $bank === null ) {
      return $bic;
    }

    set_transient( $transient_name, $bank, 86400 );

    return $bank;
  }

  /**
   * @param WC_Order $order
   *
   * @return int|string
   */
  public static function get_invoice_id( WC_Order $order ) {
    /**
     * WC-PDF Invoice ID
     */
    $invoice_id = get_post_meta( $order->get_id(), '_wcpdf_invoice_number', true );

    if ( $invoice_id !== false && $invoice_id !== 0 && trim( $invoice_id ) !== '' ) {
      return trim( $invoice_id );
    }

    $billie_invoice_id = get_post_meta( $order->get_id(), '_billie_invoice_id', true );

    if ( $billie_invoice_id !== false && $billie_invoice_id !== 0 && trim( $billie_invoice_id ) !== '' ) {
      return trim( $billie_invoice_id );
    }

    if ( ! function_exists( 'wc_gzdp_get_order_last_invoice' ) ) {
      return null;
    }

    $invoice = wc_gzdp_get_order_last_invoice( $order );

    if ( $invoice !== null ) {
      return $invoice->number_formatted;
    }


    return null;
  }

}
