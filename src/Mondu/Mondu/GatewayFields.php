<?php

namespace Mondu\Mondu;

class GatewayFields {

	/**
	 * Returns the fields.
	 */
	public static function fields( $payment_method ) {
		return [
			'enabled' => [
				'title'   => __('Enable/Disable', 'woocommerce'),
				'type'    => 'checkbox',
				/* translators: %s: Payment Method */
				'label'   => sprintf(__('Enable %s payment method', 'mondu'), $payment_method),
				'default' => 'no',
			],
		];
	}
}
