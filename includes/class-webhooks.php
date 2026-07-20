<?php
/**
 * Native webhook builder — lets an admin wire arbitrary WordPress content
 * events to arbitrary outbound webhooks (e.g. the Next.js ISR revalidation
 * endpoint, Slack, Zapier) without a separate plugin.
 *
 * @package HeadlessBridge
 */

namespace HeadlessBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhooks
 */
class Webhooks {

	/** Option key holding the array of configured webhook records. */
	private const OPTION_KEY = 'headlessbridge_webhooks';

	/**
	 * Post types tracked for published/updated/trashed triggers. Filterable
	 * so another post type (custom, or from another plugin) can be added
	 * without touching this file.
	 */
	private const TRACKED_POST_TYPES = [
		'post'    => [ 'label' => 'Post', 'type' => 'post' ],
		'page'    => [ 'label' => 'Page', 'type' => 'page' ],
		'product' => [ 'label' => 'Product', 'type' => 'product' ],
	];

	public function __construct( private Settings $settings ) {}

	public function register_hooks(): void {
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status' ], 10, 3 );

		add_action( 'created_category', [ $this, 'on_category_created' ] );
		add_action( 'edited_category', [ $this, 'on_category_updated' ] );
		add_action( 'delete_category', [ $this, 'on_category_deleted' ], 10, 3 );

		add_action( 'profile_update', [ $this, 'on_author_changed' ] );
		add_action( 'user_register', [ $this, 'on_author_changed' ] );

		add_action( 'wp_insert_comment', [ $this, 'on_comment_posted' ], 10, 2 );

		add_action( 'update_option_blogname', [ $this, 'on_settings_changed' ] );
		add_action( 'update_option_blogdescription', [ $this, 'on_settings_changed' ] );
		add_action( 'update_option_site_icon', [ $this, 'on_settings_changed' ] );

		add_action( 'wp_ajax_headlessbridge_webhook_save', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_headlessbridge_webhook_delete', [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_headlessbridge_webhook_get', [ $this, 'ajax_get' ] );
		add_action( 'wp_ajax_headlessbridge_webhook_test', [ $this, 'ajax_test' ] );
		add_action( 'wp_ajax_headlessbridge_generate_secret', [ $this, 'ajax_generate_secret' ] );
	}

	// -------------------------------------------------------------------------
	// Trigger registry
	// -------------------------------------------------------------------------

	/**
	 * Post types tracked for published/updated/trashed triggers, minus
	 * "product" when WooCommerce isn't active.
	 *
	 * @return array<string, array{label:string, type:string}>
	 */
	public function get_tracked_post_types(): array {
		$tracked = apply_filters( 'headlessbridge_webhook_tracked_post_types', self::TRACKED_POST_TYPES );

		if ( ! class_exists( 'WooCommerce' ) ) {
			unset( $tracked['product'] );
		}

		return $tracked;
	}

	/**
	 * All available trigger keys mapped to human-readable labels.
	 *
	 * @return array<string, string>
	 */
	public function get_triggers(): array {
		$triggers = [];

		foreach ( $this->get_tracked_post_types() as $post_type => $meta ) {
			$triggers[ "{$post_type}_published" ] = sprintf(
				/* translators: %s: post type label, e.g. "Post" */
				__( '%s Published', 'headless-bridge-by-kjm' ),
				$meta['label']
			);
			$triggers[ "{$post_type}_updated" ] = sprintf(
				/* translators: %s: post type label, e.g. "Post" */
				__( '%s Updated', 'headless-bridge-by-kjm' ),
				$meta['label']
			);
			$triggers[ "{$post_type}_trashed" ] = sprintf(
				/* translators: %s: post type label, e.g. "Post" */
				__( '%s Unpublished / Trashed', 'headless-bridge-by-kjm' ),
				$meta['label']
			);
		}

		$triggers['category_created'] = __( 'Category Created', 'headless-bridge-by-kjm' );
		$triggers['category_updated'] = __( 'Category Updated', 'headless-bridge-by-kjm' );
		$triggers['category_deleted'] = __( 'Category Deleted', 'headless-bridge-by-kjm' );
		$triggers['author_updated']   = __( 'Author Profile Updated', 'headless-bridge-by-kjm' );
		$triggers['comment_posted']   = __( 'Comment Posted (approved)', 'headless-bridge-by-kjm' );
		$triggers['settings_updated'] = __( 'Site Settings Updated', 'headless-bridge-by-kjm' );

		return apply_filters( 'headlessbridge_webhook_triggers', $triggers );
	}

