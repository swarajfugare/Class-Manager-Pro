<?php
/**
 * Settings admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the settings page.
 */
function cmp_render_settings_page() {
	cmp_require_manage_options();

	$active_tab = sanitize_key( cmp_field( $_GET, 'tab', 'razorpay' ) );
	if ( ! in_array( $active_tab, array( 'razorpay', 'whatsapp', 'messages', 'integrations', 'logs' ), true ) ) {
		$active_tab = 'razorpay';
	}

	$key_id                    = (string) get_option( 'cmp_razorpay_key_id', '' );
	$secret                    = (string) get_option( 'cmp_razorpay_secret', '' );
	$webhook_secret            = (string) get_option( 'cmp_razorpay_webhook_secret', '' );
	$webhook_url               = rest_url( 'cmp/v1/razorpay-webhook' );
	$razorpay_fee_percent      = (float) get_option( 'cmp_razorpay_fee_percent', 2.36 );
	$currency_symbol           = (string) get_option( 'cmp_currency_symbol', '₹' );
	$auto_deduct_gateway_fee   = '1' === (string) get_option( 'cmp_auto_deduct_gateway_fee', '1' );

	$notifications_enabled     = '1' === (string) get_option( 'cmp_notifications_enabled', get_option( 'cmp_sms_enabled', '0' ) );
	$reminder_days             = (int) get_option( 'cmp_reminder_days', 7 );
	$message_templates         = cmp_get_default_message_templates();
	$whatsapp_template         = (string) get_option( 'cmp_whatsapp_template', '' );
	$email_subject             = (string) get_option( 'cmp_email_subject', '' );
	$email_template            = (string) get_option( 'cmp_email_template', $message_templates['email_template'] );
	$payment_reminder_template = (string) get_option( 'cmp_message_template_payment_reminder', $message_templates['payment_reminder'] );
	$welcome_template          = (string) get_option( 'cmp_message_template_welcome', $message_templates['welcome_message'] );
	$course_info_template      = (string) get_option( 'cmp_message_template_course_info', $message_templates['course_info'] );

	$attendance_enabled        = cmp_is_attendance_enabled();
	$last_sync_at              = (string) get_option( 'cmp_last_razorpay_sync_at', '' );
	$last_sync_summary         = get_option( 'cmp_last_razorpay_sync_summary', array() );

	$wa_settings               = function_exists( 'cmp_get_whatsapp_settings' ) ? cmp_get_whatsapp_settings() : array(
		'enabled'              => false,
		'auto_welcome'         => true,
		'gateway_type'         => 'meta_cloud',
		'api_url'              => '',
		'api_token'            => '',
		'phone_number_id'      => '',
		'sender'               => get_bloginfo( 'name' ),
		'webhook_verify_token' => '',
		'welcome_template'     => '',
	);
	$wa_webhook_url            = rest_url( 'cmp/v1/whatsapp-webhook' );
	$wp_razorpay_keys          = cmp_detect_wordpress_razorpay();
	$credentials               = cmp_get_razorpay_credentials();
	$has_api_credentials       = '' !== $credentials['key_id'] && '' !== $credentials['secret'];

	$teachers                  = cmp_get_teacher_users();
	$selected_teacher_log_id   = absint( cmp_field( $_GET, 'teacher_user_id', 0 ) );
	$admin_logs                = cmp_get_admin_logs( 100 );
	$teacher_logs              = $selected_teacher_log_id ? cmp_get_teacher_logs( $selected_teacher_log_id, 200 ) : array();

	$students_export_url       = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-students', 'cmp_export' => 'students' ), admin_url( 'admin.php' ) ), 'cmp_export_students' );
	$payments_export_url       = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-payments', 'cmp_export' => 'payments' ), admin_url( 'admin.php' ) ), 'cmp_export_payments' );
	$classes_export_url        = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-classes', 'cmp_export' => 'classes' ), admin_url( 'admin.php' ) ), 'cmp_export_classes' );
	$admin_logs_export_url     = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-settings', 'cmp_export' => 'admin-logs' ), admin_url( 'admin.php' ) ), 'cmp_export_admin-logs' );
	$teacher_logs_export_url   = $selected_teacher_log_id ? wp_nonce_url( add_query_arg( array( 'page' => 'cmp-settings', 'cmp_export' => 'teacher-logs', 'teacher_user_id' => $selected_teacher_log_id ), admin_url( 'admin.php' ) ), 'cmp_export_teacher-logs' ) : '';
	$smartcertify_status       = cmp_smartcertify_get_status_summary();
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Settings', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Configure payment gateway credentials, WhatsApp messaging, automated alerts, and system logs.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-settings' ); ?>

		<nav class="nav-tab-wrapper cmp-tab-nav" style="margin: 16px 0 22px 0;">
			<a class="nav-tab <?php echo 'razorpay' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings', array( 'tab' => 'razorpay' ) ) ); ?>">💳 <?php esc_html_e( 'Payment & Razorpay', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'whatsapp' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings', array( 'tab' => 'whatsapp' ) ) ); ?>">📱 <?php esc_html_e( 'WhatsApp API', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'messages' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings', array( 'tab' => 'messages' ) ) ); ?>">✉️ <?php esc_html_e( 'Message Templates & Alerts', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'integrations' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings', array( 'tab' => 'integrations' ) ) ); ?>">🎓 <?php esc_html_e( 'Certificates & Attendance', 'class-manager-pro' ); ?></a>
			<a class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( cmp_admin_url( 'cmp-settings', array( 'tab' => 'logs' ) ) ); ?>">📋 <?php esc_html_e( 'Backups & Activity Logs', 'class-manager-pro' ); ?></a>
		</nav>

		<?php if ( 'razorpay' === $active_tab ) : ?>
			<!-- TAB 1: PAYMENT & RAZORPAY -->
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Razorpay Connection & Gateway Controls', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Configure Razorpay API keys, webhooks, and gateway fee deduction settings.', 'class-manager-pro' ); ?></p>
					</div>
					<?php if ( $has_api_credentials && 'class-manager-pro' !== $credentials['source'] ) : ?>
						<span class="cmp-inline-badge"><?php echo esc_html( sprintf( __( 'Using detected keys from %s', 'class-manager-pro' ), $credentials['source'] ) ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $wp_razorpay_keys ) ) : ?>
					<div class="cmp-callout cmp-callout-info" style="margin-bottom: 20px;">
						<h3><?php esc_html_e( 'Detected WordPress Razorpay Integrations', 'class-manager-pro' ); ?></h3>
						<ul class="cmp-simple-list">
							<?php foreach ( $wp_razorpay_keys as $source => $keys ) : ?>
								<li><?php echo esc_html( sprintf( __( '%1$s: %2$s', 'class-manager-pro' ), $source, $keys['key_id'] ) ); ?></li>
							<?php endforeach; ?>
						</ul>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
							<input type="hidden" name="action" value="cmp_import_detected_razorpay_keys">
							<?php wp_nonce_field( 'cmp_import_detected_razorpay_keys' ); ?>
							<?php submit_button( __( 'Use Detected Razorpay Keys', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_save_settings">
					<?php wp_nonce_field( 'cmp_save_settings' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-razorpay-key-id"><?php esc_html_e( 'Razorpay Key ID', 'class-manager-pro' ); ?></label></th>
							<td><input type="text" id="cmp-razorpay-key-id" name="razorpay_key_id" class="regular-text" value="<?php echo esc_attr( $key_id ); ?>" autocomplete="off" placeholder="rzp_live_..."></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-razorpay-secret"><?php esc_html_e( 'Razorpay Secret', 'class-manager-pro' ); ?></label></th>
							<td><input type="password" id="cmp-razorpay-secret" name="razorpay_secret" class="regular-text" value="<?php echo esc_attr( $secret ); ?>" autocomplete="new-password"></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-webhook-secret"><?php esc_html_e( 'Webhook Secret', 'class-manager-pro' ); ?></label></th>
							<td><input type="password" id="cmp-webhook-secret" name="razorpay_webhook_secret" class="regular-text" value="<?php echo esc_attr( $webhook_secret ); ?>" autocomplete="new-password"></td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-webhook-url"><?php esc_html_e( 'Webhook URL', 'class-manager-pro' ); ?></label></th>
							<td>
								<div class="cmp-inline-tools">
									<input type="url" id="cmp-webhook-url" class="large-text code" value="<?php echo esc_url( $webhook_url ); ?>" readonly>
									<button type="button" class="button" data-cmp-copy-target="#cmp-webhook-url"><?php esc_html_e( 'Copy', 'class-manager-pro' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Configure this Webhook URL in Razorpay Dashboard. Enable payment.captured, payment.failed, and payment_link.paid events.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr style="border-top: 1px solid #e2e8f0;">
							<th scope="row"><label for="cmp-fee-percent"><?php esc_html_e( 'Razorpay Fee Deduction (%)', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="number" id="cmp-fee-percent" name="razorpay_fee_percent" class="small-text" step="0.01" min="0" max="100" value="<?php echo esc_attr( $razorpay_fee_percent ); ?>"> <strong>%</strong>
								<p class="description"><?php esc_html_e( 'Fixed gateway fee percentage charged by Razorpay (standard is 2% + 18% GST = 2.36%). The plugin uses this to automatically calculate exact batch deductions in Rupees as admissions increase.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-currency-symbol"><?php esc_html_e( 'Currency Symbol', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="text" id="cmp-currency-symbol" name="currency_symbol" class="small-text" value="<?php echo esc_attr( $currency_symbol ); ?>">
								<p class="description"><?php esc_html_e( 'Default currency symbol displayed across student fees, admissions, and financial reports.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Batch Profitability Calculation', 'class-manager-pro' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="auto_deduct_gateway_fee" value="1" <?php checked( $auto_deduct_gateway_fee, true ); ?>>
									<strong><?php esc_html_e( 'Include Razorpay gateway deductions in batch total expenses and net income', 'class-manager-pro' ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'When checked, the auto-calculated Razorpay charges will be included as an expense line in batch financials.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Payment Settings', 'class-manager-pro' ) ); ?>
				</form>

				<div class="cmp-grid cmp-grid-2" style="margin-top:24px;">
					<div class="cmp-callout">
						<h3><?php esc_html_e( 'Auto-Sync All Razorpay Payment Pages', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Scan and import successful payments directly into matching batches, capturing failed transactions as leads.', 'class-manager-pro' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-inline-form-stack" data-cmp-ajax-import="razorpay-sync-all" data-cmp-feedback="#cmp-razorpay-sync-all-feedback" data-cmp-busy-label="<?php esc_attr_e( 'Syncing...', 'class-manager-pro' ); ?>">
							<input type="hidden" name="action" value="cmp_sync_all_payment_pages">
							<?php wp_nonce_field( 'cmp_sync_all_payment_pages' ); ?>
							<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
								<input type="text" name="razorpay_page_id" class="regular-text" style="max-width:280px;" placeholder="<?php esc_attr_e( 'Optional: Page ID (pl_xxx)', 'class-manager-pro' ); ?>">
								<?php submit_button( __( 'Sync Payments Now', 'class-manager-pro' ), 'primary', 'submit', false, array( 'id' => 'cmp-btn-sync-all-pages' ) ); ?>
							</div>
						</form>
						<div class="cmp-muted" id="cmp-razorpay-sync-all-feedback">
							<?php if ( ! empty( $last_sync_at ) ) : ?>
								<p style="margin:4px 0 0 0; font-size:12px;"><strong><?php esc_html_e( 'Last synced:', 'class-manager-pro' ); ?></strong> <?php echo esc_html( $last_sync_at ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="cmp-callout">
						<h3><?php esc_html_e( 'API Connection Test & Workspace', 'class-manager-pro' ); ?></h3>
						<p class="cmp-muted"><?php esc_html_e( 'Verify authentication with Razorpay or open the dedicated payment page import tool.', 'class-manager-pro' ); ?></p>
						<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:12px;">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form" data-cmp-ajax-import="razorpay-connection" data-cmp-feedback="#cmp-razorpay-connection-feedback" data-cmp-busy-label="<?php esc_attr_e( 'Testing...', 'class-manager-pro' ); ?>">
								<input type="hidden" name="action" value="cmp_test_razorpay_connection">
								<?php wp_nonce_field( 'cmp_test_razorpay_connection' ); ?>
								<?php submit_button( __( 'Test Connection', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
							</form>
							<a class="button button-secondary" href="<?php echo esc_url( cmp_admin_url( 'cmp-razorpay-import' ) ); ?>"><?php esc_html_e( 'Open Import Workspace →', 'class-manager-pro' ); ?></a>
						</div>
						<p class="cmp-muted" id="cmp-razorpay-connection-feedback" style="margin-top:8px; font-size:12px;"></p>
					</div>
				</div>
			</section>

		<?php elseif ( 'whatsapp' === $active_tab ) : ?>
			<!-- TAB 2: WHATSAPP API & WEBHOOK -->
			<section class="cmp-panel" id="cmp-whatsapp-settings">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'WhatsApp API & Webhook Integration', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Send instant WhatsApp confirmations to newly admitted students and handle message status webhooks.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_save_whatsapp_settings">
					<?php wp_nonce_field( 'cmp_save_whatsapp_settings' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Direct Messaging Status', 'class-manager-pro' ); ?></th>
							<td>
								<label style="display:block; margin-bottom: 6px;">
									<input type="checkbox" name="whatsapp_enabled" value="1" <?php checked( $wa_settings['enabled'], true ); ?>>
									<strong><?php esc_html_e( 'Enable WhatsApp API Delivery', 'class-manager-pro' ); ?></strong>
								</label>
								<label style="display:block;">
									<input type="checkbox" name="whatsapp_auto_welcome" value="1" <?php checked( $wa_settings['auto_welcome'], true ); ?>>
									<?php esc_html_e( 'Automatically send WhatsApp Welcome message to student upon new admission', 'class-manager-pro' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-gateway"><?php esc_html_e( 'Gateway Provider', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-wa-gateway" name="whatsapp_gateway_type">
									<option value="meta_cloud" <?php selected( $wa_settings['gateway_type'], 'meta_cloud' ); ?>><?php esc_html_e( 'WhatsApp Cloud API (Meta Official)', 'class-manager-pro' ); ?></option>
									<option value="custom_webhook" <?php selected( $wa_settings['gateway_type'], 'custom_webhook' ); ?>><?php esc_html_e( 'Custom Gateway Webhook (Wati / AiSensy / UltraMsg / WA-Sender)', 'class-manager-pro' ); ?></option>
									<option value="wa_link" <?php selected( $wa_settings['gateway_type'], 'wa_link' ); ?>><?php esc_html_e( 'Direct Chat Link (wa.me fallback without API)', 'class-manager-pro' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-phone-id"><?php esc_html_e( 'Phone Number ID', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="text" id="cmp-wa-phone-id" name="whatsapp_phone_number_id" class="regular-text" value="<?php echo esc_attr( $wa_settings['phone_number_id'] ); ?>" placeholder="e.g. 104829104859201">
								<p class="description"><?php esc_html_e( 'Required for Meta WhatsApp Cloud API (found in Meta App Dashboard > WhatsApp > API Setup).', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-token"><?php esc_html_e( 'API Token / Bearer Key', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="password" id="cmp-wa-token" name="whatsapp_api_token" class="regular-text" value="<?php echo esc_attr( $wa_settings['api_token'] ); ?>" autocomplete="new-password">
								<p class="description"><?php esc_html_e( 'Meta System User Permanent Token or third-party WhatsApp Gateway Bearer Key.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-api-url"><?php esc_html_e( 'Custom Gateway URL', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="url" id="cmp-wa-api-url" name="whatsapp_api_url" class="large-text code" value="<?php echo esc_url( $wa_settings['api_url'] ); ?>" placeholder="https://api.yourwhatsappgateway.com/send">
								<p class="description"><?php esc_html_e( 'Endpoint URL for Custom Webhook Gateway. Leave blank if using Meta Cloud API default endpoint.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-webhook-url"><?php esc_html_e( 'WhatsApp Webhook URL', 'class-manager-pro' ); ?></label></th>
							<td>
								<div class="cmp-inline-tools">
									<input type="url" id="cmp-wa-webhook-url" class="large-text code" value="<?php echo esc_url( $wa_webhook_url ); ?>" readonly>
									<button type="button" class="button" data-cmp-copy-target="#cmp-wa-webhook-url"><?php esc_html_e( 'Copy Webhook URL', 'class-manager-pro' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Paste this Webhook Callback URL into Meta Developer Dashboard or your WhatsApp Gateway.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-verify-token"><?php esc_html_e( 'Webhook Verify Token', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="text" id="cmp-wa-verify-token" name="whatsapp_webhook_verify_token" class="regular-text" value="<?php echo esc_attr( $wa_settings['webhook_verify_token'] ); ?>" placeholder="e.g. my_secure_token_2026">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-wa-template"><?php esc_html_e( 'Student Welcome Template', 'class-manager-pro' ); ?></label></th>
							<td>
								<textarea id="cmp-wa-template" name="whatsapp_welcome_template" rows="5" class="large-text"><?php echo esc_textarea( $wa_settings['welcome_template'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Variables: {{name}}, {{class_name}}, {{batch_name}}, {{fee_status}}, {{login_url}}, {{due_date}}, {{site_name}}.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save WhatsApp Settings', 'class-manager-pro' ) ); ?>
				</form>

				<div class="cmp-callout" style="margin-top: 20px;">
					<h3><?php esc_html_e( 'Test WhatsApp Connection', 'class-manager-pro' ); ?></h3>
					<p class="cmp-muted"><?php esc_html_e( 'Send a live test message to verify that your credentials communicate properly.', 'class-manager-pro' ); ?></p>
					<div class="cmp-toolbar" style="display:flex; gap:10px; align-items:center;">
						<input type="tel" id="cmp-wa-test-phone" placeholder="<?php esc_attr_e( '10-digit mobile number', 'class-manager-pro' ); ?>" class="regular-text">
						<button type="button" class="button button-secondary" id="cmp-wa-test-btn"><?php esc_html_e( 'Send Test Message', 'class-manager-pro' ); ?></button>
						<span id="cmp-wa-test-feedback" class="cmp-muted"></span>
					</div>
				</div>
			</section>

		<?php elseif ( 'messages' === $active_tab ) : ?>
			<!-- TAB 3: MESSAGE & EMAIL TEMPLATES & ALERTS -->
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Email & Reminder Templates', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Automate fee reminder emails, welcome messages, and anti-spam institute admission digests.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_save_sms_settings">
					<?php wp_nonce_field( 'cmp_save_sms_settings' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-sms-enabled"><?php esc_html_e( 'Enable Reminders', 'class-manager-pro' ); ?></label></th>
							<td>
								<label><input type="checkbox" id="cmp-sms-enabled" name="sms_enabled" value="1" <?php checked( $notifications_enabled, true ); ?>> <?php esc_html_e( 'Send automatic fee reminders', 'class-manager-pro' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-reminder-days"><?php esc_html_e( 'Reminder Window', 'class-manager-pro' ); ?></label></th>
							<td>
								<select id="cmp-reminder-days" name="reminder_days">
									<?php foreach ( array( 1, 3, 7, 14 ) as $day_option ) : ?>
										<option value="<?php echo esc_attr( $day_option ); ?>" <?php selected( $reminder_days, $day_option ); ?>><?php echo esc_html( sprintf( _n( '%d day before due date', '%d days before due date', $day_option, 'class-manager-pro' ), $day_option ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-email-subject"><?php esc_html_e( 'Email Subject', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="text" id="cmp-email-subject" name="email_subject" class="large-text" value="<?php echo esc_attr( $email_subject ); ?>">
								<p class="description"><?php esc_html_e( 'Supported variables: {{name}}, {{amount}}, {{course}}, {{batch}}.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'HTML Email Template', 'class-manager-pro' ); ?></th>
							<td>
								<?php
								wp_editor(
									$email_template,
									'cmp_email_template_editor',
									array(
										'textarea_name' => 'email_template',
										'textarea_rows' => 8,
										'media_buttons' => false,
										'quicktags'     => true,
										'teeny'         => false,
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'Variables: {{name}}, {{amount}}, {{course}}, {{batch}}, {{due_date}}, {{payment_link}}.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-whatsapp-template"><?php esc_html_e( 'WhatsApp Manual Reminder Template', 'class-manager-pro' ); ?></label></th>
							<td>
								<textarea id="cmp-whatsapp-template" name="whatsapp_template" rows="4" class="large-text"><?php echo esc_textarea( $whatsapp_template ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used when clicking manual reminder links. Supported: {{name}}, {{amount}}, {{course}}, {{batch}}, {{due_date}}.', 'class-manager-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Welcome Message Template', 'class-manager-pro' ); ?></th>
							<td>
								<textarea id="cmp-message-template-welcome" name="message_template_welcome" rows="3" class="large-text"><?php echo esc_textarea( $welcome_template ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Admission Alerts (Anti-Spam)', 'class-manager-pro' ); ?></th>
							<td>
								<label style="display:block; margin-bottom: 6px;">
									<input type="checkbox" name="notify_admin_on_admission" value="1" <?php checked( (bool) get_option( 'cmp_notify_admin_on_admission', 0 ), true ); ?>>
									<?php esc_html_e( 'Send immediate email to admin on every admission', 'class-manager-pro' ); ?>
								</label>
								<label style="display:block; margin-bottom: 6px;">
									<input type="checkbox" name="notify_teacher_on_admission" value="1" <?php checked( (bool) get_option( 'cmp_notify_teacher_on_admission', 0 ), true ); ?>>
									<?php esc_html_e( 'Send immediate email to teacher on every admission to their batch', 'class-manager-pro' ); ?>
								</label>
								<label style="display:block; margin-bottom: 6px;">
									<input type="checkbox" name="enable_daily_admission_digest" value="1" <?php checked( (bool) get_option( 'cmp_enable_daily_admission_digest', 1 ), true ); ?>>
									<strong><?php esc_html_e( 'Enable Daily Admission Digest (Recommended: 1 consolidated daily email instead of inbox spam)', 'class-manager-pro' ); ?></strong>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cmp-digest-recipient"><?php esc_html_e( 'Digest Recipient Email', 'class-manager-pro' ); ?></label></th>
							<td>
								<input type="email" id="cmp-digest-recipient" name="digest_recipient_email" class="regular-text" value="<?php echo esc_attr( get_option( 'cmp_digest_recipient_email', get_option( 'admin_email' ) ) ); ?>">
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Message Settings', 'class-manager-pro' ) ); ?>
				</form>

				<div style="display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
						<input type="hidden" name="action" value="cmp_send_fee_reminders">
						<?php wp_nonce_field( 'cmp_send_fee_reminders' ); ?>
						<?php submit_button( __( 'Send Due Reminders Now', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
						<input type="hidden" name="action" value="cmp_send_daily_digest_now">
						<?php wp_nonce_field( 'cmp_send_daily_digest_now' ); ?>
						<?php submit_button( __( 'Send Daily Institute Digest Now', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</section>

		<?php elseif ( 'integrations' === $active_tab ) : ?>
			<!-- TAB 4: CERTIFICATES & ATTENDANCE -->
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Certificate Integration (SmartCertify)', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Automate certificate generation and issuing upon batch completion.', 'class-manager-pro' ); ?></p>
					</div>
					<?php if ( $smartcertify_status['enabled'] ) : ?>
						<span class="cmp-inline-badge cmp-inline-badge-success"><?php esc_html_e( 'Enabled', 'class-manager-pro' ); ?></span>
					<?php else : ?>
						<span class="cmp-inline-badge cmp-inline-badge-warning"><?php esc_html_e( 'Disabled', 'class-manager-pro' ); ?></span>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_save_smartcertify_settings">
					<?php wp_nonce_field( 'cmp_save_smartcertify_settings' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-smartcertify-enabled"><?php esc_html_e( 'SmartCertify Integration', 'class-manager-pro' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="cmp-smartcertify-enabled" name="smartcertify_integration_enabled" value="1" <?php checked( $smartcertify_status['enabled'], true ); ?>>
									<strong><?php esc_html_e( 'Allow Class Manager Pro to automatically generate certificates when marking a batch as Complete', 'class-manager-pro' ); ?></strong>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Connection Status', 'class-manager-pro' ); ?></th>
							<td><strong><?php echo esc_html( $smartcertify_status['connection_label'] ); ?></strong></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Plugin Version', 'class-manager-pro' ); ?></th>
							<td><?php echo esc_html( $smartcertify_status['plugin_version'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last Sync Status', 'class-manager-pro' ); ?></th>
							<td><?php echo esc_html( $smartcertify_status['last_sync_status'] ); ?></td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Integration Settings', 'class-manager-pro' ) ); ?>
				</form>
			</section>

			<section class="cmp-panel" style="margin-top:20px;">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Attendance Defaults', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Attendance is managed per batch. Enrolled students start with Present by default.', 'class-manager-pro' ); ?></p>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
					<input type="hidden" name="action" value="cmp_save_attendance_settings">
					<?php wp_nonce_field( 'cmp_save_attendance_settings' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cmp-attendance-enabled"><?php esc_html_e( 'Enable Attendance', 'class-manager-pro' ); ?></label></th>
							<td>
								<label><input type="checkbox" id="cmp-attendance-enabled" name="attendance_enabled" value="1" <?php checked( $attendance_enabled, true ); ?>> <?php esc_html_e( 'Show per-batch attendance tracking', 'class-manager-pro' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Default Status', 'class-manager-pro' ); ?></th>
							<td>
								<span class="cmp-inline-badge cmp-inline-badge-success"><?php esc_html_e( 'Present', 'class-manager-pro' ); ?></span>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Attendance Settings', 'class-manager-pro' ) ); ?>
				</form>
			</section>

		<?php elseif ( 'logs' === $active_tab ) : ?>
			<!-- TAB 5: BACKUPS & ACTIVITY LOGS -->
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Data Backup & CSV Exports', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Download complete CSV backups of your institute records.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-toolbar" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
					<a class="button button-primary" href="<?php echo esc_url( $students_export_url ); ?>">📥 <?php esc_html_e( 'Export Students CSV', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( $payments_export_url ); ?>">📥 <?php esc_html_e( 'Export Payments CSV', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( $classes_export_url ); ?>">📥 <?php esc_html_e( 'Export Classes CSV', 'class-manager-pro' ); ?></a>
				</div>
				<p class="description"><?php echo esc_html( sprintf( __( 'Error logs are stored securely at: %s', 'class-manager-pro' ), cmp_get_log_file_path() ) ); ?></p>
			</section>

			<section class="cmp-panel" style="margin-top:20px;">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Admin Activity Logs', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Review recent actions performed by administrators.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( $admin_logs_export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
				</div>

				<div class="cmp-log-container">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Timestamp', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Admin', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Details', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $admin_logs ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No admin activity recorded yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $admin_logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( $log->created_at ); ?></td>
										<td><?php echo esc_html( $log->admin_name ? $log->admin_name : __( 'System', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( cmp_get_admin_log_action_label( $log->action ) ); ?></td>
										<td><?php echo esc_html( $log->message ? $log->message : sprintf( __( '%1$s #%2$d', 'class-manager-pro' ), ucfirst( $log->object_type ), (int) $log->object_id ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel" style="margin-top:20px;">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Teacher Activity Logs', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Audit access logs from teacher logins.', 'class-manager-pro' ); ?></p>
					</div>
					<?php if ( $teacher_logs_export_url ) : ?>
						<a class="button" href="<?php echo esc_url( $teacher_logs_export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
					<?php endif; ?>
				</div>

				<form method="get" class="cmp-inline-form" style="margin-bottom:14px;">
					<input type="hidden" name="page" value="cmp-settings">
					<input type="hidden" name="tab" value="logs">
					<label for="cmp-teacher-log-user">
						<span><?php esc_html_e( 'Select Teacher:', 'class-manager-pro' ); ?></span>
						<select id="cmp-teacher-log-user" name="teacher_user_id">
							<option value="0"><?php esc_html_e( 'Choose teacher', 'class-manager-pro' ); ?></option>
							<?php foreach ( $teachers as $teacher ) : ?>
								<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( $selected_teacher_log_id, (int) $teacher->ID ); ?>><?php echo esc_html( sprintf( '%1$s (#%2$d)', $teacher->display_name, (int) $teacher->ID ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php submit_button( __( 'Load Logs', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
				</form>

				<div class="cmp-log-container">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Timestamp', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Details', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! $selected_teacher_log_id ) : ?>
								<tr><td colspan="6"><?php esc_html_e( 'Choose a teacher to view activity logs.', 'class-manager-pro' ); ?></td></tr>
							<?php elseif ( empty( $teacher_logs ) ) : ?>
								<tr><td colspan="6"><?php esc_html_e( 'No teacher activity recorded yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $teacher_logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( $log->created_at ); ?></td>
										<td><?php echo esc_html( $log->teacher_name ? $log->teacher_name : __( 'Unknown Teacher', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( cmp_get_teacher_log_action_label( $log->action ) ); ?></td>
										<td><?php echo esc_html( $log->batch_name ? $log->batch_name : __( 'Not set', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( $log->student_name ? $log->student_name . ( $log->student_unique_id ? ' (' . $log->student_unique_id . ')' : '' ) : __( 'Not set', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( $log->message ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

	</div>
	<?php
}

/**
 * Detects Razorpay keys from other WordPress plugins.
 *
 * @return array
 */
function cmp_detect_wordpress_razorpay() {
	$detected = array();

	$option_pairs = array(
		'razorpay_key_id'     => array( 'label' => 'Generic Razorpay', 'secret_key' => 'razorpay_key_secret' ),
		'rzp_key_id'          => array( 'label' => 'Razorpay Simple', 'secret_key' => 'rzp_key_secret' ),
		'mp_razorpay_key_id'  => array( 'label' => 'MemberPress', 'secret_key' => 'mp_razorpay_key_secret' ),
		'pms_razorpay_key_id' => array( 'label' => 'Paid Member Subscriptions', 'secret_key' => 'pms_razorpay_key_secret' ),
	);

	foreach ( $option_pairs as $key_option => $config ) {
		$key_id = get_option( $key_option, '' );
		$secret = get_option( $config['secret_key'], '' );

		if ( ! empty( $key_id ) && ! empty( $secret ) ) {
			$detected[ $config['label'] ] = array(
				'key_id' => (string) $key_id,
				'secret' => (string) $secret,
			);
		}
	}

	$array_options = array(
		'woocommerce_razorpay_settings' => 'WooCommerce Razorpay',
		'woo_razorpay_settings'         => 'WooCommerce Razorpay',
	);

	foreach ( $array_options as $option_name => $label ) {
		$settings = get_option( $option_name, array() );

		if ( ! is_array( $settings ) ) {
			continue;
		}

		$key_id = '';
		$secret = '';

		foreach ( array( 'key_id', 'api_key', 'live_key_id' ) as $candidate ) {
			if ( ! empty( $settings[ $candidate ] ) ) {
				$key_id = (string) $settings[ $candidate ];
				break;
			}
		}

		foreach ( array( 'key_secret', 'api_secret', 'live_key_secret' ) as $candidate ) {
			if ( ! empty( $settings[ $candidate ] ) ) {
				$secret = (string) $settings[ $candidate ];
				break;
			}
		}

		if ( '' !== $key_id && '' !== $secret ) {
			$detected[ $label ] = array(
				'key_id' => $key_id,
				'secret' => $secret,
			);
		}
	}

	return $detected;
}
