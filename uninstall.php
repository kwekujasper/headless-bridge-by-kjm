<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options from the database.
 *
 * @package KJMHCG
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// This script runs standalone (WordPress requires it, it doesn't include
// it into another scope), so its top-level variables aren't real globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$option_keys = [
	'kjmhcg_enabled',
	'kjmhcg_frontend_url',
	'kjmhcg_noindex',
	'kjmhcg_preserve_slugs',
	'kjmhcg_post_path_prefix',
	'kjmhcg_disable_rss',
	'kjmhcg_disable_search',
	'kjmhcg_disable_comments',
	'kjmhcg_disable_author_archives',
	'kjmhcg_disable_date_archives',
	'kjmhcg_allowed_origins',
	'kjmhcg_maintenance_mode',
	'kjmhcg_xmlrpc_enabled',
	'kjmhcg_robots_txt',
	'kjmhcg_image_strategy',
	'kjmhcg_home_category',
	'kjmhcg_menu_items',
	'kjmhcg_homepage_sections',
	'kjmhcg_webhooks',
];

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

// Also remove options left behind under the plugin's two older prefixes
// (HeadlessWP → "headlesswp_", Headless Bridge → "headlessbridge_"), since the
// rename migration copies rather than deletes them. Keys share the same
// suffixes as the current "kjmhcg_" options.
foreach ( [ 'headlesswp_', 'headlessbridge_' ] as $legacy_prefix ) {
	foreach ( $option_keys as $key ) {
		delete_option( $legacy_prefix . substr( $key, strlen( 'kjmhcg_' ) ) );
	}
	delete_transient( $legacy_prefix . 'health_cache' );
}

delete_transient( 'kjmhcg_health_cache' );
