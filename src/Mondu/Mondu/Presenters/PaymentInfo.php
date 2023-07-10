<?php

namespace Mondu\Mondu\Presenters;

use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\Support\Helper;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Plugin;
use Exception;
use WC_Order;

class PaymentInfo {
	/**
	 * Order
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Wrapper
	 *
	 * @var MonduRequestWrapper
	 */
	private $mondu_request_wrapper;

	/**
	 * Order Data
	 *
	 * @var array
	 */
	private $order_data;

	/**
		 * Invoices Data
		 *
	 * @var array
	 */
	private $invoices_data;

	public function __construct( $order_id ) {
		$this->order                 = new WC_Order($order_id);
		$this->mondu_request_wrapper = new MonduRequestWrapper();
		$this->order_data            = $this->get_order();
		$this->invoices_data         = $this->get_invoices();
	}

	public function get_order_data() {
		return $this->order_data;
	}

	public function get_invoices_data() {
		return $this->invoices_data;
	}

	public function get_wcpdf_shop_name() {
		$wcpdf = \WPO_WCPDF::instance();

		return $wcpdf->documents->documents['\WPO\WC\PDF_Invoices\Documents\Invoice']->get_shop_name() !== null ?
			$wcpdf->documents->documents['\WPO\WC\PDF_Invoices\Documents\Invoice']->get_shop_name() :
			get_bloginfo('name');
	}

	/**
	 * Get Mondu Html Section
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_mondu_section_html() {
		if ( !in_array( $this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true ) ) {
			return null;
		}

		if ( $this->order_data ) {
			$order_data = $this->order_data;
			?>
			<section class="woocommerce-order-details mondu-payment">
				<p>
					<span><strong><?php esc_html_e('Order state', 'mondu'); ?>:</strong></span>
					<span><?php printf(esc_html($order_data['state'])); ?></span>
				</p>
				<p>
					<span><strong><?php esc_html_e('Mondu ID', 'mondu'); ?>:</strong></span>
					<span><?php printf(esc_html($order_data['uuid'])); ?></span>
				</p>
				<?php
				if ( in_array($this->order_data['state'], [ 'confirmed', 'partially_shipped' ], true) ) {
					?>
					<?php
					$mondu_data = [
						'order_id' => $this->order->get_id(),
						'security' => wp_create_nonce('mondu-create-invoice'),
					];
					?>
					<button data-mondu='<?php echo( wp_json_encode($mondu_data) ); ?>' id="mondu-create-invoice-button" type="submit" class="button grant_access">
						<?php esc_html_e('Create Invoice', 'mondu'); ?>
					</button>
					<?php
				}
				?>
			</section>
			<hr>
			<?php $this->get_mondu_payment_html(); ?>
			<?php $this->get_mondu_invoices_html(); ?>
			<?php
		} else {
			?>
			<section class="woocommerce-order-details mondu-payment">
				<p>
					<span><strong><?php esc_html_e('Corrupt Mondu order!', 'mondu'); ?></strong></span>
				</p>
			</section>
			<?php
		}
	}

	/**
	 * Get Mondu Payment HTML
	 *
	 * @return false|string|null
	 */
	public function get_mondu_payment_html() {
		if ( !in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true) ) {
			return null;
		}

		if ( !isset($this->order_data['bank_account']) ) {
			return null;
		}

		$bank_account = $this->order_data['bank_account'];
		$net_terms = $this->get_mondu_net_term();

