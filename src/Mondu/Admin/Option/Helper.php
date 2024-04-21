<?php
/**
 * Account options
 *
 * @package Mondu
 */

namespace Mondu\Admin\Option;

use Mondu\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access not allowed' );
}

/**
 * Class Account
 */
abstract class Helper {
	/**
	 * The global settings.
	 *
	 * @var false|mixed|null
	 */
	protected $global_settings;

	/**
	 * Account constructor.
	 */
	public function __construct() {
		$this->global_settings = get_option( Plugin::OPTION_NAME );
	}

	/**
	 * Register the settings
	 *
	 * @param string $option_name The option name.
	 * @param string $field_name The field name.
	 * @param string $tip The tip.
	 */
	protected function text_field( $option_name, $field_name, $tip = '' ) {
		$field_id    = $field_name;
		$field_value = $this->global_settings[ $field_name ] ?? '';
		$field_name  = $option_name . '[' . $field_name . ']';

		?>
		<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $tip ); ?>"></span>
		<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $field_value ); ?>" />
		<?php
	}

	/**
	 * Register the settings
	 *
	 * @param string $option_name The option name.
	 * @param string $field_name The field name.
	 * @param string $options The options.
	 * @param string $tip The tip.
	 */
	protected function select_field( $option_name, $field_name, $options, $tip ) {
		$field_id    = $field_name;
		$field_value = $this->global_settings[ $field_name ] ?? '';
		$field_name  = $option_name . '[' . $field_name . ']';

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
