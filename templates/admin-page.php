<?php
/**
 * Admin settings page template.
 *
 * @package KJMHCG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This is an included template — its top-level variables are locals scoped
// to the calling method, not real WordPress globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Resolve active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification

$tabs = [
	'general'  => __( 'General', 'kjm-headless-cms-gateway' ),
	'content'  => __( 'Content', 'kjm-headless-cms-gateway' ),
	'seo'      => __( 'SEO', 'kjm-headless-cms-gateway' ),
	'features' => __( 'Features', 'kjm-headless-cms-gateway' ),
	'api'      => __( 'API & CORS', 'kjm-headless-cms-gateway' ),
	'webhooks' => __( 'Webhooks', 'kjm-headless-cms-gateway' ),
	'health'   => __( 'Health', 'kjm-headless-cms-gateway' ),
	'tools'    => __( 'Tools', 'kjm-headless-cms-gateway' ),
];

// Notices.
if ( isset( $_GET['flushed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Permalink structure flushed.', 'kjm-headless-cms-gateway' ) . '</p></div>';
endif;
if ( isset( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings imported successfully.', 'kjm-headless-cms-gateway' ) . '</p></div>';
endif;
if ( isset( $_GET['import_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Import failed. Please upload a valid Headless CMS Gateway JSON file.', 'kjm-headless-cms-gateway' ) . '</p></div>';
endif;
if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings have been reset to defaults.', 'kjm-headless-cms-gateway' ) . '</p></div>';
endif;
if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'kjm-headless-cms-gateway' ) . '</p></div>';
endif;
?>

<div class="wrap kjmhcg-wrap">

	<div class="kjmhcg-header">
		<div class="kjmhcg-header-inner">
			<span class="kjmhcg-logo">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" width="36" height="36" aria-hidden="true">
					<circle cx="18" cy="18" r="18" fill="#1a1a2e"/>
					<path d="M10 12 L18 8 L26 12 L26 24 L18 28 L10 24 Z" fill="none" stroke="#4f46e5" stroke-width="2"/>
					<line x1="18" y1="8" x2="18" y2="28" stroke="#4f46e5" stroke-width="1" stroke-dasharray="2,2"/>
					<circle cx="18" cy="18" r="3" fill="#4f46e5"/>
				</svg>
			</span>
			<h1><?php esc_html_e( 'KJM Headless CMS Gateway', 'kjm-headless-cms-gateway' ); ?></h1>
			<span class="kjmhcg-version">v<?php echo esc_html( KJMHCG_VERSION ); ?></span>
			<?php if ( $settings->is_headless() ) : ?>
				<span class="kjmhcg-badge kjmhcg-badge--active"><?php esc_html_e( 'Headless Active', 'kjm-headless-cms-gateway' ); ?></span>
			<?php else : ?>
				<span class="kjmhcg-badge kjmhcg-badge--inactive"><?php esc_html_e( 'Headless Inactive', 'kjm-headless-cms-gateway' ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<nav class="kjmhcg-nav-tab-wrapper nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=kjm-headless-cms-gateway&tab=' . $slug ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="kjmhcg-tab-content">

		<?php if ( 'general' === $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'kjmhcg_general' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Headless Mode', 'kjm-headless-cms-gateway' ); ?></th>
					<td>
						<label class="kjmhcg-toggle">
							<input type="checkbox" name="kjmhcg_enabled" value="1"
								<?php checked( $settings->get( 'kjmhcg_enabled' ), '1' ); ?> />
							<span class="kjmhcg-toggle__slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Redirect all frontend requests to the external frontend. API, admin, and AJAX endpoints are always preserved.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="kjmhcg_frontend_url"><?php esc_html_e( 'Frontend URL', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<input type="url" id="kjmhcg_frontend_url" name="kjmhcg_frontend_url"
							value="<?php echo esc_attr( $settings->get( 'kjmhcg_frontend_url' ) ); ?>"
							class="regular-text" placeholder="https://plus233.com" />
						<p class="description"><?php esc_html_e( 'The URL of your Next.js, Nuxt, Astro, or other frontend. Example: https://plus233.com', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="kjmhcg_image_strategy"><?php esc_html_e( 'Image Optimization', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<?php
						$current_image_strategy = $settings->image_strategy();
						$image_strategy_options = [
							'native'      => __( 'Native (platform optimizer — default)', 'kjm-headless-cms-gateway' ),
							'sharp'       => __( 'Sharp (self-hosted, Node.js only)', 'kjm-headless-cms-gateway' ),
							'proxy'       => __( 'Free proxy (wsrv.nl)', 'kjm-headless-cms-gateway' ),
							'unoptimized' => __( 'Unoptimized (serve original files)', 'kjm-headless-cms-gateway' ),
						];
						?>
						<select id="kjmhcg_image_strategy" name="kjmhcg_image_strategy">
							<?php foreach ( $image_strategy_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_image_strategy, $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Controls how the frontend serves images. Native uses the hosting platform\'s built-in optimizer (Vercel/Cloudflare/etc. — today\'s default). Sharp routes images through the frontend\'s own resizer and only works if it\'s running on a plain Node.js server (not Vercel/Cloudflare Workers edge functions). Free proxy routes images through the free wsrv.nl image proxy instead. Unoptimized serves original files with no resizing or format conversion. Takes effect on the frontend\'s next cache refresh — no redeploy needed.', 'kjm-headless-cms-gateway' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Preserve Slugs', 'kjm-headless-cms-gateway' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kjmhcg_preserve_slugs" value="1"
								<?php checked( $settings->get( 'kjmhcg_preserve_slugs', '1' ), '1' ); ?> />
							<?php esc_html_e( 'Append request path to frontend URL on redirect.', 'kjm-headless-cms-gateway' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Example: /my-post on WordPress redirects to plus233.com/my-post', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="kjmhcg_post_path_prefix"><?php esc_html_e( 'Post Path Prefix', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<input type="text" id="kjmhcg_post_path_prefix" name="kjmhcg_post_path_prefix"
							value="<?php echo esc_attr( $settings->get( 'kjmhcg_post_path_prefix' ) ); ?>"
							class="regular-text" placeholder="post" />
						<p class="description"><?php esc_html_e( 'Optional path segment prepended to single-post redirects. Leave blank to redirect straight to the frontend root (e.g. plus233.com/my-post). Set to "post" to redirect to plus233.com/post/my-post instead. Only applies when Preserve Slugs is enabled.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Maintenance Mode', 'kjm-headless-cms-gateway' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kjmhcg_maintenance_mode" value="1"
								<?php checked( $settings->get( 'kjmhcg_maintenance_mode' ), '1' ); ?> />
							<?php esc_html_e( 'Show maintenance page instead of redirecting.', 'kjm-headless-cms-gateway' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Use when your frontend is temporarily down to avoid redirect loops.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'XML-RPC', 'kjm-headless-cms-gateway' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kjmhcg_xmlrpc_enabled" value="1"
								<?php checked( $settings->get( 'kjmhcg_xmlrpc_enabled', '1' ), '1' ); ?> />
							<?php esc_html_e( 'Keep XML-RPC enabled (recommended for Jetpack / mobile apps).', 'kjm-headless-cms-gateway' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save General Settings', 'kjm-headless-cms-gateway' ) ); ?>
		</form>

		<?php elseif ( 'content' === $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'kjmhcg_content' ); ?>
			<h2><?php esc_html_e( 'Frontend Content Mapping', 'kjm-headless-cms-gateway' ); ?></h2>
			<p class="description" style="margin-bottom:16px">
				<?php esc_html_e( 'Map your WordPress categories to slots on the frontend. Change categories here and the frontend follows — no code edits or redeploy needed.', 'kjm-headless-cms-gateway' ); ?>
			</p>
			<?php
			$content_categories = get_categories( [ 'hide_empty' => false, 'orderby' => 'name' ] );
			$content_slugs      = wp_list_pluck( $content_categories, 'slug' );

			/**
			 * Render a drag-to-order checkbox picker of categories, bound to a
			 * hidden field that stores the checked slugs newline-separated in the
			 * list's current order (admin.js keeps it in sync on check/drag).
			 * Checked items are listed first in their saved order, then the rest.
			 */
			$render_category_picker = function ( $field_name, $saved_value, $all_categories ) {
				$saved   = array_filter( array_map( 'trim', explode( "\n", (string) $saved_value ) ) );
				$by_slug = [];
				foreach ( $all_categories as $cat ) {
					$by_slug[ $cat->slug ] = $cat;
				}
				$ordered = [];
				foreach ( $saved as $slug ) {
					if ( isset( $by_slug[ $slug ] ) ) {
						$ordered[ $slug ] = $by_slug[ $slug ];
					}
				}
				foreach ( $all_categories as $cat ) {
					if ( ! isset( $ordered[ $cat->slug ] ) ) {
						$ordered[ $cat->slug ] = $cat;
					}
				}

				if ( empty( $ordered ) ) {
					echo '<p class="description">' . esc_html__( 'No categories found yet. Create some in Posts → Categories.', 'kjm-headless-cms-gateway' ) . '</p>';
					printf( '<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />', esc_attr( $field_name ), esc_attr( $saved_value ) );
					return;
				}
				?>
				<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>"
					id="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $saved_value ); ?>" />
				<ul class="kjmhcg-cat-picker" data-target="<?php echo esc_attr( $field_name ); ?>">
					<?php foreach ( $ordered as $slug => $cat ) : ?>
						<li class="kjmhcg-cat-item">
							<span class="kjmhcg-cat-handle" aria-hidden="true">&#9776;</span>
							<label>
								<input type="checkbox" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $saved, true ) ); ?> />
								<span class="kjmhcg-cat-name"><?php echo esc_html( $cat->name ); ?></span>
								<code class="kjmhcg-cat-slug"><?php echo esc_html( $slug ); ?></code>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
			};
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="kjmhcg_home_category"><?php esc_html_e( 'Homepage Category', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<?php $current_home_category = $settings->get( 'kjmhcg_home_category' ); ?>
						<select id="kjmhcg_home_category" name="kjmhcg_home_category">
							<option value="" <?php selected( $current_home_category, '' ); ?>>
								<?php esc_html_e( '— Most recent posts (all categories) —', 'kjm-headless-cms-gateway' ); ?>
							</option>
							<?php foreach ( $content_categories as $content_category ) : ?>
								<option value="<?php echo esc_attr( $content_category->slug ); ?>" <?php selected( $current_home_category, $content_category->slug ); ?>>
									<?php echo esc_html( $content_category->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Posts in this category fill the homepage feed. If it is empty or unset, the homepage falls back to the most recent posts across all categories, so it is never blank.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Navigation Menu', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<?php
						// Parse the saved menu tokens into an ordered list, then append
						// any categories not already included (shown unchecked).
						$menu_saved   = $settings->get( 'kjmhcg_menu_items' );
						$menu_lines   = array_filter( array_map( 'trim', explode( "\n", (string) $menu_saved ) ) );
						$menu_checked = [];
						$menu_order   = [];
						foreach ( $menu_lines as $menu_line ) {
							if ( 0 === strpos( $menu_line, 'category:' ) ) {
								$menu_slug                  = substr( $menu_line, strlen( 'category:' ) );
								$menu_order[]               = [ 'type' => 'category', 'slug' => $menu_slug ];
								$menu_checked[ $menu_slug ] = true;
							} elseif ( 0 === strpos( $menu_line, 'link:' ) ) {
								$menu_rest = substr( $menu_line, strlen( 'link:' ) );
								$menu_pos  = strpos( $menu_rest, '|' );
								if ( false !== $menu_pos ) {
									$menu_order[] = [ 'type' => 'link', 'label' => substr( $menu_rest, 0, $menu_pos ), 'url' => substr( $menu_rest, $menu_pos + 1 ) ];
								}
							}
						}
						$menu_by_slug = [];
						foreach ( $content_categories as $menu_cat ) {
							$menu_by_slug[ $menu_cat->slug ] = $menu_cat;
						}
						foreach ( $content_categories as $menu_cat ) {
							if ( ! isset( $menu_checked[ $menu_cat->slug ] ) ) {
								$menu_order[] = [ 'type' => 'category', 'slug' => $menu_cat->slug, 'unchecked' => true ];
							}
						}
						?>
						<input type="hidden" name="kjmhcg_menu_items" id="kjmhcg_menu_items" value="<?php echo esc_attr( $menu_saved ); ?>" />
						<div class="kjmhcg-menu-builder" data-target="kjmhcg_menu_items">
							<ul class="kjmhcg-cat-picker hb-menu-list">
								<?php foreach ( $menu_order as $menu_item ) : ?>
									<?php if ( 'category' === $menu_item['type'] ) : ?>
										<?php $menu_cat = $menu_by_slug[ $menu_item['slug'] ] ?? null; ?>
										<?php if ( $menu_cat ) : ?>
											<li class="kjmhcg-cat-item hb-menu-item" data-type="category" data-slug="<?php echo esc_attr( $menu_cat->slug ); ?>">
												<span class="kjmhcg-cat-handle" aria-hidden="true">&#9776;</span>
												<label>
													<input type="checkbox" <?php checked( empty( $menu_item['unchecked'] ) ); ?> />
													<span class="kjmhcg-cat-name"><?php echo esc_html( $menu_cat->name ); ?></span>
													<code class="kjmhcg-cat-slug"><?php echo esc_html( $menu_cat->slug ); ?></code>
												</label>
											</li>
										<?php endif; ?>
									<?php else : ?>
										<li class="kjmhcg-cat-item hb-menu-item" data-type="link">
											<span class="kjmhcg-cat-handle" aria-hidden="true">&#9776;</span>
											<input type="text" class="hb-link-label" placeholder="<?php esc_attr_e( 'Label', 'kjm-headless-cms-gateway' ); ?>" value="<?php echo esc_attr( $menu_item['label'] ); ?>" />
											<input type="text" class="hb-link-url" placeholder="<?php esc_attr_e( '/about or https://…', 'kjm-headless-cms-gateway' ); ?>" value="<?php echo esc_attr( $menu_item['url'] ); ?>" />
											<button type="button" class="button-link hb-link-remove" aria-label="<?php esc_attr_e( 'Remove link', 'kjm-headless-cms-gateway' ); ?>">&times;</button>
										</li>
									<?php endif; ?>
								<?php endforeach; ?>
							</ul>
							<button type="button" class="button button-secondary hb-add-link">+ <?php esc_html_e( 'Add link', 'kjm-headless-cms-gateway' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Check the categories to show in the nav menu, add custom links (label + URL — e.g. /about or an external site), and drag everything into the order it should appear. Leave all unchecked with no links to automatically list every category. A category with no published posts stays hidden until it has one.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="kjmhcg_homepage_sections"><?php esc_html_e( 'Homepage Sections', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<?php $render_category_picker( 'kjmhcg_homepage_sections', $settings->get( 'kjmhcg_homepage_sections' ), $content_categories ); ?>
						<p class="description"><?php esc_html_e( 'Check the categories to feature as homepage sections, and drag them into order. Each becomes its own section of that category\'s latest posts, above the main "Latest" feed. Leave all unchecked to auto-pick the top categories by post count.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Content Settings', 'kjm-headless-cms-gateway' ) ); ?>
		</form>

		<?php elseif ( 'seo' === $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'kjmhcg_seo' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Add Noindex Header', 'kjm-headless-cms-gateway' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kjmhcg_noindex" value="1"
								<?php checked( $settings->get( 'kjmhcg_noindex' ), '1' ); ?> />
							<?php esc_html_e( 'Send X-Robots-Tag: noindex, nofollow on all WordPress responses.', 'kjm-headless-cms-gateway' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Prevents search engines from indexing the WordPress backend URL, since content is served by the frontend.', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Override robots.txt', 'kjm-headless-cms-gateway' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kjmhcg_robots_txt" value="1"
								<?php checked( $settings->get( 'kjmhcg_robots_txt' ), '1' ); ?> />
							<?php esc_html_e( 'Replace WordPress robots.txt with "Disallow: /" and a sitemap pointer to the frontend.', 'kjm-headless-cms-gateway' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save SEO Settings', 'kjm-headless-cms-gateway' ) ); ?>
		</form>

		<?php elseif ( 'features' === $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'kjmhcg_features' ); ?>
			<p class="description" style="margin-bottom:16px"><?php esc_html_e( 'These toggles are applied only when Headless Mode is active.', 'kjm-headless-cms-gateway' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$feature_options = [
					'kjmhcg_disable_rss'             => __( 'Disable RSS / Feeds', 'kjm-headless-cms-gateway' ),
					'kjmhcg_disable_search'          => __( 'Disable Frontend Search', 'kjm-headless-cms-gateway' ),
					'kjmhcg_disable_comments'        => __( 'Disable Comments', 'kjm-headless-cms-gateway' ),
					'kjmhcg_disable_author_archives' => __( 'Disable Author Archives', 'kjm-headless-cms-gateway' ),
					'kjmhcg_disable_date_archives'   => __( 'Disable Date Archives', 'kjm-headless-cms-gateway' ),
				];
				foreach ( $feature_options as $key => $label ) :
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1"
								<?php checked( $settings->get( $key ), '1' ); ?> />
							<?php esc_html_e( 'Enable', 'kjm-headless-cms-gateway' ); ?>
						</label>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Save Feature Settings', 'kjm-headless-cms-gateway' ) ); ?>
		</form>

		<?php elseif ( 'api' === $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'kjmhcg_api' ); ?>
			<h2><?php esc_html_e( 'CORS Settings', 'kjm-headless-cms-gateway' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="kjmhcg_allowed_origins"><?php esc_html_e( 'Allowed Origins', 'kjm-headless-cms-gateway' ); ?></label>
					</th>
					<td>
						<textarea id="kjmhcg_allowed_origins" name="kjmhcg_allowed_origins"
							rows="6" class="large-text code"
							placeholder="https://plus233.com&#10;https://app.plus233.com"><?php echo esc_textarea( $settings->get( 'kjmhcg_allowed_origins' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line. These domains will receive Access-Control-Allow-Origin headers. Enter * on its own line to allow all origins (not recommended for production).', 'kjm-headless-cms-gateway' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="kjmhcg-info-box">
				<strong><?php esc_html_e( 'Headers sent for allowed origins:', 'kjm-headless-cms-gateway' ); ?></strong>
				<pre>Access-Control-Allow-Origin: &lt;origin&gt;
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With</pre>
			</div>
			<?php submit_button( __( 'Save CORS Settings', 'kjm-headless-cms-gateway' ) ); ?>
		</form>

		<?php elseif ( 'webhooks' === $active_tab ) : ?>
		<?php include KJMHCG_PLUGIN_DIR . 'templates/webhooks-tab.php'; ?>

		<?php elseif ( 'health' === $active_tab ) : ?>
		<div class="kjmhcg-health">
			<p><?php esc_html_e( 'Health checks verify that WordPress API endpoints and your frontend are reachable. Results are cached for 5 minutes.', 'kjm-headless-cms-gateway' ); ?></p>
			<p>
				<button type="button" id="kjmhcg-run-check" class="button button-primary">
					<?php esc_html_e( 'Run Check', 'kjm-headless-cms-gateway' ); ?>
				</button>
				<button type="button" id="kjmhcg-clear-cache" class="button button-secondary">
					<?php esc_html_e( 'Clear Cache', 'kjm-headless-cms-gateway' ); ?>
				</button>
			</p>
			<div id="kjmhcg-health-results">
				<?php
				$results = $health->get_cached_results();
				include KJMHCG_PLUGIN_DIR . 'templates/health-widget.php';
				?>
			</div>
		</div>

		<?php elseif ( 'tools' === $active_tab ) : ?>
		<div class="kjmhcg-tools">

			<div class="kjmhcg-tool-card">
				<h3><?php esc_html_e( 'Test Frontend', 'kjm-headless-cms-gateway' ); ?></h3>
				<p><?php esc_html_e( 'Open your configured frontend URL in a new tab.', 'kjm-headless-cms-gateway' ); ?></p>
				<?php $frontend_url = $settings->frontend_url(); ?>
				<?php if ( $frontend_url ) : ?>
					<a href="<?php echo esc_url( $frontend_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
						<?php esc_html_e( 'Open Frontend', 'kjm-headless-cms-gateway' ); ?>
					</a>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No frontend URL configured.', 'kjm-headless-cms-gateway' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="kjmhcg-tool-card">
				<h3><?php esc_html_e( 'Flush Permalinks', 'kjm-headless-cms-gateway' ); ?></h3>
				<p><?php esc_html_e( 'Regenerate WordPress rewrite rules. Run after changing settings.', 'kjm-headless-cms-gateway' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="kjmhcg_flush_permalinks" />
					<?php wp_nonce_field( 'kjmhcg_tools_nonce' ); ?>
					<?php submit_button( __( 'Flush Permalinks', 'kjm-headless-cms-gateway' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="kjmhcg-tool-card">
				<h3><?php esc_html_e( 'Export Settings', 'kjm-headless-cms-gateway' ); ?></h3>
				<p><?php esc_html_e( 'Download all plugin settings as a JSON file.', 'kjm-headless-cms-gateway' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="kjmhcg_export_settings" />
					<?php wp_nonce_field( 'kjmhcg_tools_nonce' ); ?>
					<?php submit_button( __( 'Export Settings', 'kjm-headless-cms-gateway' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="kjmhcg-tool-card">
				<h3><?php esc_html_e( 'Import Settings', 'kjm-headless-cms-gateway' ); ?></h3>
				<p><?php esc_html_e( 'Upload a Headless CMS Gateway JSON settings file to restore settings.', 'kjm-headless-cms-gateway' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="kjmhcg_import_settings" />
					<?php wp_nonce_field( 'kjmhcg_tools_nonce' ); ?>
					<input type="file" name="kjmhcg_import_file" accept=".json" style="margin-bottom:8px;display:block;" />
					<?php submit_button( __( 'Import Settings', 'kjm-headless-cms-gateway' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="kjmhcg-tool-card kjmhcg-tool-card--danger">
				<h3><?php esc_html_e( 'Reset Settings', 'kjm-headless-cms-gateway' ); ?></h3>
				<p><?php esc_html_e( 'Restore all Headless CMS Gateway settings to their factory defaults. This cannot be undone.', 'kjm-headless-cms-gateway' ); ?></p>
				<?php if ( isset( $_GET['reset_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<p class="kjmhcg-status--fail"><?php esc_html_e( 'Incorrect password. Settings were not reset.', 'kjm-headless-cms-gateway' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="kjmhcg-reset-form">
					<input type="hidden" name="action" value="kjmhcg_reset_settings" />
					<?php wp_nonce_field( 'kjmhcg_tools_nonce' ); ?>
					<input type="hidden" name="kjmhcg_reset_password" id="kjmhcg-reset-password-hidden" value="" />
					<button type="button" class="button button-link-delete" id="kjmhcg-reset-open">
						<?php esc_html_e( 'Reset Settings', 'kjm-headless-cms-gateway' ); ?>
					</button>
				</form>
			</div>

			<div id="kjmhcg-reset-modal-overlay" class="kjmhcg-modal-overlay" style="display:none;">
				<div class="kjmhcg-modal" role="dialog" aria-modal="true" aria-labelledby="kjmhcg-reset-modal-title">
					<h3 id="kjmhcg-reset-modal-title"><?php esc_html_e( 'Confirm Reset', 'kjm-headless-cms-gateway' ); ?></h3>
					<p><?php esc_html_e( 'This will restore all Headless CMS Gateway settings — including every configured webhook — to their factory defaults. This cannot be undone.', 'kjm-headless-cms-gateway' ); ?></p>
					<label for="kjmhcg-reset-password"><?php esc_html_e( 'Enter your account password to confirm:', 'kjm-headless-cms-gateway' ); ?></label>
					<input type="password" id="kjmhcg-reset-password" class="regular-text" autocomplete="current-password" />
					<p class="kjmhcg-status--fail" id="kjmhcg-reset-modal-error" style="display:none;">
						<?php esc_html_e( 'Please enter your password.', 'kjm-headless-cms-gateway' ); ?>
					</p>
					<p class="kjmhcg-modal-actions">
						<button type="button" class="button button-secondary" id="kjmhcg-reset-cancel"><?php esc_html_e( 'Cancel', 'kjm-headless-cms-gateway' ); ?></button>
						<button type="button" class="button button-primary kjmhcg-modal-danger" id="kjmhcg-reset-confirm"><?php esc_html_e( 'Reset Settings', 'kjm-headless-cms-gateway' ); ?></button>
					</p>
				</div>
			</div>

		</div>
		<?php endif; ?>

	</div><!-- .kjmhcg-tab-content -->

	<div class="kjmhcg-footer">
		<p>
			<?php
			printf(
				/* translators: 1: plugin name, 2: author link */
				esc_html__( '%1$s — crafted by %2$s', 'kjm-headless-cms-gateway' ),
				'<strong>KJM Headless CMS Gateway</strong>',
				'<a href="https://kwekujasper.com" target="_blank" rel="noopener noreferrer">Kweku Jasper Media</a>'
			);
			?>
		</p>
	</div>

</div><!-- .kjmhcg-wrap -->
