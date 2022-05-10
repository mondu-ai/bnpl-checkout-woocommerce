<?php

namespace Mondu\Admin\Option;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

class Account extends Helper {
  const OPTION_NAME = 'mondu_account';

  public function __construct() {
    $this->options = get_option( self::OPTION_NAME );
  }

  public function register() {
    register_setting( 'mondu', self::OPTION_NAME );

    /*
     * General Settings
     */
    add_settings_section( 'mondu_account_settings_general',
      __( 'Settings', 'mondu' ),
      [ ],
      'mondu-settings-account' );
    add_settings_field( 'sandbox_or_production',
      __( 'Sandbox or production', 'mondu' ),
      [ $this, 'field_sandbox_or_production' ],
      'mondu-settings-account',
      'mondu_account_settings_general' );
    add_settings_field( 'client_id',
      __( 'Client ID', 'mondu' ),
      [ $this, 'field_client_id' ],
      'mondu-settings-account',
      'mondu_account_settings_general' );
    add_settings_field( 'client_secret',
      __( 'Client Secret', 'mondu' ),
      [ $this, 'field_client_secret' ],
      'mondu-settings-account',
      'mondu_account_settings_general' );
  }

  public function field_sandbox_or_production() {
    $this->selectField( self::OPTION_NAME, 'field_sandbox_or_production', [
      'sandbox'    => __( 'Sandbox', 'mondu' ),
      'production' => __( 'Production', 'mondu' ),
    ], 'single' );
  }

  public function field_client_id() {
    $this->textField( self::OPTION_NAME, 'client_id' );
  }

  public function field_client_secret() {
    $this->textField( self::OPTION_NAME, 'client_secret' );
  }

  public function render( $validation_error = null ) {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $credentials_validated = get_option( 'credentials_validated' );

    $oauth_possible = ( $this->options !== null ) && is_array( $this->options ) && isset( $this->options['client_id'], $this->options['client_secret'] );

    include MONDU_VIEW_PATH . '/admin/options.php';
  }
}
