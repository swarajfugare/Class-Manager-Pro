<?php
/**
 * Batches admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a batch form.
 *
 * @param object|null $batch Batch row.
 * @param string      $return_page Return page slug.
 */
function cmp_render_batch_form( $batch = null, $return_page = 'cmp-batches' ) {
	$is_edit         = $batch && ! empty( $batch->id );
	$classes         = cmp_get_classes();
	$courses         = cmp_get_tutor_courses();
	$teachers        = cmp_get_teacher_users( $is_edit ? (int) $batch->teacher_user_id : 0 );
	$status          = $is_edit ? $batch->status : 'active';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
		<input type="hidden" name="action" value="cmp_save_batch">
		<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
		<?php endif; ?>
		<?php wp_nonce_field( 'cmp_save_batch' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmp-batch-name"><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-batch-name" name="batch_name" class="regular-text" value="<?php echo esc_attr( $is_edit ? $batch->batch_name : '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-class"><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-batch-class" name="class_id" required>
						<option value=""><?php esc_html_e( 'Select class', 'class-manager-pro' ); ?></option>
						<?php foreach ( $classes as $class ) : ?>
							<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $is_edit ? (int) $batch->class_id : 0, (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-teacher"><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-batch-teacher" name="teacher_user_id">
						<option value="0"><?php esc_html_e( 'No teacher assigned', 'class-manager-pro' ); ?></option>
						<?php foreach ( $teachers as $teacher ) : ?>
							<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( $is_edit ? (int) $batch->teacher_user_id : 0, (int) $teacher->ID ); ?>><?php echo esc_html( sprintf( '%1$s (#%2$d)', $teacher->display_name, (int) $teacher->ID ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-course"><?php esc_html_e( 'Tutor LMS Course', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-batch-course" name="course_id">
						<option value="0"><?php esc_html_e( 'No Tutor LMS course linked', 'class-manager-pro' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( (int) $course->ID ); ?>" <?php selected( $is_edit ? (int) $batch->course_id : 0, (int) $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php if ( cmp_is_tutor_lms_available() ) : ?>
							<?php esc_html_e( 'Students added to this batch are enrolled automatically in the selected Tutor LMS course.', 'class-manager-pro' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Tutor LMS is not active right now. You can still save the batch and link a course later.', 'class-manager-pro' ); ?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-start-date"><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></label></th>
				<td><input type="date" id="cmp-batch-start-date" name="start_date" value="<?php echo esc_attr( $is_edit ? $batch->start_date : '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-status"><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-batch-status" name="status">
						<?php foreach ( cmp_batch_statuses() as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( ucfirst( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-fee"><?php esc_html_e( 'Batch Fee', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="number" id="cmp-batch-fee" name="batch_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? cmp_get_batch_effective_fee( $batch ) : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Used as the default fee for new students in this batch.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-fee-due-date"><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="date" id="cmp-batch-fee-due-date" name="fee_due_date" value="<?php echo esc_attr( $is_edit ? $batch->fee_due_date : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Automatic reminder emails use this date for pending-fee students.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-razorpay-page-id"><?php esc_html_e( 'Razorpay Payment Page ID', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="text" id="cmp-batch-razorpay-page-id" name="razorpay_page_id" class="regular-text" value="<?php echo esc_attr( $is_edit ? $batch->razorpay_page_id : '' ); ?>" placeholder="pp_xxx">
					<p class="description"><?php esc_html_e( 'Save the Payment Page ID here, then use Import Students to pull captured payments into this batch.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-intake-limit"><?php esc_html_e( 'Max Students', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="number" id="cmp-batch-intake-limit" name="intake_limit" min="0" step="1" value="<?php echo esc_attr( $is_edit ? (int) $batch->intake_limit : '' ); ?>">
					<p class="description">
						<?php
						echo esc_html(
							$is_edit
								? sprintf(
									/* translators: 1: current students 2: maximum students */
									__( 'Current students: %1$d. Max students: %2$d. Imports and manual adds stop when this limit is reached. Leave empty or 0 for no limit.', 'class-manager-pro' ),
									(int) cmp_get_batch_student_count( (int) $batch->id ),
									(int) $batch->intake_limit
								)
								: __( 'Imports and manual adds stop when this limit is reached. Leave empty or 0 for no limit.', 'class-manager-pro' )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-class-days"><?php esc_html_e( 'Days', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-batch-class-days" name="class_days" class="regular-text" value="<?php echo esc_attr( $is_edit ? $batch->class_days : '' ); ?>" placeholder="<?php esc_attr_e( 'Mon, Wed, Fri', 'class-manager-pro' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-manual-income"><?php esc_html_e( 'Manual Income', 'class-manager-pro' ); ?></label></th>
				<td><input type="number" id="cmp-batch-manual-income" name="manual_income" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? (float) $batch->manual_income : '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-is-free"><?php esc_html_e( 'Free Batch', 'class-manager-pro' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="cmp-batch-is-free" name="is_free" value="1" <?php checked( $is_edit && ! empty( $batch->is_free ), true ); ?>>
						<?php esc_html_e( 'Students fill the intake form without Razorpay payment.', 'class-manager-pro' ); ?>
					</label>
				</td>
			</tr>
			<?php if ( $is_edit ) : ?>
				<?php if ( ! empty( $batch->razorpay_link ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Payment Page URL', 'class-manager-pro' ); ?></th>
						<td>
							<div class="cmp-inline-tools">
								<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $batch->razorpay_link ); ?>" id="cmp-batch-razorpay-url-<?php echo esc_attr( (int) $batch->id ); ?>">
								<button type="button" class="button" data-cmp-copy-target="#cmp-batch-razorpay-url-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy URL', 'class-manager-pro' ); ?></button>
								<a class="button" href="<?php echo esc_url( $batch->razorpay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Page', 'class-manager-pro' ); ?></a>
							</div>
						</td>
					</tr>
				<?php endif; ?>
			<?php endif; ?>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Batch', 'class-manager-pro' ) : __( 'Add Batch', 'class-manager-pro' ) ); ?>
	</form>
	<?php
}

/**
 * Renders the batch import tools panel.
 *
 * @param object $batch Batch row.
 * @param string $return_page Return page.
 * @param string $return_action Return action.
 */
function cmp_render_batch_import_tools( $batch, $return_page = 'cmp-batches', $return_action = 'view' ) {
	$import_workspace_url = cmp_admin_url(
		'cmp-razorpay-import',
		array(
			'class_id'         => (int) $batch->class_id,
			'batch_id'         => (int) $batch->id,
			'razorpay_page_id' => $batch->razorpay_page_id,
		)
	);
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Import from Razorpay', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Use the saved Payment Page ID to create or update students in this batch and store only new payment IDs.', 'class-manager-pro' ); ?></p>
			</div>
		</div>

		<?php if ( empty( $batch->razorpay_page_id ) ) : ?>
			<div class="cmp-callout cmp-callout-warning">
				<p><?php esc_html_e( 'Save a Razorpay Payment Page ID on this batch before importing students.', 'class-manager-pro' ); ?></p>
			</div>
		<?php else : ?>
			<div class="cmp-toolbar">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form" data-cmp-ajax-import="razorpay-page" data-cmp-feedback="#cmp-batch-import-feedback-<?php echo esc_attr( (int) $batch->id ); ?>">
					<input type="hidden" name="action" value="cmp_import_razorpay_page_to_batch">
					<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
					<input type="hidden" name="razorpay_page_id" value="<?php echo esc_attr( $batch->razorpay_page_id ); ?>">
					<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
					<input type="hidden" name="return_action" value="<?php echo esc_attr( $return_action ); ?>">
					<?php wp_nonce_field( 'cmp_import_razorpay_page_to_batch' ); ?>
					<?php submit_button( __( 'Import Students', 'class-manager-pro' ), 'primary', 'submit', false ); ?>
				</form>
				<a class="button" href="<?php echo esc_url( $import_workspace_url ); ?>"><?php esc_html_e( 'Preview Payments', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( $import_workspace_url . '#cmp-student-file-import' ); ?>"><?php esc_html_e( 'Import CSV / Excel', 'class-manager-pro' ); ?></a>
			</div>
			<p class="cmp-muted" id="cmp-batch-import-feedback-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Imports run without leaving this batch workspace.', 'class-manager-pro' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Renders the attendance panel inside a batch workspace.
 *
 * @param object $batch Batch row.
 * @param string $return_page Return page slug.
 * @return void
 */
function cmp_render_batch_attendance_panel( $batch, $return_page = 'cmp-batches' ) {
	$date              = sanitize_text_field( cmp_field( $_GET, 'attendance_date', current_time( 'Y-m-d' ) ) );
	$students          = cmp_get_students( array( 'batch_id' => (int) $batch->id ) );
	$records           = cmp_get_batch_attendance( (int) $batch->id, $date );
	$summary           = cmp_get_batch_attendance_summary( (int) $batch->id, $date );
	$default           = 'present';
	$teacher_user_id   = absint( cmp_field( $_GET, 'teacher_user_id', 0 ) );
	$date_strip        = cmp_get_batch_attendance_date_strip( (int) $batch->id, $date, 14 );
	$load_date_args    = array(
		'id'              => (int) $batch->id,
		'attendance_date' => $date,
	);
	$status_labels     = array(
		'present' => __( 'Present', 'class-manager-pro' ),
		'absent'  => __( 'Absent', 'class-manager-pro' ),
		'leave'   => __( 'Leave', 'class-manager-pro' ),
	);

	if ( 'cmp-teacher-console' === $return_page ) {
		$load_date_args['teacher_view'] = 'attendance';

		if ( $teacher_user_id ) {
			$load_date_args['teacher_user_id'] = $teacher_user_id;
		}
	} else {
		$load_date_args['action'] = 'view';
		$load_date_args['tab']    = 'attendance';
	}
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Attendance Workspace', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted">
					<?php esc_html_e( 'Choose a date, check the saved dots, and mark each student with simple attendance buttons.', 'class-manager-pro' ); ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<?php esc_html_e( ' Admins can edit any saved attendance from here.', 'class-manager-pro' ); ?>
					<?php endif; ?>
				</p>
			</div>
			<form method="get" class="cmp-inline-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( $return_page ); ?>">
				<input type="hidden" name="id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<?php if ( 'cmp-teacher-console' === $return_page ) : ?>
					<input type="hidden" name="teacher_view" value="attendance">
					<?php if ( $teacher_user_id ) : ?>
						<input type="hidden" name="teacher_user_id" value="<?php echo esc_attr( $teacher_user_id ); ?>">
					<?php endif; ?>
				<?php else : ?>
					<input type="hidden" name="action" value="view">
					<input type="hidden" name="tab" value="attendance">
				<?php endif; ?>
				<input type="date" name="attendance_date" value="<?php echo esc_attr( $date ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Load Date', 'class-manager-pro' ); ?></button>
			</form>
		</div>

		<div class="cmp-attendance-date-strip" aria-label="<?php esc_attr_e( 'Recent attendance dates', 'class-manager-pro' ); ?>">
			<?php foreach ( $date_strip as $date_item ) : ?>
				<?php
				$item_args = $load_date_args;
				$item_args['attendance_date'] = $date_item['date'];
				$item_classes = 'cmp-attendance-date-pill';

				if ( ! empty( $date_item['is_selected'] ) ) {
					$item_classes .= ' is-active';
				}

				if ( ! empty( $date_item['has_records'] ) ) {
					$item_classes .= ' is-recorded';
				}

				$item_title = ! empty( $date_item['has_records'] )
					? sprintf(
						/* translators: 1: present count 2: absent count 3: leave count */
						__( 'Present: %1$d, Absent: %2$d, Leave: %3$d', 'class-manager-pro' ),
						(int) $date_item['summary']['present'],
						(int) $date_item['summary']['absent'],
						(int) $date_item['summary']['leave']
					)
					: __( 'No attendance saved yet.', 'class-manager-pro' );
				?>
				<a class="<?php echo esc_attr( $item_classes ); ?>" href="<?php echo esc_url( cmp_admin_url( $return_page, $item_args ) ); ?>" title="<?php echo esc_attr( $item_title ); ?>">
					<span><?php echo esc_html( $date_item['day_label'] ); ?></span>
					<strong><?php echo esc_html( $date_item['date_label'] ); ?></strong>
					<i class="cmp-attendance-dot<?php echo ! empty( $date_item['has_records'] ) ? ' is-visible' : ''; ?>" data-cmp-attendance-date-dot="<?php echo esc_attr( $date_item['date'] ); ?>"></i>
				</a>
			<?php endforeach; ?>
		</div>
		<p class="cmp-muted"><?php esc_html_e( 'A small dot means attendance is already saved for that date.', 'class-manager-pro' ); ?></p>

		<div class="cmp-cards cmp-cards-3">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></span>
				<strong data-cmp-attendance-summary="present"><?php echo esc_html( number_format_i18n( $summary['present'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Absent', 'class-manager-pro' ); ?></span>
				<strong data-cmp-attendance-summary="absent"><?php echo esc_html( number_format_i18n( $summary['absent'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'On Leave', 'class-manager-pro' ); ?></span>
				<strong data-cmp-attendance-summary="leave"><?php echo esc_html( number_format_i18n( $summary['leave'] ) ); ?></strong>
			</div>
		</div>

		<?php if ( empty( $students ) ) : ?>
			<p><?php esc_html_e( 'No students are enrolled in this batch yet.', 'class-manager-pro' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-attendance-form="1">
				<input type="hidden" name="action" value="cmp_save_attendance">
				<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<input type="hidden" name="attendance_date" value="<?php echo esc_attr( $date ); ?>">
				<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
				<?php if ( 'cmp-teacher-console' === $return_page ) : ?>
					<input type="hidden" name="teacher_view" value="attendance">
					<?php if ( $teacher_user_id ) : ?>
						<input type="hidden" name="teacher_user_id" value="<?php echo esc_attr( $teacher_user_id ); ?>">
					<?php endif; ?>
				<?php endif; ?>
				<?php wp_nonce_field( 'cmp_save_attendance' ); ?>

				<div class="cmp-attendance-grid">
					<?php foreach ( $students as $student ) : ?>
						<?php
						$record         = isset( $records[ (int) $student->id ] ) ? $records[ (int) $student->id ] : null;
						$current_status = $record ? $record->status : $default;
						?>
						<article class="cmp-attendance-student-card">
							<div class="cmp-attendance-student-head">
								<div>
									<h3><?php echo esc_html( $student->name ); ?></h3>
									<p class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></p>
								</div>
								<div class="cmp-attendance-student-meta">
									<span><?php echo esc_html( $student->phone ? $student->phone : __( 'Phone not set', 'class-manager-pro' ) ); ?></span>
									<?php if ( ! empty( $student->email ) ) : ?>
										<span><?php echo esc_html( $student->email ); ?></span>
									<?php endif; ?>
								</div>
							</div>

							<div class="cmp-attendance-options" data-cmp-attendance-toggle="1">
								<input type="hidden" name="attendance[<?php echo esc_attr( (int) $student->id ); ?>][status]" value="<?php echo esc_attr( $current_status ); ?>" data-cmp-attendance-status-input>
								<?php foreach ( cmp_attendance_statuses() as $status ) : ?>
									<?php
									$option_classes = 'button cmp-attendance-option';

									if ( $current_status === $status ) {
										$option_classes .= ' is-selected is-' . sanitize_html_class( $status );
									}
									?>
									<button type="button" class="<?php echo esc_attr( $option_classes ); ?>" data-cmp-attendance-set="<?php echo esc_attr( $status ); ?>">
										<?php echo esc_html( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status ) ); ?>
									</button>
								<?php endforeach; ?>
							</div>

						</article>
					<?php endforeach; ?>
				</div>

				<p class="cmp-muted" data-cmp-attendance-feedback><?php esc_html_e( 'Attendance updates save directly to this batch.', 'class-manager-pro' ); ?></p>
				<?php submit_button( __( 'Save Attendance', 'class-manager-pro' ) ); ?>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Renders the batch students tab.
 *
 * @param object $batch Batch row.
 */
function cmp_render_batch_students_tab( $batch ) {
	$students = cmp_get_students( array( 'batch_id' => (int) $batch->id ) );
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Manage students for this batch from one focused workspace.', 'class-manager-pro' ); ?></p>
			</div>
			<div class="cmp-toolbar">
				<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id ) ) ); ?>#cmp-add-student"><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id ) ) ); ?>"><?php esc_html_e( 'Open Full Student List', 'class-manager-pro' ); ?></a>
				<span class="cmp-muted" id="cmp-batch-students-feedback"></span>
			</div>
		</div>

		<div class="cmp-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $students ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No students in this batch yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $students as $student ) : ?>
							<?php
							$email_url    = cmp_get_email_reminder_url(
								$student,
								'cmp-batches',
								array(
									'action' => 'view',
									'id'     => (int) $batch->id,
									'tab'    => 'students',
								)
							);
							$whatsapp_url = cmp_get_whatsapp_reminder_url( $student );
							$view_url     = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
							?>
							<tr>
								<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
								<td><?php echo esc_html( $student->phone ); ?></td>
								<td><?php echo esc_html( $student->email ? $student->email : __( 'Not set', 'class-manager-pro' ) ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></td>
								<td><?php echo esc_html( cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) ) ); ?></td>
								<td class="cmp-actions">
									<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'class-manager-pro' ); ?></a>
									<?php if ( $email_url ) : ?>
										<a class="cmp-send-email-link" href="<?php echo esc_url( $email_url ); ?>" data-cmp-send-email="1" data-cmp-student-id="<?php echo esc_attr( (int) $student->id ); ?>" data-cmp-feedback="#cmp-batch-students-feedback"><?php esc_html_e( 'Send Email', 'class-manager-pro' ); ?></a>
									<?php endif; ?>
									<?php if ( $whatsapp_url ) : ?>
										<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
}

/**
 * Renders the batch expenses tab.
 *
 * @param object $batch Batch row.
 * @param array  $metrics Batch metrics.
 * @return void
 */
function cmp_render_batch_expenses_tab( $batch, $metrics ) {
	$totals            = cmp_get_batch_expense_totals( (int) $batch->id );
	$expenses          = cmp_get_batch_expenses( (int) $batch->id );
	$category_labels   = cmp_expense_category_labels();
	$default_date      = current_time( 'Y-m-d' );
	$expenses_count    = count( $expenses );
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Expenses', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Track teacher payment, Meta ads spend, ads material cost, and other expenses directly inside this batch.', 'class-manager-pro' ); ?></p>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-3">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Teacher Payment', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $totals['teacher_payment'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Meta Ads Spend', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $totals['meta_ads'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Ads Material Cost', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $totals['ad_material'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Other Expenses', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $totals['other'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Expense', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $totals['total_expense'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Net Income', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['net_income'] ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel cmp-panel-nested">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'Add Expense', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Save each spend entry against the correct category so batch profitability stays accurate.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_save_expense">
					<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
					<input type="hidden" name="return_tab" value="expenses">
					<?php wp_nonce_field( 'cmp_save_expense' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-expense-category"><?php esc_html_e( 'Category', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-expense-category" name="category">
									<?php foreach ( $category_labels as $category_key => $category_label ) : ?>
										<option value="<?php echo esc_attr( $category_key ); ?>"><?php echo esc_html( $category_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-expense-amount"><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></label></th>
							<td><input type="number" id="cmp-expense-amount" name="amount" min="0" step="0.01" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-expense-date"><?php esc_html_e( 'Expense Date', 'class-manager-pro' ); ?></label></th>
							<td><input type="date" id="cmp-expense-date" name="expense_date" value="<?php echo esc_attr( $default_date ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-expense-notes"><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></label></th>
							<td><textarea id="cmp-expense-notes" name="notes" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Add a short note for this expense entry', 'class-manager-pro' ); ?>"></textarea></td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Expense', 'class-manager-pro' ) ); ?>
				</form>
			</section>

			<section class="cmp-panel cmp-panel-nested">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'Expense Summary', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php echo esc_html( sprintf( _n( '%d expense entry recorded for this batch.', '%d expense entries recorded for this batch.', $expenses_count, 'class-manager-pro' ), $expenses_count ) ); ?></p>
					</div>
				</div>

				<div class="cmp-detail-grid">
					<p><span><?php esc_html_e( 'Collected Revenue', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $metrics['revenue'] ) ); ?></strong></p>
					<p><span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $metrics['pending_fee'] ) ); ?></strong></p>
					<p><span><?php esc_html_e( 'Teacher Payment', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $totals['teacher_payment'] ) ); ?></strong></p>
					<p><span><?php esc_html_e( 'Ads Spend', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $totals['ads_spend'] ) ); ?></strong></p>
					<p><span><?php esc_html_e( 'Other Expenses', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $totals['other'] ) ); ?></strong></p>
					<p><span><?php esc_html_e( 'Net Income', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $metrics['net_income'] ) ); ?></strong></p>
				</div>
			</section>
		</div>

		<section class="cmp-panel cmp-panel-nested">
			<div class="cmp-panel-header">
				<div>
					<h3><?php esc_html_e( 'Expense Entries', 'class-manager-pro' ); ?></h3>
					<p class="cmp-muted"><?php esc_html_e( 'Review every expense entry saved for this batch.', 'class-manager-pro' ); ?></p>
				</div>
			</div>

			<div class="cmp-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Category', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $expenses ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No expense entries added for this batch yet.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $expenses as $expense ) : ?>
								<?php
								$delete_url = wp_nonce_url(
									admin_url(
										'admin-post.php?action=cmp_delete_expense&id=' . (int) $expense->id . '&batch_id=' . (int) $batch->id . '&tab=expenses'
									),
									'cmp_delete_expense_' . (int) $expense->id
								);
								?>
								<tr>
									<td><?php echo esc_html( $expense->expense_date ); ?></td>
									<td><?php echo esc_html( isset( $category_labels[ $expense->category ] ) ? $category_labels[ $expense->category ] : ucfirst( str_replace( '_', ' ', $expense->category ) ) ); ?></td>
									<td><?php echo esc_html( $expense->notes ? $expense->notes : __( 'No notes', 'class-manager-pro' ) ); ?></td>
									<td><?php echo esc_html( cmp_format_money( $expense->amount ) ); ?></td>
									<td class="cmp-actions">
										<a class="cmp-delete-link" href="<?php echo esc_url( $delete_url ); ?>" data-cmp-confirm="<?php esc_attr_e( 'Delete this expense entry?', 'class-manager-pro' ); ?>"><?php esc_html_e( 'Delete', 'class-manager-pro' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</section>
	<?php
}

/**
 * Renders the batch registration links panel.
 *
 * @param object $batch Batch row.
 * @return void
 */
function cmp_render_batch_registration_links_panel( $batch ) {
	$temp_token      = cmp_get_batch_registration_token_for_batch( (int) $batch->id );
	$temp_link_url   = $temp_token ? cmp_get_batch_temporary_registration_url( $temp_token ) : '';
	?>
	<section class="cmp-panel" id="cmp-batch-registration-links">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Registration Links', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Generate a temporary registration link that stays active for 10 minutes from creation time unless you close it earlier.', 'class-manager-pro' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
				<input type="hidden" name="action" value="cmp_generate_batch_registration_link">
				<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<?php wp_nonce_field( 'cmp_generate_batch_registration_link_' . (int) $batch->id ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Link', 'class-manager-pro' ); ?></button>
			</form>
		</div>

		<section class="cmp-panel cmp-panel-nested">
			<div class="cmp-panel-header">
				<div>
					<h3><?php esc_html_e( 'Temporary Registration Link', 'class-manager-pro' ); ?></h3>
					<p class="cmp-muted"><?php esc_html_e( 'Only temporary registration links are available for this batch.', 'class-manager-pro' ); ?></p>
				</div>
			</div>

			<?php if ( $temp_token && $temp_link_url ) : ?>
				<div class="cmp-inline-tools">
					<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $temp_link_url ); ?>" id="cmp-batch-temp-link-<?php echo esc_attr( (int) $batch->id ); ?>">
					<button type="button" class="button" data-cmp-copy-target="#cmp-batch-temp-link-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy Link', 'class-manager-pro' ); ?></button>
					<a class="button" href="<?php echo esc_url( $temp_link_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Form', 'class-manager-pro' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
						<input type="hidden" name="action" value="cmp_close_batch_registration_link">
						<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
						<?php wp_nonce_field( 'cmp_close_batch_registration_link_' . (int) $batch->id ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Close Link', 'class-manager-pro' ); ?></button>
					</form>
				</div>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: expiry datetime */
							__( 'Active until %s.', 'class-manager-pro' ),
							$temp_token->expires_at
						)
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No active temporary registration link is available right now.', 'class-manager-pro' ); ?></p>
			<?php endif; ?>
		</section>
	</section>
	<?php
}

/**
 * Renders the batch announcements tab.
 *
 * @param object $batch Batch row.
 * @return void
 */
function cmp_render_batch_announcements_tab( $batch ) {
	$announcements     = cmp_get_batch_announcements( (int) $batch->id, 20 );
	$whatsapp_enabled  = cmp_is_whatsapp_delivery_enabled();
	$email_formats     = cmp_get_announcement_email_formats();
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Send Announcement', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Send one message to every student in this batch by email, with optional WhatsApp delivery when notifications are enabled.', 'class-manager-pro' ); ?></p>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel cmp-panel-nested">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_send_batch_announcement">
					<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
					<?php wp_nonce_field( 'cmp_send_batch_announcement_' . (int) $batch->id ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-announcement-subject"><?php esc_html_e( 'Subject', 'class-manager-pro' ); ?></label></th>
							<td><input type="text" id="cmp-announcement-subject" name="subject" class="regular-text" placeholder="<?php esc_attr_e( 'Batch announcement subject', 'class-manager-pro' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-announcement-message"><?php esc_html_e( 'Message', 'class-manager-pro' ); ?></label></th>
							<td>
								<textarea id="cmp-announcement-message" name="message" rows="8" class="large-text" required placeholder="<?php esc_attr_e( 'Write the announcement you want every student in this batch to receive.', 'class-manager-pro' ); ?>"></textarea>
								<p class="description"><?php esc_html_e( 'Email is always sent. Keep the message clear and short so it also works well on WhatsApp.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-announcement-email-format"><?php esc_html_e( 'Email Format', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-announcement-email-format" name="email_format">
									<?php foreach ( $email_formats as $format_key => $format_label ) : ?>
										<option value="<?php echo esc_attr( $format_key ); ?>"><?php echo esc_html( $format_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Choose HTML Email when you want to send styled email content. WhatsApp delivery always uses a plain-text version.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></th>
							<td>
								<?php if ( $whatsapp_enabled ) : ?>
									<label>
										<input type="checkbox" name="send_whatsapp" value="1">
										<?php esc_html_e( 'Also send through the configured WhatsApp/webhook channel.', 'class-manager-pro' ); ?>
									</label>
								<?php else : ?>
									<p><?php esc_html_e( 'WhatsApp delivery is currently disabled in Settings. Email delivery will still be sent.', 'class-manager-pro' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Send Announcement', 'class-manager-pro' ) ); ?>
				</form>
			</section>

			<section class="cmp-panel cmp-panel-nested">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'Announcement History', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Review the latest announcement runs for this batch.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Subject', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Channels', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Results', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'By', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $announcements ) ) : ?>
								<tr><td colspan="6"><?php esc_html_e( 'No announcements have been sent for this batch yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $announcements as $announcement ) : ?>
									<?php
									$announcement_email_format = isset( $announcement->email_format ) ? $announcement->email_format : 'plain';
									$announcement_retry_count  = isset( $announcement->retry_count ) ? (int) $announcement->retry_count : 0;
									$has_failed_recipients     = ! empty( $announcement->failed_email_recipients );
									?>
									<tr>
										<td><?php echo esc_html( $announcement->created_at ); ?></td>
										<td><?php echo esc_html( $announcement->subject ); ?></td>
										<td>
											<?php echo esc_html( strtoupper( str_replace( ',', ' + ', $announcement->channels ) ) ); ?>
											<br><span class="cmp-muted"><?php echo esc_html( isset( $email_formats[ $announcement_email_format ] ) ? $email_formats[ $announcement_email_format ] : ucfirst( $announcement_email_format ) ); ?></span>
										</td>
										<td>
											<?php
											echo esc_html(
												sprintf(
													/* translators: 1: email sent 2: email failed 3: WhatsApp sent 4: WhatsApp failed */
													__( 'Email %1$d sent / %2$d failed. WhatsApp %3$d sent / %4$d failed.', 'class-manager-pro' ),
													(int) $announcement->email_sent,
													(int) $announcement->email_failed,
													(int) $announcement->whatsapp_sent,
													(int) $announcement->whatsapp_failed
												)
											);
											?>
											<?php if ( $announcement_retry_count > 0 ) : ?>
												<br><span class="cmp-muted"><?php echo esc_html( sprintf( __( 'Retries: %d', 'class-manager-pro' ), $announcement_retry_count ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $announcement->created_by_name ? $announcement->created_by_name : __( 'System', 'class-manager-pro' ) ); ?></td>
										<td class="cmp-actions">
											<?php if ( (int) $announcement->email_failed > 0 && $has_failed_recipients ) : ?>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
													<input type="hidden" name="action" value="cmp_retry_batch_announcement_failed_emails">
													<input type="hidden" name="announcement_id" value="<?php echo esc_attr( (int) $announcement->id ); ?>">
													<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
													<?php wp_nonce_field( 'cmp_retry_batch_announcement_failed_emails_' . (int) $announcement->id ); ?>
													<button type="submit" class="button button-small"><?php esc_html_e( 'Send Failed Emails', 'class-manager-pro' ); ?></button>
												</form>
											<?php else : ?>
												<span class="cmp-muted"><?php esc_html_e( 'No failed emails', 'class-manager-pro' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</section>
	<?php
}

/**
 * Renders the batch detail panel.
 *
 * @param object $batch Batch row.
 */
function cmp_render_batch_detail_panel( $batch ) {
	$metrics         = cmp_get_batch_metrics( (int) $batch->id );
	$course_title    = ! empty( $batch->course_id ) ? cmp_get_tutor_course_title( (int) $batch->course_id ) : __( 'Not linked', 'class-manager-pro' );
	$course_url      = ! empty( $batch->course_id ) ? cmp_get_tutor_course_url( (int) $batch->course_id ) : '';
	$current_tab     = sanitize_key( cmp_field( $_GET, 'tab', 'students' ) );

	if ( ! in_array( $current_tab, array( 'students', 'attendance', 'expenses', 'announcements' ), true ) ) {
		$current_tab = 'students';
	}
	?>
	<div class="wrap cmp-wrap">
		<h1><?php echo esc_html( $batch->batch_name ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'This page is focused on one batch only: students, attendance, expenses, import, and linked learning details.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<div class="cmp-toolbar">
			<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>"><?php esc_html_e( 'Back to Batches', 'class-manager-pro' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $batch->id ) ) ); ?>"><?php esc_html_e( 'Edit Batch Info', 'class-manager-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id ) ) ); ?>#cmp-add-student"><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-razorpay-import', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id, 'razorpay_page_id' => $batch->razorpay_page_id ) ) ); ?>"><?php esc_html_e( 'Import from Razorpay', 'class-manager-pro' ); ?></a>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
				<input type="hidden" name="action" value="cmp_generate_batch_registration_link">
				<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<?php wp_nonce_field( 'cmp_generate_batch_registration_link_' . (int) $batch->id ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Generate Link', 'class-manager-pro' ); ?></button>
			</form>
			<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'announcements' ) ) ); ?>"><?php esc_html_e( 'Send Announcement', 'class-manager-pro' ); ?></a>
			<?php if ( $course_url ) : ?>
				<a class="button" href="<?php echo esc_url( $course_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Course', 'class-manager-pro' ); ?></a>
			<?php endif; ?>
		</div>

		<div class="cmp-cards cmp-cards-3">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['student_count'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Collected', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['revenue'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fee'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Expense', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['expense'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Net Income', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['net_income'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( ucfirst( $batch->status ) ); ?></strong>
			</div>
		</div>

		<section class="cmp-panel">
			<div class="cmp-detail-grid">
				<p><span><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->class_name ); ?></strong></p>
				<p><span><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_get_teacher_label( (int) $batch->teacher_user_id ) ); ?></strong></p>
				<p><span><?php esc_html_e( 'Tutor LMS Course', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $course_title ); ?></strong></p>
				<p><span><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->start_date ? $batch->start_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
				<p><span><?php esc_html_e( 'Batch Fee', 'class-manager-pro' ); ?></span><strong><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free', 'class-manager-pro' ) : esc_html( cmp_format_money( cmp_get_batch_effective_fee( $batch ) ) ); ?></strong></p>
				<p><span><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->fee_due_date ? $batch->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
				<p><span><?php esc_html_e( 'Payment Page ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->razorpay_page_id ? $batch->razorpay_page_id : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
				<p><span><?php esc_html_e( 'Days', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->class_days ? $batch->class_days : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			</div>
		</section>

		<?php cmp_render_batch_registration_links_panel( $batch ); ?>
		<?php cmp_render_batch_import_tools( $batch, 'cmp-batches', 'view' ); ?>

		<nav class="nav-tab-wrapper cmp-tab-nav" aria-label="<?php esc_attr_e( 'Batch sections', 'class-manager-pro' ); ?>">
			<a class="nav-tab <?php echo 'students' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'students' ) ) ); ?>"><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></a>
			<?php if ( cmp_is_attendance_enabled() ) : ?>
				<a class="nav-tab <?php echo 'attendance' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'attendance' ) ) ); ?>"><?php esc_html_e( 'Attendance', 'class-manager-pro' ); ?></a>
			<?php endif; ?>
			<a class="nav-tab <?php echo 'expenses' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'expenses' ) ) ); ?>"><?php esc_html_e( 'Expenses', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'announcements' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'announcements' ) ) ); ?>"><?php esc_html_e( 'Announcements', 'class-manager-pro' ); ?></a>
		</nav>

		<?php if ( 'attendance' === $current_tab && cmp_is_attendance_enabled() ) : ?>
			<?php cmp_render_batch_attendance_panel( $batch ); ?>
		<?php elseif ( 'expenses' === $current_tab ) : ?>
			<?php cmp_render_batch_expenses_tab( $batch, $metrics ); ?>
		<?php elseif ( 'announcements' === $current_tab ) : ?>
			<?php cmp_render_batch_announcements_tab( $batch ); ?>
		<?php else : ?>
			<?php cmp_render_batch_students_tab( $batch ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders the batches page.
 */
function cmp_render_batches_page() {
	cmp_require_manage_options();

	$action      = sanitize_key( cmp_field( $_GET, 'action' ) );
	$id          = absint( cmp_field( $_GET, 'id', 0 ) );
	$batch       = $id ? cmp_get_batch( $id ) : null;
	$edit_batch  = ( 'edit' === $action && $batch ) ? $batch : null;
	$view_batch  = ( 'view' === $action && $batch ) ? $batch : null;
	$metrics     = cmp_get_batch_overview_metrics();
	$batch_rows  = cmp_get_batches_with_metrics();
	$list_url    = cmp_admin_url( 'cmp-batches' );
	$import_url  = cmp_admin_url( 'cmp-razorpay-import' );
	$add_url     = cmp_admin_url( 'cmp-batches', array( 'action' => 'add' ) );

	if ( $view_batch ) {
		cmp_render_batch_detail_panel( $view_batch );
		return;
	}
	?>
	<div class="wrap cmp-wrap">
		<h1><?php echo esc_html( $edit_batch ? __( 'Edit Batch', 'class-manager-pro' ) : ( 'add' === $action ? __( 'Add Batch', 'class-manager-pro' ) : __( 'Batches', 'class-manager-pro' ) ) ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Keep the batch list simple, then open a single batch when you need students, attendance, or Razorpay import.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<?php if ( $edit_batch || 'add' === $action ) : ?>
			<div class="cmp-toolbar">
				<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Back to Batch List', 'class-manager-pro' ); ?></a>
				<?php if ( $edit_batch ) : ?>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $edit_batch->id ) ) ); ?>"><?php esc_html_e( 'View Batch', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
			</div>

			<section class="cmp-panel">
				<?php cmp_render_batch_form( $edit_batch, 'cmp-batches' ); ?>
			</section>

			<?php if ( $edit_batch ) : ?>
				<?php cmp_render_batch_import_tools( $edit_batch, 'cmp-batches', 'view' ); ?>
			<?php endif; ?>
			</div>
			<?php
			return;
		endif;
		?>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_batches'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Active Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['active_batches'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['total_revenue'] ) ); ?></strong>
			</div>
		</div>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Batch List', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Open one batch at a time for students, attendance, import, and linked course details.', 'class-manager-pro' ); ?></p>
				</div>
				<div class="cmp-toolbar">
					<a class="button button-primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Batch', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( $import_url ); ?>"><?php esc_html_e( 'Import from Razorpay', 'class-manager-pro' ); ?></a>
				</div>
			</div>

			<div class="cmp-toolbar cmp-bulk-toolbar">
				<?php wp_nonce_field( 'cmp_admin_nonce', 'cmp_admin_ajax_nonce' ); ?>
				<select id="cmp-batch-bulk-action">
					<option value=""><?php esc_html_e( 'Bulk actions', 'class-manager-pro' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete selected', 'class-manager-pro' ); ?></option>
					<option value="change_status"><?php esc_html_e( 'Change status', 'class-manager-pro' ); ?></option>
					<option value="export"><?php esc_html_e( 'Export selected', 'class-manager-pro' ); ?></option>
				</select>
				<select id="cmp-batch-bulk-status">
					<option value=""><?php esc_html_e( 'Choose status', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_batch_statuses() as $status ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<button
					type="button"
					class="button button-secondary"
					data-cmp-bulk-apply="1"
					data-cmp-entity-type="batch"
					data-cmp-action-select="#cmp-batch-bulk-action"
					data-cmp-status-select="#cmp-batch-bulk-status"
					data-cmp-checkbox=".cmp-batch-select"
					data-cmp-feedback="#cmp-batch-bulk-feedback"
				><?php esc_html_e( 'Apply', 'class-manager-pro' ); ?></button>
				<span class="cmp-muted" id="cmp-batch-bulk-feedback"></span>
			</div>

			<div class="cmp-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><input type="checkbox" id="cmp-batch-select-all" data-cmp-select-all=".cmp-batch-select"></th>
							<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Tutor Course', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Payment Page ID', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $batch_rows ) ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'No batches found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $batch_rows as $row ) : ?>
								<?php
								$view_url        = cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) );
								$edit_url        = cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $row->id ) );
								$import_row_url  = cmp_admin_url( 'cmp-razorpay-import', array( 'class_id' => (int) $row->class_id, 'batch_id' => (int) $row->id, 'razorpay_page_id' => $row->razorpay_page_id ) );
								$delete_url      = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_delete_batch&id=' . (int) $row->id ), 'cmp_delete_batch_' . (int) $row->id );
								$course_title    = ! empty( $row->course_id ) ? cmp_get_tutor_course_title( (int) $row->course_id ) : __( 'Not linked', 'class-manager-pro' );
								?>
								<tr data-cmp-row-id="batch-<?php echo esc_attr( (int) $row->id ); ?>">
									<td><input type="checkbox" class="cmp-batch-select" value="<?php echo esc_attr( (int) $row->id ); ?>"></td>
									<td><?php echo esc_html( $row->batch_name ); ?></td>
									<td><?php echo esc_html( $row->class_name ); ?></td>
									<td><?php echo esc_html( cmp_get_teacher_label( (int) $row->teacher_user_id ) ); ?></td>
									<td><?php echo esc_html( $course_title ); ?></td>
									<td><?php echo esc_html( $row->razorpay_page_id ? $row->razorpay_page_id : __( 'Not set', 'class-manager-pro' ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row->student_count ) ); ?></td>
									<td><?php echo esc_html( $row->start_date ? $row->start_date : __( 'Not set', 'class-manager-pro' ) ); ?></td>
									<td><span class="cmp-status cmp-status-<?php echo esc_attr( $row->status ); ?>" data-cmp-status-badge="batch-<?php echo esc_attr( (int) $row->id ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
									<td class="cmp-actions">
										<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View Batch', 'class-manager-pro' ); ?></a>
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
										<a href="<?php echo esc_url( $import_row_url ); ?>"><?php esc_html_e( 'Import', 'class-manager-pro' ); ?></a>
										<a
											class="cmp-delete-link"
											href="<?php echo esc_url( $delete_url ); ?>"
											data-id="<?php echo esc_attr( (int) $row->id ); ?>"
											data-type="batch"
											data-cmp-ajax-delete="1"
											data-cmp-entity-type="batch"
											data-cmp-entity-id="<?php echo esc_attr( (int) $row->id ); ?>"
											data-cmp-confirm="<?php esc_attr_e( 'Delete this batch?', 'class-manager-pro' ); ?>"
											data-cmp-feedback="#cmp-batch-bulk-feedback"
										><?php esc_html_e( 'Delete', 'class-manager-pro' ); ?></a>
									</td>
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
