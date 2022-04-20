<p>
  <label for="mondu_invoice_id"><?php echo __('Invoice-ID','mondu'); ?></label><br>
  <input
    name="mondu_invoice_id" id="mondu_invoice_id" type="text"
    <?php if('completed' === $invoice_status): ?> readonly <?php endif; ?>
    value="<?php echo $invoice_id; ?>"/>
</p>
