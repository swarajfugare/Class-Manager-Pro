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

	$finance           = cmp_get_finance_summary();
	$batch_finance     = cmp_get_batches_with_metrics();
	$class_attendance  = cmp_get_attendance_class_report();
	$batch_attendance  = cmp_get_attendance_batch_report();
	$student_attendance = cmp_get_attendance_student_report();
	$chart_data = array(
		'monthlyRevenue' => cmp_get_monthly_revenue( 12 ),
		'classRevenue'   => cmp_get_class_revenue(),
		'studentGrowth'  => cmp_get_student_growth( 12 ),
		'courseDemandHeatmap' => cmp_get_course_demand_heatmap( 10 ),
	);

	wp_add_inline_script( 'cmp-admin', 'window.CMPCharts = ' . wp_json_encode( $chart_data ) . ';', 'before' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Analytics', 'class-manager-pro' ); ?></h1>
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
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel cmp-panel-wide">
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
			<section class="cmp-panel cmp-panel-wide">
				<h2><?php esc_html_e( 'Student Growth', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-student-growth"></canvas></div>
			</section>
		</div>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Batch Finance Analysis', 'class-manager-pro' ); ?></h2>
			<div class="cmp-table-scroll">
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

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Class Attendance Analysis', 'class-manager-pro' ); ?></h2>
				<div class="cmp-table-scroll">
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
										<td><?php echo esc_html( cmp_format_money( $rate ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Batch Attendance Analysis', 'class-manager-pro' ); ?></h2>
				<div class="cmp-table-scroll">
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
										<td><?php echo esc_html( cmp_format_money( $rate ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Student Attendance Analysis', 'class-manager-pro' ); ?></h2>
			<div class="cmp-table-scroll">
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
									<td><?php echo esc_html( cmp_format_money( $rate ) ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
	<?php
}
