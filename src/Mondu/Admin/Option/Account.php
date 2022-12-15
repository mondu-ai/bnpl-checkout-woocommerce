<?php

namespace Mondu\Admin\Option;

use Mondu\Plugin;

defined('ABSPATH') or die('Direct access not allowed');

class Account extends Helper {
  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);
  }

  public function register() {
    register_setting('mondu', Plugin::OPTION_NAME);

    /*
     * General Settings
     */
    add_settings_section('mondu_account_settings_general',
      __('Settings', 'mondu'),
      [],
      'mondu-settings-account');
    add_settings_field('sandbox_or_production',
      __('Sandbox or production', 'mondu'),
      [$this, 'field_sandbox_or_production'],
      'mondu-settings-account',
      'mondu_account_settings_general');
    add_settings_field('api_token',
      __('API Token', 'mondu'),
      [$this, 'field_api_token'],
      'mondu-settings-account',
      'mondu_account_settings_general');
    add_settings_field('send_line_items',
      __('Send line items', 'mondu'),
      [$this, 'field_send_line_items'],
      'mondu-settings-account',
      'mondu_account_settings_general');
  }

  public function field_send_line_items() {
    $this->selectField(Plugin::OPTION_NAME, 'field_send_line_items', [
      'yes' => __('Yes', 'mondu'),
      'order' => __('Send line items only for orders', 'mondu'),
      'no' => __('No', 'mondu'),
    ], 'single');
  }

  public function field_sandbox_or_production() {
    $this->selectField(Plugin::OPTION_NAME, 'field_sandbox_or_production', [
      'sandbox' => __('Sandbox', 'mondu'),
      'production' => __('Production', 'mondu'),
    ], 'single');
  }

  public function field_api_token() {
    $this->textField(Plugin::OPTION_NAME, 'api_token');
  }

  public function render($validation_error = null, $webhooks_error = null) {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $credentials_validated = get_option('_mondu_credentials_validated');
    $webhooks_registered = get_option('_mondu_webhooks_registered');

    include MONDU_VIEW_PATH . '/admin/options.php';
  }
}
