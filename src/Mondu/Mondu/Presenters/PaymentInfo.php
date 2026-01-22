<?php
/**
 * Payment Info Presenter
 *
 * @package Mondu
 */
namespace Mondu\Mondu\Presenters;

use Mondu\Exceptions\ResponseException;
use Mondu\Mondu\Support\Helper;
use Mondu\Mondu\MonduRequestWrapper;
use Mondu\Plugin;
use Exception;
use WC_Order;

/**
 * Payment Info Presenter
 *
 * @package Mondu
 */
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

	/**
	 * PaymentInfo constructor.
	 *
	 * @param int $order_id Order ID
	 */
	public function __construct( $order_id ) {
		$this->order                 = new WC_Order($order_id);
		$this->mondu_request_wrapper = new MonduRequestWrapper();
		$this->order_data            = $this->get_order();
		$this->invoices_data         = $this->get_invoices();
	}

	/**
	 * Get Order Data
	 *
	 * @return array
	 */
	public function get_order_data() {
		return $this->order_data;
	}

	/**
	 * Get Invoices Data
	 *
	 * @return array
	 */
	public function get_invoices_data() {
		return $this->invoices_data;
	}

	/**
	 * Get WCPDF Shop Name
	 *
	 * @return string
	 */
	public function get_wcpdf_shop_name() {
		if ( ! class_exists( '\WPO_WCPDF' ) ) {
			return get_bloginfo( 'name' );
		}

		try {
			$wcpdf = \WPO_WCPDF::instance();

			if ( ! isset( $wcpdf->documents ) || ! isset( $wcpdf->documents->documents ) ) {
				return get_bloginfo( 'name' );
			}

			$invoice_keys = [
				'\WPO\WC\PDF_Invoices\Documents\Invoice',
				'WPO\WC\PDF_Invoices\Documents\Invoice',
				'\WPO\IPS\Documents\Invoice',
				'invoice',
			];

			foreach ( $invoice_keys as $key ) {
				if ( isset( $wcpdf->documents->documents[ $key ] ) ) {
					$shop_name = $wcpdf->documents->documents[ $key ]->get_shop_name();
					if ( $shop_name !== null ) {
						return $shop_name;
					}
				}
			}

			return get_bloginfo( 'name' );
		} catch ( Exception $e ) {
			return get_bloginfo( 'name' );
		}
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
					<span><strong><?php esc_html_e( 'Order state', 'mondu' ); ?>:</strong></span>
					<span><?php printf( esc_html( $order_data['state'] ) ); ?></span>
				</p>
				<p>
					<span><strong><?php esc_html_e( 'Mondu ID', 'mondu' ); ?>:</strong></span>
					<span><?php printf( esc_html( $order_data['uuid'] ) ); ?></span>
				</p>
				<?php
				if ( in_array( $this->order_data['state'], [ 'confirmed', 'partially_shipped' ], true ) ) {
					?>
					<?php
					$mondu_data = [
						'order_id' => $this->order->get_id(),
						'security' => wp_create_nonce( 'mondu-create-invoice' ),
					];
					?>
					<button data-mondu='<?php echo( wp_json_encode( $mondu_data ) ); ?>' id="mondu-create-invoice-button" type="submit" class="button grant_access">
						<?php esc_html_e( 'Create Invoice', 'mondu' ); ?>
					</button>
					<?php
				}
				?>
				<?php
				if ( in_array( $this->order_data['state'], [ 'authorized' ], true ) &&
					$this->order->get_status() === 'on-hold'
				) {
					?>
					<?php
					$mondu_data = [
						'order_id'   => $this->order->get_id(),
						'order_uuid' => $order_data['uuid'],
						'security'   => wp_create_nonce( 'mondu-confirm-order' ),
					];
					?>
					<button data-mondu='<?php echo( wp_json_encode( $mondu_data ) ); ?>' id="mondu-confirm-order-button" type="submit" class="button grant_access">
						<?php esc_html_e( 'Confirm Order', 'mondu' ); ?>
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
					<span><strong><?php esc_html_e( 'Corrupt Mondu order!', 'mondu' ); ?></strong></span>
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
		if ( !in_array( $this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true ) ) {
			return null;
		}

		if ( !isset( $this->order_data['bank_account'] ) ) {
			return null;
		}

		$bank_account   = $this->order_data['bank_account'];
		$net_terms      = $this->get_mondu_net_term();
		$mondu_uk_buyer = $bank_account['account_number'] && $bank_account['sort_code'];

		if ( $bank_account ) {
			?>
			<style>
				.mondu-payment > table > tbody > tr > td {
					min-width: 130px;
				}
			</style>
			<section class="woocommerce-order-details mondu-payment">
				<p>
					<span><strong><?php esc_html_e( 'Account holder', 'mondu' ); ?>:</strong></span>
					<span><?php printf( esc_html( $bank_account['account_holder'] ) ); ?></span>
				</p>
				<p>
					<span><strong><?php esc_html_e( 'Bank', 'mondu' ); ?>:</strong></span>
					<span><?php printf( esc_html( $bank_account['bank'] ) ); ?></span>
				</p>
				<?php if ( $mondu_uk_buyer ) { ?>
					<?php if ( $bank_account['account_number'] ) { ?>
						<p>
							<span><strong><?php esc_html_e( 'Account number', 'mondu' ); ?>:</strong></span>
							<span><?php printf( esc_html( $bank_account['account_number'] ) ); ?></span>
						</p>
					<?php } ?>
					<?php if ( $bank_account['sort_code'] ) { ?>
						<p>
							<span><strong><?php esc_html_e( 'Sort code', 'mondu' ); ?>:</strong></span>
							<span><?php printf( esc_html($bank_account['sort_code'] ) ); ?></span>
						</p>
					<?php } ?>
				<?php } else { ?>
					<?php if ( $bank_account['iban'] ) { ?>
						<p>
							<span><strong><?php esc_html_e( 'IBAN', 'mondu' ); ?>:</strong></span>
							<span><?php printf( esc_html( $bank_account['iban'] ) ); ?></span>
						</p>
					<?php } ?>
					<?php if ( $bank_account['bic'] ) { ?>
						<p>
							<span><strong><?php esc_html_e( 'BIC', 'mondu' ); ?>:</strong></span>
							<span><?php printf( esc_html( $bank_account['bic'] ) ); ?></span>
						</p>
					<?php } ?>
				<?php } ?>
				<?php if ( $net_terms ) { ?>
					<p>
						<span><strong><?php esc_html_e( 'Payment term', 'mondu' ); ?>:</strong></span>
						<span><?php /* translators: %s: Days */ printf( esc_html__( '%s Days', 'mondu' ), esc_html($net_terms) ); ?></span>
					</p>
				<?php } ?>
			</section>
			<?php
		}
	}

	/**
	 * Get Mondu Net Term
	 *
	 * @return int|null
	 */
	public function get_mondu_net_term() {
		if ( !in_array( $this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true ) ) {
			return null;
		}

		if ( $this->order_data && isset( $this->order_data['authorized_net_term'] ) ) {
			return $this->order_data['authorized_net_term'];
		}

		return null;
	}

	/**
	 * Get Mondu Invoices HTML
	 *
	 * @return void
	 */
	public function get_mondu_invoices_html() {
		foreach ( $this->invoices_data as $invoice ) {
			$currency = isset( $invoice['order']['currency'] ) ? $invoice['order']['currency'] : $this->order->get_currency();
			?>
			<hr>
			<p>
				<span><strong><?php esc_html_e( 'Invoice state', 'mondu' ); ?>:</strong></span>
				<?php printf( esc_html( isset( $invoice['state'] ) ? $invoice['state'] : '' ) ); ?>
			</p>
			<p>
				<span><strong><?php esc_html_e( 'Invoice number', 'mondu' ); ?>:</strong></span>
				<?php printf( esc_html( isset( $invoice['invoice_number'] ) ? $invoice['invoice_number'] : '' ) ); ?>
			</p>
			<p>
				<span><strong><?php esc_html_e( 'Total', 'mondu' ); ?>:</strong></span>
				<?php printf( '%s %s', esc_html( (string) ( isset( $invoice['gross_amount_cents'] ) ? $invoice['gross_amount_cents'] / 100 : 0 ) ), esc_html( $currency ) ); ?>
			</p>
			<p>
				<span><strong><?php esc_html_e( 'Paid out', 'mondu' ); ?>:</strong></span>
				<?php printf( esc_html( ( isset( $invoice['paid_out'] ) && $invoice['paid_out'] ) ? __( 'Yes', 'mondu' ) : __( 'No', 'mondu') ) ); ?>
			</p>
			<?php
			$this->get_mondu_credit_note_html( $invoice, $currency );
			if ( isset( $invoice['state'] ) && 'canceled' !== $invoice['state'] ) {
				?>
				<?php
					$mondu_data = [
						'order_id'       => $this->order->get_id(),
						'invoice_id'     => isset( $invoice['uuid'] ) ? $invoice['uuid'] : '',
						'mondu_order_id' => isset( $this->order_data['uuid'] ) ? $this->order_data['uuid'] : '',
						'security'       => wp_create_nonce( 'mondu-cancel-invoice' ),
					];
					?>
					<button data-mondu='<?php echo( wp_json_encode( $mondu_data ) ); ?>' id="mondu-cancel-invoice-button" type="submit" class="button grant_access">
						<?php esc_html_e( 'Cancel Invoice', 'mondu' ); ?>
					</button>
				<?php
			}
		}
	}

	/**
	 * Get Mondu Credit Note HTML
	 *
	 * @param array  $invoice  Invoice
	 * @param string $currency Currency code
	 * @return void
	 */
	public function get_mondu_credit_note_html( $invoice, $currency = null ) {
		if ( empty( $invoice['credit_notes'] ) ) {
			return null;
		}

		if ( $currency === null ) {
			$currency = isset( $invoice['order']['currency'] ) ? $invoice['order']['currency'] : $this->order->get_currency();
		}

		?>
			<p><strong><?php esc_html_e( 'Credit Notes', 'mondu' ); ?>:</strong></p>
		<?php

		foreach ( $invoice['credit_notes'] as $note ) {
			$external_ref = isset( $note['external_reference_id'] ) ? $note['external_reference_id'] : '';
			$gross_amount = isset( $note['gross_amount_cents'] ) ? $note['gross_amount_cents'] / 100 : 0;
			$tax_amount   = isset( $note['tax_cents'] ) ? $note['tax_cents'] / 100 : 0;
			$notes_text   = isset( $note['notes'] ) && $note['notes'] ? '- ' . esc_html( $note['notes'] ) : '';
			?>
			<li>
				<?php printf( '%s: %s %s (%s: %s %s) %s', '<strong>#' . esc_html( $external_ref ) . '</strong>', esc_html( $gross_amount ), esc_html( $currency ), esc_html__( 'Tax', 'mondu' ), esc_html( $tax_amount ), esc_html( $currency ), $notes_text ); ?>
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
		if ( !in_array( $this->order->get_payment_method(), Plugin::PAYMENT_METHODS, true ) ) {
			return null;
		}

		$file = MONDU_VIEW_PATH . '/pdf/mondu-invoice-section.php';
		if ( !file_exists( $file ) ) {
			return null;
		}

		if ( empty( $this->order_data ) || !isset( $this->order_data['bank_account'] ) ) {
			return null;
		}

		try {
			$wcpdf_shop_name = $this->get_wcpdf_shop_name();
			$payment_method  = $this->order->get_payment_method();
			$bank_account    = $this->order_data['bank_account'];
			$invoice_number  = Helper::get_invoice_number( $this->order );
			$net_terms       = $this->get_mondu_net_term();
			$mondu_uk_buyer  = isset( $bank_account['account_number'] ) && isset( $bank_account['sort_code'] ) 
				&& $bank_account['account_number'] && $bank_account['sort_code'];

			include $file;
		} catch ( Exception $e ) {
			Helper::log([
				'message'  => 'Error generating WCPDF section',
				'order_id' => $this->order->get_id(),
				'error'    => $e->getMessage(),
			], 'ERROR');
			return null;
		}
	}

	/**
	 * Get Invoices
	 *
	 * @return array
	 */
	private function get_invoices() {
		try {
			return $this->mondu_request_wrapper->get_invoices( $this->order->get_id() );
		} catch ( ResponseException $e ) {
			return [];
		}
	}

	/**
	 * Get Order
	 *
	 * @return array
	 */
	private function get_order() {
		try {
			return $this->mondu_request_wrapper->get_order( $this->order->get_id() );
		} catch ( ResponseException $e ) {
			return [];
		}
	}
}
