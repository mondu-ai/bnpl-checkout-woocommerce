<?php
/**
 * Plugin Name: Mondu Buy Now Pay Later
 * Description: Mondu provides B2B E-commerce and B2B marketplaces with an online payment solution to buy now and pay later.
 * Version: 3.0.0
 * Author: Mondu
 * Author URI: https://mondu.ai
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: mondu
 * Domain Path: /languages
 *
 * Requires at least: 5.9.0
 * Requires PHP: 7.4
 * WC requires at least: 6.5
 * WC tested up to: 8.7
 *
 * Copyright 2024 Mondu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MONDU_PLUGIN_VERSION', '3.0.0' );
define( 'MONDU_PLUGIN_FILE', __FILE__ );
define( 'MONDU_PLUGIN_PATH', __DIR__ );
define( 'MONDU_PLUGIN_BASENAME', plugin_basename( MONDU_PLUGIN_FILE ) );
define( 'MONDU_PUBLIC_PATH', plugin_dir_url( MONDU_PLUGIN_FILE ) );
define( 'MONDU_VIEW_PATH', MONDU_PLUGIN_PATH . '/views' );

function mondu_env( $name, $default_value ) {
    if ( getenv( $name ) !== false ) {
        define( $name, getenv( $name ) );
    } else {
        define( $name, $default_value );
    }
}
mondu_env( 'MONDU_SANDBOX_URL', 'https://api.demo.mondu.ai/api/v1' );
mondu_env( 'MONDU_PRODUCTION_URL', 'https://api.mondu.ai/api/v1' );
mondu_env( 'MONDU_WEBHOOKS_URL', get_home_url() );

function mondu_activate() {
}
register_activation_hook( MONDU_PLUGIN_FILE, 'mondu_activate' );

function mondu_deactivate() {
    delete_option( '_mondu_credentials_validated' );
    delete_option( '_mondu_webhooks_registered' );
    delete_option( 'woocommerce_mondu_installment_settings' );
    delete_option( 'woocommerce_mondu_direct_debit_settings' );
    delete_option( 'woocommerce_mondu_invoice_settings' );
    delete_option( 'woocommerce_mondu_installment_by_invoice_settings' );
}
register_deactivation_hook( MONDU_PLUGIN_FILE, 'mondu_deactivate' );

// Here because this needs to happen before plugins_loaded hook
add_action('before_woocommerce_init', function() {
    if ( class_exists( FeaturesUtil::class ) ) {
        FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
        FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__ );
    }
});

if ( ! class_exists( 'Mondu' ) ) {
    class Mondu {
        /**
         * The reference to the singleton instance of this class.
         *
         * @var Mondu $instance
         */
        private static $instance;

        /**
         * Mondu Request Wrapper
         *
         * @var MonduRequestWrapper
         */
        public $mondu_request_wrapper;

        public $global_settings;

        /**
         * Initializes the plugin.
         */
        protected function __construct() {
            add_action( 'plugins_loaded', array( $this, 'init' ) );

            add_filter( 'mondu_order_locale', 'mondu_locale', 1 );

            add_filter( 'plugin_action_links_' . MONDU_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
            add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
        }

        /**
         * Returns the singleton instance of this class.
         *
         * @return Mondu The singleton instance.
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Initializes the plugin.
         */
        public function init() {
            if ( ! class_exists( 'WooCommerce' ) ) {
                // This file is required to deactivate the plugin.
                // WordPress is not fully loaded when we are activating the plugin.
                include_once ABSPATH . '/wp-admin/includes/plugin.php';

                if ( is_multisite() ) {
                    add_action( 'network_admin_notices', array( $this, 'print_notice' ) );
                } else {
                    add_action( 'admin_notices', array( $this, 'print_notice' ) );
                }
                deactivate_plugins( MONDU_PLUGIN_BASENAME );
                return;
            }
            // $this->init_composer();
            $this->include_files();

            $this->global_settings       = get_option( OPTION_NAME );
            $this->mondu_request_wrapper = new MonduRequestWrapper();

            load_plugin_textdomain( 'mondu', false, plugin_basename( __DIR__ ) . '/languages' );

            /*
            * Adds the mondu gateway to the list of gateways
            */
            add_filter( 'woocommerce_payment_gateways', array( MonduGateway::class, 'add' ) );

            $plugin = new Plugin();
            $plugin->init();
        }

        public function log( array $message, $level = 'DEBUG' ) {
            wc_get_logger()->log( $level, wc_print_r( $message, true ), array( 'source' => 'mondu' ) );
        }

        public function print_notice() {
            $class   = 'notice notice-error';
            $message = __( 'Mondu requires WooCommerce to be activated.', 'mondu' );

            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }

        /**
         * Show action links on the plugin screen.
         *
         * @param mixed $links Plugin Action links.
         *
         * @return array
         */
        public function add_action_links( $links ) {
            $action_links = array(
                'settings' => '<a href="' . admin_url( 'admin.php?page=mondu-settings-account' ) . '" aria-label="' . esc_attr__( 'View Mondu settings', 'mondu' ) . '">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
            );

            return array_merge( $action_links, $links );
        }

        /**
         * Show row meta on the plugin screen.
         *
         * @param mixed $links Plugin Row Meta.
         * @param mixed $file   Plugin Base file.
         *
         * @return array
         */
        public function add_row_meta( $links, $file ) {
            if ( MONDU_PLUGIN_BASENAME !== $file ) {
                return $links;
            }

            $row_meta = array(
                'docs'  => '<a target="_blank" href="' . esc_url( 'https://docs.mondu.ai/docs/woocommerce-installation-guide' ) . '" aria-label="' . esc_attr__( 'View Mondu documentation', 'mondu' ) . '">' . esc_html__( 'Docs', 'mondu' ) . '</a>',
                'intro' => '<a target="_blank" href="' . esc_url( esc_attr__( 'https://mondu.ai/introduction-to-paying-with-mondu', 'mondu' ) ) . '" aria-label="' . esc_attr__( 'View introduction to paying with Mondu', 'mondu' ) . '">' . esc_html__( 'Mondu introduction', 'mondu' ) . '</a>',
                'faq'   => '<a target="_blank" href="' . esc_url( esc_attr__( 'https://mondu.ai/faq', 'mondu' ) ) . '" aria-label="' . esc_attr__( 'View FAQ', 'mondu' ) . '">' . esc_html__( 'FAQ', 'mondu' ) . '</a>',
            );

            return array_merge( $links, $row_meta );
        }

        // TODO: remove?
        // private function init_composer() {
        // 	$autoloader = MONDU_PLUGIN_PATH . '/vendor/autoload.php';

        // 	if ( ! is_readable( $autoloader ) ) {
        // 		self::missing_autoloader();
        // 		return false;
        // 	}

        // 	$autoloader_result = require $autoloader;
        // 	if ( ! $autoloader_result ) {
        // 		return false;
        // 	}

        // 	return $autoloader_result;
        // }

        private function include_files() {
            include_once MONDU_PLUGIN_PATH . '/includes/mondu-constants.php';
            include_once MONDU_PLUGIN_PATH . '/includes/mondu-functions.php';
            include_once MONDU_PLUGIN_PATH . '/includes/mondu-order-functions.php';

            include_once MONDU_PLUGIN_PATH . '/classes/exceptions/class-mondu-exception.php';
            include_once MONDU_PLUGIN_PATH . '/classes/exceptions/class-mondu-response-exception.php';

            include_once MONDU_PLUGIN_PATH . '/classes/controllers/class-mondu-api.php';
            include_once MONDU_PLUGIN_PATH . '/classes/controllers/class-mondu-orders-controller.php';
            include_once MONDU_PLUGIN_PATH . '/classes/controllers/class-mondu-webhooks-controller.php';

            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-gateway.php';
            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-gateway-direct-debit.php';
            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-gateway-installment.php';
            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-gateway-invoice.php';

            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-api.php';
            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-checkout.php';
            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-request-wrapper.php';
            include_once MONDU_PLUGIN_PATH . '/classes/class-mondu-signature-verifier.php';

            include_once MONDU_PLUGIN_PATH . '/src/Mondu/Plugin.php';

            if ( is_admin() ) {
                include_once MONDU_PLUGIN_PATH . '/includes/admin/mondu-admin-functions.php';

                include_once MONDU_PLUGIN_PATH . '/classes/admin/class-mondu-admin-form-fields.php';
                include_once MONDU_PLUGIN_PATH . '/classes/admin/class-mondu-admin-order-actions.php';
                include_once MONDU_PLUGIN_PATH . '/classes/admin/class-mondu-admin-payment-info.php';
                include_once MONDU_PLUGIN_PATH . '/classes/admin/class-mondu-admin-settings-options.php';
                include_once MONDU_PLUGIN_PATH . '/classes/admin/class-mondu-admin-settings.php';
            }
        }
    }
    Mondu::get_instance();
}

/**
 * Main instance Mondu WooCommerce.
 *
 * Returns the main instance of Mondu.
 *
 * @return Mondu
 */
function Mondu_WC() {
    return Mondu::get_instance();
}