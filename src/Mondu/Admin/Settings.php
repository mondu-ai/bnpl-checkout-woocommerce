<?php

namespace Mondu\Admin;

use Mondu\Admin\Option\Account;
use Mondu\Mondu\Api;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\CredentialsNotSetException;
use Mondu\Exceptions\ResponseException;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

class Settings {
  /** @var Account */
  private $accountOptions;

  /** @var Api */
  private $api;

  public function init() {
    add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
    add_action( 'admin_init', [ $this, 'register_options' ] );
  }

  public function plugin_menu() {
    add_menu_page( __( 'Mondu Settings', 'mondu' ),
      __( 'Mondu', 'mondu' ),
      'manage_options',
      'mondu-settings-account',
      [ $this, 'render_account_options' ] );
  }

  public function register_options() {
    $this->accountOptions = new Account();
    $this->accountOptions->register();

    $this->api = new Api();
  }

  public function render_account_options() {
    $validationError = null;

    if ( ( count( $_POST ) > 0 ) && check_admin_referer( 'validate-credentials' ) ) {
      try {
        $this->api->validateCredentials();
        update_option( 'credentials_validated', time() );
      } catch ( MonduException $e ) {
        $validationError = $e->getMessage();
      }
    }

    $this->accountOptions->render( $validationError );
  }
}
