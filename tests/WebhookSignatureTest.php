<?php
/**
 * Framework-free test for the webhook signature check in WebhooksController::index().
 *
 * WordPress and WooCommerce are replaced by small stubs, and the webhook topic is one the
 * controller does not handle, so a request that passes the check answers 200 without
 * touching any order.
 * Run:  php tests/WebhookSignatureTest.php   (exit code 0 = all passed)
 *
 * @package Mondu
 */

// WP_REST_Controller declares the properties the controller sets; the stub below does not.
error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );
define( 'MONDU_PLUGIN_VERSION', 'test' );
define( 'MONDU_SANDBOX_URL', 'https://api.mondu.test' );
define( 'MONDU_PRODUCTION_URL', 'https://api.mondu.test' );

$GLOBALS['test_options'] = [];

class WP_REST_Controller {}

class WP_REST_Request {
	private $body;
	private $headers;

	public function __construct( $body, $headers ) {
		$this->body    = $body;
		$this->headers = $headers;
	}

	public function get_body() {
		return $this->body;
	}

	public function get_json_params() {
		return json_decode( $this->body, true );
	}

	public function get_header( $key ) {
		return $this->headers[ strtolower( $key ) ] ?? null;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data, $status ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['test_options'] ) ? $GLOBALS['test_options'][ $name ] : $default;
}

function __( $text ) {
	return $text;
}

function wc_get_logger() {
	return new class() {
		public function log( ...$args ) {}
	};
}

function wc_print_r( $value, $return_value ) {
	return print_r( $value, $return_value );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

// A rejected webhook is reported to Mondu as a plugin event; answer that call locally.
function wp_remote_request( $url, $args ) {
	return [
		'response' => [
			'code'    => 200,
			'message' => 'OK',
		],
		'body'     => '{}',
	];
}

require __DIR__ . '/../src/autoload.php';

use Mondu\Mondu\Controllers\WebhooksController;

$failures = 0;
$passed   = 0;

$check = function ( $name, $expected, $actual ) use ( &$failures, &$passed ) {
	if ( $expected === $actual ) {
		$passed++;
		printf( "ok    %s\n", $name );
	} else {
		$failures++;
		printf( "FAIL  %s expected=%s got=%s\n", $name, var_export( $expected, true ), var_export( $actual, true ) );
	}
};

// Returns the response status for a webhook signed with $signature, or for one with no header.
$send = function ( $stored_secret, $signature ) {
	$GLOBALS['test_options'] = [ 'mondu_account' => [ 'api_token' => 'test' ] ];
	if ( null !== $stored_secret ) {
		$GLOBALS['test_options']['_mondu_webhook_secret'] = $stored_secret;
	}
	$body    = '{"topic":"test/unhandled"}';
	$headers = null === $signature ? [] : [ 'x-mondu-signature' => $signature( $body ) ];

	return ( new WebhooksController() )->index( new WP_REST_Request( $body, $headers ) )->status;
};

$sign_with = function ( $key ) {
	return function ( $body ) use ( $key ) {
		return hash_hmac( 'sha256', $body, $key );
	};
};

$secret = 'whsec_0123456789abcdef';

$check( 'valid signature is accepted', 200, $send( $secret, $sign_with( $secret ) ) );
$check( 'signature made with another key is rejected', 400, $send( $secret, $sign_with( 'other' ) ) );
$check( 'missing signature header is rejected', 400, $send( $secret, null ) );
$check( 'secret never stored: forged empty key signature is refused', 503, $send( null, $sign_with( '' ) ) );
$check( 'secret stored empty: forged empty key signature is refused', 503, $send( '', $sign_with( '' ) ) );
$check( 'secret stored as false: forged empty key signature is refused', 503, $send( false, $sign_with( '' ) ) );

echo "\n{$passed} passed, {$failures} failed\n";
exit( 0 === $failures ? 0 : 1 );
