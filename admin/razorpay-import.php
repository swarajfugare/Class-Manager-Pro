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
 * Renders the manual Razorpay page import workspace.
 */
function cmp_render_razorpay_import_page() {
	cmp_require_manage_options();

	$credentials = cmp_get_razorpay_credentials();
	$has_keys    = '' !== $credentials['key_id'] && '' !== $credentials['secret'];
	$page_id     = sanitize_text_field( cmp_field( $_GET, 'razorpay_page_id' ) );
	$links       = $has_keys ? cmp_get_razorpay_payment_links_for_admin() : array();
	$link        = null;
	$payments    = array();
	$classes     = cmp_get_classes();
	$batches     = cmp_get_batches();
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Razorpay Import', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Choose a Razorpay payment page, review only successful captured payments, then add those students into the class and batch you choose. Duplicate student names, emails, or phones in the selected batch are merged into one student.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<?php if ( ! $has_keys ) : ?>
			<section class="cmp-panel">
				<div class="cmp-callout cmp-callout-warning">
					<p><?php esc_html_e( 'Razorpay API keys are not configured. Add keys in Settings first, or import keys from an existing WordPress Razorpay integration.', 'class-manager-pro' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'class-manager-pro' ); ?></a>
				</div>
			</section>
		<?php elseif ( is_wp_error( $links ) ) : ?>
			<section class="cmp-panel">
				<div class="cmp-callout cmp-callout-warning">
					<p><?php echo esc_html( $links->get_error_message() ); ?></p>
				</div>
			</section>
		<?php else : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Choose Razorpay Page', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'These are payment links/pages from your Razorpay dashboard.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<form method="get" class="cmp-toolbar">
					<input type="hidden" name="page" value="cmp-razorpay-import">
					<select name="razorpay_page_id" required>
						<option value=""><?php esc_html_e( 'Select Razorpay page', 'class-manager-pro' ); ?></option>
						<?php foreach ( $links as $candidate ) : ?>
							<?php
							$candidate_id    = isset( $candidate['id'] ) ? sanitize_text_field( $candidate['id'] ) : '';
							$candidate_title = cmp_razorpay_entity_title( $candidate );
							$candidate_label = sprintf(
								'%1$s (%2$s)',
								$candidate_title,
								$candidate_id
							);
							?>
							<option value="<?php echo esc_attr( $candidate_id ); ?>" <?php selected( $page_id, $candidate_id ); ?>><?php echo esc_html( $candidate_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Load Page Data', 'class-manager-pro' ); ?></button>
				</form>
			</section>

			<?php if ( '' !== $page_id ) : ?>
				<?php
				$link     = cmp_get_razorpay_payment_link( $page_id );
				$payments = is_wp_error( $link ) ? $link : cmp_get_successful_razorpay_payments_for_link( $page_id );
				?>

				<?php if ( is_wp_error( $link ) ) : ?>
					<section class="cmp-panel">
						<div class="cmp-callout cmp-callout-warning">
							<p><?php echo esc_html( $link->get_error_message() ); ?></p>
						</div>
					</section>
				<?php elseif ( is_wp_error( $payments ) ) : ?>
					<section class="cmp-panel">
						<div class="cmp-callout cmp-callout-warning">
							<p><?php echo esc_html( $payments->get_error_message() ); ?></p>
						</div>
					</section>
				<?php else : ?>
					<?php $unique_students = cmp_count_unique_razorpay_students( $payments ); ?>
					<section class="cmp-panel">
						<div class="cmp-panel-header">
							<div>
								<h2><?php echo esc_html( cmp_razorpay_entity_title( $link ) ); ?></h2>
								<p class="cmp-muted"><?php echo esc_html( sprintf( __( '%1$d successful captured payments found for %2$d unique paid students. Failed, authorized, and cancelled payments are not shown.', 'class-manager-pro' ), count( $payments ), $unique_students ) ); ?></p>
							</div>
							<?php if ( ! empty( $link['short_url'] ) ) : ?>
								<a class="button" href="<?php echo esc_url( $link['short_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Razorpay Page', 'class-manager-pro' ); ?></a>
							<?php endif; ?>
						</div>

						<div class="cmp-table-scroll">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Payment ID', 'class-manager-pro' ); ?></th>
										<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $payments ) ) : ?>
										<tr><td colspan="6"><?php esc_html_e( 'No successful paid students were found for this Razorpay page.', 'class-manager-pro' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $payments as $payment ) : ?>
											<?php $preview = cmp_payment_student_preview( $payment ); ?>
											<tr>
												<td><?php echo esc_html( $preview['name'] ); ?></td>
												<td><?php echo esc_html( $preview['phone'] ); ?></td>
												<td><?php echo esc_html( $preview['email'] ); ?></td>
												<td><?php echo esc_html( cmp_format_money( $preview['amount'] ) ); ?></td>
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
							<h2><?php esc_html_e( 'Add Loaded Students to Batch', 'class-manager-pro' ); ?></h2>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
								<input type="hidden" name="action" value="cmp_import_razorpay_page_to_batch">
								<input type="hidden" name="razorpay_page_id" value="<?php echo esc_attr( $page_id ); ?>">
								<?php wp_nonce_field( 'cmp_import_razorpay_page_to_batch' ); ?>

								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="cmp-import-class"><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></label></th>
										<td>
											<select id="cmp-import-class" name="class_id" data-cmp-class-select required>
												<option value="0"><?php esc_html_e( 'Choose class', 'class-manager-pro' ); ?></option>
												<?php foreach ( $classes as $class ) : ?>
													<option value="<?php echo esc_attr( (int) $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
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
													<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>"><?php echo esc_html( $batch->batch_name ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</table>

								<?php submit_button( __( 'Add All Loaded Students to This Batch', 'class-manager-pro' ), 'primary' ); ?>
							</form>
						</section>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
