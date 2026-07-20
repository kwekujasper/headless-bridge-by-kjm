<?php
/**
 * Handles frontend redirect logic when headless mode is active.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Redirects
 */
class Redirects {

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
		add_action( 'send_headers', [ $this, 'maybe_noindex' ], 1 );

		// Optional robots.txt override.
		add_action( 'do_robots', [ $this, 'maybe_override_robots' ] );
	}

	/**
	 * Redirect frontend requests to the external frontend URL.
	 * API endpoints, admin, AJAX and cron are always allowed through.
	 */
	public function maybe_redirect(): void {
		if ( ! $this->settings->is_headless() ) {
			return;
		}

		if ( $this->is_allowed_request() ) {
			return;
		}

		$frontend_url = $this->settings->frontend_url();

		if ( empty( $frontend_url ) ) {
			// No frontend URL configured — show maintenance notice.
			$this->show_maintenance();
			return;
		}

		if ( $this->settings->is_enabled( 'headlessbridge_maintenance_mode' ) ) {
			$this->show_maintenance();
			return;
		}

		$destination = $this->build_destination( $frontend_url );

		add_filter(
			'allowed_redirect_hosts',
			function ( array $hosts ) use ( $destination ): array {
				$host = wp_parse_url( $destination, PHP_URL_HOST );
				if ( $host ) {
					$hosts[] = $host;
				}
				return $hosts;
			}
		);

		wp_safe_redirect( $destination, 301 );
		exit;
	}

	/**
	 * Determine the redirect destination, optionally preserving the request slug/path.
	 */
	private function build_destination( string $frontend_url ): string {
		$frontend_url = rtrim( $frontend_url, '/' );

		if ( ! $this->settings->is_enabled( 'headlessbridge_preserve_slugs' ) ) {
			return trailingslashit( $frontend_url );
		}

		$mapped_path = $this->map_path_for_frontend();
		if ( null !== $mapped_path ) {
			return $frontend_url . $mapped_path;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH ) ?? '/';
		$query       = wp_parse_url( $request_uri, PHP_URL_QUERY ) ?? '';

		$destination = $frontend_url . $path;
		if ( ! empty( $query ) ) {
			$destination .= '?' . $query;
		}

		return $destination;
	}

	/**
	 * Maps the current WordPress request to the equivalent Next.js frontend
	 * route, for content types whose frontend path doesn't mirror the WP
	 * permalink 1:1 (e.g. a category or author archive lives under
	 * /category/slug/ or /author/slug/ on the frontend, while a single post
	 * mirrors its WP slug at the root: /my-post/). Returns null when no
	 * mapping is known, so the caller falls back to preserving the raw
	 * request path (used for static pages, which do mirror their WP slug).
	 */
	private function map_path_for_frontend(): ?string {
		if ( is_single() ) {
			$post = get_queried_object();
			if ( ! ( $post instanceof \WP_Post ) ) {
				return null;
			}
			$prefix = $this->settings->post_path_prefix();
			return '/' . ( '' !== $prefix ? $prefix . '/' : '' ) . $post->post_name . '/';
		}

		if ( is_category() ) {
			$term = get_queried_object();
			return $term instanceof \WP_Term ? '/category/' . $term->slug . '/' : null;
		}

		if ( is_author() ) {
			$author = get_queried_object();
			return $author instanceof \WP_User ? '/author/' . $author->user_nicename . '/' : null;
		}

		if ( is_front_page() || is_home() ) {
			return '/';
		}

		return null;
	}

	/**
	 * Add X-Robots-Tag noindex header to all WordPress-generated responses.
	 */
	public function maybe_noindex(): void {
		if ( $this->settings->is_enabled( 'headlessbridge_noindex' ) ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/**
	 * Optionally override robots.txt output.
	 */
	public function maybe_override_robots(): void {
		if ( ! $this->settings->is_enabled( 'headlessbridge_robots_txt' ) ) {
			return;
		}

		echo "User-agent: *\nDisallow: /\n";

		$frontend_url = $this->settings->frontend_url();
		if ( ! empty( $frontend_url ) ) {
			echo 'Sitemap: ' . esc_url( trailingslashit( $frontend_url ) . 'sitemap.xml' ) . "\n";
		}

		exit;
	}

	/**
	 * Show a maintenance page and exit.
	 */
	private function show_maintenance(): void {
		status_header( 503 );
		nocache_headers();

		$template = HEADLESSBRIDGE_PLUGIN_DIR . 'templates/maintenance.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<p>' . esc_html__( 'Frontend temporarily unavailable. Please try again later.', 'headless-bridge-by-kjm' ) . '</p>';
		}
		exit;
	}

	/**
	 * Returns true for any request that must bypass frontend redirection.
	 */
	private function is_allowed_request(): bool {
		// WordPress admin.
		if ( is_admin() ) {
			return true;
		}

		// Post/page previews (e.g. ?p=123&preview=true from the WP editor).
		// The frontend can't render unpublished/draft content, so let
		// WordPress serve its own preview instead of redirecting it away.
		if ( is_preview() ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$allowed_patterns = [
			'#^/wp-json(/|$)#i',
			'#^/graphql(/|$)#i',
			'#^/wp-admin(/|$)#i',
			'#^/wp-login\.php#i',
			'#^/wp-cron\.php#i',
			'#^/admin-ajax\.php#i',
			'#^/wp-content/#i',
			'#^/wp-includes/#i',
			'#^/favicon\.ico$#i',
			'#^/robots\.txt$#i',
			'#^/sitemap#i',
		];

		foreach ( $allowed_patterns as $pattern ) {
			if ( preg_match( $pattern, $request_uri ) ) {
				return true;
			}
		}

		// REST API via WordPress conditional.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// AJAX requests.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}

		// Cron requests.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		// XML-RPC.
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return ! $this->settings->is_enabled( 'headlessbridge_xmlrpc_enabled' );
		}

		return false;
	}
}
