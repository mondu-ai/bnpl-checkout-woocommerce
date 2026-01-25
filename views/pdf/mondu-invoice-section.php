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

if ( !isset($bank_account) ) {
	$bank_account = '';
}

if ( !isset($mondu_uk_buyer) ) {
	$mondu_uk_buyer = '';
}

if ( !isset($net_terms) ) {
	$net_terms = '';
}

if ( !isset($invoice_number) ) {
	$invoice_number = '';
}

if ( 'mondu_invoice' === $payment_method ) {
	?>
	<section>
		<p>
			<?php
                printf(wp_kses(__('Please be informed that the invoice amount has been assigned in accordance with the General Terms and Conditions of <strong>%s</strong> for the Invoice with Payment Term Model to Mondu Financial Services B.V. (first assignment), and Mondu Capital S.à r.l., acting on behalf of Compartment 4 (second assignment). <br/>We request payment in full to the following account:', 'mondu'), [
                        'strong' => [],
                ]), esc_html($wcpdf_shop_name));

				/* translators: %s: Company */
				printf(wp_kses(__('Please be informed that the invoice amount has been assigned in accordance with the General Terms and Conditions of <strong>%s</strong> for the Invoice with Payment Term Model to Mondu Financial Services B.V. (first assignment), and Mondu Capital S.à r.l., acting on behalf of Compartment 4 (second assignment).', 'mondu'), [
					'strong' => [],
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
					<td><strong><?php esc_html_e('Payment reference', 'mondu'); ?>:</strong></td>
					<td><?php echo esc_html($invoice_number); ?></td>
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
				/* translators: %1$s: Company */
				printf(wp_kses(__('Please be informed that the invoice amount has been assigned in accordance with the General Terms and Conditions of <strong>%1$s</strong> for the Invoice with Payment Term Model to Mondu Financial Services B.V. (first assignment), and Mondu Capital S.à r.l., acting on behalf of Compartment 4 (second assignment).', 'mondu'), [
					'strong' => [],
				]), esc_html($wcpdf_shop_name));
			?>
		</p>
		<p>
			<?php
				/* translators: %1$s: direct debit */
				printf(esc_html__('Since you have selected the Mondu payment method Invoice Purchase by SEPA Direct Debit, the invoice amount will be debited from your bank account on the due date.', 'mondu'));
			?>
		</p>
		<p>
			<?php esc_html_e('You will receive a direct debit notification before the amount is debited from your account. Please ensure that you have sufficient funds in your account.', 'mondu'); ?>
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
				/* translators: %1$s: Company */
				printf(wp_kses(__('Please be informed that the invoice amount has been assigned and pledged in accordance with the General Terms and Conditions of <strong>%1$s</strong> for the Installment Payment Model to Mondu Financial Services B.V. (first assignment), and Mondu Capital S.à r.l., acting on behalf of Compartment 4 (second assignment).', 'mondu'), [
					'strong' => [],
				]), esc_html($wcpdf_shop_name), esc_html($payment_name));
			?>
		</p>
		<p>
			<?php
				/* translators: %1$s: direct debit */
				printf(esc_html__('Since you have selected the Mondu payment method Installment Purchase by SEPA Direct Debit, the individual installments will be debited from your bank account on their respective due date.', 'mondu'));
			?>
		</p>
		<p>
			<?php esc_html_e('Before the amounts are debited from your account, you will receive an advance notice regarding the direct debit. Please ensure that you have sufficient funds in your account. In the event of changes to your order, the installment plan will be adjusted to the new order total.', 'mondu'); ?>
		</p>
	</section>
	<?php
}

if ( 'mondu_pay_now' === $payment_method ) {
    $payment_name = __('Instant Pay', 'mondu');
    ?>
    <section>
        <p>
            <?php
                printf(wp_kses(__('Please be informed that the invoice amount has been assigned in accordance with the General Terms and Conditions of <strong>%s</strong> for the Mondu Payment Model to Mondu Financial Services B.V. (first assignment), and Mondu Capital S.à r.l. (second assignment).', 'mondu'), [
					'strong' => [],
				]), esc_html($wcpdf_shop_name));
            ?>
        </p>
        <p>
            <?php esc_html_e('The invoice has been paid with Mondu Instant Pay.', 'mondu'); ?>
        </p>
    </section>
    <?php
}

if ( 'mondu_installment_by_invoice' === $payment_method ) {
	?>
	<section>
		<p>
			<?php
				/* translators: %1$s: Company */
				printf(wp_kses(__('This invoice was created in accordance with the general terms and conditions of <strong>%1$s</strong> and <strong>Mondu GmbH</strong> for the installment payment model.', 'mondu'), [
					'strong' => [],
				]), esc_html($wcpdf_shop_name));
			?>
		</p>
		<p>
			<?php printf(esc_html__('Since you have chosen the Mondu\'s installment payment method via bank transfer, the individual installments will need to be paid via bank transfer before the due date.', 'mondu')); ?>
		</p>
		<p>
			<?php esc_html_e('Before the installment is due, you will receive a notice regarding the upcoming payment. Kindly make sure that you make the payment before the due date of each installment. In the event of changes to your order, the installment plan will be adjusted to reflect the new order total.', 'mondu'); ?>
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
					<td><strong><?php esc_html_e('Payment reference', 'mondu'); ?>:</strong></td>
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
