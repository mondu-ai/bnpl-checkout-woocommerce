<?php if ( ! defined( 'ABSPATH' ) ) exit;

if($payment_method === 'mondu_invoice') {
    ?>
      <section>
        <p>
            <?php printf(__("mondu_invoice_section", "mondu"), $wcpdfShopName); ?>
        </p>
      </section>
      <br/>
    <?php
}

if ($payment_method === 'mondu_direct_debit') {
    ?>
    <section>
        <p>
            <?php printf(__('mondu_sepa_section', 'mondu'), $wcpdfShopName) ?>
        </p>
    </section>
    <?php
} 

if ($payment_method === 'mondu_installment') {
    ?>
    <section>
        <p>
            <?php printf(__('mondu_installment_section', 'mondu'), $wcpdfShopName) ?>
        </p>
    </section>
    <?php
} 
?>
