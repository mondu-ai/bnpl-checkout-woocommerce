<?php
/**
 * Plugin functions file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mondu_locale() {
  /**
   * WPML current language
   *
   * @since 1.3.2
   */
  return apply_filters( 'wpml_current_language', get_locale() );
}

/**
 * Get language
 *
 * @return string
 */
function get_language() {
  /**
   * Locale for the order creation
   *
   * @since 2.0.0
   */
  $language = apply_filters( 'mondu_order_locale', get_locale() );
  return substr( $language, 0, 2 );
}

/**
 * Is Production
 *
 * @return bool
 */
function is_production() {
  if ( is_array( Mondu_WC()->global_settings )
    && isset( Mondu_WC()->global_settings['sandbox_or_production'] )
    && 'production' === Mondu_WC()->global_settings['sandbox_or_production']
  ) {
    return true;
  }
  return false;
}

/**
 * Not Null or Empty
 *
 * @param $value
 * @return bool
 */
function not_null_or_empty( $value ) {
  return null !== $value && '' !== $value;
}
