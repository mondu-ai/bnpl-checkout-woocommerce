<?php
/**
 * Settings Options class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonduSettingsOptions {
	/**
	 * FormFields
	 *
	 * @var FormFields
	 */
	public $form_fields;

	public function __construct() {
		$this->form_fields = new FormFields();
	}

	public function register() {
		register_setting( 'mondu', OPTION_NAME );

		add_settings_section(
			'mondu_account_settings_general',
			__( 'Settings', 'woocommerce' ),
			array(),
			'mondu-settings-account'
		);
		add_settings_field(
			...$this->form_fields->sandbox_or_production_field()
		);
		add_settings_field(
			...$this->form_fields->api_token_field()
		);
		add_settings_field(
			...$this->form_fields->send_line_items_field()
		);
	}

	public function render( $validation_error = null, $webhooks_error = null ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}
		$credentials_validated = get_option( '_mondu_credentials_validated' );
		$webhooks_registered   = get_option( '_mondu_webhooks_registered' );

		include MONDU_VIEW_PATH . '/admin/options.php';
	}
}
