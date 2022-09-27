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
    $mondu_icon = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNzIiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCA3MiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE2LjI2OCA5LjU0ODY4QzE2LjI2OCA4LjA1Njk0IDE2LjY1OSA2LjY1NTcyIDE3LjM0NDUgNS40NDQzOEMxNy4xNzIyIDUuMTY4NCAxNi45NTU3IDQuOTE2NzEgMTYuNjgzMyA0LjY1NjkyQzE1Ljg2ODIgMy44ODkzNCAxNC44NDMyIDMuNDkyNjggMTMuNjMxMyAzLjQ5MjY4QzEyLjA0NjcgMy40OTI2OCAxMC43NjU2IDQuMjE0NjMgOS45MjYxNyA1LjU5MDA5QzkuMTgxMDIgNC4xOTI1NSA4LjAzOTAxIDMuNDkyNjggNi41MjM2OSAzLjQ5MjY4QzUuMDc4MzIgMy40OTI2OCA0LjA5OTc2IDMuOTgxMzQgMy4zMDc0OSA1LjEyMzUxVjMuODg5MzRIMFYxNS42MDMySDMuNTE4ODJWOS4yMjExOUMzLjUxODgyIDcuNjM3NDYgNC4xOTQwMSA2Ljc3NjQyIDUuNDc1OTIgNi43NzY0MkM2LjY2NTA2IDYuNzc2NDIgNy4yNzEwNCA3LjU5MTEgNy4yNzEwNCA5LjIyMTE5VjE1LjYwMzJIMTAuNzg3NlY5LjIyMTE5QzEwLjc4NzYgOC4yOTAyNCAxMC45MjkgNy43Nzg3NiAxMS4zMDE2IDcuMzU5MjhDMTEuNjI3IDcuMDA5NzEgMTIuMTg1OSA2Ljc3NjQyIDEyLjY5OTggNi43NzY0MkMxMy4yMzU5IDYuNzc2NDIgMTMuNzk0NyA3LjAzMjUyIDE0LjA1MSA3LjQwNDkxQzE0LjM1MzYgNy44MjQzOSAxNC40NDY0IDguMjkwOTcgMTQuNDQ2NCA5LjM4NTMxVjE1LjYwMzJIMTcuOTY1MlYxNC41OTQzQzE2LjkwMDUgMTMuMTkzIDE2LjI2OCAxMS40NDU5IDE2LjI2OCA5LjU0ODY4WiIgZmlsbD0iIzQ2MDg2RCIvPgo8cGF0aCBkPSJNNDIuMTA5NSA0LjY4MTIxQzQxLjMxNzIgMy45MTIxNiA0MC4xNzUyIDMuNDkyNjggMzguOTQwNCAzLjQ5MjY4QzM3LjQ3MyAzLjQ5MjY4IDM2LjU4NjUgMy45MzQ5NyAzNS43MjQyIDUuMTIzNTFWMy44ODkzNEgzMi40MTZWNC40NzA3M0MzMy40OTQ3IDUuODc4NTggMzQuMTM1MyA3LjYzODIgMzQuMTM1MyA5LjU0ODY4QzM0LjEzNTMgMTEuNDU5OSAzMy40OTQ3IDEzLjIxNzMgMzIuNDE2IDE0LjYyNTJWMTUuNjAzMkgzNS45MzI2VjkuMDYwMDJDMzUuOTMyNiA4LjI0Mzg3IDM2LjAwNCA3Ljk2NTY5IDM2LjI2MDMgNy41OTExQzM2LjYwODYgNy4wNzk2MiAzNy4yMzg4IDYuNzc2NDIgMzcuOTE0IDYuNzc2NDJDMzguNTQyOCA2Ljc3NjQyIDM5LjEyNiA3LjA1NjgxIDM5LjQwNjUgNy40OTgzN0MzOS42NjI4IDcuODcwNzUgMzkuNzU0OCA4LjMzNzM0IDM5Ljc1NDggOS4yMjExOVYxNS42MDMySDQzLjI3MzZWOC4yOTAyNEM0My4yNzQzIDYuNDI2ODUgNDIuOTk2IDUuNTE3OTcgNDIuMTA5NSA0LjY4MTIxWiIgZmlsbD0iIzQ2MDg2RCIvPgo8cGF0aCBkPSJNNTQuMzQ2OCAwVjQuODkwMjlDNTMuNDE1NCAzLjkzNTA1IDUyLjM0MjYgMy40OTI3NSA1MC44OTk1IDMuNDkyNzVDNDcuNTQxOSAzLjQ5Mjc1IDQ1LjA3MzggNi4xNDg3NCA0NS4wNzM4IDkuNzM0OTVDNDUuMDczOCAxMy4zNjY4IDQ3LjU0MjYgMTYgNTAuOTQ1MSAxNkM1Mi41NTM5IDE2IDUzLjU1NTMgMTUuNTMzNCA1NC41NTgyIDE0LjM0NzFWMTUuNjAzM0g1Ny44NjY0VjBINTQuMzQ2OFpNNTEuNTUwNCAxMi43MTYyQzQ5LjgyNjcgMTIuNzE2MiA0OC41OTI2IDExLjQ1NzggNDguNTkyNiA5LjcxMDY2QzQ4LjU5MjYgOC4wMTA2NSA0OS44NDk1IDYuNzc2NDkgNTEuNTk3NSA2Ljc3NjQ5QzUzLjI3NjMgNi43NzY0OSA1NC41NTc0IDguMDU3MDIgNTQuNTU3NCA5LjY4Nzg1QzU0LjU1ODIgMTEuNDEwNyA1My4yNzYzIDEyLjcxNjIgNTEuNTUwNCAxMi43MTYyWiIgZmlsbD0iIzQ2MDg2RCIvPgo8cGF0aCBkPSJNNjcuNzAwOCAzLjg4OTY1VjEwLjQzMjhDNjcuNzAwOCAxMS44Nzc1IDY2Ljk3ODUgMTIuNzE2NSA2NS43MTk0IDEyLjcxNjVDNjUuMTM3OCAxMi43MTY1IDY0LjY0ODkgMTIuNTMwMyA2NC4zMjEyIDEyLjIwMjhDNjMuOTcxNSAxMS44NTQ3IDYzLjgzMjMgMTEuMzY2IDYzLjgzMjMgMTAuNDMyOFYzLjg4OTY1SDYwLjMxMzVWMTAuODk5NEM2MC4zMTM1IDEyLjczODUgNjAuNTcxMiAxMy41Nzc1IDYxLjM4NjMgMTQuNTMzNUM2Mi4yMjU3IDE1LjQ4NzMgNjMuMzkwNSAxNi4wMDAyIDY0Ljc4ODcgMTYuMDAwMkM2Ni4zNDkgMTYuMDAwMiA2Ny4yODE5IDE1LjUxMTUgNjcuOTEwNyAxNC4zNDczVjE1LjYwMzVINzEuMjIxMVYzLjg4OTY1SDY3LjcwMDhaIiBmaWxsPSIjNDYwODZEIi8+CjxwYXRoIGQ9Ik0zMC4xMDY5IDUuNzA0MTZDMjguODk0OSA0LjIzNzQ0IDI3LjI2MzMgMy40OTI2OCAyNS4xODk4IDMuNDkyNjhDMjMuNDQxOCAzLjQ5MjY4IDIxLjc4NzMgNC4xNDU0NSAyMC43MTY4IDUuMjM5NzlDMTkuNTQ5NyA2LjQwNDA0IDE4Ljg3NDUgOC4wNTY5NSAxOC44NzQ1IDkuNzEwNTlDMTguODc0NSAxMy4zNjY3IDIxLjUwOSAxNS45OTk5IDI1LjE2NyAxNS45OTk5QzI3LjAwNyAxNS45OTk5IDI4LjU0NDQgMTUuNDE3IDI5LjY4NzIgMTQuMjUyOEMzMC44NTIgMTMuMDY0MiAzMS41MjcyIDExLjQzNTYgMzEuNTI3MiA5LjczNDg4QzMxLjUyOCA4LjI2ODE2IDMxLjAxNTUgNi44NDYzMyAzMC4xMDY5IDUuNzA0MTZaTTI1LjIxMTkgMTIuNzE2MkMyMy42MDUzIDEyLjcxNjIgMjIuMzkzMyAxMS40MzU2IDIyLjM5MzMgOS43MTA1OUMyMi4zOTMzIDguMDgxOTcgMjMuNjUyNCA2Ljc3NjQyIDI1LjIxMTkgNi43NzY0MkMyNi43NTE1IDYuNzc2NDIgMjguMDA4NCA4LjEwNDA1IDI4LjAwODQgOS43MzQ4OEMyOC4wMDg0IDExLjQzNTYgMjYuNzk2NCAxMi43MTYyIDI1LjIxMTkgMTIuNzE2MloiIGZpbGw9IiM2NUU5QzMiLz4KPC9zdmc+Cg==';

    add_menu_page(
      __('Mondu Settings', 'mondu'),
      __('Mondu', 'mondu'),
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

    $this->api = new Api();

    $this->global_settings = get_option(Plugin::OPTION_NAME);
  }

  public function render_account_options() {
    $validation_error = null;
    $webhooks_error = null;

    if (isset($_POST['validate-credentials']) && check_admin_referer('validate-credentials', 'validate-credentials')) {
      try {
        if ($this->missing_credentials()) {
          throw new CredentialsNotSetException(__('Missing Credentials', 'mondu'));
        }

        $secret = $this->api->webhook_secret();
        update_option('_mondu_webhook_secret', $secret['webhook_secret']);

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
