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
	if ( ! current_user_can( 'manage_options' ) && ! cmp_current_user_has_teacher_batches() ) {
		wp_die( esc_html__( 'No Class Manager Pro batches are assigned to this teacher account.', 'class-manager-pro' ) );
	}

	$batches  = cmp_get_current_teacher_batches();
	$batch_id = absint( cmp_field( $_GET, 'id', 0 ) );
	$batch    = $batch_id ? cmp_get_batch( $batch_id ) : ( ! empty( $batches ) ? $batches[0] : null );

	if ( $batch && ! current_user_can( 'manage_options' ) && (int) $batch->teacher_user_id !== get_current_user_id() ) {
		$batch = ! empty( $batches ) ? $batches[0] : null;
	}

	$students            = $batch ? cmp_get_students( array( 'batch_id' => (int) $batch->id ) ) : array();
	$selected_student_id = absint( cmp_field( $_GET, 'student_id', 0 ) );
	$selected_student    = $selected_student_id ? cmp_get_student( $selected_student_id ) : null;
	$attendance_totals   = $batch ? cmp_get_batch_attendance_totals( (int) $batch->id ) : array( 'present' => 0, 'absent' => 0, 'leave' => 0, 'total' => 0, 'rate' => 0 );

	if ( $selected_student && ( ! $batch || (int) $selected_student->batch_id !== (int) $batch->id ) ) {
		$selected_student = null;
	}

	if ( $batch && get_current_user_id() && (int) $batch->teacher_user_id === get_current_user_id() ) {
		cmp_log_teacher_action(
			array(
				'teacher_user_id' => get_current_user_id(),
				'batch_id'        => (int) $batch->id,
				'action'          => 'batch_viewed',
				'message'         => sprintf(
					/* translators: %s: batch name */
					__( 'Viewed batch "%s".', 'class-manager-pro' ),
					$batch->batch_name
				),
			)
		);

		if ( $selected_student ) {
			cmp_log_teacher_action(
				array(
					'teacher_user_id' => get_current_user_id(),
					'batch_id'        => (int) $batch->id,
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
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Teacher Console', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Assigned teachers can view student names and phone numbers, then mark attendance for their batches.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<?php if ( empty( $batches ) ) : ?>
			<section class="cmp-panel">
				<p><?php esc_html_e( 'No batches are assigned yet.', 'class-manager-pro' ); ?></p>
			</section>
		<?php else : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'My Batches', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Choose a batch to view students and attendance.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-toolbar">
					<?php foreach ( $batches as $row ) : ?>
						<a class="button <?php echo $batch && (int) $batch->id === (int) $row->id ? 'button-primary' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'id' => (int) $row->id ) ) ); ?>">
							<?php echo esc_html( $row->batch_name ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</section>

			<?php if ( $batch ) : ?>
				<section class="cmp-panel">
					<div class="cmp-panel-header">
						<div>
							<h2><?php echo esc_html( $batch->batch_name ); ?></h2>
							<p class="cmp-muted"><?php echo esc_html( $batch->class_name ); ?><?php echo $batch->class_days ? ' | ' . esc_html( $batch->class_days ) : ''; ?><?php echo $batch->start_date ? ' | ' . esc_html( $batch->start_date ) : ''; ?></p>
						</div>
					</div>

					<div class="cmp-cards cmp-cards-4">
						<div class="cmp-card">
							<span><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( count( $students ) ) ); ?></strong>
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
							<strong><?php echo esc_html( cmp_format_money( $attendance_totals['rate'] ) ); ?>%</strong>
						</div>
					</div>

					<div class="cmp-table-scroll">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $students ) ) : ?>
									<tr><td colspan="4"><?php esc_html_e( 'No students are enrolled yet.', 'class-manager-pro' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $students as $student ) : ?>
										<tr>
											<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
											<td><?php echo esc_html( $student->phone ); ?></td>
											<td><?php echo esc_html( $student->email ); ?></td>
											<td><a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'id' => (int) $batch->id, 'student_id' => (int) $student->id ) ) ); ?>"><?php esc_html_e( 'View', 'class-manager-pro' ); ?></a></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</section>

				<?php if ( $selected_student ) : ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php echo esc_html( $selected_student->name ); ?></h2>
								<p class="cmp-muted"><?php echo esc_html( $selected_student->unique_id ); ?></p>
							</div>
							<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-teacher-console', array( 'id' => (int) $batch->id ) ) ); ?>"><?php esc_html_e( 'Back to Students', 'class-manager-pro' ); ?></a>
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

				<?php if ( cmp_is_attendance_enabled() ) : ?>
					<?php cmp_render_batch_attendance_panel( $batch, 'cmp-teacher-console' ); ?>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
