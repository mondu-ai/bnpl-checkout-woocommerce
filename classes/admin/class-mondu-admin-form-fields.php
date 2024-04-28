<?php

class FormFields {
	public function sandbox_or_production_field() {
		return array(
			'sandbox_or_production',
			__( 'Sandbox or production', 'mondu' ),
			array( $this, 'sandbox_or_production_field_callback' ),
			'mondu-settings-account',
			'mondu_account_settings_general',
			array(
				'label_for' => 'sandbox_or_production',
				'tip'       => __( 'Mondu\'s environment to use.', 'mondu' ),
			)
		);
	}

	public function sandbox_or_production_field_callback( $args = array() ) {
		$this->selectField(
			'sandbox_or_production',
			array(
				'sandbox'    => __( 'Sandbox', 'mondu' ),
				'production' => __( 'Production', 'mondu' ),
			),
			$args['tip']
		);
	}

	public function api_token_field() {
		return array(
			'api_token',
			__( 'API Token', 'mondu' ),
			array( $this, 'api_token_field_callback' ),
			'mondu-settings-account',
			'mondu_account_settings_general',
			array(
				'label_for' => 'api_token',
				'tip'       => __( 'API Token provided by Mondu.', 'mondu' ),
			)
		);
	}

	public function api_token_field_callback( $args = array() ) {
		$this->textField( 'api_token', $args['tip'] );
	}

	public function send_line_items_field() {
		return array(
			'send_line_items',
			__( 'Send line items', 'mondu' ),
			array( $this, 'send_line_items_field_callback' ),
			'mondu-settings-account',
			'mondu_account_settings_general',
			array(
				'label_for' => 'send_line_items',
				'tip'       => __( 'Send the line items when creating order and invoice.', 'mondu' ),
			)
		);
	}

	public function send_line_items_field_callback( $args = array() ) {
		$this->selectField(
			'send_line_items',
			array(
				'yes'   => __( 'Yes', 'mondu' ),
				'order' => __( 'Send line items only for orders', 'mondu' ),
				'no'    => __( 'No', 'mondu' ),
			),
			$args['tip']
		);
	}

	private function textField( $field_name, $tip = '' ) {
		$field_id    = $field_name;
		$field_value = isset( Mondu_WC()->global_settings[ $field_name ] ) ? Mondu_WC()->global_settings[ $field_name ] : '';
		$field_name  = OPTION_NAME . '[' . $field_name . ']';

		?>
		<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $tip ); ?>"></span>
		<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $field_value ); ?>" />
		<?php
	}

	public function selectField( $field_name, $options, $tip ) {
		$field_id    = $field_name;
		$field_value = isset( Mondu_WC()->global_settings[ $field_name ] ) ? Mondu_WC()->global_settings[ $field_name ] : '';
		$field_name  = OPTION_NAME . '[' . $field_name . ']';

		?>
		<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $tip ); ?>"></span>
		<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
		<?php
		foreach ( $options as $value => $label ) {
			?>
			<?php $selected = $field_value === $value ? 'selected' : ''; ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php echo esc_attr( $selected ); ?>><?php echo esc_attr( $label ); ?></option>
			<?php
		}
		?>
		</select>
		<?php
	}
}
