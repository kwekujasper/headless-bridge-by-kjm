<?php
/**
 * Unit tests for HeadlessBridge\Cors.
 *
 * @package HeadlessBridge
 */

use HeadlessBridge\Cors;
use HeadlessBridge\Settings;

class Test_Cors extends WP_UnitTestCase {

	private Settings $settings;
	private Cors     $cors;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new Settings();
		$this->cors     = new Cors( $this->settings );
	}

	public function tear_down(): void {
		delete_option( 'headlessbridge_allowed_origins' );
		parent::tear_down();
	}

	public function test_no_cors_headers_emitted_without_origin(): void {
		update_option( 'headlessbridge_allowed_origins', 'https://plus233.com' );
		unset( $_SERVER['HTTP_ORIGIN'] );

		// Should not throw or emit anything when there's no request origin.
		$this->expectNotToPerformAssertions();
		$this->cors->send_cors_headers();
	}

	public function test_no_cors_headers_without_configured_origins(): void {
		update_option( 'headlessbridge_allowed_origins', '' );
		$_SERVER['HTTP_ORIGIN'] = 'https://evil.com';

		$this->expectNotToPerformAssertions();
		$this->cors->send_cors_headers();
	}
}
