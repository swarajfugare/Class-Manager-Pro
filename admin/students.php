<?php
/**
 * Students admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a student form.
 *
 * @param object|null $student Student row.
 * @param string      $return_page Return page slug.
 */
function cmp_render_student_form( $student = null, $return_page = 'cmp-students' ) {
	$is_edit        = $student && ! empty( $student->id );
	$classes        = cmp_get_classes();
	$batches        = cmp_get_batches();
	$selected_class = $is_edit ? (int) $student->class_id : 0;
	$selected_batch = $is_edit ? (int) $student->batch_id : 0;
	$status         = $is_edit ? $student->status : 'active';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form cmp-student-form">
		<input type="hidden" name="action" value="cmp_save_student">
		<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (int) $student->id ); ?>">
		<?php endif; ?>
		<?php wp_nonce_field( 'cmp_save_student' ); ?>

		<table class="form-table" role="presentation">
			<?php if ( $is_edit ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Student ID', 'class-manager-pro' ); ?></th>
					<td><strong><?php echo esc_html( $student->unique_id ); ?></strong></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="cmp-student-name"><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-student-name" name="name" class="regular-text" value="<?php echo esc_attr( $is_edit ? $student->name : '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-phone"><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-student-phone" name="phone" class="regular-text" value="<?php echo esc_attr( $is_edit ? $student->phone : '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-email"><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></label></th>
				<td><input type="email" id="cmp-student-email" name="email" class="regular-text" value="<?php echo esc_attr( $is_edit ? $student->email : '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-class"><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-student-class" name="class_id" data-cmp-class-select required>
						<option value=""><?php esc_html_e( 'Select class', 'class-manager-pro' ); ?></option>
						<?php foreach ( $classes as $class ) : ?>
							<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $selected_class, (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-batch"><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-student-batch" name="batch_id" data-cmp-batches data-cmp-batch-fee-target="#cmp-student-total-fee" required>
						<option value=""><?php esc_html_e( 'Select batch', 'class-manager-pro' ); ?></option>
						<?php foreach ( $batches as $batch ) : ?>
							<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" data-batch-fee="<?php echo esc_attr( cmp_get_batch_effective_fee( $batch ) ); ?>" <?php selected( $selected_batch, (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Choose the batch first. The total fee field will use the batch fee automatically, and you can still adjust it for special cases.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-total-fee"><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></label></th>
				<td><input type="number" id="cmp-student-total-fee" name="total_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? $student->total_fee : '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-paid-fee"><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="number" id="cmp-student-paid-fee" name="paid_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? $student->paid_fee : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Manual payments are still best added from the Payments screen so the ledger stays clean. This field is useful for initial setup or migration.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-status"><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-student-status" name="status">
						<?php foreach ( cmp_student_statuses() as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( ucfirst( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-notes"><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></label></th>
				<td><textarea id="cmp-student-notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $is_edit ? $student->notes : '' ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Student', 'class-manager-pro' ) : __( 'Add Student', 'class-manager-pro' ) ); ?>
	</form>
	<?php
}

/**
 * Renders a read-only student detail panel.
 *
 * @param int $student_id Student ID.
 */
function cmp_render_student_detail_panel( $student_id ) {
	$student  = cmp_get_student( $student_id );
	$payments = cmp_get_payments( array( 'student_id' => $student_id ) );

	if ( ! $student ) {
		return;
	}

	$remaining      = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
	$batch_manage   = cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $student->batch_id ) );
	$email_reminder = cmp_get_email_reminder_url( $student );
	$wa_reminder    = cmp_get_whatsapp_reminder_url( $student );
	$profile_url    = cmp_get_student_profile_url( $student );
	$payment_status = cmp_get_student_payment_status( $student );
	$lifetime_value = cmp_get_student_ltv( $student_id );
	$next_course_id = ! empty( $student->class_next_course_id ) ? absint( $student->class_next_course_id ) : 0;
	$next_course_title = $next_course_id ? cmp_get_tutor_course_title( $next_course_id ) : '';
	$upsell_url = ( 'completed' === $student->status && $next_course_id ) ? wp_nonce_url( admin_url( 'admin-post.php?action=cmp_enroll_student_next_course&id=' . (int) $student->id ), 'cmp_enroll_student_next_course_' . (int) $student->id ) : '';
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php echo esc_html( $student->name ); ?></h2>
				<p class="cmp-muted"><?php echo esc_html( $student->class_name ); ?><?php echo $student->batch_name ? ' | ' . esc_html( $student->batch_name ) : ''; ?></p>
			</div>
			<div class="cmp-toolbar">
				<a class="button" href="<?php echo esc_url( $batch_manage ); ?>"><?php esc_html_e( 'Open Batch Workspace', 'class-manager-pro' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id ) ) . '#cmp-add-payment' ); ?>"><?php esc_html_e( 'Add Payment', 'class-manager-pro' ); ?></a>
				<?php if ( $profile_url ) : ?>
					<a class="button" href="<?php echo esc_url( $profile_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Profile', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
				<?php if ( $upsell_url ) : ?>
					<a class="button" href="<?php echo esc_url( $upsell_url ); ?>"><?php esc_html_e( 'Enroll in Next Course', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
				<?php if ( $email_reminder ) : ?>
					<a class="button" href="<?php echo esc_url( $email_reminder ); ?>"><?php esc_html_e( 'Email Reminder', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
				<?php if ( $wa_reminder ) : ?>
					<a class="button" href="<?php echo esc_url( $wa_reminder ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp Reminder', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="cmp-detail-grid">
			<p><span><?php esc_html_e( 'Student ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->unique_id ); ?></strong></p>
			<p><span><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->phone ); ?></strong></p>
			<p><span><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->email ? $student->email : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $student->total_fee ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $remaining ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->fee_due_date ? $student->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Payment Status', 'class-manager-pro' ); ?></span><strong><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></strong></p>
			<p><span><?php esc_html_e( 'Lifetime Value', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $lifetime_value ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ucfirst( $student->status ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'WordPress User', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ! empty( $student->user_id ) ? '#' . (int) $student->user_id : __( 'Not linked', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Next Course', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $next_course_title ? $next_course_title : __( 'Not configured', 'class-manager-pro' ) ); ?></strong></p>
		</div>

		<?php if ( '' !== $student->notes ) : ?>
			<div class="cmp-notes"><?php echo nl2br( esc_html( $student->notes ) ); ?></div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Payments', 'class-manager-pro' ); ?></h3>
		<div class="cmp-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Mode', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $payments ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No payments found.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $payments as $payment ) : ?>
							<tr>
								<td><?php echo esc_html( cmp_format_money( $payment->amount ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $payment->payment_mode ) ); ?></td>
								<td><?php echo esc_html( $payment->transaction_id ); ?></td>
								<td><?php echo esc_html( $payment->payment_date ); ?></td>
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
 * Renders the students page.
 */
function cmp_render_students_page() {
	cmp_require_manage_options();

	$action     = sanitize_key( cmp_field( $_GET, 'action' ) );
	$id         = absint( cmp_field( $_GET, 'id', 0 ) );
	$student    = ( 'edit' === $action && $id ) ? cmp_get_student( $id ) : null;
	$filters    = cmp_read_student_filters( $_GET );
	$paged      = cmp_get_current_page_number();
	$per_page   = cmp_get_default_per_page();
	$pagination = cmp_get_pagination_data( cmp_get_students_count( $filters ), $paged, $per_page );
	$row_args   = array_merge(
		$filters,
		array(
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		)
	);
	$classes    = cmp_get_classes();
	$batches    = cmp_get_batches();
	$metrics    = cmp_get_student_overview_metrics();
	$export_url = wp_nonce_url(
		add_query_arg(
			array_merge(
				array(
					'page'       => 'cmp-students',
					'cmp_export' => 'students',
				),
				$filters
			),
			admin_url( 'admin.php' )
		),
		'cmp_export_students'
	);
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Students can now arrive from three paths: manual entry, the shared batch intake form, or imported Razorpay data. Batch fee controls the default total fee for new records.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['total_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Active Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['active_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Completed Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['completed_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fee'] ) ); ?></strong>
			</div>
		</div>

		<?php if ( 'view' === $action && $id ) : ?>
			<?php cmp_render_student_detail_panel( $id ); ?>
		<?php elseif ( $student ) : ?>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Edit Student', 'class-manager-pro' ); ?></h2>
				<?php cmp_render_student_form( $student, 'cmp-students' ); ?>
			</section>
		<?php endif; ?>

		<form method="get" class="cmp-filter-form cmp-toolbar">
			<input type="hidden" name="page" value="cmp-students">
			<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search name, phone, email, ID', 'class-manager-pro' ); ?>">
			<select name="class_id" data-cmp-class-select>
				<option value="0"><?php esc_html_e( 'All classes', 'class-manager-pro' ); ?></option>
				<?php foreach ( $classes as $class ) : ?>
					<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $filters['class_id'], (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="batch_id" data-cmp-batches>
				<option value="0"><?php esc_html_e( 'All batches', 'class-manager-pro' ); ?></option>
				<?php foreach ( $batches as $batch ) : ?>
					<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $filters['batch_id'], (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'class-manager-pro' ); ?></option>
				<?php foreach ( cmp_student_statuses() as $status ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
			<a class="button cmp-export-link" data-base-url="<?php echo esc_url( $export_url ); ?>" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-add-new' ) ); ?>"><?php esc_html_e( 'Add New', 'class-manager-pro' ); ?></a>
		</form>

		<div class="cmp-toolbar cmp-bulk-toolbar">
			<?php wp_nonce_field( 'cmp_admin_nonce', 'cmp_admin_ajax_nonce' ); ?>
			<select id="cmp-student-bulk-action">
				<option value=""><?php esc_html_e( 'Bulk actions', 'class-manager-pro' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete selected', 'class-manager-pro' ); ?></option>
				<option value="change_batch"><?php esc_html_e( 'Change batch', 'class-manager-pro' ); ?></option>
				<option value="export"><?php esc_html_e( 'Export selected', 'class-manager-pro' ); ?></option>
			</select>
			<select id="cmp-student-bulk-class" data-cmp-class-select>
				<option value="0"><?php esc_html_e( 'Choose class', 'class-manager-pro' ); ?></option>
				<?php foreach ( $classes as $class ) : ?>
					<option value="<?php echo esc_attr( (int) $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="cmp-student-bulk-batch" data-cmp-batches>
				<option value="0"><?php esc_html_e( 'Choose batch', 'class-manager-pro' ); ?></option>
				<?php foreach ( $batches as $batch ) : ?>
					<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>"><?php echo esc_html( $batch->batch_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button button-secondary" id="cmp-student-bulk-apply"><?php esc_html_e( 'Apply', 'class-manager-pro' ); ?></button>
			<span class="cmp-muted" id="cmp-student-bulk-feedback"></span>
		</div>

		<section class="cmp-panel">
			<div class="cmp-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><input type="checkbox" id="cmp-student-select-all" data-cmp-select-all=".cmp-student-select"></th>
							<th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Payment Status', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody class="cmp-student-results">
						<?php echo cmp_render_student_rows( $row_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</tbody>
				</table>
			</div>
			<?php cmp_render_pagination( $pagination, $filters ); ?>
		</section>

		<?php if ( ! $student && 'view' !== $action ) : ?>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></h2>
				<?php cmp_render_student_form( null, 'cmp-students' ); ?>
			</section>
		<?php endif; ?>
	</div>
	<?php
}
