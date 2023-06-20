<?php

namespace Mondu\Mondu\Models;

class Token {
	/**
	 * Expires in
	 *
	 * @var int
	 */
	private $expires_in = 0;

	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Token constructor.
	 *
	 * @param string $access_token
	 * @param int $expires_in
	 */
	public function __construct( $access_token, $expires_in = 0 ) {
		$this->expires_in   = $expires_in;
		$this->access_token = $access_token;
	}

	public function get_expires_in() {
		return $this->expires_in;
	}

	public function set_expires_in( $expires_in ) {
		$this->expires_in = $expires_in;

		return $this;
	}

	public function get_access_token() {
		return $this->access_token;
	}

	public function set_access_token( $access_token ) {
		$this->access_token = $access_token;

		return $this;
	}
}
