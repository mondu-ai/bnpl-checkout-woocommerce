<?php

namespace Mondu\Mondu\Controllers;

use Mondu\Mondu\Models\SignatureVerifier;
use Mondu\Exceptions\MonduException;
use Mondu\Plugin;
use WP_Error;
use WC_Order;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

class WebhooksController extends WP_REST_Controller {
  public function __construct() {
    $this->namespace = 'mondu/v1/webhooks';
    $this->logger = wc_get_logger();
  }

  // Register our routes
  public function register_routes() {
    register_rest_route($this->namespace, '/index', array(
      array(
        'methods'  => 'POST',
        'callback' => array($this, 'index'),
        'permission_callback' => '__return_true'
     ),
   ));
  }

  public function index(WP_REST_Request $request) {
    try {
      $verifier = new SignatureVerifier();

      $params = $request->get_json_params();
      $signature_payload = $request->get_header('X-MONDU-SIGNATURE');
      $signature = $verifier->create_hmac($params);

      if (!$signature === $signature_payload) {
        throw new MonduException('Signature mismatch');
      }

      $topic = @$params['topic'];
      switch ($topic) {
        case 'order/pending':
          [$res_body, $res_status] = $this->handle_pending($params);
          break;
        case 'order/confirmed':
          [$res_body, $res_status] = $this->handle_confirmed($params);
          break;
        case 'order/declined':
          [$res_body, $res_status] = $this->handle_declined($params);
          break;
        case 'invoice/payment':
          [$res_body, $res_status] = $this->handle_invoice_payment($params);
          break;
        case 'invoice/canceled':
          [$res_body, $res_status] = $this->handle_invoice_canceled($params);
          break;
        default:
          throw new MonduException('Unregistered topic');
        }
      } catch (MonduException $e) {
        $res_body = ['message' => $e->getMessage()];
        $res_status = 400;
    }

    if (strpos($res_status, '2') === 0) {
      return new WP_REST_Response($res_body, 200);
    } else {
      return new WP_Error($res_body, array('status' => $res_status));
    }
  }

  private function handle_pending($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];

    if (!$woocommerce_order_id || !$mondu_order_id) {
      throw new MonduException('Required params missing');
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => 'not found'], 404];
    }

    $this->logger->debug('changing order status', [
      'woocommerce_order_id' => $woocommerce_order_id,
      'mondu_order_id' => $mondu_order_id,
      'state' => $params['order_state'],
      'params' => $params,
    ]);

    $order->update_status('wc-processing', __('Processing', 'woocommerce'));

    return [['message' => 'ok'], 200];
  }

  private function handle_confirmed($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];

    if (!$woocommerce_order_id || !$mondu_order_id) {
      throw new MonduException('Required params missing');
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => 'not found'], 404];
    }

    $this->logger->debug('changing order status', [
      'woocommerce_order_id' => $woocommerce_order_id,
      'mondu_order_id' => $mondu_order_id,
      'state' => $params['order_state'],
      'params' => $params,
    ]);

    $order->update_status('wc-completed', __('Completed', 'woocommerce'));

    return [['message' => 'ok'], 200];
  }

  private function handle_declined($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];

    if (!$woocommerce_order_id || !$mondu_order_id) {
      throw new MonduException('Required params missing');
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => 'not found'], 404];
    }

    $this->logger->debug('changing order status', [
      'woocommerce_order_id' => $woocommerce_order_id,
      'mondu_order_id' => $mondu_order_id,
      'state' => $params['order_state'],
      'params' => $params,
    ]);

    $order->update_status('wc-failed', __('Failed', 'woocommerce'));

    $reason = $params['reason'];
    update_post_meta($woocommerce_order_id, Plugin::FAILURE_REASON_KEY, $reason);

    return [['message' => 'ok'], 200];
  }

  private function handle_invoice_payment($params) {
    $woocommerce_order_id = $params['external_reference_id'];

    if (!$woocommerce_order_id) {
      throw new MonduException('Required params missing');
    }

    $invoice = wcpdf_get_invoice($woocommerce_order_id);

    if (!$invoice) {
      return [['message' => 'not found'], 404];
    }

    update_post_meta($woocommerce_order_id, Plugin::INVOICE_PAID_KEY, true);

    return [['message' => 'ok'], 200];
  }

  private function handle_invoice_canceled($params) {
    $woocommerce_order_id = $params['external_reference_id'];

    if (!$woocommerce_order_id) {
      throw new MonduException('Required params missing');
    }

    $order = new WC_Order($woocommerce_order_id);
    $invoice = wcpdf_get_invoice($order);

    if (!$order || !$invoice) {
      return [['message' => 'not found'], 404];
    }

    update_post_meta($woocommerce_order_id, Plugin::INVOICE_CANCELED_KEY, true);

    return [['message' => 'ok'], 200];
  }
}
