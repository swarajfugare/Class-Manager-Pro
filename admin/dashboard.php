<?php
/**
 * Dashboard admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the dashboard page.
 */
function cmp_render_dashboard_page() {
	cmp_require_manage_options();

	$metrics         = cmp_get_dashboard_metrics();
	$recent_students = cmp_get_students( array( 'limit' => 10 ) );
	$chart_data      = array(
		'dashboardRevenue' => cmp_get_monthly_revenue( 6 ),
		'studentStatus'    => cmp_get_student_status_counts(),
	);

	wp_add_inline_script( 'cmp-admin', 'window.CMPCharts = ' . wp_json_encode( $chart_data ) . ';', 'before' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Class Manager Pro', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<div class="cmp-cards">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Classes', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_classes'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_batches'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Revenue', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['total_revenue'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fees'] ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Monthly Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart"><canvas id="cmp-dashboard-revenue"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Student Status', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart"><canvas id="cmp-dashboard-status"></canvas></div>
			</section>
		</div>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Recent Students', 'class-manager-pro' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_students ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No students yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent_students as $student ) : ?>
							<tr>
								<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
								<td><?php echo esc_html( $student->phone ); ?></td>
								<td><?php echo esc_html( $student->class_name ); ?></td>
								<td><?php echo esc_html( $student->batch_name ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></td>
								<td><span class="cmp-status cmp-status-<?php echo esc_attr( $student->status ); ?>"><?php echo esc_html( ucfirst( $student->status ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	</div>
	<?php
}
