<?php

namespace Mondu\Mondu;

use Mondu\Plugin;
use Mondu\Mondu\Models\Token;
use Mondu\Mondu\Support\Helper;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use WC_Logger_Interface;

class Api {
  private $global_settings;

  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);
  }

  public function register() {
    register_setting('mondu', Plugin::OPTION_NAME);
  }

  /**
   * @param array $params
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_order(array $params) {
    $result = $this->post('/orders', $params);

    $response = json_decode($result['body'], true);

    WC()->session->set('mondu_order_id', $response['order']['uuid']);

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_order($mondu_uuid) {
    $result = $this->get(sprintf('/orders/%s', $mondu_uuid), null);

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function update_external_info($mondu_uuid, $params) {
    $result = $this->post(sprintf('/orders/%s/update_external_info', $mondu_uuid), $params);

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   * @param array $params
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function adjust_order($mondu_uuid, array $params) {
    $result = $this->post(sprintf('/orders/%s/adjust', $mondu_uuid), $params);

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function cancel_order($mondu_uuid) {
    $result = $this->post(sprintf('/orders/%s/cancel', $mondu_uuid));

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   * @param array $params
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function ship_order($mondu_uuid, array $params) {
    $result = $this->post(sprintf('/orders/%s/invoices', $mondu_uuid), $params);

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_invoices($mondu_uuid) {
    $result = $this->get(sprintf('/orders/%s/invoices', $mondu_uuid), null);

    return json_decode($result['body'], true);
  }

    /**
     * @param $mondu_order_uuid
     * @param $mondu_invoice_uuid
     * @return string
     * @throws MonduException
     * @throws ResponseException
     */
  public function get_invoice($mondu_order_uuid, $mondu_invoice_uuid) {
    $result = $this->get(sprintf('/orders/%s/invoices/%s', $mondu_order_uuid, $mondu_invoice_uuid), null);
    return json_decode($result['body'], true);
  }

  /**
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function webhook_secret() {
    $result = $this->get('/webhooks/keys', null);

    return json_decode($result['body'], true);
  }

  /**
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_webhooks() {
    $result = $this->get('/webhooks', null);

    return json_decode($result['body'], true);
  }

  /**
   * @param string $topic
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function register_webhook(string $topic) {
    $params = [
      'topic' => $topic,
      'address' => get_site_url() . '/?rest_route=/mondu/v1/webhooks/index'
    ];

    $result = $this->post('/webhooks', $params);

    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_uuid
   * @param $mondu_invoice_uuid
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function cancel_invoice($mondu_uuid, $mondu_invoice_uuid) {
    $result = $this->post(sprintf('/orders/%s/invoices/%s/cancel', $mondu_uuid, $mondu_invoice_uuid));
    return json_decode($result['body'], true);
  }

  /**
   * @param $mondu_invoice_uuid
   * @param array $credit_note
   * @return mixed
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_credit_note($mondu_invoice_uuid, array $credit_note) {
      $result = $this->post(sprintf('/invoices/%s/credit_notes', $mondu_invoice_uuid), $credit_note);
      return json_decode($result['body'], true);
  }

  /**
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function get_payment_methods() {
    $result = $this->get('/payment_methods', null);

    return json_decode($result['body'], true);
  }

  /**
   * @param $path
   * @param array|string|null $body
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function post($path, array $body = null) {
    $method = 'POST';

    return $this->request($path, $method, $body);
  }

  /**
   * @param $path
   * @param array|string|null $body
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function put($path, array $body = null) {
    $method = 'PUT';

    return $this->request($path, $method, $body);
  }

  /**
   * @param $path
   * @param array|null $body
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function patch($path, array $body = null) {
    $method = 'PATCH';

    return $this->request($path, $method, $body);
  }

  /**
   * @param $path
   * @param array|null $parameters
   * @param Token|null $token
   * @param bool $sandbox
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function get($path, $parameters = null) {
    if ($parameters !== null) {
      $path .= '&' . http_build_query($parameters);
    }

    $method = 'GET';

    return $this->request($path, $method);
  }

  /**
   * @param $result
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function validate_remote_result($url, $result) {
    Helper::log(array('code' => @$result['response']['code'], 'url' => $url, 'response' => @$result['body']));

    if ($result instanceof \WP_Error) {
      throw new MonduException(__($result->get_error_message(), $result->get_error_code()));
    }

    if (!is_array($result) || !isset($result['response'], $result['body']) || !isset($result['response']['code'], $result['response']['message'])) {
      throw new MonduException('Unexpected API response format');
    }
    if (strpos($result['response']['code'], '2') !== 0) {
      $message = $result['response']['message'];
      if (isset($result['body']['errors'], $result['body']['errors']['title'])) {
        $message = $result['body']['errors']['title'];
      }

      throw new ResponseException($message, $result['response']['code'], json_decode($result['body'], true));
    }

    return $result;
  }

  /**
   * @param $path
   * @param $body
   * @param $method
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function request($path, $method = 'GET', $body = null) {
    $url = $this->is_production() ? MONDU_PRODUCTION_URL : MONDU_SANDBOX_URL;
    $url .= $path;

    $headers = [
      'Content-Type' => 'application/json',
      'Api-Token'    => $this->global_settings['api_token'],
    ];

    $args = [
      'headers' => $headers,
      'method'  => $method,
      'timeout' => 30,
    ];

    if ($body !== null) {
      $args['body'] = json_encode($body);
    }

    Helper::log(['method' => $method, 'url' => $url, 'body' => @$args['body']]);

    return $this->validate_remote_result($url, wp_remote_request($url, $args));
  }

  /**
   * @return bool
   */
  private function is_production() {
    $is_production = false;
    if (
      is_array($this->global_settings) &&
      isset($this->global_settings['field_sandbox_or_production']) &&
      $this->global_settings['field_sandbox_or_production'] === 'production'
    ) {
      $is_production = true;
    }

    return $is_production;
  }
}
