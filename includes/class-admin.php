<?php
/**
 * Admin interface — settings page, menus, and asset enqueueing.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

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
		add_filter( 'plugin_action_links_' . HEADLESSBRIDGE_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );

		// Tools: handle form submissions.
		add_action( 'admin_post_headlessbridge_flush_permalinks', [ $this, 'handle_flush_permalinks' ] );
		add_action( 'admin_post_headlessbridge_export_settings',  [ $this, 'handle_export_settings' ] );
		add_action( 'admin_post_headlessbridge_import_settings',  [ $this, 'handle_import_settings' ] );
		add_action( 'admin_post_headlessbridge_reset_settings',   [ $this, 'handle_reset_settings' ] );
	}

	/**
	 * Register the Settings > Headless Bridge submenu.
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'Headless Bridge Settings', 'headless-bridge-by-kjm' ),
			__( 'Headless Bridge', 'headless-bridge-by-kjm' ),
			'manage_options',
			'headless-bridge-by-kjm',
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
				'headlessbridge-admin',
				HEADLESSBRIDGE_PLUGIN_URL . 'assets/css/admin.css',
				[],
				HEADLESSBRIDGE_VERSION
			);
			return;
		}

		if ( 'settings_page_headless-bridge-by-kjm' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'headlessbridge-admin',
			HEADLESSBRIDGE_PLUGIN_URL . 'assets/css/admin.css',
			[],
			HEADLESSBRIDGE_VERSION
		);

		wp_enqueue_script(
			'headlessbridge-admin',
			HEADLESSBRIDGE_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			HEADLESSBRIDGE_VERSION,
			true
		);

		wp_localize_script( 'headlessbridge-admin', 'headlessbridgeAdmin', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'healthNonce'   => wp_create_nonce( 'headlessbridge_health_nonce' ),
			'webhooksNonce' => wp_create_nonce( 'headlessbridge_webhooks_nonce' ),
			'i18n'          => [
				'checking'        => __( 'Checking…', 'headless-bridge-by-kjm' ),
				'runCheck'        => __( 'Run Check', 'headless-bridge-by-kjm' ),
				'pass'            => __( 'Pass', 'headless-bridge-by-kjm' ),
				'fail'            => __( 'Fail', 'headless-bridge-by-kjm' ),
				'info'            => __( 'Info', 'headless-bridge-by-kjm' ),
				'clearCache'      => __( 'Clear Cache', 'headless-bridge-by-kjm' ),
				'cacheCleared'    => __( 'Cache cleared.', 'headless-bridge-by-kjm' ),
				'error'           => __( 'An error occurred.', 'headless-bridge-by-kjm' ),
				'save'            => __( 'Save Webhook', 'headless-bridge-by-kjm' ),
				'saving'          => __( 'Saving…', 'headless-bridge-by-kjm' ),
				'delete'          => __( 'Delete', 'headless-bridge-by-kjm' ),
				'deleting'        => __( 'Deleting…', 'headless-bridge-by-kjm' ),
				'confirmDelete'   => __( 'Delete this webhook? This cannot be undone.', 'headless-bridge-by-kjm' ),
				'sendTest'        => __( 'Send Test', 'headless-bridge-by-kjm' ),
				'sendingTest'     => __( 'Sending…', 'headless-bridge-by-kjm' ),
				'testPass'        => __( 'Success', 'headless-bridge-by-kjm' ),
				'testFail'        => __( 'Failed', 'headless-bridge-by-kjm' ),
				'generateSecret'  => __( 'Generate', 'headless-bridge-by-kjm' ),
				'generating'      => __( 'Generating…', 'headless-bridge-by-kjm' ),
				'showSecret'      => __( 'Show', 'headless-bridge-by-kjm' ),
				'hideSecret'      => __( 'Hide', 'headless-bridge-by-kjm' ),
				'addWebhook'      => __( 'Add New Webhook', 'headless-bridge-by-kjm' ),
				'editWebhook'     => __( 'Edit Webhook', 'headless-bridge-by-kjm' ),
				'cancel'          => __( 'Cancel', 'headless-bridge-by-kjm' ),
				'keepExisting'    => __( '(leave blank to keep the existing secret)', 'headless-bridge-by-kjm' ),
				'noTriggers'      => __( 'Select at least one trigger.', 'headless-bridge-by-kjm' ),
				'quickSetupName'  => __( 'Frontend Revalidation', 'headless-bridge-by-kjm' ),
			],
		] );
	}

	/**
	 * Show an admin notice when headless mode is active without a frontend URL.
	 */
	public function maybe_show_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_headless-bridge-by-kjm' === $screen->id ) {
			return;
		}

		if ( $this->settings->is_headless() && empty( $this->settings->frontend_url() ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo wp_kses_post( sprintf(
				/* translators: %s: link to settings page */
				__( '<strong>Headless Bridge:</strong> Headless mode is active but no Frontend URL is configured. <a href="%s">Configure now</a>.', 'headless-bridge-by-kjm' ),
				esc_url( admin_url( 'options-general.php?page=headless-bridge-by-kjm' ) )
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
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=headless-bridge-by-kjm' ) ) . '">'
			. esc_html__( 'Settings', 'headless-bridge-by-kjm' ) . '</a>';
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
		include HEADLESSBRIDGE_PLUGIN_DIR . 'templates/admin-page.php';
	}

	// -------------------------------------------------------------------------
	// Tool handlers
	// -------------------------------------------------------------------------

	/**
	 * Flush rewrite rules.
	 */
	public function handle_flush_permalinks(): void {
		check_admin_referer( 'headlessbridge_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		flush_rewrite_rules();
		wp_safe_redirect( add_query_arg( [ 'page' => 'headless-bridge-by-kjm', 'tab' => 'tools', 'flushed' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Export all plugin settings as a JSON file download.
	 */
	public function handle_export_settings(): void {
		check_admin_referer( 'headlessbridge_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		$export = [
			'headlessbridge_version'                 => HEADLESSBRIDGE_VERSION,
			'headlessbridge_enabled'                 => $this->settings->get( 'headlessbridge_enabled' ),
			'headlessbridge_frontend_url'            => $this->settings->get( 'headlessbridge_frontend_url' ),
			'headlessbridge_noindex'                 => $this->settings->get( 'headlessbridge_noindex' ),
			'headlessbridge_preserve_slugs'          => $this->settings->get( 'headlessbridge_preserve_slugs' ),
			'headlessbridge_post_path_prefix'        => $this->settings->get( 'headlessbridge_post_path_prefix' ),
			'headlessbridge_disable_rss'             => $this->settings->get( 'headlessbridge_disable_rss' ),
			'headlessbridge_disable_search'          => $this->settings->get( 'headlessbridge_disable_search' ),
			'headlessbridge_disable_comments'        => $this->settings->get( 'headlessbridge_disable_comments' ),
			'headlessbridge_disable_author_archives' => $this->settings->get( 'headlessbridge_disable_author_archives' ),
			'headlessbridge_disable_date_archives'   => $this->settings->get( 'headlessbridge_disable_date_archives' ),
			'headlessbridge_allowed_origins'         => $this->settings->get( 'headlessbridge_allowed_origins' ),
			'headlessbridge_maintenance_mode'        => $this->settings->get( 'headlessbridge_maintenance_mode' ),
			'headlessbridge_xmlrpc_enabled'          => $this->settings->get( 'headlessbridge_xmlrpc_enabled' ),
			'headlessbridge_robots_txt'              => $this->settings->get( 'headlessbridge_robots_txt' ),
			'headlessbridge_image_strategy'          => $this->settings->get( 'headlessbridge_image_strategy' ),
			// Secrets stripped — a downloadable JSON file shouldn't carry live credentials.
			'headlessbridge_webhooks'                => array_map(
				function ( array $webhook ): array {
					$webhook['secret'] = '';
					return $webhook;
				},
				$this->webhooks->get_all()
			),
		];

		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="headlessbridge-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $export, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import settings from an uploaded JSON file.
	 */
	public function handle_import_settings(): void {
		check_admin_referer( 'headlessbridge_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		if ( empty( $_FILES['headlessbridge_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'headless-bridge-by-kjm', 'tab' => 'tools', 'import_error' => '1' ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		$file_path = sanitize_text_field( wp_unslash( $_FILES['headlessbridge_import_file']['tmp_name'] ) );
		$content   = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data      = json_decode( $content, true );

		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'headless-bridge-by-kjm', 'tab' => 'tools', 'import_error' => '1' ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		$allowed_keys = [
			'headlessbridge_enabled', 'headlessbridge_frontend_url', 'headlessbridge_noindex',
			'headlessbridge_preserve_slugs', 'headlessbridge_post_path_prefix', 'headlessbridge_disable_rss', 'headlessbridge_disable_search',
			'headlessbridge_disable_comments', 'headlessbridge_disable_author_archives',
			'headlessbridge_disable_date_archives', 'headlessbridge_allowed_origins',
			'headlessbridge_maintenance_mode', 'headlessbridge_xmlrpc_enabled', 'headlessbridge_robots_txt',
			'headlessbridge_image_strategy',
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
		if ( isset( $data['headlessbridge_webhooks'] ) && is_array( $data['headlessbridge_webhooks'] ) ) {
			$imported = $this->webhooks->sanitize_import( $data['headlessbridge_webhooks'] );
			update_option( 'headlessbridge_webhooks', $imported, false );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'headless-bridge-by-kjm', 'tab' => 'tools', 'imported' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Reset all settings to their defaults.
	 */
	public function handle_reset_settings(): void {
		check_admin_referer( 'headlessbridge_tools_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		// Require the current user's password to confirm a destructive,
		// irreversible action — a nonce only proves the request came from
		// this site's admin UI, not that the person at the keyboard right
		// now intends this specific action.
		$password     = isset( $_POST['headlessbridge_reset_password'] ) ? sanitize_text_field( wp_unslash( $_POST['headlessbridge_reset_password'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$current_user = wp_get_current_user();

		if ( '' === $password || ! wp_check_password( $password, $current_user->user_pass, $current_user->ID ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'headless-bridge-by-kjm', 'tab' => 'tools', 'reset_error' => '1' ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		$keys = [
			'headlessbridge_enabled', 'headlessbridge_frontend_url', 'headlessbridge_noindex',
			'headlessbridge_preserve_slugs', 'headlessbridge_post_path_prefix', 'headlessbridge_disable_rss', 'headlessbridge_disable_search',
			'headlessbridge_disable_comments', 'headlessbridge_disable_author_archives',
			'headlessbridge_disable_date_archives', 'headlessbridge_allowed_origins',
			'headlessbridge_maintenance_mode', 'headlessbridge_xmlrpc_enabled', 'headlessbridge_robots_txt',
			'headlessbridge_image_strategy', 'headlessbridge_webhooks',
		];

		foreach ( $keys as $key ) {
			delete_option( $key );
		}

		// Re-run activation to restore defaults.
		require_once HEADLESSBRIDGE_PLUGIN_DIR . 'includes/class-activator.php';
		Activator::activate();

		wp_safe_redirect( add_query_arg( [ 'page' => 'headless-bridge-by-kjm', 'reset' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}
}
