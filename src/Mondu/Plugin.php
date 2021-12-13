<?php

namespace Mondu;

use Mondu\Admin\Settings;

class Plugin {
    public function __construct()
    {
        add_action('woocommerce_after_checkout_validation', function () {
            if ($_POST['confirm-order-flag'] === "1") {
                wc_add_notice(__('error_confirmation', 'mondu'), 'error');
            }
        });
    }

    public function init() {
		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();

            // $order = new Order();
            // $order->init();
		}

        
    }
}