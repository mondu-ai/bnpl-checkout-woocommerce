<?php

if ( !defined('WP_UNINSTALL_PLUGIN') ) {
  exit;
}

// remove plugin options
delete_option('mondu_account');
delete_option('_mondu_webhook_secret');
delete_option('_mondu_credentials_validated');
delete_option('_mondu_webhooks_registered');

// remove plugin transients
delete_transient('mondu_merchant_payment_methods');
