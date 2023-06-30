<?php

namespace Mondu\Admin\Option;

use Mondu\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	die( 'Direct access not allowed' );
}

class Account extends Helper {
	public function register() {
		register_setting('mondu', Plugin::OPTION_NAME);

		/*
		 * General Settings
		 */
		add_settings_section(
			'mondu_account_settings_general',
			__('Settings', 'woocommerce'),
			[],
			'mondu-settings-account'
		);
		add_settings_field(
			'sandbox_or_production',
			__('Sandbox or production', 'mondu'),
			[ $this, 'field_sandbox_or_production' ],
			'mondu-settings-account',
			'mondu_account_settings_general',
			[
				'label_for' => 'sandbox_or_production',
				'tip'       => __('Mondu\'s environment to use.', 'mondu'),
			]
		);
		add_settings_field('api_token',
			__('API Token', 'mondu'),
			[ $this, 'field_api_token' ],
			'mondu-settings-account',
			'mondu_account_settings_general',
			[
				'label_for' => 'api_token',
				'tip'       => __('API Token provided by Mondu.', 'mondu'),
			]
		);

		add_settings_field('send_line_items',
			__('Send line items', 'mondu'),
			[ $this, 'field_send_line_items' ],
			'mondu-settings-account',
			'mondu_account_settings_general',
			[
				'label_for' => 'send_line_items',
				'tip'       => __('Send the line items when creating order and invoice.', 'mondu'),
			]
		);
	}

	public function field_send_line_items( $args = [] ) {
		$this->selectField(Plugin::OPTION_NAME, 'send_line_items', [
			'yes'   => __('Yes', 'mondu'),
			'order' => __('Send line items only for orders', 'mondu'),
			'no'    => __('No', 'mondu'),
		], $args['tip']);
	}

	public function field_sandbox_or_production( $args = [] ) {
		$this->selectField(Plugin::OPTION_NAME, 'sandbox_or_production', [
			'sandbox'    => __('Sandbox', 'mondu'),
			'production' => __('Production', 'mondu'),
		], $args['tip']);
	}

	public function field_api_token( $args = [] ) {
		$this->textField(Plugin::OPTION_NAME, 'api_token', $args['tip']);
	}

	public function render( $validation_error = null, $webhooks_error = null ) {
		if ( !current_user_can('manage_options') ) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
		}
		$credentials_validated = get_option('_mondu_credentials_validated');
		$webhooks_registered   = get_option('_mondu_webhooks_registered');

		include MONDU_VIEW_PATH . '/admin/options.php';
	}
}
