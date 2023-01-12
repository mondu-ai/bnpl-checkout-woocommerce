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
    $this->order_data = $this->get_order();
    $this->invoices_data = $this->get_invoices();
  }

  public function get_order_data() {
    return $this->order_data;
  }

  public function get_invoices_data() {
    return $this->invoices_data;
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
  public function get_mondu_payment_html() {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    if (!isset($this->order_data['bank_account'])) {
      return null;
    }

    $bank_account = $this->order_data['bank_account'];

    ob_start();

    ?>
      <section class="woocommerce-order-details mondu-payment">
        <p>
          <span><strong><?php _e('Account holder', 'mondu'); ?>:</strong></span>
          <span><?php printf($bank_account['account_holder']); ?></span>
        </p>
        <p>
          <span><strong><?php _e('Bank', 'mondu'); ?>:</strong></span>
          <span><?php printf($bank_account['bank']); ?></span>
        </p>
        <p>
          <span><strong><?php _e('IBAN', 'mondu'); ?>:</strong></span>
          <span><?php printf($bank_account['iban']); ?></span>
        </p>
        <p>
          <span><strong><?php _e('BIC', 'mondu'); ?>:</strong></span>
          <span><?php printf($bank_account['bic']); ?></span>
        </p>
      </section>
    <?php

    return ob_get_clean();
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
  public function get_mondu_wcpdf_section_html() {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    ob_start();

    if ($this->order_data && isset($this->order_data['bank_account'])) {
      $order_data = $this->order_data;
      ?>
        <p>
          <strong>
            <?php _e('Please transfer your invoice exclusively to the following bank account', 'mondu'); ?>:
          </strong>
        </p>
        <br>
        <?php printf($this->get_mondu_payment_html()) ?>
        <?php printf($this->get_mondu_net_terms()) ?>
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

  private function get_mondu_net_terms() {
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    ob_start();

    if ($this->order_data && isset($this->order_data['authorized_net_term'])) {
      $order_data = $this->order_data;
      ?>
        <p>
          <strong>
            <?php printf(__('Your authorized net term is %s days from delivery date', 'mondu'), $order_data['authorized_net_term']); ?>.
          </strong>
        </p>
      <?php
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
