<?php
/**
 * Unit tests for HeadlessBridge\Settings.
 *
 * Run with: vendor/bin/phpunit tests/
 *
 * @package HeadlessBridge
 */

use HeadlessBridge\Settings;

class Test_Settings extends WP_UnitTestCase {

	private Settings $settings;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		delete_option( 'headlessbridge_enabled' );
		delete_option( 'headlessbridge_frontend_url' );
		delete_option( 'headlessbridge_allowed_origins' );
		parent::tear_down();
	}

	public function test_is_headless_returns_false_by_default(): void {
		$this->assertFalse( $this->settings->is_headless() );
	}

	public function test_is_headless_returns_true_when_enabled(): void {
		update_option( 'headlessbridge_enabled', '1' );
		$this->assertTrue( $this->settings->is_headless() );
	}

	public function test_frontend_url_returns_empty_by_default(): void {
		$this->assertSame( '', $this->settings->frontend_url() );
	}

	public function test_frontend_url_returns_configured_url(): void {
		update_option( 'headlessbridge_frontend_url', 'https://plus233.com' );
		$this->assertSame( 'https://plus233.com', $this->settings->frontend_url() );
	}

	public function test_allowed_origins_returns_empty_array_by_default(): void {
		$this->assertSame( [], $this->settings->allowed_origins() );
	}

	public function test_allowed_origins_parses_newline_separated_list(): void {
		update_option( 'headlessbridge_allowed_origins', "https://plus233.com\nhttps://app.plus233.com" );
		$origins = $this->settings->allowed_origins();
		$this->assertCount( 2, $origins );
		$this->assertContains( 'https://plus233.com', $origins );
		$this->assertContains( 'https://app.plus233.com', $origins );
	}

	public function test_sanitize_origins_strips_whitespace(): void {
		$result = $this->settings->sanitize_origins( "  https://plus233.com  \n  https://app.plus233.com  \n" );
		$lines  = explode( "\n", $result );
		$this->assertCount( 2, array_filter( $lines ) );
	}

	public function test_is_enabled_false_for_zero_value(): void {
		update_option( 'headlessbridge_disable_rss', '0' );
		$this->assertFalse( $this->settings->is_enabled( 'headlessbridge_disable_rss' ) );
	}

	public function test_is_enabled_true_for_one_value(): void {
		update_option( 'headlessbridge_disable_rss', '1' );
		$this->assertTrue( $this->settings->is_enabled( 'headlessbridge_disable_rss' ) );
	}
}
