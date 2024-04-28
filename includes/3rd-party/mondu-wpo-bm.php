<?php
/**
 * Functions for the 3rd party plugin BM.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BM' ) && ! class_exists( 'MonduBM' ) ) {
	class MonduBM {
		public function __construct() {
			add_filter( 'bm_filter_price', '__return_false' );
		}
	}
	new MonduBM();
}
