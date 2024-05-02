<?php
/**
 * Response Exception file.
 */

class ResponseException extends MonduException {
	private $body = null;

	public function __construct( $message = '', $code = 0, $body = null ) {
		$this->body = $body;
		parent::__construct( $message, $code );
	}

	public function getBody() {
		return $this->body;
	}
}
