<?php
/**
 *  Plugin Name: Billie Rechnungskauf für WooCommerce
 * Plugin URI: https://www.billie.io/
 * Description: Jetzt durchstarten mit der beliebtesten Zahlungsart für B2B-Shops: Billie Rechnungskauf
 * Version: 1.2.6
 * Author: pooliestudios
 * Author URI: https://pooliestudios.com
 * License: MIT
 * Text Domain: billie
 * Domain Path: lang
 * WC requires at least: 3.0.0
 * WC tested up to: 4.4.1
 */

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

define( 'BILLIE_PLUGIN_VERSION', '1.2.6' );
define( 'BILLIE_PLUGIN_PATH', __DIR__ );
define( 'BILLIE_VIEW_PATH', BILLIE_PLUGIN_PATH . '/views' );
define( 'BILLIE_RESSOURCES_PATH', BILLIE_PLUGIN_PATH . '/ressources' );

define( 'BILLIE_SANDBOX_URL', 'http://host.docker.internal:3000/api/v1' );
define( 'BILLIE_PRODUCTION_URL', 'http://host.docker.internal:3000/api/v1' );

require_once 'src/autoload.php';

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
  $billiePlugin = new \Billie\Plugin();
  add_action( 'init', [ $billiePlugin, 'add_callback_url' ] );
  add_action( 'plugins_loaded', [ $billiePlugin, 'init' ] );
}
