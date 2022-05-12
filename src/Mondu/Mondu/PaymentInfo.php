<?php

namespace Mondu\Mondu;

use Mondu\Plugin;
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
  public static function get_mondu_payment_info($order_id) {
    $order = new WC_Order($order_id);

    if ($order->get_payment_method() !== 'mondu') {
      return null;
    }

    $bank_account = get_post_meta($order_id, Plugin::BANK_ACCOUNT_KEY, true);

    if ($bank_account == null) {
      return null;
    }

    return [
      'account_holder' => $bank_account['account_holder'],
      'bank' => $bank_account['bank'],
      'iban' => $bank_account['iban'],
      'bic' => $bank_account['bic'],
   ];
  }

  /**
   * @param $order_id
   *
   * @return string
   * @throws Exception
   */
  public static function get_mondu_payment_html($order_id) {
    $payment_info = self::get_mondu_payment_info($order_id);

    if ($payment_info === null) {
      return '';
    }

    ob_start();

    ?>
      <section class="woocommerce-order-details mondu-payment">
        <p><?php printf(__('Kontoinhaber: %s', 'mondu'), $payment_info['account_holder']); ?></p>
        <p><?php printf(__('Bank: %s', 'mondu'), $payment_info['bank']); ?></p>
        <p><?php printf(__('IBAN: %s', 'mondu'), $payment_info['iban']); ?></p>
        <p><?php printf(__('BIC: %s', 'mondu'), $payment_info['bic']); ?></p>
      </section>
    <?php

    return ob_get_clean();
  }

  /**
   * @param WC_Order $order
   *
   * @return int|string
   */
  public static function get_invoice_id(WC_Order $order) {
    /**
     * WC-PDF Invoice ID
     */
    $invoice_id = get_post_meta($order->get_id(), '_wcpdf_invoice_number', true);

    if ($invoice_id !== false && $invoice_id !== 0 && trim($invoice_id) !== '') {
      return trim($invoice_id);
    }

    $mondu_invoice_id = get_post_meta($order->get_id(), '_mondu_invoice_id', true);

    if ($mondu_invoice_id !== false && $mondu_invoice_id !== 0 && trim($mondu_invoice_id) !== '') {
      return trim($mondu_invoice_id);
    }

    if (!function_exists('wc_gzdp_get_order_last_invoice')) {
      return null;
    }

    $invoice = wc_gzdp_get_order_last_invoice($order);

    if ($invoice !== null) {
      return $invoice->number_formatted;
    }

    return null;
  }
}
