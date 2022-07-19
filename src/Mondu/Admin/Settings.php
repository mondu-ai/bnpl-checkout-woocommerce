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

    $this->logger  = wc_get_logger();

    $this->global_settings = get_option(Plugin::OPTION_NAME);
  }

  public function render_account_options() {
    $validation_error = null;
    $webhooks_error = null;

    try {
      if (isset($_POST['validate-credentials']) && check_admin_referer('validate-credentials', 'validate-credentials')) {
        if ($this->missing_credentials()) {
          throw new CredentialsNotSetException(__('Missing Credentials', 'mondu'));
        }

        update_option('_mondu_credentials_validated', time());
      } else if (isset($_POST['register-webhooks']) && check_admin_referer('register-webhooks', 'register-webhooks')) {

        $secret = $this->api->webhook_secret();
        update_option('_mondu_webhook_secret', $secret['webhook_secret']);

        $this->register_webhooks_if_not_registered();

        update_option('_mondu_webhooks_registered', time());
      } else if (isset($_GET['settings-updated']) || $this->missing_credentials()) {
        delete_option('_mondu_credentials_validated');
        delete_option('_mondu_webhooks_registered');
      }
    } catch (CredentialsNotSetException $e) {
      delete_option('_mondu_credentials_validated');
      $validation_error = $e->getMessage();
    } catch (MonduException $e) {
      delete_option('_mondu_webhooks_registered');
      $webhooks_error = $e->getMessage();
    }

    $this->account_options->render($validation_error, $webhooks_error);
  }

  private function missing_credentials() {
    return (
      !isset($this->global_settings) ||
      !is_array($this->global_settings) ||
      !isset($this->global_settings['api_token']) ||
      $this->global_settings['api_token'] == ''
    );
  }

  private function register_webhooks_if_not_registered() {
    $webhooks = $this->api->get_webhooks();
    $registered_topics = array_map(function($webhook) {
      return $webhook['topic'];
    }, $webhooks['webhooks']);

    $required_topics = ['order', 'invoice'];
    foreach ($required_topics as $topic) {
      if (!in_array($topic, $registered_topics)) {
        $this->api->register_webhook($topic);
      }
    }
  }
}
