<?php

namespace Mondu\Mondu\Support;

use Mondu\Mondu\Support\Helper;
use Mondu\Plugin;
use WC_Order;
use WC_Order_Refund;

class OrderData {
	/**
	 * Create Order
	 *
	 * @param WC_Order $order
	 * @param $success_url
	 * @return array
	 */
	public static function create_order( WC_Order $order, $success_url ) {
		$data = self::order_data_from_wc_order( $order );

		if ( is_wc_endpoint_url('order-pay') ) {
			$non_successful_url = $order->get_checkout_payment_url();
		} else {
			$non_successful_url = wc_get_checkout_url();
		}

		$data['success_url']  = $success_url;
		$data['cancel_url']   = $non_successful_url;
		$data['declined_url'] = $non_successful_url;
		$data['state_flow']   = 'authorization_flow';
		$data['language']     = Helper::get_language();

		return $data;
	}

	/**
	 * Invoice Data from WC_Order
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public static function invoice_data_from_wc_order( WC_Order $order ) {
		$invoice_data = [
			'external_reference_id' => (string) $order->get_order_number(),
			'invoice_url'           => Helper::create_invoice_url($order),
			'gross_amount_cents'    => round( (float) $order->get_total() * 100),
			'tax_cents'             => round( (float) ( $order->get_total_tax() - $order->get_shipping_tax() ) * 100), # Considering that is not possible to save taxes that does not belongs to products, removes shipping taxes here
			'discount_cents'        => round( (float) $order->get_discount_total() * 100),
			'shipping_price_cents'  => round( (float) ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100), # Considering that is not possible to save taxes that does not belongs to products, sum shipping taxes here
		];

		if ( $order->get_shipping_method() ) {
			$invoice_data['shipping_info']['shipping_method'] = $order->get_shipping_method();
		}

		if ( count( $order->get_items() ) > 0 ) {
			$invoice_data['line_items'] = [];

			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				$line_item = [
					'external_reference_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
					'quantity'              => $item->get_quantity(),
				];

				$invoice_data['line_items'][] = $line_item;
			}
		}

		return $invoice_data;
	}

	/**
	 * Create Credit note
	 *
	 * @param WC_Order_Refund $refund
	 * @return array
	 */
	public static function create_credit_note( WC_Order_Refund $refund ) {
		$credit_note = [
			'gross_amount_cents'    => abs(round( (float) $refund->get_total() * 100)),
			'tax_cents'             => abs(round( (float) $refund->get_total_tax() * 100)),
			'external_reference_id' => (string) $refund->get_id(),
		];

		if ( $refund->get_reason() ) {
			$credit_note['notes'] = $refund->get_reason();
		}

		if ( count( $refund->get_items() ) > 0 ) {
			$credit_note['line_items'] = [];

			foreach ( $refund->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				$line_item = [
					'external_reference_id' => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
					'quantity'              => abs($item->get_quantity()), # The quantity will be negative
				];

				$credit_note['line_items'][] = $line_item;
			}
		}

		return $credit_note;
	}

