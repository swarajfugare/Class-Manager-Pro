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
 * Stores a public duplicate warning for a batch token.
 *
 * @param string $token Batch token.
 * @param string $message Message.
 * @return void
 */
function cmp_store_public_duplicate_warning( $token, $message ) {
	$token = sanitize_text_field( $token );

	if ( '' === $token || '' === trim( (string) $message ) ) {
		return;
	}

	set_transient( 'cmp_public_duplicate_warning_' . md5( $token ), sanitize_text_field( $message ), MINUTE_IN_SECONDS * 5 );
}

/**
 * Pops a public duplicate warning for a batch token.
 *
 * @param string $token Batch token.
 * @return string
 */
function cmp_pop_public_duplicate_warning( $token ) {
	$token   = sanitize_text_field( $token );
	$key     = 'cmp_public_duplicate_warning_' . md5( $token );
	$message = (string) get_transient( $key );

	if ( '' !== $message ) {
		delete_transient( $key );
	}

	return sanitize_text_field( $message );
}

/**
 * Stores a successful public registration payload for a short time.
 *
 * @param string $token Access token.
 * @param array  $data Success payload.
 * @return void
 */
function cmp_store_public_registration_success( $token, $data ) {
	$token = sanitize_text_field( $token );

	if ( '' === $token || empty( $data ) || ! is_array( $data ) ) {
		return;
	}

	set_transient( 'cmp_public_success_' . md5( $token ), $data, MINUTE_IN_SECONDS * 15 );
}

/**
 * Pops the last public registration success payload for a token.
 *
 * @param string $token Access token.
 * @return array
 */
function cmp_pop_public_registration_success( $token ) {
	$token = sanitize_text_field( $token );

	if ( '' === $token ) {
		return array();
	}

	$key  = 'cmp_public_success_' . md5( $token );
	$data = get_transient( $key );

	if ( is_array( $data ) ) {
		delete_transient( $key );

		return $data;
	}

	return array();
}

/**
 * Returns the frontend URL for a public batch access token.
 *
 * @param string $access_type Access type.
 * @param string $token Token value.
 * @return string
 */
function cmp_get_public_batch_access_url( $access_type, $token ) {
	$token       = sanitize_text_field( $token );
	$access_type  = sanitize_key( $access_type );
	$query_key    = 'permanent' === $access_type ? 'cmp_batch_form' : 'token';

	return add_query_arg(
		array(
			$query_key => $token,
		),
		home_url( '/register/' )
	);
}

/**
 * Renders the public admission progress indicator.
 *
 * @param string $status Current status.
 * @return void
 */
