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
					<select id="cmp-student-class" name="class_id" data-cmp-class-select data-cmp-fee-target="#cmp-student-total-fee" required>
						<option value=""><?php esc_html_e( 'Select class', 'class-manager-pro' ); ?></option>
						<?php foreach ( $classes as $class ) : ?>
							<option value="<?php echo esc_attr( (int) $class->id ); ?>" data-total-fee="<?php echo esc_attr( $class->total_fee ); ?>" <?php selected( $selected_class, (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-batch"><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-student-batch" name="batch_id" data-cmp-batches required>
						<option value=""><?php esc_html_e( 'Select batch', 'class-manager-pro' ); ?></option>
						<?php foreach ( $batches as $batch ) : ?>
							<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $selected_batch, (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-total-fee"><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></label></th>
				<td><input type="number" id="cmp-student-total-fee" name="total_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? $student->total_fee : '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-paid-fee"><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></label></th>
				<td><input type="number" id="cmp-student-paid-fee" name="paid_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? $student->paid_fee : '' ); ?>"></td>
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

	$remaining = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<h2><?php echo esc_html( $student->name ); ?></h2>
			<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id ) ) . '#cmp-add-payment' ); ?>"><?php esc_html_e( 'Add Payment', 'class-manager-pro' ); ?></a>
		</div>

		<div class="cmp-detail-grid">
			<p><span><?php esc_html_e( 'Student ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->unique_id ); ?></strong></p>
			<p><span><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->phone ); ?></strong></p>
			<p><span><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->email ); ?></strong></p>
			<p><span><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->class_name ); ?></strong></p>
			<p><span><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->batch_name ); ?></strong></p>
			<p><span><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $student->total_fee ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $remaining ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ucfirst( $student->status ) ); ?></strong></p>
		</div>

		<?php if ( '' !== $student->notes ) : ?>
			<div class="cmp-notes"><?php echo nl2br( esc_html( $student->notes ) ); ?></div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Payments', 'class-manager-pro' ); ?></h3>
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
		<p class="cmp-page-intro"><?php esc_html_e( 'Use Batches as the main intake workspace. Students can now be added manually here, through the shared batch form link, or automatically after batch-linked Razorpay payments.', 'class-manager-pro' ); ?></p>
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

		<form method="get" class="cmp-filter-form cmp-toolbar" data-cmp-action="cmp_filter_students" data-cmp-target=".cmp-student-results">
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

		<section class="cmp-panel">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody class="cmp-student-results">
					<?php echo cmp_render_student_rows( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</tbody>
			</table>
		</section>
	</div>
	<?php
}
