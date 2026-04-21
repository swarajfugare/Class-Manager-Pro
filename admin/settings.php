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
	$attendance_enabled        = cmp_is_attendance_enabled();
	$default_attendance_status = (string) get_option( 'cmp_default_attendance_status', 'present' );
	$wp_razorpay_keys          = cmp_detect_wordpress_razorpay();
	$credentials               = cmp_get_razorpay_credentials();
	$has_api_credentials       = '' !== $credentials['key_id'] && '' !== $credentials['secret'];
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Settings', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Use this page to connect Razorpay, pull historical data into classes and batches, automate reminders, and control attendance defaults. Class fees now work as defaults only. Live pricing should be saved on each batch.', 'class-manager-pro' ); ?></p>
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
					<h2><?php esc_html_e( 'Import from Razorpay', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'This importer reads captured Razorpay payments only, groups similar paid page names into one class, and creates batches underneath automatically.', 'class-manager-pro' ); ?></p>
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
						<h3><?php esc_html_e( 'One-Time Full Import', 'class-manager-pro' ); ?></h3>
						<p><?php esc_html_e( 'Fetch captured payments from Razorpay in one run. Only pages that have successful paid students are synced into classes and batches.', 'class-manager-pro' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form">
							<input type="hidden" name="action" value="cmp_import_razorpay_data">
							<?php wp_nonce_field( 'cmp_import_razorpay_data' ); ?>
							<?php submit_button( __( 'Import All Razorpay Data', 'class-manager-pro' ), 'primary', 'submit', false ); ?>
						</form>
					</div>
					<div class="cmp-callout">
						<h3><?php esc_html_e( 'Manual Import', 'class-manager-pro' ); ?></h3>
						<p><?php esc_html_e( 'Use the dedicated import page when you want to choose a Razorpay page, preview successful students, and then add them into a selected class and batch.', 'class-manager-pro' ); ?></p>
						<p><a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-razorpay-import' ) ); ?>"><?php esc_html_e( 'Open Razorpay Import Page', 'class-manager-pro' ); ?></a></p>
						<hr>
						<p><?php esc_html_e( 'Advanced: pull one Payment Link or one captured Payment directly by ID.', 'class-manager-pro' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
							<input type="hidden" name="action" value="cmp_manual_import_razorpay">
							<?php wp_nonce_field( 'cmp_manual_import_razorpay' ); ?>
							<div class="cmp-grid cmp-grid-2">
								<label>
									<span><?php esc_html_e( 'Import Type', 'class-manager-pro' ); ?></span>
									<select name="import_type">
										<option value="payment_link"><?php esc_html_e( 'Payment Link', 'class-manager-pro' ); ?></option>
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
					<p class="cmp-muted"><?php esc_html_e( 'Reminders run daily through WP-Cron. You can also fire them manually from here. They use the batch fee due date and only target active students who still have pending fees.', 'class-manager-pro' ); ?></p>
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

		<section class="cmp-panel cmp-danger-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Reset Plugin Data', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'This clears classes, batches, students, payments, attendance, reminders, and temporary intake matches from Class Manager Pro only. Settings and Razorpay keys are kept.', 'class-manager-pro' ); ?></p>
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
