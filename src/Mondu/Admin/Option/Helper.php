<?php
/**
 * Helper class for options.
 *
 * @package Mondu
 */
namespace Mondu\Admin\Option;

use Mondu\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	die( 'Direct access not allowed' );
}

/**
 * Class Helper
 *
 * @package Mondu
 */
abstract class Helper {
    /**
     * The global settings.
     *
     * @var false|mixed|null
     */
    protected $global_settings;

    /**
     * Helper constructor.
     */
	public function __construct() {
		$this->global_settings = get_option( Plugin::OPTION_NAME );
	}

    /**
     * Text field rendering.
     *
     * @param $option_name
     * @param $field_name
     * @param $tip
     * @return void
     */
	protected function textField( $option_name, $field_name, $tip = '' ) {
		$field_id    = $field_name;
		$field_value = isset( $this->global_settings[ $field_name ] ) ? $this->global_settings[ $field_name ] : '';
		$field_name  = $option_name . '[' . $field_name . ']';

		?>
		<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $tip ); ?>"></span>
		<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $field_value ); ?>" />
		<?php
	}

    /**
     * Select field rendering.
     *
     * @param $option_name
     * @param $field_name
     * @param $options
     * @param $tip
     * @return void
     */
	protected function selectField( $option_name, $field_name, $options, $tip ) {
		$field_id    = $field_name;
		$field_value = isset( $this->global_settings[ $field_name ] ) ? $this->global_settings[ $field_name ] : '';
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
