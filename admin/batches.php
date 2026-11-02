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
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="cmp_save_batch_ajax">
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
					<input type="text" id="cmp-batch-razorpay-page-id" name="razorpay_page_id" class="regular-text" value="<?php echo esc_attr( $is_edit ? $batch->razorpay_page_id : '' ); ?>" placeholder="pl_xxx (e.g. pl_TtZ3AHnvOHwHw2)">
					<p class="description"><?php esc_html_e( 'Enter your Razorpay Payment Page ID (e.g. pl_TtZ3AHnvOHwHw2) or full Payment Page URL. Captured payments will automatically enroll into this batch.', 'class-manager-pro' ); ?></p>
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
			<tr>
				<th scope="row"><label for="cmp-batch-permanent-registration-enabled"><?php esc_html_e( 'Permanent Registration Link', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="hidden" name="permanent_registration_enabled" value="0">
					<label>
						<input type="checkbox" id="cmp-batch-permanent-registration-enabled" name="permanent_registration_enabled" value="1" <?php checked( $is_edit && ! empty( $batch->permanent_registration_enabled ), true ); ?>>
						<?php esc_html_e( 'Enable one permanent public link for this free batch.', 'class-manager-pro' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Free batches can stay open on one permanent URL. Paid batches keep using secure temporary links.', 'class-manager-pro' ); ?></p>
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
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form" data-cmp-ajax-import="razorpay-page" data-cmp-feedback="#cmp-batch-import-feedback-<?php echo esc_attr( (int) $batch->id ); ?>" data-cmp-busy-label="<?php esc_attr_e( 'Syncing payments from Razorpay...', 'class-manager-pro' ); ?>">
					<input type="hidden" name="action" value="cmp_import_razorpay_page_to_batch">
					<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
					<input type="hidden" name="razorpay_page_id" value="<?php echo esc_attr( $batch->razorpay_page_id ); ?>">
					<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
					<input type="hidden" name="return_action" value="<?php echo esc_attr( $return_action ); ?>">
					<?php wp_nonce_field( 'cmp_import_razorpay_page_to_batch' ); ?>
					<?php submit_button( __( 'Import Students from Razorpay', 'class-manager-pro' ), 'primary', 'submit', false ); ?>
				</form>
				<a class="button" href="<?php echo esc_url( $import_workspace_url ); ?>"><?php esc_html_e( 'Preview Payments', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( $import_workspace_url . '#cmp-student-file-import' ); ?>"><?php esc_html_e( 'Import CSV / Excel', 'class-manager-pro' ); ?></a>
			</div>
			<p class="cmp-muted" id="cmp-batch-import-feedback-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Click "Import Students from Razorpay" to directly sync payments for this batch without leaving this page.', 'class-manager-pro' ); ?></p>
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
				<h2><?php esc_html_e( 'Enrolled Students', 'class-manager-pro' ); ?> (<?php echo esc_html( count( $students ) ); ?>)</h2>
			</div>
			<div class="cmp-toolbar">
				<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id ) ) ); ?>#cmp-add-student"><?php esc_html_e( '+ Add Student', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id ) ) ); ?>"><?php esc_html_e( 'All Students', 'class-manager-pro' ); ?></a>
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
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Quick Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $students ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No students enrolled in this batch yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $students as $student ) : ?>
							<?php
							$view_url     = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
							$pay_url      = cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id, 'batch_id' => (int) $batch->id ) ) . '#cmp-add-payment';
							$clean_phone  = preg_replace( '/\D+/', '', (string) $student->phone );
							$wa_url       = ! empty( $clean_phone ) ? 'https://wa.me/' . ( strlen( $clean_phone ) === 10 ? '91' . $clean_phone : $clean_phone ) : '';
							$pending_fee  = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
							?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $student->name ); ?></a></strong>
									<?php if ( ! empty( $student->unique_id ) ) : ?>
										<br><span class="cmp-muted" style="font-size:11px;"><?php echo esc_html( $student->unique_id ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $student->phone ); ?></td>
								<td><?php echo esc_html( $student->email ? $student->email : '-' ); ?></td>
								<td><strong><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></strong></td>
								<td>
									<?php if ( $pending_fee > 0 ) : ?>
										<span style="color:#d63638;font-weight:600;"><?php echo esc_html( cmp_format_money( $pending_fee ) ); ?></span>
									<?php else : ?>
										<span style="color:#008a20;font-weight:600;">✓ <?php esc_html_e( 'Paid', 'class-manager-pro' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span class="cmp-badge-pill <?php echo 'active' === $student->status ? 'cmp-badge-active' : 'cmp-badge-completed'; ?>">
										<?php echo esc_html( ucfirst( $student->status ) ); ?>
									</span>
								</td>
								<td class="cmp-actions" style="white-space:nowrap;">
									<a class="button button-small" href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'Profile', 'class-manager-pro' ); ?></a>
									<?php
									$pending_utr = cmp_get_student_pending_utr( $student );
									if ( $pending_utr ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0;">
											<input type="hidden" name="action" value="cmp_approve_temp_admission_utr">
											<input type="hidden" name="student_id" value="<?php echo esc_attr( (int) $student->id ); ?>">
											<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
											<input type="hidden" name="utr_number" value="<?php echo esc_attr( $pending_utr['utr'] ); ?>">
											<?php wp_nonce_field( 'cmp_approve_utr_' . (int) $student->id ); ?>
											<button type="submit" class="button button-small button-primary" style="background:#008a20;border-color:#00731a;color:#fff;" title="<?php echo esc_attr( sprintf( __( 'Approve UTR: %s (UPI)', 'class-manager-pro' ), $pending_utr['utr'] ) ); ?>">
												✓ <?php esc_html_e( 'Approve UTR', 'class-manager-pro' ); ?>
											</button>
										</form>
									<?php elseif ( $pending_fee > 0 ) : ?>
										<a class="button button-small button-primary" href="<?php echo esc_url( $pay_url ); ?>"><?php esc_html_e( '+ Pay', 'class-manager-pro' ); ?></a>
									<?php endif; ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0;">
										<input type="hidden" name="action" value="cmp_remove_student_from_batch">
										<input type="hidden" name="student_id" value="<?php echo esc_attr( (int) $student->id ); ?>">
										<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
										<?php wp_nonce_field( 'cmp_remove_student_from_batch_' . (int) $student->id ); ?>
										<button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to remove this student from this batch?', 'class-manager-pro' ); ?>');" title="<?php esc_attr_e( 'Remove from this batch', 'class-manager-pro' ); ?>" style="color:#d63638;">
											🗑 <?php esc_html_e( 'Remove', 'class-manager-pro' ); ?>
										</button>
									</form>
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
	$tokens          = function_exists( 'cmp_get_batch_registration_tokens_for_batch' ) ? cmp_get_batch_registration_tokens_for_batch( (int) $batch->id, false, 10 ) : array();
	$active_count    = function_exists( 'cmp_get_active_batch_registration_tokens_count' ) ? cmp_get_active_batch_registration_tokens_count( (int) $batch->id ) : 0;
	$permanent_link  = cmp_get_batch_permanent_registration_url( $batch );
	$permanent_qr    = cmp_get_registration_qr_url( $permanent_link, 220 );
	$perm_status     = ! empty( $batch->permanent_registration_enabled ) ? array( 'key' => 'active', 'label' => __( 'Active', 'class-manager-pro' ) ) : array( 'key' => 'inactive', 'label' => __( 'Inactive', 'class-manager-pro' ) );
	?>
	<section class="cmp-panel" id="cmp-batch-registration-links">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Temporary Registration Links', 'class-manager-pro' ); ?> (<?php echo esc_html( $active_count ); ?> / 5 <?php esc_html_e( 'Active', 'class-manager-pro' ); ?>)</h2>
				<p class="cmp-muted"><?php esc_html_e( 'Generate up to 5 active temporary admission links at once. Each link is valid for 10 minutes.', 'class-manager-pro' ); ?></p>
			</div>
			<div>
				<?php if ( $active_count < 5 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
						<input type="hidden" name="action" value="cmp_generate_batch_registration_link">
						<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
						<?php wp_nonce_field( 'cmp_generate_batch_registration_link_' . (int) $batch->id ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( '+ Generate New Link', 'class-manager-pro' ); ?></button>
					</form>
				<?php else : ?>
					<button type="button" class="button button-disabled" disabled title="<?php esc_attr_e( 'Maximum 5 active links allowed. Close an active link to create another.', 'class-manager-pro' ); ?>">
						<?php esc_html_e( 'Max Limit Reached (5/5 Active)', 'class-manager-pro' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<div class="cmp-tokens-grid">
			<?php if ( empty( $tokens ) ) : ?>
				<div class="cmp-callout cmp-callout-info">
					<p><?php esc_html_e( 'No temporary links generated yet. Click "+ Generate New Link" to create one (up to 5 active links).', 'class-manager-pro' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $tokens as $t_index => $t_row ) : ?>
					<?php
					$t_status    = cmp_get_batch_registration_link_status( $t_row );
					$t_url       = 'active' === $t_status['key'] ? cmp_get_batch_temporary_registration_url( $t_row ) : '';
					$is_active   = 'active' === $t_status['key'];
					$badge_class = 'cmp-badge-token-' . $t_status['key'];
					?>
					<div class="cmp-token-row <?php echo $is_active ? 'is-active-row' : ''; ?>">
						<div class="cmp-token-info">
							<span class="<?php echo esc_attr( $badge_class ); ?>">
								<?php echo esc_html( $t_status['label'] ); ?>
							</span>
							<?php if ( $t_url ) : ?>
								<div class="cmp-token-input-wrap">
									<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $t_url ); ?>" id="cmp-tok-<?php echo esc_attr( (int) $t_row->id ); ?>">
									<button type="button" class="button button-small" data-cmp-copy-target="#cmp-tok-<?php echo esc_attr( (int) $t_row->id ); ?>">
										<?php esc_html_e( 'Copy', 'class-manager-pro' ); ?>
									</button>
									<a class="button button-small" href="<?php echo esc_url( $t_url ); ?>" target="_blank" rel="noopener">
										<?php esc_html_e( 'Open', 'class-manager-pro' ); ?>
									</a>
								</div>
							<?php else : ?>
								<code style="color:#64748b;"><?php echo esc_html( substr( $t_row->token, 0, 16 ) . '...' ); ?></code>
							<?php endif; ?>
							<span class="cmp-token-meta">
								<?php if ( $is_active ) : ?>
									<?php esc_html_e( 'Expires in:', 'class-manager-pro' ); ?>
									<strong class="cmp-registration-countdown" data-cmp-expiry-at="<?php echo esc_attr( $t_row->expires_at ); ?>"><?php echo esc_html( $t_row->expires_at ); ?></strong>
								<?php else : ?>
									<?php echo esc_html( $t_row->created_at ); ?>
								<?php endif; ?>
							</span>
						</div>
						<div class="cmp-token-actions">
							<?php if ( $is_active ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
									<input type="hidden" name="action" value="cmp_close_batch_registration_token">
									<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
									<input type="hidden" name="token_id" value="<?php echo esc_attr( (int) $t_row->id ); ?>">
									<?php wp_nonce_field( 'cmp_close_batch_registration_token_' . (int) $t_row->id ); ?>
									<button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Close this registration link?', 'class-manager-pro' ); ?>');">
										<?php esc_html_e( 'Close Link', 'class-manager-pro' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $batch->is_free ) ) : ?>
			<section class="cmp-panel cmp-panel-nested" style="margin-top:20px;">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'Permanent Registration Link (Free Batch)', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Permanent link allows unlimited submissions for free batches.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<?php if ( ! empty( $batch->permanent_registration_enabled ) && $permanent_link ) : ?>
					<div class="cmp-inline-tools">
						<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $permanent_link ); ?>" id="cmp-batch-permanent-link-<?php echo esc_attr( (int) $batch->id ); ?>">
						<button type="button" class="button" data-cmp-copy-target="#cmp-batch-permanent-link-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy Link', 'class-manager-pro' ); ?></button>
						<a class="button" href="<?php echo esc_url( $permanent_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Form', 'class-manager-pro' ); ?></a>
					</div>
					<?php if ( $permanent_qr ) : ?>
						<div class="cmp-registration-qr" style="margin-top:12px;">
							<img src="<?php echo esc_url( $permanent_qr ); ?>" alt="<?php esc_attr_e( 'Permanent registration QR code', 'class-manager-pro' ); ?>" style="width:140px;height:140px;">
						</div>
					<?php endif; ?>
				<?php else : ?>
					<p class="cmp-muted"><?php esc_html_e( 'Enable permanent link in batch settings to show this URL.', 'class-manager-pro' ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>
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
	$announcements    = cmp_get_batch_announcements( (int) $batch->id, 20 );
	$whatsapp_enabled = cmp_is_whatsapp_delivery_enabled();
	$email_formats    = cmp_get_announcement_email_formats();
	$ws_settings      = function_exists( 'cmp_get_whatsapp_settings' ) ? cmp_get_whatsapp_settings() : array();
	$has_wa_api       = ! empty( $ws_settings['api_token'] ) || ! empty( $ws_settings['phone_number_id'] );
	$gateway_type     = isset( $ws_settings['gateway_type'] ) ? $ws_settings['gateway_type'] : '';
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Batch Announcements', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Broadcast messages to all enrolled students via WhatsApp API and Email.', 'class-manager-pro' ); ?></p>
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
							<td><input type="text" id="cmp-announcement-subject" name="subject" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Class Schedule Update / Important Notice', 'class-manager-pro' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-announcement-message"><?php esc_html_e( 'Message Content', 'class-manager-pro' ); ?></label></th>
							<td>
								<textarea id="cmp-announcement-message" name="message" rows="7" class="large-text" required placeholder="<?php esc_attr_e( 'Write announcement message to all enrolled students...', 'class-manager-pro' ); ?>"></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Delivery Channels', 'class-manager-pro' ); ?></th>
							<td>
								<div style="display:flex;flex-direction:column;gap:8px;">
									<label>
										<input type="checkbox" name="send_whatsapp" value="1" checked>
										<strong><?php esc_html_e( 'Send via WhatsApp API', 'class-manager-pro' ); ?></strong>
										<?php if ( $has_wa_api ) : ?>
											<span class="cmp-inline-badge cmp-inline-badge-active" style="margin-left:6px;"><?php echo esc_html( 'meta_cloud' === $gateway_type ? __( 'Meta Cloud API Connected', 'class-manager-pro' ) : __( 'WhatsApp Gateway Connected', 'class-manager-pro' ) ); ?></span>
										<?php else : ?>
											<span class="cmp-muted" style="font-size:12px;margin-left:6px;">(<a href="<?php echo esc_url( cmp_admin_url( 'cmp-settings' ) ); ?>" target="_blank"><?php esc_html_e( 'Configure API in Settings', 'class-manager-pro' ); ?></a>)</span>
										<?php endif; ?>
									</label>
									<label>
										<input type="checkbox" name="send_email" value="1" checked>
										<strong><?php esc_html_e( 'Send via Email', 'class-manager-pro' ); ?></strong>
									</label>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-announcement-email-format"><?php esc_html_e( 'Email Style', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-announcement-email-format" name="email_format">
									<?php foreach ( $email_formats as $format_key => $format_label ) : ?>
										<option value="<?php echo esc_attr( $format_key ); ?>"><?php echo esc_html( $format_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>

					<?php submit_button( __( '📢 Broadcast Announcement Now', 'class-manager-pro' ) ); ?>
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
 * Renders the batch admissions tab.
 *
 * @param object $batch Batch row.
 * @return void
 */
function cmp_render_batch_admissions_tab( $batch ) {
	$search    = sanitize_text_field( cmp_field( $_GET, 'registration_search', '' ) );
	$overview  = cmp_get_batch_registration_overview( (int) $batch->id );
	$history   = cmp_get_batch_registration_history( (int) $batch->id, $search, 12 );
	$latest_temp_token = cmp_get_batch_registration_token_for_batch( (int) $batch->id, false );
	$latest_temp_status = $latest_temp_token ? cmp_get_batch_registration_link_status( $latest_temp_token ) : $overview['temporary_status'];
	$search_id = 'cmp-batch-registration-search-' . (int) $batch->id;
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Registration Overview', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Track admission flow, link usage, and recent student joins in one workspace.', 'class-manager-pro' ); ?></p>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Registrations', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $overview['total_registrations'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( "Today's Registrations", 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $overview['today_registrations'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Registrations', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $overview['pending_registrations'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Successful Registrations', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $overview['successful_registrations'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Temporary Links', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $overview['temporary_links'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Permanent Links', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $overview['permanent_links'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( $overview['registration_status_label'] ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Capacity', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( $overview['batch_full'] ? __( 'Batch Full', 'class-manager-pro' ) : ( $overview['capacity_limit'] > 0 ? $overview['capacity_remaining'] . ' ' . __( 'left', 'class-manager-pro' ) : __( 'Open', 'class-manager-pro' ) ) ); ?></strong>
			</div>
		</div>
	</section>

	<div class="cmp-grid cmp-grid-2">
		<section class="cmp-panel cmp-panel-nested">
			<div class="cmp-panel-header">
				<div>
					<h3><?php esc_html_e( 'Temporary Link Snapshot', 'class-manager-pro' ); ?></h3>
					<p class="cmp-muted"><?php esc_html_e( 'Temporary links are ideal for paid batches and one-time admissions.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<p>
				<span class="cmp-inline-badge cmp-inline-badge-<?php echo esc_attr( $latest_temp_status['key'] ); ?>"><?php echo esc_html( $latest_temp_status['label'] ); ?></span>
			</p>
			<p class="cmp-muted"><?php echo esc_html( $latest_temp_status['description'] ); ?></p>
			<p class="cmp-muted"><?php echo esc_html( sprintf( __( 'Last registration: %s', 'class-manager-pro' ), ! empty( $overview['last_registration_at'] ) ? $overview['last_registration_at'] : __( 'Never', 'class-manager-pro' ) ) ); ?></p>
		</section>
		<section class="cmp-panel cmp-panel-nested">
			<div class="cmp-panel-header">
				<div>
					<h3><?php esc_html_e( 'Permanent Link Snapshot', 'class-manager-pro' ); ?></h3>
					<p class="cmp-muted"><?php esc_html_e( 'Free batches can keep a permanent link open when enabled.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<p>
				<span class="cmp-inline-badge cmp-inline-badge-<?php echo esc_attr( $overview['permanent_status']['key'] ); ?>"><?php echo esc_html( $overview['permanent_status']['label'] ); ?></span>
			</p>
			<p class="cmp-muted"><?php echo esc_html( $overview['permanent_status']['description'] ); ?></p>
			<p class="cmp-muted"><?php echo esc_html( sprintf( __( 'Registrations captured: %s', 'class-manager-pro' ), number_format_i18n( (int) $overview['successful_registrations'] ) ) ); ?></p>
		</section>
	</div>

	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Registration History', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Search by student name, mobile number, or email to review admission history.', 'class-manager-pro' ); ?></p>
			</div>
			<form method="get" class="cmp-inline-form">
				<input type="hidden" name="page" value="cmp-batches">
				<input type="hidden" name="action" value="view">
				<input type="hidden" name="id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<input type="hidden" name="tab" value="admissions">
				<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>"><?php esc_html_e( 'Search registrations', 'class-manager-pro' ); ?></label>
				<input type="search" id="<?php echo esc_attr( $search_id ); ?>" name="registration_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name, mobile, or email', 'class-manager-pro' ); ?>">
				<?php submit_button( __( 'Search', 'class-manager-pro' ), 'secondary', '', false ); ?>
			</form>
		</div>

		<div class="cmp-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Method', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Source', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Time', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $history ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No registration history found for this batch.', 'class-manager-pro' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $history as $row ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( ! empty( $row->student_name ) ? $row->student_name : __( 'Unknown', 'class-manager-pro' ) ); ?></strong><br>
									<span class="cmp-muted"><?php echo esc_html( ! empty( $row->student_unique_id ) ? $row->student_unique_id : '' ); ?></span>
								</td>
								<td>
									<?php echo esc_html( ! empty( $row->student_phone ) ? $row->student_phone : __( 'Not set', 'class-manager-pro' ) ); ?><br>
									<span class="cmp-muted"><?php echo esc_html( ! empty( $row->student_email ) ? $row->student_email : __( 'Not set', 'class-manager-pro' ) ); ?></span>
								</td>
								<td><?php echo esc_html( ! empty( $row->registration_method_label ) ? $row->registration_method_label : __( 'Manual', 'class-manager-pro' ) ); ?></td>
								<td><?php echo esc_html( ! empty( $row->registration_source_label ) ? $row->registration_source_label : __( 'Manual', 'class-manager-pro' ) ); ?></td>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><span class="cmp-inline-badge"><?php echo esc_html( ! empty( $row->registration_status_label ) ? $row->registration_status_label : __( 'Successful', 'class-manager-pro' ) ); ?></span></td>
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
 * Renders the batch certificates tab showing full logs of who generated and who downloaded certificates.
 *
 * @param object $batch Batch row.
 */
function cmp_render_batch_certificates_tab( $batch ) {
	$batch_id  = (int) $batch->id;
	$cert_data = function_exists( 'cmp_get_batch_certificate_records' )
		? cmp_get_batch_certificate_records( $batch_id )
		: array( 'records' => array(), 'total_students' => 0, 'generated_count' => 0, 'downloaded_count' => 0, 'pending_count' => 0 );

	$records          = $cert_data['records'];
	$total_students   = $cert_data['total_students'];
	$generated_count  = $cert_data['generated_count'];
	$downloaded_count = $cert_data['downloaded_count'];
	$pending_count    = $cert_data['pending_count'];
	$download_rate    = $generated_count > 0 ? round( ( $downloaded_count / $generated_count ) * 100, 1 ) : 0;
	?>
	<div class="cmp-cards cmp-cards-4">
		<div class="cmp-card">
			<span><?php esc_html_e( 'Total Enrolled Students', 'class-manager-pro' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $total_students ) ); ?></strong>
			<small class="cmp-muted"><?php esc_html_e( 'Eligible for certificates', 'class-manager-pro' ); ?></small>
		</div>
		<div class="cmp-card">
			<span><?php esc_html_e( 'Certificates Generated', 'class-manager-pro' ); ?></span>
			<strong style="color:#059669;"><?php echo esc_html( number_format_i18n( $generated_count ) ); ?></strong>
			<small class="cmp-muted"><?php echo esc_html( sprintf( __( '%d pending generation', 'class-manager-pro' ), $pending_count ) ); ?></small>
		</div>
		<div class="cmp-card">
			<span><?php esc_html_e( 'Downloaded by Students', 'class-manager-pro' ); ?></span>
			<strong style="color:#2563eb;"><?php echo esc_html( number_format_i18n( $downloaded_count ) ); ?></strong>
			<small class="cmp-muted"><?php echo esc_html( sprintf( __( '%s%% download rate', 'class-manager-pro' ), $download_rate ) ); ?></small>
		</div>
		<div class="cmp-card">
			<span><?php esc_html_e( 'Not Yet Downloaded', 'class-manager-pro' ); ?></span>
			<strong style="color:<?php echo ( $generated_count - $downloaded_count ) > 0 ? '#d97706' : '#059669'; ?>;"><?php echo esc_html( number_format_i18n( max( 0, $generated_count - $downloaded_count ) ) ); ?></strong>
			<small class="cmp-muted"><?php esc_html_e( 'Ready for download', 'class-manager-pro' ); ?></small>
		</div>
	</div>

	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'Batch Certificates Log & Download Tracker', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Track which students have had their certificates generated, who has downloaded them, and download counts.', 'class-manager-pro' ); ?></p>
			</div>
			<div class="cmp-toolbar">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0;">
					<input type="hidden" name="action" value="cmp_generate_batch_certificates">
					<input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch_id ); ?>">
					<?php wp_nonce_field( 'cmp_generate_batch_certificates_' . $batch_id ); ?>
					<button type="submit" class="button button-primary" style="background:#059669;border-color:#059669;">
						⚡ <?php esc_html_e( 'Generate / Re-issue All Certificates', 'class-manager-pro' ); ?>
					</button>
				</form>
				<input type="search" id="cmp-cert-filter-input" placeholder="<?php esc_attr_e( 'Search certificate log...', 'class-manager-pro' ); ?>" style="min-width:200px;padding:4px 10px;border-radius:6px;font-size:13px;" onkeyup="var val = this.value.toLowerCase(); document.querySelectorAll('#cmp-cert-log-tbody tr').forEach(function(r){ r.style.display = r.innerText.toLowerCase().indexOf(val) > -1 ? '' : 'none'; });">
			</div>
		</div>

		<div class="cmp-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student Name & Contact', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Certificate Status', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Code / Serial', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Generated Date', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Download Activity', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody id="cmp-cert-log-tbody">
					<?php if ( empty( $records ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No students enrolled in this batch.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $records as $rec ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $rec['student_name'] ); ?></strong>
									<br><span class="cmp-muted" style="font-size:12px;"><?php echo esc_html( $rec['phone'] ? $rec['phone'] : $rec['email'] ); ?></span>
								</td>
								<td>
									<?php if ( 'downloaded' === $rec['status'] ) : ?>
										<span class="cmp-status cmp-status-active" style="background:#dcfce7;color:#166534;font-weight:600;padding:4px 8px;border-radius:6px;">✓ <?php esc_html_e( 'Downloaded', 'class-manager-pro' ); ?></span>
									<?php elseif ( 'generated' === $rec['status'] ) : ?>
										<span class="cmp-status" style="background:#e0f2fe;color:#075985;font-weight:600;padding:4px 8px;border-radius:6px;">⚡ <?php esc_html_e( 'Generated', 'class-manager-pro' ); ?></span>
									<?php else : ?>
										<span class="cmp-status" style="background:#f1f5f9;color:#64748b;font-weight:600;padding:4px 8px;border-radius:6px;">⏳ <?php esc_html_e( 'Not Generated', 'class-manager-pro' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $rec['code'] || $rec['serial'] ) : ?>
										<code><?php echo esc_html( $rec['code'] ? $rec['code'] : $rec['serial'] ); ?></code>
										<?php if ( $rec['serial'] && $rec['code'] && $rec['serial'] !== $rec['code'] ) : ?>
											<br><small class="cmp-muted"><?php echo esc_html( $rec['serial'] ); ?></small>
										<?php endif; ?>
									<?php else : ?>
										<span class="cmp-muted">—</span>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( ! empty( $rec['generated_at'] ) ? wp_date( 'd M Y, h:i A', strtotime( $rec['generated_at'] ) ) : '—' ); ?>
								</td>
								<td>
									<?php if ( (int) $rec['download_count'] > 0 ) : ?>
										<strong style="color:#059669;"><?php echo esc_html( sprintf( _n( '%d time', '%d times', (int) $rec['download_count'], 'class-manager-pro' ), (int) $rec['download_count'] ) ); ?></strong>
										<?php if ( ! empty( $rec['last_download_at'] ) ) : ?>
											<br><small class="cmp-muted"><?php echo esc_html( wp_date( 'd M Y, h:i A', strtotime( $rec['last_download_at'] ) ) ); ?></small>
										<?php endif; ?>
									<?php else : ?>
										<span class="cmp-muted" style="color:#94a3b8;"><?php esc_html_e( 'Not downloaded yet', 'class-manager-pro' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="cmp-actions" style="white-space:nowrap;">
									<?php if ( ! empty( $rec['pdf_url'] ) ) : ?>
										<a href="<?php echo esc_url( $rec['pdf_url'] ); ?>" target="_blank" class="button button-small" style="color:#0284c7;">
											📄 <?php esc_html_e( 'View PDF', 'class-manager-pro' ); ?>
										</a>
									<?php endif; ?>

									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin:0;">
										<input type="hidden" name="action" value="cmp_generate_single_student_certificate">
										<input type="hidden" name="student_id" value="<?php echo esc_attr( $rec['student_id'] ); ?>">
										<input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch_id ); ?>">
										<?php wp_nonce_field( 'cmp_generate_single_cert_' . $rec['student_id'] ); ?>
										<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Generate / Re-issue certificate for this student', 'class-manager-pro' ); ?>">
											⚡ <?php echo 'not_generated' === $rec['status'] ? esc_html__( 'Generate', 'class-manager-pro' ) : esc_html__( 'Re-issue', 'class-manager-pro' ); ?>
										</button>
									</form>

									<?php if ( ! empty( $rec['wa_link'] ) && 'not_generated' !== $rec['status'] ) : ?>
										<a href="<?php echo esc_url( $rec['wa_link'] ); ?>" target="_blank" class="button button-small" style="color:#16a34a;" title="<?php esc_attr_e( 'Send Certificate Code via WhatsApp', 'class-manager-pro' ); ?>">
											💬 WhatsApp
										</a>
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
 * Renders the batch detail panel.
 *
 * @param object $batch Batch row.
 */
function cmp_render_batch_detail_panel( $batch ) {
	$metrics      = cmp_get_batch_metrics( (int) $batch->id );
	$course_title = ! empty( $batch->course_id ) ? cmp_get_tutor_course_title( (int) $batch->course_id ) : '';
	$course_url   = ! empty( $batch->course_id ) ? cmp_get_tutor_course_url( (int) $batch->course_id ) : '';
	$current_tab  = sanitize_key( cmp_field( $_GET, 'tab', 'students' ) );

	if ( ! in_array( $current_tab, array( 'students', 'attendance', 'expenses', 'announcements', 'admissions', 'import' ), true ) ) {
		$current_tab = 'students';
	}
	?>
	<div class="wrap cmp-wrap">
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-batches' ); ?>

		<?php if ( ! empty( $_GET['cmp_auto_copied'] ) ) : ?>
			<script>
			(function() {
				var rawUrl = <?php echo wp_json_encode( rawurldecode( sanitize_text_field( $_GET['cmp_auto_copied'] ) ) ); ?>;
				if (rawUrl && navigator.clipboard) {
					navigator.clipboard.writeText(rawUrl).catch(function(){});
				}
			})();
			</script>
		<?php endif; ?>

		<div class="cmp-batch-pro-header cmp-pro-header-modern">
			<div class="cmp-batch-title-group">
				<div class="cmp-title-row">
					<h1><?php echo esc_html( $batch->batch_name ); ?></h1>
					<span class="cmp-badge-pill cmp-badge-class"><?php echo esc_html( $batch->class_name ); ?></span>
					<span class="cmp-badge-pill <?php echo 'active' === $batch->status ? 'cmp-badge-active' : 'cmp-badge-completed'; ?>">
						<?php echo esc_html( ucfirst( $batch->status ) ); ?>
					</span>
				</div>
				<div class="cmp-title-meta-line">
					<span><?php esc_html_e( 'Teacher:', 'class-manager-pro' ); ?> <strong><?php echo esc_html( cmp_get_teacher_label( (int) $batch->teacher_user_id ) ); ?></strong></span>
					<span class="cmp-meta-sep">•</span>
					<span><?php esc_html_e( 'Batch Fee:', 'class-manager-pro' ); ?> <strong><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free', 'class-manager-pro' ) : esc_html( cmp_format_money( cmp_get_batch_effective_fee( $batch ) ) ); ?></strong></span>
					<?php if ( $batch->start_date ) : ?>
						<span class="cmp-meta-sep">•</span>
						<span><?php esc_html_e( 'Starts:', 'class-manager-pro' ); ?> <strong><?php echo esc_html( $batch->start_date ); ?></strong></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="cmp-toolbar cmp-toolbar-pro">
				<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-students', array( 'class_id' => (int) $batch->class_id, 'batch_id' => (int) $batch->id ) ) ); ?>#cmp-add-student"><?php esc_html_e( '+ Student', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments', array( 'batch_id' => (int) $batch->id ) ) ); ?>#cmp-add-payment"><?php esc_html_e( '+ Payment', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'admissions' ) ) ); ?>"><?php esc_html_e( '🔗 Temp Link', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'announcements' ) ) ); ?>"><?php esc_html_e( '📢 Announcement', 'class-manager-pro' ); ?></a>

				<?php if ( 'active' === $batch->status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0;">
						<input type="hidden" name="action" value="cmp_toggle_batch_status">
						<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
						<input type="hidden" name="new_status" value="completed">
						<?php wp_nonce_field( 'cmp_toggle_batch_status_' . (int) $batch->id ); ?>
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to mark this batch as Completed?', 'class-manager-pro' ); ?>');" title="<?php esc_attr_e( 'Mark Batch Completed', 'class-manager-pro' ); ?>">
							✓ <?php esc_html_e( 'Complete Batch', 'class-manager-pro' ); ?>
						</button>
					</form>
				<?php endif; ?>

				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $batch->id ) ) ); ?>"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>"><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></a>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-6 cmp-batch-cards-styled">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['student_count'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Collected', 'class-manager-pro' ); ?></span>
				<strong style="color:#008a20;"><?php echo esc_html( cmp_format_money( $metrics['revenue'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fees', 'class-manager-pro' ); ?></span>
				<strong style="color:<?php echo (float) $metrics['pending_fee'] > 0 ? '#d63638' : '#008a20'; ?>;"><?php echo esc_html( cmp_format_money( $metrics['pending_fee'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Expenses', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['expense'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Net Income', 'class-manager-pro' ); ?></span>
				<strong style="color:<?php echo (float) $metrics['net_income'] >= 0 ? '#008a20' : '#d63638'; ?>;"><?php echo esc_html( cmp_format_money( $metrics['net_income'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Batch Status', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( ucfirst( $batch->status ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-batch-info-strip">
			<div class="cmp-info-strip-item">
				<span><?php esc_html_e( 'Teacher:', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_get_teacher_label( (int) $batch->teacher_user_id ) ); ?></strong>
			</div>
			<div class="cmp-info-strip-item">
				<span><?php esc_html_e( 'Start Date:', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( $batch->start_date ? $batch->start_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong>
			</div>
			<div class="cmp-info-strip-item">
				<span><?php esc_html_e( 'Fee:', 'class-manager-pro' ); ?></span>
				<strong><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free', 'class-manager-pro' ) : esc_html( cmp_format_money( cmp_get_batch_effective_fee( $batch ) ) ); ?></strong>
			</div>
			<?php if ( $batch->fee_due_date ) : ?>
				<div class="cmp-info-strip-item">
					<span><?php esc_html_e( 'Fee Due:', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $batch->fee_due_date ); ?></strong>
				</div>
			<?php endif; ?>
			<?php if ( $batch->razorpay_page_id ) : ?>
				<div class="cmp-info-strip-item">
					<span><?php esc_html_e( 'Razorpay Page:', 'class-manager-pro' ); ?></span>
					<strong><code><?php echo esc_html( $batch->razorpay_page_id ); ?></code></strong>
				</div>
			<?php endif; ?>
			<?php if ( $course_title ) : ?>
				<div class="cmp-info-strip-item">
					<span><?php esc_html_e( 'LMS Course:', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $course_title ); ?></strong>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( 'completed' === (string) $batch->status ) : ?>
			<?php
			$cert_data = function_exists( 'cmp_get_batch_certificate_records' )
				? cmp_get_batch_certificate_records( (int) $batch->id )
				: array( 'total_students' => 0, 'generated_count' => 0, 'downloaded_count' => 0, 'pending_count' => 0 );
			?>
			<div class="cmp-panel" style="margin: 16px 0; border-left: 4px solid #10b981; background: #f0fdf4; padding: 18px 22px; border-radius: 8px;">
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
					<div>
						<h3 style="margin:0 0 6px 0; color:#065f46; display:flex; align-items:center; gap:8px; font-size:16px;">
							🎓 <?php esc_html_e( 'Batch Completed - Certificates Available', 'class-manager-pro' ); ?>
						</h3>
						<p style="margin:0; color:#047857; font-size:13px;">
							<?php
							printf(
								/* translators: 1: generated count 2: total students 3: downloaded count */
								esc_html__( '%1$d of %2$d certificates generated • %3$d downloaded by students.', 'class-manager-pro' ),
								(int) $cert_data['generated_count'],
								(int) $cert_data['total_students'],
								(int) $cert_data['downloaded_count']
							);
							?>
						</p>
					</div>
					<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin:0;">
							<input type="hidden" name="action" value="cmp_generate_batch_certificates">
							<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
							<?php wp_nonce_field( 'cmp_generate_batch_certificates_' . (int) $batch->id ); ?>
							<button type="submit" class="button button-primary" style="background:#059669; border-color:#059669;">
								⚡ <?php esc_html_e( 'Generate / Re-issue All Certificates', 'class-manager-pro' ); ?>
							</button>
						</form>
						<a class="button button-secondary" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'certificates' ) ) ); ?>">
							📜 <?php esc_html_e( 'View Certificate Logs & Downloads →', 'class-manager-pro' ); ?>
						</a>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper cmp-tab-nav" aria-label="<?php esc_attr_e( 'Batch sections', 'class-manager-pro' ); ?>">
			<a class="nav-tab <?php echo 'students' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'students' ) ) ); ?>"><?php esc_html_e( 'Enrolled Students', 'class-manager-pro' ); ?> (<?php echo esc_html( $metrics['student_count'] ); ?>)</a>
			<?php if ( cmp_is_attendance_enabled() ) : ?>
				<a class="nav-tab <?php echo 'attendance' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'attendance' ) ) ); ?>"><?php esc_html_e( 'Attendance', 'class-manager-pro' ); ?></a>
			<?php endif; ?>
			<a class="nav-tab <?php echo 'expenses' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'expenses' ) ) ); ?>"><?php esc_html_e( 'Expenses', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'admissions' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'admissions' ) ) ); ?>"><?php esc_html_e( 'Admissions & Temp Links', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'announcements' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'announcements' ) ) ); ?>"><?php esc_html_e( 'Announcements', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'certificates' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'certificates' ) ) ); ?>">🎓 <?php esc_html_e( 'Certificates', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'import' === $current_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id, 'tab' => 'import' ) ) ); ?>"><?php esc_html_e( 'Import Tools', 'class-manager-pro' ); ?></a>
		</nav>

		<?php if ( 'attendance' === $current_tab && cmp_is_attendance_enabled() ) : ?>
			<?php cmp_render_batch_attendance_panel( $batch ); ?>
		<?php elseif ( 'expenses' === $current_tab ) : ?>
			<?php cmp_render_batch_expenses_tab( $batch, $metrics ); ?>
		<?php elseif ( 'admissions' === $current_tab ) : ?>
			<?php cmp_render_batch_registration_links_panel( $batch ); ?>
			<?php cmp_render_batch_admissions_tab( $batch ); ?>
		<?php elseif ( 'announcements' === $current_tab ) : ?>
			<?php cmp_render_batch_announcements_tab( $batch ); ?>
		<?php elseif ( 'certificates' === $current_tab ) : ?>
			<?php cmp_render_batch_certificates_tab( $batch ); ?>
		<?php elseif ( 'import' === $current_tab ) : ?>
			<?php cmp_render_batch_import_tools( $batch, 'cmp-batches', 'view' ); ?>
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
		<h1><?php echo esc_html( $edit_batch ? __( 'Edit Batch', 'class-manager-pro' ) : ( 'add' === $action ? __( 'Add Batch', 'class-manager-pro' ) : __( 'Classes & Batches', 'class-manager-pro' ) ) ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Work from class structure into batch workspaces for students, attendance, payments, announcements, teachers, and Tutor LMS connections.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-batches' ); ?>
		<?php
		cmp_render_workflow_tabs(
			array(
				array( 'key' => 'classes', 'label' => __( 'Classes', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-classes' ) ),
				array( 'key' => 'batches', 'label' => __( 'Batches', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-batches' ) ),
			),
			'batches',
			__( 'Class and batch sections', 'class-manager-pro' )
		);
		?>

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

		<?php
		$status_filter = sanitize_key( cmp_field( $_GET, 'status', '' ) );
		$class_filter  = absint( cmp_field( $_GET, 'class_id', 0 ) );

		$count_all       = count( $batch_rows );
		$count_active    = 0;
		$count_completed = 0;
		$count_upcoming  = 0;

		foreach ( $batch_rows as $b_row ) {
			if ( 'active' === $b_row->status ) {
				$count_active++;
			} elseif ( 'completed' === $b_row->status ) {
				$count_completed++;
			} elseif ( 'upcoming' === $b_row->status ) {
				$count_upcoming++;
			}
		}

		$filtered_batches = array();
		foreach ( $batch_rows as $b_row ) {
			if ( $class_filter && (int) $b_row->class_id !== $class_filter ) {
				continue;
			}
			if ( $status_filter && $b_row->status !== $status_filter ) {
				continue;
			}
			$filtered_batches[] = $b_row;
		}
		?>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Batch List', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Open one batch at a time for students, attendance, temporary links, and Tutor LMS connections.', 'class-manager-pro' ); ?></p>
				</div>
				<div class="cmp-toolbar">
					<a class="button button-primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( '+ Add New Batch', 'class-manager-pro' ); ?></a>
				</div>
			</div>

			<div class="cmp-filter-chips-bar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0 16px 0;padding-bottom:12px;border-bottom:1px solid #e2e8f0;">
				<div class="cmp-filter-chips" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
					<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', $class_filter ? array( 'class_id' => $class_filter ) : array() ) ); ?>" class="button <?php echo empty( $status_filter ) ? 'button-primary' : ''; ?>" style="border-radius:20px;font-size:12px;">
						<?php esc_html_e( 'All Batches', 'class-manager-pro' ); ?> (<?php echo (int) $count_all; ?>)
					</a>
					<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array_merge( $class_filter ? array( 'class_id' => $class_filter ) : array(), array( 'status' => 'active' ) ) ) ); ?>" class="button <?php echo 'active' === $status_filter ? 'button-primary' : ''; ?>" style="border-radius:20px;font-size:12px;">
						🟢 <?php esc_html_e( 'Active', 'class-manager-pro' ); ?> (<?php echo (int) $count_active; ?>)
					</a>
					<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array_merge( $class_filter ? array( 'class_id' => $class_filter ) : array(), array( 'status' => 'completed' ) ) ) ); ?>" class="button <?php echo 'completed' === $status_filter ? 'button-primary' : ''; ?>" style="border-radius:20px;font-size:12px;">
						✓ <?php esc_html_e( 'Completed', 'class-manager-pro' ); ?> (<?php echo (int) $count_completed; ?>)
					</a>
					<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array_merge( $class_filter ? array( 'class_id' => $class_filter ) : array(), array( 'status' => 'upcoming' ) ) ) ); ?>" class="button <?php echo 'upcoming' === $status_filter ? 'button-primary' : ''; ?>" style="border-radius:20px;font-size:12px;">
						⏳ <?php esc_html_e( 'Upcoming', 'class-manager-pro' ); ?> (<?php echo (int) $count_upcoming; ?>)
					</a>
					<?php if ( $class_filter ) : ?>
						<span class="cmp-badge" style="background:#e0e7ff;color:#3730a3;font-size:12px;padding:4px 10px;border-radius:20px;">
							<?php
							$filter_class = cmp_get_class( $class_filter );
							printf( esc_html__( 'Filtered by: %s', 'class-manager-pro' ), esc_html( $filter_class ? $filter_class->name : '' ) );
							?>
							<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>" style="text-decoration:none;margin-left:6px;font-weight:bold;color:#4338ca;">✕</a>
						</span>
					<?php endif; ?>
				</div>

				<div class="cmp-batch-quick-search" style="display:flex;align-items:center;gap:8px;">
					<input type="search" id="cmp-quick-batch-filter-input" placeholder="<?php esc_attr_e( 'Quick search batches...', 'class-manager-pro' ); ?>" style="min-width:220px;padding:4px 10px;border-radius:6px;font-size:13px;" onkeyup="var val = this.value.toLowerCase(); document.querySelectorAll('#cmp-batch-table-body tr').forEach(function(r){ r.style.display = r.innerText.toLowerCase().indexOf(val) > -1 ? '' : 'none'; });">
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
							<th><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Tutor Course', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="cmp-batch-table-body">
						<?php if ( empty( $filtered_batches ) ) : ?>
							<tr><td colspan="9"><?php esc_html_e( 'No batches found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $filtered_batches as $row ) : ?>
								<?php
								$view_url     = cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) );
								$edit_url     = cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $row->id ) );
								$clone_url    = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_clone_batch&id=' . (int) $row->id ), 'cmp_clone_batch_' . (int) $row->id );
								$delete_url   = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_delete_batch&id=' . (int) $row->id ), 'cmp_delete_batch_' . (int) $row->id );
								$course_title = ! empty( $row->course_id ) ? cmp_get_tutor_course_title( (int) $row->course_id ) : __( 'Not linked', 'class-manager-pro' );
								?>
								<tr data-cmp-row-id="batch-<?php echo esc_attr( (int) $row->id ); ?>">
									<td><input type="checkbox" class="cmp-batch-select" value="<?php echo esc_attr( (int) $row->id ); ?>"></td>
									<td>
										<strong><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $row->batch_name ); ?></a></strong>
									</td>
									<td><?php echo esc_html( $row->class_name ); ?></td>
									<td><?php echo esc_html( cmp_get_teacher_label( (int) $row->teacher_user_id ) ); ?></td>
									<td><?php echo esc_html( $course_title ); ?></td>
									<td>
										<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id, 'tab' => 'students' ) ) ); ?>">
											<strong><?php echo esc_html( number_format_i18n( $row->student_count ) ); ?></strong>
										</a>
									</td>
									<td><?php echo esc_html( $row->start_date ? $row->start_date : __( 'Not set', 'class-manager-pro' ) ); ?></td>
									<td><span class="cmp-status cmp-status-<?php echo esc_attr( $row->status ); ?>" data-cmp-status-badge="batch-<?php echo esc_attr( (int) $row->id ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
									<td class="cmp-actions" style="white-space:nowrap;">
										<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php esc_html_e( 'View', 'class-manager-pro' ); ?></a>
										<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
										<?php if ( 'completed' !== $row->status ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin:0;">
												<input type="hidden" name="action" value="cmp_toggle_batch_status">
												<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $row->id ); ?>">
												<input type="hidden" name="new_status" value="completed">
												<?php wp_nonce_field( 'cmp_toggle_batch_status_' . (int) $row->id ); ?>
												<button type="submit" class="button button-small" style="color:#059669;font-weight:600;" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Mark batch "%s" as Completed?', 'class-manager-pro' ), $row->batch_name ) ); ?>');" title="<?php esc_attr_e( 'Mark Completed', 'class-manager-pro' ); ?>">✓ <?php esc_html_e( 'Complete', 'class-manager-pro' ); ?></button>
											</form>
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
	<?php
}
