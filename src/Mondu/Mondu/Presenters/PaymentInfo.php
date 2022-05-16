<?php

namespace Mondu\Mondu\Presenters;

use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Plugin;
use DateInterval;
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
    if ($this->order->get_payment_method() !== 'mondu') {
      return null;
    }

    if (!isset($this->order_data['bank_account'])) {
      return null;
    }

    $bank_account = $this->order_data['bank_account'];

    ob_start();

    ?>
      <section class="woocommerce-order-details mondu-payment">
        <p><?php printf(__('Kontoinhaber: %s', 'mondu'), $bank_account['account_holder']); ?></p>
        <p><?php printf(__('Bank: %s', 'mondu'), $bank_account['bank']); ?></p>
        <p><?php printf(__('IBAN: %s', 'mondu'), $bank_account['iban']); ?></p>
        <p><?php printf(__('BIC: %s', 'mondu'), $bank_account['bic']); ?></p>
      </section>
    <?php

    return ob_get_clean();
  }

  private function get_order() {
    return $this->mondu_request_wrapper->get_order($this->order->get_id());
  }
}
