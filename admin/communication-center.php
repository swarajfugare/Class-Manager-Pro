<?php
/**
 * Communication Center admin pages.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the current Communication Center section.
 *
 * @return string
 */
function cmp_communication_current_section() {
	return cmp_communication_section_from_page( cmp_field( $_GET, 'page', 'cmp-communication-center' ) );
}

/**
 * Returns a section heading.
 *
 * @param string $section Section key.
 * @return array
 */
function cmp_communication_section_heading( $section ) {
	$map = array(
		'dashboard'   => array( __( 'Communication Center', 'class-manager-pro' ), __( 'Unified email, WhatsApp, campaign, and automation controls for enterprise admissions.', 'class-manager-pro' ) ),
		'email'       => array( __( 'Email Center', 'class-manager-pro' ), __( 'Compose announcements, welcome emails, reminders, and scheduled broadcasts.', 'class-manager-pro' ) ),
		'whatsapp'    => array( __( 'WhatsApp Center', 'class-manager-pro' ), __( 'Generate professional prefilled WhatsApp messages for batches, classes, teachers, and leads.', 'class-manager-pro' ) ),
		'campaigns'   => array( __( 'Campaign Manager', 'class-manager-pro' ), __( 'Plan, schedule, run, and cancel communication campaigns with clear status tracking.', 'class-manager-pro' ) ),
		'templates'   => array( __( 'Templates', 'class-manager-pro' ), __( 'Save reusable HTML or text templates with version history and preview support.', 'class-manager-pro' ) ),
		'automation'  => array( __( 'Automation', 'class-manager-pro' ), __( 'Trigger welcome, fee reminder, and completion flows from registration and payment events.', 'class-manager-pro' ) ),
		'history'     => array( __( 'History', 'class-manager-pro' ), __( 'Searchable delivery history and notification logs for every important communication.', 'class-manager-pro' ) ),
		'statistics'  => array( __( 'Statistics', 'class-manager-pro' ), __( 'Measure delivery rate, open rate, campaign performance, and channel usage.', 'class-manager-pro' ) ),
		'settings'    => array( __( 'Settings', 'class-manager-pro' ), __( 'Tune sender details, signatures, and communication defaults.', 'class-manager-pro' ) ),
	);

	return isset( $map[ $section ] ) ? $map[ $section ] : $map['dashboard'];
}

/**
 * Returns a section shell.
 *
 * @param string $section Section key.
 * @param string $inner_html Inner HTML.
 * @return string
 */
function cmp_communication_shell_markup( $section, $inner_html ) {
	list( $title, $subtitle ) = cmp_communication_section_heading( $section );

	ob_start();
	?>
	<div class="wrap cmp-wrap cmp-communication-shell">
		<h1><?php echo esc_html( $title ); ?></h1>
		<p class="cmp-page-intro"><?php echo esc_html( $subtitle ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-communication-center' ); ?>
		<?php cmp_render_workflow_tabs( cmp_communication_section_tabs(), $section, __( 'Communication Center sections', 'class-manager-pro' ) ); ?>
		<?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Formats a datetime value for the admin screens.
 *
 * @param string $value Datetime.
 * @return string
 */
function cmp_communication_format_datetime( $value ) {
	if ( '' === trim( (string) $value ) ) {
		return '—';
	}

	return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $value ) );
}

/**
 * Returns a recipient preview list for a scope.
 *
 * @param string $scope Scope key.
 * @param array  $args Args.
 * @return array
 */
function cmp_communication_preview_recipients( $scope, $args = array() ) {
	$recipients = cmp_communication_get_recipients( $scope, $args );

	return is_array( $recipients ) ? $recipients : array();
}

/**
 * Renders a recipient preview table.
 *
 * @param array  $recipients Recipient list.
 * @param string $empty_message Empty state message.
 * @return string
 */
