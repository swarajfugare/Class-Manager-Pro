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

	$range                  = cmp_get_dashboard_range();
	$dashboard              = cmp_get_dashboard_snapshot( $range );
	$metrics                = $dashboard['metrics'];
	$pending_students       = $dashboard['pending_students'];
	$recent_students        = $dashboard['recent_students'];
	$course_completion_rows = $dashboard['course_completion_rows'];
	$batch_performance_rows = $dashboard['batch_performance_rows'];
	$class_revenue_rows     = $dashboard['class_revenue_rows'];
	$teacher_rows           = $dashboard['teacher_rows'];
	$interested_count       = function_exists( 'cmp_get_interested_students_count' ) ? cmp_get_interested_students_count() : 0;
	$batch_alert_rows       = array_slice(
		array_filter(
			$batch_performance_rows,
			static function ( $row ) {
				return (int) $row->pending_students > 0 || (float) $row->payment_completion < 70;
			}
		),
		0,
		4
	);
	$teacher_alert_rows     = array_slice(
		array_filter(
			$teacher_rows,
			static function ( $row ) {
				return (int) $row->pending_students > 0 || (int) $row->interested_students > 0;
			}
		),
		0,
		4
	);

	global $wpdb;
	$today_start             = current_time( 'Y-m-d 00:00:00' );
	$today_admissions        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . cmp_table( 'students' ) . " WHERE created_at >= %s", $today_start ) );
	$today_revenue           = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM " . cmp_table( 'payments' ) . " WHERE is_deleted = 0 AND (payment_date >= %s OR created_at >= %s)", $today_start, $today_start ) );
	$pending_utr_count       = (int) $wpdb->get_var( "SELECT COUNT(id) FROM " . cmp_table( 'students' ) . " WHERE notes LIKE '%[TEMP_LINK_UTR:%' AND notes NOT LIKE '%[APPROVED_UTR:%'" );
	$completed_batches_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM " . cmp_table( 'batches' ) . " WHERE status = 'completed'" );

	wp_add_inline_script(
		'cmp-admin',
		'window.CMPCharts = Object.assign({}, window.CMPCharts || {}, ' . wp_json_encode( $dashboard['chart_data'] ) . ');',
		'before'
	);
	?>
	<div class="wrap cmp-wrap cmp-dashboard-shell">
		<h1><?php esc_html_e( 'Class Manager Pro', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Start with the work that needs attention now, then drill into students, payments, teachers, and reports when needed.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-dashboard' ); ?>

		<form method="get" class="cmp-toolbar">
			<input type="hidden" name="page" value="cmp-dashboard">
			<select name="range">
				<option value="today" <?php selected( $range, 'today' ); ?>><?php esc_html_e( 'Today', 'class-manager-pro' ); ?></option>
				<option value="week" <?php selected( $range, 'week' ); ?>><?php esc_html_e( 'This Week', 'class-manager-pro' ); ?></option>
				<option value="month" <?php selected( $range, 'month' ); ?>><?php esc_html_e( 'This Month', 'class-manager-pro' ); ?></option>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filter', 'class-manager-pro' ); ?></button>
		</form>

		<?php if ( $pending_utr_count > 0 ) : ?>
			<div class="cmp-panel" style="margin: 16px 0; border-left: 4px solid #f59e0b; background: #fffbeb; padding: 14px 18px; border-radius: 8px;">
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
					<div style="display:flex; align-items:center; gap:12px;">
						<span style="font-size:24px;">💳</span>
						<div>
							<strong style="color:#92400e; font-size:14px;"><?php echo esc_html( sprintf( _n( '%d Admission awaiting Payment UTR Approval', '%d Admissions awaiting Payment UTR Approval', $pending_utr_count, 'class-manager-pro' ), $pending_utr_count ) ); ?></strong>
							<p style="margin:2px 0 0 0; color:#b45309; font-size:12px;"><?php esc_html_e( 'Students have submitted UPI / UTR numbers via temporary links and are waiting for verification.', 'class-manager-pro' ); ?></p>
						</div>
					</div>
					<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>" class="button button-primary" style="background:#d97706;border-color:#d97706;">
						<?php esc_html_e( 'Review & Approve Admissions →', 'class-manager-pro' ); ?>
					</a>
				</div>
			</div>
		<?php endif; ?>

		<section class="cmp-action-board">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Action Queue', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'The fastest path to today\'s admin work: collect pending fees, follow up leads, check teachers, and review batches.', 'class-manager-pro' ); ?></p>
				</div>
				<div class="cmp-toolbar">
					<a class="button button-primary" href="#cmp-pending-students-panel"><?php esc_html_e( 'Collect Pending Fees ↓', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments' ) . '#cmp-add-payment' ); ?>"><?php esc_html_e( 'Add Payment', 'class-manager-pro' ); ?></a>
				</div>
			</div>
			<div class="cmp-action-grid">
				<a class="cmp-action-card cmp-action-card-urgent" href="#cmp-pending-students-panel">
					<span><?php esc_html_e( 'Pending fees', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fees'] ) ); ?></strong>
					<small><?php echo esc_html( sprintf( __( '%d students need collection', 'class-manager-pro' ), (int) $metrics['pending_students'] ) ); ?></small>
				</a>
				<a class="cmp-action-card" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'tab' => 'interested' ) ) ); ?>">
					<span><?php esc_html_e( 'Follow-ups needed', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $interested_count ) ); ?></strong>
					<small><?php esc_html_e( 'Interested students and failed payment attempts', 'class-manager-pro' ); ?></small>
				</a>
				<a class="cmp-action-card" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console' ) ); ?>">
					<span><?php esc_html_e( 'Teacher alerts', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( count( $teacher_alert_rows ) ) ); ?></strong>
					<small><?php esc_html_e( 'Teachers with pending collections or leads', 'class-manager-pro' ); ?></small>
				</a>
				<a class="cmp-action-card" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>">
					<span><?php esc_html_e( 'Batch alerts', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( count( $batch_alert_rows ) ) ); ?></strong>
					<small><?php esc_html_e( 'Batches with collection risk', 'class-manager-pro' ); ?></small>
				</a>
				<a class="cmp-action-card" href="<?php echo esc_url( cmp_admin_url( 'cmp-students' ) ); ?>">
					<span><?php esc_html_e( 'Recent admissions', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( count( $recent_students ) ) ); ?></strong>
					<small><?php esc_html_e( 'Newest students ready for review', 'class-manager-pro' ); ?></small>
				</a>
			</div>
		</section>

		<div class="cmp-cards cmp-cards-4" style="margin: 18px 0;">
			<div class="cmp-card" style="border-top: 3px solid #3b82f6;">
				<span><?php esc_html_e( 'Today\'s New Admissions', 'class-manager-pro' ); ?></span>
				<strong style="color:#2563eb;"><?php echo esc_html( number_format_i18n( $today_admissions ) ); ?></strong>
				<small class="cmp-muted"><?php echo esc_html( wp_date( 'd M Y' ) ); ?></small>
			</div>
			<div class="cmp-card" style="border-top: 3px solid #10b981;">
				<span><?php esc_html_e( 'Today\'s Collections', 'class-manager-pro' ); ?></span>
				<strong style="color:#059669;"><?php echo esc_html( cmp_format_money( $today_revenue ) ); ?></strong>
				<small class="cmp-muted"><?php esc_html_e( 'Recorded today across all batches', 'class-manager-pro' ); ?></small>
			</div>
			<div class="cmp-card" style="border-top: 3px solid #8b5cf6;">
				<span><?php esc_html_e( 'Batches in Session', 'class-manager-pro' ); ?></span>
				<strong style="color:#7c3aed;"><?php echo esc_html( number_format_i18n( $metrics['active_batches'] ) ); ?></strong>
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( 'Out of %d total batches', 'class-manager-pro' ), (int) $metrics['total_batches'] ) ); ?></small>
			</div>
			<div class="cmp-card" style="border-top: 3px solid #f59e0b;">
				<span><?php esc_html_e( 'Batch Certificates Hub', 'class-manager-pro' ); ?></span>
				<strong style="color:#d97706;"><?php echo esc_html( number_format_i18n( $completed_batches_count ) ); ?></strong>
				<small class="cmp-muted">
					<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'status' => 'completed' ) ) ); ?>" style="text-decoration:none;font-weight:600;">
						<?php esc_html_e( 'Completed batches →', 'class-manager-pro' ); ?>
					</a>
				</small>
			</div>
		</div>

		<div class="cmp-cards cmp-dashboard-metrics">
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
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( '%d active students overall', 'class-manager-pro' ), (int) $metrics['active_students'] ) ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_students'] ) ); ?></strong>
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( '%d completed', 'class-manager-pro' ), (int) $metrics['completed_students'] ) ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Active Teachers', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_teachers'] ) ); ?></strong>
				<small class="cmp-muted"><?php esc_html_e( 'Assigned across active and completed batches', 'class-manager-pro' ); ?></small>
			</div>
				<div class="cmp-card">
					<span><?php echo esc_html( sprintf( __( 'Net Revenue (%s)', 'class-manager-pro' ), $metrics['range_label'] ) ); ?></span>
					<strong><?php echo esc_html( cmp_format_money( $metrics['filtered_revenue'] ) ); ?></strong>
					<small class="cmp-muted"><?php echo esc_html( sprintf( __( '%d payments recorded', 'class-manager-pro' ), (int) $metrics['filtered_payments'] ) ); ?></small>
				</div>
				<div class="cmp-card">
					<span><?php esc_html_e( 'Total Net Revenue', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( cmp_format_money( $metrics['total_revenue'] ) ); ?></strong>
				</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['pending_students'] ) ); ?></strong>
				<small class="cmp-muted"><?php esc_html_e( 'Students with unpaid balance', 'class-manager-pro' ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fees'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Collection Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['collection_rate'], 2 ) ); ?>%</strong>
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
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Net Revenue Trend', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Recent monthly collections help spot momentum and slow periods quickly.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-dashboard-revenue"></canvas></div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Student Status Mix', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'See how many students are active, completed, or dropped without leaving the dashboard.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-chart"><canvas id="cmp-dashboard-status"></canvas></div>
			</section>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel" id="cmp-pending-students-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Pending Fee Students', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Review the students with the largest unpaid balances and click Payment to collect instantly.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'tab' => 'pending', 'fee_status' => 'pending' ) ) ); ?>"><?php esc_html_e( 'All Pending', 'class-manager-pro' ); ?></a>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class / Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $pending_students ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No pending students right now.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $pending_students as $student ) : ?>
									<?php $payment_status = cmp_get_student_payment_status( $student ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) ) ); ?>"><strong><?php echo esc_html( $student->name ); ?></strong></a>
											<br><span class="cmp-muted"><?php echo esc_html( $student->phone ); ?></span>
										</td>
										<td>
											<?php echo esc_html( $student->class_name ? $student->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( cmp_get_student_batch_label( $student ) ); ?></span>
										</td>
										<td><strong style="color:#d97706;"><?php echo esc_html( cmp_format_money( max( 0, (float) $student->pending_fee ) ) ); ?></strong></td>
										<td><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></td>
										<td>
											<a class="button button-small button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id, 'amount' => max( 0, (float) $student->pending_fee ) ) ) ); ?>#cmp-add-payment">
												💳 <?php esc_html_e( 'Payment', 'class-manager-pro' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Recently Added Students', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Keep track of the latest admissions and where they were placed.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class / Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Created', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $recent_students ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No students have been added yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $recent_students as $student ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) ) ); ?>"><?php echo esc_html( $student->name ); ?></a>
											<br><span class="cmp-muted"><?php echo esc_html( $student->email ? $student->email : $student->phone ); ?></span>
										</td>
										<td>
											<?php echo esc_html( $student->class_name ? $student->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( cmp_get_student_batch_label( $student ) ); ?></span>
										</td>
										<td><?php echo esc_html( $student->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Course Completion Insights', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Spot classes with strong completions and classes that may need intervention.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Completed', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $course_completion_rows ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No class completion data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $course_completion_rows as $row ) : ?>
									<tr>
										<td>
											<?php echo esc_html( $row->class_name ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( sprintf( __( '%1$d active / %2$d dropped', 'class-manager-pro' ), (int) $row->active_students, (int) $row->dropped_students ) ); ?></span>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->total_students ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->completed_students ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) $row->completion_rate, 2 ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Teacher Overview', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Review assigned batches, student load, and pending collections per teacher.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console' ) ); ?>"><?php esc_html_e( 'Open Teacher Console', 'class-manager-pro' ); ?></a>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $teacher_rows ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No assigned teachers yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $teacher_rows as $teacher_row ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => (int) $teacher_row->ID ) ) ); ?>"><?php echo esc_html( $teacher_row->display_name ); ?></a>
											<br><span class="cmp-muted"><?php echo esc_html( $teacher_row->user_email ); ?></span>
										</td>
										<td>
											<?php echo esc_html( number_format_i18n( (int) $teacher_row->assigned_batches ) ); ?>
											<?php if ( ! empty( $teacher_row->batch_labels ) ) : ?>
												<br><span class="cmp-muted"><?php echo esc_html( implode( ', ', $teacher_row->batch_labels ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) $teacher_row->total_students ) ); ?></td>
										<td>
											<?php echo esc_html( number_format_i18n( (int) $teacher_row->pending_students ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( cmp_format_money( (float) $teacher_row->pending_amount ) ); ?></span>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Batch-wise Performance', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Compare batches by net revenue, payment completion, and pending fee pressure.', 'class-manager-pro' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>"><?php esc_html_e( 'Manage Batches', 'class-manager-pro' ); ?></a>
			</div>
			<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Net Revenue', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Payment Completion', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $batch_performance_rows ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No batch performance data yet.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $batch_performance_rows as $row ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) ) ); ?>"><?php echo esc_html( $row->batch_name ); ?></a>
										<br><span class="cmp-muted"><?php echo esc_html( $row->class_name ); ?></span>
									</td>
									<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
									<td><?php echo esc_html( cmp_format_money( (float) $row->revenue ) ); ?></td>
									<td>
										<?php echo esc_html( number_format_i18n( (int) $row->pending_students ) ); ?>
										<br><span class="cmp-muted"><?php echo esc_html( cmp_format_money( (float) $row->pending_amount ) ); ?></span>
									</td>
									<td><?php echo esc_html( number_format_i18n( (float) $row->payment_completion, 2 ) ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Net Revenue Overview', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Keep the strongest classes and the classes with the most pending balance in one view.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-analytics' ) ); ?>"><?php esc_html_e( 'Open Analytics', 'class-manager-pro' ); ?></a>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Net Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $class_revenue_rows ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No net revenue data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $class_revenue_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->revenue ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->pending_amount ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Quick Actions', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Jump straight into the workspace that needs attention next.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-toolbar">
					<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>"><?php esc_html_e( 'Classes & Batches', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students' ) ); ?>"><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments' ) ); ?>"><?php esc_html_e( 'Payments', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console' ) ); ?>"><?php esc_html_e( 'Teacher Console', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-analytics' ) ); ?>"><?php esc_html_e( 'Analytics', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-razorpay-import' ) ); ?>"><?php esc_html_e( 'Import from Razorpay', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'status' => 'completed' ) ) ); ?>">🎓 <?php esc_html_e( 'Certificates Hub', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'class-manager-pro' ); ?></a>
				</div>
			</section>
		</div>
	</div>
	<?php
}
