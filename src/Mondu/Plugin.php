<?php

class Plugin {
	public function init() {
		/*
		 * These methods add the Mondu invoice's info to a WCPDF Invoice
		 */
		if ( class_exists( 'WPO_WCPDF' ) ) {
			add_action( 'wpo_wcpdf_after_order_details', array( $this, 'wcpdf_add_mondu_payment_info_to_pdf' ), 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', array( $this, 'wcpdf_add_status_to_invoice_when_order_is_canceled' ), 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', array( $this, 'wcpdf_add_paid_to_invoice_when_invoice_is_paid' ), 10, 2 );
			add_action( 'wpo_wcpdf_after_order_data', array( $this, 'wcpdf_add_status_to_invoice_when_invoice_is_canceled' ), 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_after_document_data', array( $this, 'wcpdf_add_paid_to_invoice_admin_when_invoice_is_paid' ), 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_after_document_data', array( $this, 'wcpdf_add_status_to_invoice_admin_when_invoice_is_canceled' ), 10, 2 );
			add_action( 'wpo_wcpdf_reload_text_domains', array( $this, 'wcpdf_add_mondu_payment_language_switch' ), 10, 1 );
		}
	}

	/**
	 * WCPDF Mondu template type
	 *
	 * @param $template_type
	 * @return bool
	 */
	public function wcpdf_mondu_template_type( $template_type ) {

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

	/**
	 * WCPDF add Mondu payment info
	 *
	 * @param $template_type
	 * @param $order
	 * @throws Exception
	 */
	public function wcpdf_add_mondu_payment_info_to_pdf( $template_type, $order ) {
		if ( ! $this->wcpdf_mondu_template_type( $template_type ) || ! order_has_mondu( $order ) ) {
			return;
		}

		$payment_info = new PaymentInfo( $order->get_id() );
		echo esc_html( $payment_info->get_mondu_wcpdf_section_html( true ) );
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

	public function wcpdf_add_mondu_payment_language_switch( $locale ) {
		unload_textdomain( 'mondu' );
		$this->load_textdomain();
	}
}
