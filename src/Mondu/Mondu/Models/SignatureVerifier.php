<?php
/**
 * Signature Verifier
 *
 * @package Mondu
 */
namespace Mondu\Mondu\Models;

/**
 * Signature Verifier
 *
 * @package Mondu
 */
class SignatureVerifier {
	/** @var string $secret */
    private $secret;

    /**
     * Constructor
     */
	public function __construct() {
		$this->secret = get_option( '_mondu_webhook_secret' );
	}

    /**
     * Get secret
     *
     * @return string
     */
	public function get_secret() {
		return $this->secret;
	}

    /**
     * Set secret
     *
     * @param string $secret Secret.
     *
     * @return $this
     */
	public function set_secret( $secret ) {
		$this->secret = $secret;

		return $this;
	}

    /**
     * Create HMAC
     *
     * @param string $payload Payload.
     *
     * @return string
     */
	public function create_hmac( $payload ) {
		return hash_hmac( 'sha256', $payload, $this->secret );
	}

    /**
     * Verify signature
     *
     * @param $signature
     *
     * @return bool
     */
	public function verify( $signature ) {
		return $this->secret === $signature;
	}
}
