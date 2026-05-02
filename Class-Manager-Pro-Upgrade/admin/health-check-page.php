<?php
/**
 * Health Check Page
 *
 * @package ClassManagerPro
 */

function cmp_render_health_check_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'class-manager-pro' ) );
	}

	$results = CMP_Health_Check::get_results();
	?>
	<div class="wrap cmp-admin cmp-health-check">
		<h1><?php esc_html_e( 'Health Check', 'class-manager-pro' ); ?></h1>

		<div class="cmp-health-summary">
			<div class="cmp-health-card cmp-health-critical">
				<span class="dashicons dashicons-warning"></span>
				<div class="cmp-health-number"><?php echo esc_html( $results['critical'] ); ?></div>
				<div class="cmp-health-label"><?php esc_html_e( 'Critical', 'class-manager-pro' ); ?></div>
			</div>
			<div class="cmp-health-card cmp-health-warning">
				<span class="dashicons dashicons-flag"></span>
				<div class="cmp-health-number"><?php echo esc_html( $results['warning'] ); ?></div>
				<div class="cmp-health-label"><?php esc_html_e( 'Warnings', 'class-manager-pro' ); ?></div>
			</div>
			<div class="cmp-health-card cmp-health-info">
				<span class="dashicons dashicons-info"></span>
				<div class="cmp-health-number"><?php echo esc_html( $results['info'] ); ?></div>
				<div class="cmp-health-label"><?php esc_html_e( 'Info', 'class-manager-pro' ); ?></div>
			</div>
			<div class="cmp-health-card cmp-health-total">
				<span class="dashicons dashicons-chart-pie"></span>
				<div class="cmp-health-number"><?php echo esc_html( $results['total'] ); ?></div>
				<div class="cmp-health-label"><?php esc_html_e( 'Total Issues', 'class-manager-pro' ); ?></div>
			</div>
		</div>

		<p>
			<button type="button" id="cmp-run-health-scan" class="button button-primary">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Run New Scan', 'class-manager-pro' ); ?>
			</button>
			<span class="cmp-last-scan"><?php printf( esc_html__( 'Last scan: %s', 'class-manager-pro' ), esc_html( $results['timestamp'] ) ); ?></span>
		</p>

		<div id="cmp-health-issues" class="cmp-health-issues">
			<?php if ( empty( $results['issues'] ) ) : ?>
				<div class="cmp-notice cmp-notice-success">
					<p><?php esc_html_e( 'All systems are healthy! No issues found.', 'class-manager-pro' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Severity', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Issue', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $results['issues'] as $issue ) : ?>
							<tr data-type="<?php echo esc_attr( $issue['type'] ); ?>" data-id="<?php echo esc_attr( $issue['id'] ); ?>">
								<td>
									<span class="cmp-badge cmp-badge-<?php echo esc_attr( $issue['severity'] ); ?>">
										<?php echo esc_html( ucfirst( $issue['severity'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $issue['message'] ); ?></td>
								<td>
									<?php if ( 'none' !== $issue['action'] ) : ?>
										<button type="button" class="button button-small cmp-repair-btn" data-details="<?php echo esc_attr( wp_json_encode( $issue['details'] ) ); ?>">
											<?php esc_html_e( 'Repair', 'class-manager-pro' ); ?>
										</button>
									<?php else : ?>
										<span class="cmp-badge cmp-badge-info"><?php esc_html_e( 'No action', 'class-manager-pro' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
