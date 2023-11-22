<?php

namespace Mondu\Mondu\Support;

use Mondu\Plugin;
use WC_Order;
use WP_Query;

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
	 * Get invoice WCPDF document
	 *
	 * @param WC_Order $order
	 * @return mixed
	 */
	public static function get_invoice( WC_Order $order ) {
		if ( function_exists( 'wcpdf_get_invoice' ) ) {
			return wcpdf_get_invoice( $order, false );
		} else {
			return $order;
		}
	}

	/**
	 * Get invoice number
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public static function get_invoice_number( WC_Order $order ) {
		if ( function_exists( 'wcpdf_get_invoice' ) ) {
			$document = wcpdf_get_invoice( $order, false );
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
	 * Get order from order number
	 * Tries to get it using the meta key _order_number otherwise gets it according to the plugin
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public static function get_order_from_order_number( $order_number ) {
		$order = wc_get_order( $order_number );
		if ( $order ) {
			return $order;
		}

		$search_key = '_order_number';
		$search_term = $order_number;

		if ( is_plugin_active( 'custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php' ) ) {
			$search_key = '_alg_wc_full_custom_order_number';
		}

		if ( is_plugin_active( 'wp-lister-amazon/wp-lister-amazon.php' ) ) {
			$search_key = '_wpla_amazon_order_id';
		}

		if ( is_plugin_active( 'yith-woocommerce-sequential-order-number-premium/init.php' ) ) {
			$search_key = '_ywson_custom_number_order_complete';
		}

		if ( is_plugin_active( 'woocommerce-jetpack/woocommerce-jetpack.php' ) || is_plugin_active( 'booster-plus-for-woocommerce/booster-plus-for-woocommerce.php' ) ) {
			$wcj_order_numbers_enabled = get_option( 'wcj_order_numbers_enabled' );

			// Get prefix and suffix options
			$prefix = do_shortcode( get_option( 'wcj_order_number_prefix', '' ) );
			$prefix .= date_i18n( get_option( 'wcj_order_number_date_prefix', '' ) );
			$suffix = do_shortcode( get_option( 'wcj_order_number_suffix', '' ) );
			$suffix .= date_i18n( get_option( 'wcj_order_number_date_suffix', '' ) );

			// Ignore suffix and prefix from search input
			$search_no_suffix            = preg_replace( "/\A{$prefix}/i", '', $order_number );
			$search_no_suffix_and_prefix = preg_replace( "/{$suffix}\z/i", '', $search_no_suffix );
			$final_search                = empty( $search_no_suffix_and_prefix ) ? $order_number : $search_no_suffix_and_prefix;

			$search_term_fallback = substr( $final_search, strlen( $prefix ) );
			$search_term_fallback = ltrim( $search_term_fallback, 0 );

			if ( strlen( $suffix ) > 0 ) {
				$search_term_fallback = substr( $search_term_fallback, 0, -strlen( $suffix ) );
			}

			if ( 'yes' == $wcj_order_numbers_enabled ) {
				if ( 'no' == get_option( 'wcj_order_number_sequential_enabled' ) ) {
					$order_id = $final_search;
				} else {
					$search_key  = '_wcj_order_number';
					$search_term = $final_search;
				}
			}
		}

		if ( !isset( $order_id ) ) {
			$args  = array(
				'numberposts'            => 1,
				'post_type'              => 'shop_order',
				'fields'                 => 'ids',
				'post_status'            => 'any',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => $search_key,
						'value'   => $search_term,
						'compare' => '=',
					),
				)
			);
			$query = new WP_Query( $args );

			if ( !empty( $query->posts ) ) {
				$order_id = $query->posts[ 0 ];
			} elseif ( isset( $search_term_fallback ) ) {
				$args  = array(
					'numberposts'            => 1,
					'post_type'              => 'shop_order',
					'fields'                 => 'ids',
					'post_status'            => 'any',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'     => $search_key,
							'value'   => $search_term_fallback,
							'compare' => '=',
						),
					)
				);
				$query = new WP_Query( $args );

				if ( !empty( $query->posts ) ) {
					$order_id = $query->posts[ 0 ];
				}
			}
		}

		if ( isset( $order_id ) ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				return $order;
			}
		} else {
			Helper::log([
				'message'              => 'Error trying to fetch the order',
				'order_id_isset'       => isset( $order_id ),
				'order_number'         => $order_number,
				'search_key'           => $search_key,
				'search_term'          => $search_term,
				'search_term_fallback' => isset( $search_term_fallback ),
			]);
		}
	}

	/**
	 * Is Production
	 *
	 * @return bool
	 */
	public static function is_production() {
		$global_settings = get_option( Plugin::OPTION_NAME );

		if ( is_array( $global_settings )
			&& isset( $global_settings['sandbox_or_production'] )
			&& 'production' === $global_settings['sandbox_or_production']
		) {
			return true;
		}
		return false;
	}

	public static function log( array $message, $level = 'DEBUG' ) {
		$logger = wc_get_logger();
		$logger->log( $level, wc_print_r($message, true), [ 'source' => 'mondu' ] );
	}
}
