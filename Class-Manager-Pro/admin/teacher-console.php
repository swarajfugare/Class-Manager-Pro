<?php
/**
 * Teacher console page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a focused console for assigned teachers.
 */
function cmp_render_teacher_console_page() {
	$is_admin = current_user_can( 'manage_options' );

	if ( ! $is_admin && ! cmp_current_user_has_teacher_batches() ) {
		wp_die( esc_html__( 'No Class Manager Pro batches are assigned to this teacher account.', 'class-manager-pro' ) );
	}

	$teacher_overview_rows = $is_admin ? cmp_get_teacher_overview_rows( array(), 4, true ) : array();
	$teacher_ids          = array();
	$teacher_row_map      = array();
	$selected_teacher_id = $is_admin ? absint( cmp_field( $_GET, 'teacher_user_id', 0 ) ) : get_current_user_id();
	$batch_id           = absint( cmp_field( $_GET, 'id', 0 ) );
	$selected_batch     = null;

	foreach ( $teacher_overview_rows as $teacher_row ) {
		$teacher_ids[]                           = (int) $teacher_row->ID;
		$teacher_row_map[ (int) $teacher_row->ID ] = $teacher_row;
	}

	if ( ! $is_admin ) {
		$teacher_ids[] = get_current_user_id();
	}

	$teacher_metrics_map = cmp_get_teacher_metrics_map( $teacher_ids );

	if ( $is_admin && ! $selected_teacher_id && ! empty( $teacher_overview_rows ) ) {
		$selected_teacher_id = (int) $teacher_overview_rows[0]->ID;
	}

	if ( ! $is_admin ) {
		$selected_teacher_id = get_current_user_id();
	}

	$teacher_user            = $selected_teacher_id ? get_userdata( $selected_teacher_id ) : null;
	$selected_teacher_row    = isset( $teacher_row_map[ (int) $selected_teacher_id ] ) ? $teacher_row_map[ (int) $selected_teacher_id ] : null;
	$selected_teacher_data = isset( $teacher_metrics_map[ (int) $selected_teacher_id ] ) ? $teacher_metrics_map[ (int) $selected_teacher_id ] : array(
		'assigned_batches' => 0,
		'total_students'   => 0,
		'pending_students' => 0,
		'pending_amount'   => 0,
		'interested_students' => 0,
		'teacher_payment_total' => 0,
		'total_revenue'    => 0,
	);
	$batch_rows           = $selected_teacher_id ? cmp_get_teacher_batch_performance( $selected_teacher_id ) : array();

	if ( ! empty( $batch_rows ) ) {
		foreach ( $batch_rows as $row ) {
			if ( (int) $row->id === $batch_id ) {
				$selected_batch = cmp_get_batch( (int) $row->id );
				break;
			}
		}

		if ( ! $selected_batch ) {
			$selected_batch = cmp_get_batch( (int) $batch_rows[0]->id );
		}
	}

	$students            = $selected_batch ? cmp_get_students( array( 'batch_id' => (int) $selected_batch->id ) ) : array();
	$interested_students = $selected_batch ? cmp_get_interested_students_for_batch( (int) $selected_batch->id ) : array();
	$selected_student_id = absint( cmp_field( $_GET, 'student_id', 0 ) );
	$selected_student    = $selected_student_id ? cmp_get_student( $selected_student_id ) : null;
	$attendance_totals   = $selected_batch ? cmp_get_batch_attendance_totals( (int) $selected_batch->id ) : array( 'present' => 0, 'absent' => 0, 'leave' => 0, 'total' => 0, 'rate' => 0 );
	$teacher_logs        = $selected_teacher_id ? cmp_get_teacher_logs( $selected_teacher_id, 20 ) : array();
	$teacher_view        = sanitize_key( cmp_field( $_GET, 'teacher_view', $selected_student ? 'students' : 'overview' ) );
	$pending_students    = array();
	$recent_students     = array();

	if ( ! in_array( $teacher_view, array( 'overview', 'students', 'attendance', 'interested' ), true ) ) {
		$teacher_view = 'overview';
	}

	if ( $selected_student && ( ! $selected_batch || (int) $selected_student->batch_id !== (int) $selected_batch->id ) ) {
		$selected_student = null;
	}

	foreach ( $students as $student ) {
		if ( count( $recent_students ) < 8 ) {
			$recent_students[] = $student;
		}

		if ( (float) $student->paid_fee + 0.01 < (float) $student->total_fee ) {
			$pending_students[] = $student;
		}
	}

	if ( $selected_batch && get_current_user_id() && (int) $selected_batch->teacher_user_id === get_current_user_id() ) {
		cmp_log_teacher_action(
			array(
				'teacher_user_id' => get_current_user_id(),
				'batch_id'        => (int) $selected_batch->id,
				'action'          => 'batch_viewed',
				'message'         => sprintf(
					/* translators: %s: batch name */
					__( 'Viewed batch "%s".', 'class-manager-pro' ),
					$selected_batch->batch_name
				),
			)
		);

		if ( $selected_student ) {
			cmp_log_teacher_action(
				array(
					'teacher_user_id' => get_current_user_id(),
					'batch_id'        => (int) $selected_batch->id,
					'student_id'      => (int) $selected_student->id,
					'action'          => 'student_viewed',
					'message'         => sprintf(
						/* translators: %s: student name */
						__( 'Viewed student "%s".', 'class-manager-pro' ),
						$selected_student->name
					),
				)
			);
		}
	}

	$teacher_logs_export_url = $selected_teacher_id
		? wp_nonce_url(
			add_query_arg(
				array(
					'page'            => 'cmp-teacher-console',
					'cmp_export'      => 'teacher-logs',
					'teacher_user_id' => (int) $selected_teacher_id,
				),
				admin_url( 'admin.php' )
			),
			'cmp_export_teacher-logs'
		)
		: '';
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Teacher Console', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Use a simpler teacher workspace with separate overview, students, interested follow-ups, and attendance sections for each batch.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<?php if ( $is_admin ) : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Teachers', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Open a teacher workspace to review assigned batches, students, and attendance.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Assigned Batches', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher Payment', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Interested', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending Payments', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $teacher_overview_rows ) ) : ?>
								<tr><td colspan="8"><?php esc_html_e( 'No teachers are assigned to batches yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $teacher_overview_rows as $teacher_row ) : ?>
									<?php $teacher_console_url = cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => (int) $teacher_row->ID ) ); ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $teacher_row->display_name ); ?></strong>
											<?php if ( (int) $selected_teacher_id === (int) $teacher_row->ID ) : ?>
												<br><span class="cmp-muted"><?php esc_html_e( 'Currently selected', 'class-manager-pro' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $teacher_row->user_email ); ?></td>
										<td>
											<?php echo esc_html( number_format_i18n( $teacher_row->assigned_batches ) ); ?>
											<?php if ( ! empty( $teacher_row->batch_labels ) ) : ?>
												<br><span class="cmp-muted"><?php echo esc_html( implode( ', ', $teacher_row->batch_labels ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( number_format_i18n( $teacher_row->total_students ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $teacher_row->teacher_payment_total ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $teacher_row->interested_students ) ); ?></td>
										<td>
											<?php echo esc_html( number_format_i18n( $teacher_row->pending_students ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( cmp_format_money( $teacher_row->pending_amount ) ); ?></span>
										</td>
										<td>
											<a class="button button-small <?php echo (int) $selected_teacher_id === (int) $teacher_row->ID ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $teacher_console_url ); ?>"><?php esc_html_e( 'Open Console', 'class-manager-pro' ); ?></a>
											<?php if ( cmp_get_user_edit_link( (int) $teacher_row->ID ) ) : ?>
												<a class="button button-small" href="<?php echo esc_url( cmp_get_user_edit_link( (int) $teacher_row->ID ) ); ?>"><?php esc_html_e( 'Edit Teacher', 'class-manager-pro' ); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $teacher_user ) : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php echo esc_html( $is_admin ? $teacher_user->display_name : __( 'My Performance', 'class-manager-pro' ) ); ?></h2>
						<p class="cmp-muted">
							<?php echo esc_html( $teacher_user->user_email ); ?>
							<?php if ( $selected_teacher_row && ! empty( $selected_teacher_row->batch_labels ) ) : ?>
								<br><?php echo esc_html( implode( ', ', $selected_teacher_row->batch_labels ) ); ?>
							<?php endif; ?>
						</p>
					</div>
					<div class="cmp-toolbar">
						<?php if ( $teacher_logs_export_url ) : ?>
							<a class="button" href="<?php echo esc_url( $teacher_logs_export_url ); ?>"><?php esc_html_e( 'Export Activity', 'class-manager-pro' ); ?></a>
						<?php endif; ?>
						<?php if ( $is_admin && cmp_get_user_edit_link( (int) $teacher_user->ID ) ) : ?>
							<a class="button" href="<?php echo esc_url( cmp_get_user_edit_link( (int) $teacher_user->ID ) ); ?>"><?php esc_html_e( 'Edit Teacher', 'class-manager-pro' ); ?></a>
						<?php endif; ?>
					</div>
				</div>

				<div class="cmp-cards">
					<div class="cmp-card">
						<span><?php esc_html_e( 'Assigned Batches', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $selected_teacher_data['assigned_batches'] ) ); ?></strong>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Total Students', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $selected_teacher_data['total_students'] ) ); ?></strong>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Total Earnings', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( cmp_format_money( $selected_teacher_data['teacher_payment_total'] ) ); ?></strong>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Pending Payments', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $selected_teacher_data['pending_students'] ) ); ?></strong>
						<small class="cmp-muted"><?php echo esc_html( cmp_format_money( $selected_teacher_data['pending_amount'] ) ); ?></small>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $selected_teacher_data['interested_students'] ) ); ?></strong>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( empty( $batch_rows ) ) : ?>
			<section class="cmp-panel">
				<p>
					<?php
					echo esc_html(
						$is_admin && $teacher_user
							? sprintf(
								/* translators: %s: teacher name */
								__( 'No batches are assigned to %s yet.', 'class-manager-pro' ),
								$teacher_user->display_name
							)
							: __( 'No batches are assigned yet.', 'class-manager-pro' )
						);
					?>
				</p>
			</section>
		<?php else : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php echo esc_html( $is_admin ? __( 'Assigned Batches', 'class-manager-pro' ) : __( 'My Batches', 'class-manager-pro' ) ); ?></h2>
						<p class="cmp-muted">
							<?php
							echo esc_html(
								$is_admin && $teacher_user
									? sprintf(
										/* translators: %s: teacher name */
										__( 'Choose a batch assigned to %s.', 'class-manager-pro' ),
										$teacher_user->display_name
									)
									: __( 'Choose a batch to view students, interested follow-ups, and attendance.', 'class-manager-pro' )
							);
							?>
						</p>
					</div>
				</div>

				<form method="get" class="cmp-toolbar">
					<input type="hidden" name="page" value="cmp-teacher-console">
					<?php if ( $is_admin && $selected_teacher_id ) : ?>
						<input type="hidden" name="teacher_user_id" value="<?php echo esc_attr( (int) $selected_teacher_id ); ?>">
					<?php endif; ?>
					<input type="hidden" name="teacher_view" value="<?php echo esc_attr( $teacher_view ); ?>">
					<select name="id" data-cmp-searchable="1">
						<?php foreach ( $batch_rows as $row ) : ?>
							<option value="<?php echo esc_attr( (int) $row->id ); ?>" <?php selected( $selected_batch ? (int) $selected_batch->id : 0, (int) $row->id ); ?>>
								<?php echo esc_html( sprintf( '%1$s / %2$s', $row->class_name, $row->batch_name ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter by Batch', 'class-manager-pro' ); ?></button>
				</form>

				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher Payment', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Interested', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending Payments', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $batch_rows as $row ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $row->batch_name ); ?></strong>
										<?php if ( $selected_batch && (int) $selected_batch->id === (int) $row->id ) : ?>
											<br><span class="cmp-muted"><?php esc_html_e( 'Currently selected', 'class-manager-pro' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $row->class_name ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row->student_count ) ); ?></td>
									<td><?php echo esc_html( cmp_format_money( $row->teacher_payment ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row->interested_students ) ); ?></td>
									<td>
										<?php echo esc_html( number_format_i18n( $row->pending_students ) ); ?>
										<br><span class="cmp-muted"><?php echo esc_html( cmp_format_money( $row->pending_amount ) ); ?></span>
									</td>
									<td class="cmp-actions">
										<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => $is_admin ? (int) $selected_teacher_id : 0, 'id' => (int) $row->id, 'teacher_view' => 'students' ) ) ); ?>"><?php esc_html_e( 'View Students', 'class-manager-pro' ); ?></a>
										<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => $is_admin ? (int) $selected_teacher_id : 0, 'id' => (int) $row->id, 'teacher_view' => 'interested' ) ) ); ?>"><?php esc_html_e( 'Interested', 'class-manager-pro' ); ?></a>
										<?php if ( cmp_is_attendance_enabled() ) : ?>
											<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => $is_admin ? (int) $selected_teacher_id : 0, 'id' => (int) $row->id, 'teacher_view' => 'attendance' ) ) ); ?>"><?php esc_html_e( 'Attendance', 'class-manager-pro' ); ?></a>
										<?php endif; ?>
										<?php if ( $is_admin ) : ?>
											<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) ) ); ?>"><?php esc_html_e( 'View Batch Details', 'class-manager-pro' ); ?></a>
											<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $row->id ) ) ); ?>"><?php esc_html_e( 'Edit Assignment', 'class-manager-pro' ); ?></a>
										<?php else : ?>
											<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'id' => (int) $row->id, 'teacher_view' => 'overview' ) ) ); ?>"><?php esc_html_e( 'Overview', 'class-manager-pro' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>

			<?php if ( $selected_batch ) : ?>
				<?php
				$teacher_view_args = array(
					'id'           => (int) $selected_batch->id,
					'teacher_view' => 'overview',
				);

				if ( $is_admin && $selected_teacher_id ) {
					$teacher_view_args['teacher_user_id'] = (int) $selected_teacher_id;
				}

				$interested_table_context = array(
					'return_page' => 'cmp-teacher-console',
					'return_args' => array(
						'id'           => (int) $selected_batch->id,
						'teacher_view' => 'interested',
					),
				);

				if ( $is_admin && $selected_teacher_id ) {
					$interested_table_context['return_args']['teacher_user_id'] = (int) $selected_teacher_id;
				}
				?>
				<section class="cmp-panel">
					<div class="cmp-panel-header">
						<div>
							<h2><?php echo esc_html( $selected_batch->batch_name ); ?></h2>
							<p class="cmp-muted"><?php echo esc_html( $selected_batch->class_name ); ?><?php echo $selected_batch->class_days ? ' | ' . esc_html( $selected_batch->class_days ) : ''; ?><?php echo $selected_batch->start_date ? ' | ' . esc_html( $selected_batch->start_date ) : ''; ?><?php echo $selected_batch->teacher_user_id ? ' | ' . esc_html( cmp_get_teacher_label( (int) $selected_batch->teacher_user_id ) ) : ''; ?></p>
						</div>
						<div class="cmp-toolbar">
							<?php if ( $is_admin ) : ?>
								<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $selected_batch->id ) ) ); ?>"><?php esc_html_e( 'View Batch Details', 'class-manager-pro' ); ?></a>
								<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $selected_batch->id ) ) ); ?>"><?php esc_html_e( 'Edit Assignment', 'class-manager-pro' ); ?></a>
							<?php endif; ?>
						</div>
					</div>

					<div class="cmp-cards">
						<div class="cmp-card">
							<span><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( count( $students ) ) ); ?></strong>
						</div>
						<div class="cmp-card">
							<span><?php esc_html_e( 'Teacher Payment', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( cmp_format_money( cmp_get_batch_expense_totals( (int) $selected_batch->id )['teacher_payment'] ) ); ?></strong>
						</div>
						<div class="cmp-card">
							<span><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( count( $interested_students ) ) ); ?></strong>
						</div>
						<div class="cmp-card">
							<span><?php esc_html_e( 'Present Marks', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( $attendance_totals['present'] ) ); ?></strong>
						</div>
						<div class="cmp-card">
							<span><?php esc_html_e( 'Absent Marks', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( $attendance_totals['absent'] ) ); ?></strong>
						</div>
						<div class="cmp-card">
							<span><?php esc_html_e( 'Attendance Rate', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( (float) $attendance_totals['rate'], 2 ) ); ?>%</strong>
						</div>
					</div>

					<div class="cmp-teacher-section-nav">
						<a class="button <?php echo 'overview' === $teacher_view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', $teacher_view_args ) ); ?>"><?php esc_html_e( 'Overview', 'class-manager-pro' ); ?></a>
						<a class="button <?php echo 'students' === $teacher_view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array_merge( $teacher_view_args, array( 'teacher_view' => 'students' ) ) ) ); ?>"><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></a>
						<a class="button <?php echo 'interested' === $teacher_view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array_merge( $teacher_view_args, array( 'teacher_view' => 'interested' ) ) ) ); ?>"><?php esc_html_e( 'Interested', 'class-manager-pro' ); ?></a>
						<?php if ( cmp_is_attendance_enabled() ) : ?>
							<a class="button <?php echo 'attendance' === $teacher_view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array_merge( $teacher_view_args, array( 'teacher_view' => 'attendance' ) ) ) ); ?>"><?php esc_html_e( 'Attendance', 'class-manager-pro' ); ?></a>
						<?php endif; ?>
					</div>
				</section>

				<?php if ( 'overview' === $teacher_view ) : ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php esc_html_e( 'Batch Overview', 'class-manager-pro' ); ?></h2>
								<p class="cmp-muted"><?php esc_html_e( 'Review the students who need attention before opening the detailed student or attendance section.', 'class-manager-pro' ); ?></p>
							</div>
						</div>

						<div class="cmp-grid cmp-grid-2">
							<section class="cmp-panel cmp-panel-nested">
								<div class="cmp-panel-header">
									<div>
										<h3><?php esc_html_e( 'Pending Fee Students', 'class-manager-pro' ); ?></h3>
										<p class="cmp-muted"><?php esc_html_e( 'Students whose fees are still pending in this batch.', 'class-manager-pro' ); ?></p>
									</div>
								</div>

								<div class="cmp-table-scroll cmp-table-scroll-y">
									<table class="widefat striped">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
												<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
												<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php if ( empty( $pending_students ) ) : ?>
												<tr><td colspan="3"><?php esc_html_e( 'No pending fees in this batch.', 'class-manager-pro' ); ?></td></tr>
											<?php else : ?>
												<?php foreach ( $pending_students as $student ) : ?>
													<?php $payment_status = cmp_get_student_payment_status( $student ); ?>
													<tr>
														<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
														<td><?php echo esc_html( cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) ) ); ?></td>
														<td><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></td>
													</tr>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</section>

							<section class="cmp-panel cmp-panel-nested">
								<div class="cmp-panel-header">
									<div>
										<h3><?php esc_html_e( 'Student Snapshot', 'class-manager-pro' ); ?></h3>
										<p class="cmp-muted"><?php esc_html_e( 'A quick look at the current batch roster.', 'class-manager-pro' ); ?></p>
									</div>
								</div>

								<div class="cmp-table-scroll cmp-table-scroll-y">
									<table class="widefat striped">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
												<th><?php esc_html_e( 'Contact', 'class-manager-pro' ); ?></th>
												<th><?php esc_html_e( 'Payment', 'class-manager-pro' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php if ( empty( $recent_students ) ) : ?>
												<tr><td colspan="3"><?php esc_html_e( 'No students are enrolled yet.', 'class-manager-pro' ); ?></td></tr>
											<?php else : ?>
												<?php foreach ( $recent_students as $student ) : ?>
													<?php $payment_status = cmp_get_student_payment_status( $student ); ?>
													<tr>
														<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
														<td><?php echo esc_html( $student->phone ? $student->phone : __( 'Phone not set', 'class-manager-pro' ) ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->email ? $student->email : __( 'Email not set', 'class-manager-pro' ) ); ?></span></td>
														<td><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></td>
													</tr>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</section>
						</div>
					</section>
				<?php elseif ( 'students' === $teacher_view ) : ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></h2>
								<p class="cmp-muted"><?php esc_html_e( 'Open one student at a time when you need to check contact and payment details.', 'class-manager-pro' ); ?></p>
							</div>
						</div>

						<div class="cmp-table-scroll">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Payment Status', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $students ) ) : ?>
										<tr><td colspan="6"><?php esc_html_e( 'No students are enrolled yet.', 'class-manager-pro' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $students as $student ) : ?>
											<?php $payment_status = cmp_get_student_payment_status( $student ); ?>
											<tr>
												<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
												<td><?php echo esc_html( $student->phone ); ?></td>
												<td><?php echo esc_html( $student->email ); ?></td>
												<td><?php echo esc_html( cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) ) ); ?></td>
												<td><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></td>
												<td><a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => $is_admin ? $selected_teacher_id : 0, 'id' => (int) $selected_batch->id, 'teacher_view' => 'students', 'student_id' => (int) $student->id ) ) ); ?>"><?php esc_html_e( 'View', 'class-manager-pro' ); ?></a></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</section>
				<?php elseif ( 'interested' === $teacher_view ) : ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></h2>
								<p class="cmp-muted"><?php esc_html_e( 'Failed payment attempts stay here for quick call, WhatsApp, or email follow-up.', 'class-manager-pro' ); ?></p>
							</div>
						</div>

						<?php cmp_render_interested_students_table( $interested_students, __( 'No failed payment attempts are linked to this batch right now.', 'class-manager-pro' ), $interested_table_context ); ?>
					</section>
				<?php endif; ?>

				<?php if ( $selected_student && 'students' === $teacher_view ) : ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php echo esc_html( $selected_student->name ); ?></h2>
								<p class="cmp-muted"><?php echo esc_html( $selected_student->unique_id ); ?></p>
							</div>
							<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'teacher_user_id' => $is_admin ? $selected_teacher_id : 0, 'id' => (int) $selected_batch->id, 'teacher_view' => 'students' ) ) ); ?>"><?php esc_html_e( 'Back to Students', 'class-manager-pro' ); ?></a>
						</div>

						<div class="cmp-detail-grid">
							<p><span><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $selected_student->phone ); ?></strong></p>
							<p><span><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $selected_student->email ? $selected_student->email : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
							<p><span><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ucfirst( $selected_student->status ) ); ?></strong></p>
							<p><span><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $selected_student->class_name ); ?></strong></p>
							<p><span><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $selected_student->batch_name ); ?></strong></p>
							<p><span><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $selected_student->fee_due_date ? $selected_student->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( cmp_is_attendance_enabled() && 'attendance' === $teacher_view ) : ?>
					<?php cmp_render_batch_attendance_panel( $selected_batch, 'cmp-teacher-console' ); ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $teacher_logs ) ) : ?>
				<section class="cmp-panel">
					<div class="cmp-panel-header">
						<div>
							<h2><?php esc_html_e( 'Recent Activity', 'class-manager-pro' ); ?></h2>
							<p class="cmp-muted"><?php esc_html_e( 'Use this activity feed to review how teachers are navigating batches and student records.', 'class-manager-pro' ); ?></p>
						</div>
					</div>
					<div class="cmp-table-scroll">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Message', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Time', 'class-manager-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $teacher_logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( cmp_get_teacher_log_action_label( $log->action ) ); ?></td>
										<td><?php echo esc_html( $log->batch_name ? $log->batch_name : __( 'Not available', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( $log->student_name ? $log->student_name : __( 'Not available', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( $log->message ); ?></td>
										<td><?php echo esc_html( $log->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
