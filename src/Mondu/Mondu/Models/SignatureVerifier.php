<?php

namespace Mondu\Mondu\Models;

class SignatureVerifier {
  /** @var string */
  private $secret;

  /**
   * @param string $secret
   */
  public function __construct() {
    $this->secret = get_option('_mondu_webhook_secret');
  }

  /**
   * @return string
   */
  public function get_secret() {
    return $this->secret;
  }

  /**
   * @param string $secret
   *
   * @return Token
   */
  public function set_secret($secret) {
    $this->secret = $secret;

    return $this;
  }

  /**
   * @param string $signature
   *
   * @return bool
   */
  public function create_hmac($payload) {
    return hash_hmac('sha256', json_encode($payload), $this->secret);
  }

  /**
   * @param string $signature
   *
   * @return bool
   */
  public function verify($signature) {
    return $this->secret == $signature;
  }
}
