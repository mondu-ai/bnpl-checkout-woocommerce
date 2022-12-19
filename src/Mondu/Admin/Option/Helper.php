<?php

namespace Mondu\Admin\Option;

use Mondu\Plugin;

defined('ABSPATH') or die('Direct access not allowed');

abstract class Helper {
  /**
   * @var array|bool|mixed|void
   */
  protected $global_settings;

  public function __construct() {
    $this->global_settings = get_option(Plugin::OPTION_NAME);
  }

  protected function textField($option_name, $field_name, $default = '') {
    printf(
      '<input type="text" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="%s" />',
      isset($this->global_settings[$field_name]) ? esc_attr($this->global_settings[$field_name]) : $default
   );
  }

  protected function selectField($option_name, $field_name, $options, $type = 'single') {
    $selected_value = isset($this->global_settings[$field_name]) ? $this->global_settings[$field_name] : '';

    $multiple = '';
    $name = $option_name . '[' . $field_name . ']';
    if ($type === 'multiple') {
      $multiple = ' multiple="multiple"';
      $name     .= '[]';
    }

    echo '<select id="' . $field_name . '" name="' . $name . '"' . $multiple . '>';
    foreach ($options as $value => $label) {
      $selected = false;
      if (is_array($selected_value) && $type === 'multiple') {
        if (in_array($value, $selected_value, true)) {
          $selected = true;
        }
      } elseif ($selected_value === $value) {
        $selected = true;
      }

      if ($selected) {
        $selected = ' selected="selected"';
      }
      echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
  }
}
