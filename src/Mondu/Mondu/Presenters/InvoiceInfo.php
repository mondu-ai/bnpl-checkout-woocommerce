<?php

namespace Mondu\Mondu\Presenters;

use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Plugin;
use DateInterval;
use Exception;
use WC_Order;

class InvoiceInfo {
  private $order;
  private $invoice_data;
  private $mondu_request_wrapper;

  /**
   * @param $order_id
   */
  public function __construct($order_id) {
    $this->order = new WC_Order($order_id);
    $this->mondu_request_wrapper = new MonduRequestWrapper();
    $this->invoices_data = $this->get_invoices();
  }

  /**
   * @param $order_id
   *
   * @return string
   * @throws Exception
   */
  public function get_mondu_invoice_html() {
    if ($this->order->get_payment_method() !== 'mondu') {
      return null;
    }

    if (!isset($this->invoices_data)) {
      return null;
    }

    foreach ($this->invoices_data as $key => $invoice_data) {
    }

    ob_start();

    ?>
    <?php

    return ob_get_clean();
  }

  private function get_invoices() {
    return $this->mondu_request_wrapper->get_invoices($this->order->get_id());
  }
}
