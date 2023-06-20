<?php

namespace Mondu\Mondu\Models;

class SignatureVerifier {
	private $secret;

	public function __construct() {
		$this->secret = get_option('_mondu_webhook_secret');
	}

	public function get_secret() {
		return $this->secret;
	}

	public function set_secret( $secret ) {
		$this->secret = $secret;

		return $this;
	}

	public function create_hmac( $payload ) {
		return hash_hmac('sha256', wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT), $this->secret);
	}


	public function verify( $signature ) {
		return $this->secret === $signature;
	}
}
