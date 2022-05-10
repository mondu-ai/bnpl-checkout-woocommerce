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
  private $account_options;

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
    $this->account_options = new Account();
    $this->account_options->register();

    $this->api = new Api();
  }

  public function render_account_options() {
    $validation_error = null;

    if ( ( count( $_POST ) > 0 ) && check_admin_referer( 'validate-credentials' ) ) {
      try {
        $this->api->validate_credentials();
        update_option( 'credentials_validated', time() );
      } catch ( MonduException $e ) {
        $validation_error = $e->getMessage();
      }
    }

    $this->account_options->render( $validation_error );
  }
}
