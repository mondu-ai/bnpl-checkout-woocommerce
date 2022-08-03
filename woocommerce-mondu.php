<?php
/**
 * Plugin Name: Mondu
 * Plugin URI: https://mondu.ai/
 * Description: Increase your revenue with Mondu’s solution, without the operational burden.
 * Version: 0.0.4
 * Author: mondu
 * Author URI: https://mondu.ai
 * License: MIT
 * Text Domain: Mondu
 * WC requires at least: 4.6
 * WC tested up to: 5.9.3
 */

defined('ABSPATH') or die('Direct access not allowed');

define('MONDU_PLUGIN_VERSION', '0.0.4');
define('MONDU_PLUGIN_PATH', __DIR__);
define('MONDU_VIEW_PATH', MONDU_PLUGIN_PATH . '/views');
define('MONDU_RESSOURCES_PATH', MONDU_PLUGIN_PATH . '/resources');

define('MONDU_SANDBOX_URL', 'https://api.demo.mondu.ai/api/v1');
define('MONDU_PRODUCTION_URL', 'https://api.mondu.ai/api/v1');

require_once 'src/autoload.php';

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
  add_action('plugins_loaded', [new \Mondu\Plugin(), 'init']);
}
