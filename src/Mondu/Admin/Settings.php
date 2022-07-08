<?php

namespace Mondu\Admin;

use Mondu\Plugin;
use Mondu\Mondu\Api;
use Mondu\Admin\Option\Account;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\CredentialsNotSetException;

defined('ABSPATH') or die('Direct access not allowed');

class Settings {
  /** @var Account */
  private $account_options;

  /** @var Api */
  private $api;

  private $global_settings;

  public function init() {
    add_action('admin_menu', [$this, 'plugin_menu']);
    add_action('admin_init', [$this, 'register_options']);
  }

  public function plugin_menu() {
    add_menu_page(__('Mondu Settings', 'mondu'),
      __('Mondu', 'mondu'),
      'manage_options',
      'mondu-settings-account',
      [$this, 'render_account_options']);
  }

  public function register_options() {
    $this->account_options = new Account();
    $this->account_options->register();

    $this->api = new Api();

    $this->global_settings = get_option(Plugin::OPTION_NAME);
  }

  public function render_account_options() {
    $validation_error = null;

    try {
      if (isset($_POST['validate-credentials']) && check_admin_referer('validate-credentials', 'validate-credentials')) {
        if ($this->missing_credentials()) {
          throw new CredentialsNotSetException(__('Missing Credentials', 'mondu'));
        }

        $secret = $this->api->webhook_secret();
        update_option('_mondu_webhook_secret', $secret['webhook_secret']);

        # Validate registered webhooks and only register it if it is not registered
        $params = array('address' => get_site_url() . '/?rest_route=/mondu/v1/webhooks/index');
        $this->api->register_webhook(array_merge($params, array('topic' => 'order')));
        $this->api->register_webhook(array_merge($params, array('topic' => 'invoice')));

        update_option('_mondu_credentials_validated', time());
      } else if (isset($_GET['settings-updated']) || $this->missing_credentials()) {
        delete_option('_mondu_credentials_validated');
      }
    } catch (MonduException | CredentialsNotSetException $e) {
      delete_option('_mondu_credentials_validated');
      $validation_error = $e->getMessage();
    }

    $this->account_options->render($validation_error);
  }

  private function missing_credentials() {
    return (
      !isset($this->global_settings) ||
      !is_array($this->global_settings) ||
      !isset($this->global_settings['api_token']) ||
      $this->global_settings['api_token'] == ''
    );
  }
}
