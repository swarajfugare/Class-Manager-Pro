<?php
/**
 * Analytics admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the analytics page.
 */
function cmp_render_analytics_page() {
	cmp_require_manage_options();

	$finance            = cmp_get_finance_summary();
	$dashboard_metrics  = cmp_get_dashboard_metrics( 'month' );
	$batch_finance      = cmp_get_batches_with_metrics();
	$batch_performance  = cmp_get_dashboard_batch_performance_rows( 12 );
	$teacher_rows       = cmp_get_dashboard_teacher_rows( 12 );
	$pending_students   = cmp_get_dashboard_pending_students( 12 );
	$class_attendance   = cmp_get_attendance_class_report();
	$batch_attendance   = cmp_get_attendance_batch_report();
	$student_attendance = cmp_get_attendance_student_report();
	$chart_data = array(
		'monthlyRevenue'      => cmp_get_monthly_revenue( 12 ),
		'classRevenue'        => cmp_get_class_revenue(),
		'studentGrowth'       => cmp_get_student_growth( 12 ),
		'courseDemandHeatmap' => cmp_get_course_demand_heatmap( 10 ),
	);

	wp_add_inline_script( 'cmp-admin', 'window.CMPCharts = ' . wp_json_encode( $chart_data ) . ';', 'before' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Analytics', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Use structured analytics panels to review revenue, demand, payment risk, teacher performance, and attendance without scrolling through a single long report.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Income', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['total_income'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Expense', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['total_expense'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Teacher Payment', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['teacher_payment'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Ads Spend', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['ads_spend'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Manual Income', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['manual_income'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Net Income', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['net_income'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $dashboard_metrics['pending_fees'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Collection Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $dashboard_metrics['collection_rate'], 2 ) ); ?>%</strong>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Monthly Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-monthly-revenue"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Class-wise Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart"><canvas id="cmp-analytics-class-revenue"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Course Demand Heatmap', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart"><canvas id="cmp-analytics-course-demand"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Student Growth', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-student-growth"></canvas></div>
			</section>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Batch Finance Analysis', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Review income, expense, and profitability per batch in a contained panel.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Income', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Expense', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher Pay', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Ads Spend', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Net', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $batch_finance ) ) : ?>
								<tr><td colspan="7"><?php esc_html_e( 'No batch finance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $batch_finance as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->batch_name ); ?></td>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->revenue ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->total_expense ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->teacher_payment ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->ads_spend ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->net_income ) ); ?></td>
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
						<h2><?php esc_html_e( 'Batch Payment Risk', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'See which batches are collecting well and which ones carry the most pending balance.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Completion', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $batch_performance ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No batch performance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $batch_performance as $row ) : ?>
									<tr>
										<td>
											<?php echo esc_html( $row->batch_name ); ?>
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

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Teacher Performance', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Keep teacher assignments, student coverage, and pending fee exposure visible.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $teacher_rows ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No assigned teachers yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $teacher_rows as $row ) : ?>
									<tr>
										<td>
											<?php echo esc_html( $row->display_name ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( $row->user_email ); ?></span>
										</td>
										<td>
											<?php echo esc_html( number_format_i18n( (int) $row->assigned_batches ) ); ?>
											<?php if ( ! empty( $row->batch_labels ) ) : ?>
												<br><span class="cmp-muted"><?php echo esc_html( implode( ', ', $row->batch_labels ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->total_students ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->total_revenue ) ); ?></td>
										<td>
											<?php echo esc_html( number_format_i18n( (int) $row->pending_students ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( cmp_format_money( (float) $row->pending_amount ) ); ?></span>
										</td>
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
						<h2><?php esc_html_e( 'Pending Payment Students', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Keep recent payment risk visible inside analytics alongside the broader reports.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class / Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $pending_students ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No pending students right now.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $pending_students as $student ) : ?>
									<tr>
										<td>
											<?php echo esc_html( $student->name ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( $student->phone ); ?></span>
										</td>
										<td>
											<?php echo esc_html( $student->class_name ? $student->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( cmp_get_student_batch_label( $student ) ); ?></span>
										</td>
										<td><?php echo esc_html( cmp_format_money( max( 0, (float) $student->pending_fee ) ) ); ?></td>
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
						<h2><?php esc_html_e( 'Class Attendance Analysis', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Compare attendance performance across classes in a fixed-height report panel.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $class_attendance ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No attendance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $class_attendance as $row ) : ?>
									<?php $rate = (int) $row->total_marked > 0 ? round( ( (int) $row->present_count / (int) $row->total_marked ) * 100, 2 ) : 0; ?>
									<tr>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->batch_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->present_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $rate, 2 ) ); ?>%</td>
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
						<h2><?php esc_html_e( 'Batch Attendance Analysis', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Review attendance at the batch level without expanding the page length.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $batch_attendance ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No attendance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $batch_attendance as $row ) : ?>
									<?php $rate = (int) $row->total_marked > 0 ? round( ( (int) $row->present_count / (int) $row->total_marked ) * 100, 2 ) : 0; ?>
									<tr>
										<td><?php echo esc_html( $row->batch_name ); ?></td>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->present_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $rate, 2 ) ); ?>%</td>
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
						<h2><?php esc_html_e( 'Student Attendance Analysis', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'The student-level report stays in its own scrollable container to keep the screen manageable.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-xl">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Absent', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Leave', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $student_attendance ) ) : ?>
								<tr><td colspan="7"><?php esc_html_e( 'No student attendance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $student_attendance as $row ) : ?>
									<?php $rate = (int) $row->total_marked > 0 ? round( ( (int) $row->present_count / (int) $row->total_marked ) * 100, 2 ) : 0; ?>
									<tr>
										<td><?php echo esc_html( $row->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $row->phone ); ?></span></td>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( $row->batch_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->present_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->absent_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->leave_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $rate, 2 ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</div>
	<?php
}
