<?php
/**
 * Plugin Name: WooCommerce-Mondu
 * Plugin URI: https://github.com/mondu-ai/bnpl-checkout-woocommerce/releases
 * Description: Mondu provides B2B E-commerce and B2B marketplaces with an online payment solution to buy now and pay later.
 * Version: 1.3.3
 * Author: Mondu
 * Author URI: https://mondu.ai
 *
 * Text Domain: mondu
 * Domain Path: /languages/
 *
 * Requires at least: 5.9.0
 * Requires PHP: 7.4
 * WC requires at least: 6.5
 * WC tested up to: 7.8
 *
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright 2023 Mondu
 */

if ( !defined('ABSPATH') ) {
	die('Direct access not allowed');
}

define('MONDU_PLUGIN_VERSION', '1.3.4');
define('MONDU_PLUGIN_FILE', __FILE__);
define('MONDU_PLUGIN_PATH', __DIR__);
define('MONDU_PLUGIN_BASENAME', plugin_basename(MONDU_PLUGIN_FILE));
define('MONDU_PUBLIC_PATH', plugin_dir_url(MONDU_PLUGIN_FILE));
define('MONDU_VIEW_PATH', MONDU_PLUGIN_PATH . '/views');

if ( getenv('MONDU_SANDBOX_URL') !== false ) {
	define('MONDU_SANDBOX_URL', getenv('MONDU_SANDBOX_URL'));
} else {
	define('MONDU_SANDBOX_URL', 'https://api.demo.mondu.ai/api/v1');
}
if ( getenv('MONDU_PRODUCTION_URL') !== false ) {
	define('MONDU_PRODUCTION_URL', getenv('MONDU_PRODUCTION_URL'));
} else {
	define('MONDU_PRODUCTION_URL', 'https://api.mondu.ai/api/v1');
}
if ( getenv('MONDU_WIDGET_SANDBOX_URL') !== false ) {
	define('MONDU_WIDGET_SANDBOX_URL', getenv('MONDU_WIDGET_SANDBOX_URL'));
} else {
	define('MONDU_WIDGET_SANDBOX_URL', 'https://checkout.demo.mondu.ai/widget.js');
}
if ( getenv('MONDU_WIDGET_PRODUCTION_URL') !== false ) {
	define('MONDU_WIDGET_PRODUCTION_URL', getenv('MONDU_WIDGET_PRODUCTION_URL'));
} else {
	define('MONDU_WIDGET_PRODUCTION_URL', 'https://checkout.mondu.ai/widget.js');
}

require_once 'src/autoload.php';

add_action('plugins_loaded', [ new \Mondu\Plugin(), 'init' ]);

function mondu_activate() {
}
register_activation_hook(MONDU_PLUGIN_FILE, 'mondu_activate');

function mondu_deactivate() {
	delete_option('_mondu_credentials_validated');
	delete_option('_mondu_webhooks_registered');
	delete_option('woocommerce_mondu_installment_settings');
	delete_option('woocommerce_mondu_direct_debit_settings');
	delete_option('woocommerce_mondu_invoice_settings');
}
register_deactivation_hook(MONDU_PLUGIN_FILE, 'mondu_deactivate');
