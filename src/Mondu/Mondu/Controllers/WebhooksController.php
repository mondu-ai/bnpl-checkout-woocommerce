<?php

namespace Mondu\Mondu\Controllers;

use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Models\SignatureVerifier;
use Mondu\Mondu\Support\Helper;
use Mondu\Exceptions\MonduException;
use Mondu\Plugin;
use WC_Order;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

class WebhooksController extends WP_REST_Controller {
  /**
   * @var MonduRequestWrapper
   */
  private $mondu_request_wrapper;

  public function __construct() {
    $this->namespace = 'mondu/v1/webhooks';
    $this->mondu_request_wrapper = new MonduRequestWrapper();
  }

  // Register our routes
  public function register_routes() {
    register_rest_route($this->namespace, '/index', array(
      array(
        'methods' => 'POST',
        'callback' => array($this, 'index'),
        'permission_callback' => '__return_true'
      )
    ));
  }

  public function index(WP_REST_Request $request) {
    try {
      $verifier = new SignatureVerifier();

      $params = $request->get_json_params();
      $signature_payload = $request->get_header('X-MONDU-SIGNATURE');
      $signature = $verifier->create_hmac($params);
      $topic = @$params['topic'];

      Helper::log(array('webhook_topic' => $topic, 'params' => $params));

      if ($signature !== $signature_payload) {
        throw new MonduException(__('Signature mismatch.', 'mondu'));
      }

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
          throw new MonduException(__('Unregistered topic.', 'mondu'));
      }
    } catch (MonduException $e) {
      $this->mondu_request_wrapper->log_plugin_event($e, 'webhooks');
      $res_body = ['message' => $e->getMessage()];
      $res_status = 400;
    } catch (\Exception $e) {
      $this->mondu_request_wrapper->log_plugin_event($e, 'webhooks');
      $res_body = ['message' => __('Something happened on our end.', 'mondu')];
      $res_status = 200;
    }

    return new WP_REST_Response($res_body, $res_status);
  }

  private function handle_pending($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];

    if (!$woocommerce_order_id || !$mondu_order_id) {
      throw new MonduException(__('Required params missing.', 'mondu'));
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => __('Not Found', 'mondu')], 404];
    }

    Helper::log(array('woocommerce_order_id' => $woocommerce_order_id, 'mondu_order_id' => $mondu_order_id, 'state' => $params['order_state'], 'params' => $params));

    $order->update_status('wc-processing', __('Processing', 'woocommerce'));

    return [['message' => 'ok'], 200];
  }

  private function handle_confirmed($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];

    if (!$woocommerce_order_id || !$mondu_order_id) {
      throw new MonduException(__('Required params missing.', 'mondu'));
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => __('Not Found', 'mondu')], 404];
    }

    Helper::log(array('woocommerce_order_id' => $woocommerce_order_id, 'mondu_order_id' => $mondu_order_id, 'state' => $params['order_state'], 'params' => $params));

    $order->update_status('wc-completed', __('Completed', 'woocommerce'));

    return [['message' => 'ok'], 200];
  }

  private function handle_declined($params) {
    $woocommerce_order_id = $params['external_reference_id'];
    $mondu_order_id = $params['order_uuid'];

    if (!$woocommerce_order_id || !$mondu_order_id) {
      throw new MonduException(__('Required params missing.', 'mondu'));
    }

    $order = new WC_Order($woocommerce_order_id);

    if (!$order) {
      return [['message' => __('Not Found', 'mondu')], 404];
    }

    Helper::log(array('woocommerce_order_id' => $woocommerce_order_id, 'mondu_order_id' => $mondu_order_id, 'state' => $params['order_state'], 'params' => $params));

    $order->update_status('wc-failed', __('Failed', 'woocommerce'));

    $reason = $params['reason'];
    update_post_meta($woocommerce_order_id, Plugin::FAILURE_REASON_KEY, $reason);

    return [['message' => 'ok'], 200];
  }

  private function handle_invoice_payment($params) {
    $woocommerce_order_id = $params['external_reference_id'];

    if (!$woocommerce_order_id) {
      throw new MonduException(__('Required params missing.', 'mondu'));
    }

    if (function_exists('wcpdf_get_invoice')) {
      $invoice = wcpdf_get_invoice($woocommerce_order_id);

      if (!$invoice) {
        return [['message' => __('Not Found', 'mondu')], 404];
      }
    }

    // add invoice invoice payment action

    return [['message' => 'ok'], 200];
  }

  private function handle_invoice_canceled($params) {
    $woocommerce_order_id = $params['external_reference_id'];

    if (!$woocommerce_order_id) {
      throw new MonduException(__('Required params missing.', 'mondu'));
    }

    if (function_exists('wcpdf_get_invoice')) {
      $order = new WC_Order($woocommerce_order_id);
      $invoice = wcpdf_get_invoice($order);

      if (!$order || !$invoice) {
        return [['message' => __('Not Found', 'mondu')], 404];
      }
    }

    // add invoice invoice canceled action

    return [['message' => 'ok'], 200];
  }
}
