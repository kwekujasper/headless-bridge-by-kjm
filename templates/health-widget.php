<?php
/**
 * Health check results partial — used in dashboard widget and the Health tab.
 *
 * Expects: $results array from Health::get_cached_results() or Health::run_checks().
 *
 * @package HeadlessBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$check_labels = [
	'wp_api'   => __( 'WordPress REST API', 'headless-bridge-by-kjm' ),
	'graphql'  => __( 'GraphQL Endpoint', 'headless-bridge-by-kjm' ),
	'frontend' => __( 'Frontend Reachability', 'headless-bridge-by-kjm' ),
	'cors'     => __( 'CORS Configuration', 'headless-bridge-by-kjm' ),
	'plugin'   => __( 'Plugin Status', 'headless-bridge-by-kjm' ),
];
?>
<div class="headlessbridge-health-grid">
	<?php foreach ( $check_labels as $key => $label ) :
		if ( ! isset( $results[ $key ] ) ) continue;
		$check  = $results[ $key ];
		$ok     = $check['ok'];
		$detail = $check['detail'];

		if ( true === $ok ) {
			$status_class = 'headlessbridge-status--pass';
			$status_icon  = '✓';
			$status_text  = __( 'Pass', 'headless-bridge-by-kjm' );
		} elseif ( false === $ok ) {
			$status_class = 'headlessbridge-status--fail';
			$status_icon  = '✗';
			$status_text  = __( 'Fail', 'headless-bridge-by-kjm' );
		} else {
			$status_class = 'headlessbridge-status--info';
			$status_icon  = '●';
			$status_text  = __( 'Info', 'headless-bridge-by-kjm' );
		}
	?>
	<div class="headlessbridge-health-item">
		<span class="headlessbridge-health-label"><?php echo esc_html( $label ); ?></span>
		<span class="headlessbridge-health-status <?php echo esc_attr( $status_class ); ?>">
			<span class="headlessbridge-status-icon" aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
			<?php echo esc_html( $status_text ); ?>
		</span>
		<span class="headlessbridge-health-detail"><?php echo esc_html( $detail ); ?></span>
	</div>
	<?php endforeach; ?>
</div>
<?php if ( ! empty( $results['checked_at'] ) ) : ?>
<p class="headlessbridge-health-timestamp">
	<?php
	printf(
		/* translators: %s: datetime string */
		esc_html__( 'Last checked: %s', 'headless-bridge-by-kjm' ),
		esc_html( $results['checked_at'] )
	);
	?>
</p>
<?php endif; ?>
