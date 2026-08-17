<?php
/**
 * Health check results partial — used in dashboard widget and the Health tab.
 *
 * Expects: $results array from Health::get_cached_results() or Health::run_checks().
 *
 * @package KJMHCG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This is an included template — its top-level variables are locals scoped
// to the calling method, not real WordPress globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$check_labels = [
	'wp_api'   => __( 'WordPress REST API', 'kjm-headless-cms-gateway' ),
	'graphql'  => __( 'GraphQL Endpoint', 'kjm-headless-cms-gateway' ),
	'frontend' => __( 'Frontend Reachability', 'kjm-headless-cms-gateway' ),
	'cors'     => __( 'CORS Configuration', 'kjm-headless-cms-gateway' ),
	'plugin'   => __( 'Plugin Status', 'kjm-headless-cms-gateway' ),
];
?>
<div class="kjmhcg-health-grid">
	<?php foreach ( $check_labels as $key => $label ) :
		if ( ! isset( $results[ $key ] ) ) continue;
		$check  = $results[ $key ];
		$ok     = $check['ok'];
		$detail = $check['detail'];

		if ( true === $ok ) {
			$status_class = 'kjmhcg-status--pass';
			$status_icon  = '✓';
			$status_text  = __( 'Pass', 'kjm-headless-cms-gateway' );
		} elseif ( false === $ok ) {
			$status_class = 'kjmhcg-status--fail';
			$status_icon  = '✗';
			$status_text  = __( 'Fail', 'kjm-headless-cms-gateway' );
		} else {
			$status_class = 'kjmhcg-status--info';
			$status_icon  = '●';
			$status_text  = __( 'Info', 'kjm-headless-cms-gateway' );
		}
	?>
	<div class="kjmhcg-health-item">
		<span class="kjmhcg-health-label"><?php echo esc_html( $label ); ?></span>
		<span class="kjmhcg-health-status <?php echo esc_attr( $status_class ); ?>">
			<span class="kjmhcg-status-icon" aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
			<?php echo esc_html( $status_text ); ?>
		</span>
		<span class="kjmhcg-health-detail"><?php echo esc_html( $detail ); ?></span>
	</div>
	<?php endforeach; ?>
</div>
<?php if ( ! empty( $results['checked_at'] ) ) : ?>
<p class="kjmhcg-health-timestamp">
	<?php
	printf(
		/* translators: %s: datetime string */
		esc_html__( 'Last checked: %s', 'kjm-headless-cms-gateway' ),
		esc_html( $results['checked_at'] )
	);
	?>
</p>
<?php endif; ?>
