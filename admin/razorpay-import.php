<?php
/**
 * Razorpay import workspace.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Razorpay Payment Page import workspace.
 */
function cmp_render_razorpay_import_page() {
	cmp_require_manage_options();

	$credentials      = cmp_get_razorpay_credentials();
	$has_keys         = '' !== $credentials['key_id'] && '' !== $credentials['secret'];
	$selected_id      = sanitize_text_field( cmp_field( $_GET, 'razorpay_page_id' ) );
	$manual_id        = sanitize_text_field( cmp_field( $_GET, 'manual_razorpay_page_id' ) );
	$selected_batch   = cmp_get_batch( absint( cmp_field( $_GET, 'batch_id', 0 ) ) );
	$source_batch_id  = absint( cmp_field( $_GET, 'source_batch_id', 0 ) );
	$source_batch     = $source_batch_id ? cmp_get_batch( $source_batch_id ) : null;
	$classes          = cmp_get_classes();
	$batches          = cmp_get_batches();
	$stored_batches   = array();

	foreach ( $batches as $batch ) {
		if ( ! empty( $batch->razorpay_page_id ) ) {
			$stored_batches[] = $batch;
		}
	}

	if ( $source_batch && empty( $source_batch->razorpay_page_id ) ) {
		$source_batch = null;
	}

	$page_id = $manual_id;

	if ( '' === $page_id ) {
		$page_id = $selected_id;
	}

	if ( '' === $page_id && $selected_batch && ! empty( $selected_batch->razorpay_page_id ) ) {
		$page_id = sanitize_text_field( $selected_batch->razorpay_page_id );
	}

	if ( '' === $page_id && $source_batch && ! empty( $source_batch->razorpay_page_id ) ) {
		$page_id = sanitize_text_field( $source_batch->razorpay_page_id );
	}

	$class_id       = absint( cmp_field( $_GET, 'class_id', $selected_batch ? (int) $selected_batch->class_id : ( $source_batch ? (int) $source_batch->class_id : 0 ) ) );
	$batch_id       = $selected_batch ? (int) $selected_batch->id : absint( cmp_field( $_GET, 'batch_id', $source_batch ? (int) $source_batch->id : 0 ) );
	$load_attempted = '' !== $manual_id || '' !== $selected_id || $source_batch_id > 0;
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Import from Razorpay', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Enter a Razorpay Payment Link ID, Payment Page ID, or page URL, review the captured payments, and import students into the correct class and batch.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<?php if ( $selected_batch ) : ?>
			<section class="cmp-panel">
				<div class="cmp-callout cmp-callout-info">
					<p><?php echo esc_html( sprintf( __( 'Importing into %1$s / %2$s.', 'class-manager-pro' ), $selected_batch->class_name, $selected_batch->batch_name ) ); ?></p>
					<p><a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $selected_batch->id ) ) ); ?>"><?php esc_html_e( 'Back to Batch', 'class-manager-pro' ); ?></a></p>
				</div>
			</section>
		<?php endif; ?>

		<section class="cmp-panel" id="cmp-student-file-import">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Import Students (CSV / Excel)', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Upload a Razorpay CSV or spreadsheet export, choose the class and batch, then import students safely in bulk. Captured payments assign the selected batch, add the 2.36% Razorpay charge for reporting, and failed payments go to Interested Students instead of the main student list.', 'class-manager-pro' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $classes ) || empty( $batches ) ) : ?>
				<div class="cmp-callout cmp-callout-warning">
					<p><?php esc_html_e( 'Create at least one class and batch before importing students.', 'class-manager-pro' ); ?></p>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" enctype="multipart/form-data" data-cmp-ajax-import="student-file" data-cmp-feedback="#cmp-student-file-import-feedback">
					<input type="hidden" name="action" value="cmp_import_students_file">
					<?php wp_nonce_field( 'cmp_import_students_file' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-student-import-file"><?php esc_html_e( 'File', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="file" id="cmp-student-import-file" name="import_file" accept=".csv,.xls,.xlsx" required>
								<p class="description"><?php esc_html_e( 'Allowed formats: .csv, .xls, .xlsx', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-file-import-class"><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-file-import-class" name="class_id" data-cmp-class-select required>
									<option value="0"><?php esc_html_e( 'Choose class', 'class-manager-pro' ); ?></option>
									<?php foreach ( $classes as $class ) : ?>
										<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $class_id, (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-file-import-batch"><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-file-import-batch" name="batch_id" data-cmp-batches required>
									<option value="0"><?php esc_html_e( 'Choose batch', 'class-manager-pro' ); ?></option>
									<?php foreach ( $batches as $batch ) : ?>
										<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $batch_id, (int) $batch->id ); ?>><?php echo esc_html( sprintf( '%1$s / %2$s', $batch->class_name, $batch->batch_name ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Captured rows are assigned to this batch. Failed-payment rows are saved to Interested Students for follow-up without creating student enrollments.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
					</table>

					<p class="cmp-muted" id="cmp-student-file-import-feedback"><?php esc_html_e( 'Import students without reloading this page.', 'class-manager-pro' ); ?></p>
					<?php submit_button( __( 'Import Students', 'class-manager-pro' ) ); ?>
				</form>
			<?php endif; ?>
		</section>

		<?php if ( ! $has_keys ) : ?>
			<section class="cmp-panel">
				<div class="cmp-callout cmp-callout-warning">
					<p><?php esc_html_e( 'Razorpay API keys are not configured. Add keys in Settings first.', 'class-manager-pro' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'class-manager-pro' ); ?></a></p>
				</div>
			</section>
		<?php else : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Enter Payment Link / Page Reference', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Paste a Razorpay Payment Link ID, Payment Page ID, short URL, or full page URL. You can also load the saved reference from a batch.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<form method="get" class="cmp-toolbar">
					<input type="hidden" name="page" value="cmp-razorpay-import">
					<?php if ( $class_id ) : ?>
						<input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>">
					<?php endif; ?>
					<?php if ( $batch_id ) : ?>
						<input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch_id ); ?>">
					<?php endif; ?>
					<select name="source_batch_id">
						<option value="0"><?php esc_html_e( 'Use saved batch page ID (optional)', 'class-manager-pro' ); ?></option>
						<?php foreach ( $stored_batches as $stored_batch ) : ?>
							<option value="<?php echo esc_attr( (int) $stored_batch->id ); ?>" <?php selected( $source_batch_id, (int) $stored_batch->id ); ?>>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: class name 2: batch name 3: payment page id */
										__( '%1$s / %2$s (%3$s)', 'class-manager-pro' ),
										$stored_batch->class_name,
										$stored_batch->batch_name,
										$stored_batch->razorpay_page_id
									)
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="manual_razorpay_page_id" class="regular-text" value="<?php echo esc_attr( $manual_id ? $manual_id : $page_id ); ?>" placeholder="<?php esc_attr_e( 'Enter Razorpay page ID, link ID, or URL', 'class-manager-pro' ); ?>">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Load Payments', 'class-manager-pro' ); ?></button>
				</form>
			</section>

			<?php if ( $load_attempted && '' === $page_id ) : ?>
				<section class="cmp-panel">
					<div class="cmp-callout cmp-callout-warning">
						<p><?php esc_html_e( 'Enter a Razorpay Payment Link/Page reference or choose a batch with a saved reference.', 'class-manager-pro' ); ?></p>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( '' !== $page_id ) : ?>
				<?php
				$page_name = $selected_batch ? cmp_clean_title_text( $selected_batch->batch_name ) : ( $source_batch ? cmp_clean_title_text( $source_batch->batch_name ) : '' );
				$payments = cmp_get_successful_razorpay_payments_for_link( $page_id, $page_name );
				?>

				<?php if ( is_wp_error( $payments ) ) : ?>
					<section class="cmp-panel">
						<div class="cmp-callout cmp-callout-warning">
							<p><?php echo esc_html( $payments->get_error_message() ); ?></p>
						</div>
					</section>
				<?php else : ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php echo esc_html( $page_id ); ?></h2>
								<p class="cmp-muted"><?php echo esc_html( sprintf( __( '%1$d successful captured payments found. Duplicate payment IDs are skipped automatically on import, and Razorpay imports add the 2.36%% gateway charge to reporting totals.', 'class-manager-pro' ), count( $payments ) ) ); ?></p>
							</div>
						</div>

						<div class="cmp-table-scroll">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Original', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Charge', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Final', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Payment ID', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $payments ) ) : ?>
										<tr><td colspan="8"><?php esc_html_e( 'No captured payments were found for this Razorpay reference.', 'class-manager-pro' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $payments as $payment ) : ?>
											<?php $preview = cmp_payment_student_preview( $payment ); ?>
											<tr>
												<td><?php echo esc_html( $preview['name'] ); ?></td>
												<td><?php echo esc_html( $preview['phone'] ); ?></td>
												<td><?php echo esc_html( $preview['email'] ); ?></td>
												<td><?php echo esc_html( cmp_format_money( $preview['original_amount'] ) ); ?></td>
												<td><?php echo esc_html( cmp_format_money( $preview['charge_amount'] ) ); ?></td>
												<td><?php echo esc_html( cmp_format_money( $preview['final_amount'] ) ); ?></td>
												<td><?php echo esc_html( $preview['id'] ); ?></td>
												<td><?php echo esc_html( $preview['date'] ); ?></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</section>

					<?php if ( ! empty( $payments ) ) : ?>
						<section class="cmp-panel">
							<div class="cmp-panel-header">
								<div>
									<h2><?php esc_html_e( 'Import Students', 'class-manager-pro' ); ?></h2>
									<p class="cmp-muted"><?php esc_html_e( 'Every successful payment is matched to the selected batch. Existing students and existing payment IDs are not duplicated, and the Razorpay charge is stored separately from the student fee amount.', 'class-manager-pro' ); ?></p>
								</div>
							</div>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-import="razorpay-page" data-cmp-feedback="#cmp-razorpay-page-import-feedback">
								<input type="hidden" name="action" value="cmp_import_razorpay_page_to_batch">
								<input type="hidden" name="razorpay_page_id" value="<?php echo esc_attr( $page_id ); ?>">
								<input type="hidden" name="return_page" value="<?php echo esc_attr( $selected_batch ? 'cmp-batches' : 'cmp-razorpay-import' ); ?>">
								<?php if ( $selected_batch ) : ?>
									<input type="hidden" name="return_action" value="view">
								<?php endif; ?>
								<?php wp_nonce_field( 'cmp_import_razorpay_page_to_batch' ); ?>

								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="cmp-import-class"><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></label></th>
										<td>
											<select id="cmp-import-class" name="class_id" data-cmp-class-select required>
												<option value="0"><?php esc_html_e( 'Choose class', 'class-manager-pro' ); ?></option>
												<?php foreach ( $classes as $class ) : ?>
													<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $class_id, (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="cmp-import-batch"><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></label></th>
										<td>
											<select id="cmp-import-batch" name="batch_id" data-cmp-batches required>
												<option value="0"><?php esc_html_e( 'Choose batch', 'class-manager-pro' ); ?></option>
												<?php foreach ( $batches as $batch ) : ?>
													<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $batch_id, (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</table>

								<p class="cmp-muted" id="cmp-razorpay-page-import-feedback"><?php esc_html_e( 'Import the selected payments without leaving this screen.', 'class-manager-pro' ); ?></p>
								<?php submit_button( __( 'Import Students', 'class-manager-pro' ), 'primary' ); ?>
							</form>
						</section>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
