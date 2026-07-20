<?php
/**
 * Webhooks tab — list configured webhooks and an add/edit form.
 *
 * Expects: $webhooks (HeadlessBridge\Webhooks instance), from Admin::render_settings_page().
 *
 * Security note: this template must NEVER embed a webhook's secret anywhere
 * in the rendered HTML (visible or hidden, including inline JS data) — only
 * a `has_secret` boolean. The Edit button fetches a fresh, secret-redacted
 * copy via AJAX rather than relying on anything already in the page.
 *
 * @package HeadlessBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This is an included template — its top-level variables are locals scoped
// to the calling method, not real WordPress globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$all_triggers = $webhooks->get_triggers();
$records      = $webhooks->get_all();
?>

<p class="description">
	<?php esc_html_e( 'Wire WordPress content events (post published, category changed, and more) to any outbound webhook — your Next.js frontend, Slack, Zapier, or anything else that accepts a POST request. A single webhook can be attached to multiple triggers.', 'headless-bridge-by-kjm' ); ?>
</p>
<div class="headlessbridge-info-box">
	<p style="margin-top:0;">
		<strong><?php esc_html_e( 'What "Send Test" and "Last Attempt" mean:', 'headless-bridge-by-kjm' ); ?></strong>
	</p>
	<p>
		<?php esc_html_e( '"Send Test" fires a one-off trial call right now, using sample data, and tells you immediately whether it succeeded or failed (with the reason). Use it after saving a webhook to confirm it\'s set up correctly.', 'headless-bridge-by-kjm' ); ?>
	</p>
	<p style="margin-bottom:0;">
		<?php esc_html_e( '"Last Attempt" instead shows the most recent time this webhook fired for real — e.g. when you actually published a post — and which event triggered it. It won\'t always show a pass/fail mark, because real triggers fire in the background without waiting to hear back; use "Send Test" if you want a definite yes/no.', 'headless-bridge-by-kjm' ); ?>
	</p>
</div>

<table class="widefat headlessbridge-webhooks-table" id="headlessbridge-webhooks-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'headless-bridge-by-kjm' ); ?></th>
			<th><?php esc_html_e( 'Triggers', 'headless-bridge-by-kjm' ); ?></th>
			<th><?php esc_html_e( 'URL', 'headless-bridge-by-kjm' ); ?></th>
			<th><?php esc_html_e( 'Enabled', 'headless-bridge-by-kjm' ); ?></th>
			<th><?php esc_html_e( 'Last Attempt', 'headless-bridge-by-kjm' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'headless-bridge-by-kjm' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $records ) ) : ?>
		<tr class="headlessbridge-webhooks-empty">
			<td colspan="6"><?php esc_html_e( 'No webhooks configured yet.', 'headless-bridge-by-kjm' ); ?></td>
		</tr>
		<?php else : ?>
			<?php foreach ( $records as $webhook ) : ?>
			<tr data-webhook-id="<?php echo esc_attr( $webhook['id'] ); ?>">
				<td><?php echo esc_html( $webhook['name'] ); ?></td>
				<td>
					<?php
					$labels = array_map(
						fn( $key ) => $all_triggers[ $key ] ?? $key,
						$webhook['triggers']
					);
					echo esc_html( implode( ', ', $labels ) );
					?>
				</td>
				<td class="headlessbridge-webhooks-url"><?php echo esc_html( $webhook['url'] ); ?></td>
				<td><?php echo $webhook['enabled'] ? esc_html__( 'Yes', 'headless-bridge-by-kjm' ) : esc_html__( 'No', 'headless-bridge-by-kjm' ); ?></td>
				<td>
					<?php if ( empty( $webhook['last_attempt'] ) ) : ?>
						<span class="headlessbridge-status--info"><?php esc_html_e( 'Never fired', 'headless-bridge-by-kjm' ); ?></span>
					<?php else : ?>
						<?php
						$attempt = $webhook['last_attempt'];
						$ok      = $attempt['ok'] ?? null;
						$status_class = true === $ok ? 'headlessbridge-status--pass' : ( false === $ok ? 'headlessbridge-status--fail' : 'headlessbridge-status--info' );
						?>
						<span class="<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $all_triggers[ $attempt['trigger'] ] ?? $attempt['trigger'] ); ?>
							&mdash; <?php echo esc_html( $attempt['time'] ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<button type="button" class="button button-small headlessbridge-webhook-edit"><?php esc_html_e( 'Edit', 'headless-bridge-by-kjm' ); ?></button>
					<button type="button" class="button button-small headlessbridge-webhook-test"><?php esc_html_e( 'Send Test', 'headless-bridge-by-kjm' ); ?></button>
					<button type="button" class="button button-small button-link-delete headlessbridge-webhook-delete"><?php esc_html_e( 'Delete', 'headless-bridge-by-kjm' ); ?></button>
					<div class="headlessbridge-webhook-test-result"></div>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<p>
	<button type="button" class="button button-primary" id="headlessbridge-webhook-quick-setup"><?php esc_html_e( 'Quick Setup: Revalidate My Frontend', 'headless-bridge-by-kjm' ); ?></button>
	<button type="button" class="button button-secondary" id="headlessbridge-webhook-add"><?php esc_html_e( 'Add Custom Webhook', 'headless-bridge-by-kjm' ); ?></button>
</p>
<p class="description">
	<?php esc_html_e( 'Not sure which to pick? "Quick Setup" is for the common case — telling your Next.js/Nuxt/etc. frontend to refresh whenever content changes. It fills in everything except your frontend URL and secret. "Add Custom Webhook" is for anything else (Slack, Zapier, a different service).', 'headless-bridge-by-kjm' ); ?>
</p>

<div id="headlessbridge-webhook-form-wrap" class="headlessbridge-webhook-form-wrap" style="display:none;">
	<h3 id="headlessbridge-webhook-form-title"><?php esc_html_e( 'Add New Webhook', 'headless-bridge-by-kjm' ); ?></h3>

	<input type="hidden" id="headlessbridge-webhook-id" value="" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="headlessbridge-webhook-name"><?php esc_html_e( 'Name', 'headless-bridge-by-kjm' ); ?></label></th>
			<td>
				<input type="text" id="headlessbridge-webhook-name" class="regular-text" placeholder="<?php esc_attr_e( 'Frontend Revalidation', 'headless-bridge-by-kjm' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Triggers', 'headless-bridge-by-kjm' ); ?></th>
			<td>
				<div class="headlessbridge-trigger-grid">
					<?php foreach ( $all_triggers as $key => $label ) : ?>
						<label>
							<input type="checkbox" class="headlessbridge-webhook-trigger" value="<?php echo esc_attr( $key ); ?>" />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="headlessbridge-webhook-url"><?php esc_html_e( 'URL', 'headless-bridge-by-kjm' ); ?></label></th>
			<td>
				<input type="url" id="headlessbridge-webhook-url" class="regular-text"
					placeholder="<?php echo esc_attr( $webhooks->suggested_url() ?: 'https://example.com/api/revalidate' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="headlessbridge-webhook-secret"><?php esc_html_e( 'Secret', 'headless-bridge-by-kjm' ); ?></label></th>
			<td>
				<input type="password" id="headlessbridge-webhook-secret" class="regular-text" autocomplete="off" />
				<button type="button" class="button button-secondary" id="headlessbridge-webhook-secret-toggle"><?php esc_html_e( 'Show', 'headless-bridge-by-kjm' ); ?></button>
				<button type="button" class="button button-secondary" id="headlessbridge-webhook-secret-generate"><?php esc_html_e( 'Generate', 'headless-bridge-by-kjm' ); ?></button>
				<p class="description" id="headlessbridge-webhook-secret-note" style="display:none;">
					<?php esc_html_e( '(leave blank to keep the existing secret)', 'headless-bridge-by-kjm' ); ?>
				</p>
				<p class="description"><?php esc_html_e( 'A shared password so only WordPress can trigger this webhook. Click Generate, then copy the value into your frontend\'s matching setting (e.g. REVALIDATE_SECRET).', 'headless-bridge-by-kjm' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Payload', 'headless-bridge-by-kjm' ); ?></th>
			<td>
				<p class="description">
					<?php esc_html_e( 'The "payload" is just the message this webhook sends — a small block of text describing what changed. You do not need to understand it: the default below already works for revalidating a Next.js/Nuxt/etc. frontend. Only open "Advanced" if you’re sending to something else (like Slack) that expects a different message format.', 'headless-bridge-by-kjm' ); ?>
				</p>
				<details class="headlessbridge-payload-advanced">
					<summary><?php esc_html_e( 'Advanced: edit the payload template (optional)', 'headless-bridge-by-kjm' ); ?></summary>
					<textarea id="headlessbridge-webhook-payload" rows="4" class="large-text code">{"type":{{type}},"slug":{{slug}}}</textarea>
					<p class="description"><?php esc_html_e( 'Every trigger provides {{type}} and {{slug}}, plus extras like {{post_title}} or {{post_url}}. Tags already include quotes — write "slug":{{slug}}, not "slug":"{{slug}}".', 'headless-bridge-by-kjm' ); ?></p>
				</details>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enabled', 'headless-bridge-by-kjm' ); ?></th>
			<td>
				<label class="headlessbridge-toggle">
					<input type="checkbox" id="headlessbridge-webhook-enabled" checked="checked" />
					<span class="headlessbridge-toggle__slider"></span>
				</label>
			</td>
		</tr>
	</table>

	<p class="headlessbridge-webhook-form-error" id="headlessbridge-webhook-form-error" style="display:none;"></p>

	<button type="button" class="button button-primary" id="headlessbridge-webhook-save"><?php esc_html_e( 'Save Webhook', 'headless-bridge-by-kjm' ); ?></button>
	<button type="button" class="button button-secondary" id="headlessbridge-webhook-cancel"><?php esc_html_e( 'Cancel', 'headless-bridge-by-kjm' ); ?></button>
</div>
