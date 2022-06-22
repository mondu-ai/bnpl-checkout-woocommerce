<?php

namespace Mondu\Mondu;

use Mondu\Mondu\Models\Token;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\CredentialsNotSetException;
use Mondu\Exceptions\ResponseException;
use WC_Logger_Interface;

class Api {
  const OPTION_NAME = 'mondu_account';

  private $options;

  private $logger;

  public function __construct() {
    $this->options = get_option(self::OPTION_NAME);
    $this->logger  = wc_get_logger();
  }

  public function register() {
    register_setting('mondu', self::OPTION_NAME);
  }

  /**
   * @param array $params
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function create_order(array $params) {
    $result = $this->post('/orders', $params, );

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
    $result = $this->post(sprintf('/orders/%s/update_external_info', $mondu_uuid), $params, );

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
    $result = $this->post(sprintf('/orders/%s/adjust', $mondu_uuid), $params, );

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
    $result = $this->post(sprintf('/orders/%s/cancel', $mondu_uuid), [], );

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
    $result = $this->post(sprintf('/orders/%s/invoices', $mondu_uuid), $params, );

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
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function webhook_secret() {
    $result = $this->get('/webhooks/keys', null);

    return json_decode($result['body'], true);
  }

  /**
   * @param array $params
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function register_webhook(array $params) {
    $result = $this->post('/webhooks', $params);

    return json_decode($result['body'], true);
  }

  /**
   * @throws CredentialsNotSetException
   */
  public function validate_credentials() {
    if (!isset($this->options) || !is_array($this->options) || !isset($this->options['api_token'], $this->options['webhooks_secret'])) {
      throw new CredentialsNotSetException(__('Missing Credentials', 'mondu'));
    }
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

    return $this->request($path, $body, $method);
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

    return $this->request($path, $body, $method);
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

    return $this->request($path, $body, $method);
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

    return $this->request($path, null, $method);
  }

  /**
   * @param $result
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function validate_remote_result($result) {
  //   $this->logger->debug('validating', [
  //     'result' => $result,
  //  ]);

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
  private function request($path, $body, $method = 'GET') {
    $url = $this->is_production() ? MONDU_PRODUCTION_URL : MONDU_SANDBOX_URL;
    $url .= $path;

    $headers = [
      'Content-Type' => 'application/json',
      'Api-Token'    => $this->options['api_token'],
    ];

    $args = [
      'body'    => json_encode($body),
      'headers' => $headers,
      'method'  => $method,
      'timeout' => 30,
   ];

    // if ($json_request) {
    //   $args['data_format'] = $body;
    // }

    return $this->validate_remote_result(wp_remote_request($url, $args));
  }

  /**
   * @return bool
   */
  private function is_production() {
    $is_production = false;
    if (
      is_array($this->options) &&
      isset($this->options['field_sandbox_or_production']) &&
      $this->options['field_sandbox_or_production'] === 'production'
   ) {
      $is_production = true;
    }

    return $is_production;
  }
}
