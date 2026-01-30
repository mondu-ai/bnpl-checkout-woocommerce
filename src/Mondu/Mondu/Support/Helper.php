<?php
/**
 * Helper class
 *
 * @package Mondu
 */
namespace Mondu\Mondu\Support;

use Mondu\Plugin;
use WC_Order;
use WP_Query;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class Helper
 *
 * @package Mondu\Mondu\Support
 */
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
		if ( class_exists( '\WPO_WCPDF' ) && function_exists( 'WPO_WCPDF' ) ) {
			try {
				$wcpdf = \WPO_WCPDF();
				
				$access_type = 'logged_in';
				if ( isset( $wcpdf->endpoint ) && method_exists( $wcpdf->endpoint, 'get_document_link_access_type' ) ) {
					$access_type = $wcpdf->endpoint->get_document_link_access_type();
				}
				
				if ( 'full' === $access_type ) {
					$invoice_url = add_query_arg(
						[
							'action'        => 'generate_wpo_wcpdf',
							'document_type' => 'invoice',
							'order_ids'     => $order->get_id(),
							'access_key'    => $order->get_order_key(),
						],
						admin_url( 'admin-ajax.php' )
					);
					
					return apply_filters( 'mondu_invoice_url', $invoice_url );
				}
			} catch ( \Exception $e ) {
			}
		}
		
		$invoice_url = $order->get_view_order_url();

		return apply_filters( 'mondu_invoice_url', $invoice_url );
	}

	/**
	 * Get invoice WCPDF document
	 *
	 * @param WC_Order $order
	 * @return mixed
	 */
	public static function get_invoice( WC_Order $order ) {
		if ( function_exists( 'wcpdf_get_document' ) ) {
			return wcpdf_get_document( 'invoice', $order, false );
		} elseif ( function_exists( 'wcpdf_get_invoice' ) ) {
			return wcpdf_get_invoice( $order, false );
		}
		return $order;
	}

	/**
	 * Get invoice number for Mondu (invoice external_reference_id).
	 *
	 * With WCPDF: use WCPDF invoice document number (matches PDF). Order number is never
	 * used for invoice external_reference_id when WCPDF is active; if no document exists
	 * yet, the document is created (init) so the number comes from WCPDF sequence.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public static function get_invoice_number( WC_Order $order ) {
		$invoice_number = null;

		if ( class_exists( '\WPO_WCPDF' ) ) {
			if ( function_exists( 'wcpdf_get_document' ) ) {
				$document = wcpdf_get_document( 'invoice', $order, false );
				if ( $document && $document->get_number() ) {
					$invoice_number = $document->get_number()->get_formatted();
				}
				if ( ( $invoice_number === null || $invoice_number === '' ) && $order->get_meta( '_wcpdf_invoice_number' ) !== '' ) {
					$invoice_number = (string) $order->get_meta( '_wcpdf_invoice_number' );
				}
				if ( $invoice_number === null || $invoice_number === '' ) {
					$document = wcpdf_get_document( 'invoice', $order, true );
					if ( $document && $document->get_number() ) {
						$invoice_number = $document->get_number()->get_formatted();
					}
				}
			} elseif ( function_exists( 'wcpdf_get_invoice' ) ) {
				$document = wcpdf_get_invoice( $order, false );
				if ( $document && $document->get_number() ) {
					$invoice_number = $document->get_number()->get_formatted();
				}
				if ( ( $invoice_number === null || $invoice_number === '' ) && $order->get_meta( '_wcpdf_invoice_number' ) !== '' ) {
					$invoice_number = (string) $order->get_meta( '_wcpdf_invoice_number' );
				}
				if ( $invoice_number === null || $invoice_number === '' ) {
					$document = wcpdf_get_invoice( $order, true );
					if ( $document && $document->get_number() ) {
						$invoice_number = $document->get_number()->get_formatted();
					}
				}
			}
		}

		if ( $invoice_number === null || $invoice_number === '' ) {
			$invoice_number = (string) $order->get_order_number();
		}

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
		$language = apply_filters( 'mondu_order_locale', get_locale() );
		return substr( $language, 0, 2 );
	}

	/**
	 * Get order from order number
	 * Tries to get it using the meta key _order_number otherwise gets it according to the plugin
	 *
	 * @param int|string $order_number
	 * @return false|WC_Order
	 */
	public static function get_order_from_order_number( $order_number ) {
		$order = wc_get_order( $order_number );
		if ( $order ) {
			return $order;
		}

		$search_key  = '_order_number';
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
			$prefix  = do_shortcode( get_option( 'wcj_order_number_prefix', '' ) );
			$prefix .= date_i18n( get_option( 'wcj_order_number_date_prefix', '' ) );
			$suffix  = do_shortcode( get_option( 'wcj_order_number_suffix', '' ) );
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

			if ( 'yes' === $wcj_order_numbers_enabled ) {
				if ( 'no' === get_option( 'wcj_order_number_sequential_enabled' ) ) {
					$order_id = $final_search;
				} else {
					$search_key  = '_wcj_order_number';
					$search_term = $final_search;
				}
			}
		}

		if ( !isset( $order_id ) ) {
			$args  = [
				'numberposts'            => 1,
				'post_type'              => 'shop_order',
				'fields'                 => 'ids',
				'post_status'            => 'any',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => $search_key,
						'value'   => $search_term,
						'compare' => '=',
					],
				],
			];
			$query = new WP_Query( $args );

			if ( !empty( $query->posts ) ) {
				$order_id = $query->posts[0];
			} elseif ( isset( $search_term_fallback ) ) {
				$args  = [
					'numberposts'            => 1,
					'post_type'              => 'shop_order',
					'fields'                 => 'ids',
					'post_status'            => 'any',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'     => $search_key,
							'value'   => $search_term_fallback,
							'compare' => '=',
						],
					],
				];
				$query = new WP_Query( $args );

				if ( !empty( $query->posts ) ) {
					$order_id = $query->posts[0];
				}
			}
		}

		if ( !isset( $order_id ) ) {
			self::log([
				'message'              => 'Error trying to fetch the order',
				'order_id_isset'       => isset( $order_id ),
				'order_number'         => $order_number,
				'search_key'           => $search_key,
				'search_term'          => $search_term,
				'search_term_fallback' => isset( $search_term_fallback ),
			]);
			return false;
		}

		return wc_get_order( $order_id );
	}

	/**
	 * Get order from mondu order uuid
	 *
	 * @param $mondu_order_uuid
	 * @return bool|WC_Order
	 */
	public static function get_order_from_mondu_uuid( $mondu_order_uuid ) {
		$search_key  = Plugin::ORDER_ID_KEY;
		$search_term = $mondu_order_uuid;

		$args     = [
			'numberposts'            => 1,
			'post_type'              => 'shop_order',
			'fields'                 => 'ids',
			'post_status'            => 'any',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => $search_key,
					'value'   => $search_term,
					'compare' => '=',
				],
			],
		];
		$query    = new WP_Query( $args );
		$order_id = $query->posts[0];
		$order    = wc_get_order( $order_id );

		if ( !$order ) {
			self::log([
				'message'              => 'Error trying to fetch the order',
				'order_id_isset'       => isset( $order_id ),
				'mondu_order_uuid'     => $mondu_order_uuid,
				'search_key'           => $search_key,
				'search_term'          => $search_term,
				'search_term_fallback' => isset( $search_term_fallback ),
			]);
		}

		return $order;
	}

	/**
	 * Get order from order number
	 *
	 * @param $order_number
	 * @param $mondu_order_uuid
	 * @return bool|WC_Order
	 */
	public static function get_order_from_order_number_or_uuid( $order_number = null, $mondu_order_uuid = null ) {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			return self::get_order_hpos( $order_number, $mondu_order_uuid );
		} else {
			// Traditional CPT-based orders are in use.
			return self::get_order_cpt( $order_number, $mondu_order_uuid );
		}
	}

	/**
	 * Get order from order number
	 *
	 * @param $order_number
	 * @param $mondu_order_uuid
	 * @return bool|WC_Order
	 */
	private static function get_order_hpos( $order_number, $mondu_order_uuid ) {
		$order = wc_get_order( $order_number );

		if ( $order ) {
			return $order;
		}

		$orders = wc_get_orders([
			'meta_key'   => Plugin::ORDER_ID_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $mondu_order_uuid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		]);

		if ( !empty( $orders ) ) {
			return $orders[0];
		}

		return false;
	}

	/**
	 * Get order from order number
	 *
	 * @param $order_number
	 * @param $mondu_order_uuid
	 * @return bool|WC_Order
	 */
	private static function get_order_cpt( $order_number = null, $mondu_order_uuid = null ) {
		$order = false;

		if ( $order_number ) {
			$order = static::get_order_from_order_number( $order_number );
		}

		if ( !$order && $mondu_order_uuid ) {
			$order = static::get_order_from_mondu_uuid( $mondu_order_uuid );
		}

		return $order;
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

	/**
	 * Log
	 *
	 * @param array $message
	 * @param string $level
	 */
	public static function log( array $message, $level = 'DEBUG' ) {
		$logger = wc_get_logger();
		$logger->log( $level, wc_print_r( $message, true ), [ 'source' => 'mondu' ] );
	}
}
