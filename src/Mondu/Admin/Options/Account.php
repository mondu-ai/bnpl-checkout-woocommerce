<?php
namespace Mondu\Admin\Options;

class Account {
    const OPTION_NAME = 'mondu_account';

    public function __construct() {
        $this->options = get_option( self::OPTION_NAME );
    }
    
    public function register() {
        register_setting( 'mondu', self::OPTION_NAME );

        // General settings
        add_settings_section( 'mondu_account_settings_general',
            __( 'Settings', 'mondu' ),
            [ $this, 'account_info' ],
            'mondu-settings-account' );
        add_settings_field( 'sandbox_or_production',
            __( 'Sandbox or production', 'mondu' ),
            [ $this, 'field_sandbox_or_production' ],
            'mondu-settings-account',
            'mondu_account_settings_general' );
        add_settings_field( 'client_secret',
            __( 'Mondu api token', 'mondu' ),
            [ $this, 'field_client_secret' ],
            'mondu-settings-account',
            'mondu_account_settings_general' );
    }

    public function account_info() {
        _e( 'Configure plugin', 'mondu' );
    }
    
    public function field_client_id() {
        $this->textField( self::OPTION_NAME, 'client_id' );
    }

    public function field_client_secret() {
        $this->textField( self::OPTION_NAME, 'client_secret' );
    }

    public function field_sandbox_or_production() {
        $this->selectField( self::OPTION_NAME, 'field_sandbox_or_production', [
            'sandbox'    => __( 'Sandbox', 'mondu' ),
            'production' => __( 'Production', 'mondu' ),
        ], 'single' );
    }

    public function render() {
        if(!current_user_can('manage_options')) {
            wp_die(__( 'You do not have sufficient permissions to access this page.'));
        }

        include MONDU_VIEW_PATH.'/admin/options.php';
    }

    protected function textField( $optionName, $fieldName, $default = '' ) {
        printf(
            '<input type="text" id="' . $fieldName . '" name="' . $optionName . '[' . $fieldName . ']" value="%s" />',
            isset( $this->options[ $fieldName ] ) ? esc_attr( $this->options[ $fieldName ] ) : $default
        );
    }

    protected function selectField( $optionName, $fieldName, $options, $type = 'single' ) {
        $selectedValue = isset( $this->options[ $fieldName ] ) ? $this->options[ $fieldName ] : '';

        $multiple = '';
        $name     = $optionName . '[' . $fieldName . ']';
        if ( $type === 'multiple' ) {
            $multiple = ' multiple="multiple"';
            $name     .= '[]';
        }

        echo '<select id="' . $fieldName . '" name="' . $name . '"' . $multiple . '>';
        foreach ( $options as $value => $label ) {
            $selected = false;
            if ( is_array( $selectedValue ) && $type === 'multiple' ) {
                if ( in_array( $value, $selectedValue, true ) ) {
                    $selected = true;
                }
            } elseif ( $selectedValue === $value ) {
                $selected = true;
            }

            if ( $selected ) {
                $selected = ' selected="selected"';
            }
            echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }
}
