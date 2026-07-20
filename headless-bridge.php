<?php
/**
 * Plugin Name:       Headless Bridge by KJM
 * Plugin URI:        https://kwekujasper.com/headless-bridge
 * Description:       Transform WordPress into a secure, configurable headless CMS for any modern frontend framework (Next.js, Nuxt, Astro, SvelteKit, and more).
 * Version:           1.2.4
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Kweku Jasper Media
 * Author URI:        https://kwekujasper.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       headless-bridge-by-kjm
 * Domain Path:       /languages
 *
 * @package HeadlessBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HEADLESSBRIDGE_VERSION', '1.2.4' );
define( 'HEADLESSBRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HEADLESSBRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HEADLESSBRIDGE_PLUGIN_FILE', __FILE__ );
define( 'HEADLESSBRIDGE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for Headless Bridge classes.
 */
spl_autoload_register( function ( string $class ) {
	$prefix    = 'HeadlessBridge\\';
	$base_dir  = HEADLESSBRIDGE_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative = substr( $class, $len );
	$file     = $base_dir . 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function () {
	require_once HEADLESSBRIDGE_PLUGIN_DIR . 'includes/class-activator.php';
	HeadlessBridge\Activator::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
	require_once HEADLESSBRIDGE_PLUGIN_DIR . 'includes/class-deactivator.php';
	HeadlessBridge\Deactivator::deactivate();
} );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', function () {
	require_once HEADLESSBRIDGE_PLUGIN_DIR . 'includes/class-plugin.php';
	HeadlessBridge\Plugin::get_instance()->run();
} );
