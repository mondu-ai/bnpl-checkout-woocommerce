<?php
/**
 * Response Exception
 *
 * @package Mondu
 */

namespace Mondu\Exceptions;

/**
 * Class MonduException
 *
 * @package Mondu\Exceptions
 */
class ResponseException extends MonduException {
	/**
	 * Body of the response.
	 *
	 * @var null $body
	 */
	private $body;

	/**
	 * ResponseException constructor.
	 *
	 * @param string $message Message.
	 * @param int    $code Code.
	 * @param null   $body Body.
	 */
	public function __construct( $message = '', $code = 0, $body = null ) {
		$this->body = $body;
		parent::__construct( $message, $code );
	}

	/**
	 * Get the response body
	 *
	 * @return null
	 */
	public function getBody() {
		return $this->body;
	}
}
