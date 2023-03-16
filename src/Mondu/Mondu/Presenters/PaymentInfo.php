<?php

namespace Mondu\Mondu\Presenters;

use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Plugin;
use Exception;
use WC_Order;

class PaymentInfo {
  private WC_Order $order;
  private MonduRequestWrapper $mondu_request_wrapper;
  private array $order_data;
  private array $invoices_data;

  /**
   * @param $order_id
   */
  public function __construct($order_id) {
    $this->order = new WC_Order($order_id);
    $this->mondu_request_wrapper = new MonduRequestWrapper();
    $order_data = $this->get_order();
    if (!$order_data)
      $order_data = array();

    $this->order_data = $order_data;

    $invoices_data = $this->get_invoices();
    if (!$invoices_data)
      $invoices_data = array();

    $this->invoices_data = $invoices_data;
  }

  public function get_order_data() {
    return $this->order_data;
  }

  public function get_invoices_data() {
    return $this->invoices_data;
  }

  public function get_wcpdf_shop_name() {
    $wcpdf = \WPO_WCPDF::instance();

    return $wcpdf->documents->documents['\WPO\WC\PDF_Invoices\Documents\Invoice']->get_shop_name() ?? get_bloginfo('name');
  }

  /**
   * @return string
   * @throws Exception
   */
  public function get_mondu_section_html() {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    ob_start();

    if ($this->order_data && isset($this->order_data['bank_account'])) {
      $order_data = $this->order_data;
      ?>
        <section class="woocommerce-order-details mondu-payment">
          <p>
            <span><strong><?php _e('Order state', 'mondu'); ?>:</strong></span>
            <span><?php printf($order_data['state']); ?></span>
          </p>
          <p>
            <span><strong><?php _e('Mondu ID', 'mondu'); ?>:</strong></span>
            <span><?php printf($order_data['uuid']); ?></span>
          </p>
          <?php
            if (in_array($this->order_data['state'], ['confirmed', 'partially_shipped'])) {
              ?>
                <?php $mondu_data = [
                  'order_id' => $this->order->get_id(),
                ]; ?>
                <button data-mondu='<?php echo(json_encode($mondu_data)) ?>' id="mondu-create-invoice-button" type="submit" class="button grant_access">
                  <?php _e('Create Invoice', 'mondu'); ?>
                </button>
              <?php
            }
          ?>
        </section>
        <hr>
        <?php printf($this->get_mondu_payment_html()) ?>
        <?php printf($this->get_mondu_invoices_html()) ?>
      <?php
    } else {
      ?>
        <section class="woocommerce-order-details mondu-payment">
          <p>
            <span><strong><?php _e('Corrupt Mondu order!', 'mondu'); ?></strong></span>
          </p>
        </section>
      <?php
    }

    return ob_get_clean();
  }

  /**
   * @param $order_id
   *
   * @return string
   * @throws Exception
   */
  public function get_mondu_payment_html($pdf=false) {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    if (!isset($this->order_data['bank_account'])) {
      return null;
    }

    $bank_account = $this->order_data['bank_account'];

    if ($pdf) {
      if (function_exists('wcpdf_get_document')) {
        $document = wcpdf_get_document('invoice', $this->order, false);
        $invoice_number = $document->get_number()->get_formatted();
      } else {
        $invoice_number = $this->order->get_order_number();
      }

      $invoice_number = apply_filters('mondu_invoice_reference_id', $invoice_number);
    }

    ob_start();

    ?>
      <style>
        .mondu-payment > table > tbody > tr > td {
          min-width: 130px;
        }
      </style>
      <section class="woocommerce-order-details mondu-payment">
        <table>
          <tr>
            <td><strong><?php _e('Account holder', 'mondu'); ?>:</strong></td>
            <td><?php printf($bank_account['account_holder']); ?></span></td>
          </tr>
          <tr>
            <td><strong><?php _e('Bank', 'mondu'); ?>:</strong></td>
            <td><?php printf($bank_account['bank']); ?></td>
          </tr>
          <tr>
            <td><strong><?php _e('IBAN', 'mondu'); ?>:</strong></td>
            <td><?php printf($bank_account['iban']); ?></td>
          </tr>
          <tr>
            <td><strong><?php _e('BIC', 'mondu'); ?>:</strong></td>
            <td><?php printf($bank_account['bic']); ?></td>
          </tr>
          <?php if ($pdf) { ?>
          <tr>
            <td><strong><?php _e('Purpose', 'mondu'); ?>:</strong></td>
            <td><?php echo __('Invoice number', 'mondu'). ' '. $invoice_number. ' ' . $this->get_wcpdf_shop_name() ?></td>
          </tr>
          <?php } ?>
          <?php if ($this->get_mondu_net_term()) { ?>
            <td><strong><?php _e('Payment term', 'mondu'); ?>:</strong></td>
            <td><?php /* translators: %s: Days */printf(__('%s Days', 'mondu'), $this->get_mondu_net_term()); ?></td>
          <?php } ?>
        </table>
      </section>
    <?php

    return ob_get_clean();
  }

