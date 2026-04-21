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
	$status          = $is_edit ? $batch->status : 'active';
	$public_form_url = $is_edit ? cmp_get_batch_public_form_url( $batch ) : '';
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
				<th scope="row"><label for="cmp-batch-name"><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-batch-name" name="batch_name" class="regular-text" value="<?php echo esc_attr( $is_edit ? $batch->batch_name : '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-fee"><?php esc_html_e( 'Batch Fee', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="number" id="cmp-batch-fee" name="batch_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? cmp_get_batch_effective_fee( $batch ) : '' ); ?>">
					<p class="description"><?php esc_html_e( 'This fee is now the live pricing source for new students in the batch. Class fee acts only as a fallback default.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-is-free"><?php esc_html_e( 'Free Class Batch', 'class-manager-pro' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="cmp-batch-is-free" name="is_free" value="1" <?php checked( $is_edit && ! empty( $batch->is_free ), true ); ?>>
						<?php esc_html_e( 'This is a free batch. Use the form only to collect student details, with no payment required.', 'class-manager-pro' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-start-date"><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></label></th>
				<td><input type="date" id="cmp-batch-start-date" name="start_date" value="<?php echo esc_attr( $is_edit ? $batch->start_date : '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-fee-due-date"><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="date" id="cmp-batch-fee-due-date" name="fee_due_date" value="<?php echo esc_attr( $is_edit ? $batch->fee_due_date : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Automatic reminders use this date to decide when to notify students with pending fees.', 'class-manager-pro' ); ?></p>
				</td>
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
				<th scope="row"><label for="cmp-batch-razorpay-link"><?php esc_html_e( 'Razorpay Page Link', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="url" id="cmp-batch-razorpay-link" name="razorpay_link" class="large-text" value="<?php echo esc_attr( $is_edit ? $batch->razorpay_link : '' ); ?>" placeholder="https://rzp.io/...">
					<p class="description"><?php esc_html_e( 'Students fill the batch intake form first, then continue to this payment link. Matching happens by the saved batch mapping plus the student phone/email.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-batch-razorpay-page-id"><?php esc_html_e( 'Razorpay Page ID', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="text" id="cmp-batch-razorpay-page-id" name="razorpay_page_id" class="regular-text" value="<?php echo esc_attr( $is_edit ? $batch->razorpay_page_id : '' ); ?>" placeholder="plink_xxx">
					<p class="description"><?php esc_html_e( 'Optional. The importer fills this automatically when data comes from Razorpay. You can also save it manually for stronger matching.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<?php if ( $is_edit ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Student Form Link', 'class-manager-pro' ); ?></th>
					<td>
						<div class="cmp-inline-tools">
							<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $public_form_url ); ?>" id="cmp-batch-public-link-<?php echo esc_attr( (int) $batch->id ); ?>">
							<button type="button" class="button" data-cmp-copy-target="#cmp-batch-public-link-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy Link', 'class-manager-pro' ); ?></button>
							<a class="button" href="<?php echo esc_url( $public_form_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Form', 'class-manager-pro' ); ?></a>
						</div>
					</td>
				</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Batch', 'class-manager-pro' ) : __( 'Add Batch', 'class-manager-pro' ) ); ?>
	</form>
	<?php
}

/**
 * Renders the attendance panel inside a batch workspace.
 *
 * @param object $batch Batch row.
 * @return void
 */
function cmp_render_batch_attendance_panel( $batch ) {
	$date    = sanitize_text_field( cmp_field( $_GET, 'attendance_date', current_time( 'Y-m-d' ) ) );
	$students = cmp_get_students( array( 'batch_id' => (int) $batch->id ) );
	$records  = cmp_get_batch_attendance( (int) $batch->id, $date );
	$summary  = cmp_get_batch_attendance_summary( (int) $batch->id, $date );
	$default  = (string) get_option( 'cmp_default_attendance_status', 'present' );
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h3><?php esc_html_e( 'Attendance', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php esc_html_e( 'Mark attendance per batch and per date. This stays inside wp-admin and does not show on the frontend.', 'class-manager-pro' ); ?></p>
			</div>
			<form method="get" class="cmp-inline-form">
				<input type="hidden" name="page" value="cmp-batches">
				<input type="hidden" name="action" value="view">
				<input type="hidden" name="id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<input type="date" name="attendance_date" value="<?php echo esc_attr( $date ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Load Date', 'class-manager-pro' ); ?></button>
			</form>
		</div>

		<div class="cmp-cards cmp-cards-3">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $summary['present'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Absent', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $summary['absent'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'On Leave', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $summary['leave'] ) ); ?></strong>
			</div>
		</div>

		<?php if ( empty( $students ) ) : ?>
			<p><?php esc_html_e( 'No students are enrolled in this batch yet.', 'class-manager-pro' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
				<input type="hidden" name="action" value="cmp_save_attendance">
				<input type="hidden" name="batch_id" value="<?php echo esc_attr( (int) $batch->id ); ?>">
				<input type="hidden" name="attendance_date" value="<?php echo esc_attr( $date ); ?>">
				<?php wp_nonce_field( 'cmp_save_attendance' ); ?>

				<div class="cmp-table-scroll">
					<table class="widefat striped cmp-attendance-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $students as $student ) : ?>
								<?php $record = isset( $records[ (int) $student->id ] ) ? $records[ (int) $student->id ] : null; ?>
								<tr>
									<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
									<td><?php echo esc_html( $student->phone ); ?></td>
									<td>
										<select name="attendance[<?php echo esc_attr( (int) $student->id ); ?>][status]">
											<?php foreach ( cmp_attendance_statuses() as $status ) : ?>
												<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $record ? $record->status : $default, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><input type="text" name="attendance[<?php echo esc_attr( (int) $student->id ); ?>][notes]" value="<?php echo esc_attr( $record ? $record->notes : '' ); ?>" class="large-text"></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php submit_button( __( 'Save Attendance', 'class-manager-pro' ) ); ?>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Renders the batch detail panel.
 *
 * @param object $batch Batch row.
 */
function cmp_render_batch_detail_panel( $batch ) {
	$metrics          = cmp_get_batch_metrics( (int) $batch->id );
	$students         = cmp_get_students( array( 'batch_id' => (int) $batch->id ) );
	$payments         = cmp_get_payments(
		array(
			'batch_id' => (int) $batch->id,
			'limit'    => 12,
		)
	);
	$public_form_url  = cmp_get_batch_public_form_url( $batch );
	$student_list_url = cmp_admin_url(
		'cmp-students',
		array(
			'batch_id' => (int) $batch->id,
			'class_id' => (int) $batch->class_id,
		)
	);
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php echo esc_html( $batch->batch_name ); ?></h2>
				<p class="cmp-muted"><?php echo esc_html( $batch->class_name ); ?><?php echo $batch->start_date ? ' | ' . esc_html( $batch->start_date ) : ''; ?><?php echo ! empty( $batch->is_free ) ? ' | ' . esc_html__( 'Free Batch', 'class-manager-pro' ) : ''; ?></p>
			</div>
			<div class="cmp-toolbar">
				<a class="button" href="<?php echo esc_url( $student_list_url ); ?>"><?php esc_html_e( 'Open Students', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( $public_form_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Intake Form', 'class-manager-pro' ); ?></a>
				<?php if ( ! empty( $batch->razorpay_link ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $batch->razorpay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Razorpay Page', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $metrics['student_count'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['revenue'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $metrics['pending_fee'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Batch Fee', 'class-manager-pro' ); ?></span>
				<strong><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free', 'class-manager-pro' ) : esc_html( cmp_format_money( cmp_get_batch_effective_fee( $batch ) ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-detail-grid">
			<p><span><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->start_date ? $batch->start_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->fee_due_date ? $batch->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Razorpay Page ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->razorpay_page_id ? $batch->razorpay_page_id : __( 'Manual mapping', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Payment Type', 'class-manager-pro' ); ?></span><strong><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free form only', 'class-manager-pro' ) : esc_html__( 'Paid', 'class-manager-pro' ); ?></strong></p>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<div class="cmp-link-block">
				<h3><?php esc_html_e( 'Student Intake Link', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php esc_html_e( 'Share this single form link with students. Class and batch are already fixed by the plugin, so students never need to type IDs.', 'class-manager-pro' ); ?></p>
				<div class="cmp-inline-tools">
					<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $public_form_url ); ?>" id="cmp-batch-share-link-<?php echo esc_attr( (int) $batch->id ); ?>">
					<button type="button" class="button" data-cmp-copy-target="#cmp-batch-share-link-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy Link', 'class-manager-pro' ); ?></button>
				</div>
			</div>
			<div class="cmp-link-block">
				<h3><?php esc_html_e( 'Payment Mapping', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php echo ! empty( $batch->is_free ) ? esc_html__( 'This is a free batch, so students only need the intake form. No Razorpay payment is required.', 'class-manager-pro' ) : esc_html__( 'Save the Razorpay link and page ID here. Webhooks and imports use them to place payments in the right batch automatically.', 'class-manager-pro' ); ?></p>
				<?php if ( ! empty( $batch->razorpay_link ) && empty( $batch->is_free ) ) : ?>
					<div class="cmp-inline-tools">
						<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $batch->razorpay_link ); ?>" id="cmp-batch-razorpay-display-<?php echo esc_attr( (int) $batch->id ); ?>">
						<button type="button" class="button" data-cmp-copy-target="#cmp-batch-razorpay-display-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy Razorpay Link', 'class-manager-pro' ); ?></button>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'No Razorpay page link is saved for this batch yet.', 'class-manager-pro' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<div>
				<h3><?php esc_html_e( 'Students in This Batch', 'class-manager-pro' ); ?></h3>
				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Paid', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $students ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No students in this batch yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( array_slice( $students, 0, 10 ) as $student ) : ?>
									<tr>
										<td><?php echo esc_html( $student->name ); ?><br><span class="cmp-muted"><?php echo esc_html( $student->unique_id ); ?></span></td>
										<td><?php echo esc_html( $student->phone ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></td>
										<td><?php echo esc_html( cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<div>
				<h3><?php esc_html_e( 'Recent Payments', 'class-manager-pro' ); ?></h3>
				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Mode', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $payments ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No payments in this batch yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $payments as $payment ) : ?>
									<tr>
										<td><?php echo esc_html( $payment->student_name ); ?></td>
										<td><?php echo esc_html( cmp_format_money( $payment->amount ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $payment->payment_mode ) ); ?></td>
										<td><?php echo esc_html( $payment->payment_date ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>

	<?php if ( cmp_is_attendance_enabled() ) : ?>
		<?php cmp_render_batch_attendance_panel( $batch ); ?>
	<?php endif; ?>
	<?php
}

/**
 * Renders the batches page.
 */
function cmp_render_batches_page() {
	cmp_require_manage_options();

	$action     = sanitize_key( cmp_field( $_GET, 'action' ) );
	$id         = absint( cmp_field( $_GET, 'id', 0 ) );
	$batch      = $id ? cmp_get_batch( $id ) : null;
	$edit_batch = ( 'edit' === $action && $batch ) ? $batch : null;
	$view_batch = ( 'view' === $action && $batch ) ? $batch : null;
	$metrics    = cmp_get_batch_overview_metrics();
	$batch_rows = cmp_get_batches_with_metrics();
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'This is the main enrollment workspace now. Save the batch fee, due date, and Razorpay mapping here, then share the generated intake form link with students.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

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

		<?php if ( $view_batch ) : ?>
			<?php cmp_render_batch_detail_panel( $view_batch ); ?>
		<?php endif; ?>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<h2><?php esc_html_e( 'Batch Workspace', 'class-manager-pro' ); ?></h2>
				<?php if ( $view_batch || $edit_batch ) : ?>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches' ) ); ?>"><?php esc_html_e( 'Back to Batch List', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
			</div>

			<div class="cmp-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Batch Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Fee Due', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $batch_rows ) ) : ?>
							<tr><td colspan="9"><?php esc_html_e( 'No batches found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $batch_rows as $row ) : ?>
								<?php
								$view_url        = cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) );
								$edit_url        = cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $row->id ) );
								$delete_url      = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_delete_batch&id=' . (int) $row->id ), 'cmp_delete_batch_' . (int) $row->id );
								$public_form_url = cmp_get_batch_public_form_url( $row );
								?>
								<tr>
									<td><?php echo esc_html( $row->batch_name ); ?></td>
									<td><?php echo esc_html( $row->class_name ); ?></td>
									<td><?php echo ! empty( $row->is_free ) ? esc_html__( 'Free', 'class-manager-pro' ) : esc_html( cmp_format_money( cmp_get_batch_effective_fee( $row ) ) ); ?></td>
									<td><?php echo esc_html( $row->fee_due_date ? $row->fee_due_date : '-' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row->student_count ) ); ?></td>
									<td><?php echo esc_html( cmp_format_money( $row->revenue ) ); ?></td>
									<td><?php echo esc_html( cmp_format_money( $row->pending_fee ) ); ?></td>
									<td><span class="cmp-status cmp-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
									<td class="cmp-actions">
										<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'Manage', 'class-manager-pro' ); ?></a>
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
										<a href="<?php echo esc_url( $public_form_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Form', 'class-manager-pro' ); ?></a>
										<a class="cmp-delete-link" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'class-manager-pro' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="cmp-panel">
			<h2><?php echo esc_html( $edit_batch ? __( 'Edit Batch', 'class-manager-pro' ) : __( 'Add Batch', 'class-manager-pro' ) ); ?></h2>
			<?php cmp_render_batch_form( $edit_batch, 'cmp-batches' ); ?>
		</section>
	</div>
	<?php
}