	/**
	 * Suggested URL for a new webhook, derived from the configured frontend URL.
	 */
	public function suggested_url(): string {
		$frontend = $this->settings->frontend_url();
		return $frontend ? trailingslashit( $frontend ) . 'api/revalidate' : '';
	}

	// -------------------------------------------------------------------------
	// WP hook handlers -> dispatch()
	// -------------------------------------------------------------------------

	public function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$tracked = $this->get_tracked_post_types();
		if ( ! isset( $tracked[ $post->post_type ] ) || empty( $post->post_name ) ) {
			return;
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$trigger_key = "{$post->post_type}_published";
		} elseif ( 'publish' === $new_status && 'publish' === $old_status ) {
			$trigger_key = "{$post->post_type}_updated";
		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$trigger_key = "{$post->post_type}_trashed";
		} else {
			return;
		}

		$context = [
			'type'        => $tracked[ $post->post_type ]['type'],
			'slug'        => $this->resolve_slug_for_context( $post, $new_status ),
			'post_id'     => $post->ID,
			'post_title'  => $post->post_title,
			'post_url'    => (string) get_permalink( $post ),
			'post_status' => $new_status,
		];

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$context['sku']          = $product->get_sku();
				$context['price']        = $product->get_price();
				$context['stock_status'] = $product->get_stock_status();
			}
		}

		$this->dispatch( $trigger_key, $context );
	}

	/**
	 * wp_trash_post() appends "__trashed" to post_name before firing
	 * transition_post_status, freeing the original slug for reuse — but it
	 * stashes the pre-trash slug in this meta key so wp_untrash_post() can
	 * restore it. Use that so a *_trashed webhook still reports the real
	 * route instead of the mangled one.
	 */
	private function resolve_slug_for_context( \WP_Post $post, string $new_status ): string {
		if ( 'trash' !== $new_status ) {
			return $post->post_name;
		}

		$original = get_post_meta( $post->ID, '_wp_desired_post_slug', true );
		if ( ! empty( $original ) ) {
			return $original;
		}

		return (string) preg_replace( '/__trashed$/', '', $post->post_name );
	}

	public function on_category_created( int $term_id ): void {
		$this->dispatch_category( $term_id, 'category_created' );
	}

	public function on_category_updated( int $term_id ): void {
		$this->dispatch_category( $term_id, 'category_updated' );
	}

	private function dispatch_category( int $term_id, string $trigger_key ): void {
		$term = get_term( $term_id, 'category' );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$this->dispatch( $trigger_key, [
			'type'      => 'category',
			'slug'      => $term->slug,
			'term_id'   => $term->term_id,
			'term_name' => $term->name,
		] );
	}

	/**
	 * @param \WP_Term $deleted_term The now-deleted term object.
	 */
	public function on_category_deleted( int $term_id, int $tt_id, \WP_Term $deleted_term ): void {
		if ( empty( $deleted_term->slug ) ) {
			return;
		}

		$this->dispatch( 'category_deleted', [
			'type'      => 'category',
			'slug'      => $deleted_term->slug,
			'term_id'   => $term_id,
			'term_name' => $deleted_term->name,
		] );
	}

	public function on_author_changed( int $user_id ): void {
		if ( count_user_posts( $user_id, 'post' ) < 1 ) {
			return;
		}

		$slug = get_the_author_meta( 'user_nicename', $user_id );
		if ( empty( $slug ) ) {
			return;
		}

		$this->dispatch( 'author_updated', [
			'type'    => 'author',
			'slug'    => $slug,
			'user_id' => $user_id,
		] );
	}

	public function on_comment_posted( int $comment_id, \WP_Comment $comment ): void {
		if ( '1' !== (string) $comment->comment_approved ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || empty( $post->post_name ) ) {
			return;
		}

		$this->dispatch( 'comment_posted', [
			'type'       => 'comment',
			'slug'       => $post->post_name,
			'comment_id' => $comment_id,
			'post_id'    => $post->ID,
		] );
	}

	public function on_settings_changed(): void {
		$this->dispatch( 'settings_updated', [
			'type' => 'settings',
			'slug' => 'settings',
		] );
	}

	// -------------------------------------------------------------------------
	// Dispatch / send
	// -------------------------------------------------------------------------

	/**
	 * Fire every enabled webhook attached to $trigger_key.
	 *
	 * @param array<string, mixed> $context
	 */
	private function dispatch( string $trigger_key, array $context ): void {
		foreach ( $this->get_all() as $webhook ) {
			if ( empty( $webhook['enabled'] ) || ! in_array( $trigger_key, $webhook['triggers'] ?? [], true ) ) {
				continue;
			}

			$this->fire( $webhook, $trigger_key, $context );
		}
	}

	private function fire( array $webhook, string $trigger_key, array $context ): void {
		$rendered = $this->render_template( $webhook['payload'], $context );

		if ( is_wp_error( $rendered ) ) {
			// Known failure: the template didn't render, nothing was sent.
			$this->record_last_attempt( $webhook['id'], $trigger_key, false );
			return;
		}

		// Sent fire-and-forget — delivery outcome isn't knowable, so `ok`
		// stays null here (only a blocking Test Webhook run can set true/false).
		$this->record_last_attempt( $webhook['id'], $trigger_key, null );
		$this->send_webhook( $webhook['url'], $this->decrypt_secret( $webhook['secret'] ), $rendered );
	}

	/**
	 * Generic, target-agnostic outbound POST. Reused by every webhook
	 * regardless of trigger — this is the extension point a future webhook
	 * target (e.g. a Slack notification) would call directly.
	 *
	 * @param array<string, mixed> $args wp_remote_post() overrides, e.g. for a blocking test call.
	 * @return array|\WP_Error
	 */
	private function send_webhook( string $url, string $secret, string $rendered_json_body, array $args = [] ) {
		return wp_remote_post( $url, array_merge( [
			'blocking'  => false,
			'timeout'   => 3,
			'sslverify' => apply_filters( 'headlessbridge_webhook_sslverify', true, $url ),
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $secret,
			],
			'body'      => $rendered_json_body,
		], $args ) );
	}

	/**
	 * Render a `{{tag}}` payload template against a context array. Tag values
	 * are substituted via wp_json_encode() (quotes included), so templates
	 * must NOT wrap tags in their own quotes: `"slug":{{slug}}`, not
	 * `"slug":"{{slug}}"` — this avoids broken JSON when a value contains a
	 * quote or other special character.
	 *
	 * @param array<string, mixed> $context
	 * @return string|\WP_Error Rendered JSON string, or WP_Error if invalid.
	 */
	public function render_template( string $template, array $context ) {
		$search  = [];
		$replace = [];

		foreach ( $context as $key => $value ) {
			$search[]  = '{{' . $key . '}}';
			$replace[] = wp_json_encode( (string) $value );
		}

		$rendered = str_replace( $search, $replace, $template );
		$decoded  = json_decode( $rendered, true );

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'headlessbridge_invalid_payload',
				__( 'Payload template did not render to valid JSON.', 'headless-bridge-by-kjm' )
			);
		}

		return $rendered;
	}

	/**
	 * Dummy sample context for a given trigger, used to validate a template
	 * at save time and to power the "Send Test Webhook" button.
	 *
	 * @return array<string, mixed>
	 */
	private function sample_context( string $trigger_key ): array {
		if ( str_starts_with( $trigger_key, 'category_' ) ) {
			return [ 'type' => 'category', 'slug' => 'sample-category', 'term_id' => 1, 'term_name' => 'Sample Category' ];
		}

		if ( 'author_updated' === $trigger_key ) {
			return [ 'type' => 'author', 'slug' => 'sample-author', 'user_id' => 1 ];
		}

		if ( 'comment_posted' === $trigger_key ) {
			return [ 'type' => 'comment', 'slug' => 'sample-post', 'comment_id' => 1, 'post_id' => 1 ];
		}

		if ( 'settings_updated' === $trigger_key ) {
			return [ 'type' => 'settings', 'slug' => 'settings' ];
		}

		// Any {post_type}_published / _updated / _trashed trigger.
		$type = (string) preg_replace( '/_(published|updated|trashed)$/', '', $trigger_key );

		return [
			'type'        => $type,
			'slug'        => 'sample-' . $type,
			'post_id'     => 1,
			'post_title'  => 'Sample ' . ucfirst( $type ),
			'post_url'    => home_url( '/sample-' . $type . '/' ),
			'post_status' => 'publish',
		];
	}

	private function record_last_attempt( string $id, string $trigger_key, ?bool $ok ): void {
		$webhooks = $this->get_all();

		foreach ( $webhooks as &$webhook ) {
			if ( $webhook['id'] === $id ) {
				$webhook['last_attempt'] = [
					'time'    => current_time( 'mysql' ),
					'trigger' => $trigger_key,
					'ok'      => $ok,
				];
				break;
			}
		}
		unset( $webhook );

		update_option( self::OPTION_KEY, $webhooks, false );
	}

	// -------------------------------------------------------------------------
	// Storage CRUD
	// -------------------------------------------------------------------------

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all(): array {
		$webhooks = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $webhooks ) ) {
			return [];
		}

		// Older versions stored IDs with mixed case (from wp_generate_password()),
		// but every AJAX lookup runs the incoming ID through sanitize_key() first
		// (lowercase-only). Normalize here so those pre-existing records stay
		// matchable instead of becoming permanently un-editable/undeletable.
		foreach ( $webhooks as &$webhook ) {
			if ( isset( $webhook['id'] ) ) {
				$webhook['id'] = sanitize_key( $webhook['id'] );
			}
		}
		unset( $webhook );

		return $webhooks;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		foreach ( $this->get_all() as $webhook ) {
			if ( $webhook['id'] === $id ) {
				return $webhook;
			}
		}
		return null;
	}

	/**
	 * Validate and persist a webhook record (create if 'id' is empty/unknown,
	 * update otherwise). An empty 'secret' on update keeps the existing
	 * secret rather than blanking it — the field is never pre-filled with
	 * the real value in the admin UI, so "empty" means "unchanged" here.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save( array $data ) {
		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new \WP_Error( 'headlessbridge_missing_name', __( 'Webhook name is required.', 'headless-bridge-by-kjm' ) );
		}

		$triggers = array_values( array_intersect(
			array_map( 'sanitize_key', (array) ( $data['triggers'] ?? [] ) ),
			array_keys( $this->get_triggers() )
		) );
		if ( empty( $triggers ) ) {
			return new \WP_Error( 'headlessbridge_missing_triggers', __( 'Select at least one trigger.', 'headless-bridge-by-kjm' ) );
		}

		$url = esc_url_raw( (string) ( $data['url'] ?? '' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'headlessbridge_invalid_url', __( 'Enter a valid webhook URL.', 'headless-bridge-by-kjm' ) );
		}

		$payload = trim( (string) ( $data['payload'] ?? '' ) );
		if ( '' === $payload ) {
			return new \WP_Error( 'headlessbridge_missing_payload', __( 'Payload template is required.', 'headless-bridge-by-kjm' ) );
		}

		// Validate against every selected trigger, not just the first — a
		// template can reference tags (e.g. {{post_title}}) that only some
		// of the chosen trigger types provide, so it must render cleanly for
		// all of them or it will silently fail to fire for the others.
		foreach ( $triggers as $trigger_key ) {
			if ( is_wp_error( $this->render_template( $payload, $this->sample_context( $trigger_key ) ) ) ) {
				return new \WP_Error(
					'headlessbridge_invalid_template',
					__( 'Payload template must render to valid JSON for every selected trigger. Tags already include quotes — write "slug":{{slug}}, not "slug":"{{slug}}".', 'headless-bridge-by-kjm' )
				);
			}
		}

		$id       = sanitize_key( (string) ( $data['id'] ?? '' ) );
		$existing = $id ? $this->get( $id ) : null;
		$secret   = trim( (string) ( $data['secret'] ?? '' ) );

		$record = [
			'id'           => $existing ? $existing['id'] : sanitize_key( 'whk_' . wp_generate_password( 12, false, false ) ),
			'name'         => $name,
			'triggers'     => $triggers,
			'url'          => $url,
			'secret'       => '' !== $secret ? $this->encrypt_secret( $this->sanitize_secret( $secret ) ) : ( $existing['secret'] ?? '' ),
			'payload'      => $payload,
			'enabled'      => ! empty( $data['enabled'] ),
			'last_attempt' => $existing['last_attempt'] ?? null,
		];

		$webhooks = $this->get_all();
		$updated  = false;

		foreach ( $webhooks as $i => $webhook ) {
			if ( $webhook['id'] === $record['id'] ) {
				$webhooks[ $i ] = $record;
				$updated        = true;
				break;
			}
		}
		if ( ! $updated ) {
			$webhooks[] = $record;
		}

		update_option( self::OPTION_KEY, $webhooks, false );

		return $record;
	}

	/**
	 * Validate and sanitize webhook records from an imported settings file
	 * through the same rules as save() (valid URL, known trigger keys,
	 * template that renders to JSON for every selected trigger) rather than
	 * trusting the file's contents outright — an imported JSON file is
	 * attacker-reachable input (e.g. a malicious "settings backup" handed to
	 * an admin) and must not be able to plant an arbitrary webhook URL,
	 * unbounded triggers, or an invalid payload directly into the option.
	 * Invalid entries are silently dropped rather than failing the whole
	 * import. Secrets are never present in an export file, so a webhook
	 * whose id matches an existing one keeps its current (already
	 * encrypted) secret; a new id starts with no secret, same as the
	 * previous import behavior.
	 *
	 * @param array<int, mixed> $raw Decoded 'headlessbridge_webhooks' from the uploaded JSON.
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize_import( array $raw ): array {
		$existing_by_id = [];
		foreach ( $this->get_all() as $webhook ) {
			$existing_by_id[ $webhook['id'] ] = $webhook;
		}

		$known_triggers = array_keys( $this->get_triggers() );
		$sanitized      = [];

		foreach ( $raw as $webhook ) {
			if ( ! is_array( $webhook ) ) {
				continue;
			}

			$name = sanitize_text_field( $webhook['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$triggers = array_values( array_intersect(
				array_map( 'sanitize_key', (array) ( $webhook['triggers'] ?? [] ) ),
				$known_triggers
			) );
			if ( empty( $triggers ) ) {
				continue;
			}

			$url = esc_url_raw( (string) ( $webhook['url'] ?? '' ) );
			if ( '' === $url || ! wp_http_validate_url( $url ) ) {
				continue;
			}

			$payload = trim( (string) ( $webhook['payload'] ?? '' ) );
			if ( '' === $payload ) {
				continue;
			}

			$template_valid = true;
			foreach ( $triggers as $trigger_key ) {
				if ( is_wp_error( $this->render_template( $payload, $this->sample_context( $trigger_key ) ) ) ) {
					$template_valid = false;
					break;
				}
			}
			if ( ! $template_valid ) {
				continue;
			}

			$id = sanitize_key( (string) ( $webhook['id'] ?? '' ) );
			if ( '' === $id || ! isset( $existing_by_id[ $id ] ) ) {
				$id = sanitize_key( 'whk_' . wp_generate_password( 12, false, false ) );
			}

			$sanitized[] = [
				'id'           => $id,
				'name'         => $name,
				'triggers'     => $triggers,
				'url'          => $url,
				'secret'       => $existing_by_id[ $id ]['secret'] ?? '',
				'payload'      => $payload,
				'enabled'      => ! empty( $webhook['enabled'] ),
				'last_attempt' => $existing_by_id[ $id ]['last_attempt'] ?? null,
			];
		}

		return $sanitized;
	}

	public function delete( string $id ): bool {
		$webhooks = array_values( array_filter(
			$this->get_all(),
			fn( array $webhook ): bool => $webhook['id'] !== $id
		) );

		return update_option( self::OPTION_KEY, $webhooks, false );
	}

	/**
	 * Strip the secret from a record before it's sent to the browser,
	 * replacing it with a boolean so the UI can still show "secret is set".
	 *
	 * @param array<string, mixed> $webhook
	 * @return array<string, mixed>
	 */
	private function redact( array $webhook ): array {
		$webhook['has_secret'] = '' !== ( $webhook['secret'] ?? '' );
		unset( $webhook['secret'] );
		return $webhook;
	}

	/**
	 * Preserve base64/hex/openssl-generated bytes exactly; strip only control
	 * characters. Deliberately not sanitize_text_field(), which collapses
	 * internal whitespace and could corrupt a secret compared via
	 * timingSafeEqual on the receiving end.
	 */
	private function sanitize_secret( string $value ): string {
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) );
	}

	/**
	 * Encrypt a webhook secret before it's persisted, so a database-only
	 * compromise (a SQLi in an unrelated plugin, a leaked backup, etc.)
	 * doesn't hand over every shared secret in plaintext. Keyed from
	 * WordPress's own AUTH_KEY/AUTH_SALT (wp_salt()), which every install
	 * already has and treats as sensitive — no new secret to manage.
	 * Falls back to plaintext storage if the openssl extension is missing.
	 */
	private function encrypt_secret( string $plain ): string {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}

		$iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', $this->encryption_key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return $plain;
		}

		return 'enc:' . base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored secret. Values without the "enc:" prefix are treated
	 * as legacy plaintext (secrets saved before encryption-at-rest existed),
	 * so existing sites keep working without a migration step — they're
	 * transparently re-encrypted the next time that webhook is saved.
	 */
	private function decrypt_secret( string $stored ): string {
		if ( '' === $stored || ! str_starts_with( $stored, 'enc:' ) ) {
			return $stored;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}

		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv     = substr( $raw, 0, $iv_len );
		$cipher = substr( $raw, $iv_len );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', $this->encryption_key(), OPENSSL_RAW_DATA, $iv );

		return false !== $plain ? $plain : '';
	}

	/**
	 * Derive a stable 256-bit key from WordPress's own auth salt rather than
	 * storing a separate encryption key anywhere.
	 */
	private function encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function ajax_save(): void {
		check_ajax_referer( 'headlessbridge_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		$data   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result = $this->save( [
			'id'       => $data['id'] ?? '',
			'name'     => $data['name'] ?? '',
			'triggers' => $data['triggers'] ?? [],
			'url'      => $data['url'] ?? '',
			'secret'   => $data['secret'] ?? '',
			'payload'  => $data['payload'] ?? '',
			'enabled'  => ! empty( $data['enabled'] ),
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $this->redact( $result ) );
	}

	public function ajax_delete(): void {
		check_ajax_referer( 'headlessbridge_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		$id = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $id || ! $this->delete( $id ) ) {
			wp_send_json_error( __( 'Webhook not found.', 'headless-bridge-by-kjm' ) );
		}

		wp_send_json_success();
	}

	public function ajax_get(): void {
		check_ajax_referer( 'headlessbridge_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		$id      = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$webhook = $id ? $this->get( $id ) : null;

		if ( ! $webhook ) {
			wp_send_json_error( __( 'Webhook not found.', 'headless-bridge-by-kjm' ) );
		}

		wp_send_json_success( $this->redact( $webhook ) );
	}

	public function ajax_test(): void {
		check_ajax_referer( 'headlessbridge_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		$id      = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$webhook = $id ? $this->get( $id ) : null;

		if ( ! $webhook ) {
			wp_send_json_error( __( 'Webhook not found.', 'headless-bridge-by-kjm' ) );
		}

		if ( empty( $webhook['secret'] ) ) {
			wp_send_json_error( __( 'Set a secret before testing.', 'headless-bridge-by-kjm' ) );
		}

		$trigger_key = $webhook['triggers'][0] ?? null;
		if ( ! $trigger_key ) {
			wp_send_json_error( __( 'This webhook has no triggers configured.', 'headless-bridge-by-kjm' ) );
		}

		$context  = $this->sample_context( $trigger_key );
		$rendered = $this->render_template( $webhook['payload'], $context );

		if ( is_wp_error( $rendered ) ) {
			wp_send_json_error( $rendered->get_error_message() );
		}

		$response = $this->send_webhook( $webhook['url'], $this->decrypt_secret( $webhook['secret'] ), $rendered, [
			'blocking' => true,
			'timeout'  => 8,
		] );

		if ( is_wp_error( $response ) ) {
			$this->record_last_attempt( $id, $trigger_key, false );
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$ok   = $code >= 200 && $code < 300;

		$this->record_last_attempt( $id, $trigger_key, $ok );

		if ( $ok ) {
			wp_send_json_success( [
				/* translators: %d: HTTP status code */
				'detail' => sprintf( __( 'HTTP %d', 'headless-bridge-by-kjm' ), $code ),
			] );
		}

		wp_send_json_error( sprintf(
			/* translators: 1: HTTP status code, 2: response body */
			__( 'HTTP %1$d: %2$s', 'headless-bridge-by-kjm' ),
			$code,
			wp_remote_retrieve_body( $response )
		) );
	}

	public function ajax_generate_secret(): void {
		check_ajax_referer( 'headlessbridge_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'headless-bridge-by-kjm' ) );
		}

		wp_send_json_success( [ 'secret' => wp_generate_password( 32, false ) ] );
	}
}