		if ( $bank_account ) {
			?>
			<style>
				.mondu-payment > table > tbody > tr > td {
					min-width: 130px;
				}
			</style>
			<section class="woocommerce-order-details mondu-payment">
				<p>
					<span><strong><?php esc_html_e('Account holder', 'mondu'); ?>:</strong></span>
					<span><?php printf(esc_html($bank_account['account_holder'])); ?></span>
				</p>
				<p>
					<span><strong><?php esc_html_e('Bank', 'mondu'); ?>:</strong></span>
					<span><?php printf(esc_html($bank_account['bank'])); ?></span>
				</p>
				<p>
					<span><strong><?php esc_html_e('IBAN', 'mondu'); ?>:</strong></span>
					<span><?php printf(esc_html($bank_account['iban'])); ?></span>
				</p>
				<p>
					<span><strong><?php esc_html_e('BIC', 'mondu'); ?>:</strong></span>
					<span><?php printf(esc_html($bank_account['bic'])); ?></span>
				</p>
				<?php if ( $net_terms ) { ?>
					<p>
						<span><strong><?php esc_html_e('Payment term', 'mondu'); ?>:</strong></span>
						<span><?php /* translators: %s: Days */ printf(esc_html__('%s Days', 'mondu'), esc_html($net_terms)); ?></span>
					</p>
				<?php } ?>
			</section>
			<?php
		}
	}

	public function get_mondu_net_term() {
		if ( !in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true) ) {
			return null;
		}

		if ( $this->order_data && isset($this->order_data['authorized_net_term']) ) {
			return $this->order_data['authorized_net_term'];
		}

		return null;
	}

	public function get_mondu_invoices_html() {
		foreach ( $this->invoices_data as $invoice ) {
			?>
			<hr>
			<p>
				<span><strong><?php esc_html_e('Invoice state', 'mondu'); ?>:</strong></span>
				<?php printf(esc_html($invoice['state'])); ?>
			</p>
			<p>
				<span><strong><?php esc_html_e('Invoice number', 'mondu'); ?>:</strong></span>
				<?php printf(esc_html($invoice['invoice_number'])); ?>
			</p>
			<p>
				<span><strong><?php esc_html_e('Total', 'mondu'); ?>:</strong></span>
				<?php printf('%s %s', esc_html( (string) $invoice['gross_amount_cents'] / 100 ), esc_html($invoice['order']['currency'])); ?>
			</p>
			<p>
				<span><strong><?php esc_html_e('Paid out', 'mondu'); ?>:</strong></span>
				<?php printf(esc_html($invoice['paid_out'] ? __('Yes', 'mondu') : __('No', 'mondu'))); ?>
			</p>
			<?php
			$this->get_mondu_credit_note_html($invoice);
			if ( 'canceled' !== $invoice['state'] ) {
				?>
				<?php
					$mondu_data = [
						'order_id'       => $this->order->get_id(),
						'invoice_id'     => $invoice['uuid'],
						'mondu_order_id' => $this->order_data['uuid'],
						'security'       => wp_create_nonce('mondu-cancel-invoice'),
					];
					?>
					<button data-mondu='<?php echo( wp_json_encode($mondu_data) ); ?>' id="mondu-cancel-invoice-button" type="submit" class="button grant_access">
						<?php esc_html_e('Cancel Invoice', 'mondu'); ?>
					</button>
				<?php
			}
		}
	}

	public function get_mondu_credit_note_html( $invoice ) {
		if ( empty($invoice['credit_notes']) ) {
			return null;
		}

		?>
			<p><strong><?php esc_html_e('Credit Notes', 'mondu'); ?>:</strong></p>
		<?php

		foreach ( $invoice['credit_notes'] as $note ) {
			?>
			<li>
				<?php printf('%s: %s %s (%s: %s %s) %s', '<strong>#' . esc_html($note['external_reference_id']) . '</strong>', esc_html( $note['gross_amount_cents'] / 100 ), esc_html($invoice['order']['currency']), esc_html__('Tax', 'mondu'), esc_html( $note['tax_cents'] / 100 ), esc_html($invoice['order']['currency']), $note['notes'] ? '- ' . esc_html( $note['notes'] ) : '' ); ?>
			</li>
			<?php
		}
	}

	/**
	 * Get Mondu WCPDF HTML
	 *
	 * @return false|string|null
	 */
	public function get_mondu_wcpdf_section_html() {
		if ( !in_array($this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true) ) {
			return null;
		}

		$file = MONDU_VIEW_PATH . '/pdf/mondu-invoice-section.php';
		if ( !file_exists($file) ) {
			return null;
		}

		// These variables are used in the file that is included
		$wcpdf_shop_name = $this->get_wcpdf_shop_name();
		$payment_method = $this->order->get_payment_method();
		$bank_account = $this->order_data['bank_account'];
		$invoice_number = Helper::get_invoice_number( $this->order );
		$net_terms = $this->get_mondu_net_term();

		include $file;
	}

	private function get_invoices() {
		try {
			return $this->mondu_request_wrapper->get_invoices($this->order->get_id());
		} catch ( ResponseException $e ) {
			return [];
		}
	}

	private function get_order() {
		try {
			return $this->mondu_request_wrapper->get_order($this->order->get_id());
		} catch ( ResponseException $e ) {
			return [];
		}
	}
}
