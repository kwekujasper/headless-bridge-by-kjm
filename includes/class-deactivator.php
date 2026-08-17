<?php
/**
 * Fired during plugin deactivation.
 *
 * @package KJMHCG
 */

namespace KJMHCG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
