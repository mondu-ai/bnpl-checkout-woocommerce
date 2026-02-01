<?php
/**
 * Languages available for gateway title/description translations.
 *
 * @package Mondu
 */
namespace Mondu\Config;

defined( 'ABSPATH' ) || exit;

/**
 * TitleLanguagesConfig
 */
class TitleLanguagesConfig {

	/**
	 * Language code => label. Used in admin for "Titles by language" and "Descriptions by language".
	 * Extend by adding entries or via filter 'mondu_title_translations_languages'.
	 *
	 * @var array<string, string>
	 */
	private static $languages = [
		'en' => 'English',
		'de' => 'Deutsch',
		'fr' => 'Français',
		'nl' => 'Nederlands',
		'uk' => 'Українська',
		'pl' => 'Polski',
		'es' => 'Español',
		'it' => 'Italiano',
		'pt' => 'Português',
		'cs' => 'Čeština',
		'sk' => 'Slovenčina',
		'hu' => 'Magyar',
		'ro' => 'Română',
		'bg' => 'Български',
		'hr' => 'Hrvatski',
		'sl' => 'Slovenščina',
		'et' => 'Eesti',
		'lv' => 'Latviešu',
		'lt' => 'Lietuvių',
	];

	/**
	 * Languages for title/description translations (code => label). Filterable.
	 *
	 * @return array<string, string>
	 */
	public static function get_languages() {
		return apply_filters( 'mondu_title_translations_languages', self::$languages );
	}
}
