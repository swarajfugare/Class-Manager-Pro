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
	$selected_class = $is_edit ? (int) $student->class_id : absint( cmp_field( $_GET, 'class_id', 0 ) );
	$selected_batch = $is_edit ? (int) $student->batch_id : absint( cmp_field( $_GET, 'batch_id', 0 ) );
	$status         = $is_edit ? $student->status : 'active';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form cmp-student-form" data-cmp-ajax-form="cmp_save_student_ajax">
		<input type="hidden" name="action" value="cmp_save_student">
		<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
		<?php if ( ! empty( $_GET['return_action'] ) ) : ?>
			<input type="hidden" name="return_action" value="<?php echo esc_attr( sanitize_key( $_GET['return_action'] ) ); ?>">
			<input type="hidden" name="return_id" value="<?php echo esc_attr( absint( cmp_field( $_GET, 'return_id', 0 ) ) ); ?>">
		<?php endif; ?>
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
				<td>
					<input type="email" id="cmp-student-email" name="email" class="regular-text" value="<?php echo esc_attr( $is_edit ? $student->email : '' ); ?>" required>
					<p class="description"><?php esc_html_e( 'A valid email address is required for admissions, follow-up notifications, and duplicate matching.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-city"><?php esc_html_e( 'City', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="text" id="cmp-student-city" name="city" class="regular-text" value="<?php echo esc_attr( $is_edit && ! empty( $student->city_original ) ? $student->city_original : ( $is_edit && ! empty( $student->city_normalized ) ? $student->city_normalized : '' ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-student-lead-source"><?php esc_html_e( 'Lead Source', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="text" id="cmp-student-lead-source" name="lead_source" class="regular-text" value="<?php echo esc_attr( $is_edit && ! empty( $student->lead_source ) ? $student->lead_source : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Instagram, Razorpay, Referral, Direct', 'class-manager-pro' ); ?>">
				</td>
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
				<th scope="row"><?php esc_html_e( 'Capacity Override', 'class-manager-pro' ); ?></th>
				<td>
					<input type="hidden" name="capacity_override" value="0">
					<label>
						<input type="checkbox" name="capacity_override" value="1">
						<?php esc_html_e( 'Allow this admission even if the batch limit has been reached.', 'class-manager-pro' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Use this only when you need to manually override the maximum student count.', 'class-manager-pro' ); ?></p>
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
 * Renders the dedicated student profile and control center in admin.
 *
 * @param int $student_id Student ID.
 */
function cmp_render_student_profile_workspace( $student_id ) {
	$summary = cmp_get_student_lifetime_summary( $student_id );

	if ( ! $summary || empty( $summary['student'] ) ) {
		?>
		<div class="wrap cmp-wrap">
			<h1><?php esc_html_e( 'Student Profile', 'class-manager-pro' ); ?></h1>
			<div class="notice notice-error"><p><?php esc_html_e( 'Student record not found.', 'class-manager-pro' ); ?></p></div>
			<p><a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students' ) ); ?>">← <?php esc_html_e( 'Back to All Students', 'class-manager-pro' ); ?></a></p>
		</div>
		<?php
		return;
	}

	$student           = $summary['student'];
	$enrollments       = $summary['enrollments'];
	$payments          = $summary['payments'];
	$attendance        = $summary['attendance'];
	$classes           = cmp_get_classes();
	$batches           = cmp_get_batches();
	$payment_status    = cmp_get_student_payment_status( $student );
	$batch_label       = cmp_get_student_batch_label( $student );
	$current_pending   = max( 0.0, (float) $student->total_fee - (float) $student->paid_fee );
	$batch_manage_url  = ! empty( $student->batch_id ) ? cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $student->batch_id ) ) : '';
	$edit_profile_url  = cmp_admin_url( 'cmp-students', array( 'action' => 'edit', 'id' => (int) $student->id, 'return_action' => 'view', 'return_id' => (int) $student->id ) );
	$next_course_id    = ! empty( $student->class_next_course_id ) ? absint( $student->class_next_course_id ) : 0;
	$next_course_title = $next_course_id ? cmp_get_tutor_course_title( $next_course_id ) : '';
	$upsell_url        = ( 'completed' === $student->status && $next_course_id ) ? wp_nonce_url( admin_url( 'admin-post.php?action=cmp_enroll_student_next_course&id=' . (int) $student->id ), 'cmp_enroll_student_next_course_' . (int) $student->id ) : '';
	$active_tab        = sanitize_key( cmp_field( $_GET, 'profile_tab', 'overview' ) );

	if ( ! in_array( $active_tab, array( 'overview', 'classes', 'payments', 'attendance', 'print' ), true ) ) {
		$active_tab = 'overview';
	}

	// First Letter & Avatar
	$first_letter = strtoupper( mb_substr( trim( $student->name ), 0, 1 ) );
	$avatar_url   = '';
	if ( ! empty( $student->user_id ) ) {
		$avatar_url = get_avatar_url( (int) $student->user_id, array( 'size' => 128, 'default' => '404' ) );
	} elseif ( ! empty( $student->email ) ) {
		$avatar_url = get_avatar_url( $student->email, array( 'size' => 128, 'default' => '404' ) );
	}

	// WhatsApp & communication links
	$phone_clean = preg_replace( '/[^0-9]/', '', $student->phone );
	if ( 10 === strlen( $phone_clean ) ) {
		$phone_clean = '91' . $phone_clean;
	}
	$wa_direct_url    = $phone_clean ? 'https://wa.me/' . $phone_clean : '';
	$wa_reminder_url  = cmp_get_whatsapp_reminder_url( $student );
	$wa_welcome_text  = sprintf(
		"Hello %s,\nWelcome to %s! We are happy to have you in our %s batch. Please let us know if you need any assistance.",
		$student->name,
		$student->class_name,
		$student->batch_name
	);
	$wa_welcome_url   = $phone_clean ? 'https://wa.me/' . $phone_clean . '?text=' . rawurlencode( $wa_welcome_text ) : '';
	$phone_call_url   = ! empty( $student->phone ) ? 'tel:' . esc_attr( $student->phone ) : '';
	$email_mailto_url = ! empty( $student->email ) ? 'mailto:' . esc_attr( $student->email ) : '';
	$email_follow_url = cmp_get_email_reminder_url( $student, 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
	$institute_name   = get_bloginfo( 'name' );
	?>
	<div class="wrap cmp-wrap cmp-student-profile-wrap">
		<h1><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'One student workspace for active admissions, interested leads, pending fee follow-ups, completed students, bulk actions, and quick communication.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-students' ); ?>

		<div class="cmp-profile-breadcrumb" style="margin-top: 18px;">
			<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-students' ) ); ?>">← <?php esc_html_e( 'Back to All Students', 'class-manager-pro' ); ?></a>
			<?php if ( $batch_manage_url ) : ?>
				<a class="button" href="<?php echo esc_url( $batch_manage_url ); ?>"><?php esc_html_e( 'Open Batch Workspace', 'class-manager-pro' ); ?></a>
			<?php endif; ?>
		</div>

		<!-- Hero Section -->
		<div class="cmp-student-profile-hero">
			<div class="cmp-student-hero-main">
				<div class="cmp-student-avatar" aria-hidden="true">
					<?php if ( ! empty( $avatar_url ) ) : ?>
						<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $student->name ); ?>" class="cmp-student-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
						<span class="cmp-student-avatar-letter" style="display:none;"><?php echo esc_html( $first_letter ); ?></span>
					<?php else : ?>
						<span class="cmp-student-avatar-letter"><?php echo esc_html( $first_letter ); ?></span>
					<?php endif; ?>
				</div>
				<div class="cmp-student-identity">
					<div class="cmp-student-title-row">
						<h2><?php echo esc_html( $student->name ); ?></h2>
						<span class="cmp-pill cmp-pill-id"><?php echo esc_html( $student->unique_id ); ?></span>
						<span class="cmp-status cmp-status-<?php echo esc_attr( $student->status ); ?>"><?php echo esc_html( ucfirst( $student->status ) ); ?></span>
						<span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span>
					</div>
					<p class="cmp-student-subtext">
						<span><strong><?php esc_html_e( 'Class:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( $student->class_name ? $student->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?></span>
						<span>•</span>
						<span><strong><?php esc_html_e( 'Batch:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( $batch_label ? $batch_label : __( 'Not assigned', 'class-manager-pro' ) ); ?></span>
						<?php if ( ! empty( $student->city_normalized ) || ! empty( $student->city_original ) ) : ?>
							<span>•</span>
							<span><strong><?php esc_html_e( 'City:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( ! empty( $student->city_normalized ) ? $student->city_normalized : $student->city_original ); ?></span>
						<?php endif; ?>
						<span>•</span>
						<span><strong><?php esc_html_e( 'Enrolled Batches:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( $summary['total_enrollments'] ); ?></span>
					</p>
				</div>
			</div>

			<div class="cmp-student-hero-actions">
				<?php if ( (float) $summary['total_pending'] > 0 ) : ?>
					<button type="button" class="button button-primary" data-cmp-drawer-toggle="#cmp-drawer-collect-payment">
						+ <?php esc_html_e( 'Collect Payment', 'class-manager-pro' ); ?>
					</button>
				<?php else : ?>
					<span class="cmp-badge-pill cmp-badge-completed" style="font-size:13px;padding:6px 12px;background:#e6f4ea;color:#137333;font-weight:600;display:inline-flex;align-items:center;">
						✓ <?php esc_html_e( 'All Fees Cleared', 'class-manager-pro' ); ?>
					</span>
				<?php endif; ?>
				<button type="button" class="button button-secondary" data-cmp-drawer-toggle="#cmp-drawer-enroll-class">
					+ <?php esc_html_e( 'Enroll in Another Class', 'class-manager-pro' ); ?>
				</button>
				<a class="button" href="<?php echo esc_url( $edit_profile_url ); ?>">
					✏ <?php esc_html_e( 'Edit Profile', 'class-manager-pro' ); ?>
				</a>
				<button type="button" class="button" data-cmp-print-student="1">
					🖨 <?php esc_html_e( 'Print Student Card', 'class-manager-pro' ); ?>
				</button>
			</div>
		</div>

		<!-- Drawer 1: Quick Collect Payment -->
		<div id="cmp-drawer-collect-payment" class="cmp-drawer-card" style="display: none;">
			<div class="cmp-drawer-header">
				<h3><?php esc_html_e( 'Record / Collect Payment', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php esc_html_e( 'Instantly add a cash, UPI, or bank payment to this student without navigating away.', 'class-manager-pro' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
				<input type="hidden" name="action" value="cmp_quick_student_collect_payment">
				<input type="hidden" name="view_student_id" value="<?php echo esc_attr( (int) $student->id ); ?>">
				<?php wp_nonce_field( 'cmp_quick_student_collect_payment_' . (int) $student->id ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cmp-quick-pay-student-id"><?php esc_html_e( 'Apply to Class / Batch', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-quick-pay-student-id" name="student_id" required>
								<?php foreach ( $enrollments as $enr ) : ?>
									<?php $enr_balance = max( 0, (float) $enr->total_fee - (float) $enr->paid_fee ); ?>
									<option value="<?php echo esc_attr( (int) $enr->id ); ?>" <?php selected( (int) $student->id, (int) $enr->id ); ?>>
										<?php echo esc_html( $enr->class_name ); ?> - <?php echo esc_html( cmp_get_student_batch_label( $enr ) ); ?> (<?php echo esc_html( sprintf( __( 'Due: %s', 'class-manager-pro' ), cmp_format_money( $enr_balance ) ) ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-pay-amount"><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></label></th>
						<td>
							<input type="number" id="cmp-quick-pay-amount" name="amount" min="0.01" step="0.01" class="regular-text" value="<?php echo esc_attr( $current_pending > 0 ? $current_pending : '' ); ?>" placeholder="0.00" required>
							<p class="description"><?php echo esc_html( sprintf( __( 'Current batch pending balance: %s', 'class-manager-pro' ), cmp_format_money( $current_pending ) ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-pay-mode"><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-quick-pay-mode" name="payment_mode" required>
								<?php
								$allowed_modes = array( 'upi', 'razorpay', 'cash' );
								foreach ( $allowed_modes as $mode ) : ?>
									<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( 'upi', $mode ); ?>><?php echo esc_html( 'upi' === $mode ? 'UPI' : ucfirst( $mode ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-pay-txn"><?php esc_html_e( 'Transaction ID / UTR', 'class-manager-pro' ); ?></label></th>
						<td>
							<input type="text" id="cmp-quick-pay-txn" name="transaction_id" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. UPI Ref, Cheque No, Cash Receipt No', 'class-manager-pro' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-pay-date"><?php esc_html_e( 'Payment Date', 'class-manager-pro' ); ?></label></th>
						<td>
							<input type="date" id="cmp-quick-pay-date" name="payment_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-pay-notes"><?php esc_html_e( 'Notes / Remarks', 'class-manager-pro' ); ?></label></th>
						<td>
							<textarea id="cmp-quick-pay-notes" name="notes" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Optional payment remark...', 'class-manager-pro' ); ?>"></textarea>
						</td>
					</tr>
				</table>
				<div class="cmp-drawer-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save & Record Payment', 'class-manager-pro' ); ?></button>
					<button type="button" class="button" data-cmp-drawer-toggle="#cmp-drawer-collect-payment"><?php esc_html_e( 'Cancel', 'class-manager-pro' ); ?></button>
				</div>
			</form>
		</div>

		<!-- Drawer 2: Quick Enroll in Another Class -->
		<div id="cmp-drawer-enroll-class" class="cmp-drawer-card" style="display: none;">
			<div class="cmp-drawer-header">
				<h3><?php esc_html_e( 'Enroll Student in Another Class / Batch', 'class-manager-pro' ); ?></h3>
				<p class="cmp-muted"><?php esc_html_e( 'Quickly enroll this student into a second course or upcoming batch with their existing contact details.', 'class-manager-pro' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
				<input type="hidden" name="action" value="cmp_quick_student_enroll_another_class">
				<input type="hidden" name="source_student_id" value="<?php echo esc_attr( (int) $student->id ); ?>">
				<?php wp_nonce_field( 'cmp_quick_enroll_another_class_' . (int) $student->id ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cmp-quick-enroll-class"><?php esc_html_e( 'Select Class', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-quick-enroll-class" name="class_id" data-cmp-class-select required>
								<option value=""><?php esc_html_e( 'Choose class...', 'class-manager-pro' ); ?></option>
								<?php foreach ( $classes as $c ) : ?>
									<option value="<?php echo esc_attr( (int) $c->id ); ?>"><?php echo esc_html( $c->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-enroll-batch"><?php esc_html_e( 'Select Batch', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-quick-enroll-batch" name="batch_id" data-cmp-batches data-cmp-batch-fee-target="#cmp-quick-enroll-fee" required>
								<option value=""><?php esc_html_e( 'Choose batch...', 'class-manager-pro' ); ?></option>
								<?php foreach ( $batches as $b ) : ?>
									<option value="<?php echo esc_attr( (int) $b->id ); ?>" data-class-id="<?php echo esc_attr( (int) $b->class_id ); ?>" data-batch-fee="<?php echo esc_attr( cmp_get_batch_effective_fee( $b ) ); ?>">
										<?php echo esc_html( $b->batch_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-enroll-fee"><?php esc_html_e( 'Agreed Total Fee', 'class-manager-pro' ); ?></label></th>
						<td>
							<input type="number" id="cmp-quick-enroll-fee" name="total_fee" min="0" step="0.01" class="regular-text" placeholder="0.00">
							<p class="description"><?php esc_html_e( 'Auto-filled from batch fee, or customize for special discounts.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-enroll-initial-payment"><?php esc_html_e( 'Initial Payment (Optional)', 'class-manager-pro' ); ?></label></th>
						<td>
							<input type="number" id="cmp-quick-enroll-initial-payment" name="initial_payment_amount" min="0" step="0.01" class="regular-text" placeholder="0.00">
							<p class="description"><?php esc_html_e( 'Leave 0 or empty if the student has not paid the new course fee yet.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-enroll-initial-mode"><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-quick-enroll-initial-mode" name="initial_payment_mode">
								<?php foreach ( cmp_payment_modes() as $mode ) : ?>
									<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( 'cash', $mode ); ?>><?php echo esc_html( ucfirst( $mode ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-quick-enroll-notes"><?php esc_html_e( 'Admission Notes', 'class-manager-pro' ); ?></label></th>
						<td>
							<textarea id="cmp-quick-enroll-notes" name="notes" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Reason or special remarks for enrolling in this class...', 'class-manager-pro' ); ?>"></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Capacity Override', 'class-manager-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="capacity_override" value="1">
								<?php esc_html_e( 'Allow enrollment even if batch capacity limit is reached.', 'class-manager-pro' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<div class="cmp-drawer-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm & Enroll Student', 'class-manager-pro' ); ?></button>
					<button type="button" class="button" data-cmp-drawer-toggle="#cmp-drawer-enroll-class"><?php esc_html_e( 'Cancel', 'class-manager-pro' ); ?></button>
				</div>
			</form>
		</div>

		<!-- 5 Metric KPI Cards (Full Width) -->
		<div class="cmp-cards cmp-cards-5">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Classes & Batches Joined', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $summary['total_enrollments'] ) ); ?></strong>
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( 'Across %d unique course(s)', 'class-manager-pro' ), $summary['unique_classes_count'] ) ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Fees Collected (LTV)', 'class-manager-pro' ); ?></span>
				<strong class="cmp-text-success"><?php echo esc_html( cmp_format_money( $summary['lifetime_paid_fee'] ) ); ?></strong>
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( 'Total Billed: %s', 'class-manager-pro' ), cmp_format_money( $summary['lifetime_total_fee'] ) ) ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Pending Balance', 'class-manager-pro' ); ?></span>
				<strong class="<?php echo $summary['lifetime_pending_fee'] > 0 ? 'cmp-text-warning' : 'cmp-text-muted'; ?>">
					<?php echo esc_html( cmp_format_money( $summary['lifetime_pending_fee'] ) ); ?>
				</strong>
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( 'Current Batch: %s', 'class-manager-pro' ), cmp_format_money( $current_pending ) ) ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Attendance Rate', 'class-manager-pro' ); ?></span>
				<strong><?php echo null !== $attendance['rate'] ? esc_html( $attendance['rate'] . '%' ) : esc_html__( 'N/A', 'class-manager-pro' ); ?></strong>
				<small class="cmp-muted"><?php echo esc_html( sprintf( __( '%1$d Present / %2$d Marked', 'class-manager-pro' ), $attendance['present'], $attendance['total'] ) ); ?></small>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Admission & Profile Status', 'class-manager-pro' ); ?></span>
				<strong><span class="cmp-status cmp-status-<?php echo esc_attr( $student->status ); ?>"><?php echo esc_html( ucfirst( $student->status ) ); ?></span></strong>
				<small class="cmp-muted"><?php echo esc_html( ! empty( $student->lead_source ) ? sprintf( __( 'Source: %s', 'class-manager-pro' ), ucfirst( $student->lead_source ) ) : sprintf( __( 'Joined: %s', 'class-manager-pro' ), date_i18n( get_option( 'date_format' ), strtotime( $student->created_at ) ) ) ); ?></small>
			</div>
		</div>

		<!-- Tabs Bar -->
		<nav class="nav-tab-wrapper cmp-profile-nav-tabs" aria-label="<?php esc_attr_e( 'Student Profile Sections', 'class-manager-pro' ); ?>">
			<a href="#overview" class="nav-tab cmp-profile-nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>" data-cmp-tab="overview">
				👤 <?php esc_html_e( 'Overview & Profile', 'class-manager-pro' ); ?>
			</a>
			<a href="#classes" class="nav-tab cmp-profile-nav-tab <?php echo 'classes' === $active_tab ? 'nav-tab-active' : ''; ?>" data-cmp-tab="classes">
				📚 <?php esc_html_e( 'Enrolled Classes & Batches', 'class-manager-pro' ); ?> (<?php echo count( $enrollments ); ?>)
			</a>
			<a href="#payments" class="nav-tab cmp-profile-nav-tab <?php echo 'payments' === $active_tab ? 'nav-tab-active' : ''; ?>" data-cmp-tab="payments">
				💰 <?php esc_html_e( 'Complete Payment History', 'class-manager-pro' ); ?> (<?php echo count( $payments ); ?>)
			</a>
			<a href="#attendance" class="nav-tab cmp-profile-nav-tab <?php echo 'attendance' === $active_tab ? 'nav-tab-active' : ''; ?>" data-cmp-tab="attendance">
				📅 <?php esc_html_e( 'Attendance Records', 'class-manager-pro' ); ?>
			</a>
			<a href="#print" class="nav-tab cmp-profile-nav-tab <?php echo 'print' === $active_tab ? 'nav-tab-active' : ''; ?>" data-cmp-tab="print">
				🖨 <?php esc_html_e( 'Print Student Statement', 'class-manager-pro' ); ?>
			</a>
		</nav>

		<!-- TAB 1: Overview & Profile -->
		<div id="cmp-profile-tab-overview" class="cmp-profile-tab-content" style="<?php echo 'overview' === $active_tab ? '' : 'display: none;'; ?>">
			<div class="cmp-profile-columns">
				<!-- Left Column: Details -->
				<section class="cmp-panel cmp-profile-col-main">
					<div class="cmp-panel-header">
						<h3><?php esc_html_e( 'Student Information', 'class-manager-pro' ); ?></h3>
						<a class="button button-small" href="<?php echo esc_url( $edit_profile_url ); ?>">✏ <?php esc_html_e( 'Edit All Details', 'class-manager-pro' ); ?></a>
					</div>
					<div class="cmp-detail-grid cmp-profile-detail-grid">
						<p><span><?php esc_html_e( 'Student Unique ID', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->unique_id ); ?></strong></p>
						<p><span><?php esc_html_e( 'Full Name', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->name ); ?></strong></p>
						<p><span><?php esc_html_e( 'Phone / Mobile', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->phone ); ?></strong></p>
						<p><span><?php esc_html_e( 'Email Address', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->email ? $student->email : __( 'Not provided', 'class-manager-pro' ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'City (Normalized)', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ! empty( $student->city_normalized ) ? $student->city_normalized : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'City (Original)', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ! empty( $student->city_original ) ? $student->city_original : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Lead Source', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( ! empty( $student->lead_source ) ? ucfirst( $student->lead_source ) : __( 'Direct Admission', 'class-manager-pro' ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Registration Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->created_at ); ?></strong></p>
						<p><span><?php esc_html_e( 'Current Class', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->class_name ); ?></strong></p>
						<p><span><?php esc_html_e( 'Current Batch', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch_label ? $batch_label : __( 'Not assigned', 'class-manager-pro' ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Current Batch Total Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $student->total_fee ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Current Batch Paid Fee', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $student->paid_fee ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Current Batch Balance', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( cmp_format_money( $current_pending ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $student->fee_due_date ? $student->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></strong></p>
						<p><span><?php esc_html_e( 'Payment Status', 'class-manager-pro' ); ?></span><strong><span class="cmp-status cmp-status-<?php echo esc_attr( $payment_status['key'] ); ?>"><?php echo esc_html( $payment_status['label'] ); ?></span></strong></p>
						<p><span><?php esc_html_e( 'WordPress User', 'class-manager-pro' ); ?></span>
							<strong>
								<?php if ( ! empty( $student->user_id ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $student->user_id ) ); ?>" target="_blank" rel="noopener">#<?php echo esc_html( (int) $student->user_id ); ?> (<?php esc_html_e( 'View Account', 'class-manager-pro' ); ?>)</a>
								<?php else : ?>
									<?php esc_html_e( 'Not linked', 'class-manager-pro' ); ?>
								<?php endif; ?>
							</strong>
						</p>
						<?php if ( $next_course_title ) : ?>
							<p><span><?php esc_html_e( 'Next Course', 'class-manager-pro' ); ?></span>
								<strong>
									<?php echo esc_html( $next_course_title ); ?>
									<?php if ( $upsell_url ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $upsell_url ); ?>"><?php esc_html_e( 'Enroll Now', 'class-manager-pro' ); ?></a>
									<?php endif; ?>
								</strong>
							</p>
						<?php endif; ?>
					</div>
				</section>

				<!-- Right Column: Quick Controls -->
				<div class="cmp-profile-col-side">
					<!-- Quick Status Changer -->
					<section class="cmp-panel">
						<h3><?php esc_html_e( 'Quick Status Changer', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Change student enrollment status for this batch with 1 click.', 'class-manager-pro' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-quick-status-form">
							<input type="hidden" name="action" value="cmp_quick_student_status_change">
							<input type="hidden" name="student_id" value="<?php echo esc_attr( (int) $student->id ); ?>">
							<?php wp_nonce_field( 'cmp_quick_student_status_' . (int) $student->id ); ?>
							<div class="cmp-quick-status-row">
								<select name="status" class="cmp-full-width">
									<?php foreach ( cmp_student_statuses() as $status_key ) : ?>
										<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $student->status, $status_key ); ?>>
											<?php echo esc_html( ucfirst( $status_key ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Update Status', 'class-manager-pro' ); ?></button>
							</div>
						</form>
					</section>

					<!-- Communication Hub -->
					<section class="cmp-panel">
						<h3><?php esc_html_e( 'Direct Communication', 'class-manager-pro' ); ?></h3>
						<div class="cmp-comm-buttons">
							<?php if ( $wa_direct_url ) : ?>
								<a class="button cmp-comm-btn cmp-comm-wa" href="<?php echo esc_url( $wa_direct_url ); ?>" target="_blank" rel="noopener">
									💬 <?php esc_html_e( 'WhatsApp Direct Chat', 'class-manager-pro' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $wa_reminder_url && $current_pending > 0 ) : ?>
								<a class="button cmp-comm-btn cmp-comm-wa-reminder" href="<?php echo esc_url( $wa_reminder_url ); ?>" target="_blank" rel="noopener">
									🔔 <?php esc_html_e( 'Send Fee Reminder WhatsApp', 'class-manager-pro' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $wa_welcome_url ) : ?>
								<a class="button cmp-comm-btn cmp-comm-wa-welcome" href="<?php echo esc_url( $wa_welcome_url ); ?>" target="_blank" rel="noopener">
									👋 <?php esc_html_e( 'Send Class Welcome WhatsApp', 'class-manager-pro' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $phone_call_url ) : ?>
								<a class="button cmp-comm-btn cmp-comm-call" href="<?php echo esc_url( $phone_call_url ); ?>">
									📞 <?php esc_html_e( 'Call Student', 'class-manager-pro' ); ?> (<?php echo esc_html( $student->phone ); ?>)
								</a>
							<?php endif; ?>
							<?php if ( $email_mailto_url ) : ?>
								<a class="button cmp-comm-btn cmp-comm-email" href="<?php echo esc_url( $email_mailto_url ); ?>">
									✉ <?php esc_html_e( 'Send Email', 'class-manager-pro' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $email_follow_url ) : ?>
								<a class="button cmp-send-email-link" href="<?php echo esc_url( $email_follow_url ); ?>" data-cmp-send-email="1" data-cmp-student-id="<?php echo esc_attr( (int) $student->id ); ?>" data-cmp-feedback="#cmp-student-email-feedback">
									📨 <?php esc_html_e( 'Send System Follow-Up Mail', 'class-manager-pro' ); ?>
								</a>
								<span class="cmp-muted" id="cmp-student-email-feedback"></span>
							<?php endif; ?>
						</div>
					</section>

					<!-- Quick Notes & Remarks -->
					<section class="cmp-panel">
						<h3><?php esc_html_e( 'Student Notes & Remarks', 'class-manager-pro' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="cmp_quick_student_update_notes">
							<input type="hidden" name="student_id" value="<?php echo esc_attr( (int) $student->id ); ?>">
							<?php wp_nonce_field( 'cmp_quick_update_notes_' . (int) $student->id ); ?>
							<textarea name="notes" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Add internal notes about fee payment arrangements, attendance, behavior, etc...', 'class-manager-pro' ); ?>"><?php echo esc_textarea( $student->notes ); ?></textarea>
							<div class="cmp-notes-action-row" style="margin-top: 8px;">
								<button type="submit" class="button"><?php esc_html_e( 'Save Notes', 'class-manager-pro' ); ?></button>
							</div>
						</form>
					</section>
				</div>
			</div>
		</div>

		<!-- TAB 2: Enrolled Classes & Batches -->
		<div id="cmp-profile-tab-classes" class="cmp-profile-tab-content" style="<?php echo 'classes' === $active_tab ? '' : 'display: none;'; ?>">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'All Enrolled Classes & Batches', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Lifetime enrollment record: every class and batch this student has joined at your institute.', 'class-manager-pro' ); ?></p>
					</div>
					<div>
						<button type="button" class="button button-primary" data-cmp-drawer-toggle="#cmp-drawer-enroll-class">
							+ <?php esc_html_e( 'Enroll in Another Class', 'class-manager-pro' ); ?>
						</button>
					</div>
				</div>

				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th>#</th>
								<th><?php esc_html_e( 'Class Name', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Enrolled Date', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Pending Balance', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Payment Status', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $idx = 1; ?>
							<?php foreach ( $enrollments as $enr ) : ?>
								<?php
								$enr_balance = max( 0.0, (float) $enr->total_fee - (float) $enr->paid_fee );
								$enr_status  = cmp_get_student_payment_status( $enr );
								$is_current  = ( (int) $enr->id === (int) $student->id );
								$batch_url   = ! empty( $enr->batch_id ) ? cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $enr->batch_id ) ) : '';
								$switch_url  = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $enr->id ) );
								?>
								<tr class="<?php echo $is_current ? 'cmp-row-highlight' : ''; ?>">
									<td><?php echo esc_html( $idx++ ); ?></td>
									<td>
										<strong><?php echo esc_html( $enr->class_name ? $enr->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?></strong>
										<?php if ( $is_current ) : ?>
											<span class="cmp-pill cmp-pill-current"><?php esc_html_e( 'Active Profile', 'class-manager-pro' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( cmp_get_student_batch_label( $enr ) ); ?></td>
									<td><?php echo esc_html( ! empty( $enr->created_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $enr->created_at ) ) : '—' ); ?></td>
									<td><span class="cmp-status cmp-status-<?php echo esc_attr( $enr->status ); ?>"><?php echo esc_html( ucfirst( $enr->status ) ); ?></span></td>
									<td><strong><?php echo esc_html( cmp_format_money( $enr->total_fee ) ); ?></strong></td>
									<td><strong class="cmp-text-success"><?php echo esc_html( cmp_format_money( $enr->paid_fee ) ); ?></strong></td>
									<td>
										<strong class="<?php echo $enr_balance > 0 ? 'cmp-text-warning' : 'cmp-text-muted'; ?>">
											<?php echo esc_html( cmp_format_money( $enr_balance ) ); ?>
										</strong>
									</td>
									<td><span class="cmp-status cmp-status-<?php echo esc_attr( $enr_status['key'] ); ?>"><?php echo esc_html( $enr_status['label'] ); ?></span></td>
									<td><?php echo esc_html( ! empty( $enr->fee_due_date ) ? $enr->fee_due_date : __( 'Not set', 'class-manager-pro' ) ); ?></td>
									<td class="cmp-actions">
										<?php if ( ! $is_current ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $switch_url ); ?>"><?php esc_html_e( 'Switch View', 'class-manager-pro' ); ?></a>
										<?php endif; ?>
										<?php if ( $batch_url ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $batch_url ); ?>"><?php esc_html_e( 'Batch Workspace', 'class-manager-pro' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr style="font-weight: 700; background: #f8fafc;">
								<td colspan="5" style="text-align: right;"><?php esc_html_e( 'Lifetime Totals Across All Batches:', 'class-manager-pro' ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $summary['lifetime_total_fee'] ) ); ?></td>
								<td class="cmp-text-success"><?php echo esc_html( cmp_format_money( $summary['lifetime_paid_fee'] ) ); ?></td>
								<td class="<?php echo $summary['lifetime_pending_fee'] > 0 ? 'cmp-text-warning' : ''; ?>"><?php echo esc_html( cmp_format_money( $summary['lifetime_pending_fee'] ) ); ?></td>
								<td colspan="3"></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>
		</div>

		<!-- TAB 3: Complete Payment History -->
		<div id="cmp-profile-tab-payments" class="cmp-profile-tab-content" style="<?php echo 'payments' === $active_tab ? '' : 'display: none;'; ?>">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'Complete Payment History (Lifetime Ledger)', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted" id="cmp-student-payment-feedback"><?php esc_html_e( 'All payments collected from this student across all classes with audit records and transaction details.', 'class-manager-pro' ); ?></p>
					</div>
					<div>
						<?php if ( (float) $summary['total_pending'] > 0 ) : ?>
							<button type="button" class="button button-primary" data-cmp-drawer-toggle="#cmp-drawer-collect-payment">
								+ <?php esc_html_e( 'Add / Collect Payment', 'class-manager-pro' ); ?>
							</button>
						<?php else : ?>
							<span style="color:#008a20;font-weight:600;font-size:13px;padding:6px 12px;background:#e6f4ea;border-radius:4px;display:inline-block;">✓ <?php esc_html_e( 'All Payments Cleared', 'class-manager-pro' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Payment ID', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class / Course', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Amount Collected', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Transaction ID / UTR', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Payment Date', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $payments ) ) : ?>
								<tr><td colspan="8"><?php esc_html_e( 'No payments recorded for this student yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $payments as $p ) : ?>
									<?php
									$del_url  = cmp_get_payment_delete_url( (int) $p->id, 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
									$view_url = cmp_get_payment_view_url( (int) $p->id, 'cmp-payments' );
									?>
									<tr data-cmp-row-id="payment-<?php echo esc_attr( (int) $p->id ); ?>">
										<td><strong>#<?php echo esc_html( (int) $p->id ); ?></strong></td>
										<td><?php echo esc_html( $p->class_name ? $p->class_name : __( 'Unassigned', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( $p->batch_name ? $p->batch_name : __( 'Unassigned', 'class-manager-pro' ) ); ?></td>
										<td><strong class="cmp-text-success" style="font-size: 15px;"><?php echo esc_html( cmp_format_money( $p->amount ) ); ?></strong></td>
										<td><span class="cmp-pill cmp-pill-mode"><?php echo esc_html( ucfirst( $p->payment_mode ) ); ?></span></td>
										<td><code><?php echo esc_html( $p->transaction_id ? $p->transaction_id : __( 'Not set', 'class-manager-pro' ) ); ?></code></td>
										<td><?php echo esc_html( $p->payment_date ); ?></td>
										<td class="cmp-actions">
											<a class="button button-small" href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'Audit Log', 'class-manager-pro' ); ?></a>
											<a
												class="button button-small cmp-delete-link"
												href="<?php echo esc_url( $del_url ); ?>"
												data-id="<?php echo esc_attr( (int) $p->id ); ?>"
												data-type="payment"
												data-cmp-ajax-delete="1"
												data-cmp-entity-type="payment"
												data-cmp-entity-id="<?php echo esc_attr( (int) $p->id ); ?>"
												data-cmp-confirm="<?php esc_attr_e( 'Move this payment to Trash?', 'class-manager-pro' ); ?>"
												data-cmp-feedback="#cmp-student-payment-feedback"
												data-cmp-refresh-page="1"
											><?php esc_html_e( 'Trash', 'class-manager-pro' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
						<tfoot>
							<tr style="font-weight: 700; background: #f8fafc;">
								<td colspan="3" style="text-align: right;"><?php esc_html_e( 'Total Collected Fees:', 'class-manager-pro' ); ?></td>
								<td class="cmp-text-success" style="font-size: 16px;"><?php echo esc_html( cmp_format_money( $summary['lifetime_paid_fee'] ) ); ?></td>
								<td colspan="2" style="text-align: right;"><?php esc_html_e( 'Total Outstanding Due:', 'class-manager-pro' ); ?></td>
								<td colspan="2" class="<?php echo $summary['lifetime_pending_fee'] > 0 ? 'cmp-text-warning' : ''; ?>" style="font-size: 16px;">
									<?php echo esc_html( cmp_format_money( $summary['lifetime_pending_fee'] ) ); ?>
								</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>
		</div>

		<!-- TAB 4: Attendance Records -->
		<div id="cmp-profile-tab-attendance" class="cmp-profile-tab-content" style="<?php echo 'attendance' === $active_tab ? '' : 'display: none;'; ?>">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h3><?php esc_html_e( 'Attendance Performance & History', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Detailed breakdown of student attendance across all class sessions.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<div class="cmp-attendance-pills-bar">
					<div class="cmp-att-pill cmp-att-pill-present">
						<span class="cmp-att-pill-num"><?php echo esc_html( $attendance['present'] ); ?></span>
						<span class="cmp-att-pill-label"><?php esc_html_e( 'Present Days', 'class-manager-pro' ); ?></span>
					</div>
					<div class="cmp-att-pill cmp-att-pill-absent">
						<span class="cmp-att-pill-num"><?php echo esc_html( $attendance['absent'] ); ?></span>
						<span class="cmp-att-pill-label"><?php esc_html_e( 'Absent Days', 'class-manager-pro' ); ?></span>
					</div>
					<div class="cmp-att-pill cmp-att-pill-leave">
						<span class="cmp-att-pill-num"><?php echo esc_html( $attendance['leave'] ); ?></span>
						<span class="cmp-att-pill-label"><?php esc_html_e( 'Approved Leaves', 'class-manager-pro' ); ?></span>
					</div>
					<div class="cmp-att-pill cmp-att-pill-rate">
						<span class="cmp-att-pill-num"><?php echo null !== $attendance['rate'] ? esc_html( $attendance['rate'] . '%' ) : esc_html__( 'N/A', 'class-manager-pro' ); ?></span>
						<span class="cmp-att-pill-label"><?php esc_html_e( 'Attendance Rate', 'class-manager-pro' ); ?></span>
					</div>
				</div>

				<div class="cmp-table-scroll" style="margin-top: 20px;">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Notes / Remarks', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $attendance['recent'] ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No attendance sessions marked for this student yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $attendance['recent'] as $att_log ) : ?>
									<tr>
										<td><strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $att_log->attendance_date ) ) ); ?></strong></td>
										<td><?php echo esc_html( $att_log->class_name ? $att_log->class_name : '—' ); ?></td>
										<td><?php echo esc_html( $att_log->batch_name ? $att_log->batch_name : '—' ); ?></td>
										<td>
											<span class="cmp-status cmp-status-att-<?php echo esc_attr( $att_log->status ); ?>">
												<?php echo esc_html( ucfirst( $att_log->status ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( ! empty( $att_log->notes ) ? $att_log->notes : '—' ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<!-- TAB 5: Printable Student Card & Statement -->
		<div id="cmp-profile-tab-print" class="cmp-profile-tab-content" style="<?php echo 'print' === $active_tab ? '' : 'display: none;'; ?>">
			<div class="cmp-print-toolbar">
				<button type="button" class="button button-primary button-hero" data-cmp-print-student="1">
					🖨 <?php esc_html_e( 'Print Official Student Card & Ledger', 'class-manager-pro' ); ?>
				</button>
				<p class="cmp-muted"><?php esc_html_e( 'Clicking print formats this document cleanly for paper A4 printing or PDF saving, automatically hiding WordPress dashboard navigation.', 'class-manager-pro' ); ?></p>
			</div>

			<!-- Official Printable Document -->
			<div class="cmp-printable-student-card">
				<div class="cmp-print-doc-header">
					<div>
						<h2><?php echo esc_html( $institute_name ); ?></h2>
						<p class="cmp-print-doc-sub"><?php esc_html_e( 'Official Student Admission & Account Statement', 'class-manager-pro' ); ?></p>
					</div>
					<div class="cmp-print-doc-meta">
						<p><strong><?php esc_html_e( 'Date Generated:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></p>
						<p><strong><?php esc_html_e( 'Student ID:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( $student->unique_id ); ?></p>
					</div>
				</div>

				<div class="cmp-print-student-info">
					<table class="cmp-print-info-table">
						<tr>
							<td><strong><?php esc_html_e( 'Student Name:', 'class-manager-pro' ); ?></strong></td>
							<td><?php echo esc_html( $student->name ); ?></td>
							<td><strong><?php esc_html_e( 'Phone / Mobile:', 'class-manager-pro' ); ?></strong></td>
							<td><?php echo esc_html( $student->phone ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Email:', 'class-manager-pro' ); ?></strong></td>
							<td><?php echo esc_html( $student->email ? $student->email : __( 'N/A', 'class-manager-pro' ) ); ?></td>
							<td><strong><?php esc_html_e( 'City:', 'class-manager-pro' ); ?></strong></td>
							<td><?php echo esc_html( ! empty( $student->city_normalized ) ? $student->city_normalized : ( ! empty( $student->city_original ) ? $student->city_original : __( 'N/A', 'class-manager-pro' ) ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Admission Date:', 'class-manager-pro' ); ?></strong></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $student->created_at ) ) ); ?></td>
							<td><strong><?php esc_html_e( 'Enrollment Status:', 'class-manager-pro' ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $student->status ) ); ?></td>
						</tr>
					</table>
				</div>

				<h4 class="cmp-print-heading"><?php esc_html_e( 'Enrolled Courses & Batches', 'class-manager-pro' ); ?></h4>
				<table class="cmp-print-table">
					<thead>
						<tr>
							<th>#</th>
							<th><?php esc_html_e( 'Class / Course', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Pending Balance', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $p_idx = 1; ?>
						<?php foreach ( $enrollments as $enr ) : ?>
							<?php $enr_bal = max( 0.0, (float) $enr->total_fee - (float) $enr->paid_fee ); ?>
							<tr>
								<td><?php echo esc_html( $p_idx++ ); ?></td>
								<td><?php echo esc_html( $enr->class_name ? $enr->class_name : '—' ); ?></td>
								<td><?php echo esc_html( cmp_get_student_batch_label( $enr ) ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $enr->total_fee ) ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $enr->paid_fee ) ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $enr_bal ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $enr->status ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h4 class="cmp-print-heading"><?php esc_html_e( 'Payment Transactions Ledger', 'class-manager-pro' ); ?></h4>
				<table class="cmp-print-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Receipt / Pay ID', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Course / Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Reference / UTR', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $payments ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No payment transactions recorded.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $payments as $p ) : ?>
								<tr>
									<td>#<?php echo esc_html( (int) $p->id ); ?></td>
									<td><?php echo esc_html( $p->class_name . ' - ' . $p->batch_name ); ?></td>
									<td><?php echo esc_html( $p->payment_date ); ?></td>
									<td><?php echo esc_html( ucfirst( $p->payment_mode ) ); ?></td>
									<td><?php echo esc_html( $p->transaction_id ? $p->transaction_id : '—' ); ?></td>
									<td><strong><?php echo esc_html( cmp_format_money( $p->amount ) ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- Financial Summary Box -->
				<div class="cmp-print-summary-box">
					<div class="cmp-print-summary-item">
						<span><?php esc_html_e( 'Total Course Fees:', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( cmp_format_money( $summary['lifetime_total_fee'] ) ); ?></strong>
					</div>
					<div class="cmp-print-summary-item">
						<span><?php esc_html_e( 'Total Paid Fees:', 'class-manager-pro' ); ?></span>
						<strong style="color: #15803d;"><?php echo esc_html( cmp_format_money( $summary['lifetime_paid_fee'] ) ); ?></strong>
					</div>
					<div class="cmp-print-summary-item">
						<span><?php esc_html_e( 'Balance Due:', 'class-manager-pro' ); ?></span>
						<strong style="color: #b91c1c;"><?php echo esc_html( cmp_format_money( $summary['lifetime_pending_fee'] ) ); ?></strong>
					</div>
				</div>

				<div class="cmp-print-signatures">
					<div class="cmp-print-sig-block">
						<div class="cmp-print-sig-line"></div>
						<p><?php esc_html_e( 'Student / Parent Signature', 'class-manager-pro' ); ?></p>
					</div>
					<div class="cmp-print-sig-block">
						<div class="cmp-print-sig-line"></div>
						<p><?php esc_html_e( 'Authorized Signatory & Seal', 'class-manager-pro' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Backwards compatibility wrapper for student detail panel.
 *
 * @param int $student_id Student ID.
 */
function cmp_render_student_detail_panel( $student_id ) {
	cmp_render_student_profile_workspace( $student_id );
}

/**
 * Renders the students page.
 */
function cmp_render_students_page() {
	cmp_require_manage_options();

	$action     = sanitize_key( cmp_field( $_GET, 'action' ) );
	$id         = absint( cmp_field( $_GET, 'id', 0 ) );

	if ( 'view' === $action && $id ) {
		cmp_render_student_profile_workspace( $id );
		return;
	}
	$current_tab = sanitize_key( cmp_field( $_GET, 'tab', 'active' ) );
	if ( ! in_array( $current_tab, array( 'active', 'interested', 'pending', 'completed' ), true ) ) {
		$current_tab = 'active';
	}
	$student    = ( 'edit' === $action && $id ) ? cmp_get_student( $id ) : null;
	$filters    = cmp_read_student_filters( $_GET );

	if ( empty( $action ) ) {
		if ( 'active' === $current_tab && '' === $filters['status'] ) {
			$filters['status'] = 'active';
		} elseif ( 'completed' === $current_tab ) {
			$filters['status'] = 'completed';
		} elseif ( 'pending' === $current_tab ) {
			$filters['fee_status'] = 'pending';
		}
	}

	$paged      = cmp_get_current_page_number();
	$per_page   = cmp_get_default_per_page();
	$pagination = cmp_get_pagination_data( cmp_get_students_count( $filters ), $paged, $per_page );
	$pagination_filters = array_merge( $filters, array( 'tab' => $current_tab ) );
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
	$student_tabs = array(
		array( 'key' => 'active', 'label' => __( 'Active', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-students', array( 'tab' => 'active', 'status' => 'active' ) ) ),
		array( 'key' => 'interested', 'label' => __( 'Interested', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-students', array( 'tab' => 'interested' ) ) ),
		array( 'key' => 'pending', 'label' => __( 'Pending', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-students', array( 'tab' => 'pending', 'fee_status' => 'pending' ) ) ),
		array( 'key' => 'completed', 'label' => __( 'Completed', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-students', array( 'tab' => 'completed', 'status' => 'completed' ) ) ),
	);
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'One student workspace for active admissions, interested leads, pending fee follow-ups, completed students, bulk actions, and quick communication.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-students' ); ?>
		<?php cmp_render_workflow_tabs( $student_tabs, $current_tab, __( 'Student sections', 'class-manager-pro' ) ); ?>

		<?php if ( 'interested' === $current_tab && empty( $action ) ) : ?>
			<?php cmp_render_interested_students_workspace( true ); ?>
		</div>
			<?php
			return;
		endif;
		?>

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

		<?php if ( $student ) : ?>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Edit Student', 'class-manager-pro' ); ?></h2>
				<?php cmp_render_student_form( $student, 'cmp-students' ); ?>
			</section>
		<?php endif; ?>

		<div id="cmp-student-results-wrap">
			<section class="cmp-panel">
				<div class="cmp-panel-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
					<div class="cmp-master-bulk-group" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
						<h2 style="margin:0 8px 0 0;"><?php esc_html_e( 'Students List', 'class-manager-pro' ); ?></h2>
						<?php wp_nonce_field( 'cmp_admin_nonce', 'cmp_admin_ajax_nonce' ); ?>
						<select id="cmp-student-bulk-action">
							<option value=""><?php esc_html_e( 'Bulk action', 'class-manager-pro' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'class-manager-pro' ); ?></option>
							<option value="change_status"><?php esc_html_e( 'Change status', 'class-manager-pro' ); ?></option>
							<option value="move_to_batch"><?php esc_html_e( 'Move batch', 'class-manager-pro' ); ?></option>
							<option value="send_mail"><?php esc_html_e( 'Send Mail', 'class-manager-pro' ); ?></option>
							<option value="send_whatsapp"><?php esc_html_e( 'WhatsApp API', 'class-manager-pro' ); ?></option>
							<option value="clear_payment"><?php esc_html_e( 'Clear Payment', 'class-manager-pro' ); ?></option>
							<option value="export"><?php esc_html_e( 'Export', 'class-manager-pro' ); ?></option>
						</select>
						<select id="cmp-student-bulk-status" style="display: none;">
							<option value=""><?php esc_html_e( 'Choose status', 'class-manager-pro' ); ?></option>
							<?php foreach ( cmp_student_statuses() as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<select id="cmp-student-bulk-class" data-cmp-class-select style="display: none;">
							<option value="0"><?php esc_html_e( 'Choose class', 'class-manager-pro' ); ?></option>
							<?php foreach ( $classes as $class ) : ?>
								<option value="<?php echo esc_attr( (int) $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<select id="cmp-student-bulk-batch" data-cmp-batches style="display: none;">
							<option value="0"><?php esc_html_e( 'Choose batch', 'class-manager-pro' ); ?></option>
							<?php foreach ( $batches as $batch ) : ?>
								<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>"><?php echo esc_html( $batch->batch_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button button-secondary" id="cmp-student-bulk-apply"><?php esc_html_e( 'Apply', 'class-manager-pro' ); ?></button>
						<span class="cmp-muted" id="cmp-student-bulk-feedback"></span>
					</div>

					<div class="cmp-master-action-links" style="display:flex; align-items:center; gap:8px;">
						<a class="button cmp-export-link" data-base-url="<?php echo esc_url( $export_url ); ?>" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export', 'class-manager-pro' ); ?></a>
						<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-razorpay-import' ) . '#cmp-student-file-import' ); ?>"><?php esc_html_e( 'Import', 'class-manager-pro' ); ?></a>
						<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-students' ) ); ?>#cmp-add-student">+ <?php esc_html_e( 'Student', 'class-manager-pro' ); ?></a>
					</div>
				</div>

				<div class="cmp-students-master-bar" style="margin-bottom:14px; border:none; padding:0; background:transparent;">
					<form method="get" class="cmp-filter-form cmp-master-filter-form" data-cmp-action="cmp_filter_students" data-cmp-target="#cmp-student-results-wrap" style="width:100%;">
						<input type="hidden" name="page" value="cmp-students">
						<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
						<?php if ( 'pending' === $current_tab ) : ?>
							<input type="hidden" name="fee_status" value="pending">
						<?php endif; ?>

						<div class="cmp-master-filter-group" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; width:100%;">
							<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search student, phone, email, ID...', 'class-manager-pro' ); ?>" class="cmp-master-search-input" style="flex:1; min-width:200px;">
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
						</div>
					</form>
				</div>
				<div class="cmp-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 32px;"><input type="checkbox" id="cmp-student-select-all" data-cmp-select-all=".cmp-student-select"></th>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Contact & City', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Enrolled Classes & Batches', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Fee & Balance', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Admission Date & Source', 'class-manager-pro' ); ?></th>
								<th style="min-width: 220px;"><?php esc_html_e( 'Quick Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody class="cmp-student-results">
							<?php echo cmp_render_student_rows( $row_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</tbody>
					</table>
				</div>
				<?php cmp_render_pagination( $pagination, $pagination_filters ); ?>
			</section>
		</div>

		<?php if ( ! $student && 'view' !== $action ) : ?>
			<section class="cmp-panel" id="cmp-add-student">
				<h2><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></h2>
				<?php cmp_render_student_form( null, 'cmp-students' ); ?>
			</section>
		<?php endif; ?>
	</div>
	<?php
}
