<?php

namespace Mondu\Admin;

use Mondu\Plugin;
use Mondu\Admin\Option\Account;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\CredentialsNotSetException;
use Mondu\Mondu\MonduRequestWrapper;

defined('ABSPATH') or die('Direct access not allowed');

class Settings {
  /**
   * @var array|bool|mixed|void
   */
  private $global_settings;

  /**
   * @var MonduRequestWrapper
   */
  private $mondu_request_wrapper;

  /**
   * @var Account
   */
  private $account_options;

  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);

    $this->mondu_request_wrapper = new MonduRequestWrapper();
  }

  public function init() {
    add_action('admin_menu', [$this, 'plugin_menu']);
    add_action('admin_init', [$this, 'register_options']);
    add_action('admin_post_download_logs', [$this, 'download_mondu_logs']);
  }

  public function plugin_menu() {
    $mondu_icon = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(MONDU_VIEW_PATH . '/mondu.svg'));

    add_menu_page(
      __('Mondu Settings', 'mondu'),
      'Mondu',
      'manage_options',
      'mondu-settings-account',
      [$this, 'render_account_options'],
      $mondu_icon,
      '55.5'
    );
  }

  public function register_options() {
    $this->account_options = new Account();
    $this->account_options->register();
  }

  public function render_account_options() {
    $validation_error = null;
    $webhooks_error = null;

    if (isset($_POST['validate-credentials']) && check_admin_referer('validate-credentials', 'validate-credentials')) {
      try {
        if ($this->missing_credentials()) {
          throw new CredentialsNotSetException(__('Missing Credentials', 'mondu'));
        }

        $secret = $this->mondu_request_wrapper->webhook_secret();
        update_option('_mondu_webhook_secret', $secret);

        update_option('_mondu_credentials_validated', time());
      } catch (MonduException | CredentialsNotSetException $e) {
        delete_option('_mondu_credentials_validated');
        $validation_error = $e->getMessage();
      }
    } else if (isset($_POST['register-webhooks']) && check_admin_referer('register-webhooks', 'register-webhooks')) {
      try {
        $this->register_webhooks_if_not_registered();

        update_option('_mondu_webhooks_registered', time());
      } catch (MonduException | CredentialsNotSetException $e) {
        delete_option('_mondu_webhooks_registered');
        $webhooks_error = $e->getMessage();
      }
    } else if (isset($_GET['settings-updated']) || $this->missing_credentials()) {
      delete_option('_mondu_credentials_validated');
      delete_option('_mondu_webhooks_registered');
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
    $webhooks = $this->mondu_request_wrapper->get_webhooks();
    $registered_topics = array_map(function($webhook) {
      return $webhook['topic'];
    }, $webhooks);

    $required_topics = ['order', 'invoice'];
    foreach ($required_topics as $topic) {
      if (!in_array($topic, $registered_topics)) {
        $this->mondu_request_wrapper->register_webhook($topic);
      }
    }
  }

  public function download_mondu_logs() {
    $date = $_REQUEST['date'];

    if ($date == null) {
      status_header(400);
      exit(__('Date is required.'));
    }

    $file = $this->get_file($date);

    if ($file == null) {
      status_header(404);
      exit(__('Log not found.'));
    }

    $filename = 'mondu-' . $date . '.log';

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    readfile($file);

    die();
  }

  private function get_file($date) {
    $base_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';

    if ($dir = opendir($base_dir)) {
      while ($file = readdir($dir)) {
        if (str_starts_with($file, 'mondu-' . $date) && str_ends_with($file, '.log')) {
          return $base_dir . $file;
        }
      }
    }
  }
}
