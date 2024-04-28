<?php
/**
 * Functions for the 3rd party plugin WPO_WCPDF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WPO_WCPDF' ) && ! class_exists( 'MonduWPO_WCPDF' ) ) {
	class MonduWPO_WCPDF {
		public function __construct() {
			add_action( 'wpo_wcpdf_after_order_details', array( $this, 'wcpdf_add_mondu_payment_info_to_pdf' ), 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', array( $this, 'wcpdf_add_status_to_invoice_when_order_is_canceled' ), 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', array( $this, 'wcpdf_add_paid_to_invoice_when_invoice_is_paid' ), 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', array( $this, 'wcpdf_add_status_to_invoice_when_invoice_is_canceled' ), 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_after_document_data', array( $this, 'wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid' ), 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_after_document_data', array( $this, 'wcpdf_add_status_to_invoice_admin_when_invoice_is_canceled' ), 10, 2 );
			add_action( 'wpo_wcpdf_reload_text_domains', array( $this, 'wcpdf_add_mondu_payment_language_switch' ), 10, 1 );
		}

        /**
         * WCPDF add Mondu payment info
         *
         * @param $template_type
         * @param $order
         * @return void|null
         */
		public function wcpdf_add_mondu_payment_info_to_pdf( $template_type, $order ) {
			if ( ! $this->wcpdf_mondu_template_type( $template_type ) || ! order_has_mondu( $order ) ) {
				return;
			}

			if ( ! in_array( $order->get_payment_method(), PAYMENT_METHODS, true ) ) {
				return null;
			}

			$file = MONDU_VIEW_PATH . '/pdf/mondu-invoice-section.php';
			if ( ! file_exists( $file ) ) {
				return null;
			}

			// These variables are used in the file that is included
			$wcpdf_shop_name = $this->get_wcpdf_shop_name();
			$payment_method  = $order->get_payment_method();
			$bank_account    = $this->order_data['bank_account'];
			$invoice_number  = get_invoice_number( $order );
			$net_terms       = $this->get_mondu_net_term();
			$mondu_uk_buyer  = $bank_account['account_number'] && $bank_account['sort_code'];

			// TODO: do we need the echo here?
			// echo esc_html( $this->get_mondu_wcpdf_section_html() );
			include $file;
		}

		/**
		 * WCPDF add status canceled
		 *
		 * @param $template_type
		 * @param $order
		 * @throws Exception
		 */
		public function wcpdf_add_status_to_invoice_when_order_is_canceled( $template_type, $order ) {
			if ( ! $this->wcpdf_mondu_template_type( $template_type ) || ! order_has_mondu( $order ) ) {
				return;
			}

			$payment_info = new PaymentInfo( $order->get_id() );
			$order_data   = $payment_info->get_order_data();

			if ( 'cancelled' === $order->get_status() || 'canceled' === $order_data['state'] ) {
				?>
					<tr class="order-status">
						<th><?php esc_html_e( 'Order state', 'mondu' ); ?>:</th>
						<td><?php esc_html_e( 'Canceled', 'mondu' ); ?></td>
					</tr>
				<?php
			}
		}

		/**
		 * WCPDF add status paid
		 *
		 * @param $template_type
		 * @param $order
		 * @throws Exception
		 */
		public function wcpdf_add_paid_to_invoice_when_invoice_is_paid( $template_type, $order ) {
			if ( ! $this->wcpdf_mondu_template_type( $template_type ) || ! order_has_mondu( $order ) ) {
				return;
			}

			$payment_info = new PaymentInfo( $order->get_id() );
			$invoice_data = $payment_info->get_invoices_data();

			if ( $invoice_data && $invoice_data[0]['paid_out'] ) {
				?>
					<tr class="invoice-status">
						<th><?php esc_html_e( 'Mondu Invoice paid', 'mondu' ); ?>:</th>
						<td><?php esc_html_e( 'Yes', 'mondu' ); ?></td>
					</tr>
				<?php
			}
		}

		/**
		 * WCPDF add status canceled invoice
		 *
		 * @param $template_type
		 * @param $order
		 * @throws Exception
		 */
		public function wcpdf_add_status_to_invoice_when_invoice_is_canceled( $template_type, $order ) {
			if ( ! $this->wcpdf_mondu_template_type( $template_type ) || ! order_has_mondu( $order ) ) {
				return;
			}

			$payment_info = new PaymentInfo( $order->get_id() );
			$invoice_data = $payment_info->get_invoices_data();

			if ( $invoice_data && 'canceled' === $invoice_data[0]['state'] ) {
				?>
					<tr class="invoice-status">
						<th><?php esc_html_e( 'Mondu Invoice state', 'mondu' ); ?>:</th>
						<td><?php esc_html_e( 'Canceled', 'mondu' ); ?></td>
					</tr>
				<?php
			}
		}

		/**
		 * WCPDF add status paid invoice admin
		 *
		 * @param $document
		 * @param $order
		 * @throws Exception
		 */
		public function wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid( $document, $order ) {
			if ( $document->get_type() !== 'invoice' || ! order_has_mondu( $order ) ) {
				return;
			}

			$payment_info = new PaymentInfo( $order->get_id() );
			$invoice_data = $payment_info->get_invoices_data();

			if ( $invoice_data && $invoice_data[0]['paid_out'] ) {
				?>
					<div class="invoice-number">
						<p>
						<span><strong><?php esc_html_e( 'Mondu Invoice paid', 'mondu' ); ?>:</strong></span>
						<span><?php esc_html_e( 'Yes', 'mondu' ); ?></span>
						</p>
					</div>
				<?php
			}
		}

		/**
		 * WCPDF add status canceled invoice admin
		 *
		 * @param $document
		 * @param $order
		 * @throws Exception
		 */
		public function wcpdf_add_status_to_invoice_admin_when_invoice_is_canceled( $document, $order ) {
			if ( $document->get_type() !== 'invoice' || ! order_has_mondu( $order ) ) {
				return;
			}

			$payment_info = new PaymentInfo( $order->get_id() );
			$invoice_data = $payment_info->get_invoices_data();

			if ( $invoice_data && 'canceled' === $invoice_data[0]['state'] ) {
				?>
					<div class="invoice-number">
						<p>
						<span><strong><?php esc_html_e( 'Mondu Invoice state', 'mondu' ); ?>:</strong></span>
						<span><?php esc_html_e( 'Canceled', 'mondu' ); ?></span>
						</p>
					</div>
				<?php
			}
		}

		# TODO: why do we need this?
		public function wcpdf_add_mondu_payment_language_switch( $locale ) {
			unload_textdomain( 'mondu' );
			$this->load_textdomain();
		}

		/**
		 * WCPDF Mondu template type
		 *
		 * @param $template_type
		 * @return bool
		 */
		private function wcpdf_mondu_template_type( $template_type ) {
			/**
			 * Extend allowed templates
			 *
			 * @since 1.3.2
			 */
			$allowed_templates = apply_filters( 'mondu_wcpdf_template_type', array( 'invoice' ) );
			if ( in_array( $template_type, $allowed_templates, true ) ) {
				return true;
			}

			return false;
		}

		private function get_invoices( $order_id ) {
			try {
				return Mondu_WC()->mondu_request_wrapper->get_invoices( $order_id );
			} catch ( ResponseException $e ) {
				return array();
			}
		}

		private function get_order( $order_id) {
			try {
				return Mondu_WC()->mondu_request_wrapper->get_order( $order_id );
			} catch ( ResponseException $e ) {
				return array();
			}
		}
	}
	new MonduWPO_WCPDF();
}