function cmp_render_public_registration_steps( $status = '' ) {
	$steps = array(
		array( 'key' => 'link', 'label' => __( 'Link Opened', 'class-manager-pro' ) ),
		array( 'key' => 'validation', 'label' => __( 'Validation', 'class-manager-pro' ) ),
		array( 'key' => 'registration', 'label' => __( 'Registration', 'class-manager-pro' ) ),
		array( 'key' => 'confirmation', 'label' => __( 'Confirmation', 'class-manager-pro' ) ),
		array( 'key' => 'student_added', 'label' => __( 'Student Added', 'class-manager-pro' ) ),
	);
	$active_index = 'success' === sanitize_key( $status ) ? 4 : 2;
	?>
	<nav class="cmp-public-steps" aria-label="<?php esc_attr_e( 'Registration progress', 'class-manager-pro' ); ?>">
		<?php foreach ( $steps as $index => $step ) : ?>
			<div class="cmp-public-step <?php echo esc_attr( $index < $active_index ? 'is-complete' : ( $index === $active_index ? 'is-active' : '' ) ); ?>">
				<span class="cmp-public-step-index"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
				<strong><?php echo esc_html( $step['label'] ); ?></strong>
			</div>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Renders the public batch form page when a valid token is present.
 */
function cmp_maybe_render_public_batch_page() {
	$temporary_token = sanitize_text_field( cmp_field( $_GET, 'token' ) );
	$legacy_token    = sanitize_text_field( cmp_field( $_GET, 'cmp_batch_form' ) );
	$status          = sanitize_key( cmp_field( $_GET, 'cmp_public_status' ) );
	$is_success      = 'success' === $status;
	$access_type     = '';
	$access_token    = '';
	$batch           = null;
	$token_row       = null;

	if ( is_admin() || ( '' === $legacy_token && '' === $temporary_token ) ) {
		return;
	}

	if ( '' !== $temporary_token ) {
		$access_type  = 'temp';
		$access_token = $temporary_token;
		$token_row    = cmp_get_batch_registration_token_record( $temporary_token, true, true );
		$batch        = $token_row;
	} elseif ( '' !== $legacy_token ) {
		$access_type  = 'permanent';
		$access_token = $legacy_token;
		$batch        = cmp_get_batch_by_token( $legacy_token );
	}

	if ( ! $batch ) {
		status_header( 404 );
		nocache_headers();
		cmp_render_public_batch_shell(
			__( 'Form Not Found', 'class-manager-pro' ),
			'<div class="cmp-public-page"><section class="cmp-public-panel"><h1>' . esc_html__( 'Form Not Found', 'class-manager-pro' ) . '</h1><p>' . esc_html__( 'This student form link is invalid or expired.', 'class-manager-pro' ) . '</p></section></div>'
		);
		exit;
	}

	if ( 'permanent' === $access_type && ( empty( $batch->is_free ) || empty( $batch->permanent_registration_enabled ) ) ) {
		status_header( 404 );
		nocache_headers();
		cmp_render_public_batch_shell(
			__( 'Form Not Found', 'class-manager-pro' ),
			'<div class="cmp-public-page"><section class="cmp-public-panel"><h1>' . esc_html__( 'Form Not Found', 'class-manager-pro' ) . '</h1><p>' . esc_html__( 'This permanent registration link is not enabled for the selected batch.', 'class-manager-pro' ) . '</p></section></div>'
		);
		exit;
	}

	$error       = sanitize_text_field( cmp_field( $_GET, 'cmp_public_error' ) );
	$warning     = cmp_pop_public_duplicate_warning( $access_token );
	$success     = cmp_pop_public_registration_success( $access_token );
	$batch_fee   = cmp_get_batch_effective_fee( $batch );
	$content     = '';
	$form_notice = '';

	if ( 'success' === $status ) {
		$success_name  = ! empty( $success['student_name'] ) ? $success['student_name'] : '';
		$success_phone = ! empty( $success['phone'] ) ? $success['phone'] : '';
		$success_email = ! empty( $success['email'] ) ? $success['email'] : '';
		$course_title  = ! empty( $batch->course_id ) ? cmp_get_tutor_course_title( (int) $batch->course_id ) : __( 'Not linked', 'class-manager-pro' );
		$teacher_label = ! empty( $batch->teacher_user_id ) ? cmp_get_teacher_label( (int) $batch->teacher_user_id ) : __( 'Not assigned yet', 'class-manager-pro' );
		$support_email = sanitize_email( (string) get_option( 'admin_email', get_bloginfo( 'admin_email' ) ) );

		ob_start();
		?>
		<div class="cmp-public-success">
			<div class="cmp-public-success-badge"><?php echo esc_html__( 'Registration Successful', 'class-manager-pro' ); ?></div>
			<h2><?php echo esc_html( $batch->batch_name ); ?></h2>
			<p class="cmp-public-success-intro"><?php esc_html_e( 'Your registration has been saved. Please keep the details below for your records and next steps.', 'class-manager-pro' ); ?></p>
			<div class="cmp-public-success-grid">
				<div>
					<span><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $batch->batch_name ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Student Name', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $success_name ? $success_name : __( 'Saved successfully', 'class-manager-pro' ) ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Course Name', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $course_title ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $teacher_label ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Contact Details', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( trim( $success_phone . ( $success_email ? ' | ' . $success_email : '' ) ) ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Support Contact', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $support_email ? $support_email : get_bloginfo( 'name' ) ); ?></strong>
				</div>
				<div class="cmp-public-success-grid-span">
					<span><?php esc_html_e( 'Next Steps', 'class-manager-pro' ); ?></span>
					<strong><?php esc_html_e( 'Wait for admin follow-up, payment instructions, or the batch welcome message.', 'class-manager-pro' ); ?></strong>
				</div>
			</div>
		</div>
		<?php
		$form_notice .= ob_get_clean();
	} elseif ( '' !== $error ) {
		$form_notice .= '<div class="cmp-public-alert cmp-public-alert-error"><p>' . esc_html( $error ) . '</p></div>';
	}

	if ( '' !== $warning ) {
		$form_notice .= '<div class="cmp-public-alert cmp-public-alert-error"><p>' . esc_html( $warning ) . '</p></div>';
	}

	if ( 'temp' === $access_type && $token_row ) {
		$link_status = cmp_get_batch_registration_link_status( $token_row );
		$form_notice .= '<div class="cmp-public-alert cmp-public-alert-success"><p>';
		$form_notice .= esc_html( $link_status['description'] );
		$form_notice .= '</p></div>';
	}

	ob_start();
	?>
	<div class="cmp-public-page">
		<?php cmp_render_public_registration_steps( $status ); ?>
		<section class="cmp-public-hero">
			<div>
			<p class="cmp-public-eyebrow"><?php echo esc_html( $batch->class_name ); ?></p>
			<h1><?php echo esc_html( $batch->batch_name ); ?></h1>
			<p class="cmp-public-subtitle">
				<?php
				if ( 'permanent' === $access_type ) {
					esc_html_e( 'This free batch has a permanent registration link enabled. Use it any time until the admin disables it.', 'class-manager-pro' );
				} else {
					esc_html_e( 'Fill your details once. The batch and class are already linked for you.', 'class-manager-pro' );
				}
				?>
			</p>
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

			<?php if ( 'success' === $status ) : ?>
				<div class="cmp-public-success-actions">
					<?php if ( ! empty( $batch->is_free ) ) : ?>
						<div class="cmp-public-payment">
							<h2><?php esc_html_e( 'All Set', 'class-manager-pro' ); ?></h2>
							<p><?php esc_html_e( 'This is a free batch, so no payment is required. Our team may still contact you with next-step details.', 'class-manager-pro' ); ?></p>
						</div>
					<?php elseif ( ! empty( $batch->razorpay_link ) ) : ?>
						<div class="cmp-public-payment">
							<h2><?php esc_html_e( 'Next Step: Payment', 'class-manager-pro' ); ?></h2>
							<p><?php esc_html_e( 'If payment is pending, use the payment button below to complete your batch payment in Razorpay.', 'class-manager-pro' ); ?></p>
							<a class="cmp-public-button cmp-public-button-secondary" href="<?php echo esc_url( $batch->razorpay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Continue to Razorpay', 'class-manager-pro' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			<?php elseif ( 'active' !== $batch->status ) : ?>
				<div class="cmp-public-alert cmp-public-alert-error">
					<p><?php esc_html_e( 'This batch is not accepting new submissions right now.', 'class-manager-pro' ); ?></p>
				</div>
		<?php elseif ( 'temp' === $access_type && $token_row && in_array( cmp_get_batch_registration_link_status( $token_row )['key'], array( 'used_successfully', 'expired', 'closed_by_admin' ), true ) ) : ?>
			<div class="cmp-public-alert cmp-public-alert-error">
				<p><?php esc_html_e( 'This temporary registration link is no longer active.', 'class-manager-pro' ); ?></p>
			</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-public-form">
					<input type="hidden" name="action" value="cmp_submit_public_batch_form">
					<input type="hidden" name="access_type" value="<?php echo esc_attr( $access_type ); ?>">
					<input type="hidden" name="access_token" value="<?php echo esc_attr( $access_token ); ?>">
					<input type="hidden" name="notice_token" value="<?php echo esc_attr( $access_token ); ?>">
					<?php wp_nonce_field( 'cmp_public_batch_form_' . $access_token ); ?>

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
							<input type="email" name="email" required>
							<small><?php esc_html_e( 'A valid email address is required to complete registration.', 'class-manager-pro' ); ?></small>
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

					<button type="submit" class="cmp-public-button"><?php esc_html_e( 'Submit Registration', 'class-manager-pro' ); ?></button>
				</form>
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
	$access_type  = sanitize_key( cmp_field( $_POST, 'access_type', 'temp' ) );
	$access_token = sanitize_text_field( cmp_field( $_POST, 'access_token' ) );
	$token_row    = 'temp' === $access_type ? cmp_get_batch_registration_token_record( $access_token ) : null;
	$batch        = $token_row;

	if ( 'permanent' === $access_type ) {
		$batch = cmp_get_batch_by_token( $access_token );
	}

	if ( ! $batch ) {
		wp_die( esc_html__( 'This registration link is invalid or expired.', 'class-manager-pro' ) );
	}

	if ( 'permanent' === $access_type && ( empty( $batch->is_free ) || empty( $batch->permanent_registration_enabled ) ) ) {
		wp_die( esc_html__( 'This permanent registration link is not enabled for the selected batch.', 'class-manager-pro' ) );
	}

	check_admin_referer( 'cmp_public_batch_form_' . $access_token );

	if ( 'active' !== $batch->status ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'cmp_public_error' => __( 'This batch is not accepting new submissions.', 'class-manager-pro' ),
				),
				cmp_get_public_batch_access_url( $access_type, $access_token )
			)
		);
		exit;
	}

	if ( 'temp' === $access_type ) {
		$token_status = $token_row ? cmp_get_batch_registration_link_status( $token_row ) : array( 'key' => 'inactive', 'label' => __( 'Inactive', 'class-manager-pro' ), 'description' => __( 'This temporary registration link is invalid or expired.', 'class-manager-pro' ) );

		if ( in_array( $token_status['key'], array( 'used_successfully', 'expired', 'closed_by_admin', 'inactive' ), true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'cmp_public_error' => ! empty( $token_status['description'] ) ? $token_status['description'] : __( 'This temporary registration link is invalid or expired.', 'class-manager-pro' ),
					),
					cmp_get_public_batch_access_url( $access_type, $access_token )
				)
			);
			exit;
		}
	}

	$result = cmp_register_public_student_for_batch( $batch, $_POST );

	if ( is_wp_error( $result ) ) {
		if ( 'cmp_batch_full' === $result->get_error_code() && 'temp' === $access_type ) {
			cmp_close_batch_registration_link( (int) $batch->id );
		}
		$error_message = 'cmp_batch_full' === $result->get_error_code() ? __( 'Batch Full', 'class-manager-pro' ) : $result->get_error_message();
		wp_safe_redirect(
			add_query_arg(
				array(
					'cmp_public_error' => $error_message,
				),
				cmp_get_public_batch_access_url( $access_type, $access_token )
			)
		);
		exit;
	}

	if ( 'temp' === $access_type ) {
		cmp_mark_batch_registration_token_used( $access_token, (int) $result );
	}

	$student = cmp_get_student( (int) $result );

	cmp_store_public_registration_success(
		$access_token,
		array(
			'student_id'   => (int) $result,
			'student_name' => $student && ! empty( $student->name ) ? $student->name : sanitize_text_field( cmp_field( $_POST, 'name' ) ),
			'phone'        => $student && ! empty( $student->phone ) ? $student->phone : sanitize_text_field( cmp_field( $_POST, 'phone' ) ),
			'email'        => $student && ! empty( $student->email ) ? $student->email : sanitize_email( cmp_field( $_POST, 'email' ) ),
			'batch_name'   => $batch->batch_name,
			'class_name'   => $batch->class_name,
		)
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'cmp_public_status' => 'success',
			),
			cmp_get_public_batch_access_url( $access_type, $access_token )
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
