<?php
/**
 * Plugin constants file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ORDER_ID_KEY        = '_mondu_order_id';
const INVOICE_ID_KEY      = '_mondu_invoice_id';
const OPTION_NAME         = 'mondu_account';
const PAYMENT_METHODS     = array(
  'invoice'      => 'mondu_invoice',
  'direct_debit' => 'mondu_direct_debit',
  'installment'  => 'mondu_installment',
);
