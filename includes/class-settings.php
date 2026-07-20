<?php
/**
 * Settings accessor — thin wrapper around WordPress Options API.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/** Allowed values for the headlessbridge_image_strategy option. */
	private const IMAGE_STRATEGIES = [ 'native', 'sharp', 'proxy', 'unoptimized' ];

	/**
	 * Get a typed option value with a fallback default.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Fallback when option is not set.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = '' ): mixed {
		return get_option( $key, $default );
	}

	/**
	 * Convenience: return true when an option is the string '1'.
	 */
	public function is_enabled( string $key ): bool {
		return '1' === (string) $this->get( $key, '0' );
	}

	/**
	 * Return the sanitized frontend URL, or empty string.
	 */
	public function frontend_url(): string {
		return (string) $this->get( 'headlessbridge_frontend_url', '' );
	}

	/**
	 * Return the configured path prefix for single-post redirects
	 * (e.g. "post" so a post redirects to /post/my-slug/), with slashes
	 * trimmed. Empty string means posts redirect to the frontend root:
	 * /my-slug/.
	 */
	public function post_path_prefix(): string {
		return trim( (string) $this->get( 'headlessbridge_post_path_prefix', '' ), '/' );
	}

	/**
	 * Return the frontend's image optimization strategy: 'native' (the
	 * hosting platform's own optimizer — default), 'sharp' (frontend's
	 * self-hosted Node.js resizer), 'proxy' (wsrv.nl free image proxy), or
	 * 'unoptimized' (serve original files untouched). Re-validates against
	 * the same whitelist on read, in case the option was ever written
	 * directly via update_option() bypassing the sanitize callback.
	 */
	public function image_strategy(): string {
		$value = (string) $this->get( 'headlessbridge_image_strategy', 'native' );
		return in_array( $value, self::IMAGE_STRATEGIES, true ) ? $value : 'native';
	}

	/**
	 * Whether headless mode is currently active.
	 */
	public function is_headless(): bool {
		return $this->is_enabled( 'headlessbridge_enabled' );
	}

	/**
	 * Return allowed CORS origins as an array of trimmed strings.
	 *
	 * @return string[]
	 */
	public function allowed_origins(): array {
		$raw = (string) $this->get( 'headlessbridge_allowed_origins', '' );
		if ( '' === $raw ) {
			return [];
		}
		return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	}

	/**
	 * Register settings with WordPress Settings API (called once on init).
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register all plugin options via Settings API.
	 *
	 * Each admin tab is a separate <form> that only submits its own fields.
	 * WordPress's options.php processes every option registered under a
	 * group on every save of that group — including with a null value for
	 * fields absent from the submitted form — so options must be split into
	 * one group per tab. Sharing a single group across tabs previously
	 * caused saving one tab (e.g. CORS) to null out another tab's fields
	 * (e.g. General's headlessbridge_enabled / headlessbridge_frontend_url).
	 */
	public function register_settings(): void {
		$groups = [
			'headlessbridge_general'  => [
				'headlessbridge_enabled'          => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_frontend_url'     => [ 'sanitize_callback' => 'esc_url_raw' ],
				'headlessbridge_image_strategy'   => [ 'sanitize_callback' => [ $this, 'sanitize_image_strategy' ] ],
				'headlessbridge_preserve_slugs'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_post_path_prefix' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_maintenance_mode' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_xmlrpc_enabled'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
			],
			'headlessbridge_seo'      => [
				'headlessbridge_noindex'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_robots_txt' => [ 'sanitize_callback' => 'sanitize_text_field' ],
			],
			'headlessbridge_features' => [
				'headlessbridge_disable_rss'             => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_disable_search'          => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_disable_comments'        => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_disable_author_archives' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'headlessbridge_disable_date_archives'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
			],
			'headlessbridge_api'      => [
				'headlessbridge_allowed_origins' => [ 'sanitize_callback' => [ $this, 'sanitize_origins' ] ],
			],
		];

		foreach ( $groups as $group => $options ) {
			foreach ( $options as $key => $args ) {
				register_setting( $group, $key, $args );
			}
		}
	}

	/**
	 * Sanitize a newline-separated list of URLs.
	 *
	 * Settings are split across multiple per-tab forms that all share the
	 * same registered option group, so saving one tab causes WordPress to
	 * run every group member's sanitize callback — including this one with
	 * a null value for fields that weren't part of the submitted form.
	 *
	 * @param string|null $value Raw textarea input.
	 * @return string
	 */
	public function sanitize_origins( ?string $value ): string {
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) $value ) ) );
		$clean = array_map( 'esc_url_raw', $lines );
		return implode( "\n", $clean );
	}

	/**
	 * Sanitize the image strategy setting to one of the known values,
	 * falling back to 'native' for anything unrecognized (e.g. a tampered
	 * POST body) or absent (a save from a tab that doesn't include this
	 * field).
	 */
	public function sanitize_image_strategy( ?string $value ): string {
		return in_array( $value, self::IMAGE_STRATEGIES, true ) ? $value : 'native';
	}
}
