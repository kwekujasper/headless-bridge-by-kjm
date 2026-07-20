<?php
/**
 * Exposes select Headless Bridge settings over WPGraphQL so the frontend can
 * read them at request time instead of needing a duplicated, manually
 * synced config value.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Graphql
 */
class Graphql {

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		add_action( 'graphql_register_types', [ $this, 'register_fields' ] );
	}

	/**
	 * Adds a postPathPrefix field to the GeneralSettings GraphQL type.
	 * No-ops if WPGraphQL isn't active.
	 */
	public function register_fields(): void {
		if ( ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		register_graphql_field(
			'GeneralSettings',
			'postPathPrefix',
			[
				'type'        => 'String',
				'description' => __( 'Path segment Headless Bridge prepends to single-post redirects (e.g. "post"), or empty for root-level post URLs.', 'headless-bridge-by-kjm' ),
				'resolve'     => fn() => $this->settings->post_path_prefix(),
			]
		);

		register_graphql_field(
			'GeneralSettings',
			'imageStrategy',
			[
				'type'        => 'String',
				'description' => __( 'Frontend image optimization strategy: "native", "sharp" (self-hosted Node.js resizer), "proxy" (wsrv.nl), or "unoptimized".', 'headless-bridge-by-kjm' ),
				'resolve'     => fn() => $this->settings->image_strategy(),
			]
		);
	}
}
