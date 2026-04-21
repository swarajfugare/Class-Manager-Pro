<?php
/**
 * Payments admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a payment form.
 *
 * @param int $selected_student_id Selected student ID.
 */
function cmp_render_payment_form( $selected_student_id = 0 ) {
	$students = cmp_get_students();
	$now      = current_time( 'timestamp' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" id="cmp-add-payment">
		<input type="hidden" name="action" value="cmp_save_payment">
		<?php wp_nonce_field( 'cmp_save_payment' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmp-payment-student"><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-payment-student" name="student_id" required>
						<option value=""><?php esc_html_e( 'Select student', 'class-manager-pro' ); ?></option>
						<?php foreach ( $students as $student ) : ?>
							<option value="<?php echo esc_attr( (int) $student->id ); ?>" <?php selected( $selected_student_id, (int) $student->id ); ?>>
								<?php echo esc_html( $student->unique_id . ' - ' . $student->name . ' (' . $student->phone . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-amount"><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></label></th>
				<td><input type="number" id="cmp-payment-amount" name="amount" min="0" step="0.01" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-mode"><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-payment-mode" name="payment_mode">
						<?php foreach ( cmp_payment_modes() as $mode ) : ?>
							<option value="<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( ucfirst( $mode ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-transaction"><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-payment-transaction" name="transaction_id" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-payment-date"><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></label></th>
				<td><input type="datetime-local" id="cmp-payment-date" name="payment_date" value="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i', $now ) ); ?>"></td>
			</tr>
		</table>

		<?php submit_button( __( 'Add Payment', 'class-manager-pro' ) ); ?>
	</form>
	<?php
}

/**
 * Renders the payments page.
 */
function cmp_render_payments_page() {
	cmp_require_manage_options();

	$selected_student_id = absint( cmp_field( $_GET, 'student_id', 0 ) );
	$payment_mode        = sanitize_key( cmp_field( $_GET, 'payment_mode' ) );
	$payments            = cmp_get_payments( array( 'payment_mode' => $payment_mode ) );
	$export_url          = wp_nonce_url(
		add_query_arg(
			array(
				'page'         => 'cmp-payments',
				'cmp_export'   => 'payments',
				'payment_mode' => $payment_mode,
			),
			admin_url( 'admin.php' )
		),
		'cmp_export_payments'
	);
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Payments', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Only completed payments are saved here. Failed or authorized-only Razorpay payments are ignored during webhook sync and historical imports.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Add Manual Payment', 'class-manager-pro' ); ?></h2>
			<?php cmp_render_payment_form( $selected_student_id ); ?>
		</section>

		<form method="get" class="cmp-toolbar">
			<input type="hidden" name="page" value="cmp-payments">
			<select name="payment_mode">
				<option value=""><?php esc_html_e( 'All modes', 'class-manager-pro' ); ?></option>
				<?php foreach ( cmp_payment_modes() as $mode ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $payment_mode, $mode ); ?>><?php echo esc_html( ucfirst( $mode ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
		</form>

		<section class="cmp-panel">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $payments ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No payments found.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $payments as $payment ) : ?>
							<tr>
								<td><?php echo esc_html( $payment->student_name ); ?><br><span class="cmp-muted"><?php echo esc_html( $payment->student_unique_id ); ?></span></td>
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
	</div>
	<?php
}
