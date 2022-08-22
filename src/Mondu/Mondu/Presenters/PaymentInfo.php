<?php

namespace Mondu\Mondu\Presenters;

use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Plugin;
use Exception;
use WC_Order;

class PaymentInfo {
  private $order;
  private $order_data;
  private $mondu_request_wrapper;

  /**
   * @param $order_id
   */
  public function __construct($order_id) {
    $this->order = new WC_Order($order_id);
    $this->mondu_request_wrapper = new MonduRequestWrapper();
    $this->order_data = $this->get_order();
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
          <span><strong><?php printf(__('Kontoinhaber:', 'mondu')); ?></strong></span>
          <span><?php printf($bank_account['bank']); ?></span>
        </p>
        <p>
          <span><strong><?php printf(__('Bank:', 'mondu')); ?></strong></span>
          <span><?php printf($bank_account['bank']); ?></span>
        </p>
        <p>
          <span><strong><?php printf(__('IBAN:', 'mondu')); ?></strong></span>
          <span><?php printf($bank_account['iban']); ?></span>
        </p>
        <p>
          <span><strong><?php printf(__('BIC:', 'mondu')); ?></strong></span>
          <span><?php printf($bank_account['bic']); ?></span>
        </p>
      </section>
    <?php

    return ob_get_clean();
  }

  /**
   * @return string
   * @throws Exception
   */
  public function get_mondu_section_html() {
    $invoice_data = $this->get_invoice();
    if (!in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS)) {
      return null;
    }

    ob_start();

    if ($this->order_data && isset($this->order_data['bank_account'])) {
      $order_data = $this->order_data;
      ?>
        <section class="woocommerce-order-details mondu-payment">
            <p>
                <span><strong><?php printf(__('State:', 'mondu')); ?></strong></span>
                <span><?php printf($order_data['state']); ?></span>
            </p>
            <p>
                <span><strong><?php printf(__('Mondu id:', 'mondu')); ?></strong></span>
                <span><?php printf($order_data['uuid']); ?></span>
            </p>
            <?php
                if(in_array($this->order_data['state'], ['confirmed', 'partially_shipped'])) {
                    ?>
                        <button <?php $order_data['state'] === 'canceled' ? printf('disabled') : ''?> data-mondu='<?php echo(json_encode(['order_id' => $this->order->get_id()])) ?>' id="mondu-create-invoice-button" type="submit" class="button grant_access">
                            <?php printf(__('Invoice order', 'mondu')); ?>
                        </button>
                    <?php
                }
            ?>
        </section>
        <hr>
        <?php printf($this->get_mondu_payment_html()) ?>
        <?php printf($this->get_mondu_invoice_html($invoice_data, $order_data['uuid'])) ?>
      <?php
    } else {
      ?>
        <section class="woocommerce-order-details mondu-payment">
          <p>
            <span><strong>Corrupt Mondu Order!</strong></span>
          </p>
        </section>
      <?php
    }

    return ob_get_clean();
  }

  public function get_mondu_invoice_html($invoice_data, $mondu_order_id = null) {

    ob_start();

      if($invoice_data) {
          $currency = $invoice_data['order']['currency'];
      ?>
        <hr>
        <p>
          <span><strong><?php printf(__('Invoice state:', 'mondu')); ?></strong></span>
          <?php printf($invoice_data['state']) ?>
        </p>
        <p>
          <span><strong><?php printf(__('Invoice number:', 'mondu')); ?></strong></span>
          <?php printf($invoice_data['invoice_number']) ?>
        </p>
        <p>
          <span><strong><?php printf(__('Total:', 'mondu')); ?></strong></span>
          <?php printf('%s %s', ($invoice_data['gross_amount_cents'] / 100), $currency) ?>
        </p>
        <p>
          <span><strong><?php printf(__('Paid out:', 'mondu')); ?></strong></span>
          <?php printf($invoice_data['paid_out'] ? 'Yes' : 'No') ?>
        </p>
        <div>
            <?php
                if($invoice_data['credit_notes']) {
                    printf('<hr>');
                }
                foreach ($invoice_data['credit_notes'] as $note) {
                    ?>
                        <p>
                            <span>
                                <strong><?php printf(__('Credit Note number', 'mondu')); ?></strong>
                                <?php printf($note['external_reference_id']) ?>
                            </span>
                        </p>
                        <p>
                            <span>
                                <strong><?php printf(__('Total: ', 'mondu')); ?></strong>
                                <?php printf('%s %s', ($note['gross_amount_cents'] / 100), $currency) ?>
                            </span>
                        </p>
                    <?php
                }
            ?>
        </div>
        <button <?php $invoice_data['state'] === 'canceled' ? printf('disabled') : ''?> data-mondu='<?php echo(json_encode(['invoice_id' => $invoice_data['uuid'], 'order_id' => $mondu_order_id])) ?>' id="mondu-cancel-invoice-button" type="submit" class="button grant_access">
            <?php printf(__('Cancel Invoice', 'mondu')); ?>
        </button>
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

  private function get_invoice() {
      try {
          return $this->mondu_request_wrapper->get_invoice($this->order->get_id());
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
