<?php
/**
 * Plugin function file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MonduApi' ) ) {
	class MonduApi {
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public function register_routes() {
			new MonduOrdersController();
			new MonduWebhooksController();
		}
	}
}

new MonduApi();
