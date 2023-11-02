<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MonduCheckout' ) ) {
	class MonduCheckout {
		const AVAILABLE_COUNTRIES = array( 'DE', 'AT', 'NL', 'FR', 'BE', 'GB' );

		public function __construct() {
			/*
			*	remove the payment methods if not supported in the country
			*/
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_mondu_if_unsupported_country' ) );

			/*
			* Validates required fields
			*/
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_required_fields' ), 10, 2 );
		}

		public function remove_mondu_if_unsupported_country( $available_gateways ) {
			if ( is_admin() || !is_checkout() ) {
				return $available_gateways;
			}

			$mondu_payments = Mondu_WC()->mondu_request_wrapper->get_merchant_payment_methods();

			foreach ( PAYMENT_METHODS as $payment_method => $woo_payment_method ) {
				$customer = $this->get_wc_customer();
				if ( ! $this->is_country_available($customer->get_billing_country())
					|| ! in_array($payment_method, $mondu_payments, true)
				) {
					if ( isset($available_gateways[ PAYMENT_METHODS[ $payment_method ] ]) ) {
						unset($available_gateways[ PAYMENT_METHODS[ $payment_method ] ]);
					}
				}
			}

			return $available_gateways;
		}

		/**
		 * Validate Required fields
		 *
		 * @param array    $fields
		 * @param WP_Error $errors
		 */
		public function validate_required_fields( array $fields, WP_Error $errors ) {
			if ( ! in_array( $fields['payment_method'], PAYMENT_METHODS, true ) ) {
				return;
			}

			if ( ! not_null_or_empty( $fields['billing_company'] ) && ! not_null_or_empty( $fields['shipping_company'] ) ) {
				/* translators: %s: Company */
				$errors->add( 'validation', sprintf( __( '%s is a required field for Mondu payments.', 'mondu' ), '<strong>' . __( 'Company', 'mondu' ) . '</strong>' ) );
			}

			if ( ! $this->is_country_available( $fields['billing_country'] ) ) {
				/* translators: %s: Billing country */
				$errors->add( 'validation', sprintf( __( '%s not available for Mondu Payments.', 'mondu' ), '<strong>' . __( 'Billing country', 'mondu' ) . '</strong>' ) );
			}
		}

		private function is_country_available( $country ) {
			return in_array( $country, self::AVAILABLE_COUNTRIES, true );
		}

		private function get_wc_customer() {
  		return isset( WC()->customer ) ? WC()->customer : new WC_Customer( get_current_user_id() );
		}
	}
}

new MonduCheckout();
