<?php if ( ! defined( 'ABSPATH' ) ) exit;

if ($payment_method === 'mondu_invoice') {
  ?>
    <section>
      <p>
        <?php /* translators: %s: Company */printf(__('This invoice was created in accordance with the general terms and conditions of <strong>%s</strong> and <strong>Mondu GmbH</strong> for the purchase on account payment model. Please pay debt-discharging to the following account:', 'mondu'), $wcpdfShopName); ?>
      </p>
    </section>
    <br/>
  <?php
}

if ($payment_method === 'mondu_direct_debit') {
  ?>
    <section>
      <p>
        <?php /* translators: %s: Company */printf(__('This invoice was created in accordance with the general terms and conditions of <strong>%s</strong> and <strong>Mondu GmbH</strong> for the purchase on account payment model.<br/><br/>Since you have chosen the payment method to purchase on account with payment via SEPA direct debit through Mondu, the invoice amount will be debited from your bank account on the due date.<br/><br/>Before the amount is debited from your account, you will receive notice of the direct debit. Kindly make sure you have sufficient funds in your account.', 'mondu'), $wcpdfShopName) ?>
      </p>
    </section>
  <?php
}

if ($payment_method === 'mondu_installment') {
  ?>
    <section>
      <p>
        <?php /* translators: %s: Company */printf(__('This invoice was created in accordance with the general terms and conditions of <strong>%s</strong> and <strong>Mondu GmbH</strong> for the instalment payment model.<br/><br/>Since you have chosen the instalment payment method via SEPA direct debit through Mondu, the individual installments will be debited from your bank account on the due date.<br/><br/>Before the amounts are debited from your account, you will receive notice regarding the direct debit. Kindly make sure you have sufficient funds in your account. In the event of changes to your order, the instalment plan will be adjusted to reflect the new order total.', 'mondu'), $wcpdfShopName) ?>
      </p>
    </section>
  <?php
}
?>
