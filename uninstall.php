<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options from the database.
 *
 * @package HeadlessBridge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// This script runs standalone (WordPress requires it, it doesn't include
// it into another scope), so its top-level variables aren't real globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$option_keys = [
	'headlessbridge_enabled',
	'headlessbridge_frontend_url',
	'headlessbridge_noindex',
	'headlessbridge_preserve_slugs',
	'headlessbridge_post_path_prefix',
	'headlessbridge_disable_rss',
	'headlessbridge_disable_search',
	'headlessbridge_disable_comments',
	'headlessbridge_disable_author_archives',
	'headlessbridge_disable_date_archives',
	'headlessbridge_allowed_origins',
	'headlessbridge_maintenance_mode',
	'headlessbridge_xmlrpc_enabled',
	'headlessbridge_robots_txt',
	'headlessbridge_image_strategy',
	'headlessbridge_webhooks',
];

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

delete_transient( 'headlessbridge_health_cache' );
