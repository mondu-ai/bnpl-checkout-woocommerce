<p>
  <label for="billie_invoice_id"><?php echo __('Invoice-ID','billie'); ?></label><br>
  <input
    name="billie_invoice_id" id="billie_invoice_id" type="text"
    <?php if('completed' === $invoice_status): ?> readonly <?php endif; ?>
    value="<?php echo $invoice_id; ?>"/>
</p>
