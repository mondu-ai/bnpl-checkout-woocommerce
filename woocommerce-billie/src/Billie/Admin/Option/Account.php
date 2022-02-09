<?php


namespace Billie\Admin\Option;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

class Account extends Helper {
  const OPTION_NAME = 'billie_account';

  public function __construct() {
    $this->options = get_option( self::OPTION_NAME );
  }

  public function register() {
    register_setting( 'billie', self::OPTION_NAME );

    /*
     * General Settings
     */
    add_settings_section( 'billie_account_settings_general',
      __( 'Settings', 'billie' ),
      [ $this, 'account_info' ],
      'billie-settings-account' );
    add_settings_field( 'sandbox_or_production',
      __( 'Sandbox or production', 'billie' ),
      [ $this, 'field_sandbox_or_production' ],
      'billie-settings-account',
      'billie_account_settings_general' );
    add_settings_field( 'client_id',
      __( 'Client ID', 'billie' ),
      [ $this, 'field_client_id' ],
      'billie-settings-account',
      'billie_account_settings_general' );
    add_settings_field( 'client_secret',
      __( 'Client Secret', 'billie' ),
      [ $this, 'field_client_secret' ],
      'billie-settings-account',
      'billie_account_settings_general' );
  }

  public function account_info() {
    _e( 'plugin.settings.info', 'billie' );
  }

  public function field_client_id() {
    $this->textField( self::OPTION_NAME, 'client_id' );
  }

  public function field_client_secret() {
    $this->textField( self::OPTION_NAME, 'client_secret' );
  }

  public function field_sandbox_or_production() {
    $this->selectField( self::OPTION_NAME, 'field_sandbox_or_production', [
      'sandbox'    => __( 'Sandbox', 'billie' ),
      'production' => __( 'Production', 'billie' ),
    ], 'single' );
  }

  public function render( $validationError = null ) {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $credentialsValidated = get_option( 'credentials_validated' );

    $oauthPossible = ( $this->options !== null ) && is_array( $this->options ) && isset( $this->options['client_id'], $this->options['client_secret'] );

    include BILLIE_VIEW_PATH . '/admin/options.php';
  }
}
