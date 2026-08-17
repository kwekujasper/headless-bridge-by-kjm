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
	 * Return the category slug whose posts fill the frontend homepage feed,
	 * or empty string when unset. Empty tells the frontend to fall back to
	 * its own default (and ultimately to the most recent posts across all
	 * categories), so the homepage is never blank if the category is renamed
	 * or removed in WordPress.
	 */
	public function home_category(): string {
		return sanitize_title( (string) $this->get( 'headlessbridge_home_category', '' ) );
	}

	/**
	 * Return the ordered nav-menu items as a newline-separated token string
	 * (empty = the frontend auto-lists every category, its default). Each line
	 * is either `category:<slug>` (label resolved from the category on the
	 * frontend) or `link:<label>|<url>` (a custom link). Stored and returned
	 * raw; the frontend splits on newlines and parses each token. A category
	 * whose slug no longer exists / has no posts is dropped by the frontend.
	 */
	public function menu_items(): string {
		return (string) $this->get( 'headlessbridge_menu_items', '' );
	}

	/**
	 * Return the ordered category slugs the frontend homepage should render as
	 * their own sections (each showing a handful of that category's latest
	 * posts), as a newline-separated string. Empty = the frontend shows no
	 * per-category sections (just its featured + latest feed). Same storage
	 * shape as menu_categories().
	 */
	public function homepage_sections(): string {
		$raw   = (string) $this->get( 'headlessbridge_homepage_sections', '' );
		$slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( "\n", $raw ) ) ) );
		return implode( "\n", $slugs );
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
			'headlessbridge_content'  => [
				'headlessbridge_home_category'     => [ 'sanitize_callback' => 'sanitize_title' ],
				'headlessbridge_menu_items'        => [ 'sanitize_callback' => [ $this, 'sanitize_menu_items' ] ],
				'headlessbridge_homepage_sections' => [ 'sanitize_callback' => [ $this, 'sanitize_menu_categories' ] ],
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

	/**
	 * Sanitize the newline-separated menu-category slug list.
	 *
	 * Like sanitize_origins(), this runs on every save of the Content group —
	 * including with a null value for a field absent from the submitted form —
	 * so it must tolerate null/empty input. Each line is normalized to a
	 * category slug via sanitize_title(); blank lines are dropped.
	 *
	 * @param string|null $value Raw textarea input.
	 * @return string
	 */
	public function sanitize_menu_categories( ?string $value ): string {
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) $value ) ) );
		$slugs = array_filter( array_map( 'sanitize_title', $lines ) );
		return implode( "\n", $slugs );
	}

	/**
	 * Sanitize the ordered nav-menu token list. One item per line, each either
	 * `category:<slug>` or `link:<label>|<url>`. Category slugs run through
	 * sanitize_title; link labels are text-sanitized with the `|` delimiter
	 * stripped so it can't corrupt parsing, and URLs through esc_url_raw
	 * (relative paths like /about are preserved). Anything malformed is
	 * dropped. Tolerates null/empty (runs on every save of the Content group).
	 *
	 * @param string|null $value Raw newline token list from the hidden field.
	 * @return string
	 */
	public function sanitize_menu_items( ?string $value ): string {
		$out = [];
		foreach ( array_map( 'trim', explode( "\n", (string) $value ) ) as $line ) {
			if ( '' === $line ) {
				continue;
			}
			if ( 0 === strpos( $line, 'category:' ) ) {
				$slug = sanitize_title( substr( $line, strlen( 'category:' ) ) );
				if ( '' !== $slug ) {
					$out[] = 'category:' . $slug;
				}
			} elseif ( 0 === strpos( $line, 'link:' ) ) {
				$rest = substr( $line, strlen( 'link:' ) );
				$pos  = strpos( $rest, '|' );
				if ( false === $pos ) {
					continue;
				}
				$label = str_replace( '|', '', sanitize_text_field( substr( $rest, 0, $pos ) ) );
				$url   = esc_url_raw( trim( substr( $rest, $pos + 1 ) ) );
				if ( '' !== $label && '' !== $url ) {
					$out[] = 'link:' . $label . '|' . $url;
				}
			}
		}
		return implode( "\n", $out );
	}
}
