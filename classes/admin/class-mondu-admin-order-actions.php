<?php
/**
 * Admin Order Actions class.
 */

if ( ! defined('ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MonduAdminOrderActions' ) ) {
	class MonduAdminOrderActions {
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_payment_info_box' ) );
			add_action( 'admin_footer', array( $this, 'invoice_buttons_js' ) );

			add_action( 'wp_ajax_cancel_invoice', array( $this, 'cancel_invoice' ) );
			add_action( 'wp_ajax_create_invoice', array( $this, 'create_invoice' ) );

			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'change_address_warning' ), 10, 1 );
		}

		public function add_payment_info_box() {
            $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
                ? wc_get_page_screen_id( 'shop-order' )
                : 'shop_order';

            add_meta_box('mondu_payment_info',
                         __('Mondu Order Information', 'mondu'),
                function ($post_or_order_object) {
                    $order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

                    if ( null === $order ) return;
                    if ( !in_array($order->get_payment_method(), PAYMENT_METHODS, true) ) return;

                    $this->render_meta_box_content($order);
                },
                $screen,
                'normal'
            );
		}

		public function invoice_buttons_js() {
			require_once MONDU_VIEW_PATH . '/admin/js/invoice.php';
		}

		public function render_meta_box_content( $order ) {
			$payment_info = new PaymentInfo( $order->get_id() );
			$payment_info->get_mondu_section_html();
		}

		public function cancel_invoice() {
			$is_nonce_valid = check_ajax_referer( 'mondu-cancel-invoice', 'security', false );
			if ( ! $is_nonce_valid ) {
				status_header( 400 );
				exit( esc_html__( 'Bad Request.', 'mondu' ) );
			}

			$invoice_id     = isset( $_POST['invoice_id'] ) ? sanitize_text_field( $_POST['invoice_id'] ) : '';
			$mondu_order_id = isset( $_POST['mondu_order_id'] ) ? sanitize_text_field( $_POST['mondu_order_id'] ) : '';
			$order_id       = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';

			$order = new WC_Order( $order_id );

			try {
				Mondu_WC()->mondu_request_wrapper->cancel_invoice( $mondu_order_id, $invoice_id );
			} catch ( ResponseException $e ) {
				wp_send_json(
					array(
						'error'   => true,
						'message' => $e->getMessage(),
					)
				);
			} catch ( MonduException $e ) {
				wp_send_json(
					array(
						'error'   => true,
						'message' => $e->getMessage(),
					)
				);
			}
		}

		public function create_invoice() {
			$is_nonce_valid = check_ajax_referer( 'mondu-create-invoice', 'security', false );
			if ( ! $is_nonce_valid ) {
				status_header( 400 );
				exit( esc_html__( 'Bad Request.', 'mondu' ) );
			}

			$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';

			$order = new WC_Order( $order_id );
			if ( null === $order ) {
				return;
			}

			try {
				Mondu_WC()->mondu_request_wrapper->ship_order( $order_id );
			} catch ( ResponseException $e ) {
				wp_send_json(
					array(
						'error'   => true,
						'message' => $e->getMessage(),
					)
				);
			} catch ( MonduException $e ) {
				wp_send_json(
					array(
						'error'   => true,
						'message' => $e->getMessage(),
					)
				);
			}
		}

		public function change_address_warning( WC_Order $order ) {
			if ( ! order_has_mondu( $order ) ) {
				return;
			}

			wc_enqueue_js(
				"jQuery(document).ready(function() {
					jQuery('a.edit_address').remove();
				});"
			);
			echo '<p>' . esc_html__( 'Since this order will be paid via Mondu you will not be able to change the addresses.', 'mondu' ) . '</p>';
		}
	}
}

new MonduAdminOrderActions();