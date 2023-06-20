<?php

namespace Mondu\Admin;

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Mondu\Presenters\PaymentInfo;
use Mondu\Plugin;
use WC_Order;

if ( !defined('ABSPATH') ) {
	die('Direct access not allowed');
}

class Order {
	/**
	 * Mondu Request Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	public function init() {
		add_action('add_meta_boxes', [ $this, 'add_payment_info_box' ]);
		add_action('admin_footer', [ $this, 'invoice_buttons_js' ]);

		add_action('wp_ajax_cancel_invoice', [ $this, 'cancel_invoice' ]);
		add_action('wp_ajax_create_invoice', [ $this, 'create_invoice' ]);

		$this->mondu_request_wrapper = new MonduRequestWrapper();
	}

	public function add_payment_info_box() {
		$order = $this->check_and_get_wc_order();

		if ( null === $order ) {
			return;
		}

		add_meta_box('mondu_payment_info',
			__('Mondu Order Information', 'mondu'),
			function () use ( $order ) {
				$this->render_meta_box_content($order);
			},
			'shop_order',
			'normal'
		);
	}

	public function invoice_buttons_js() {
		require_once MONDU_VIEW_PATH . '/admin/js/invoice.php';
	}

	public function render_meta_box_content( $order ) {
		$payment_info = new PaymentInfo($order->get_id());
		$payment_info->get_mondu_section_html();
	}

	public function cancel_invoice() {
		$is_nonce_valid = check_ajax_referer( 'mondu-cancel-invoice', 'security', false );
		if ( !$is_nonce_valid ) {
			status_header(400);
			exit(esc_html__('Bad Request.', 'mondu'));
		}

		$invoice_id     = isset($_POST['invoice_id']) ? sanitize_text_field($_POST['invoice_id']) : '';
		$mondu_order_id = isset($_POST['mondu_order_id']) ? sanitize_text_field($_POST['mondu_order_id']) : '';
		$order_id       = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';

		$order = new WC_Order($order_id);

		try {
			$this->mondu_request_wrapper->cancel_invoice($mondu_order_id, $invoice_id);
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

	public function create_invoice() {
		$is_nonce_valid = check_ajax_referer( 'mondu-create-invoice', 'security', false );
		if ( !$is_nonce_valid ) {
			status_header(400);
			exit(esc_html__('Bad Request.', 'mondu'));
		}

		$order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';

		$order = new WC_Order($order_id);
		if ( null === $order ) {
			return;
		}

		try {
			$this->mondu_request_wrapper->ship_order($order_id);
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

	private function check_and_get_wc_order() {
		global $post;

		if ( !$post instanceof \WP_Post ) {
			return null;
		}

		if ( 'shop_order' !== $post->post_type ) {
			return null;
		}

		$order = new WC_Order($post->ID);

		if ( !in_array($order->get_payment_method(), Plugin::PAYMENT_METHODS, true) ) {
			return null;
		}

		return $order;
	}
}
