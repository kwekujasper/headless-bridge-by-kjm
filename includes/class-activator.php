<?php
/**
 * Fired during plugin activation.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Sets default options on first activation.
 */
class Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate(): void {
		self::migrate_legacy_options();
		self::set_defaults();
		flush_rewrite_rules();
	}

	/**
	 * One-time migration for sites that previously ran this plugin under its
	 * old name/prefix ("headlesswp_*", before the rename to Headless Bridge).
	 * Copies each legacy option to its new key only if the new key has no
	 * value yet — never overwrites, and never deletes the legacy option, so
	 * a config already set up under the old name survives the rename.
	 */
	private static function migrate_legacy_options(): void {
		$legacy_keys = [
			'headlesswp_enabled', 'headlesswp_frontend_url', 'headlesswp_noindex',
			'headlesswp_preserve_slugs', 'headlesswp_post_path_prefix', 'headlesswp_disable_rss',
			'headlesswp_disable_search', 'headlesswp_disable_comments',
			'headlesswp_disable_author_archives', 'headlesswp_disable_date_archives',
			'headlesswp_allowed_origins', 'headlesswp_maintenance_mode',
			'headlesswp_xmlrpc_enabled', 'headlesswp_robots_txt', 'headlesswp_webhooks',
			'headlesswp_image_strategy',
		];

		foreach ( $legacy_keys as $legacy_key ) {
			$legacy_value = get_option( $legacy_key, null );
			if ( null === $legacy_value ) {
				continue;
			}

			$new_key = 'headlessbridge_' . substr( $legacy_key, strlen( 'headlesswp_' ) );
			if ( get_option( $new_key ) === false ) {
				add_option( $new_key, $legacy_value );
			}
		}
	}

	/**
	 * Set default plugin options if they don't already exist.
	 */
	private static function set_defaults(): void {
		$defaults = [
			'headlessbridge_enabled'                 => '0',
			'headlessbridge_frontend_url'            => '',
			'headlessbridge_noindex'                 => '0',
			'headlessbridge_preserve_slugs'          => '1',
			'headlessbridge_post_path_prefix'        => '',
			'headlessbridge_disable_rss'             => '0',
			'headlessbridge_disable_search'          => '0',
			'headlessbridge_disable_comments'        => '0',
			'headlessbridge_disable_author_archives' => '0',
			'headlessbridge_disable_date_archives'   => '0',
			'headlessbridge_allowed_origins'         => '',
			'headlessbridge_maintenance_mode'        => '0',
			'headlessbridge_xmlrpc_enabled'          => '1',
			'headlessbridge_robots_txt'              => '0',
			'headlessbridge_image_strategy'          => 'native',
			'headlessbridge_webhooks'                => [],
		];

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}
	}
}
