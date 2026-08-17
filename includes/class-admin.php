<?php
/**
 * Admin interface — settings page, menus, and asset enqueueing.
 *
 * @package KJMHCG
 */

namespace KJMHCG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	public function __construct(
		private Settings $settings,
		private Health   $health,
		private Webhooks $webhooks
	) {}

	public function register_hooks(): void {
		add_action( 'admin_menu',             [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices',          [ $this, 'maybe_show_notices' ] );
		add_filter( 'plugin_action_links_' . KJMHCG_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );

		// Tools: handle form submissions.
		add_action( 'admin_post_kjmhcg_flush_permalinks', [ $this, 'handle_flush_permalinks' ] );
		add_action( 'admin_post_kjmhcg_export_settings',  [ $this, 'handle_export_settings' ] );
		add_action( 'admin_post_kjmhcg_import_settings',  [ $this, 'handle_import_settings' ] );
		add_action( 'admin_post_kjmhcg_reset_settings',   [ $this, 'handle_reset_settings' ] );
	}

	/**
	 * Register the Settings > Headless CMS Gateway submenu.
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'Headless CMS Gateway Settings', 'kjm-headless-cms-gateway' ),
			__( 'Headless CMS Gateway', 'kjm-headless-cms-gateway' ),
			'manage_options',
			'kjm-headless-cms-gateway',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Dashboard widget assets.
		if ( 'index.php' === $hook ) {
			wp_enqueue_style(
				'kjmhcg-admin',
				KJMHCG_PLUGIN_URL . 'assets/css/admin.css',
				[],
				KJMHCG_VERSION
			);
			return;
		}

		if ( 'settings_page_kjm-headless-cms-gateway' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'kjmhcg-admin',
			KJMHCG_PLUGIN_URL . 'assets/css/admin.css',
			[],
			KJMHCG_VERSION
		);

		wp_enqueue_script(
			'kjmhcg-admin',
			KJMHCG_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			KJMHCG_VERSION,
			true
		);

		wp_localize_script( 'kjmhcg-admin', 'kjmhcgAdmin', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'healthNonce'   => wp_create_nonce( 'kjmhcg_health_nonce' ),
			'webhooksNonce' => wp_create_nonce( 'kjmhcg_webhooks_nonce' ),
			'i18n'          => [
				'checking'        => __( 'Checking…', 'kjm-headless-cms-gateway' ),
				'runCheck'        => __( 'Run Check', 'kjm-headless-cms-gateway' ),
				'pass'            => __( 'Pass', 'kjm-headless-cms-gateway' ),
				'fail'            => __( 'Fail', 'kjm-headless-cms-gateway' ),
				'info'            => __( 'Info', 'kjm-headless-cms-gateway' ),
				'clearCache'      => __( 'Clear Cache', 'kjm-headless-cms-gateway' ),
				'cacheCleared'    => __( 'Cache cleared.', 'kjm-headless-cms-gateway' ),
				'error'           => __( 'An error occurred.', 'kjm-headless-cms-gateway' ),
				'save'            => __( 'Save Webhook', 'kjm-headless-cms-gateway' ),
				'saving'          => __( 'Saving…', 'kjm-headless-cms-gateway' ),
				'delete'          => __( 'Delete', 'kjm-headless-cms-gateway' ),
				'deleting'        => __( 'Deleting…', 'kjm-headless-cms-gateway' ),
				'confirmDelete'   => __( 'Delete this webhook? This cannot be undone.', 'kjm-headless-cms-gateway' ),
				'sendTest'        => __( 'Send Test', 'kjm-headless-cms-gateway' ),
				'sendingTest'     => __( 'Sending…', 'kjm-headless-cms-gateway' ),
				'testPass'        => __( 'Success', 'kjm-headless-cms-gateway' ),
				'testFail'        => __( 'Failed', 'kjm-headless-cms-gateway' ),
				'generateSecret'  => __( 'Generate', 'kjm-headless-cms-gateway' ),
				'generating'      => __( 'Generating…', 'kjm-headless-cms-gateway' ),
				'showSecret'      => __( 'Show', 'kjm-headless-cms-gateway' ),
				'hideSecret'      => __( 'Hide', 'kjm-headless-cms-gateway' ),
				'addWebhook'      => __( 'Add New Webhook', 'kjm-headless-cms-gateway' ),
				'editWebhook'     => __( 'Edit Webhook', 'kjm-headless-cms-gateway' ),
				'cancel'          => __( 'Cancel', 'kjm-headless-cms-gateway' ),
				'keepExisting'    => __( '(leave blank to keep the existing secret)', 'kjm-headless-cms-gateway' ),
				'noTriggers'      => __( 'Select at least one trigger.', 'kjm-headless-cms-gateway' ),
				'quickSetupName'  => __( 'Frontend Revalidation', 'kjm-headless-cms-gateway' ),
				'linkLabel'       => __( 'Label', 'kjm-headless-cms-gateway' ),
				'linkUrl'         => __( '/about or https://…', 'kjm-headless-cms-gateway' ),
			],
		] );
	}

	/**
	 * Show an admin notice when headless mode is active without a frontend URL.
	 */
	public function maybe_show_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_kjm-headless-cms-gateway' === $screen->id ) {
			return;
		}

		if ( $this->settings->is_headless() && empty( $this->settings->frontend_url() ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo wp_kses_post( sprintf(
				/* translators: %s: link to settings page */
				__( '<strong>Headless CMS Gateway:</strong> Headless mode is active but no Frontend URL is configured. <a href="%s">Configure now</a>.', 'kjm-headless-cms-gateway' ),
				esc_url( admin_url( 'options-general.php?page=kjm-headless-cms-gateway' ) )
			) );
			echo '</p></div>';
		}
	}

	/**
	 * Add "Settings" link on the Plugins list page.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=kjm-headless-cms-gateway' ) ) . '">'
			. esc_html__( 'Settings', 'kjm-headless-cms-gateway' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->settings;
		$health   = $this->health;
		$webhooks = $this->webhooks;
		include KJMHCG_PLUGIN_DIR . 'templates/admin-page.php';
	}

	// -------------------------------------------------------------------------
	// Tool handlers
	// -------------------------------------------------------------------------

	/**
	 * Flush rewrite rules.
	 */
	public function handle_flush_permalinks(): void {
		check_admin_referer( 'kjmhcg_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'kjm-headless-cms-gateway' ) );
		}

		flush_rewrite_rules();
		wp_safe_redirect( add_query_arg( [ 'page' => 'kjm-headless-cms-gateway', 'tab' => 'tools', 'flushed' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Export all plugin settings as a JSON file download.
	 */
	public function handle_export_settings(): void {
		check_admin_referer( 'kjmhcg_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'kjm-headless-cms-gateway' ) );
		}

		$export = [
			'kjmhcg_version'                 => KJMHCG_VERSION,
			'kjmhcg_enabled'                 => $this->settings->get( 'kjmhcg_enabled' ),
			'kjmhcg_frontend_url'            => $this->settings->get( 'kjmhcg_frontend_url' ),
			'kjmhcg_noindex'                 => $this->settings->get( 'kjmhcg_noindex' ),
			'kjmhcg_preserve_slugs'          => $this->settings->get( 'kjmhcg_preserve_slugs' ),
			'kjmhcg_post_path_prefix'        => $this->settings->get( 'kjmhcg_post_path_prefix' ),
			'kjmhcg_disable_rss'             => $this->settings->get( 'kjmhcg_disable_rss' ),
			'kjmhcg_disable_search'          => $this->settings->get( 'kjmhcg_disable_search' ),
			'kjmhcg_disable_comments'        => $this->settings->get( 'kjmhcg_disable_comments' ),
			'kjmhcg_disable_author_archives' => $this->settings->get( 'kjmhcg_disable_author_archives' ),
			'kjmhcg_disable_date_archives'   => $this->settings->get( 'kjmhcg_disable_date_archives' ),
			'kjmhcg_allowed_origins'         => $this->settings->get( 'kjmhcg_allowed_origins' ),
			'kjmhcg_maintenance_mode'        => $this->settings->get( 'kjmhcg_maintenance_mode' ),
			'kjmhcg_xmlrpc_enabled'          => $this->settings->get( 'kjmhcg_xmlrpc_enabled' ),
			'kjmhcg_robots_txt'              => $this->settings->get( 'kjmhcg_robots_txt' ),
			'kjmhcg_image_strategy'          => $this->settings->get( 'kjmhcg_image_strategy' ),
			'kjmhcg_home_category'           => $this->settings->get( 'kjmhcg_home_category' ),
			'kjmhcg_menu_items'              => $this->settings->get( 'kjmhcg_menu_items' ),
			'kjmhcg_homepage_sections'       => $this->settings->get( 'kjmhcg_homepage_sections' ),
			// Secrets stripped — a downloadable JSON file shouldn't carry live credentials.
			'kjmhcg_webhooks'                => array_map(
				function ( array $webhook ): array {
					$webhook['secret'] = '';
					return $webhook;
				},
				$this->webhooks->get_all()
			),
		];

		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="kjmhcg-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $export, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import settings from an uploaded JSON file.
	 */
	public function handle_import_settings(): void {
		check_admin_referer( 'kjmhcg_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'kjm-headless-cms-gateway' ) );
		}

		if ( empty( $_FILES['kjmhcg_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'kjm-headless-cms-gateway', 'tab' => 'tools', 'import_error' => '1' ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		$file_path = sanitize_text_field( wp_unslash( $_FILES['kjmhcg_import_file']['tmp_name'] ) );
		$content   = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data      = json_decode( $content, true );

		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'kjm-headless-cms-gateway', 'tab' => 'tools', 'import_error' => '1' ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		$allowed_keys = [
			'kjmhcg_enabled', 'kjmhcg_frontend_url', 'kjmhcg_noindex',
			'kjmhcg_preserve_slugs', 'kjmhcg_post_path_prefix', 'kjmhcg_disable_rss', 'kjmhcg_disable_search',
			'kjmhcg_disable_comments', 'kjmhcg_disable_author_archives',
			'kjmhcg_disable_date_archives', 'kjmhcg_allowed_origins',
			'kjmhcg_maintenance_mode', 'kjmhcg_xmlrpc_enabled', 'kjmhcg_robots_txt',
			'kjmhcg_image_strategy', 'kjmhcg_home_category', 'kjmhcg_menu_items',
			'kjmhcg_homepage_sections',
		];

		foreach ( $allowed_keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				update_option( $key, sanitize_text_field( $data[ $key ] ) );
			}
		}

		// Webhooks are an array, not a scalar, and never carry secrets in the
		// export file. sanitize_import() runs each record through the same
		// validation as the webhook builder (valid URL, known triggers, a
		// template that renders for every selected trigger) instead of
		// trusting the uploaded file's contents directly, and preserves the
		// existing secret for a matching id.
		if ( isset( $data['kjmhcg_webhooks'] ) && is_array( $data['kjmhcg_webhooks'] ) ) {
			$imported = $this->webhooks->sanitize_import( $data['kjmhcg_webhooks'] );
			update_option( 'kjmhcg_webhooks', $imported, false );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'kjm-headless-cms-gateway', 'tab' => 'tools', 'imported' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Reset all settings to their defaults.
	 */
	public function handle_reset_settings(): void {
		check_admin_referer( 'kjmhcg_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'kjm-headless-cms-gateway' ) );
		}

		// Require the current user's password to confirm a destructive,
		// irreversible action — a nonce only proves the request came from
		// this site's admin UI, not that the person at the keyboard right
		// now intends this specific action.
		$password     = isset( $_POST['kjmhcg_reset_password'] ) ? sanitize_text_field( wp_unslash( $_POST['kjmhcg_reset_password'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$current_user = wp_get_current_user();

		if ( '' === $password || ! wp_check_password( $password, $current_user->user_pass, $current_user->ID ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'kjm-headless-cms-gateway', 'tab' => 'tools', 'reset_error' => '1' ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		$keys = [
			'kjmhcg_enabled', 'kjmhcg_frontend_url', 'kjmhcg_noindex',
			'kjmhcg_preserve_slugs', 'kjmhcg_post_path_prefix', 'kjmhcg_disable_rss', 'kjmhcg_disable_search',
			'kjmhcg_disable_comments', 'kjmhcg_disable_author_archives',
			'kjmhcg_disable_date_archives', 'kjmhcg_allowed_origins',
			'kjmhcg_maintenance_mode', 'kjmhcg_xmlrpc_enabled', 'kjmhcg_robots_txt',
			'kjmhcg_image_strategy', 'kjmhcg_home_category', 'kjmhcg_menu_items',
			'kjmhcg_homepage_sections', 'kjmhcg_webhooks',
		];

		foreach ( $keys as $key ) {
			delete_option( $key );
		}

		// Re-run activation to restore defaults.
		require_once KJMHCG_PLUGIN_DIR . 'includes/class-activator.php';
		Activator::activate();

		wp_safe_redirect( add_query_arg( [ 'page' => 'kjm-headless-cms-gateway', 'reset' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}
}
