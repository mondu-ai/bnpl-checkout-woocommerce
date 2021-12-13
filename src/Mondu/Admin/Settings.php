<?php
namespace Mondu\Admin;

use Mondu\Admin\Options\Account;

class Settings {
    private $accountOptions;
    private $api;

    public function init() {
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_options' ] );
    }

    public function plugin_menu() {
        add_menu_page( 
            __( 'Mondu Settings', 'mondu' ),
            __('Mondu', 'mondu'), 'manage_options', 
            'mondu-settings-account',
            [ $this, 'render_account_options' ]
        );
    }

    public function register_options() {
        $this->accountOptions = new Account();
		$this->accountOptions->register();

		// $this->api = new Api();
    }

    public function render_account_options() {
        $validationError = null;
        $this->accountOptions->render($validationError);
    }
}
