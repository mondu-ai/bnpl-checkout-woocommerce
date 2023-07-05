<?php

namespace Mondu\Mondu\Support;

use Mondu\Plugin;
use WC_Logger_Interface;
use WC_Order;

class Helper {
	/**
	 * Not Null or Empty
	 *
	 * @param $value
	 * @return bool
	 */
	public static function not_null_or_empty( $value ) {
		return null !== $value && '' !== $value;
	}

	/**
	 * Create invoice url
	 *
	 * @param WC_Order $order
	 * @return mixed|void
	 */
	public static function create_invoice_url( WC_Order $order ) {
		if ( has_action('generate_wpo_wcpdf') ) {
			$invoice_url = add_query_arg(
				'_wpnonce',
				wp_create_nonce( 'generate_wpo_wcpdf' ),
				add_query_arg(
					[
						'action'        => 'generate_wpo_wcpdf',
						'document_type' => 'invoice',
						'order_ids'     => $order->get_id(),
						'my-account'    => true,
					],
					admin_url( 'admin-ajax.php' )
				)
			);
		} else {
			$invoice_url = $order->get_view_order_url();
		}

		/**
		 * Invoice Url Sent to Mondu API
		 *
		 * @since 1.3.2
		 */
		return apply_filters( 'mondu_invoice_url', $invoice_url );
	}

	/**
	 * Get invoice number
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public static function get_invoice_number( WC_Order $order ) {
		if ( function_exists( 'wcpdf_get_document' ) ) {
			$document = wcpdf_get_document( 'invoice', $order, false );
			if ( $document->get_number() ) {
				$invoice_number = $document->get_number()->get_formatted();
			} else {
				$invoice_number = $order->get_order_number();
			}
		} else {
			$invoice_number = $order->get_order_number();
		}

		/**
		 * Reference ID for invoice
		 *
		 * @since 1.3.2
		 */
		return apply_filters( 'mondu_invoice_reference_id', $invoice_number );
	}

	/**
	 * Get language
	 *
	 * @return string
	 */
	public static function get_language() {
		/**
		 * Locale for the order creation
		 *
		 * @since 2.0.0
		 */
		$language = apply_filters('mondu_order_locale', get_locale());
		return substr($language, 0, 2);
	}

	/**
	 * Is Production
	 *
	 * @return bool
	 */
	public static function is_production() {
		$global_settings = get_option( Plugin::OPTION_NAME );

		$is_production = false;
		if ( is_array( $global_settings )
			&& isset( $global_settings['field_sandbox_or_production'] )
			&& 'production' === $global_settings['field_sandbox_or_production']
		) {
			$is_production = true;
		}

		return $is_production;
	}

	public static function log( array $message, $level = 'DEBUG' ) {
		$logger = wc_get_logger();
		$logger->log( $level, wc_print_r($message, true), [ 'source' => 'mondu' ] );
	}
}
