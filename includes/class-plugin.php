<?php
/**
 * Core plugin class — wires up all subsystems.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Singleton that bootstraps every subsystem.
 */
class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var Settings */
	private Settings $settings;

	/** @var Admin */
	private Admin $admin;

	/** @var Redirects */
	private Redirects $redirects;

	/** @var Api */
	private Api $api;

	/** @var Security */
	private Security $security;

	/** @var Cors */
	private Cors $cors;

	/** @var Health */
	private Health $health;

	/** @var Graphql */
	private Graphql $graphql;

	/** @var Webhooks */
	private Webhooks $webhooks;

	private function __construct() {}

	/**
	 * Get or create the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all subsystems.
	 */
	public function run(): void {
		$this->settings  = new Settings();
		$this->redirects = new Redirects( $this->settings );
		$this->api       = new Api( $this->settings );
		$this->security  = new Security( $this->settings );
		$this->cors      = new Cors( $this->settings );
		$this->health    = new Health( $this->settings );
		$this->webhooks  = new Webhooks( $this->settings );
		$this->admin     = new Admin( $this->settings, $this->health, $this->webhooks );
		$this->graphql   = new Graphql( $this->settings );

		$this->settings->register_hooks();
		$this->redirects->register_hooks();
		$this->api->register_hooks();
		$this->security->register_hooks();
		$this->cors->register_hooks();
		$this->health->register_hooks();
		$this->webhooks->register_hooks();
		$this->admin->register_hooks();
		$this->graphql->register_hooks();
	}
}
