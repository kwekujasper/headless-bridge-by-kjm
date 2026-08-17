<?php
/**
 * Fired during plugin activation.
 *
 * @package KJMHCG
 */

namespace KJMHCG;

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
	 * One-time migration for sites that previously ran this plugin under an
	 * older name/prefix, before the rename to KJM Headless CMS Gateway:
	 *   - "headlesswp_*"     (the original "HeadlessWP by KJM")
	 *   - "headlessbridge_*" (the interim "Headless Bridge by KJM", 1.2–1.3)
	 * Copies each legacy option to its new "kjmhcg_" key only if that key has
	 * no value yet — never overwrites, and never deletes the legacy option, so
	 * a config already set up under an old name survives the rename. When both
	 * legacy prefixes hold a value, the newer "headlessbridge_" one wins.
	 */
	private static function migrate_legacy_options(): void {
		$suffixes = [
			'enabled', 'frontend_url', 'noindex', 'preserve_slugs', 'post_path_prefix',
			'disable_rss', 'disable_search', 'disable_comments', 'disable_author_archives',
			'disable_date_archives', 'allowed_origins', 'maintenance_mode', 'xmlrpc_enabled',
			'robots_txt', 'image_strategy', 'home_category', 'menu_items', 'homepage_sections',
			'webhooks',
		];

		// Ordered oldest → newest; the last non-null value found wins.
		$legacy_prefixes = [ 'headlesswp_', 'headlessbridge_' ];

		foreach ( $suffixes as $suffix ) {
			$new_key = 'kjmhcg_' . $suffix;
			if ( get_option( $new_key ) !== false ) {
				continue; // Already set under the new prefix — leave it alone.
			}

			$value = null;
			foreach ( $legacy_prefixes as $prefix ) {
				$legacy_value = get_option( $prefix . $suffix, null );
				if ( null !== $legacy_value ) {
					$value = $legacy_value;
				}
			}

			if ( null !== $value ) {
				add_option( $new_key, $value );
			}
		}
	}

	/**
	 * Set default plugin options if they don't already exist.
	 */
	private static function set_defaults(): void {
		$defaults = [
			'kjmhcg_enabled'                 => '0',
			'kjmhcg_frontend_url'            => '',
			'kjmhcg_noindex'                 => '0',
			'kjmhcg_preserve_slugs'          => '1',
			'kjmhcg_post_path_prefix'        => '',
			'kjmhcg_disable_rss'             => '0',
			'kjmhcg_disable_search'          => '0',
			'kjmhcg_disable_comments'        => '0',
			'kjmhcg_disable_author_archives' => '0',
			'kjmhcg_disable_date_archives'   => '0',
			'kjmhcg_allowed_origins'         => '',
			'kjmhcg_maintenance_mode'        => '0',
			'kjmhcg_xmlrpc_enabled'          => '1',
			'kjmhcg_robots_txt'              => '0',
			'kjmhcg_image_strategy'          => 'native',
			'kjmhcg_home_category'           => '',
			'kjmhcg_menu_items'              => '',
			'kjmhcg_homepage_sections'       => '',
			'kjmhcg_webhooks'                => [],
		];

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}
	}
}
