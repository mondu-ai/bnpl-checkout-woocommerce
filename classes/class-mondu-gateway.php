<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Payment_Gateway' ) ) {
	/**
	 * MonduGateway class.
	 *
	 * @extends WC_Payment_Gateway
	 */
	class MonduGateway extends WC_Payment_Gateway {

        /**
         * @var string
         */
        private $instructions;

        /**
         * @var string
         */
        public $enabled;

		public function __construct($register_hooks = true) {
			$this->init_form_fields();
			$this->init_settings();

			$this->enabled = $this->is_enabled();

            if ($register_hooks) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
                add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
                add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);
            }
        }

        /**
         * Initialise Gateway Settings Form Fields
		 */
		// TODO: test if we can remove this method and the fields = true on the gateway
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => /* translators: %s: Payment Method */ sprintf( __( 'Enable %s payment method', 'mondu' ), $this->title ),
					'default' => 'no',
				),
			);
		}

		/**
		 * Add gateway classes to the list of available payment methods
		 *
		 * @param array $methods
		 * @return array
		 */
		public static function add( array $methods ) {
			array_unshift(
                $methods,
                GatewayInvoice::class,
                GatewayDirectDebit::class,
                GatewayInstallment::class,
                GatewayInstallmentByInvoice::class
            );

			return $methods;
		}

		/**
		 * Include payment fields on order pay page
		 *
		 * @return void
		 */
		public function payment_fields() {
			parent::payment_fields();
			include MONDU_VIEW_PATH . '/checkout/payment-form.php';
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
            if ( $this->description ) {
                echo wp_kses_post(wpautop(wptexturize($this->description)));
            }
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @param WC_Order $order
		 */
		public function email_instructions( $order ) {
			if ( ! order_has_mondu( $order ) ) {
				return;
			}

            if ( $this->description && $this->id === $order->get_payment_method() ) {
                echo wp_kses_post(wpautop(wptexturize($this->description)));
            }
		}

		/**
		 * Get gateway icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			$icon_html = '<img src="https://checkout.mondu.ai/logo.svg" alt="' . $this->method_title . '" width="100" />';

			/**
			 * Mondu payment icon
			 *
			 * @since 1.3.2
			 */
			return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
		}

		/**
		 * Process payment
		 *
		 * @param $order_id
		 * @return array|void
		 * @throws ResponseException
		 */
		public function process_payment( $order_id ) {
			$order       = wc_get_order( $order_id );
			$success_url = $this->get_return_url( $order );
			$mondu_order = Mondu_WC()->mondu_request_wrapper->create_order( $order, $success_url );

			if ( ! $mondu_order ) {
				wc_add_notice( __( 'Error placing an order. Please try again.', 'mondu' ), 'error' );
				return;
			}

			return array(
				'result'   => 'success',
				'redirect' => $mondu_order['hosted_checkout_url'],
			);
		}

		/**
		 * Check if Mondu has its credentials validated.
		 *
		 * @return string
		 */
		private function is_enabled() {
			if ( null === get_option( '_mondu_credentials_validated' ) ) {
				$this->settings['enabled'] = 'no';
			}

			return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
		}
	}
}
