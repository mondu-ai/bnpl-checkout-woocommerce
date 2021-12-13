<?php

namespace Mondu\Mondu;

use Mondu\Admin\Options\Account;
use WC_Payment_Gateway;

class Gateway extends WC_Payment_Gateway {
    protected $config;

    public function __construct()
    {
        $this->config = get_option(Account::OPTION_NAME);
        $this->id = 'mondu';
		$this->has_fields = true;
		$this->title              = __( 'Mondu payment', 'mondu' );
		$this->method_title       = __( 'Mondu', 'mondu' );
		$this->description = __( 'Mondu Description', 'mondu' );
		$this->method_description = __( 'Mondu Description', 'mondu' );

        $this->init_form_fields();
        $this->init_settings();

        if(isset($this->settings['title'])) {
            $this->title = $this->settings['title'];
        }
        if(isset($this->settings['description'])) {
            $this->description = $this->settings['description'];
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = [
			'enabled'      => [
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this payment method', 'mondu' ),
				'default' => 'no',
			],
			'title'        => [
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'Mondu checkout', 'mondu' ),
				'desc_tip'    => true,
			],
			'description'  => [
				'title'   => __( 'Customer Message', 'mondu' ),
				'type'    => 'textarea',
				'default' => 'Checkout with mondu payment method'
			],
        ];

        add_action('woocommerce_update_options_payment_gateways_'. $this->id, [
            $this,
            'process_admin_options'
        ]);
    }

    public static function add( array $methods ) {
		array_unshift( $methods, static::class );

		return $methods;
	}
}
