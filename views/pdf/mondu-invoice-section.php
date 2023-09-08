<?php
if ( !defined( 'ABSPATH' ) ) {
	die( 'Direct access not allowed' );
}

if ( !isset($payment_method) ) {
	$payment_method = '';
}

if ( !isset($wcpdf_shop_name) ) {
	$wcpdf_shop_name = '';
}

if ( 'mondu_invoice' === $payment_method ) {
	?>
	<section>
		<p>
			<?php
			/* translators: %s: Company */ printf(wp_kses(__('This invoice was created in accordance with the general terms and conditions of <strong>%s</strong> and <strong>Mondu GmbH</strong> for the purchase on account payment model. Please pay debt-discharging to the following account:', 'mondu'), [
				'strong' => [],
				'br'     => [],
			]), esc_html($wcpdf_shop_name));
			?>
		</p>
	</section>
	<?php if ( $bank_account ) { ?>
		<br />
		<style>
			.mondu-payment > table > tbody > tr > td {
				min-width: 130px;
			}
		</style>
		<section class="woocommerce-order-details mondu-payment">
			<table>
				<tr>
					<td><strong><?php esc_html_e('Account holder', 'mondu'); ?>:</strong></td>
					<td><?php printf(esc_html($bank_account['account_holder'])); ?></span></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e('Bank', 'mondu'); ?>:</strong></td>
					<td><?php printf(esc_html($bank_account['bank'])); ?></td>
				</tr>
				<?php if ( $mondu_uk_buyer ) { ?>
					<?php if ( $bank_account['account_number'] ) { ?>
						<tr>
							<td><strong><?php esc_html_e('Account number', 'mondu'); ?>:</strong></td>
							<td><?php printf(esc_html($bank_account['account_number'])); ?></td>
						</tr>
					<?php } ?>
					<?php if ( $bank_account['sort_code'] ) { ?>
						<tr>
							<td><strong><?php esc_html_e('Sort code', 'mondu'); ?>:</strong></td>
							<td><?php printf(esc_html($bank_account['sort_code'])); ?></td>
						</tr>
					<?php } ?>
				<?php } else { ?>
					<?php if ( $bank_account['iban'] ) { ?>
						<tr>
							<td><strong><?php esc_html_e('IBAN', 'mondu'); ?>:</strong></td>
							<td><?php printf(esc_html($bank_account['iban'])); ?></td>
						</tr>
					<?php } ?>
					<?php if ( $bank_account['bic'] ) { ?>
						<tr>
							<td><strong><?php esc_html_e('BIC', 'mondu'); ?>:</strong></td>
							<td><?php printf(esc_html($bank_account['bic'])); ?></td>
						</tr>
					<?php } ?>
				<?php } ?>
				<tr>
					<td><strong><?php esc_html_e('Purpose', 'mondu'); ?>:</strong></td>
					<td><?php echo esc_html__('Invoice number', 'mondu') . ' ' . esc_html($invoice_number . ' ' . $this->get_wcpdf_shop_name()); ?></td>
				</tr>
				<?php if ( $net_terms ) { ?>
					<tr>
						<td><strong><?php esc_html_e('Payment term', 'mondu'); ?>:</strong></td>
						<td><?php /* translators: %s: Days */ printf(esc_html__('%s Days', 'mondu'), esc_html($net_terms)); ?></td>
					</tr>
				<?php } ?>
			</table>
		</section>
		<?php
	}
}

if ( 'mondu_direct_debit' === $payment_method ) {
	$payment_name = $mondu_uk_buyer ? __('direct debit', 'mondu') : __('SEPA direct debit', 'mondu');
	?>
	<section>
		<p>
			<?php
				/* translators: %1$s: Company %2$s: direct debit */
				printf(wp_kses(__('This invoice was created in accordance with the general terms and conditions of <strong>%1$s</strong> and <strong>Mondu GmbH</strong> for the purchase on account payment model.<br/><br/>Since you have chosen the payment method to purchase on account with payment via %2$s through Mondu, the invoice amount will be debited from your bank account on the due date.<br/><br/>Before the amount is debited from your account, you will receive notice of the direct debit. Kindly make sure you have sufficient funds in your account.', 'mondu'), [
					'strong' => [],
					'br'     => [],
				]), esc_html($wcpdf_shop_name), esc_html($payment_name));
			?>
		</p>
	</section>
	<?php
}

if ( 'mondu_installment' === $payment_method ) {
	$payment_name = $bank_account['iban'] ? __('SEPA direct debit', 'mondu') : __('direct debit', 'mondu');
	?>
	<section>
		<p>
			<?php
				/* translators: %1$s: Company %2$s: direct debit */
				printf(wp_kses(__('This invoice was created in accordance with the general terms and conditions of <strong>%1$s</strong> and <strong>Mondu GmbH</strong> for the instalment payment model.<br/><br/>Since you have chosen the instalment payment method via %2$s through Mondu, the individual installments will be debited from your bank account on the due date.<br/><br/>Before the amounts are debited from your account, you will receive notice regarding the direct debit. Kindly make sure you have sufficient funds in your account. In the event of changes to your order, the instalment plan will be adjusted to reflect the new order total.', 'mondu'), [
					'strong' => [],
					'br'     => [],
				]), esc_html($wcpdf_shop_name), esc_html($payment_name));
			?>
		</p>
	</section>
	<?php
}
?>
