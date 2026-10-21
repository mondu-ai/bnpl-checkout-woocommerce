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
	 * Has secret
	 *
	 * The secret is stored only once the admin validates the credentials. Until then
	 * there is nothing to sign with, and an HMAC keyed with an empty string can be
	 * computed by anyone from the body alone.
	 *
	 * @return bool
	 */
	public function has_secret() {
		return is_string( $this->secret ) && '' !== $this->secret;
	}

	/**
	 * Create HMAC
	 *
	 * @param string $payload Payload.
	 *
	 * @return string|null Null when no secret is configured.
	 */
	public function create_hmac( $payload ) {
		if ( !$this->has_secret() ) {
			return null;
		}

		return hash_hmac( 'sha256', (string) $payload, $this->secret );
	}

	/**
	 * Verify signature
	 *
	 * @param string $payload   Raw request body.
	 * @param mixed  $signature Signature sent by Mondu.
	 *
	 * @return bool
	 */
	public function verify( $payload, $signature ) {
		$expected = $this->create_hmac( $payload );

		return null !== $expected && is_string( $signature ) && hash_equals( $expected, $signature );
	}
}