	/**
	 * Order Data from WC_Order
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public static function order_data_from_wc_order( WC_Order $order ) {
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();
		$billing_email      = $order->get_billing_email();
		$billing_phone      = $order->get_billing_phone();
		$customer_id        = $order->get_customer_id();

		$billing_address_line1 = $order->get_billing_address_1();
		$billing_address_line2 = $order->get_billing_address_2();
		$billing_city          = $order->get_billing_city();
		$billing_state         = $order->get_billing_state();
		$billing_zip_code      = $order->get_billing_postcode();
		$billing_country_code  = $order->get_billing_country();

		$order_data = [
			'payment_method'        => array_flip( Plugin::PAYMENT_METHODS )[ $order->get_payment_method() ],
			'currency'              => get_woocommerce_currency(),
			'external_reference_id' => (string) $order->get_order_number(),
			'gross_amount_cents'    => round( (float) $order->get_total() * 100),
			'net_price_cents'       => round( (float) $order->get_subtotal() * 100),
			'tax_cents'             => round( (float) $order->get_total_tax() * 100),
			'buyer'                 => [
				'first_name'            => isset($billing_first_name) && Helper::not_null_or_empty($billing_first_name) ? $billing_first_name : null,
				'last_name'             => isset($billing_last_name) && Helper::not_null_or_empty($billing_last_name) ? $billing_last_name : null,
				'company_name'          => self::get_company_name_from_wc_order( $order ),
				'email'                 => isset($billing_email) && Helper::not_null_or_empty($billing_email) ? $billing_email : null,
				'phone'                 => isset($billing_phone) && Helper::not_null_or_empty($billing_phone) ? $billing_phone : null,
				'external_reference_id' => isset($customer_id) && Helper::not_null_or_empty($customer_id) ? (string) $customer_id : null,
				'is_registered'         => is_user_logged_in(),
			],
			'billing_address'       => [
				'address_line1' => isset($billing_address_line1) && Helper::not_null_or_empty($billing_address_line1) ? $billing_address_line1 : null,
				'address_line2' => isset($billing_address_line2) && Helper::not_null_or_empty($billing_address_line2) ? $billing_address_line2 : null,
				'city'          => isset($billing_city) && Helper::not_null_or_empty($billing_city) ? $billing_city : null,
				'state'         => isset($billing_state) && Helper::not_null_or_empty($billing_state) ? $billing_state : null,
				'zip_code'      => isset($billing_zip_code) && Helper::not_null_or_empty($billing_zip_code) ? $billing_zip_code : null,
				'country_code'  => isset($billing_country_code) && Helper::not_null_or_empty($billing_country_code) ? $billing_country_code : null,
			],
			'shipping_address'      => self::get_shipping_address_from_order( $order ),
			'lines'                 => self::get_lines_from_order( $order ),
		];

		return $order_data;
	}

	/**
	 * Order Data from WC_Order with Amount
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public static function order_data_from_wc_order_with_amount( WC_Order $order ) {
		$data           = self::order_data_from_wc_order( $order );
		$data['amount'] = self::get_amount_from_wc_order( $order );

		return $data;
	}

	/**
	 * Get shipping address from order
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private static function get_shipping_address_from_order( WC_Order $order ) {
		$shipping_data = [];

		$shipping_address_line1 = $order->get_shipping_address_1();
		$shipping_address_line2 = $order->get_shipping_address_2();
		$shipping_city          = $order->get_shipping_city();
		$shipping_state         = $order->get_shipping_state();
		$shipping_zip_code      = $order->get_shipping_postcode();
		$shipping_country_code  = $order->get_shipping_country();

		$billing_address_line1 = $order->get_billing_address_1();
		$billing_address_line2 = $order->get_billing_address_2();
		$billing_city          = $order->get_billing_city();
		$billing_state         = $order->get_billing_state();
		$billing_zip_code      = $order->get_billing_postcode();
		$billing_country_code  = $order->get_billing_country();

		if ( $order->needs_shipping_address() ) {
			$shipping_data = [
				'address_line1' => isset($shipping_address_line1) && Helper::not_null_or_empty($shipping_address_line1) ? $shipping_address_line1 : null,
				'address_line2' => isset($shipping_address_line2) && Helper::not_null_or_empty($shipping_address_line2) ? $shipping_address_line2 : null,
				'city'          => isset($shipping_city) && Helper::not_null_or_empty($shipping_city) ? $shipping_city : null,
				'state'         => isset($shipping_state) && Helper::not_null_or_empty($shipping_state) ? $shipping_state : null,
				'zip_code'      => isset($shipping_zip_code) && Helper::not_null_or_empty($shipping_zip_code) ? $shipping_zip_code : null,
				'country_code'  => isset($shipping_country_code) && Helper::not_null_or_empty($shipping_country_code) ? $shipping_country_code : null,
			];
		} else {
			$shipping_data = [
				'address_line1' => isset($billing_address_line1) && Helper::not_null_or_empty($billing_address_line1) ? $billing_address_line1 : null,
				'address_line2' => isset($billing_address_line2) && Helper::not_null_or_empty($billing_address_line2) ? $billing_address_line2 : null,
				'city'          => isset($billing_city) && Helper::not_null_or_empty($billing_city) ? $billing_city : null,
				'state'         => isset($billing_state) && Helper::not_null_or_empty($billing_state) ? $billing_state : null,
				'zip_code'      => isset($billing_zip_code) && Helper::not_null_or_empty($billing_zip_code) ? $billing_zip_code : null,
				'country_code'  => isset($billing_country_code) && Helper::not_null_or_empty($billing_country_code) ? $billing_country_code : null,
			];
		}

		return $shipping_data;
	}

	/**
	 * Get lines from order
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private static function get_lines_from_order( WC_Order $order ) {
		$line = [
			'discount_cents'       => round($order->get_discount_total() * 100),
			'shipping_price_cents' => round( (float) ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100), # Considering that is not possible to save taxes that does not belongs to products, sums shipping taxes here
			'line_items'           => [],
		];

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			$line_item = [
				'title'                    => $product->get_title(),
				'quantity'                 => $item->get_quantity(),
				'external_reference_id'    => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
				'product_id'               => Helper::not_null_or_empty($product->get_id()) ? (string) $product->get_id() : null,
				'product_sku'              => Helper::not_null_or_empty($product->get_slug()) ? (string) $product->get_slug() : null,
				'net_price_per_item_cents' => round( (float) ( $item->get_subtotal() / $item->get_quantity() ) * 100),
				'net_price_cents'          => round( (float) $item->get_subtotal() * 100),
				'tax_cents'                => round( (float) $item->get_total_tax() * 100),
				'item_type'                => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
			];

			$line['line_items'][] = $line_item;
		}

		return [ $line ];
	}

	/**
	 * Get amount from WC_Order
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public static function get_amount_from_wc_order( WC_Order $order ) {
		$net_price_cents = 0;
		$tax_cents       = 0;

		foreach ( $order->get_items() as $item_id => $item ) {
			$net_price_cents += (float) $item->get_subtotal() * 100;
			$tax_cents       += (float) $item->get_total_tax() * 100;
		}

		$amount = [
			'gross_amount_cents' => round( (float) $order->get_total() * 100),
			'net_price_cents'    => round($net_price_cents),
			'tax_cents'          => round($tax_cents),
		];

		return $amount;
	}

	/**
	 * Get company name from WC_Order
	 *
	 * @param WC_Order $order
	 * @return string|null
	 */
	public static function get_company_name_from_wc_order( WC_Order $order ) {
		$billing_company_name = $order->get_billing_company();
		$shipping_company_name = $order->get_shipping_company();

		if ( isset( $billing_company_name ) && Helper::not_null_or_empty( $billing_company_name ) ) {
			return $billing_company_name;
		} else if ( isset( $shipping_company_name ) && Helper::not_null_or_empty( $shipping_company_name ) ) {
			return $shipping_company_name;
		} else {
			return null;
		}
	}
}
