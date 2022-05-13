<?php

namespace Mondu\Mondu\Models;

class Token {
  /** @var int */
  private $expires_in = 0;
  /** @var string */
  private $access_token;

  /**
   * Token constructor.
   *
   * @param string $access_token
   * @param int $expires_in
   */
  public function __construct($access_token, $expires_in = 0) {
    $this->expires_in   = $expires_in;
    $this->access_token = $access_token;
  }

  /**
   * @return int
   */
  public function get_expires_in() {
    return $this->expires_in;
  }

  /**
   * @param int $expires_in
   *
   * @return Token
   */
  public function set_expires_in($expires_in) {
    $this->expires_in = $expires_in;

    return $this;
  }

  /**
   * @return string
   */
  public function get_access_token() {
    return $this->access_token;
  }

  /**
   * @param string $access_token
   *
   * @return Token
   */
  public function set_access_token($access_token) {
    $this->access_token = $access_token;

    return $this;
  }
}
