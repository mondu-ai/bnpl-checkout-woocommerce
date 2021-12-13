<?php
/**
 * Plugin Name: Mondu WooCommerce plugin
 * Plugin URI: https://mondu.com/
 * Description: Mondu payment gateway for woocommerce plugin
 * Version: 1.0.0
 * Author: Mondu
 * Author URI: https://mondu.com
 * License: MIT
 * Text Domain: mondu
 * Domain Path: lang
 * WC requires at least: 3.0.0
 * WC tested up to: 4.4.1
 */
// print(get_option('active_plugin'));die;


require_once 'src/autoload.php';

define( 'MONDU_PLUGIN_PATH', __DIR__ );
define( 'MONDU_VIEW_PATH', MONDU_PLUGIN_PATH . '/views' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	$monduPlugin = new \Mondu\Plugin();
	add_action( 'init', [ $monduPlugin, 'add_callback_url' ] );
	add_action( 'plugins_loaded', [ $monduPlugin, 'init' ] );
}
