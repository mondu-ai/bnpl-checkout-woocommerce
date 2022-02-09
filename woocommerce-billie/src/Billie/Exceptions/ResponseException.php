<?php


namespace Billie\Exceptions;


class ResponseException extends BillieException {

  private $body = null;

  public function __construct( $message = "", $code = 0, $body = null ) {
    $this->body = $body;
    parent::__construct( $message, $code, null );
  }

  /**
   * @return null
   */
  public function getBody() {
    return $this->body;
  }
}
