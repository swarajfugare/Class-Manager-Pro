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

	$key_id                    = (string) get_option( 'cmp_razorpay_key_id', '' );
	$secret                    = (string) get_option( 'cmp_razorpay_secret', '' );
	$webhook_secret            = (string) get_option( 'cmp_razorpay_webhook_secret', '' );
	$webhook_url               = rest_url( 'cmp/v1/razorpay-webhook' );
	$notifications_enabled     = '1' === (string) get_option( 'cmp_notifications_enabled', get_option( 'cmp_sms_enabled', '0' ) );
	$notification_provider     = (string) get_option( 'cmp_notification_provider', 'log_only' );
	$notification_webhook_url  = (string) get_option( 'cmp_notification_webhook_url', '' );
	$notification_auth_token   = (string) get_option( 'cmp_notification_auth_token', '' );
	$notification_sender       = (string) get_option( 'cmp_notification_sender', get_option( 'cmp_sms_sender', '' ) );
	$notification_channels     = (string) get_option( 'cmp_notification_channels', 'both' );
	$reminder_days             = (int) get_option( 'cmp_reminder_days', 7 );
	$whatsapp_template         = (string) get_option( 'cmp_whatsapp_template', '' );
	$sms_template              = (string) get_option( 'cmp_sms_template', '' );
	$email_subject             = (string) get_option( 'cmp_email_subject', '' );
	$email_template            = (string) get_option( 'cmp_email_template', '' );
	$attendance_enabled        = cmp_is_attendance_enabled();
	$default_attendance_status = (string) get_option( 'cmp_default_attendance_status', 'present' );
	$automation_sync_enabled   = '1' === (string) get_option( 'cmp_automation_sync_enabled', '0' );
	$automation_sync_interval  = (string) get_option( 'cmp_automation_sync_interval', 'hourly' );
	$automation_sync_lookback_days = (int) get_option( 'cmp_automation_sync_lookback_days', 7 );
	$automation_sync_class_id  = (int) get_option( 'cmp_automation_sync_class_id', 0 );
	$automation_sync_batch_id  = (int) get_option( 'cmp_automation_sync_batch_id', 0 );
	$last_sync_at              = (string) get_option( 'cmp_last_razorpay_sync_at', '' );
	$last_sync_summary         = get_option( 'cmp_last_razorpay_sync_summary', array() );
	$wp_razorpay_keys          = cmp_detect_wordpress_razorpay();
	$credentials               = cmp_get_razorpay_credentials();
	$has_api_credentials       = '' !== $credentials['key_id'] && '' !== $credentials['secret'];
	$classes                   = cmp_get_classes();
	$batches                   = cmp_get_batches();
	$teachers                  = cmp_get_teacher_users();
	$selected_teacher_log_id   = absint( cmp_field( $_GET, 'teacher_user_id', 0 ) );
	$admin_logs                = cmp_get_admin_logs( 100 );
	$teacher_logs              = $selected_teacher_log_id ? cmp_get_teacher_logs( $selected_teacher_log_id, 200 ) : array();
	$students_export_url       = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-students', 'cmp_export' => 'students' ), admin_url( 'admin.php' ) ), 'cmp_export_students' );
	$payments_export_url       = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-payments', 'cmp_export' => 'payments' ), admin_url( 'admin.php' ) ), 'cmp_export_payments' );
	$classes_export_url        = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-classes', 'cmp_export' => 'classes' ), admin_url( 'admin.php' ) ), 'cmp_export_classes' );
	$admin_logs_export_url     = wp_nonce_url( add_query_arg( array( 'page' => 'cmp-settings', 'cmp_export' => 'admin-logs' ), admin_url( 'admin.php' ) ), 'cmp_export_admin-logs' );
	$teacher_logs_export_url   = $selected_teacher_log_id ? wp_nonce_url( add_query_arg( array( 'page' => 'cmp-settings', 'cmp_export' => 'teacher-logs', 'teacher_user_id' => $selected_teacher_log_id ), admin_url( 'admin.php' ) ), 'cmp_export_teacher-logs' ) : '';
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Settings', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Use this page to connect Razorpay, automate reminders, and control attendance defaults. Class fees now work as defaults only. Live pricing should be saved on each batch.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Razorpay Connection', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'If your WordPress site already has Razorpay configured somewhere else, you can pull those keys in here and reuse them.', 'class-manager-pro' ); ?></p>
				</div>
				<?php if ( $has_api_credentials && 'class-manager-pro' !== $credentials['source'] ) : ?>
					<span class="cmp-inline-badge"><?php echo esc_html( sprintf( __( 'Using detected keys from %s', 'class-manager-pro' ), $credentials['source'] ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $wp_razorpay_keys ) ) : ?>
				<div class="cmp-callout cmp-callout-info">
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
						<td><input type="text" id="cmp-razorpay-key-id" name="razorpay_key_id" class="regular-text" value="<?php echo esc_attr( $key_id ); ?>" autocomplete="off"></td>
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
							<p class="description"><?php esc_html_e( 'Enable payment.captured and payment_link.paid for this URL. Failed, cancelled, expired, and authorized-only events are ignored.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Razorpay Settings', 'class-manager-pro' ) ); ?>
			</form>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Automation System', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Automate Razorpay payment sync through WP-Cron so new captured payments are imported without rebuilding your existing plugin flow.', 'class-manager-pro' ); ?></p>
				</div>
				<?php if ( $last_sync_at ) : ?>
					<span class="cmp-inline-badge"><?php echo esc_html( sprintf( __( 'Last sync: %s', 'class-manager-pro' ), $last_sync_at ) ); ?></span>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
				<input type="hidden" name="action" value="cmp_save_automation_settings">
				<?php wp_nonce_field( 'cmp_save_automation_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cmp-automation-sync-enabled"><?php esc_html_e( 'Enable Auto Sync', 'class-manager-pro' ); ?></label></th>
						<td>
							<label><input type="checkbox" id="cmp-automation-sync-enabled" name="automation_sync_enabled" value="1" <?php checked( $automation_sync_enabled, true ); ?>> <?php esc_html_e( 'Automatically sync captured Razorpay payments', 'class-manager-pro' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-automation-sync-interval"><?php esc_html_e( 'Sync Interval', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-automation-sync-interval" name="automation_sync_interval">
								<?php foreach ( cmp_automation_intervals() as $interval_key => $interval_label ) : ?>
									<option value="<?php echo esc_attr( $interval_key ); ?>" <?php selected( $automation_sync_interval, $interval_key ); ?>><?php echo esc_html( $interval_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-automation-sync-lookback"><?php esc_html_e( 'Lookback Window', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-automation-sync-lookback" name="automation_sync_lookback_days">
								<?php foreach ( array( 1, 7, 30, 90 ) as $day_option ) : ?>
									<option value="<?php echo esc_attr( $day_option ); ?>" <?php selected( $automation_sync_lookback_days, $day_option ); ?>><?php echo esc_html( sprintf( _n( '%d day', '%d days', $day_option, 'class-manager-pro' ), $day_option ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'When no exact date range is given, the sync imports captured Razorpay payments from this recent window and skips already stored payment IDs.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-automation-sync-class"><?php esc_html_e( 'Sync Class', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-automation-sync-class" name="automation_sync_class_id" data-cmp-class-select>
								<option value="0"><?php esc_html_e( 'Choose class', 'class-manager-pro' ); ?></option>
								<?php foreach ( $classes as $class ) : ?>
									<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $automation_sync_class_id, (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-automation-sync-batch"><?php esc_html_e( 'Sync Batch', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-automation-sync-batch" name="automation_sync_batch_id" data-cmp-batches>
								<option value="0"><?php esc_html_e( 'Choose batch', 'class-manager-pro' ); ?></option>
								<?php foreach ( $batches as $batch ) : ?>
									<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $automation_sync_batch_id, (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Auto sync imports all captured payments into this exact class and batch instead of creating new classes or batches.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Automation Settings', 'class-manager-pro' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-space-top">
				<input type="hidden" name="action" value="cmp_sync_razorpay_payments">
				<input type="hidden" name="return_page" value="cmp-settings">
				<?php wp_nonce_field( 'cmp_sync_razorpay_payments' ); ?>
				<?php submit_button( __( 'Sync Payments Now', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( ! empty( $last_sync_summary ) && is_array( $last_sync_summary ) ) : ?>
				<div class="cmp-cards cmp-cards-4">
					<div class="cmp-card">
						<span><?php esc_html_e( 'Fetched', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( isset( $last_sync_summary['fetched'] ) ? (int) $last_sync_summary['fetched'] : 0 ) ); ?></strong>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Imported', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( isset( $last_sync_summary['imported'] ) ? (int) $last_sync_summary['imported'] : 0 ) ); ?></strong>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Duplicates', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( isset( $last_sync_summary['duplicate'] ) ? (int) $last_sync_summary['duplicate'] : 0 ) ); ?></strong>
					</div>
					<div class="cmp-card">
						<span><?php esc_html_e( 'Failed', 'class-manager-pro' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( isset( $last_sync_summary['failed'] ) ? (int) $last_sync_summary['failed'] : 0 ) ); ?></strong>
					</div>
				</div>
			<?php endif; ?>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Razorpay Page Import', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Use the dedicated import page to choose a Razorpay page or enter a page ID, preview successful paid students, and add them to a selected batch.', 'class-manager-pro' ); ?></p>
				</div>
				<?php if ( $has_api_credentials ) : ?>
					<span class="cmp-inline-badge cmp-inline-badge-success"><?php esc_html_e( 'API Ready', 'class-manager-pro' ); ?></span>
				<?php else : ?>
					<span class="cmp-inline-badge cmp-inline-badge-warning"><?php esc_html_e( 'Connection Needed', 'class-manager-pro' ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! $has_api_credentials ) : ?>
				<div class="cmp-callout cmp-callout-warning">
					<p><?php esc_html_e( 'Add Razorpay API keys above or import them from an existing WordPress integration before running an import.', 'class-manager-pro' ); ?></p>
				</div>
			<?php else : ?>
				<div class="cmp-grid cmp-grid-2">
					<div class="cmp-callout">
						<h3><?php esc_html_e( 'Manual Import', 'class-manager-pro' ); ?></h3>
						<p><?php esc_html_e( 'Use the dedicated import page when you want to choose a Razorpay page, preview successful students, and then add them into a selected class and batch.', 'class-manager-pro' ); ?></p>
						<p><a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-razorpay-import' ) ); ?>"><?php esc_html_e( 'Open Razorpay Import Page', 'class-manager-pro' ); ?></a></p>
						<hr>
						<p><?php esc_html_e( 'Advanced: pull one Payment Link/Page or one captured Payment directly by ID.', 'class-manager-pro' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
							<input type="hidden" name="action" value="cmp_manual_import_razorpay">
							<?php wp_nonce_field( 'cmp_manual_import_razorpay' ); ?>
							<div class="cmp-grid cmp-grid-2">
								<label>
									<span><?php esc_html_e( 'Import Type', 'class-manager-pro' ); ?></span>
									<select name="import_type">
										<option value="payment_link"><?php esc_html_e( 'Payment Link / Page', 'class-manager-pro' ); ?></option>
										<option value="payment"><?php esc_html_e( 'Captured Payment', 'class-manager-pro' ); ?></option>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Razorpay ID', 'class-manager-pro' ); ?></span>
									<input type="text" name="razorpay_id" class="regular-text" placeholder="plink_xxx or pay_xxx">
								</label>
							</div>
							<?php submit_button( __( 'Import Single Item', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				</div>
			<?php endif; ?>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'WhatsApp / SMS Reminders', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Reminders run daily through WP-Cron. You can also fire them manually from here. They use the batch fee due date and can send by SMS, WhatsApp, and email.', 'class-manager-pro' ); ?></p>
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
						<th scope="row"><label for="cmp-notification-provider"><?php esc_html_e( 'Delivery Mode', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-notification-provider" name="notification_provider">
								<option value="log_only" <?php selected( $notification_provider, 'log_only' ); ?>><?php esc_html_e( 'Log Only (No External Send)', 'class-manager-pro' ); ?></option>
								<option value="custom_webhook" <?php selected( $notification_provider, 'custom_webhook' ); ?>><?php esc_html_e( 'Custom SMS / WhatsApp Webhook', 'class-manager-pro' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Use the custom webhook option if you already have an SMS or WhatsApp gateway connected to your site or server.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-notification-webhook-url"><?php esc_html_e( 'Webhook URL', 'class-manager-pro' ); ?></label></th>
						<td><input type="url" id="cmp-notification-webhook-url" name="notification_webhook_url" class="large-text" value="<?php echo esc_attr( $notification_webhook_url ); ?>" placeholder="https://your-provider.example/send"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-notification-auth-token"><?php esc_html_e( 'Webhook Token', 'class-manager-pro' ); ?></label></th>
						<td><input type="password" id="cmp-notification-auth-token" name="notification_auth_token" class="regular-text" value="<?php echo esc_attr( $notification_auth_token ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-sms-sender"><?php esc_html_e( 'Sender Label', 'class-manager-pro' ); ?></label></th>
						<td><input type="text" id="cmp-sms-sender" name="sms_sender" class="regular-text" value="<?php echo esc_attr( $notification_sender ); ?>" placeholder="CMP, School, Institute"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-notification-channels"><?php esc_html_e( 'Channels', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-notification-channels" name="notification_channels">
								<option value="both" <?php selected( $notification_channels, 'both' ); ?>><?php esc_html_e( 'SMS and WhatsApp', 'class-manager-pro' ); ?></option>
								<option value="sms" <?php selected( $notification_channels, 'sms' ); ?>><?php esc_html_e( 'SMS Only', 'class-manager-pro' ); ?></option>
								<option value="whatsapp" <?php selected( $notification_channels, 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp Only', 'class-manager-pro' ); ?></option>
								<option value="email" <?php selected( $notification_channels, 'email' ); ?>><?php esc_html_e( 'Email Only', 'class-manager-pro' ); ?></option>
								<option value="sms_email" <?php selected( $notification_channels, 'sms_email' ); ?>><?php esc_html_e( 'SMS and Email', 'class-manager-pro' ); ?></option>
								<option value="all" <?php selected( $notification_channels, 'all' ); ?>><?php esc_html_e( 'SMS, WhatsApp, and Email', 'class-manager-pro' ); ?></option>
							</select>
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
						<th scope="row"><label for="cmp-sms-template"><?php esc_html_e( 'SMS Template', 'class-manager-pro' ); ?></label></th>
						<td><textarea id="cmp-sms-template" name="sms_template" rows="4" class="large-text"><?php echo esc_textarea( $sms_template ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-whatsapp-template"><?php esc_html_e( 'WhatsApp Template', 'class-manager-pro' ); ?></label></th>
						<td>
							<textarea id="cmp-whatsapp-template" name="whatsapp_template" rows="4" class="large-text"><?php echo esc_textarea( $whatsapp_template ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Available placeholders: {student_name}, {class_name}, {batch_name}, {pending_fee}, {due_date}, {payment_link}', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-email-subject"><?php esc_html_e( 'Email Subject', 'class-manager-pro' ); ?></label></th>
						<td><input type="text" id="cmp-email-subject" name="email_subject" class="large-text" value="<?php echo esc_attr( $email_subject ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-email-template"><?php esc_html_e( 'Email Template', 'class-manager-pro' ); ?></label></th>
						<td>
							<textarea id="cmp-email-template" name="email_template" rows="5" class="large-text"><?php echo esc_textarea( $email_template ); ?></textarea>
							<p class="description"><?php esc_html_e( 'The same placeholders work in email reminders too.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Reminder Settings', 'class-manager-pro' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-space-top">
				<input type="hidden" name="action" value="cmp_send_fee_reminders">
				<?php wp_nonce_field( 'cmp_send_fee_reminders' ); ?>
				<?php submit_button( __( 'Send Due Reminders Now', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Attendance Defaults', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Attendance is managed per batch workspace. These settings control whether the attendance sheet is visible and what status new rows start with.', 'class-manager-pro' ); ?></p>
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
						<th scope="row"><label for="cmp-default-attendance-status"><?php esc_html_e( 'Default Status', 'class-manager-pro' ); ?></label></th>
						<td>
							<select id="cmp-default-attendance-status" name="default_attendance_status">
								<?php foreach ( cmp_attendance_statuses() as $status ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $default_attendance_status, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Attendance Settings', 'class-manager-pro' ) ); ?>
			</form>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Backup & Export', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Export the core plugin records as CSV backups.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<div class="cmp-toolbar">
				<a class="button" href="<?php echo esc_url( $classes_export_url ); ?>"><?php esc_html_e( 'Export Classes CSV', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( $students_export_url ); ?>"><?php esc_html_e( 'Export Students CSV', 'class-manager-pro' ); ?></a>
				<a class="button" href="<?php echo esc_url( $payments_export_url ); ?>"><?php esc_html_e( 'Export Payments CSV', 'class-manager-pro' ); ?></a>
			</div>
			<p class="description"><?php echo esc_html( sprintf( __( 'Plugin errors and webhook issues are logged to: %s', 'class-manager-pro' ), cmp_get_log_file_path() ) ); ?></p>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Admin Activity Logs', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Review recent admin actions across classes, batches, students, and payments.', 'class-manager-pro' ); ?></p>
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

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Teacher Activity Logs', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Track batch and student views performed from the teacher console.', 'class-manager-pro' ); ?></p>
				</div>
				<?php if ( $teacher_logs_export_url ) : ?>
					<a class="button" href="<?php echo esc_url( $teacher_logs_export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
				<?php endif; ?>
			</div>

			<form method="get" class="cmp-inline-form">
				<input type="hidden" name="page" value="cmp-settings">
				<label for="cmp-teacher-log-user">
					<span><?php esc_html_e( 'Select Teacher', 'class-manager-pro' ); ?></span>
					<select id="cmp-teacher-log-user" name="teacher_user_id">
						<option value="0"><?php esc_html_e( 'Choose teacher', 'class-manager-pro' ); ?></option>
						<?php foreach ( $teachers as $teacher ) : ?>
							<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( $selected_teacher_log_id, (int) $teacher->ID ); ?>><?php echo esc_html( sprintf( '%1$s (#%2$d)', $teacher->display_name, (int) $teacher->ID ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php submit_button( __( 'Load Logs', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
			</form>

			<div class="cmp-log-container cmp-space-top">
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

		<section class="cmp-panel cmp-danger-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Reset Plugin Data', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'This clears classes, batches, students, payments, expenses, attendance, reminders, and temporary intake matches from Class Manager Pro only. Settings and Razorpay keys are kept.', 'class-manager-pro' ); ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-confirm="<?php esc_attr_e( 'Delete all Class Manager Pro data? This cannot be undone.', 'class-manager-pro' ); ?>">
				<input type="hidden" name="action" value="cmp_reset_plugin_data">
				<?php wp_nonce_field( 'cmp_reset_plugin_data' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cmp-reset-confirmation"><?php esc_html_e( 'Confirm Reset', 'class-manager-pro' ); ?></label></th>
						<td>
							<input type="text" id="cmp-reset-confirmation" name="reset_confirmation" class="regular-text" placeholder="DELETE" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Type DELETE and click the button below to make the plugin database new again.', 'class-manager-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Delete All Plugin Data', 'class-manager-pro' ), 'delete' ); ?>
			</form>
		</section>
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
