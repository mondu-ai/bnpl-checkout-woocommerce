<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonduBlockSupport extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    /**
     * @var string
     */
    protected $name = 'mondu_blocks';

    /**
     * @var MonduGateway[]
     */
    protected $gateways;

    public function initialize()
    {
        $this->gateways = [
            new GatewayInvoice(false),
            new GatewayDirectDebit(false),
            new GatewayInstallment(false),
            new GatewayInstallmentByInvoice(false)
        ];
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'mondu-blocks-integration',
            MONDU_PUBLIC_PATH . 'assets/src/js/mondublocks.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            MONDU_PLUGIN_VERSION,
            true
        );

        wp_set_script_translations( 'mondu-blocks-integration', 'mondu', MONDU_PLUGIN_PATH. '/languages');

        return ['mondu-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        $gateways = array_reduce($this->gateways, function ($carry, $item) {
            $carry[$item->id] = [
                'title'       => $item->get_title(),
                'description' => $item->description,
                'supports'    => $item->supports,
                'enabled'     => $item->enabled
            ];

            return $carry;
        },                       []);

        return [
            'gateways'            => $gateways,
            'available_countries' => MonduCheckout::AVAILABLE_COUNTRIES
        ];
    }
}
