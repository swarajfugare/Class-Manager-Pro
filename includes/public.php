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
		$success_city  = ! empty( $success['city'] ) ? $success['city'] : '';
		$success_utr   = ! empty( $success['utr_number'] ) ? $success['utr_number'] : '';
		$course_title  = ! empty( $batch->course_id ) ? cmp_get_tutor_course_title( (int) $batch->course_id ) : '';
		$teacher_label = ! empty( $batch->teacher_user_id ) ? cmp_get_teacher_label( (int) $batch->teacher_user_id ) : '';
		$support_phone = get_option( 'cmp_whatsapp_sender', '' );
		$now_formatted = wp_date( 'd M Y, h:i A' );

		ob_start();
		?>
		<div class="cmp-public-receipt-card" id="cmp-receipt-area">
			<div class="cmp-public-receipt-icon">✓</div>
			<h2 class="cmp-public-receipt-title"><?php esc_html_e( 'Registration Successful!', 'class-manager-pro' ); ?></h2>
			<p class="cmp-public-receipt-subtitle"><?php esc_html_e( 'Your admission details have been recorded successfully.', 'class-manager-pro' ); ?></p>

			<div class="cmp-public-receipt-details">
				<div class="cmp-receipt-row">
					<span><?php esc_html_e( 'Class / Course', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $batch->class_name ); ?></strong>
				</div>
				<div class="cmp-receipt-row">
					<span><?php esc_html_e( 'Batch Name', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $batch->batch_name ); ?></strong>
				</div>
				<div class="cmp-receipt-row">
					<span><?php esc_html_e( 'Student Name', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $success_name ? $success_name : __( 'Registered Student', 'class-manager-pro' ) ); ?></strong>
				</div>
				<div class="cmp-receipt-row">
					<span><?php esc_html_e( 'Phone Number', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $success_phone ); ?></strong>
				</div>
				<?php if ( $success_email ) : ?>
					<div class="cmp-receipt-row">
						<span><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( $success_email ); ?></strong>
					</div>
				<?php endif; ?>
				<?php if ( $success_city ) : ?>
					<div class="cmp-receipt-row">
						<span><?php esc_html_e( 'City / गाव / शहर', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( $success_city ); ?></strong>
					</div>
				<?php endif; ?>
				<?php if ( $success_utr ) : ?>
					<div class="cmp-receipt-row cmp-receipt-row-highlight">
						<span><?php esc_html_e( 'Payment UTR / Ref', 'class-manager-pro' ); ?></span>
						<strong><code><?php echo esc_html( $success_utr ); ?></code></strong>
					</div>
				<?php endif; ?>
				<div class="cmp-receipt-row">
					<span><?php esc_html_e( 'Payment Method', 'class-manager-pro' ); ?></span>
					<strong><span class="cmp-badge cmp-badge-upi">UPI</span></strong>
				</div>
				<div class="cmp-receipt-row">
					<span><?php esc_html_e( 'Date & Time', 'class-manager-pro' ); ?></span>
					<strong><?php echo esc_html( $now_formatted ); ?></strong>
				</div>
			</div>

			<canvas id="cmp-receipt-canvas" width="1000" height="1240" style="display:none;"></canvas>

			<div class="cmp-receipt-actions">
				<button type="button" class="cmp-share-btn cmp-btn-primary" id="cmp-btn-share-receipt">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
					<?php esc_html_e( 'Share Receipt Image', 'class-manager-pro' ); ?>
				</button>
				<button type="button" class="cmp-share-btn cmp-btn-secondary" id="cmp-btn-download-receipt">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
					<?php esc_html_e( 'Download Image', 'class-manager-pro' ); ?>
				</button>
				<button type="button" class="cmp-share-btn cmp-btn-outline" id="cmp-btn-copy-receipt">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
					<?php esc_html_e( 'Copy Text', 'class-manager-pro' ); ?>
				</button>
			</div>
			<p class="cmp-receipt-hint"><?php esc_html_e( 'Click "Share Receipt Image" to send your official admission card on WhatsApp or save it to your phone.', 'class-manager-pro' ); ?></p>
		</div>

		<script>
		(function(){
			var studentName = <?php echo wp_json_encode( $success_name ? $success_name : 'Registered Student' ); ?>;
			var className = <?php echo wp_json_encode( $batch->class_name ); ?>;
			var batchName = <?php echo wp_json_encode( $batch->batch_name ); ?>;
			var phone = <?php echo wp_json_encode( $success_phone ); ?>;
			var email = <?php echo wp_json_encode( $success_email ); ?>;
			var city = <?php echo wp_json_encode( $success_city ); ?>;
			var utr = <?php echo wp_json_encode( $success_utr ); ?>;
			var date = <?php echo wp_json_encode( $now_formatted ); ?>;
			var brandName = <?php echo wp_json_encode( get_bloginfo( 'name' ) ); ?>;

			var shareText = "🎓 *Admission Confirmation*\n\n"
				+ "*Class:* " + className + "\n"
				+ "*Batch:* " + batchName + "\n"
				+ "*Student:* " + studentName + "\n"
				+ "*Phone:* " + phone + "\n"
				+ (city ? ("*City:* " + city + "\n") : "")
				+ (utr ? ("*Payment UTR (UPI):* " + utr + "\n") : "")
				+ "*Date:* " + date + "\n\n"
				+ "✅ Registration Completed Successfully!";

			function drawReceiptCanvas() {
				var canvas = document.getElementById('cmp-receipt-canvas');
				if (!canvas) return null;
				var ctx = canvas.getContext('2d');
				if (!ctx) return null;

				var W = canvas.width;
				var H = canvas.height;

				// Background
				ctx.fillStyle = '#f8fafc';
				ctx.fillRect(0, 0, W, H);

				// Outer card shadow & border
				var m = 40;
				var cardW = W - m * 2;
				var cardH = H - m * 2;
				var r = 28;

				ctx.save();
				ctx.fillStyle = '#ffffff';
				ctx.strokeStyle = '#e2e8f0';
				ctx.lineWidth = 4;
				ctx.beginPath();
				ctx.roundRect(m, m, cardW, cardH, r);
				ctx.fill();
				ctx.stroke();
				ctx.restore();

				// Header banner
				var headerH = 220;
				ctx.save();
				ctx.beginPath();
				ctx.roundRect(m, m, cardW, headerH, [r, r, 0, 0]);
				ctx.clip();
				var grad = ctx.createLinearGradient(m, m, m + cardW, m + headerH);
				grad.addColorStop(0, '#008a20');
				grad.addColorStop(1, '#005f16');
				ctx.fillStyle = grad;
				ctx.fillRect(m, m, cardW, headerH);

				// Header text
				ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
				ctx.font = '600 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText(brandName.toUpperCase(), W / 2, m + 55);

				ctx.fillStyle = '#ffffff';
				ctx.font = 'bold 36px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.fillText('ADMISSION CONFIRMATION', W / 2, m + 105);

				ctx.fillStyle = '#dcfce7';
				ctx.font = '500 22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.fillText('✓ Registration & Payment Submitted Successfully', W / 2, m + 150);
				ctx.restore();

				// Table / Rows
				var startY = m + headerH + 50;
				var rowH = 72;
				var leftX = m + 50;
				var rightX = W - m - 50;

				var rows = [
					{ label: 'Course / Class', value: className },
					{ label: 'Batch Name', value: batchName },
					{ label: 'Student Name', value: studentName },
					{ label: 'Mobile Number', value: phone },
					{ label: 'Email Address', value: email || 'N/A' },
					{ label: 'City / गाव', value: city || 'N/A' }
				];

				for (var i = 0; i < rows.length; i++) {
					var curY = startY + (i * rowH);
					ctx.fillStyle = (i % 2 === 0) ? '#f8fafc' : '#ffffff';
					ctx.fillRect(leftX - 15, curY - 35, (rightX - leftX) + 30, rowH - 6);

					ctx.fillStyle = '#64748b';
					ctx.font = '600 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
					ctx.textAlign = 'left';
					ctx.fillText(rows[i].label, leftX, curY);

					ctx.fillStyle = '#0f172a';
					ctx.font = 'bold 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
					ctx.textAlign = 'right';
					var val = rows[i].value;
					if (val.length > 28) val = val.substring(0, 27) + '...';
					ctx.fillText(val, rightX, curY);
				}

				// UTR & Payment Highlight Box
				var utrBoxY = startY + (rows.length * rowH) + 15;
				var utrBoxH = 175;

				ctx.save();
				ctx.fillStyle = '#f0fdf4';
				ctx.strokeStyle = '#22c55e';
				ctx.lineWidth = 3;
				ctx.beginPath();
				ctx.roundRect(leftX - 15, utrBoxY, (rightX - leftX) + 30, utrBoxH, 16);
				ctx.fill();
				ctx.stroke();

				ctx.fillStyle = '#166534';
				ctx.font = 'bold 22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.textAlign = 'left';
				ctx.fillText('PAYMENT DETAILS (UPI)', leftX + 15, utrBoxY + 42);

				ctx.fillStyle = '#15803d';
				ctx.font = '600 20px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.textAlign = 'right';
				ctx.fillText('Status: Submitted / Under Review', rightX - 15, utrBoxY + 42);

				ctx.fillStyle = '#374151';
				ctx.font = '500 22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.textAlign = 'left';
				ctx.fillText('Transaction UTR:', leftX + 15, utrBoxY + 95);

				ctx.fillStyle = '#111827';
				ctx.font = 'bold 26px "Courier New", monospace';
				ctx.textAlign = 'left';
				ctx.fillText(utr ? utr : 'Pending Verification', leftX + 220, utrBoxY + 95);

				ctx.fillStyle = '#6b7280';
				ctx.font = '500 20px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.textAlign = 'left';
				ctx.fillText('Submitted At: ' + date, leftX + 15, utrBoxY + 140);
				ctx.restore();

				// Footer
				var footerY = H - m - 45;
				ctx.fillStyle = '#94a3b8';
				ctx.font = '500 20px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('Official Admission Confirmation • Keep this receipt for your records', W / 2, footerY);

				return canvas;
			}

			// Pre-render canvas on page load
			setTimeout(drawReceiptCanvas, 100);

			function getReceiptBlob(callback) {
				var canvas = drawReceiptCanvas();
				if (!canvas) return;
				canvas.toBlob(function(blob){
					callback(blob, canvas);
				}, 'image/png', 0.95);
			}

			var shareBtn = document.getElementById('cmp-btn-share-receipt');
			if (shareBtn) {
				shareBtn.addEventListener('click', function(){
					shareBtn.disabled = true;
					shareBtn.innerText = '⏳ Generating Image...';

					getReceiptBlob(function(blob, canvas){
						var fileName = 'admission-receipt-' + studentName.replace(/[^a-zA-Z0-9]/g, '_') + '.png';
						var file = new File([blob], fileName, { type: 'image/png' });

						if (navigator.canShare && navigator.canShare({ files: [file] })) {
							navigator.share({
								title: 'Admission Confirmation - ' + studentName,
								text: shareText,
								files: [file]
							}).then(function(){
								shareBtn.disabled = false;
								shareBtn.innerText = '📤 Share Receipt Image';
							}).catch(function(err){
								shareBtn.disabled = false;
								shareBtn.innerText = '📤 Share Receipt Image';
							});
						} else {
							// Fallback: download the image and prompt WhatsApp share
							var link = document.createElement('a');
							link.download = fileName;
							link.href = canvas.toDataURL('image/png');
							link.click();

							setTimeout(function(){
								window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(shareText), '_blank');
								shareBtn.disabled = false;
								shareBtn.innerText = '📤 Share Receipt Image';
							}, 800);
						}
					});
				});
			}

			var downloadBtn = document.getElementById('cmp-btn-download-receipt');
			if (downloadBtn) {
				downloadBtn.addEventListener('click', function(){
					var canvas = drawReceiptCanvas();
					if (!canvas) return;
					var fileName = 'admission-receipt-' + studentName.replace(/[^a-zA-Z0-9]/g, '_') + '.png';
					var link = document.createElement('a');
					link.download = fileName;
					link.href = canvas.toDataURL('image/png');
					link.click();
				});
			}

			var copyBtn = document.getElementById('cmp-btn-copy-receipt');
			if (copyBtn) {
				copyBtn.addEventListener('click', function(){
					if (navigator.clipboard) {
						navigator.clipboard.writeText(shareText).then(function(){
							copyBtn.innerText = '✓ Copied!';
							setTimeout(function(){ copyBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy Text'; }, 2000);
						});
					} else {
						var ta = document.createElement('textarea');
						ta.value = shareText;
						document.body.appendChild(ta);
						ta.select();
						document.execCommand('copy');
						document.body.removeChild(ta);
						copyBtn.innerText = '✓ Copied!';
						setTimeout(function(){ copyBtn.innerText = 'Copy Text'; }, 2000);
					}
				});
			}
		})();
		</script>
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
		<div class="cmp-public-card">
			<header class="cmp-public-header">
				<div class="cmp-public-brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
				<h1 class="cmp-public-title"><?php echo esc_html( $batch->batch_name ); ?></h1>
				<div class="cmp-public-meta">
					<span class="cmp-public-meta-item"><?php echo esc_html( $batch->class_name ); ?></span>
					<span class="cmp-public-meta-pill"><?php echo ! empty( $batch->is_free ) ? esc_html__( 'Free Batch', 'class-manager-pro' ) : esc_html( sprintf( __( 'Fee: %s', 'class-manager-pro' ), cmp_format_money( $batch_fee ) ) ); ?></span>
					<?php if ( $batch->start_date ) : ?>
						<span class="cmp-public-meta-item"><?php echo esc_html( sprintf( __( 'Starts: %s', 'class-manager-pro' ), $batch->start_date ) ); ?></span>
					<?php endif; ?>
				</div>
			</header>

			<div class="cmp-public-body">
				<?php echo $form_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php if ( 'success' === $status ) : ?>
					<?php if ( ! empty( $batch->razorpay_link ) && empty( $batch->is_free ) ) : ?>
						<div class="cmp-public-payment-box">
							<p><?php esc_html_e( 'If you have not paid yet, please complete your batch payment via Razorpay:', 'class-manager-pro' ); ?></p>
							<a class="cmp-public-button cmp-public-button-secondary" href="<?php echo esc_url( $batch->razorpay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Pay via Razorpay →', 'class-manager-pro' ); ?></a>
						</div>
					<?php endif; ?>
				<?php elseif ( 'active' !== $batch->status ) : ?>
					<div class="cmp-public-alert cmp-public-alert-error">
						<p><?php esc_html_e( 'This batch is currently not accepting new registrations.', 'class-manager-pro' ); ?></p>
					</div>
				<?php elseif ( 'temp' === $access_type && $token_row && in_array( cmp_get_batch_registration_link_status( $token_row )['key'], array( 'used_successfully', 'expired', 'closed_by_admin' ), true ) ) : ?>
					<div class="cmp-public-alert cmp-public-alert-error">
						<p><?php esc_html_e( 'This temporary registration link is expired or already used.', 'class-manager-pro' ); ?></p>
					</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-public-form-compact">
						<input type="hidden" name="action" value="cmp_submit_public_batch_form">
						<input type="hidden" name="access_type" value="<?php echo esc_attr( $access_type ); ?>">
						<input type="hidden" name="access_token" value="<?php echo esc_attr( $access_token ); ?>">
						<input type="hidden" name="notice_token" value="<?php echo esc_attr( $access_token ); ?>">
						<?php wp_nonce_field( 'cmp_public_batch_form_' . $access_token ); ?>

						<div class="cmp-form-field">
							<label for="cmp_student_name"><?php esc_html_e( 'Full Name *', 'class-manager-pro' ); ?></label>
							<input type="text" id="cmp_student_name" name="name" required placeholder="<?php esc_attr_e( 'Enter your full name', 'class-manager-pro' ); ?>">
						</div>

						<div class="cmp-form-field">
							<label for="cmp_student_phone"><?php esc_html_e( 'WhatsApp / Mobile Number *', 'class-manager-pro' ); ?></label>
							<input type="tel" id="cmp_student_phone" name="phone" required placeholder="<?php esc_attr_e( '10-digit mobile number', 'class-manager-pro' ); ?>">
						</div>

						<div class="cmp-form-field">
							<label for="cmp_student_email"><?php esc_html_e( 'Email Address *', 'class-manager-pro' ); ?></label>
							<input type="email" id="cmp_student_email" name="email" required placeholder="<?php esc_attr_e( 'name@example.com', 'class-manager-pro' ); ?>">
						</div>

						<div class="cmp-form-field">
							<label for="cmp_student_city"><?php esc_html_e( 'City / गाव / शहर *', 'class-manager-pro' ); ?></label>
							<input type="text" id="cmp_student_city" name="city" required placeholder="<?php esc_attr_e( 'Enter your city or village name', 'class-manager-pro' ); ?>">
						</div>

						<div class="cmp-form-field">
							<label for="cmp_student_utr">
								<?php esc_html_e( 'Payment UTR / Transaction No. *', 'class-manager-pro' ); ?>
								<span class="cmp-field-desc"><?php esc_html_e( 'Enter 12-digit UPI / Bank Reference / UTR Number', 'class-manager-pro' ); ?></span>
							</label>
							<input type="text" id="cmp_student_utr" name="utr_number" required placeholder="<?php esc_attr_e( 'e.g. 4123xxxxxxxx or UPI Ref No.', 'class-manager-pro' ); ?>">
						</div>

						<div class="cmp-form-field">
							<label for="cmp_student_notes"><?php esc_html_e( 'Notes / Remarks (Optional)', 'class-manager-pro' ); ?></label>
							<input type="text" id="cmp_student_notes" name="notes" placeholder="<?php esc_attr_e( 'Any optional remark or request', 'class-manager-pro' ); ?>">
						</div>

						<button type="submit" class="cmp-public-submit-btn">
							<?php esc_html_e( 'Submit Registration →', 'class-manager-pro' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
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

	// Compulsory field validation
	$name       = sanitize_text_field( cmp_field( $_POST, 'name' ) );
	$phone      = sanitize_text_field( cmp_field( $_POST, 'phone' ) );
	$email      = sanitize_email( cmp_field( $_POST, 'email' ) );
	$city       = sanitize_text_field( cmp_field( $_POST, 'city' ) );
	$utr_number = sanitize_text_field( cmp_field( $_POST, 'utr_number' ) );

	if ( '' === $name || '' === $phone || '' === $email || '' === $city || '' === $utr_number ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'cmp_public_error' => __( 'All fields (Name, Phone, Email, City, and Payment UTR) are compulsory.', 'class-manager-pro' ),
				),
				cmp_get_public_batch_access_url( $access_type, $access_token )
			)
		);
		exit;
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
			'student_name' => $student && ! empty( $student->name ) ? $student->name : $name,
			'phone'        => $student && ! empty( $student->phone ) ? $student->phone : $phone,
			'email'        => $student && ! empty( $student->email ) ? $student->email : $email,
			'city'         => $student && ! empty( $student->city_original ) ? $student->city_original : $city,
			'batch_name'   => $batch->batch_name,
			'class_name'   => $batch->class_name,
			'utr_number'   => $utr_number,
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
