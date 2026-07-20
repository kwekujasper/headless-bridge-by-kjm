<?php
/**
 * Health checker — verifies that API endpoints and the frontend are reachable.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Health
 *
 * Runs connection checks and exposes results via an admin dashboard widget
 * and a REST endpoint at /wp-json/headlessbridge/v1/health.
 */
class Health {

	/** Cache key for transient storage of health results. */
	private const CACHE_KEY = 'headlessbridge_health_cache';

	/** Cache duration in seconds. */
	private const CACHE_TTL = 300;

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
		add_action( 'rest_api_init',      [ $this, 'register_rest_route' ] );
		add_action( 'wp_ajax_headlessbridge_health_check', [ $this, 'ajax_health_check' ] );
		add_action( 'wp_ajax_headlessbridge_clear_health_cache', [ $this, 'ajax_clear_cache' ] );
	}

	/**
	 * Register the admin dashboard widget.
	 */
	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'headlessbridge_health_widget',
			__( 'Headless Bridge Status', 'headless-bridge-by-kjm' ),
			[ $this, 'render_dashboard_widget' ]
		);
	}

	/**
	 * Render the dashboard widget HTML.
	 */
	public function render_dashboard_widget(): void {
		$results = $this->get_cached_results();
		include HEADLESSBRIDGE_PLUGIN_DIR . 'templates/health-widget.php';
	}

	/**
	 * Register REST endpoint for health status.
	 */
	public function register_rest_route(): void {
		register_rest_route(
			'headlessbridge/v1',
			'/health',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_health_check' ],
				'permission_callback' => [ $this, 'rest_permission' ],
			]
		);
	}

	/**
	 * Permission callback — require manage_options.
	 */
	public function rest_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST handler — return live health results.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_health_check(): \WP_REST_Response {
		$results = $this->run_checks( true );
		return rest_ensure_response( $results );
	}

	/**
	 * AJAX handler for the admin UI "Run Check" button.
	 */
	public function ajax_health_check(): void {
		check_ajax_referer( 'headlessbridge_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		wp_send_json_success( $this->run_checks( true ) );
	}

	/**
	 * AJAX handler to clear health check cache.
	 */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'headlessbridge_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		delete_transient( self::CACHE_KEY );
		wp_send_json_success( __( 'Cache cleared.', 'headless-bridge-by-kjm' ) );
	}

	/**
	 * Return cached health results, running checks if cache is cold.
	 *
	 * @return array<string, mixed>
	 */
	public function get_cached_results(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}
		return $this->run_checks();
	}

	/**
	 * Execute all health checks and optionally refresh the cache.
	 *
	 * @param bool $refresh_cache Whether to force a cache refresh.
	 * @return array<string, mixed>
	 */
	public function run_checks( bool $refresh_cache = false ): array {
		$results = [
			'wp_api'     => $this->check_wp_api(),
			'graphql'    => $this->check_graphql(),
			'frontend'   => $this->check_frontend(),
			'cors'       => $this->check_cors(),
			'plugin'     => $this->check_plugin_status(),
			'checked_at' => current_time( 'mysql' ),
		];

		if ( $refresh_cache || false === get_transient( self::CACHE_KEY ) ) {
			set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );
		}

		return $results;
	}

	// -------------------------------------------------------------------------
	// Individual checks
	// -------------------------------------------------------------------------

	private function check_wp_api(): array {
		$url      = rest_url( '/' );
		$response = wp_remote_get( $url, [ 'timeout' => 5, 'sslverify' => apply_filters( 'headlessbridge_health_sslverify', true, $url ) ] );

		if ( is_wp_error( $response ) ) {
			return $this->result( false, $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $this->result( $code >= 200 && $code < 300, "HTTP {$code}" );
	}

	private function check_graphql(): array {
		if ( ! function_exists( 'graphql' ) ) {
			return $this->result( false, __( 'WPGraphQL plugin not active.', 'headless-bridge-by-kjm' ) );
		}

		$url      = home_url( '/graphql' );
		$response = wp_remote_post( $url, [
			'timeout'     => 5,
			'sslverify'   => apply_filters( 'headlessbridge_health_sslverify', true, $url ),
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'body'        => wp_json_encode( [ 'query' => '{ __typename }' ] ),
		] );

		if ( is_wp_error( $response ) ) {
			return $this->result( false, $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $this->result( $code >= 200 && $code < 300, "HTTP {$code}" );
	}

	private function check_frontend(): array {
		$frontend_url = $this->settings->frontend_url();

		if ( empty( $frontend_url ) ) {
			return $this->result( false, __( 'No frontend URL configured.', 'headless-bridge-by-kjm' ) );
		}

		$response = wp_remote_get( $frontend_url, [ 'timeout' => 8, 'sslverify' => apply_filters( 'headlessbridge_health_sslverify', true, $frontend_url ) ] );

		if ( is_wp_error( $response ) ) {
			return $this->result( false, $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $this->result( $code >= 200 && $code < 400, "HTTP {$code}" );
	}

	private function check_cors(): array {
		$origins = $this->settings->allowed_origins();
		if ( empty( $origins ) ) {
			return $this->result( null, __( 'No origins configured.', 'headless-bridge-by-kjm' ) );
		}
		return $this->result( true, sprintf(
			/* translators: %d: number of allowed origins */
			_n( '%d origin configured.', '%d origins configured.', count( $origins ), 'headless-bridge-by-kjm' ),
			count( $origins )
		) );
	}

	private function check_plugin_status(): array {
		return $this->result(
			true,
			sprintf(
				/* translators: %s: plugin version */
				__( 'Headless Bridge v%s active.', 'headless-bridge-by-kjm' ),
				HEADLESSBRIDGE_VERSION
			)
		);
	}

	/**
	 * Build a standardized result array.
	 *
	 * @param bool|null $ok     True = pass, false = fail, null = informational.
	 * @param string    $detail Human-readable detail string.
	 * @return array{ok: bool|null, detail: string}
	 */
	private function result( bool|null $ok, string $detail ): array {
		return [ 'ok' => $ok, 'detail' => $detail ];
	}
}
