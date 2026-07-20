<?php
/**
 * Fired during plugin deactivation.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

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
