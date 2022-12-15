<?php
/**
 * Plugin Name: WooCommerce-Mondu
 * Plugin URI: https://github.com/mondu-ai/bnpl-checkout-woocommerce/releases
 * Description: Increase your revenue with Mondu’s solution, without the operational burden.
 * Version: 1.0.4
 * Author: Mondu
 * Author URI: https://mondu.ai
 * License: MIT
 *
 * Text Domain: mondu
 * Domain Path: /languages
 *
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * WC requires at least: 4.6
 * WC tested up to: 5.9.3
 *
 * Copyright 2022 Mondu
 */

defined('ABSPATH') or die('Direct access not allowed');

define('MONDU_PLUGIN_VERSION', '1.0.4');
define('MONDU_PLUGIN_FILE', __FILE__);
define('MONDU_PLUGIN_PATH', __DIR__);
define('MONDU_PLUGIN_BASENAME', plugin_basename(MONDU_PLUGIN_FILE));
define('MONDU_PUBLIC_PATH', plugin_dir_url(MONDU_PLUGIN_FILE));
define('MONDU_VIEW_PATH', MONDU_PLUGIN_PATH . '/views');

define('MONDU_SANDBOX_URL', 'https://api.demo.mondu.ai/api/v1');
define('MONDU_PRODUCTION_URL', 'https://api.mondu.ai/api/v1');

require_once 'src/autoload.php';

add_action('plugins_loaded', [new \Mondu\Plugin(), 'init']);
