<?php
/**
 * Order class
 *
 * @package Mondu
 */
namespace Mondu\Admin;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Support\Helper;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Plugin;
use WC_Order;

if ( !defined( 'ABSPATH' ) ) {
	die( 'Direct access not allowed' );
}

/**
 * Class Order
 *
 * @package Mondu
 */
class Order {
	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	/**
	 * Order constructor.
	 *
	 * @return void
	 */
	public function init() {
		add_action('add_meta_boxes', [ $this, 'add_payment_info_box' ]);
		add_action('admin_footer', [ $this, 'invoice_buttons_js' ]);

		add_action('wp_ajax_cancel_invoice', [ $this, 'cancel_invoice' ]);
		add_action('wp_ajax_create_invoice', [ $this, 'create_invoice' ]);

		add_action('wp_ajax_confirm_order', [ $this, 'confirm_order' ]);

		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	/**
	 * Add payment info box.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function add_payment_info_box() {
		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'mondu_payment_info',
			__( 'Mondu Order Information', 'mondu' ),
			function ( $post_or_order_object ) {
				$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

				if ( null === $order ) {
					return;
				}
				if ( !in_array( $order->get_payment_method(), Plugin::PAYMENT_METHODS, true ) ) {
					return;
				}

				$this->render_meta_box_content( $order );
			},
			$screen,
			'normal'
		);
	}

	/**
	 * Invoice buttons js.
	 *
	 * @return void
	 */
	public function invoice_buttons_js() {
		require_once MONDU_VIEW_PATH . '/admin/js/invoice.html';
	}

	/**
	 * Render meta box content.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function render_meta_box_content( $order ) {
		$payment_info = new PaymentInfo( $order->get_id() );
		$payment_info->get_mondu_section_html();
	}

	/**
	 * Cancel invoice.
	 *
	 * @return void
	 */
	public function cancel_invoice() {
		$is_nonce_valid = check_ajax_referer( 'mondu-cancel-invoice', 'security', false );
		if ( !$is_nonce_valid ) {
			status_header( 400 );
			exit(esc_html__( 'Bad Request.', 'mondu' ) );
		}

		$invoice_id     = isset( $_POST['invoice_id'] ) ? sanitize_text_field( $_POST['invoice_id'] ) : '';
		$mondu_order_id = isset( $_POST['mondu_order_id'] ) ? sanitize_text_field( $_POST['mondu_order_id'] ) : '';
		$order_id       = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';

		$order = new WC_Order($order_id);

		try {
			$this->mondu_request_wrapper->cancel_invoice( $mondu_order_id, $invoice_id );
			Helper::delete_wcpdf_invoice_document( $order );
		} catch ( ResponseException $e ) {
			wp_send_json([
				'error'   => true,
				'message' => $e->getMessage(),
			]);
		} catch ( MonduException $e ) {
			wp_send_json([
				'error'   => true,
				'message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Create invoice.
	 *
	 * @return void
	 */
	public function create_invoice() {
		$is_nonce_valid = check_ajax_referer( 'mondu-create-invoice', 'security', false );
		if ( !$is_nonce_valid ) {
			status_header( 400 );
			exit( esc_html__( 'Bad Request.', 'mondu' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';

		$order = new WC_Order( $order_id );
		if ( null === $order ) {
			return;
		}

		try {
			$this->mondu_request_wrapper->ship_order( $order_id );
		} catch ( ResponseException $e ) {
			wp_send_json([
				'error'   => true,
				'message' => $e->getMessage(),
			]);
		} catch ( MonduException $e ) {
			wp_send_json([
				'error'   => true,
				'message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Confirm order.
	 *
	 * @return void
	 */
	public function confirm_order() {
		$is_nonce_valid = check_ajax_referer( 'mondu-confirm-order', 'security', false );
		if ( !$is_nonce_valid ) {
			status_header( 400 );
			exit(esc_html__( 'Bad Request.', 'mondu' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';

		$order = new WC_Order( $order_id );
		if ( null === $order ) {
			return;
		}

		$mondu_order_id = isset( $_POST['order_uuid'] ) ? sanitize_text_field( $_POST['order_uuid'] ) : '';

		if ( !$mondu_order_id ) {
			return;
		}

		try {
			$this->mondu_request_wrapper->confirm_order( $order_id, $mondu_order_id );
		} catch ( ResponseException $e ) {
			wp_send_json([
				'error'   => true,
				'message' => $e->getMessage(),
			]);
		} catch ( MonduException $e ) {
			wp_send_json([
				'error'   => true,
				'message' => $e->getMessage(),
			]);
		}
	}
}
