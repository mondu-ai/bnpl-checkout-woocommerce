<?php


namespace Billie\Billie;


use Billie\Billie\Api\Token;
use Billie\Exceptions\BillieException;
use Billie\Exceptions\CredentialsNotSetException;
use Billie\Exceptions\ResponseException;
use WC_Logger_Interface;

class Api {
  const OPTION_NAME = 'billie_account';

  private $options;

  private $logger;

  public function __construct() {
    $this->options = get_option( self::OPTION_NAME );
    $this->logger  = wc_get_logger();
  }

  public function register() {
    register_setting( 'billie', self::OPTION_NAME );
  }

  /**
   * @throws BillieException
   * @throws CredentialsNotSetException
   * @throws ResponseException
   */
  public function validateCredentials() {
    if ( ! isset( $this->options ) || ! is_array( $this->options ) || ! isset( $this->options['client_id'], $this->options['client_secret'] ) ) {
      throw new CredentialsNotSetException( __( 'Missing Credentials', 'billie' ) );
    }

    $oauthToken = $this->requestOAuthToken( $this->options['client_id'], $this->options['client_secret'], $this->isSandbox() );

    $result = $this->post( '/oauth/authorization', null, $oauthToken, $this->isSandbox() );

    $validationResult = json_decode( $result['body'], true );

    if ( ! isset( $validationResult['client_id'] ) ) {
      throw new BillieException( __( 'Unexpected validation format', 'billie' ) );
    }

    if ( $validationResult['client_id'] !== $this->options['client_id'] ) {
      throw new BillieException( __( 'Unexpected validation client id', 'billie' ) );
    }
  }

  /**
   * @param $client_id
   * @param $client_secret
   * @param bool $sandbox
   *
   * @return Token
   * @throws BillieException
   * @throws ResponseException
   */
  private function requestOAuthToken( $client_id, $client_secret, $sandbox = false ) {
    $body = [
      'grant_type'    => 'client_credentials',
      'client_id'     => $client_id,
      'client_secret' => $client_secret
    ];

    $result = $this->post( '/oauth/token', $body, null, $sandbox, false );

    $tokenResult = json_decode( $result['body'], true );

    if ( ! isset( $tokenResult['expires_in'], $tokenResult['access_token'] ) ) {
      throw new BillieException( 'Unexpected Token format' );
    }

    return new Token( $tokenResult['access_token'], $tokenResult['expires_in'] );
  }

  /**
   * @param $params
   *
   * @return string
   * @throws BillieException
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
   * @param $billie_uuid
   * @param array $billie_order_data
   *
   * @throws BillieException
   * @throws ResponseException
   */
  public function updateOrder( $billie_uuid, array $billie_order_data ) {

    $oauthToken = $this->requestOAuthToken( $this->options['client_id'], $this->options['client_secret'], $this->isSandbox() );

    $this->patch( sprintf( '/order/%s', $billie_uuid ), $billie_order_data, $oauthToken, $this->isSandbox(), true );
  }

  /**
   * @param $billie_uuid
   *
   * @throws BillieException
   * @throws ResponseException
   */
  public function cancelOrder( $billie_uuid ) {
    $oauthToken = $this->requestOAuthToken( $this->options['client_id'], $this->options['client_secret'], $this->isSandbox() );

    $this->post( sprintf( '/order/%s/cancel', $billie_uuid ), [], $oauthToken, $this->isSandbox(), true );
  }

  /**
   * @param $billie_uuid
   * @param array $billie_order_data
   *
   * @return array
   * @throws BillieException
   * @throws ResponseException
   */
  public function shipOrder( $billie_uuid, array $billie_order_data ) {
    $oauthToken = $this->requestOAuthToken( $this->options['client_id'], $this->options['client_secret'], $this->isSandbox() );

    $result = $this->post( sprintf( '/order/%s/ship', $billie_uuid ), $billie_order_data, $oauthToken, $this->isSandbox(), true );

    return json_decode( $result['body'], true );
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
   * @throws BillieException
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
   * @throws BillieException
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
   * @throws BillieException
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
   * @throws BillieException
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
   * @throws BillieException
   * @throws ResponseException
   */
  private function validateRemoteResult( $result ) {
    $this->logger->debug( 'validating', [
      'result' => $result,
    ] );


    if ( $result instanceof \WP_Error ) {
      throw new BillieException( __( $result->get_error_message(), $result->get_error_code() ) );
    }

    if ( ! is_array( $result ) || ! isset( $result['response'], $result['body'] ) || ! isset( $result['response']['code'], $result['response']['message'] ) ) {
      throw new BillieException( 'Unexpected API response format' );
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
   * @throws BillieException
   * @throws ResponseException
   */
  private function request( $path, $body, $json_request = false, Token $token = null, $method = 'PUT', $sandbox = false ) {
    $url = $sandbox ? BILLIE_SANDBOX_URL : BILLIE_PRODUCTION_URL;
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
