<?php
/**
 * Gateway Settings Renderer
 *
 * Handles rendering of custom admin fields for gateway settings (title/description translations).
 *
 * @package Mondu
 */
namespace Mondu\Mondu;

use Mondu\Config\TitleLanguagesConfig;

defined( 'ABSPATH' ) || exit;

/**
 * GatewaySettingsRenderer
 */
class GatewaySettingsRenderer {

	/**
	 * Gateway instance (for accessing get_field_key, get_option, get_tooltip_html, get_description_html).
	 *
	 * @var \WC_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Generate repeatable "language + title" rows HTML.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field config.
	 * @return string
	 */
	public function generate_title_translations_html( $key, $data ) {
		$field_key = $this->gateway->get_field_key( $key );
		$value     = $this->gateway->get_option( $key, [] );
		$rows      = is_array( $value ) ? $value : [];
		$initial   = $this->normalize_rows_for_js( $rows, 'title' );
		return $this->generate_translations_table_html( $field_key, $data, $initial, 'title-translations', __( 'Title', 'mondu' ) );
	}

	/**
	 * Generate repeatable "language + description" rows HTML.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field config.
	 * @return string
	 */
	public function generate_description_translations_html( $key, $data ) {
		$field_key = $this->gateway->get_field_key( $key );
		$value     = $this->gateway->get_option( $key, [] );
		$rows      = is_array( $value ) ? $value : [];
		$initial   = $this->normalize_rows_for_js( $rows, 'description' );
		return $this->generate_translations_table_html( $field_key, $data, $initial, 'description-translations', __( 'Description', 'mondu' ) );
	}

	/**
	 * Normalize rows to JS format [ lang, text ] from stored format [ lang, $value_key ].
	 *
	 * @param array  $rows     Stored rows.
	 * @param string $value_key Key for the value (e.g. 'title', 'description').
	 * @return array
	 */
	private function normalize_rows_for_js( array $rows, $value_key ) {
		return array_map( function ( $r ) use ( $value_key ) {
			return [
				'lang' => isset( $r['lang'] ) ? $r['lang'] : '',
				'text' => isset( $r[ $value_key ] ) ? $r[ $value_key ] : '',
			];
		}, $rows );
	}

	/**
	 * Shared HTML for repeatable language + value (title or description) table.
	 *
	 * @param string $field_key       Form field name key.
	 * @param array  $data            Field config (title, tooltip, etc.).
	 * @param array  $initial_rows    Rows for data-initial: list of [ 'lang' => code, 'text' => value ].
	 * @param string $block_id_suffix Id suffix e.g. 'title-translations' or 'description-translations'.
	 * @param string $column_label    Second column header (e.g. 'Title' or 'Description').
	 * @return string
	 */
	private function generate_translations_table_html( $field_key, $data, array $initial_rows, $block_id_suffix, $column_label ) {
		$languages = TitleLanguagesConfig::get_languages();
		$lang_list = [];
		foreach ( $languages as $code => $label ) {
			$lang_list[] = [ 'code' => $code, 'label' => $label ];
		}
		$lang_json = wp_json_encode( $lang_list );
		$initial   = wp_json_encode( $initial_rows );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->gateway->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<div class="mondu-title-translations" id="mondu-<?php echo esc_attr( $block_id_suffix ); ?>-<?php echo esc_attr( $this->gateway->id ); ?>"
					data-field-key="<?php echo esc_attr( $field_key ); ?>"
					data-languages="<?php echo esc_attr( $lang_json ); ?>"
					data-initial="<?php echo esc_attr( $initial ); ?>">
					<table class="widefat wc_input_table" cellspacing="0">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Language', 'mondu' ); ?></th>
								<th><?php echo esc_html( $column_label ); ?></th>
								<th class="mondu-tt-remove">&nbsp;</th>
							</tr>
						</thead>
						<tbody class="mondu-tt-rows"></tbody>
						<tfoot>
							<tr>
								<th colspan="3">
									<button type="button" class="button mondu-tt-add"><?php esc_html_e( 'Add language', 'mondu' ); ?></button>
								</th>
							</tr>
						</tfoot>
					</table>
					<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" class="mondu-tt-input" value="" />
				</div>
				<?php echo $this->gateway->get_description_html( $data ); ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}
}
