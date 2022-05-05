<?php

namespace Mondu\Mondu;

class Helper {
  /**
   * @param $value
   *
   * @return boolean
   */
  public static function null_or_empty( $value ) {
    return $value === NULL || $value === '';
  }

  /**
   * @param $array
   * @param $keys
   *
   * @return array[]
   */
  public static function remove_keys( $array, $keys ) {
    return array_filter(
      $array,
      fn ($key) => !in_array($key, $keys),
      ARRAY_FILTER_USE_KEY,
    );
  }
}
