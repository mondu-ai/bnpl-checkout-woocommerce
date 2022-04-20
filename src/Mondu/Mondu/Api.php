<?php

namespace Mondu\Mondu;

use Mondu\Mondu\Api\Token;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\CredentialsNotSetException;
use Mondu\Exceptions\ResponseException;
use WC_Logger_Interface;

class Api {
  const OPTION_NAME = 'mondu_account';

  private $options;

  private $logger;

  public function __construct() {
    $this->options = get_option( self::OPTION_NAME );
    $this->logger  = wc_get_logger();
  }

  public function register() {
    register_setting( 'mondu', self::OPTION_NAME );
  }

  /**
   * @throws MonduException
   * @throws CredentialsNotSetException
   * @throws ResponseException
   */
  public function validateCredentials() {
    if ( ! isset( $this->options ) || ! is_array( $this->options ) || ! isset( $this->options['client_id'], $this->options['client_secret'] ) ) {
      throw new CredentialsNotSetException( __( 'Missing Credentials', 'mondu' ) );
    }

    $oauthToken = $this->requestOAuthToken( $this->options['client_id'], $this->options['client_secret'], $this->isSandbox() );

    $result = $this->post( '/oauth/authorization', null, $oauthToken, $this->isSandbox() );

    $validationResult = json_decode( $result['body'], true );

    if ( ! isset( $validationResult['client_id'] ) ) {
      throw new MonduException( __( 'Unexpected validation format', 'mondu' ) );
    }

    if ( $validationResult['client_id'] !== $this->options['client_id'] ) {
      throw new MonduException( __( 'Unexpected validation client id', 'mondu' ) );
    }
  }

  /**
   * @param $client_id
   * @param $client_secret
   * @param bool $sandbox
   *
   * @return Token
   * @throws MonduException
   * @throws ResponseException
   */
  private function requestOAuthToken( $client_id, $client_secret, $sandbox = false ) {
    $body = [
      'grant_type'    => 'client_credentials',
      'client_id'     => $client_id,
      'client_secret' => $client_secret
    ];

    $result = $this->post( '/oauth/token', $body, null, $sandbox, false );
    // print_r($result);
    $tokenResult = json_decode( $result['body'], true );

    if ( ! isset( $tokenResult['expires_in'], $tokenResult['access_token'] ) ) {
      throw new MonduException( 'Unexpected Token format' );
    }

    return new Token( $tokenResult['access_token'], $tokenResult['expires_in'] );
  }

  /**
   * @param $params
   *
   * @return string
   * @throws MonduException
   * @throws ResponseException
   */
  public function createOrder( $params ) {

    $oauthToken = $this->requestOAuthToken( $this->options['client_id'], $this->options['client_secret'], $this->isSandbox() );

    $result = $this->post( '/orders', $params, $oauthToken, $this->isSandbox(), true );


    $response = json_decode( $result['body'], true );

    WC()->session->set( 'mondu_order_id', $response['order']['uuid'] );

    return json_decode( $result['body'], true );
  }

  /**
   * @param $mondu_uuid
   * @param array $mondu_order_data
   *
   * @throws MonduException
   * @throws ResponseException
   */


  /**
   * @param $mondu_uuid
   *
   * @throws MonduException
   * @throws ResponseException
   */


  /**
   * @param $mondu_uuid
   * @param array $mondu_order_data
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */


  /**
   * @param $path
   * @param array|string|null $body
   * @param Token|null $token
   * @param bool $sandbox
   *
   * @param bool $json_request
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function post( $path, array $body = null, $token = null, $sandbox = false, $json_request = true ) {
    $method = 'POST';

    return $this->request( $path, $body, $json_request, $token, $method, $sandbox );
  }

  /**
   * @param $path
   * @param array|string|null $body
   * @param Token|null $token
   * @param bool $sandbox
   *
   * @param bool $json_request
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function put( $path, array $body = null, $token = null, $sandbox = false, $json_request = true ) {
    $method = 'PUT';

    return $this->request( $path, $body, $json_request, $token, $method, $sandbox );
  }

  /**
   * @param $path
   * @param array|null $body
   * @param null $token
   * @param bool $sandbox
   * @param bool $json_request
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function patch( $path, array $body = null, $token = null, $sandbox = false, $json_request = true ) {
    $method = 'PATCH';

    return $this->request( $path, $body, $json_request, $token, $method, $sandbox );
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
  private function get( $path, $parameters = null, $token = null, $sandbox = false ) {
    if ( $parameters !== null ) {
      $path .= '&' . http_build_query( $parameters );
    }

    $method = 'GET';

    return $this->request( $path, null, false, $token, $method, $sandbox );
  }


  /**
   * @param $result
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function validateRemoteResult( $result ) {
    $this->logger->debug( 'validating', [
      'result' => $result,
    ] );




    if ( $result instanceof \WP_Error ) {
      throw new MonduException( __( $result->get_error_message(), $result->get_error_code() ) );
    }

    if ( ! is_array( $result ) || ! isset( $result['response'], $result['body'] ) || ! isset( $result['response']['code'], $result['response']['message'] ) ) {
      throw new MonduException( 'Unexpected API response format' );
    }

    if ( strpos( $result['response']['code'], "2" ) !== 0 ) {

      $message = $result['response']['message'];
      if ( isset( $result['body']['errors'], $result['body']['errors']['title'] ) ) {
        $message = $result['body']['errors']['title'];
      }

      throw new ResponseException( $message, $result['response']['code'], json_decode( $result['body'], true ) );

    }

    return $result;
  }

  /**
   * @return bool
   */
  private function isSandbox() {
    $isSandbox = true;
    if (
      is_array( $this->options ) &&
      isset( $this->options['field_sandbox_or_production'] ) &&
      $this->options['field_sandbox_or_production'] === 'production'
    ) {
      $isSandbox = false;
    }

    return $isSandbox;
  }

  /**
   * @param $path
   * @param $body
   * @param $json_request
   * @param Token $token
   * @param $method
   * @param bool $sandbox
   *
   * @return array
   * @throws MonduException
   * @throws ResponseException
   */
  private function request( $path, $body, $json_request = false, Token $token = null, $method = 'PUT', $sandbox = false ) {
    $url = $sandbox ? MONDU_SANDBOX_URL : MONDU_PRODUCTION_URL;
    $url .= $path;

    $this->logger->debug( 'request', [
      'path'         => $path,
      'url'          => $url,
      'body'         => $body,
      'json_request' => $json_request,
      'token'        => $token,
      'method'       => $method,
      'sandbox'      => $sandbox
    ] );

    $headers = [];

    if ( $token !== null ) {
      $headers['Authorization'] = sprintf( 'Bearer %s', $token->getAccessToken() );
    }

    if ( $json_request ) {
      $headers['Content-Type'] = 'application/json; charset=utf-8';
      $body                    = json_encode( $body );
    }

    $args = [
      'body'    => $body,
      'headers' => $headers,
      'method'  => $method,
      'timeout' => 30,
    ];

    if ( $json_request ) {
      $args['data_format'] = $body;
    }

    return $this->validateRemoteResult( wp_remote_request( $url, $args ) );
  }

}