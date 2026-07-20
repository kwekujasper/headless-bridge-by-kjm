<?php
/**
 * Handles optional WordPress feature disabling (RSS, search, comments, archives).
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Api
 *
 * Controls which WordPress features are active when in headless mode.
 */
class Api {

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		if ( ! $this->settings->is_headless() ) {
			return;
		}

		if ( $this->settings->is_enabled( 'headlessbridge_disable_rss' ) ) {
			add_action( 'do_feed',        [ $this, 'disable_feed' ], 1 );
			add_action( 'do_feed_rdf',    [ $this, 'disable_feed' ], 1 );
			add_action( 'do_feed_rss',    [ $this, 'disable_feed' ], 1 );
			add_action( 'do_feed_rss2',   [ $this, 'disable_feed' ], 1 );
			add_action( 'do_feed_atom',   [ $this, 'disable_feed' ], 1 );
			add_action( 'do_feed_rss2_comments', [ $this, 'disable_feed' ], 1 );
			add_action( 'do_feed_atom_comments', [ $this, 'disable_feed' ], 1 );
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}

		if ( $this->settings->is_enabled( 'headlessbridge_disable_search' ) ) {
			add_action( 'parse_query', [ $this, 'disable_search' ] );
			add_filter( 'get_search_form', '__return_empty_string' );
		}

		if ( $this->settings->is_enabled( 'headlessbridge_disable_comments' ) ) {
			add_filter( 'comments_open',          '__return_false', 20 );
			add_filter( 'pings_open',             '__return_false', 20 );
			add_filter( 'comments_array',         '__return_empty_array', 10 );
			add_action( 'admin_menu',             [ $this, 'remove_comments_menu' ] );
			add_action( 'init',                   [ $this, 'disable_comments_post_types' ] );
			add_action( 'admin_init',             [ $this, 'redirect_comments_admin' ] );
			remove_action( 'wp_head', 'wp_generator' );
		}

		if ( $this->settings->is_enabled( 'headlessbridge_disable_author_archives' ) ) {
			add_action( 'template_redirect', [ $this, 'disable_author_archive' ], 2 );
		}

		if ( $this->settings->is_enabled( 'headlessbridge_disable_date_archives' ) ) {
			add_action( 'template_redirect', [ $this, 'disable_date_archive' ], 2 );
		}
	}

	/**
	 * Output a 403 when a feed is requested.
	 */
	public function disable_feed(): void {
		wp_die(
			esc_html__( 'Feed not available. Please visit our website.', 'headless-bridge-by-kjm' ),
			esc_html__( 'Feed Disabled', 'headless-bridge-by-kjm' ),
			[ 'response' => 403 ]
		);
	}

	/**
	 * Return 404 on frontend search requests.
	 *
	 * @param \WP_Query $query
	 */
	public function disable_search( \WP_Query $query ): void {
		if ( $query->is_search() && ! is_admin() ) {
			$query->set_404();
			status_header( 404 );
		}
	}

	/**
	 * Remove Comments menu from WP Admin.
	 */
	public function remove_comments_menu(): void {
		remove_menu_page( 'edit-comments.php' );
	}

	/**
	 * Remove comment support from all registered post types.
	 */
	public function disable_comments_post_types(): void {
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Redirect any comment-related admin pages.
	 */
	public function redirect_comments_admin(): void {
		global $pagenow;
		if ( 'edit-comments.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Send 404 on author archive requests.
	 */
	public function disable_author_archive(): void {
		if ( is_author() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}
	}

	/**
	 * Send 404 on date archive requests.
	 */
	public function disable_date_archive(): void {
		if ( is_date() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}
	}
}
