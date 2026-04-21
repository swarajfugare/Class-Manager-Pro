<?php
/**
 * Public student intake form rendering and submission.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_nopriv_cmp_submit_public_batch_form', 'cmp_handle_public_batch_form_submission' );
add_action( 'admin_post_cmp_submit_public_batch_form', 'cmp_handle_public_batch_form_submission' );

/**
 * Renders the public batch form page when a valid token is present.
 */
function cmp_maybe_render_public_batch_page() {
	$token = sanitize_text_field( cmp_field( $_GET, 'cmp_batch_form' ) );

	if ( '' === $token || is_admin() ) {
		return;
	}

	$batch = cmp_get_batch_by_token( $token );

	if ( ! $batch ) {
		status_header( 404 );
		nocache_headers();
		cmp_render_public_batch_shell(
			__( 'Form Not Found', 'class-manager-pro' ),
			'<div class="cmp-public-page"><section class="cmp-public-panel"><h1>' . esc_html__( 'Form Not Found', 'class-manager-pro' ) . '</h1><p>' . esc_html__( 'This student form link is invalid or expired.', 'class-manager-pro' ) . '</p></section></div>'
		);
		exit;
	}

	$status      = sanitize_key( cmp_field( $_GET, 'cmp_public_status' ) );
	$error       = sanitize_text_field( cmp_field( $_GET, 'cmp_public_error' ) );
	$batch_fee   = cmp_get_batch_effective_fee( $batch );
	$content     = '';
	$form_notice = '';

	if ( 'success' === $status ) {
		$form_notice .= '<div class="cmp-public-alert cmp-public-alert-success">';
		$form_notice .= '<strong>' . esc_html__( 'Details received successfully.', 'class-manager-pro' ) . '</strong>';
		if ( ! empty( $batch->is_free ) ) {
			$form_notice .= '<p>' . esc_html__( 'Your details are saved in this free batch. No payment is required.', 'class-manager-pro' ) . '</p>';
		} else {
			$form_notice .= '<p>' . esc_html__( 'Your details are saved in the batch. Continue to payment if the batch payment link is available below.', 'class-manager-pro' ) . '</p>';
		}
		$form_notice .= '</div>';
	} elseif ( '' !== $error ) {
		$form_notice .= '<div class="cmp-public-alert cmp-public-alert-error"><p>' . esc_html( $error ) . '</p></div>';
	}

	ob_start();
	?>
	<div class="cmp-public-page">
		<section class="cmp-public-hero">
			<div>
				<p class="cmp-public-eyebrow"><?php echo esc_html( $batch->class_name ); ?></p>
				<h1><?php echo esc_html( $batch->batch_name ); ?></h1>
				<p class="cmp-public-subtitle"><?php esc_html_e( 'Fill your details once. The batch and class are already linked for you.', 'class-manager-pro' ); ?></p>
			</div>
			<div class="cmp-public-summary">
				<div><span><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->class_name ); ?></strong></div>
				<div><span><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->batch_name ); ?></strong></div>
				<div><span><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->start_date ? $batch->start_date : __( 'To be announced', 'class-manager-pro' ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Batch Fee', 'class-manager-pro' ); ?></span><strong><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free', 'class-manager-pro' ) : esc_html( cmp_format_money( $batch_fee ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Fee Due Date', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( $batch->fee_due_date ? $batch->fee_due_date : __( 'Shared by the admin later', 'class-manager-pro' ) ); ?></strong></div>
			</div>
		</section>

		<section class="cmp-public-panel">
			<?php echo $form_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php if ( 'active' !== $batch->status ) : ?>
				<div class="cmp-public-alert cmp-public-alert-error">
					<p><?php esc_html_e( 'This batch is not accepting new submissions right now.', 'class-manager-pro' ); ?></p>
				</div>
			<?php elseif ( 'success' !== $status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-public-form">
					<input type="hidden" name="action" value="cmp_submit_public_batch_form">
					<input type="hidden" name="batch_token" value="<?php echo esc_attr( $batch->public_token ); ?>">
					<?php wp_nonce_field( 'cmp_public_batch_form_' . $batch->public_token ); ?>

					<div class="cmp-public-grid">
						<label>
							<span><?php esc_html_e( 'Student Name', 'class-manager-pro' ); ?></span>
							<input type="text" name="name" required>
						</label>
						<label>
							<span><?php esc_html_e( 'Phone Number', 'class-manager-pro' ); ?></span>
							<input type="text" name="phone" required>
						</label>
						<label>
							<span><?php esc_html_e( 'Email Address', 'class-manager-pro' ); ?></span>
							<input type="email" name="email">
						</label>
						<label>
							<span><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></span>
							<input type="text" value="<?php echo esc_attr( $batch->class_name ); ?>" readonly>
						</label>
						<label class="cmp-public-grid-span">
							<span><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></span>
							<textarea name="notes" rows="4" placeholder="<?php esc_attr_e( 'Optional message for the admin', 'class-manager-pro' ); ?>"></textarea>
						</label>
					</div>

					<button type="submit" class="cmp-public-button"><?php esc_html_e( 'Submit Details', 'class-manager-pro' ); ?></button>
				</form>
			<?php endif; ?>

			<?php if ( 'success' === $status && ! empty( $batch->is_free ) ) : ?>
				<div class="cmp-public-payment">
					<h2><?php esc_html_e( 'Registration Complete', 'class-manager-pro' ); ?></h2>
					<p><?php esc_html_e( 'This is a free batch. Your details are saved and no payment is required.', 'class-manager-pro' ); ?></p>
				</div>
			<?php elseif ( 'success' === $status && ! empty( $batch->razorpay_link ) ) : ?>
				<div class="cmp-public-payment">
					<h2><?php esc_html_e( 'Payment', 'class-manager-pro' ); ?></h2>
					<p><?php esc_html_e( 'Use the payment button below to complete your batch payment in Razorpay. Use the same phone number and email there so the payment matches your batch record correctly.', 'class-manager-pro' ); ?></p>
					<a class="cmp-public-button cmp-public-button-secondary" href="<?php echo esc_url( $batch->razorpay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Continue to Razorpay', 'class-manager-pro' ); ?></a>
				</div>
			<?php endif; ?>
		</section>
	</div>
	<?php
	$content = ob_get_clean();

	cmp_render_public_batch_shell( $batch->batch_name, $content );
	exit;
}

/**
 * Handles public batch form submissions.
 */
function cmp_handle_public_batch_form_submission() {
	$token = sanitize_text_field( cmp_field( $_POST, 'batch_token' ) );
	$batch = cmp_get_batch_by_token( $token );

	if ( ! $batch ) {
		wp_die( esc_html__( 'Invalid batch form.', 'class-manager-pro' ) );
	}

	check_admin_referer( 'cmp_public_batch_form_' . $token );

	if ( 'active' !== $batch->status ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'cmp_batch_form'   => $token,
					'cmp_public_error' => __( 'This batch is not accepting new submissions.', 'class-manager-pro' ),
				),
				home_url( '/' )
			)
		);
		exit;
	}

	$result = cmp_register_public_student_for_batch( $batch, $_POST );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'cmp_batch_form'   => $token,
					'cmp_public_error' => $result->get_error_message(),
				),
				home_url( '/' )
			)
		);
		exit;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'cmp_batch_form'    => $token,
				'cmp_public_status' => 'success',
			),
			home_url( '/' )
		)
	);
	exit;
}

/**
 * Outputs the public batch form inside the active theme shell.
 *
 * @param string $title Page title.
 * @param string $content HTML content.
 */
function cmp_render_public_batch_shell( $title, $content ) {
	nocache_headers();
	wp_enqueue_style(
		'cmp-public',
		CMP_PLUGIN_URL . 'assets/css/public.css',
		array(),
		CMP_VERSION
	);

	add_filter(
		'pre_get_document_title',
		function () use ( $title ) {
			return wp_strip_all_tags( $title );
		}
	);

	add_filter(
		'body_class',
		function ( $classes ) {
			$classes[] = 'cmp-public-form-active';

			return $classes;
		}
	);

	get_header();
	?>
	<main class="cmp-public-theme-shell" role="main">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</main>
	<?php
	get_footer();
}
