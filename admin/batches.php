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
				<th scope="row"><label for="cmp-batch-razorpay-link"><?php esc_html_e( 'Razorpay Page Link', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="url" id="cmp-batch-razorpay-link" name="razorpay_link" class="large-text" value="<?php echo esc_attr( $is_edit ? $batch->razorpay_link : '' ); ?>" placeholder="https://rzp.io/...">
					<p class="description"><?php esc_html_e( 'Students first fill the batch form link, then continue to this Razorpay page for payment.', 'class-manager-pro' ); ?></p>
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
 * Renders the batch detail panel.
 *
 * @param object $batch Batch row.
 */
function cmp_render_batch_detail_panel( $batch ) {
	$metrics         = cmp_get_batch_metrics( (int) $batch->id );
	$students        = cmp_get_students( array( 'batch_id' => (int) $batch->id ) );
	$payments        = cmp_get_payments(
		array(
			'batch_id' => (int) $batch->id,
			'limit'    => 12,
		)
	);
	$public_form_url = cmp_get_batch_public_form_url( $batch );
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
				<p class="cmp-muted"><?php echo esc_html( $batch->class_name ); ?><?php echo $batch->start_date ? ' | ' . esc_html( $batch->start_date ) : ''; ?></p>
			</div>
			<div class="cmp-toolbar">
				<a class="button" href="<?php echo esc_url( $student_list_url ); ?>"><?php esc_html_e( 'Open Students', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( $public_form_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Student Form', 'class-manager-pro' ); ?></a>
				<?php if ( ! empty( $batch->razorpay_link ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $batch->razorpay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Razorpay Page', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-3">
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
		</div>

		<div class="cmp-grid cmp-grid-2">
			<div class="cmp-link-block">
				<h3><?php esc_html_e( 'Student Intake Link', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php esc_html_e( 'Share this single link with students. The class and batch are filled automatically.', 'class-manager-pro' ); ?></p>
				<div class="cmp-inline-tools">
					<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $public_form_url ); ?>" id="cmp-batch-share-link-<?php echo esc_attr( (int) $batch->id ); ?>">
					<button type="button" class="button" data-cmp-copy-target="#cmp-batch-share-link-<?php echo esc_attr( (int) $batch->id ); ?>"><?php esc_html_e( 'Copy Link', 'class-manager-pro' ); ?></button>
				</div>
			</div>
			<div class="cmp-link-block">
				<h3><?php esc_html_e( 'Razorpay Mapping', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php esc_html_e( 'Students should use the batch form first. Their record is created in this batch, then the Razorpay payment attaches to that student automatically by phone or email.', 'class-manager-pro' ); ?></p>
				<?php if ( ! empty( $batch->razorpay_link ) ) : ?>
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
			<div>
				<h3><?php esc_html_e( 'Recent Payments', 'class-manager-pro' ); ?></h3>
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
	</section>
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
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'This is now the main workspace for enrollment. Save the Razorpay page link on the batch, share the generated student form link, and let the plugin attach new students and payments to the correct batch automatically.', 'class-manager-pro' ); ?></p>
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

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $batch_rows ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No batches found.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $batch_rows as $row ) : ?>
							<?php
							$view_url       = cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) );
							$edit_url       = cmp_admin_url( 'cmp-batches', array( 'action' => 'edit', 'id' => (int) $row->id ) );
							$delete_url     = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_delete_batch&id=' . (int) $row->id ), 'cmp_delete_batch_' . (int) $row->id );
							$public_form_url = cmp_get_batch_public_form_url( $row );
							?>
							<tr>
								<td><?php echo esc_html( $row->batch_name ); ?></td>
								<td><?php echo esc_html( $row->class_name ); ?></td>
								<td><?php echo esc_html( $row->start_date ); ?></td>
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
		</section>

		<section class="cmp-panel">
			<h2><?php echo esc_html( $edit_batch ? __( 'Edit Batch', 'class-manager-pro' ) : __( 'Add Batch', 'class-manager-pro' ) ); ?></h2>
			<?php cmp_render_batch_form( $edit_batch, 'cmp-batches' ); ?>
		</section>
	</div>
	<?php
}
