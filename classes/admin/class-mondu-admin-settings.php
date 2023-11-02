<?php
/**
 * Settings Class class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MonduSettings' ) ) {
	class MonduSettings {
		/**
		 * MonduSettingsOptions
		 *
		 * @var MonduSettingsOptions
		 */
		private $account_options;

		public function __construct() {
			$this->account_options = new MonduSettingsOptions();

			add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_options' ) );
			add_action( 'admin_post_download_logs', array( $this, 'download_mondu_logs' ) );
			add_filter( 'woocommerce_screen_ids', array( $this, 'set_wc_screen_ids' ) );
		}

		public function plugin_menu() {
			$mondu_icon = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( 'https://checkout.mondu.ai/logo.svg' ) );

			add_menu_page(
				__( 'Mondu Settings', 'mondu' ),
				'Mondu',
				'manage_options',
				'mondu-settings-account',
				array( $this, 'render_account_options' ),
				$mondu_icon,
				'55.5'
			);
		}

		public function register_options() {
			$this->account_options->register();
		}

		public function set_wc_screen_ids( $screen ) {
			$screen[] = 'toplevel_page_mondu-settings-account';
			return $screen;
		}

		public function render_account_options() {
			$validation_error = null;
			$webhooks_error   = null;

			if ( isset( $_POST['validate-credentials'] ) && check_admin_referer( 'validate-credentials', 'validate-credentials' ) ) {
				try {
					if ( $this->missing_credentials() ) {
						throw new MonduException( __( 'Missing Credentials', 'mondu' ) );
					}

					$secret = Mondu_WC()->mondu_request_wrapper->webhook_secret();
					update_option( '_mondu_webhook_secret', $secret );

					update_option( '_mondu_credentials_validated', time() );
				} catch ( MonduException $e ) {
					delete_option( '_mondu_credentials_validated' );
					$validation_error = $e->getMessage();
				}
			} elseif ( isset( $_POST['register-webhooks'] ) && check_admin_referer( 'register-webhooks', 'register-webhooks' ) ) {
				try {
					$this->register_webhooks_if_not_registered();

					update_option( '_mondu_webhooks_registered', time() );
				} catch ( MonduException $e ) {
					delete_option( '_mondu_webhooks_registered' );
					$webhooks_error = $e->getMessage();
				}
			} elseif ( isset( $_GET['settings-updated'] ) || $this->missing_credentials() ) {
				delete_option( '_mondu_credentials_validated' );
				delete_option( '_mondu_webhooks_registered' );
			}

			$this->account_options->render( $validation_error, $webhooks_error );
		}

		private function missing_credentials() {
			return (
			! isset( Mondu_WC()->global_settings ) ||
			! is_array( Mondu_WC()->global_settings ) ||
			! isset( Mondu_WC()->global_settings['api_token'] ) ||
			'' === Mondu_WC()->global_settings['api_token']
			);
		}

		private function register_webhooks_if_not_registered() {
			$webhooks          = Mondu_WC()->mondu_request_wrapper->get_webhooks();
			$registered_topics = array_map(
				function ( $webhook ) {
					return $webhook['topic'];
				},
				$webhooks
			);

			$required_topics = array( 'order', 'invoice' );
			foreach ( $required_topics as $topic ) {
				if ( ! in_array( $topic, $registered_topics, true ) ) {
					Mondu_WC()->mondu_request_wrapper->register_webhook( $topic );
				}
			}
		}

		public function download_mondu_logs() {
			$is_nonce_valid = check_ajax_referer( 'mondu-download-logs', 'security', false );
			if ( ! $is_nonce_valid ) {
				status_header( 400 );
				exit( esc_html__( 'Bad Request.', 'mondu' ) );
			}
			$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : null;

			if ( null === $date ) {
				status_header( 400 );
				exit( esc_html__( 'Date is required.' ) );
			}

			$file = $this->get_file( $date );

			if ( null === $file ) {
				status_header( 404 );
				exit( esc_html__( 'Log not found.' ) );
			}

			$filename = 'mondu-' . $date . '.log';

			header( 'Content-Type: text/plain' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '";' );

			readfile( $file );

			die();
		}

		private function get_file( $date ) {
			$base_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
			$dir      = opendir( $base_dir );
			if ( $dir ) {
				while ( $file = readdir( $dir ) ) {
					if ( str_starts_with( $file, 'mondu-' . $date ) && str_ends_with( $file, '.log' ) ) {
						return $base_dir . $file;
					}
				}
			}
		}
	}
}

new MonduSettings();
