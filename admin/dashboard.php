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

	$range            = cmp_get_dashboard_range();
	$metrics          = cmp_get_dashboard_metrics( $range );
	$recent_students  = cmp_get_students( array( 'limit' => 10 ) );
	$pending_students = cmp_get_students_with_pending_fees( 8 );
	$admin_logs       = cmp_get_admin_logs( 8 );
	$chart_data       = array(
		'dashboardRevenue' => cmp_get_monthly_revenue( 6 ),
		'studentStatus'    => cmp_get_student_status_counts(),
	);

	wp_add_inline_script( 'cmp-admin', 'window.CMPCharts = ' . wp_json_encode( $chart_data ) . ';', 'before' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Class Manager Pro', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<form method="get" class="cmp-toolbar">
			<input type="hidden" name="page" value="cmp-dashboard">
			<select name="range">
				<option value="today" <?php selected( $range, 'today' ); ?>><?php esc_html_e( 'Today', 'class-manager-pro' ); ?></option>
				<option value="week" <?php selected( $range, 'week' ); ?>><?php esc_html_e( 'This Week', 'class-manager-pro' ); ?></option>
				<option value="month" <?php selected( $range, 'month' ); ?>><?php esc_html_e( 'This Month', 'class-manager-pro' ); ?></option>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filter', 'class-manager-pro' ); ?></button>
		</form>

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
				<span><?php echo esc_html( sprintf( __( 'Students (%s)', 'class-manager-pro' ), $metrics['range_label'] ) ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['filtered_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php echo esc_html( sprintf( __( 'Revenue (%s)', 'class-manager-pro' ), $metrics['range_label'] ) ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['filtered_revenue'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Revenue', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['total_revenue'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fees'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Expense', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['total_expense'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Net Income', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['net_income'] ) ); ?></strong>
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
						<th><?php esc_html_e( 'Payment Status', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_students ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No students yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent_students as $student ) : ?>
							<?php $payment_status = cmp_get_student_payment_status( $student ); ?>
							<tr>
								<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
								<td><?php echo esc_html( $student->phone ); ?></td>
								<td><?php echo esc_html( $student->class_name ); ?></td>
								<td><?php echo esc_html( $student->batch_name ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></td>
								<td><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></td>
								<td><span class="cmp-status cmp-status-<?php echo esc_attr( $student->status ); ?>"><?php echo esc_html( ucfirst( $student->status ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Students With Pending Fees', 'class-manager-pro' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $pending_students ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No pending follow-ups right now.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $pending_students as $student ) : ?>
							<?php
							$follow_up_url = cmp_get_email_reminder_url( $student );

							if ( ! $follow_up_url ) {
								$follow_up_url = cmp_get_whatsapp_reminder_url( $student );
							}

							if ( ! $follow_up_url ) {
								$follow_up_url = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
							}
							?>
							<tr>
								<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
								<td><?php echo esc_html( $student->class_name ); ?></td>
								<td><?php echo esc_html( $student->batch_name ); ?></td>
								<td><?php echo esc_html( cmp_format_money( (float) $student->total_fee - (float) $student->paid_fee ) ); ?></td>
								<td><?php echo esc_html( $student->fee_due_date ? $student->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $follow_up_url ); ?>"<?php echo false !== strpos( $follow_up_url, 'https://wa.me/' ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php esc_html_e( 'Follow Up', 'class-manager-pro' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Admin Activity Log', 'class-manager-pro' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Admin', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Details', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $admin_logs ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No admin activity recorded yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $admin_logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td><?php echo esc_html( $log->admin_name ? $log->admin_name : __( 'System', 'class-manager-pro' ) ); ?></td>
								<td><?php echo esc_html( cmp_get_admin_log_action_label( $log->action ) ); ?></td>
								<td><?php echo esc_html( $log->message ? $log->message : sprintf( __( '%1$s #%2$d', 'class-manager-pro' ), ucfirst( $log->object_type ), (int) $log->object_id ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	</div>
	<?php
}
