<?php
/**
 * Security hardening for the headless setup.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Security
 *
 * Removes metadata leaks, optionally disables XML-RPC,
 * and tightens REST API access.
 */
class Security {

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		// Remove version from head and RSS.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		// Remove unnecessary head links.
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		if ( ! $this->settings->is_enabled( 'headlessbridge_xmlrpc_enabled' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', [ $this, 'remove_xmlrpc_methods' ] );
		}

		// Add security headers on all frontend responses.
		add_action( 'send_headers', [ $this, 'add_security_headers' ] );
	}

	/**
	 * Return empty methods array to fully disable XML-RPC.
	 *
	 * @param array $methods Existing XML-RPC methods.
	 * @return array
	 */
	public function remove_xmlrpc_methods( array $methods ): array {
		return [];
	}

	/**
	 * Emit security response headers.
	 */
	public function add_security_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}
}
