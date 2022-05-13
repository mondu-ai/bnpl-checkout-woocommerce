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

      $topic = $params['topic'];
      switch ($topic) {
        case 'order/pending':
          [$res_body, $res_status] = $this->handle_pending($params);
          break;
        case 'order/declined':
          [$res_body, $res_status] = $this->handle_declined($params);
          break;
        default:
          throw new MonduException('Unregistered topic');
        }
      } catch (MonduException $e) {
        $res_body = ['message' => $e->getMessage()];
        $res_status = 400;
    }

    $this->logger->debug('result', [
      'body' => $res_body,
      'status' => $res_status,
      'params' => $params,
   ]);

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

    $this->logger->debug('changing status', [
      'woocommerce_order_id' => $woocommerce_order_id,
      'mondu_order_id' => $mondu_order_id,
      'state' => $params['order_state'],
      'params' => $params,
   ]);

    $order->update_status('wc-processing', __('Processing', 'woocommerce'));

    return [['message' => 'ok'], 200];
  }

  public function handle_declined($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];
    $mondu_order_state = $params['order_state'];

    if (!$woocommerce_order_id || !$mondu_order_id || !$mondu_order_state) {
      throw new MonduException('Required params missing');
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => 'not found'], 404];
    }

    $this->logger->debug('changing status', [
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
}