  public function get_mondu_net_term() {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    if ($this->order_data && isset($this->order_data['authorized_net_term'])) {
      return $this->order_data['authorized_net_term'];
    }

    return null;
  }

  public function get_mondu_invoices_html() {
    ob_start();

    foreach ($this->invoices_data as $invoice) {
      ?>
        <hr>
        <p>
          <span><strong><?php _e('Invoice state', 'mondu'); ?>:</strong></span>
          <?php printf($invoice['state']) ?>
        </p>
        <p>
          <span><strong><?php _e('Invoice number', 'mondu'); ?>:</strong></span>
          <?php printf($invoice['invoice_number']) ?>
        </p>
        <p>
          <span><strong><?php _e('Total', 'mondu'); ?>:</strong></span>
          <?php printf('%s %s', ($invoice['gross_amount_cents'] / 100), $invoice['order']['currency']) ?>
        </p>
        <p>
          <span><strong><?php _e('Paid out', 'mondu'); ?>:</strong></span>
          <?php printf($invoice['paid_out'] ? __('Yes', 'mondu') : __('No', 'mondu')) ?>
        </p>
        <div>
          <?php printf($this->get_mondu_credit_note_html($invoice)) ?>
        </div>
          <?php
            if (in_array($invoice['state'], ['created', 'canceled'])) {
              ?>
                <?php $mondu_data = [
                  'order_id' => $this->order->get_id(),
                  'invoice_id' => $invoice['uuid'],
                  'mondu_order_id' => $this->order_data['uuid'],
                ]; ?>
                <button <?php $invoice['state'] === 'canceled' ? printf('disabled') : ''?> data-mondu='<?php echo(json_encode($mondu_data)) ?>' id="mondu-cancel-invoice-button" type="submit" class="button grant_access">
                  <?php _e('Cancel Invoice', 'mondu'); ?>
                </button>
              <?php
            }
          ?>
      <?php
    }

    return ob_get_clean();
  }

  public function get_mondu_credit_note_html($invoice) {
    ob_start();

    foreach ($invoice['credit_notes'] as $note) {
      ?>
        <p>
          <span><strong><?php _e('Credit Note number', 'mondu'); ?>:</strong></span>
          <?php printf($note['external_reference_id']) ?>
        </p>
        <p>
          <span><strong><?php _e('Total', 'mondu'); ?>:</strong></span>
          <?php printf('%s %s', ($note['gross_amount_cents'] / 100), $invoice['order']['currency']) ?>
        </p>
      <?php
    }

    return ob_get_clean();
  }

  /**
   * @return string
   * @throws Exception
   */
  public function get_mondu_wcpdf_section_html($pdf=false) {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    ob_start();

    if ($this->order_data && isset($this->order_data['bank_account'])) {
      $order_data = $this->order_data;
      ?>
        <?php printf($this->get_mondu_payment_notice($this->order->get_payment_method())) ?>
        <?php if($this->order->get_payment_method() === 'mondu_invoice') printf($this->get_mondu_payment_html($pdf)) ?>
      <?php
    } else {
      ?>
        <section class="woocommerce-order-details mondu-payment">
          <p>
            <span><strong><?php _e('Corrupt Mondu order!', 'mondu'); ?></strong></span>
          </p>
        </section>
      <?php
    }

    return ob_get_clean();
  }
  private function get_mondu_payment_notice($payment_method) {
    ob_start();

    $file = MONDU_VIEW_PATH. '/pdf/mondu_invoice_section.php';

    //used in the file that is included
    $wcpdfShopName = $this->get_wcpdf_shop_name();
    if (file_exists($file)) {
      include($file);
    }

    return ob_get_clean();
  }

  private function get_invoices() {
    try {
      return $this->mondu_request_wrapper->get_invoices($this->order->get_id());
    } catch (ResponseException $e) {
      return false;
    }
  }

  private function get_order() {
    try {
      return $this->mondu_request_wrapper->get_order($this->order->get_id());
    } catch (ResponseException $e) {
      return false;
    }
  }
}
