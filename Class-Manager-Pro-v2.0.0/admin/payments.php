<?php
/**
 * Payments admin pages.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a payment form.
 *
 * @param int         $selected_student_id Selected student ID.
 * @param object|null $payment Optional payment row for edit mode.
 */
function cmp_render_payment_form( $selected_student_id = 0, $payment = null ) {
	$payment    = is_object( $payment ) ? $payment : null;
	$is_edit    = $payment && ! empty( $payment->id );
	$students   = $is_edit ? array() : cmp_get_students();
	$now        = current_time( 'timestamp' );
	$student_id = $is_edit ? (int) $payment->student_id : absint( $selected_student_id );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" id="<?php echo esc_attr( $is_edit ? 'cmp-edit-payment' : 'cmp-add-payment' ); ?>">
		<input type="hidden" name="action" value="cmp_save_payment">
		<input type="hidden" name="return_page" value="cmp-payments">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (int) $payment->id ); ?>">
		<?php endif; ?>
		<?php wp_nonce_field( 'cmp_save_payment' ); ?>

		<table class="form-table" role="presentation">
			<?php if ( $is_edit ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Payment', 'class-manager-pro' ); ?></th>
					<td>
						<strong><?php echo esc_html( '#' . (int) $payment->id ); ?></strong>
						<p class="description">
							<?php
							printf(
								/* translators: 1: student name 2: class name 3: batch name */
								esc_html__( 'Editing %1$s in %2$s / %3$s. Student and batch stay locked so historical payment context remains accurate.', 'class-manager-pro' ),
								$payment->student_name ? esc_html( $payment->student_name ) : esc_html__( 'Unknown student', 'class-manager-pro' ),
								$payment->class_name ? esc_html( $payment->class_name ) : esc_html__( 'Unassigned', 'class-manager-pro' ),
								$payment->batch_name ? esc_html( $payment->batch_name ) : esc_html__( 'Not assigned', 'class-manager-pro' )
							);
							?>
						</p>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row"><label for="cmp-payment-student"><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></label></th>
					<td>
						<select id="cmp-payment-student" name="student_id" required data-cmp-searchable="1">
							<option value=""><?php esc_html_e( 'Select student', 'class-manager-pro' ); ?></option>
							<?php foreach ( $students as $student ) : ?>
								<?php
								$student_label = sprintf(
									'%1$s - %2$s (%3$s) / %4$s',
									$student->unique_id,
									$student->name,
									$student->class_name ? $student->class_name : __( 'Unassigned', 'class-manager-pro' ),
									cmp_get_student_batch_label( $student )
								);
								?>
								<option value="<?php echo esc_attr( (int) $student->id ); ?>" <?php selected( $student_id, (int) $student->id ); ?>>
									<?php echo esc_html( $student_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Payments are saved against the selected student\'s current class and batch. If the amount is higher than the remaining fee, it will be adjusted automatically.', 'class-manager-pro' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="cmp-payment-amount"><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></label></th>
				<td>
					<input
						type="number"
						id="cmp-payment-amount"
						name="amount"
						min="0.01"
						step="0.01"
						required
						value="<?php echo esc_attr( $is_edit ? (float) $payment->amount : '' ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Overpayments are capped to the remaining allowed fee, and exact duplicate payment entries are blocked.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-mode"><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-payment-mode" name="payment_mode">
						<?php foreach ( cmp_payment_modes() as $mode ) : ?>
							<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $is_edit ? $payment->payment_mode : 'manual', $mode ); ?>><?php echo esc_html( ucfirst( $mode ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-transaction"><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="text" id="cmp-payment-transaction" name="transaction_id" class="regular-text" value="<?php echo esc_attr( $is_edit ? $payment->transaction_id : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Transaction IDs must stay unique across saved and trashed payments.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-date"><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></label></th>
				<td>
					<input
						type="datetime-local"
						id="cmp-payment-date"
						name="payment_date"
						value="<?php echo esc_attr( $is_edit ? wp_date( 'Y-m-d\TH:i', strtotime( $payment->payment_date ) ) : wp_date( 'Y-m-d\TH:i', $now ) ); ?>"
					>
				</td>
			</tr>
		</table>

		<div class="cmp-toolbar">
			<?php submit_button( $is_edit ? __( 'Update Payment', 'class-manager-pro' ) : __( 'Add Payment', 'class-manager-pro' ), 'primary', 'submit', false ); ?>
			<?php if ( $is_edit ) : ?>
				<a class="button" href="<?php echo esc_url( cmp_get_payment_view_url( (int) $payment->id, 'cmp-payments' ) ); ?>"><?php esc_html_e( 'Cancel', 'class-manager-pro' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
	<?php
}

/**
 * Renders the payments page navigation.
 *
 * @param string $current_page Current page slug.
 */
function cmp_render_payment_page_nav( $current_page ) {
	$current_page = cmp_clean_return_page( $current_page, 'cmp-payments' );
	?>
	<nav class="nav-tab-wrapper">
		<a class="nav-tab <?php echo esc_attr( 'cmp-payments' === $current_page ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments' ) ); ?>"><?php esc_html_e( 'Payments', 'class-manager-pro' ); ?></a>
		<a class="nav-tab <?php echo esc_attr( 'cmp-payments-trash' === $current_page ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-payments-trash' ) ); ?>"><?php esc_html_e( 'Trash', 'class-manager-pro' ); ?></a>
	</nav>
	<?php
}

/**
 * Renders an inline payment action form.
 *
 * @param string $action_url Action URL.
 * @param string $label Button label.
 * @param string $confirm Confirmation message.
 * @param string $button_class Button CSS classes.
 */
function cmp_render_payment_action_form( $action_url, $label, $confirm = '', $button_class = 'button button-secondary' ) {
	?>
	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="cmp-inline-form" <?php echo $confirm ? 'data-cmp-confirm="' . esc_attr( $confirm ) . '"' : ''; ?>>
		<button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

/**
 * Renders a payment detail panel.
 *
 * @param int    $payment_id Payment ID.
 * @param string $page_slug Current page slug.
 */
function cmp_render_payment_detail_panel( $payment_id, $page_slug = 'cmp-payments' ) {
	$page_slug      = cmp_clean_return_page( $page_slug, 'cmp-payments' );
	$expected_state = 'cmp-payments-trash' === $page_slug ? 'trash' : 'all';
	$payment        = cmp_get_payment( $payment_id, $expected_state );

	if ( ! $payment && 'cmp-payments' === $page_slug ) {
		$payment = cmp_get_payment( $payment_id, 'trash' );
	}

	if ( ! $payment ) {
		?>
		<section class="cmp-panel cmp-callout cmp-callout-warning">
			<h2><?php esc_html_e( 'Payment not found', 'class-manager-pro' ); ?></h2>
			<p><?php esc_html_e( 'The requested payment record could not be loaded.', 'class-manager-pro' ); ?></p>
		</section>
		<?php
		return;
	}

	$audit_rows       = cmp_get_payment_audit_history( (int) $payment->id, 25 );
	$is_deleted       = ! empty( $payment->is_deleted );
	$student_label    = $payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' );
	$batch_label      = $payment->batch_name ? $payment->batch_name : __( 'Not assigned', 'class-manager-pro' );
	$class_label      = $payment->class_name ? $payment->class_name : __( 'Unassigned', 'class-manager-pro' );
	$restore_url      = cmp_get_payment_restore_url( (int) $payment->id, 'cmp-payments' );
	$force_delete_url = cmp_get_payment_force_delete_url( (int) $payment->id, 'cmp-payments-trash' );
	$delete_url       = cmp_get_payment_delete_url( (int) $payment->id, 'cmp-payments' );
	$edit_url         = cmp_get_payment_edit_url( (int) $payment->id, 'cmp-payments' );
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php echo esc_html( sprintf( __( 'Payment #%d', 'class-manager-pro' ), (int) $payment->id ) ); ?></h2>
				<p class="cmp-muted"><?php echo esc_html( $student_label ); ?> | <?php echo esc_html( $class_label ); ?> / <?php echo esc_html( $batch_label ); ?></p>
			</div>
			<div class="cmp-toolbar">
				<a class="button" href="<?php echo esc_url( cmp_admin_url( $is_deleted ? 'cmp-payments-trash' : 'cmp-payments' ) ); ?>"><?php esc_html_e( 'Back to List', 'class-manager-pro' ); ?></a>
				<?php if ( ! $is_deleted ) : ?>
					<a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
					<?php cmp_render_payment_action_form( $delete_url, __( 'Move to Trash', 'class-manager-pro' ), __( 'Move this payment to Trash?', 'class-manager-pro' ) ); ?>
				<?php else : ?>
					<?php cmp_render_payment_action_form( $restore_url, __( 'Restore', 'class-manager-pro' ), __( 'Restore this payment?', 'class-manager-pro' ), 'button button-primary' ); ?>
					<?php cmp_render_payment_action_form( $force_delete_url, __( 'Delete Permanently', 'class-manager-pro' ), __( 'Permanently delete this payment? This cannot be undone.', 'class-manager-pro' ) ); ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="cmp-detail-grid">
			<p><span><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student_label ); ?></strong></p>
			<p><span><?php esc_html_e( 'Student ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $payment->student_unique_id ? $payment->student_unique_id : __( 'Not available', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $class_label ); ?></strong></p>
			<p><span><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch_label ); ?></strong></p>
			<p><span><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $payment->amount ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Original Amount', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( isset( $payment->original_amount ) && (float) $payment->original_amount > 0 ? $payment->original_amount : $payment->amount ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Charge Amount', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( isset( $payment->charge_amount ) ? $payment->charge_amount : 0 ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Final Amount', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( cmp_get_payment_display_total( $payment ) ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Mode', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ucfirst( $payment->payment_mode ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $payment->transaction_id ? $payment->transaction_id : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Payment Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $payment->payment_date ); ?></strong></p>
			<p><span><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $is_deleted ? __( 'Trashed', 'class-manager-pro' ) : __( 'Active', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Student Email', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $payment->student_email ? $payment->student_email : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Student Phone', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $payment->student_phone ? $payment->student_phone : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
			<p><span><?php esc_html_e( 'Created', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $payment->created_at ); ?></strong></p>
		</div>

		<h3><?php esc_html_e( 'Audit History', 'class-manager-pro' ); ?></h3>
		<div class="cmp-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Admin', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Old Value', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'New Value', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $audit_rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No audit history available yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $audit_rows as $audit_row ) : ?>
							<tr>
								<td><?php echo esc_html( $audit_row->created_at ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $audit_row->action_type ) ) ); ?></td>
								<td><?php echo esc_html( $audit_row->admin_name ? $audit_row->admin_name : __( 'System', 'class-manager-pro' ) ); ?></td>
								<td><?php echo esc_html( cmp_get_payment_audit_snapshot_summary( $audit_row->old_value ) ); ?></td>
								<td><?php echo esc_html( cmp_get_payment_audit_snapshot_summary( $audit_row->new_value ) ); ?></td>
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
 * Renders the payment list section.
 *
 * @param array  $payments Payment rows.
 * @param array  $pagination Pagination data.
 * @param array  $filters Active filters.
 * @param string $page_slug Current page slug.
 */
function cmp_render_payment_list_section( $payments, $pagination, $filters, $page_slug ) {
	$is_trash         = 'cmp-payments-trash' === $page_slug;
	$feedback_id      = $is_trash ? 'cmp-payment-trash-feedback' : 'cmp-payment-bulk-feedback';
	$empty_message    = $is_trash ? __( 'Trash is empty.', 'class-manager-pro' ) : __( 'No payments found.', 'class-manager-pro' );
	$delete_label     = $is_trash ? __( 'Delete Permanently', 'class-manager-pro' ) : __( 'Move to Trash', 'class-manager-pro' );
	$delete_confirm   = $is_trash ? __( 'Permanently delete this payment? This cannot be undone.', 'class-manager-pro' ) : __( 'Move this payment to Trash?', 'class-manager-pro' );
	?>
	<section class="cmp-panel">
		<?php if ( ! $is_trash ) : ?>
			<div class="cmp-toolbar cmp-bulk-toolbar">
				<?php wp_nonce_field( 'cmp_admin_nonce', 'cmp_admin_ajax_nonce' ); ?>
				<select id="cmp-payment-bulk-action">
					<option value=""><?php esc_html_e( 'Bulk actions', 'class-manager-pro' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Move selected to Trash', 'class-manager-pro' ); ?></option>
				</select>
				<button
					type="button"
					class="button button-secondary"
					data-cmp-bulk-apply="1"
					data-cmp-entity-type="payment"
					data-cmp-action-select="#cmp-payment-bulk-action"
					data-cmp-checkbox=".cmp-payment-select"
					data-cmp-feedback="#<?php echo esc_attr( $feedback_id ); ?>"
				><?php esc_html_e( 'Apply', 'class-manager-pro' ); ?></button>
				<span class="cmp-muted" id="<?php echo esc_attr( $feedback_id ); ?>"></span>
			</div>
		<?php else : ?>
			<p class="cmp-muted" id="<?php echo esc_attr( $feedback_id ); ?>"><?php esc_html_e( 'Restore a payment when it was removed by mistake, or permanently delete it when you are sure.', 'class-manager-pro' ); ?></p>
		<?php endif; ?>

		<div class="cmp-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<?php if ( ! $is_trash ) : ?>
							<th><input type="checkbox" id="cmp-payment-select-all" data-cmp-select-all=".cmp-payment-select"></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Payment ID', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Student Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class / Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $payments ) ) : ?>
						<tr><td colspan="<?php echo esc_attr( $is_trash ? 8 : 9 ); ?>"><?php echo esc_html( $empty_message ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $payments as $payment ) : ?>
							<?php
							$view_url         = cmp_get_payment_view_url( (int) $payment->id, $page_slug );
							$edit_url         = cmp_get_payment_edit_url( (int) $payment->id, 'cmp-payments' );
							$delete_url       = cmp_get_payment_delete_url( (int) $payment->id, 'cmp-payments' );
							$restore_url      = cmp_get_payment_restore_url( (int) $payment->id, 'cmp-payments' );
							$force_delete_url = cmp_get_payment_force_delete_url( (int) $payment->id, 'cmp-payments-trash' );
							?>
							<tr data-cmp-row-id="payment-<?php echo esc_attr( (int) $payment->id ); ?>">
								<?php if ( ! $is_trash ) : ?>
									<td><input type="checkbox" class="cmp-payment-select" value="<?php echo esc_attr( (int) $payment->id ); ?>"></td>
								<?php endif; ?>
								<td>#<?php echo esc_html( (int) $payment->id ); ?></td>
								<td><?php echo esc_html( $payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' ) ); ?><br><span class="cmp-muted"><?php echo esc_html( $payment->student_unique_id ); ?></span></td>
								<td><?php echo esc_html( $payment->class_name ? $payment->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?><br><span class="cmp-muted"><?php echo esc_html( $payment->batch_name ? $payment->batch_name : __( 'Not assigned', 'class-manager-pro' ) ); ?></span></td>
								<td>
									<?php echo esc_html( cmp_format_money( $payment->amount ) ); ?>
									<?php if ( cmp_payment_has_gateway_charge( $payment ) ) : ?>
										<br><span class="cmp-muted"><?php echo esc_html( sprintf( __( 'Final %s', 'class-manager-pro' ), cmp_format_money( cmp_get_payment_display_total( $payment ) ) ) ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ucfirst( $payment->payment_mode ) ); ?></td>
								<td><?php echo esc_html( $payment->transaction_id ? $payment->transaction_id : __( 'Not set', 'class-manager-pro' ) ); ?></td>
								<td><?php echo esc_html( $payment->payment_date ); ?></td>
								<td class="cmp-actions">
									<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'class-manager-pro' ); ?></a>
									<?php if ( ! $is_trash ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
										<a
											class="cmp-delete-link"
											href="<?php echo esc_url( $delete_url ); ?>"
											data-id="<?php echo esc_attr( (int) $payment->id ); ?>"
											data-type="payment"
											data-cmp-ajax-delete="1"
											data-cmp-entity-type="payment"
											data-cmp-entity-id="<?php echo esc_attr( (int) $payment->id ); ?>"
											data-cmp-confirm="<?php echo esc_attr( $delete_confirm ); ?>"
											data-cmp-feedback="#<?php echo esc_attr( $feedback_id ); ?>"
										><?php echo esc_html( $delete_label ); ?></a>
									<?php else : ?>
										<?php cmp_render_payment_action_form( $restore_url, __( 'Restore', 'class-manager-pro' ), __( 'Restore this payment?', 'class-manager-pro' ), 'button button-primary' ); ?>
										<?php cmp_render_payment_action_form( $force_delete_url, __( 'Delete Permanently', 'class-manager-pro' ), __( 'Permanently delete this payment? This cannot be undone.', 'class-manager-pro' ) ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php cmp_render_pagination( $pagination, $filters ); ?>
	</section>
	<?php
}

/**
 * Renders the shared payments screen.
 *
 * @param string $deleted_status Payment list deleted status.
 */
function cmp_render_payments_screen( $deleted_status = 'active' ) {
	cmp_require_manage_options();

	$is_trash            = 'trash' === $deleted_status;
	$page_slug           = $is_trash ? 'cmp-payments-trash' : 'cmp-payments';
	$selected_student_id = absint( cmp_field( $_GET, 'student_id', 0 ) );
	$search              = sanitize_text_field( cmp_field( $_GET, 'search' ) );
	$payment_mode        = sanitize_key( cmp_field( $_GET, 'payment_mode' ) );
	$balance_status      = $is_trash ? '' : sanitize_key( cmp_field( $_GET, 'balance_status' ) );
	$assignment_status   = $is_trash ? '' : sanitize_key( cmp_field( $_GET, 'assignment_status' ) );
	$action              = sanitize_key( cmp_field( $_GET, 'action' ) );
	$payment_id          = absint( cmp_field( $_GET, 'id', 0 ) );
	$paged               = cmp_get_current_page_number();
	$per_page            = cmp_get_default_per_page();
	$payment_filters     = array(
		'search'            => $search,
		'payment_mode'      => $payment_mode,
		'balance_status'    => $balance_status,
		'assignment_status' => $assignment_status,
		'deleted_status'    => $deleted_status,
	);
	$pagination          = cmp_get_pagination_data( cmp_get_payments_count( $payment_filters ), $paged, $per_page );
	$payments            = cmp_get_payments(
		array(
			'search'            => $search,
			'payment_mode'      => $payment_mode,
			'balance_status'    => $balance_status,
			'assignment_status' => $assignment_status,
			'deleted_status'    => $deleted_status,
			'limit'             => $pagination['per_page'],
			'offset'            => $pagination['offset'],
		)
	);
	$export_url          = ! $is_trash ? wp_nonce_url(
		add_query_arg(
			array(
				'page'              => 'cmp-payments',
				'cmp_export'        => 'payments',
				'search'            => $search,
				'payment_mode'      => $payment_mode,
				'balance_status'    => $balance_status,
				'assignment_status' => $assignment_status,
			),
			admin_url( 'admin.php' )
		),
		'cmp_export_payments'
	) : '';
	$view_payment        = $payment_id ? cmp_get_payment( $payment_id, $deleted_status ) : null;
	$any_payment         = $payment_id ? cmp_get_payment( $payment_id, 'all' ) : null;
	$edit_payment        = ( ! $is_trash && 'edit' === $action && $payment_id ) ? cmp_get_payment( $payment_id, 'active' ) : null;
	$page_title          = $is_trash ? __( 'Payment Trash', 'class-manager-pro' ) : __( 'Payments', 'class-manager-pro' );
	$page_intro          = $is_trash
		? __( 'Soft-deleted payments stay here until you restore them or permanently remove them.', 'class-manager-pro' )
		: __( 'Only completed payments are saved here. Failed or authorized-only Razorpay payments are ignored during webhook sync and historical imports. Search by student, transaction, or payment ID, edit mistakes safely, and move incorrect entries to Trash without losing history.', 'class-manager-pro' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php echo esc_html( $page_title ); ?></h1>
		<p class="cmp-page-intro"><?php echo esc_html( $page_intro ); ?></p>
		<?php cmp_render_payment_page_nav( $page_slug ); ?>
		<?php cmp_render_notice(); ?>

		<?php if ( ! $is_trash && 'edit' === $action && $edit_payment ) : ?>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Edit Payment', 'class-manager-pro' ); ?></h2>
				<?php cmp_render_payment_form( 0, $edit_payment ); ?>
			</section>
		<?php elseif ( ! $is_trash && 'edit' === $action && $payment_id ) : ?>
			<section class="cmp-panel cmp-callout cmp-callout-warning">
				<h2><?php esc_html_e( 'Payment cannot be edited', 'class-manager-pro' ); ?></h2>
				<p><?php esc_html_e( 'Only active payments can be edited. Restore the payment first if it is currently in Trash.', 'class-manager-pro' ); ?></p>
			</section>
		<?php elseif ( 'view' === $action && $payment_id && $view_payment ) : ?>
			<?php cmp_render_payment_detail_panel( $payment_id, $page_slug ); ?>
		<?php elseif ( 'view' === $action && $payment_id && $any_payment && $is_trash ) : ?>
			<section class="cmp-panel cmp-callout cmp-callout-warning">
				<h2><?php esc_html_e( 'Payment is active', 'class-manager-pro' ); ?></h2>
				<p><?php esc_html_e( 'This payment is already active and no longer appears in Trash. Open the main Payments view to review or edit it.', 'class-manager-pro' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( cmp_get_payment_view_url( $payment_id, 'cmp-payments' ) ); ?>"><?php esc_html_e( 'Open Active Payment', 'class-manager-pro' ); ?></a></p>
			</section>
		<?php elseif ( 'view' === $action && $payment_id && $any_payment ) : ?>
			<section class="cmp-panel cmp-callout cmp-callout-warning">
				<h2><?php esc_html_e( 'Payment is in Trash', 'class-manager-pro' ); ?></h2>
				<p><?php esc_html_e( 'This payment is no longer active. Open the Trash view to restore it or permanently delete it.', 'class-manager-pro' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( cmp_get_payment_view_url( $payment_id, 'cmp-payments-trash' ) ); ?>"><?php esc_html_e( 'Open Trash Record', 'class-manager-pro' ); ?></a></p>
			</section>
		<?php elseif ( 'view' === $action && $payment_id ) : ?>
			<section class="cmp-panel cmp-callout cmp-callout-warning">
				<h2><?php esc_html_e( 'Payment not found', 'class-manager-pro' ); ?></h2>
				<p><?php esc_html_e( 'The requested payment record could not be loaded.', 'class-manager-pro' ); ?></p>
			</section>
		<?php elseif ( ! $is_trash ) : ?>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Add Manual Payment', 'class-manager-pro' ); ?></h2>
				<?php cmp_render_payment_form( $selected_student_id ); ?>
			</section>
		<?php endif; ?>

		<form method="get" class="cmp-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>">
			<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search student, transaction, payment ID', 'class-manager-pro' ); ?>">
			<select name="payment_mode" data-cmp-searchable="1">
				<option value=""><?php esc_html_e( 'All modes', 'class-manager-pro' ); ?></option>
				<?php foreach ( cmp_payment_modes() as $mode ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $payment_mode, $mode ); ?>><?php echo esc_html( ucfirst( $mode ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! $is_trash ) : ?>
				<select name="balance_status" data-cmp-searchable="1">
					<option value=""><?php esc_html_e( 'All payment states', 'class-manager-pro' ); ?></option>
					<option value="pending" <?php selected( $balance_status, 'pending' ); ?>><?php esc_html_e( 'Pending fee students', 'class-manager-pro' ); ?></option>
					<option value="paid" <?php selected( $balance_status, 'paid' ); ?>><?php esc_html_e( 'Fully paid students', 'class-manager-pro' ); ?></option>
				</select>
				<select name="assignment_status" data-cmp-searchable="1">
					<option value=""><?php esc_html_e( 'All batch links', 'class-manager-pro' ); ?></option>
					<option value="assigned" <?php selected( $assignment_status, 'assigned' ); ?>><?php esc_html_e( 'Assigned to batch', 'class-manager-pro' ); ?></option>
					<option value="unassigned" <?php selected( $assignment_status, 'unassigned' ); ?>><?php esc_html_e( 'Unassigned payments', 'class-manager-pro' ); ?></option>
				</select>
			<?php endif; ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
			<?php if ( ! $is_trash ) : ?>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
			<?php endif; ?>
		</form>

		<?php cmp_render_payment_list_section( $payments, $pagination, $payment_filters, $page_slug ); ?>
	</div>
	<?php
}

/**
 * Renders the payments page.
 */
function cmp_render_payments_page() {
	cmp_render_payments_screen( 'active' );
}

/**
 * Renders the payment trash page.
 */
function cmp_render_payment_trash_page() {
	cmp_render_payments_screen( 'trash' );
}
