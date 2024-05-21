<?php
/**
 * Response Exception
 *
 * @package Mondu
 */
namespace Mondu\Exceptions;

/**
 * Class ResponseException
 *
 * @package Mondu
 */
class ResponseException extends MonduException {
	/** @var null $body */
    private $body = null;

    /**
     * ResponseException constructor.
     *
     * @param string $message
     * @param int $code
     * @param null $body
     */
	public function __construct( $message = '', $code = 0, $body = null ) {
		$this->body = $body;
		parent::__construct( $message, $code );
	}

    /**
     * Get body
     *
     * @return null
     */
	public function getBody() {
		return $this->body;
	}
}
