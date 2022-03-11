<?php
/**
 * Plugin Name: Mondu
 * Plugin URI: https://www.mondu.ai/
 * Description: Increase your revenue with Mondu’s solution, without the operational burden.
 * Version: 1.2.6
 * Author: mondu
 * Author URI: https://mondu.ai
 * License: MIT
 * Text Domain: Mondu
 * Domain Path: lang
 * WC requires at least: 3.0.0
 * WC tested up to: 4.4.1
 */

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );





define( 'MONDU_PLUGIN_VERSION', '1.2.6' );
define( 'MONDU_PLUGIN_PATH', __DIR__ );
define( 'MONDU_VIEW_PATH', MONDU_PLUGIN_PATH . '/views' );
define( 'MONDU_RESSOURCES_PATH', MONDU_PLUGIN_PATH . '/resources' );

define( 'MONDU_SANDBOX_URL', 'http://host.docker.internal:3000/api/v1' );
define( 'MONDU_PRODUCTION_URL', 'http://host.docker.internal:3000/api/v1' );

require_once 'src/autoload.php';

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
  add_action( 'plugins_loaded', [ new \Mondu\Plugin(), 'init' ] );
}
