<?php
/**
 * CORS header management.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cors
 *
 * Emits Access-Control-* headers for configured origins.
 */
class Cors {

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		add_action( 'send_headers',            [ $this, 'send_cors_headers' ] );
		add_action( 'rest_api_init',           [ $this, 'send_cors_headers' ], 15 );
		add_filter( 'rest_pre_serve_request',  [ $this, 'handle_preflight' ] );
	}

	/**
	 * Add CORS headers for allowed origins.
	 */
	public function send_cors_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$allowed_origins = $this->settings->allowed_origins();

		if ( empty( $allowed_origins ) ) {
			return;
		}

		$request_origin = $this->get_request_origin();

		if ( empty( $request_origin ) ) {
			return;
		}

		if ( in_array( $request_origin, $allowed_origins, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $request_origin ) );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With' );
			header( 'Vary: Origin' );
			return;
		}

		if ( in_array( '*', $allowed_origins, true ) ) {
			// A wildcard means "public, unauthenticated access for anyone" —
			// never pair that with Allow-Credentials, which would let any
			// site ride a logged-in visitor's cookies cross-origin and read
			// the response. Send a literal "*" (no reflected origin) and
			// drop credentials, matching what the browser itself requires
			// for a wildcard response to be usable.
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With' );
		}
	}

	/**
	 * Handle OPTIONS preflight requests and short-circuit with 200.
	 *
	 * @param bool $served Whether the request has already been served.
	 * @return bool
	 */
	public function handle_preflight( bool $served ): bool {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			$this->send_cors_headers();
			status_header( 200 );
			exit;
		}
		return $served;
	}

	/**
	 * Return the Origin header from the current request.
	 */
	private function get_request_origin(): string {
		return isset( $_SERVER['HTTP_ORIGIN'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) )
			: '';
	}
}