function cmp_communication_render_recipient_table( $recipients, $empty_message ) {
	ob_start();
	?>
	<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Context', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recipients ) ) : ?>
					<tr><td colspan="5"><?php echo esc_html( $empty_message ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $recipients as $recipient ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $recipient->name ); ?></strong>
								<br><span class="cmp-muted"><?php echo esc_html( ucfirst( $recipient->recipient_type ) ); ?></span>
							</td>
							<td><?php echo esc_html( isset( $recipient->email ) ? $recipient->email : '—' ); ?></td>
							<td><?php echo esc_html( isset( $recipient->phone ) ? $recipient->phone : '—' ); ?></td>
							<td><?php echo esc_html( trim( ( isset( $recipient->class_name ) ? $recipient->class_name : '' ) . ' ' . ( isset( $recipient->batch_name ) ? $recipient->batch_name : '' ) ) ); ?></td>
							<td>
								<?php if ( ! empty( $recipient->phone ) ) : ?>
									<?php $wa_url = cmp_communication_build_whatsapp_url( $recipient->phone, cmp_build_fee_reminder_message( $recipient, 'whatsapp' ) ); ?>
									<a class="button button-small" href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open WhatsApp', 'class-manager-pro' ); ?></a>
								<?php else : ?>
									<span class="cmp-muted">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Renders a communication dashboard body.
 *
 * @return string
 */
function cmp_communication_render_dashboard_body() {
	$summary   = cmp_get_communication_dashboard_summary();
	$messages  = cmp_get_communication_history( array( 'limit' => 8 ) );
	$campaigns = cmp_get_communication_campaigns( array( 'limit' => 5 ) );
	$templates = cmp_get_communication_templates( array( 'limit' => 5 ) );
	$stats     = $summary['stats'];

	ob_start();
	?>
		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card"><span><?php esc_html_e( 'Emails Sent', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['email_sent'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'WhatsApp Sent', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['whatsapp_sent'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Delivery Rate', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $stats['delivery_rate'], 2 ) ); ?>%</strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Open Rate', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $stats['open_rate'], 2 ) ); ?>%</strong></div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Recent Delivery History', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'The latest sent, failed, and queued communication entries.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-history' ) ); ?>"><?php esc_html_e( 'View All', 'class-manager-pro' ); ?></a>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Recipient', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $messages ) ) : ?>
								<tr><td colspan="3"><?php echo esc_html( cmp_communication_empty_state() ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $messages as $message ) : ?>
									<tr>
										<td><?php echo esc_html( $message->recipient_name ); ?><br><span class="cmp-muted"><?php echo esc_html( $message->subject ); ?></span></td>
										<td><?php echo esc_html( ucfirst( $message->channel ) ); ?></td>
										<td><span class="cmp-status cmp-status-<?php echo esc_attr( sanitize_key( $message->delivery_status ) ); ?>"><?php echo esc_html( cmp_communication_status_label( $message->delivery_status ) ); ?></span></td>
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
						<h2><?php esc_html_e( 'Campaign Queue', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Draft, scheduled, completed, and cancelled campaigns live here.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-campaigns' ) ); ?>"><?php esc_html_e( 'Open Campaigns', 'class-manager-pro' ); ?></a>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $campaigns ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No campaigns yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $campaigns as $campaign ) : ?>
									<tr>
										<td><?php echo esc_html( $campaign->title ); ?></td>
										<td><?php echo esc_html( ucfirst( $campaign->channel ) ); ?></td>
										<td><?php echo esc_html( cmp_communication_status_label( $campaign->status ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Saved Templates', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Reusable HTML and WhatsApp-ready templates with version history.', 'class-manager-pro' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-templates' ) ); ?>"><?php esc_html_e( 'Manage Templates', 'class-manager-pro' ); ?></a>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Template', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Type', 'class-manager-pro' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $templates ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No templates yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $templates as $template ) : ?>
									<tr>
										<td><?php echo esc_html( $template->template_name ); ?></td>
										<td><?php echo esc_html( ucfirst( $template->channel ) ); ?></td>
										<td><?php echo esc_html( $template->template_type ); ?></td>
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
						<h2><?php esc_html_e( 'Quick Links', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Jump straight into the message composer that fits the current job.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-toolbar cmp-toolbar-wrap">
					<a class="button button-primary" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-email' ) ); ?>"><?php esc_html_e( 'Compose Email', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-whatsapp' ) ); ?>"><?php esc_html_e( 'Open WhatsApp Center', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-campaigns' ) ); ?>"><?php esc_html_e( 'Create Campaign', 'class-manager-pro' ); ?></a>
					<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-communication-center-statistics' ) ); ?>"><?php esc_html_e( 'View Statistics', 'class-manager-pro' ); ?></a>
				</div>
			</section>
		</div>
	<?php

	return ob_get_clean();
}

/**
 * Renders the overview page.
 *
 * @return void
 */
function cmp_render_communication_center_page() {
	cmp_require_communication_center_access();
	echo cmp_communication_shell_markup( 'dashboard', cmp_communication_render_dashboard_body() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders a compose form.
 *
 * @param string $section Section.
 * @param string $channel Channel.
 * @return void
 */
function cmp_render_communication_compose_form( $section, $channel ) {
	$templates = cmp_get_communication_templates( array( 'channel' => $channel, 'status' => 'active', 'limit' => 50 ) );
	$classes   = cmp_get_classes();
	$batches   = cmp_get_batches();
	$teachers  = cmp_get_teacher_users();
	$recipients = cmp_communication_preview_recipients( 'pending_fee', array( 'limit' => 8 ) );
	?>
	<section class="cmp-panel">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="<?php echo esc_attr( 'cmp_save_communication_campaign_ajax' ); ?>">
			<input type="hidden" name="action" value="cmp_save_communication_campaign">
			<input type="hidden" name="channel" value="<?php echo esc_attr( $channel ); ?>">
			<input type="hidden" name="campaign_type" value="<?php echo esc_attr( 'whatsapp' === $channel ? 'whatsapp' : 'email' ); ?>">
			<?php wp_nonce_field( 'cmp_save_communication_campaign' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cmp-communication-title"><?php esc_html_e( 'Campaign Title', 'class-manager-pro' ); ?></label></th>
					<td><input type="text" id="cmp-communication-title" name="title" class="regular-text" value="" placeholder="<?php esc_attr_e( 'Admission Welcome Batch 2026', 'class-manager-pro' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cmp-communication-subject"><?php esc_html_e( 'Subject', 'class-manager-pro' ); ?></label></th>
					<td><input type="text" id="cmp-communication-subject" name="subject" class="large-text" value=""></td>
				</tr>
				<tr>
					<th scope="row"><label for="cmp-communication-template"><?php esc_html_e( 'Template', 'class-manager-pro' ); ?></label></th>
					<td>
						<select id="cmp-communication-template" name="template_id" data-cmp-searchable="1">
							<option value="0"><?php esc_html_e( 'No template', 'class-manager-pro' ); ?></option>
							<?php foreach ( $templates as $template ) : ?>
								<option value="<?php echo esc_attr( (int) $template->id ); ?>"><?php echo esc_html( $template->template_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cmp-communication-message"><?php esc_html_e( 'Message', 'class-manager-pro' ); ?></label></th>
					<td>
						<textarea id="cmp-communication-message" name="message" rows="8" class="large-text"><?php echo esc_textarea( cmp_communication_default_templates()[0]['body'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {{name}}, {{amount}}, {{course}}, {{batch}}, {{due_date}}, {{support_contact}}, {{sender_name}}.', 'class-manager-pro' ); ?></p>
						<p class="cmp-inline-tools">
							<button type="button" class="button" data-cmp-template-preview="cmp-communication-message" data-cmp-preview-target="#cmp-communication-preview"><?php esc_html_e( 'Preview', 'class-manager-pro' ); ?></button>
						</p>
						<div id="cmp-communication-preview" class="cmp-template-preview"></div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Recipients', 'class-manager-pro' ); ?></th>
					<td>
						<select name="recipient_scope" data-cmp-searchable="1">
							<option value="all"><?php esc_html_e( 'All Students', 'class-manager-pro' ); ?></option>
							<option value="pending_fee"><?php esc_html_e( 'Pending Fee', 'class-manager-pro' ); ?></option>
							<option value="batch"><?php esc_html_e( 'Batch Wise', 'class-manager-pro' ); ?></option>
							<option value="class"><?php esc_html_e( 'Class Wise', 'class-manager-pro' ); ?></option>
							<option value="teacher"><?php esc_html_e( 'Teacher Wise', 'class-manager-pro' ); ?></option>
							<option value="interested"><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></option>
							<option value="selected"><?php esc_html_e( 'Selected IDs', 'class-manager-pro' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'For batch/class/teacher scope, add the corresponding ID below. For selected IDs, separate values with commas.', 'class-manager-pro' ); ?></p>
						<div class="cmp-grid cmp-grid-3">
							<div><label><?php esc_html_e( 'Batch ID', 'class-manager-pro' ); ?><input type="number" name="batch_id" min="0" value="0"></label></div>
							<div><label><?php esc_html_e( 'Class ID', 'class-manager-pro' ); ?><input type="number" name="class_id" min="0" value="0"></label></div>
							<div><label><?php esc_html_e( 'Teacher ID', 'class-manager-pro' ); ?><input type="number" name="teacher_user_id" min="0" value="0"></label></div>
						</div>
						<p><label><?php esc_html_e( 'Selected IDs', 'class-manager-pro' ); ?><input type="text" name="recipient_ids" class="large-text" value="" placeholder="<?php esc_attr_e( '1, 4, 8', 'class-manager-pro' ); ?>"></label></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cmp-communication-status"><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></label></th>
					<td>
						<select id="cmp-communication-status" name="status">
							<option value="draft"><?php esc_html_e( 'Draft', 'class-manager-pro' ); ?></option>
							<option value="scheduled"><?php esc_html_e( 'Scheduled', 'class-manager-pro' ); ?></option>
							<option value="completed"><?php esc_html_e( 'Completed', 'class-manager-pro' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cmp-communication-scheduled-at"><?php esc_html_e( 'Schedule', 'class-manager-pro' ); ?></label></th>
					<td><input type="datetime-local" id="cmp-communication-scheduled-at" name="scheduled_at" value=""></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Campaign', 'class-manager-pro' ) ); ?>
		</form>
	</section>

	<section class="cmp-grid cmp-grid-2 cmp-space-top">
		<div class="cmp-panel">
			<h2><?php esc_html_e( 'Recipient Preview', 'class-manager-pro' ); ?></h2>
			<?php echo cmp_communication_render_recipient_table( $recipients, __( 'Use the recipient scope above to preview a few default recipients.', 'class-manager-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="cmp-panel">
			<h2><?php esc_html_e( 'Context Sources', 'class-manager-pro' ); ?></h2>
			<div class="cmp-table-scroll cmp-table-scroll-y">
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Type', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Count', 'class-manager-pro' ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Classes', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( count( $classes ) ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( count( $batches ) ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Teachers', 'class-manager-pro' ); ?></td><td><?php echo esc_html( number_format_i18n( count( $teachers ) ) ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Renders the Email Center.
 *
 * @return void
 */
function cmp_render_communication_email_page() {
	cmp_require_communication_center_access();

	ob_start();
	cmp_render_communication_compose_form( 'email', 'email' );
	$html = ob_get_clean();
	echo cmp_communication_shell_markup( 'email', $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders the WhatsApp Center.
 *
 * @return void
 */
function cmp_render_communication_whatsapp_page() {
	cmp_require_communication_center_access();

	$recipients = cmp_communication_preview_recipients( 'pending_fee', array( 'limit' => 12 ) );
	ob_start();
	?>
	<section class="cmp-panel">
		<div class="cmp-panel-header">
			<div>
				<h2><?php esc_html_e( 'WhatsApp Composer', 'class-manager-pro' ); ?></h2>
				<p class="cmp-muted"><?php esc_html_e( 'Create a message, then open WhatsApp with a prefilled professional prompt.', 'class-manager-pro' ); ?></p>
			</div>
		</div>
		<?php cmp_render_communication_compose_form( 'whatsapp', 'whatsapp' ); ?>
	</section>
	<section class="cmp-panel cmp-space-top">
		<h2><?php esc_html_e( 'Prefilled Links', 'class-manager-pro' ); ?></h2>
		<?php echo cmp_communication_render_recipient_table( $recipients, __( 'No recipients available for the current preview.', 'class-manager-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'whatsapp', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders campaigns page.
 *
 * @return void
 */
function cmp_render_communication_campaigns_page() {
	cmp_require_communication_center_access();

	$campaigns = cmp_get_communication_campaigns( array( 'limit' => 50 ) );
	ob_start();
	?>
	<section class="cmp-panel">
		<h2><?php esc_html_e( 'Campaign Manager', 'class-manager-pro' ); ?></h2>
		<p class="cmp-muted"><?php esc_html_e( 'Create draft, scheduled, or completed campaigns and run them when ready.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_communication_compose_form( 'campaigns', 'email' ); ?>
	</section>
	<section class="cmp-panel cmp-space-top">
		<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Sent / Failed', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Schedule', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $campaigns ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No campaigns yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $campaigns as $campaign ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $campaign->title ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( $campaign->subject ); ?></span></td>
								<td><?php echo esc_html( ucfirst( $campaign->channel ) ); ?></td>
								<td><span class="cmp-status cmp-status-<?php echo esc_attr( sanitize_key( $campaign->status ) ); ?>"><?php echo esc_html( cmp_communication_status_label( $campaign->status ) ); ?></span></td>
								<td><?php echo esc_html( number_format_i18n( (int) $campaign->sent_count ) ); ?> / <?php echo esc_html( number_format_i18n( (int) $campaign->failed_count ) ); ?></td>
								<td><?php echo esc_html( cmp_communication_format_datetime( $campaign->scheduled_at ) ); ?></td>
								<td>
									<?php if ( 'cancelled' !== $campaign->status ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-inline-form-stack" data-cmp-ajax-form="cmp_run_communication_campaign_ajax">
											<input type="hidden" name="action" value="cmp_run_communication_campaign">
											<input type="hidden" name="id" value="<?php echo esc_attr( (int) $campaign->id ); ?>">
											<?php wp_nonce_field( 'cmp_run_communication_campaign_' . (int) $campaign->id ); ?>
											<?php submit_button( __( 'Run Now', 'class-manager-pro' ), 'primary', 'submit', false ); ?>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-inline-form-stack" data-cmp-ajax-form="cmp_cancel_communication_campaign_ajax">
											<input type="hidden" name="action" value="cmp_cancel_communication_campaign">
											<input type="hidden" name="id" value="<?php echo esc_attr( (int) $campaign->id ); ?>">
											<?php wp_nonce_field( 'cmp_cancel_communication_campaign_' . (int) $campaign->id ); ?>
											<?php submit_button( __( 'Cancel', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
										</form>
									<?php else : ?>
										<span class="cmp-muted"><?php esc_html_e( 'Cancelled', 'class-manager-pro' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'campaigns', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders templates page.
 *
 * @return void
 */
function cmp_render_communication_templates_page() {
	cmp_require_communication_center_access();

	$templates = cmp_get_communication_templates( array( 'limit' => 50 ) );
	ob_start();
	?>
	<section class="cmp-panel">
		<h2><?php esc_html_e( 'Template Builder', 'class-manager-pro' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="cmp_save_communication_template_ajax">
			<input type="hidden" name="action" value="cmp_save_communication_template">
			<?php wp_nonce_field( 'cmp_save_communication_template' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="cmp-template-name"><?php esc_html_e( 'Template Name', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-template-name" name="template_name" class="regular-text" value=""></td></tr>
				<tr><th scope="row"><label for="cmp-template-type"><?php esc_html_e( 'Type', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-template-type" name="template_type" class="regular-text" value="general"></td></tr>
				<tr><th scope="row"><label for="cmp-template-channel"><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></label></th><td><select id="cmp-template-channel" name="channel"><option value="email"><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></option><option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="cmp-template-subject"><?php esc_html_e( 'Subject', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-template-subject" name="subject" class="large-text" value=""></td></tr>
				<tr><th scope="row"><label for="cmp-template-body"><?php esc_html_e( 'Body', 'class-manager-pro' ); ?></label></th><td><textarea id="cmp-template-body" name="body" rows="8" class="large-text"></textarea><p class="description"><?php esc_html_e( 'Supports HTML and variables such as {{name}}, {{batch_name}}, {{amount}}, {{signature}}.', 'class-manager-pro' ); ?></p><p class="cmp-inline-tools"><button type="button" class="button" data-cmp-template-preview="cmp-template-body" data-cmp-preview-target="#cmp-template-preview"><?php esc_html_e( 'Preview', 'class-manager-pro' ); ?></button></p><div id="cmp-template-preview" class="cmp-template-preview"></div></td></tr>
				<tr><th scope="row"><label for="cmp-template-variables"><?php esc_html_e( 'Variables', 'class-manager-pro' ); ?></label></th><td><textarea id="cmp-template-variables" name="variables" rows="3" class="large-text"></textarea></td></tr>
			</table>
			<?php submit_button( __( 'Save Template', 'class-manager-pro' ) ); ?>
		</form>
	</section>
	<section class="cmp-panel cmp-space-top">
		<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Type', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $templates ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No templates yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $templates as $template ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $template->template_name ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( $template->subject ); ?></span></td>
								<td><?php echo esc_html( ucfirst( $template->channel ) ); ?></td>
								<td><?php echo esc_html( $template->template_type ); ?></td>
								<td><?php echo esc_html( cmp_communication_status_label( $template->status ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-inline-form-stack" data-cmp-ajax-form="cmp_delete_communication_template_ajax">
										<input type="hidden" name="action" value="cmp_delete_communication_template">
										<input type="hidden" name="id" value="<?php echo esc_attr( (int) $template->id ); ?>">
										<?php wp_nonce_field( 'cmp_delete_communication_template_' . (int) $template->id ); ?>
										<?php submit_button( __( 'Archive', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'templates', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders automation page.
 *
 * @return void
 */
function cmp_render_communication_automation_page() {
	cmp_require_communication_center_access();

	$rules = cmp_get_communication_automation_rules( array( 'limit' => 50 ) );
	$templates = cmp_get_communication_templates( array( 'limit' => 50 ) );
	ob_start();
	?>
	<section class="cmp-panel">
		<h2><?php esc_html_e( 'Automation Engine', 'class-manager-pro' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="cmp_save_communication_automation_ajax">
			<input type="hidden" name="action" value="cmp_save_communication_automation">
			<?php wp_nonce_field( 'cmp_save_communication_automation' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="cmp-rule-name"><?php esc_html_e( 'Rule Name', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-rule-name" name="rule_name" class="regular-text" value=""></td></tr>
				<tr><th scope="row"><label for="cmp-trigger-key"><?php esc_html_e( 'Trigger', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-trigger-key" name="trigger_key" class="regular-text" value="student_registered" placeholder="<?php esc_attr_e( 'student_registered', 'class-manager-pro' ); ?>"></td></tr>
				<tr><th scope="row"><label for="cmp-action-type"><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-action-type" name="action_type" class="regular-text" value="send_email" placeholder="<?php esc_attr_e( 'send_email', 'class-manager-pro' ); ?>"></td></tr>
				<tr><th scope="row"><label for="cmp-rule-template"><?php esc_html_e( 'Template', 'class-manager-pro' ); ?></label></th><td><select id="cmp-rule-template" name="template_id"><option value="0"><?php esc_html_e( 'None', 'class-manager-pro' ); ?></option><?php foreach ( $templates as $template ) : ?><option value="<?php echo esc_attr( (int) $template->id ); ?>"><?php echo esc_html( $template->template_name ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th scope="row"><label for="cmp-rule-scope"><?php esc_html_e( 'Recipient Scope', 'class-manager-pro' ); ?></label></th><td><select id="cmp-rule-scope" name="recipient_scope"><option value="student"><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></option><option value="teacher"><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></option><option value="admin"><?php esc_html_e( 'Admin', 'class-manager-pro' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="cmp-rule-channel"><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></label></th><td><select id="cmp-rule-channel" name="channel"><option value="email"><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></option><option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="cmp-rule-status"><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></label></th><td><select id="cmp-rule-status" name="status"><option value="active"><?php esc_html_e( 'Active', 'class-manager-pro' ); ?></option><option value="paused"><?php esc_html_e( 'Paused', 'class-manager-pro' ); ?></option><option value="archived"><?php esc_html_e( 'Archived', 'class-manager-pro' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="cmp-rule-conditions"><?php esc_html_e( 'Conditions', 'class-manager-pro' ); ?></label></th><td><textarea id="cmp-rule-conditions" name="conditions" rows="4" class="large-text" placeholder="<?php esc_attr_e( '{"course":"Math"}', 'class-manager-pro' ); ?>"></textarea></td></tr>
				<tr><th scope="row"><label for="cmp-rule-config"><?php esc_html_e( 'Action Config', 'class-manager-pro' ); ?></label></th><td><textarea id="cmp-rule-config" name="action_config" rows="4" class="large-text" placeholder="<?php esc_attr_e( '{"delay":"0"}', 'class-manager-pro' ); ?>"></textarea></td></tr>
			</table>
			<?php submit_button( __( 'Save Rule', 'class-manager-pro' ) ); ?>
		</form>
	</section>
	<section class="cmp-panel cmp-space-top">
		<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Rule', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Trigger', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Action', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No automation rules yet.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rules as $rule ) : ?>
							<tr>
								<td><?php echo esc_html( $rule->rule_name ); ?></td>
								<td><?php echo esc_html( $rule->trigger_key ); ?></td>
								<td><?php echo esc_html( $rule->action_type ); ?></td>
								<td><?php echo esc_html( cmp_communication_status_label( $rule->status ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-inline-form cmp-inline-form-stack" data-cmp-ajax-form="cmp_delete_communication_automation_ajax">
										<input type="hidden" name="action" value="cmp_delete_communication_automation">
										<input type="hidden" name="id" value="<?php echo esc_attr( (int) $rule->id ); ?>">
										<?php wp_nonce_field( 'cmp_delete_communication_automation_' . (int) $rule->id ); ?>
										<?php submit_button( __( 'Archive', 'class-manager-pro' ), 'secondary', 'submit', false ); ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'automation', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders history page.
 *
 * @return void
 */
function cmp_render_communication_history_page() {
	cmp_require_communication_center_access();

	$filters = array(
		'search'         => sanitize_text_field( cmp_field( $_GET, 'search', '' ) ),
		'channel'        => sanitize_key( cmp_field( $_GET, 'channel', '' ) ),
		'status'         => sanitize_key( cmp_field( $_GET, 'status', '' ) ),
		'recipient_type' => sanitize_key( cmp_field( $_GET, 'recipient_type', '' ) ),
		'priority'       => sanitize_key( cmp_field( $_GET, 'priority', '' ) ),
	);
	$messages = cmp_get_communication_messages( array_merge( $filters, array( 'limit' => 100 ) ) );
	ob_start();
	?>
	<section class="cmp-panel">
		<form method="get" class="cmp-toolbar cmp-filter-form">
			<input type="hidden" name="page" value="cmp-communication-center-history">
			<input type="search" name="search" class="regular-text" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, subject', 'class-manager-pro' ); ?>">
			<select name="channel"><option value=""><?php esc_html_e( 'All Channels', 'class-manager-pro' ); ?></option><option value="email" <?php selected( $filters['channel'], 'email' ); ?>><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></option><option value="whatsapp" <?php selected( $filters['channel'], 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></option></select>
			<select name="status"><option value=""><?php esc_html_e( 'All Statuses', 'class-manager-pro' ); ?></option><option value="sent" <?php selected( $filters['status'], 'sent' ); ?>><?php esc_html_e( 'Sent', 'class-manager-pro' ); ?></option><option value="failed" <?php selected( $filters['status'], 'failed' ); ?>><?php esc_html_e( 'Failed', 'class-manager-pro' ); ?></option><option value="pending" <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></option></select>
			<select name="recipient_type"><option value=""><?php esc_html_e( 'All Recipients', 'class-manager-pro' ); ?></option><option value="student" <?php selected( $filters['recipient_type'], 'student' ); ?>><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></option><option value="lead" <?php selected( $filters['recipient_type'], 'lead' ); ?>><?php esc_html_e( 'Lead', 'class-manager-pro' ); ?></option><option value="teacher" <?php selected( $filters['recipient_type'], 'teacher' ); ?>><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></option><option value="admin" <?php selected( $filters['recipient_type'], 'admin' ); ?>><?php esc_html_e( 'Admin', 'class-manager-pro' ); ?></option></select>
			<select name="priority"><option value=""><?php esc_html_e( 'All Priorities', 'class-manager-pro' ); ?></option><option value="normal" <?php selected( $filters['priority'], 'normal' ); ?>><?php esc_html_e( 'Normal', 'class-manager-pro' ); ?></option><option value="high" <?php selected( $filters['priority'], 'high' ); ?>><?php esc_html_e( 'High', 'class-manager-pro' ); ?></option></select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
		</form>
	</section>
	<section class="cmp-panel cmp-space-top">
		<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Recipient', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Channel', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Subject', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th><th><?php esc_html_e( 'Created', 'class-manager-pro' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $messages ) ) : ?>
						<tr><td colspan="5"><?php echo esc_html( cmp_communication_empty_state() ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $messages as $message ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $message->recipient_name ); ?></strong><br>
									<span class="cmp-muted"><?php echo esc_html( $message->recipient_email ? $message->recipient_email : $message->recipient_phone ); ?></span>
								</td>
								<td><?php echo esc_html( ucfirst( $message->channel ) ); ?></td>
								<td><?php echo esc_html( $message->subject ); ?></td>
								<td><?php echo esc_html( cmp_communication_status_label( $message->delivery_status ) ); ?></td>
								<td><?php echo esc_html( cmp_communication_format_datetime( $message->created_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'history', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders statistics page.
 *
 * @return void
 */
function cmp_render_communication_statistics_page() {
	cmp_require_communication_center_access();

	$stats = cmp_get_communication_statistics();
	ob_start();
	?>
	<section class="cmp-panel">
		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card"><span><?php esc_html_e( 'Total Messages', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['total_messages'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Email Sent', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['email_sent'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'WhatsApp Sent', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['whatsapp_sent'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Failed', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['failed'] ) ); ?></strong></div>
		</div>
		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card"><span><?php esc_html_e( 'Delivery Rate', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $stats['delivery_rate'], 2 ) ); ?>%</strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Open Rate', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $stats['open_rate'], 2 ) ); ?>%</strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Campaigns', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['campaign_total'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Templates', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['template_total'] ) ); ?></strong></div>
		</div>
		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card"><span><?php esc_html_e( 'Active Rules', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['rule_total'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Scheduled Campaigns', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['campaign_scheduled'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Completed Campaigns', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['campaign_done'] ) ); ?></strong></div>
			<div class="cmp-card"><span><?php esc_html_e( 'Read Messages', 'class-manager-pro' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $stats['read_count'] ) ); ?></strong></div>
		</div>
	</section>
	<section class="cmp-panel cmp-space-top">
		<div class="cmp-grid cmp-grid-2">
			<div class="cmp-panel">
				<h2><?php esc_html_e( 'Channel Performance', 'class-manager-pro' ); ?></h2>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<tbody>
							<tr><th><?php esc_html_e( 'Email Sent', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['email_sent'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'WhatsApp Sent', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['whatsapp_sent'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Delivery Rate', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (float) $stats['delivery_rate'], 2 ) ); ?>%</td></tr>
							<tr><th><?php esc_html_e( 'Open Rate', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (float) $stats['open_rate'], 2 ) ); ?>%</td></tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="cmp-panel">
				<h2><?php esc_html_e( 'Campaign Health', 'class-manager-pro' ); ?></h2>
				<div class="cmp-table-scroll cmp-table-scroll-y">
					<table class="widefat striped">
						<tbody>
							<tr><th><?php esc_html_e( 'Total Campaigns', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['campaign_total'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Scheduled', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['campaign_scheduled'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Completed', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['campaign_done'] ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Templates', 'class-manager-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['template_total'] ) ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'statistics', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Renders settings page.
 *
 * @return void
 */
function cmp_render_communication_settings_page() {
	cmp_require_communication_center_access();

	$settings = cmp_get_communication_center_settings();
	ob_start();
	?>
	<section class="cmp-panel">
		<h2><?php esc_html_e( 'Communication Settings', 'class-manager-pro' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="cmp_save_communication_settings_ajax">
			<input type="hidden" name="action" value="cmp_save_communication_settings">
			<?php wp_nonce_field( 'cmp_save_communication_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="cmp-communication-sender"><?php esc_html_e( 'Sender Name', 'class-manager-pro' ); ?></label></th><td><input type="text" id="cmp-communication-sender" name="sender_name" class="regular-text" value="<?php echo esc_attr( $settings['sender_name'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="cmp-communication-reply"><?php esc_html_e( 'Reply-To Email', 'class-manager-pro' ); ?></label></th><td><input type="email" id="cmp-communication-reply" name="reply_to_email" class="regular-text" value="<?php echo esc_attr( $settings['reply_to_email'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="cmp-communication-support"><?php esc_html_e( 'Support Contact', 'class-manager-pro' ); ?></label></th><td><input type="email" id="cmp-communication-support" name="support_contact" class="regular-text" value="<?php echo esc_attr( $settings['support_contact'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="cmp-communication-signature"><?php esc_html_e( 'Email Signature', 'class-manager-pro' ); ?></label></th><td><textarea id="cmp-communication-signature" name="email_signature" rows="4" class="large-text"><?php echo esc_textarea( $settings['email_signature'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="cmp-communication-footer"><?php esc_html_e( 'WhatsApp Footer', 'class-manager-pro' ); ?></label></th><td><textarea id="cmp-communication-footer" name="whatsapp_footer" rows="4" class="large-text"><?php echo esc_textarea( $settings['whatsapp_footer'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="cmp-communication-auto-run"><?php esc_html_e( 'Auto Run Scheduled Campaigns', 'class-manager-pro' ); ?></label></th><td><label><input type="checkbox" id="cmp-communication-auto-run" name="campaign_auto_run" value="1" <?php checked( $settings['campaign_auto_run'], '1' ); ?>> <?php esc_html_e( 'Allow the hourly runner to process due campaigns.', 'class-manager-pro' ); ?></label></td></tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'class-manager-pro' ) ); ?>
		</form>
	</section>
	<?php
	echo cmp_communication_shell_markup( 'settings', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Saves template through admin-post.
 *
 * @return void
 */
function cmp_handle_save_communication_template() {
	cmp_require_communication_center_access();
	check_admin_referer( 'cmp_save_communication_template' );
	$result = cmp_save_communication_template_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-templates', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-templates', $result['message'] );
}

/**
 * Archives a template via admin-post.
 *
 * @return void
 */
function cmp_handle_delete_communication_template() {
	cmp_require_communication_center_access();
	$id = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_communication_template_' . $id );
	$result = cmp_delete_communication_template_record( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-templates', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-templates', __( 'Template archived.', 'class-manager-pro' ) );
}

/**
 * Saves campaign through admin-post.
 *
 * @return void
 */
function cmp_handle_save_communication_campaign() {
	cmp_require_communication_center_access();
	check_admin_referer( 'cmp_save_communication_campaign' );
	$result = cmp_save_communication_campaign_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-campaigns', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-campaigns', $result['message'] );
}

/**
 * Runs a campaign via admin-post.
 *
 * @return void
 */
function cmp_handle_run_communication_campaign() {
	cmp_require_communication_center_access();
	$id = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	check_admin_referer( 'cmp_run_communication_campaign_' . $id );
	$result = cmp_run_communication_campaign( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-campaigns', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-campaigns', $result['message'] );
}

/**
 * Cancels a campaign via admin-post.
 *
 * @return void
 */
function cmp_handle_cancel_communication_campaign() {
	cmp_require_communication_center_access();
	$id = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	check_admin_referer( 'cmp_cancel_communication_campaign_' . $id );
	$result = cmp_cancel_communication_campaign( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-campaigns', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-campaigns', __( 'Campaign cancelled.', 'class-manager-pro' ) );
}

/**
 * Saves automation through admin-post.
 *
 * @return void
 */
function cmp_handle_save_communication_automation() {
	cmp_require_communication_center_access();
	check_admin_referer( 'cmp_save_communication_automation' );
	$result = cmp_save_communication_automation_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-automation', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-automation', $result['message'] );
}

/**
 * Deletes automation via admin-post.
 *
 * @return void
 */
function cmp_handle_delete_communication_automation() {
	cmp_require_communication_center_access();
	$id = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_communication_automation_' . $id );
	$result = cmp_delete_communication_automation_record( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-automation', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-automation', __( 'Automation rule archived.', 'class-manager-pro' ) );
}

/**
 * Saves settings via admin-post.
 *
 * @return void
 */
function cmp_handle_save_communication_settings() {
	cmp_require_communication_center_access();
	check_admin_referer( 'cmp_save_communication_settings' );
	$result = cmp_update_communication_center_settings( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-settings', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-settings', __( 'Communication settings saved successfully.', 'class-manager-pro' ) );
}

/**
 * Test email handler.
 *
 * @return void
 */
function cmp_handle_send_communication_test_email() {
	cmp_require_communication_center_access();
	check_admin_referer( 'cmp_send_communication_test_email' );

	$email = sanitize_email( cmp_field( $_POST, 'test_email', get_bloginfo( 'admin_email' ) ) );
	$subject = sanitize_text_field( cmp_field( $_POST, 'subject', __( 'Communication Center test email', 'class-manager-pro' ) ) );
	$message = wp_kses_post( wp_unslash( (string) cmp_field( $_POST, 'message', __( '<p>This is a test email from Communication Center.</p>', 'class-manager-pro' ) ) ) );
	$recipient = (object) array(
		'recipient_type' => 'admin',
		'name'           => get_bloginfo( 'name' ),
		'email'          => $email,
		'phone'          => '',
	);
	$result = cmp_communication_dispatch_message( $recipient, 'email', $subject, $message, array( 'message_type' => 'test_email' ) );

	if ( empty( $result['success'] ) ) {
		cmp_redirect( 'cmp-communication-center-email', $result['message'], 'error' );
	}

	cmp_redirect( 'cmp-communication-center-email', __( 'Test email sent successfully.', 'class-manager-pro' ) );
}

/**
 * Test WhatsApp handler.
 *
 * @return void
 */
function cmp_handle_send_communication_test_whatsapp() {
	cmp_require_communication_center_access();
	check_admin_referer( 'cmp_send_communication_test_whatsapp' );

	$phone = sanitize_text_field( cmp_field( $_POST, 'test_phone', '' ) );
	$message = sanitize_textarea_field( cmp_field( $_POST, 'message', __( 'Hello from Communication Center.', 'class-manager-pro' ) ) );
	$link = cmp_communication_build_whatsapp_url( $phone, $message );

	if ( '' === $link ) {
		cmp_redirect( 'cmp-communication-center-whatsapp', __( 'Please enter a valid phone number.', 'class-manager-pro' ), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-whatsapp', __( 'WhatsApp link generated.', 'class-manager-pro' ) );
}

/**
 * Toggle message read status.
 *
 * @return void
 */
function cmp_handle_toggle_communication_message_read() {
	cmp_require_communication_center_access();
	$id = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	$read = ! empty( $_REQUEST['read'] );
	check_admin_referer( 'cmp_toggle_communication_message_read_' . $id );
	$result = cmp_toggle_communication_message_read( $id, $read );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-communication-center-history', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-communication-center-history', $read ? __( 'Message marked as read.', 'class-manager-pro' ) : __( 'Message marked as unread.', 'class-manager-pro' ) );
}

/**
 * AJAX save template.
 *
 * @return void
 */
function cmp_ajax_save_communication_template() {
	cmp_require_communication_center_access_ajax();
	check_ajax_referer( 'cmp_save_communication_template', 'nonce' );
	$result = cmp_save_communication_template_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( $result );
}

/**
 * AJAX delete template.
 *
 * @return void
 */
function cmp_ajax_delete_communication_template() {
	cmp_require_communication_center_access_ajax();
	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	check_ajax_referer( 'cmp_delete_communication_template_' . $id, 'nonce' );
	$result = cmp_delete_communication_template_record( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( array( 'message' => __( 'Template archived.', 'class-manager-pro' ) ) );
}

/**
 * AJAX save campaign.
 *
 * @return void
 */
function cmp_ajax_save_communication_campaign() {
	cmp_require_communication_center_access_ajax();
	check_ajax_referer( 'cmp_save_communication_campaign', 'nonce' );
	$result = cmp_save_communication_campaign_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( $result );
}

/**
 * AJAX run campaign.
 *
 * @return void
 */
function cmp_ajax_run_communication_campaign() {
	cmp_require_communication_center_access_ajax();
	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	check_ajax_referer( 'cmp_run_communication_campaign_' . $id, 'nonce' );
	$result = cmp_run_communication_campaign( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( $result );
}

/**
 * AJAX cancel campaign.
 *
 * @return void
 */
function cmp_ajax_cancel_communication_campaign() {
	cmp_require_communication_center_access_ajax();
	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	check_ajax_referer( 'cmp_cancel_communication_campaign_' . $id, 'nonce' );
	$result = cmp_cancel_communication_campaign( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( array( 'message' => __( 'Campaign cancelled.', 'class-manager-pro' ) ) );
}

/**
 * AJAX save automation.
 *
 * @return void
 */
function cmp_ajax_save_communication_automation() {
	cmp_require_communication_center_access_ajax();
	check_ajax_referer( 'cmp_save_communication_automation', 'nonce' );
	$result = cmp_save_communication_automation_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( $result );
}

/**
 * AJAX delete automation.
 *
 * @return void
 */
function cmp_ajax_delete_communication_automation() {
	cmp_require_communication_center_access_ajax();
	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	check_ajax_referer( 'cmp_delete_communication_automation_' . $id, 'nonce' );
	$result = cmp_delete_communication_automation_record( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( array( 'message' => __( 'Automation archived.', 'class-manager-pro' ) ) );
}

/**
 * AJAX save settings.
 *
 * @return void
 */
function cmp_ajax_save_communication_settings() {
	cmp_require_communication_center_access_ajax();
	check_ajax_referer( 'cmp_save_communication_settings', 'nonce' );
	$result = cmp_update_communication_center_settings( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( array( 'message' => __( 'Communication settings saved successfully.', 'class-manager-pro' ) ) );
}

/**
 * AJAX test email.
 *
 * @return void
 */
function cmp_ajax_send_communication_test_email() {
	cmp_require_communication_center_access_ajax();
	check_ajax_referer( 'cmp_send_communication_test_email', 'nonce' );
	$recipient = (object) array(
		'recipient_type' => 'admin',
		'name'           => get_bloginfo( 'name' ),
		'email'          => sanitize_email( cmp_field( $_POST, 'test_email', get_bloginfo( 'admin_email' ) ) ),
		'phone'          => '',
	);
	$result = cmp_communication_dispatch_message( $recipient, 'email', sanitize_text_field( cmp_field( $_POST, 'subject', __( 'Communication Center test email', 'class-manager-pro' ) ) ), wp_kses_post( wp_unslash( (string) cmp_field( $_POST, 'message', '' ) ) ), array( 'message_type' => 'test_email' ) );

	if ( empty( $result['success'] ) ) {
		wp_send_json_error( $result, 400 );
	}

	wp_send_json_success( $result );
}

/**
 * AJAX test WhatsApp.
 *
 * @return void
 */
function cmp_ajax_send_communication_test_whatsapp() {
	cmp_require_communication_center_access_ajax();
	check_ajax_referer( 'cmp_send_communication_test_whatsapp', 'nonce' );
	$link = cmp_communication_build_whatsapp_url( sanitize_text_field( cmp_field( $_POST, 'test_phone', '' ) ), sanitize_textarea_field( cmp_field( $_POST, 'message', '' ) ) );

	if ( '' === $link ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid phone number.', 'class-manager-pro' ) ), 400 );
	}

	wp_send_json_success( array( 'message' => __( 'WhatsApp link generated.', 'class-manager-pro' ), 'link' => $link ) );
}

/**
 * AJAX toggle message read state.
 *
 * @return void
 */
function cmp_ajax_toggle_communication_message_read() {
	cmp_require_communication_center_access_ajax();
	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	$read = ! empty( $_POST['read'] );
	check_ajax_referer( 'cmp_toggle_communication_message_read_' . $id, 'nonce' );
	$result = cmp_toggle_communication_message_read( $id, $read );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( array( 'message' => $read ? __( 'Message marked as read.', 'class-manager-pro' ) : __( 'Message marked as unread.', 'class-manager-pro' ) ) );
}
