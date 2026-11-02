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

	$report             = cmp_get_analytics_report_payload();
	$finance            = cmp_get_finance_summary();
	$dashboard_metrics  = cmp_get_dashboard_metrics( 'month' );
	$batch_finance      = cmp_get_batches_with_metrics();
	$batch_occupancy    = isset( $report['tables']['batch_occupancy'] ) ? $report['tables']['batch_occupancy'] : array();
	$teacher_rows       = isset( $report['tables']['teacher_performance'] ) ? $report['tables']['teacher_performance'] : cmp_get_teacher_report_rows( 12 );
	$pending_students   = cmp_get_students_with_pending_fees( 12 );
	$class_attendance   = cmp_get_attendance_class_report();
	$batch_attendance   = cmp_get_attendance_batch_report();
	$student_attendance = cmp_get_attendance_student_report();
	$top_course_rows    = isset( $report['tables']['top_courses'] ) ? $report['tables']['top_courses'] : cmp_get_top_course_rows( 10 );
	$top_city_rows      = isset( $report['tables']['top_cities'] ) ? $report['tables']['top_cities'] : cmp_get_top_city_rows( 10 );
	$lead_source_rows   = isset( $report['tables']['lead_sources'] ) ? $report['tables']['lead_sources'] : cmp_get_lead_source_report_rows();
	$repeat_rows        = isset( $report['tables']['repeat_students'] ) ? $report['tables']['repeat_students'] : array();
	$data_quality       = isset( $report['tables']['data_quality'] ) ? $report['tables']['data_quality'] : cmp_get_data_quality_summary();
	$monthly_comp       = isset( $report['metrics']['monthly_comparison'] ) ? $report['metrics']['monthly_comparison'] : cmp_get_monthly_comparison_summary();
	$yearly_comp        = isset( $report['metrics']['yearly_comparison'] ) ? $report['metrics']['yearly_comparison'] : cmp_get_yearly_comparison_summary();
	$chart_data         = isset( $report['charts'] ) ? $report['charts'] : array();
	$chart_data['classRevenue']        = cmp_get_class_revenue();
	$chart_data['courseDemandHeatmap'] = cmp_get_course_demand_heatmap( 10 );
	$chart_data['batchOccupancy']      = array(
		'labels' => array_map(
			static function ( $row ) {
				return isset( $row->batch_name ) ? $row->batch_name : '';
			},
			$batch_occupancy
		),
		'values' => array_map(
			static function ( $row ) {
				return isset( $row->student_count ) ? (int) $row->student_count : 0;
			},
			$batch_occupancy
		),
	);

	$monthly_growth = isset( $monthly_comp['previous']['admissions'] ) && (int) $monthly_comp['previous']['admissions'] > 0
		? round( ( ( (int) $monthly_comp['current']['admissions'] - (int) $monthly_comp['previous']['admissions'] ) / (int) $monthly_comp['previous']['admissions'] ) * 100, 2 )
		: 0;
	$monthly_revenue_growth = isset( $monthly_comp['previous']['revenue'] ) && (float) $monthly_comp['previous']['revenue'] > 0
		? round( ( ( (float) $monthly_comp['current']['revenue'] - (float) $monthly_comp['previous']['revenue'] ) / (float) $monthly_comp['previous']['revenue'] ) * 100, 2 )
		: 0;

	wp_add_inline_script(
		'cmp-admin',
		'window.CMPCharts = ' . wp_json_encode( $chart_data ) . '; window.CMPAnalytics = ' . wp_json_encode( $report ) . ';',
		'before'
	);
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Reports & Analytics', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Use structured analytics panels to review business growth, lead quality, teacher performance, and data integrity without scrolling through one raw report dump.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-analytics' ); ?>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Net Revenue', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $finance['total_income'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $dashboard_metrics['pending_fees'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Collection Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $dashboard_metrics['collection_rate'], 2 ) ); ?>%</strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Attendance Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $report['metrics']['overall_attendance_rate'], 2 ) ); ?>%</strong>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Completion Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $report['metrics']['student_completion_rate'], 2 ) ); ?>%</strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Repeat Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $report['metrics']['repeat_rate'], 2 ) ); ?>%</strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Avg Batch Size', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $report['metrics']['average_batch_size'], 2 ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Avg Revenue / Batch', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $report['metrics']['average_revenue_per_batch'] ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Monthly Admission Growth', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $monthly_growth, 2 ) ); ?>%</strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Monthly Revenue Growth', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $monthly_revenue_growth, 2 ) ); ?>%</strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Data Quality Warnings', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $data_quality['total_warnings'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Yearly Admissions', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $yearly_comp['current']['admissions'] ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Monthly Net Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-monthly-revenue"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Monthly Admissions', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-monthly-admissions"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Payment Trends', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-payment-trends"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Registration Trends', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-registration-trends"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Student Growth', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-student-growth"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Lead Source Performance', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-lead-source"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Repeat Students', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-repeat-students"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Class Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-class-revenue"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Course Demand Heatmap', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-course-demand"></canvas></div>
			</section>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Batch Occupancy', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'See batch strength, seat count, and occupancy in one view.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Capacity', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Occupancy', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $batch_occupancy ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No batch occupancy data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $batch_occupancy as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->batch_name ); ?><br><span class="cmp-muted"><?php echo esc_html( $row->class_name ); ?></span></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
										<td><?php echo esc_html( 0 < (int) $row->intake_limit ? number_format_i18n( (int) $row->intake_limit ) : __( 'Unlimited', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) $row->occupancy_rate, 2 ) ); ?>%</td>
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
						<h2><?php esc_html_e( 'Teacher Performance', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Teacher assignments, completion, attendance, and payment exposure at a glance.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Completion', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Attendance', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher Pay', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $teacher_rows ) ) : ?>
								<tr><td colspan="7"><?php esc_html_e( 'No assigned teachers yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $teacher_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->display_name ); ?><br><span class="cmp-muted"><?php echo esc_html( $row->user_email ); ?></span></td>
										<td>
											<?php echo esc_html( number_format_i18n( (int) $row->assigned_batches ) ); ?>
											<?php if ( ! empty( $row->batch_labels ) ) : ?>
												<br><span class="cmp-muted"><?php echo esc_html( implode( ', ', $row->batch_labels ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->total_students ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) $row->student_completion, 2 ) ); ?>%</td>
										<td><?php echo esc_html( number_format_i18n( (float) $row->attendance_rate, 2 ) ); ?>%</td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->total_revenue ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->teacher_payment_total ) ); ?></td>
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
						<h2><?php esc_html_e( 'Top Performing Courses', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'The most in-demand courses by volume, revenue, and completion rate.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Course', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Completion', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $top_course_rows ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No course performance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $top_course_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->revenue ) ); ?></td>
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
						<h2><?php esc_html_e( 'Top Cities', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Standardized city names now power the city report.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'City', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $top_city_rows ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No city data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $top_city_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->city_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->collected_amount ) ); ?></td>
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
						<h2><?php esc_html_e( 'Lead Source Performance', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Lead volume, conversion, and revenue by source.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Source', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Leads', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Conversion', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $lead_source_rows ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No lead source data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $lead_source_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['lead_source'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row['lead_count'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row['student_count'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) $row['conversion_rate'], 2 ) ); ?>%</td>
										<td><?php echo esc_html( cmp_format_money( (float) $row['revenue'] ) ); ?></td>
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
						<h2><?php esc_html_e( 'Repeat Student Report', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Track 2nd, 3rd, and 4+ enrollments as a core business KPI.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Enrollments', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Joined', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $repeat_rows ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No repeat student data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $repeat_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->student_name ); ?><br><span class="cmp-muted"><?php echo esc_html( $row->phone ? $row->phone : __( 'No phone', 'class-manager-pro' ) ); ?></span></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->enrollment_count ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( (float) $row->total_revenue ) ); ?></td>
										<td><?php echo esc_html( $row->first_joined_at ); ?><br><span class="cmp-muted"><?php echo esc_html( $row->last_joined_at ); ?></span></td>
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
						<h2><?php esc_html_e( 'Batch Finance Analysis', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Review income, expense, and profitability per batch.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Expense', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher Pay', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Net', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $batch_finance ) ) : ?>
								<tr><td colspan="6"><?php esc_html_e( 'No batch finance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $batch_finance as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->batch_name ); ?></td>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->revenue ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->total_expense ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $row->teacher_payment ) ); ?></td>
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
						<h2><?php esc_html_e( 'Data Quality Warnings', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Missing email, invalid mobile, duplicate records, city issues, and unpaid rows are surfaced here.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Issue', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Count', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><?php esc_html_e( 'Missing Email - Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['missing_email_students'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Missing Email - Interested Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['missing_email_leads'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Invalid Mobile - Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['invalid_mobile_students'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Invalid Mobile - Interested Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['invalid_mobile_leads'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Duplicate Email - Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['duplicate_email_students'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Duplicate Email - Interested Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['duplicate_email_leads'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Invalid City - Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['invalid_city_students'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Invalid City - Interested Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['invalid_city_leads'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Missing Payment - Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['missing_payment_students'] ) ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Missing Payment - Interested Students', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $data_quality['missing_payment_leads'] ) ); ?></td></tr>
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
						<p class="cmp-muted"><?php esc_html_e( 'Keep recent payment risk visible alongside the broader reports.', 'class-manager-pro' ); ?></p>
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
										<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->phone ); ?></span></td>
										<td><?php echo esc_html( $student->class_name ? $student->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?><br><span class="cmp-muted"><?php echo esc_html( cmp_get_student_batch_label( $student ) ); ?></span></td>
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
						<h2><?php esc_html_e( 'Attendance Overview', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Class, batch, and student attendance stay grouped into scrollable panels.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Class / Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Marked', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $class_attendance ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No attendance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $class_attendance as $row ) : ?>
									<?php $rate = (int) $row->total_marked > 0 ? round( ( (int) $row->present_count / (int) $row->total_marked ) * 100, 2 ) : 0; ?>
									<tr>
										<td><?php echo esc_html( $row->class_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->present_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->total_marked ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $rate, 2 ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall" style="margin-top:16px;">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Marked', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $batch_attendance ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No batch attendance data yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $batch_attendance as $row ) : ?>
									<?php $rate = (int) $row->total_marked > 0 ? round( ( (int) $row->present_count / (int) $row->total_marked ) * 100, 2 ) : 0; ?>
									<tr>
										<td><?php echo esc_html( $row->batch_name ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->present_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row->total_marked ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $rate, 2 ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-xl" style="margin-top:16px;">
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

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Monthly Comparison', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Month-over-month admissions and revenue context for faster decisions.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Metric', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Current', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Previous', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Admissions', 'class-manager-pro' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $monthly_comp['current']['admissions'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $monthly_comp['previous']['admissions'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></td>
							<td><?php echo esc_html( cmp_format_money( $monthly_comp['current']['revenue'] ) ); ?></td>
							<td><?php echo esc_html( cmp_format_money( $monthly_comp['previous']['revenue'] ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Yearly Comparison', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Year-over-year admissions and revenue context for the leadership view.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Metric', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Current', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Previous', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Admissions', 'class-manager-pro' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $yearly_comp['current']['admissions'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $yearly_comp['previous']['admissions'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></td>
							<td><?php echo esc_html( cmp_format_money( $yearly_comp['current']['revenue'] ) ); ?></td>
							<td><?php echo esc_html( cmp_format_money( $yearly_comp['previous']['revenue'] ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</section>
		</div>
	</div>
	<?php
}
