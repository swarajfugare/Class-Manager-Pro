<?php
/**
 * Communication Center helpers for Class Manager Pro.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_cmp_save_communication_template', 'cmp_handle_save_communication_template' );
add_action( 'admin_post_cmp_delete_communication_template', 'cmp_handle_delete_communication_template' );
add_action( 'admin_post_cmp_save_communication_campaign', 'cmp_handle_save_communication_campaign' );
add_action( 'admin_post_cmp_run_communication_campaign', 'cmp_handle_run_communication_campaign' );
add_action( 'admin_post_cmp_cancel_communication_campaign', 'cmp_handle_cancel_communication_campaign' );
add_action( 'admin_post_cmp_save_communication_automation', 'cmp_handle_save_communication_automation' );
add_action( 'admin_post_cmp_delete_communication_automation', 'cmp_handle_delete_communication_automation' );
add_action( 'admin_post_cmp_save_communication_settings', 'cmp_handle_save_communication_settings' );
add_action( 'admin_post_cmp_send_communication_test_email', 'cmp_handle_send_communication_test_email' );
add_action( 'admin_post_cmp_send_communication_test_whatsapp', 'cmp_handle_send_communication_test_whatsapp' );
add_action( 'admin_post_cmp_toggle_communication_message_read', 'cmp_handle_toggle_communication_message_read' );
add_action( 'wp_ajax_cmp_save_communication_template_ajax', 'cmp_ajax_save_communication_template' );
add_action( 'wp_ajax_cmp_delete_communication_template_ajax', 'cmp_ajax_delete_communication_template' );
add_action( 'wp_ajax_cmp_save_communication_campaign_ajax', 'cmp_ajax_save_communication_campaign' );
add_action( 'wp_ajax_cmp_run_communication_campaign_ajax', 'cmp_ajax_run_communication_campaign' );
add_action( 'wp_ajax_cmp_cancel_communication_campaign_ajax', 'cmp_ajax_cancel_communication_campaign' );
add_action( 'wp_ajax_cmp_save_communication_automation_ajax', 'cmp_ajax_save_communication_automation' );
add_action( 'wp_ajax_cmp_delete_communication_automation_ajax', 'cmp_ajax_delete_communication_automation' );
add_action( 'wp_ajax_cmp_save_communication_settings_ajax', 'cmp_ajax_save_communication_settings' );
add_action( 'wp_ajax_cmp_send_communication_test_email_ajax', 'cmp_ajax_send_communication_test_email' );
add_action( 'wp_ajax_cmp_send_communication_test_whatsapp_ajax', 'cmp_ajax_send_communication_test_whatsapp' );
add_action( 'wp_ajax_cmp_toggle_communication_message_read_ajax', 'cmp_ajax_toggle_communication_message_read' );
add_action( 'init', 'cmp_schedule_communication_campaign_runner' );
add_action( 'cmp_communication_campaign_runner', 'cmp_run_due_communication_campaigns' );
add_action( 'cmp_student_registered', 'cmp_communication_handle_student_registered', 10, 4 );
add_action( 'cmp_fee_reminder_logged', 'cmp_communication_handle_fee_reminder_logged', 10, 4 );
add_action( 'cmp_payment_recorded', 'cmp_communication_handle_payment_recorded', 10, 2 );
add_action( 'cmp_batch_announcement_sent', 'cmp_communication_handle_batch_announcement_sent', 10, 6 );
add_action( 'cmp_smartcertify_batch_completed', 'cmp_communication_handle_batch_completed', 10, 1 );
add_action( 'cmp_smartcertify_batch_status_changed', 'cmp_communication_handle_batch_status_changed', 10, 1 );

/**
 * Returns Communication Center table names.
 *
 * @param string $suffix Table suffix.
 * @return string
 */
function cmp_communication_table( $suffix ) {
	return cmp_table( 'communication_' . sanitize_key( $suffix ) );
}

/**
 * Returns whether the Communication Center schema exists.
 *
 * @return bool
 */
function cmp_communication_schema_needs_upgrade() {
	global $wpdb;

	foreach ( array( 'templates', 'campaigns', 'messages', 'automation_rules' ) as $suffix ) {
		$table = cmp_communication_table( $suffix );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! is_string( $found ) || $table !== $found ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the Communication Center settings key.
 *
 * @return string
 */
function cmp_communication_settings_option() {
	return 'cmp_communication_center_settings';
}

/**
 * Returns default Communication Center settings.
 *
 * @return array
 */
function cmp_communication_default_settings() {
	return array(
		'sender_name'                 => get_bloginfo( 'name' ),
		'reply_to_email'              => sanitize_email( get_bloginfo( 'admin_email' ) ),
		'support_contact'             => sanitize_email( get_bloginfo( 'admin_email' ) ),
		'email_signature'             => __( 'Warm regards,', 'class-manager-pro' ),
		'whatsapp_footer'             => __( 'Reply to this message if you need help.', 'class-manager-pro' ),
		'campaign_auto_run'           => '1',
		'campaign_time_limit'         => '60',
		'whatsapp_gateway_type'       => 'wa_link', // 'wa_link', 'custom_webhook', 'meta_cloud'
		'whatsapp_api_url'            => '',
		'whatsapp_api_token'          => '',
		'whatsapp_phone_number_id'    => '',
		'whatsapp_webhook_secret'     => wp_generate_password( 24, false ),
		'whatsapp_auto_welcome'       => '0',
		'whatsapp_auto_fee_reminder'  => '0',
		'notify_admin_on_admission'   => '0',
		'notify_teacher_on_admission' => '0',
		'enable_daily_admission_digest'=> '1',
	);
}

/**
 * Returns the current Communication Center settings.
 *
 * @return array
 */
function cmp_get_communication_center_settings() {
	$settings = get_option( cmp_communication_settings_option(), array() );

	return wp_parse_args( is_array( $settings ) ? $settings : array(), cmp_communication_default_settings() );
}

/**
 * Saves Communication Center settings.
 *
 * @param array $data Payload.
 * @return true|WP_Error
 */
function cmp_update_communication_center_settings( $data ) {
	$settings = cmp_get_communication_center_settings();

	$settings['sender_name']                 = sanitize_text_field( cmp_field( $data, 'sender_name', $settings['sender_name'] ) );
	$settings['reply_to_email']              = sanitize_email( cmp_field( $data, 'reply_to_email', $settings['reply_to_email'] ) );
	$settings['support_contact']             = sanitize_email( cmp_field( $data, 'support_contact', $settings['support_contact'] ) );
	$settings['email_signature']             = sanitize_textarea_field( cmp_field( $data, 'email_signature', $settings['email_signature'] ) );
	$settings['whatsapp_footer']             = sanitize_textarea_field( cmp_field( $data, 'whatsapp_footer', $settings['whatsapp_footer'] ) );
	$settings['campaign_auto_run']           = ! empty( $data['campaign_auto_run'] ) ? '1' : '0';
	$settings['campaign_time_limit']         = max( 1, absint( cmp_field( $data, 'campaign_time_limit', $settings['campaign_time_limit'] ) ) );
	$settings['whatsapp_gateway_type']       = sanitize_key( cmp_field( $data, 'whatsapp_gateway_type', $settings['whatsapp_gateway_type'] ) );
	$settings['whatsapp_api_url']            = esc_url_raw( cmp_field( $data, 'whatsapp_api_url', $settings['whatsapp_api_url'] ) );
	$settings['whatsapp_api_token']          = sanitize_text_field( cmp_field( $data, 'whatsapp_api_token', $settings['whatsapp_api_token'] ) );
	$settings['whatsapp_phone_number_id']    = sanitize_text_field( cmp_field( $data, 'whatsapp_phone_number_id', $settings['whatsapp_phone_number_id'] ) );
	$settings['whatsapp_webhook_secret']     = sanitize_text_field( cmp_field( $data, 'whatsapp_webhook_secret', $settings['whatsapp_webhook_secret'] ) );
	$settings['whatsapp_auto_welcome']       = ! empty( $data['whatsapp_auto_welcome'] ) ? '1' : '0';
	$settings['whatsapp_auto_fee_reminder']  = ! empty( $data['whatsapp_auto_fee_reminder'] ) ? '1' : '0';
	$settings['notify_admin_on_admission']   = ! empty( $data['notify_admin_on_admission'] ) ? '1' : '0';
	$settings['notify_teacher_on_admission'] = ! empty( $data['notify_teacher_on_admission'] ) ? '1' : '0';
	$settings['enable_daily_admission_digest']= ! empty( $data['enable_daily_admission_digest'] ) ? '1' : '0';

	// Sync options with core settings
	update_option( 'cmp_notify_admin_on_admission', $settings['notify_admin_on_admission'] );
	update_option( 'cmp_notify_teacher_on_admission', $settings['notify_teacher_on_admission'] );
	update_option( 'cmp_enable_daily_admission_digest', $settings['enable_daily_admission_digest'] );

	$updated = update_option( cmp_communication_settings_option(), $settings );

	if ( false === $updated ) {
		return new WP_Error( 'cmp_communication_settings_failed', __( 'Could not save Communication Center settings.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return true;
}

/**
 * Returns the Communication Center cache version.
 *
 * @return int
 */
function cmp_communication_cache_version() {
	return max( 1, absint( get_option( 'cmp_communication_cache_version', 1 ) ) );
}

/**
 * Bumps the Communication Center cache version.
 *
 * @return void
 */
function cmp_communication_bump_cache_version() {
	update_option( 'cmp_communication_cache_version', cmp_communication_cache_version() + 1 );
}

/**
 * Schedules the communication campaign runner.
 *
 * @return void
 */
function cmp_schedule_communication_campaign_runner() {
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}

	if ( ! wp_next_scheduled( 'cmp_communication_campaign_runner' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS * 10, 'hourly', 'cmp_communication_campaign_runner' );
	}
}

/**
 * Ensures the current user may manage Communication Center data.
 *
 * @return bool
 */
function cmp_communication_user_can_access() {
	return current_user_can( 'manage_options' );
}

/**
 * Stops execution for unauthorized access.
 */
function cmp_require_communication_center_access() {
	if ( ! cmp_communication_user_can_access() ) {
		wp_die( esc_html__( 'You do not have permission to access Communication Center.', 'class-manager-pro' ) );
	}
}

/**
 * Stops AJAX execution for unauthorized access.
 */
function cmp_require_communication_center_access_ajax() {
	if ( cmp_communication_user_can_access() ) {
		return;
	}

	wp_send_json_error(
		array(
			'message' => __( 'You do not have permission to access Communication Center.', 'class-manager-pro' ),
		),
		403
	);
}

/**
 * Returns a human friendly label for a communication status.
 *
 * @param string $status Status key.
 * @return string
 */
function cmp_communication_status_label( $status ) {
	$labels = array(
		'draft'      => __( 'Draft', 'class-manager-pro' ),
		'scheduled'  => __( 'Scheduled', 'class-manager-pro' ),
		'completed'  => __( 'Completed', 'class-manager-pro' ),
		'cancelled'  => __( 'Cancelled', 'class-manager-pro' ),
		'active'     => __( 'Active', 'class-manager-pro' ),
		'paused'     => __( 'Paused', 'class-manager-pro' ),
		'queued'     => __( 'Queued', 'class-manager-pro' ),
		'sent'       => __( 'Sent', 'class-manager-pro' ),
		'failed'     => __( 'Failed', 'class-manager-pro' ),
		'read'       => __( 'Read', 'class-manager-pro' ),
		'unread'     => __( 'Unread', 'class-manager-pro' ),
	);

	$status = sanitize_key( $status );

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucwords( str_replace( array( '_', '-' ), ' ', $status ) );
}

/**
 * Returns a list of built-in communication templates.
 *
 * @return array
 */
function cmp_communication_default_templates() {
	return array(
		array(
			'template_name' => __( 'Welcome Email', 'class-manager-pro' ),
			'template_type' => 'welcome',
			'channel'       => 'email',
			'subject'       => __( 'Welcome to {{batch_name}}', 'class-manager-pro' ),
			'body'          => __( '<p>Hello {{name}},</p><p>Welcome to {{course}}. We are excited to have you in {{batch_name}}.</p><p>{{signature}}</p>', 'class-manager-pro' ),
		),
		array(
			'template_name' => __( 'Pending Fee Reminder', 'class-manager-pro' ),
			'template_type' => 'pending_fee',
			'channel'       => 'email',
			'subject'       => __( 'Pending fee reminder for {{batch_name}}', 'class-manager-pro' ),
			'body'          => __( '<p>Hello {{name}},</p><p>Pending fee: {{amount}} for {{batch_name}}.</p><p>Due date: {{due_date}}</p><p>{{signature}}</p>', 'class-manager-pro' ),
		),
		array(
			'template_name' => __( 'WhatsApp Broadcast', 'class-manager-pro' ),
			'template_type' => 'broadcast',
			'channel'       => 'whatsapp',
			'subject'       => '',
			'body'          => __( 'Hello {{name}}, {{message}}', 'class-manager-pro' ),
		),
	);
}

/**
 * Returns communication template replacements.
 *
 * @param array $context Render context.
 * @return array
 */
function cmp_communication_template_replacements( $context ) {
	$student = isset( $context['student'] ) && is_object( $context['student'] ) ? $context['student'] : null;
	$batch   = isset( $context['batch'] ) && is_object( $context['batch'] ) ? $context['batch'] : null;
	$class    = isset( $context['class'] ) && is_object( $context['class'] ) ? $context['class'] : null;
	$teacher  = isset( $context['teacher'] ) && is_object( $context['teacher'] ) ? $context['teacher'] : null;
	$settings = cmp_get_communication_center_settings();
	$amount   = isset( $context['amount'] ) ? cmp_format_money( cmp_money_value( $context['amount'] ) ) : ( $student ? cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) ) : '' );
	$course   = $class && ! empty( $class->name ) ? $class->name : ( $student && ! empty( $student->class_name ) ? $student->class_name : '' );
	$batch_name = $batch && ! empty( $batch->batch_name ) ? $batch->batch_name : ( $student && ! empty( $student->batch_name ) ? $student->batch_name : '' );
	$due_date = $batch && ! empty( $batch->fee_due_date ) ? $batch->fee_due_date : ( $student && ! empty( $student->fee_due_date ) ? $student->fee_due_date : '' );
	$name     = $student && ! empty( $student->name ) ? $student->name : ( $teacher && ! empty( $teacher->display_name ) ? $teacher->display_name : __( 'there', 'class-manager-pro' ) );
	$phone    = $student && ! empty( $student->phone ) ? $student->phone : '';
	$email    = $student && ! empty( $student->email ) ? $student->email : ( $teacher && ! empty( $teacher->user_email ) ? $teacher->user_email : '' );
	$message  = isset( $context['message'] ) ? (string) $context['message'] : '';
	$signature = $settings['email_signature'];

	return array(
		'{{name}}'           => $name,
		'{{student_name}}'   => $name,
		'{{phone}}'          => $phone,
		'{{email}}'          => $email,
		'{{amount}}'         => $amount,
		'{{course}}'         => $course,
		'{{class_name}}'     => $course,
		'{{batch}}'          => $batch_name,
		'{{batch_name}}'     => $batch_name,
		'{{due_date}}'       => $due_date,
		'{{teacher_name}}'   => $teacher && ! empty( $teacher->display_name ) ? $teacher->display_name : '',
		'{{support_contact}}'=> $settings['support_contact'],
		'{{sender_name}}'    => $settings['sender_name'],
		'{{message}}'        => $message,
		'{{signature}}'      => $signature,
		'{student_name}'     => $name,
		'{class_name}'       => $course,
		'{batch_name}'       => $batch_name,
		'{pending_fee}'      => $amount,
		'{due_date}'         => $due_date,
		'{support_contact}'  => $settings['support_contact'],
	);
}

/**
 * Renders a communication template for a context.
 *
 * @param string $template Template text.
 * @param array  $context Context payload.
 * @return string
 */
function cmp_render_communication_template( $template, $context = array() ) {
	return strtr( (string) $template, cmp_communication_template_replacements( is_array( $context ) ? $context : array() ) );
}

/**
 * Fetches communication templates.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_communication_templates( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'search'  => '',
			'channel' => '',
			'type'    => '',
			'status'  => '',
			'limit'   => 0,
			'offset'  => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['search'] ) {
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		$where[] = '(template_name LIKE %s OR subject LIKE %s OR body LIKE %s)';
		$params  = array_merge( $params, array( $like, $like, $like ) );
	}

	if ( '' !== $args['channel'] ) {
		$where[]  = 'channel = %s';
		$params[] = sanitize_key( $args['channel'] );
	}

	if ( '' !== $args['type'] ) {
		$where[]  = 'template_type = %s';
		$params[] = sanitize_key( $args['type'] );
	}

	if ( '' !== $args['status'] ) {
		$where[]  = 'status = %s';
		$params[] = sanitize_key( $args['status'] );
	}

	$sql = 'SELECT * FROM ' . cmp_communication_table( 'templates' );

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY is_default DESC, updated_at DESC, created_at DESC, id DESC';

	if ( ! empty( $args['limit'] ) ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Returns one communication template.
 *
 * @param int $template_id Template ID.
 * @return object|null
 */
function cmp_get_communication_template( $template_id ) {
	global $wpdb;

	$template_id = absint( $template_id );

	if ( ! $template_id ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . cmp_communication_table( 'templates' ) . ' WHERE id = %d LIMIT 1', $template_id )
	);
}

/**
 * Saves a communication template.
 *
 * @param array $data Form data.
 * @return array|WP_Error
 */
function cmp_save_communication_template_record( $data ) {
	global $wpdb;

	$id = absint( cmp_field( $data, 'id', 0 ) );
	$existing = $id ? cmp_get_communication_template( $id ) : null;
	$template_name = sanitize_text_field( cmp_field( $data, 'template_name', '' ) );
	$template_type = sanitize_key( cmp_field( $data, 'template_type', 'general' ) );
	$channel = cmp_clean_enum( cmp_field( $data, 'channel', 'email' ), array( 'email', 'whatsapp' ), 'email' );
	$subject = sanitize_text_field( cmp_field( $data, 'subject', '' ) );
	$body = wp_kses_post( wp_unslash( (string) cmp_field( $data, 'body', '' ) ) );
	$variables = sanitize_textarea_field( cmp_field( $data, 'variables', '' ) );
	$status = cmp_clean_enum( cmp_field( $data, 'status', 'active' ), array( 'active', 'archived' ), 'active' );
	$is_default = ! empty( $data['is_default'] ) ? 1 : 0;
	$parent_template_id = absint( cmp_field( $data, 'parent_template_id', 0 ) );

	if ( '' === $template_name || '' === $body ) {
		return new WP_Error( 'cmp_communication_template_invalid', __( 'Template name and body are required.', 'class-manager-pro' ) );
	}

	$payload = array(
		'template_name'     => $template_name,
		'template_type'     => $template_type,
		'channel'           => $channel,
		'subject'           => $subject,
		'body'              => $body,
		'variables'         => $variables,
		'status'            => $status,
		'version_number'    => $existing ? ( (int) $existing->version_number + 1 ) : 1,
		'parent_template_id'=> $parent_template_id,
		'is_default'        => $is_default,
		'updated_by'        => get_current_user_id(),
		'updated_at'        => cmp_current_datetime(),
	);

	if ( $id ) {
		$result = $wpdb->update( cmp_communication_table( 'templates' ), $payload, array( 'id' => $id ), null, array( '%d' ) );
	} else {
		$payload['created_by'] = get_current_user_id();
		$payload['created_at']  = cmp_current_datetime();
		$result = $wpdb->insert( cmp_communication_table( 'templates' ), $payload );
	}

	if ( false === $result ) {
		return new WP_Error( 'cmp_communication_template_failed', __( 'Could not save template.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return array(
		'id'      => $id ? $id : (int) $wpdb->insert_id,
		'message' => $id ? __( 'Template updated successfully.', 'class-manager-pro' ) : __( 'Template saved successfully.', 'class-manager-pro' ),
		'refresh' => array(
			'action' => 'cmp_filter_communication_section',
			'section'=> 'templates',
			'target' => '.cmp-communication-shell',
		),
	);
}

/**
 * Deletes a communication template.
 *
 * @param int $template_id Template ID.
 * @return true|WP_Error
 */
function cmp_delete_communication_template_record( $template_id ) {
	global $wpdb;

	$template_id = absint( $template_id );

	if ( ! $template_id ) {
		return new WP_Error( 'cmp_communication_template_missing', __( 'Template not found.', 'class-manager-pro' ) );
	}

	$updated = $wpdb->update(
		cmp_communication_table( 'templates' ),
		array(
			'status'     => 'archived',
			'updated_by' => get_current_user_id(),
			'updated_at' => cmp_current_datetime(),
		),
		array( 'id' => $template_id ),
		array( '%s', '%d', '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_communication_template_delete_failed', __( 'Could not archive template.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return true;
}

/**
 * Fetches campaigns.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_communication_campaigns( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'search'  => '',
			'channel' => '',
			'status'  => '',
			'limit'   => 0,
			'offset'  => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['search'] ) {
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		$where[] = '(title LIKE %s OR subject LIKE %s OR message LIKE %s)';
		$params  = array_merge( $params, array( $like, $like, $like ) );
	}

	if ( '' !== $args['channel'] ) {
		$where[]  = 'channel = %s';
		$params[] = sanitize_key( $args['channel'] );
	}

	if ( '' !== $args['status'] ) {
		$where[]  = 'status = %s';
		$params[] = sanitize_key( $args['status'] );
	}

	$sql = 'SELECT * FROM ' . cmp_communication_table( 'campaigns' );

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY created_at DESC, id DESC';

	if ( ! empty( $args['limit'] ) ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Returns one campaign.
 *
 * @param int $campaign_id Campaign ID.
 * @return object|null
 */
function cmp_get_communication_campaign( $campaign_id ) {
	global $wpdb;

	$campaign_id = absint( $campaign_id );

	if ( ! $campaign_id ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . cmp_communication_table( 'campaigns' ) . ' WHERE id = %d LIMIT 1', $campaign_id )
	);
}

/**
 * Saves a campaign.
 *
 * @param array $data Campaign payload.
 * @return array|WP_Error
 */
function cmp_save_communication_campaign_record( $data ) {
	global $wpdb;

	$id = absint( cmp_field( $data, 'id', 0 ) );
	$campaign_type = cmp_clean_enum( cmp_field( $data, 'campaign_type', 'email' ), array( 'email', 'whatsapp', 'announcement', 'follow_up' ), 'email' );
	$channel = cmp_clean_enum( cmp_field( $data, 'channel', $campaign_type ), array( 'email', 'whatsapp' ), 'email' );
	$title = sanitize_text_field( cmp_field( $data, 'title', '' ) );
	$subject = sanitize_text_field( cmp_field( $data, 'subject', '' ) );
	$message = wp_kses_post( wp_unslash( (string) cmp_field( $data, 'message', '' ) ) );
	$template_id = absint( cmp_field( $data, 'template_id', 0 ) );
	$recipient_scope = sanitize_key( cmp_field( $data, 'recipient_scope', 'all' ) );
	$recipient_ids = sanitize_text_field( cmp_field( $data, 'recipient_ids', '' ) );
	$status = cmp_clean_enum( cmp_field( $data, 'status', 'draft' ), array( 'draft', 'scheduled', 'completed', 'cancelled' ), 'draft' );
	$scheduled_at = sanitize_text_field( cmp_field( $data, 'scheduled_at', '' ) );
	$scheduled_at = '' !== $scheduled_at ? $scheduled_at : null;

	if ( '' === $title || '' === $message ) {
		return new WP_Error( 'cmp_communication_campaign_invalid', __( 'Campaign title and message are required.', 'class-manager-pro' ) );
	}

	$payload = array(
		'campaign_type'   => $campaign_type,
		'channel'         => $channel,
		'title'           => $title,
		'subject'         => $subject,
		'message'         => $message,
		'template_id'     => $template_id,
		'recipient_scope' => $recipient_scope,
		'recipient_ids'   => $recipient_ids,
		'status'          => $status,
		'scheduled_at'    => $scheduled_at,
		'updated_by'      => get_current_user_id(),
		'updated_at'      => cmp_current_datetime(),
	);

	if ( $id ) {
		$result = $wpdb->update( cmp_communication_table( 'campaigns' ), $payload, array( 'id' => $id ), null, array( '%d' ) );
	} else {
		$payload['created_by'] = get_current_user_id();
		$payload['created_at']  = cmp_current_datetime();
		$result = $wpdb->insert( cmp_communication_table( 'campaigns' ), $payload );
	}

	if ( false === $result ) {
		return new WP_Error( 'cmp_communication_campaign_failed', __( 'Could not save campaign.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return array(
		'id'      => $id ? $id : (int) $wpdb->insert_id,
		'message' => $id ? __( 'Campaign updated successfully.', 'class-manager-pro' ) : __( 'Campaign saved successfully.', 'class-manager-pro' ),
		'refresh' => array(
			'action' => 'cmp_filter_communication_section',
			'section'=> 'campaigns',
			'target' => '.cmp-communication-shell',
		),
	);
}

/**
 * Runs a campaign now.
 *
 * @param int $campaign_id Campaign ID.
 * @return array|WP_Error
 */
function cmp_run_communication_campaign( $campaign_id ) {
	global $wpdb;

	$campaign = cmp_get_communication_campaign( $campaign_id );

	if ( ! $campaign ) {
		return new WP_Error( 'cmp_communication_campaign_missing', __( 'Campaign not found.', 'class-manager-pro' ) );
	}

	$recipients = cmp_communication_get_recipients( $campaign->recipient_scope, array( 'recipient_ids' => $campaign->recipient_ids ) );

	if ( empty( $recipients ) ) {
		return new WP_Error( 'cmp_communication_campaign_empty', __( 'No recipients were found for this campaign.', 'class-manager-pro' ) );
	}

	$subject = '' !== trim( (string) $campaign->subject ) ? $campaign->subject : $campaign->title;
	$body    = '' !== trim( (string) $campaign->message ) ? $campaign->message : '';
	$summary = array(
		'sent'   => 0,
		'failed' => 0,
	);

	foreach ( $recipients as $recipient ) {
		$result = cmp_communication_dispatch_message(
			$recipient,
			$campaign->channel,
			$subject,
			$body,
			array(
				'campaign_id' => (int) $campaign->id,
				'template_id' => (int) $campaign->template_id,
				'message_type' => $campaign->campaign_type,
			)
		);

		if ( ! empty( $result['success'] ) ) {
			++$summary['sent'];
		} else {
			++$summary['failed'];
		}
	}

	$wpdb->update(
		cmp_communication_table( 'campaigns' ),
		array(
			'status'       => 'completed',
			'completed_at' => cmp_current_datetime(),
			'sent_count'   => (int) $summary['sent'],
			'failed_count' => (int) $summary['failed'],
			'updated_by'   => get_current_user_id(),
			'updated_at'   => cmp_current_datetime(),
		),
		array( 'id' => (int) $campaign->id ),
		array( '%s', '%s', '%d', '%d', '%d', '%s' ),
		array( '%d' )
	);

	cmp_communication_bump_cache_version();

	return array(
		'id'      => (int) $campaign->id,
		'message' => sprintf(
			/* translators: 1: sent 2: failed */
			__( 'Campaign completed. Sent: %1$d, failed: %2$d.', 'class-manager-pro' ),
			(int) $summary['sent'],
			(int) $summary['failed']
		),
		'summary' => $summary,
	);
}

/**
 * Marks a campaign as cancelled.
 *
 * @param int $campaign_id Campaign ID.
 * @return true|WP_Error
 */
function cmp_cancel_communication_campaign( $campaign_id ) {
	global $wpdb;

	$campaign_id = absint( $campaign_id );

	if ( ! $campaign_id ) {
		return new WP_Error( 'cmp_communication_campaign_missing', __( 'Campaign not found.', 'class-manager-pro' ) );
	}

	$updated = $wpdb->update(
		cmp_communication_table( 'campaigns' ),
		array(
			'status'     => 'cancelled',
			'updated_by' => get_current_user_id(),
			'updated_at' => cmp_current_datetime(),
		),
		array( 'id' => $campaign_id ),
		array( '%s', '%d', '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_communication_campaign_failed', __( 'Could not cancel campaign.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return true;
}

/**
 * Fetches automation rules.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_communication_automation_rules( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'search' => '',
			'status' => '',
			'limit'  => 0,
			'offset' => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['search'] ) {
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		$where[] = '(rule_name LIKE %s OR trigger_key LIKE %s OR action_type LIKE %s)';
		$params  = array_merge( $params, array( $like, $like, $like ) );
	}

	if ( '' !== $args['status'] ) {
		$where[]  = 'status = %s';
		$params[] = sanitize_key( $args['status'] );
	}

	$sql = 'SELECT * FROM ' . cmp_communication_table( 'automation_rules' );

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY created_at DESC, id DESC';

	if ( ! empty( $args['limit'] ) ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );
		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Saves an automation rule.
 *
 * @param array $data Form data.
 * @return array|WP_Error
 */
function cmp_save_communication_automation_record( $data ) {
	global $wpdb;

	$id = absint( cmp_field( $data, 'id', 0 ) );
	$rule_name = sanitize_text_field( cmp_field( $data, 'rule_name', '' ) );
	$trigger_key = sanitize_key( cmp_field( $data, 'trigger_key', '' ) );
	$channel = cmp_clean_enum( cmp_field( $data, 'channel', 'email' ), array( 'email', 'whatsapp' ), 'email' );
	$action_type = sanitize_key( cmp_field( $data, 'action_type', 'send_email' ) );
	$template_id = absint( cmp_field( $data, 'template_id', 0 ) );
	$recipient_scope = sanitize_key( cmp_field( $data, 'recipient_scope', 'student' ) );
	$status = cmp_clean_enum( cmp_field( $data, 'status', 'active' ), array( 'active', 'paused', 'archived' ), 'active' );
	$conditions = sanitize_textarea_field( cmp_field( $data, 'conditions', '' ) );
	$action_config = sanitize_textarea_field( cmp_field( $data, 'action_config', '' ) );

	if ( '' === $rule_name || '' === $trigger_key ) {
		return new WP_Error( 'cmp_communication_rule_invalid', __( 'Rule name and trigger are required.', 'class-manager-pro' ) );
	}

	$payload = array(
		'rule_name'      => $rule_name,
		'trigger_key'    => $trigger_key,
		'channel'        => $channel,
		'action_type'    => $action_type,
		'template_id'    => $template_id,
		'recipient_scope'=> $recipient_scope,
		'status'         => $status,
		'conditions'     => $conditions,
		'action_config'  => $action_config,
		'updated_by'     => get_current_user_id(),
		'updated_at'     => cmp_current_datetime(),
	);

	if ( $id ) {
		$result = $wpdb->update( cmp_communication_table( 'automation_rules' ), $payload, array( 'id' => $id ) );
	} else {
		$payload['created_by'] = get_current_user_id();
		$payload['created_at']  = cmp_current_datetime();
		$result = $wpdb->insert( cmp_communication_table( 'automation_rules' ), $payload );
	}

	if ( false === $result ) {
		return new WP_Error( 'cmp_communication_rule_failed', __( 'Could not save automation rule.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return array(
		'id'      => $id ? $id : (int) $wpdb->insert_id,
		'message' => $id ? __( 'Automation rule updated successfully.', 'class-manager-pro' ) : __( 'Automation rule saved successfully.', 'class-manager-pro' ),
		'refresh' => array(
			'action' => 'cmp_filter_communication_section',
			'section'=> 'automation',
			'target' => '.cmp-communication-shell',
		),
	);
}

/**
 * Deletes an automation rule.
 *
 * @param int $rule_id Rule ID.
 * @return true|WP_Error
 */
function cmp_delete_communication_automation_record( $rule_id ) {
	global $wpdb;

	$rule_id = absint( $rule_id );

	if ( ! $rule_id ) {
		return new WP_Error( 'cmp_communication_rule_missing', __( 'Automation rule not found.', 'class-manager-pro' ) );
	}

	$updated = $wpdb->update(
		cmp_communication_table( 'automation_rules' ),
		array(
			'status'     => 'archived',
			'updated_by' => get_current_user_id(),
			'updated_at' => cmp_current_datetime(),
		),
		array( 'id' => $rule_id ),
		array( '%s', '%d', '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_communication_rule_failed', __( 'Could not archive automation rule.', 'class-manager-pro' ) );
	}

	cmp_communication_bump_cache_version();

	return true;
}

/**
 * Returns communication recipients for a scope.
 *
 * @param string $scope Scope key.
 * @param array  $args Extra args.
 * @return array
 */
function cmp_communication_get_recipients( $scope, $args = array() ) {
	$scope = sanitize_key( $scope );
	$args  = is_array( $args ) ? $args : array();
	$recipients = array();

	if ( 'selected' === $scope ) {
		$student_ids = cmp_clean_absint_array( cmp_field( $args, 'recipient_ids', array() ) );
		if ( empty( $student_ids ) && '' !== cmp_field( $args, 'recipient_ids', '' ) ) {
			$student_ids = cmp_clean_absint_array( explode( ',', (string) cmp_field( $args, 'recipient_ids', '' ) ) );
		}

		foreach ( cmp_get_students_by_ids( $student_ids ) as $student ) {
			$recipients[] = cmp_communication_student_recipient( $student );
		}

		return $recipients;
	}

	if ( 'batch' === $scope ) {
		$batch_id = absint( cmp_field( $args, 'batch_id', 0 ) );
		foreach ( cmp_get_students( array( 'batch_id' => $batch_id, 'limit' => 0 ) ) as $student ) {
			$recipients[] = cmp_communication_student_recipient( $student );
		}

		return $recipients;
	}

	if ( 'class' === $scope ) {
		$class_id = absint( cmp_field( $args, 'class_id', 0 ) );
		foreach ( cmp_get_students( array( 'class_id' => $class_id, 'limit' => 0 ) ) as $student ) {
			$recipients[] = cmp_communication_student_recipient( $student );
		}

		return $recipients;
	}

	if ( 'teacher' === $scope ) {
		$teacher_id = absint( cmp_field( $args, 'teacher_user_id', 0 ) );
		$users      = $teacher_id ? array( get_user_by( 'id', $teacher_id ) ) : cmp_get_teacher_users();

		foreach ( $users as $user ) {
			if ( ! $user ) {
				continue;
			}

			$recipients[] = (object) array(
				'recipient_type' => 'teacher',
				'id'             => (int) $user->ID,
				'name'           => $user->display_name,
				'email'          => $user->user_email,
				'phone'          => '',
				'class_name'     => '',
				'batch_name'     => '',
			);
		}

		return $recipients;
	}

	if ( 'interested' === $scope ) {
		$lead_id = absint( cmp_field( $args, 'lead_id', 0 ) );
		if ( $lead_id ) {
			$lead = cmp_get_interested_student( $lead_id );
			if ( $lead ) {
				$recipients[] = cmp_communication_lead_recipient( $lead );
			}
			return $recipients;
		}

		foreach ( cmp_get_interested_students( array( 'limit' => 0 ) ) as $lead ) {
			$recipients[] = cmp_communication_lead_recipient( $lead );
		}

		return $recipients;
	}

	if ( 'pending_fee' === $scope ) {
		foreach ( cmp_get_students_with_pending_fees( absint( cmp_field( $args, 'limit', 20 ) ) ) as $student ) {
			$recipients[] = cmp_communication_student_recipient( $student );
		}

		return $recipients;
	}

	if ( 'teacher_students' === $scope ) {
		$teacher_id = absint( cmp_field( $args, 'teacher_user_id', 0 ) );
		foreach ( cmp_get_students( array( 'limit' => 0 ) ) as $student ) {
			if ( $teacher_id && (int) $student->teacher_user_id !== $teacher_id ) {
				continue;
			}
			$recipients[] = cmp_communication_student_recipient( $student );
		}

		return $recipients;
	}

	if ( 'all_teachers' === $scope ) {
		foreach ( cmp_get_teacher_users() as $user ) {
			$recipients[] = (object) array(
				'recipient_type' => 'teacher',
				'id'             => (int) $user->ID,
				'name'           => $user->display_name,
				'email'          => $user->user_email,
				'phone'          => '',
				'class_name'     => '',
				'batch_name'     => '',
			);
		}

		return $recipients;
	}

	foreach ( cmp_get_students( array( 'limit' => 0 ) ) as $student ) {
		$recipients[] = cmp_communication_student_recipient( $student );
	}

	return $recipients;
}

/**
 * Returns a normalized recipient object for a student.
 *
 * @param object $student Student row.
 * @return object
 */
function cmp_communication_student_recipient( $student ) {
	return (object) array(
		'recipient_type' => 'student',
		'id'             => isset( $student->id ) ? (int) $student->id : 0,
		'name'           => isset( $student->name ) ? $student->name : '',
		'email'          => isset( $student->email ) ? $student->email : '',
		'phone'          => isset( $student->phone ) ? $student->phone : '',
		'class_name'     => isset( $student->class_name ) ? $student->class_name : '',
		'batch_name'     => isset( $student->batch_name ) ? $student->batch_name : '',
		'class_id'       => isset( $student->class_id ) ? (int) $student->class_id : 0,
		'batch_id'       => isset( $student->batch_id ) ? (int) $student->batch_id : 0,
		'fee_due_date'   => isset( $student->fee_due_date ) ? $student->fee_due_date : '',
		'remaining_fee'  => isset( $student->remaining_fee ) ? (float) $student->remaining_fee : max( 0, (float) ( $student->total_fee ?? 0 ) - (float) ( $student->paid_fee ?? 0 ) ),
	);
}

/**
 * Returns a normalized recipient object for an interested lead.
 *
 * @param object $lead Lead row.
 * @return object
 */
function cmp_communication_lead_recipient( $lead ) {
	return (object) array(
		'recipient_type' => 'lead',
		'id'             => isset( $lead->id ) ? (int) $lead->id : 0,
		'name'           => isset( $lead->name ) ? $lead->name : '',
		'email'          => isset( $lead->email ) ? $lead->email : '',
		'phone'          => isset( $lead->phone ) ? $lead->phone : '',
		'class_name'     => isset( $lead->class_name ) ? $lead->class_name : '',
		'batch_name'     => isset( $lead->batch_name ) ? $lead->batch_name : '',
		'class_id'       => isset( $lead->class_id ) ? (int) $lead->class_id : 0,
		'batch_id'       => isset( $lead->batch_id ) ? (int) $lead->batch_id : 0,
		'lead_source'    => isset( $lead->lead_source ) ? $lead->lead_source : '',
	);
}

/**
 * Logs a communication message.
 *
 * @param array $data Message payload.
 * @return int|WP_Error
 */
function cmp_log_communication_message( $data ) {
	global $wpdb;

	$payload = wp_parse_args(
		is_array( $data ) ? $data : array(),
		array(
			'campaign_id'     => 0,
			'template_id'     => 0,
			'channel'         => 'email',
			'message_type'    => 'notification',
			'recipient_type'  => 'student',
			'recipient_name'  => '',
			'recipient_email' => '',
			'recipient_phone' => '',
			'subject'         => '',
			'body'            => '',
			'status'          => 'queued',
			'delivery_status' => 'pending',
			'priority'        => 'normal',
			'action_url'      => '',
			'attachment_url'  => '',
			'provider_response' => '',
			'context'         => array(),
			'sent_at'         => null,
			'read_at'         => null,
		)
	);

	$result = $wpdb->insert(
		cmp_communication_table( 'messages' ),
		array(
			'campaign_id'      => absint( $payload['campaign_id'] ),
			'template_id'      => absint( $payload['template_id'] ),
			'channel'          => sanitize_key( $payload['channel'] ),
			'message_type'     => sanitize_key( $payload['message_type'] ),
			'recipient_type'   => sanitize_key( $payload['recipient_type'] ),
			'recipient_name'   => sanitize_text_field( $payload['recipient_name'] ),
			'recipient_email'  => sanitize_email( $payload['recipient_email'] ),
			'recipient_phone'  => sanitize_text_field( $payload['recipient_phone'] ),
			'subject'          => sanitize_text_field( $payload['subject'] ),
			'body'             => wp_kses_post( (string) $payload['body'] ),
			'status'           => sanitize_key( $payload['status'] ),
			'delivery_status'  => sanitize_key( $payload['delivery_status'] ),
			'priority'         => sanitize_key( $payload['priority'] ),
			'action_url'       => esc_url_raw( $payload['action_url'] ),
			'attachment_url'   => esc_url_raw( $payload['attachment_url'] ),
			'provider_response'=> sanitize_textarea_field( (string) $payload['provider_response'] ),
			'context'          => wp_json_encode( is_array( $payload['context'] ) ? $payload['context'] : array() ),
			'sent_at'          => $payload['sent_at'] ? sanitize_text_field( $payload['sent_at'] ) : null,
			'read_at'          => $payload['read_at'] ? sanitize_text_field( $payload['read_at'] ) : null,
			'created_by'       => get_current_user_id(),
			'created_at'       => cmp_current_datetime(),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_communication_message_failed', __( 'Could not save communication log entry.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Returns communication messages.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_communication_messages( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'search'         => '',
			'channel'        => '',
			'status'         => '',
			'recipient_type' => '',
			'priority'       => '',
			'campaign_id'    => 0,
			'limit'          => 0,
			'offset'         => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['search'] ) {
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		$where[] = '(recipient_name LIKE %s OR recipient_email LIKE %s OR recipient_phone LIKE %s OR subject LIKE %s OR body LIKE %s)';
		$params  = array_merge( $params, array( $like, $like, $like, $like, $like ) );
	}

	if ( '' !== $args['channel'] ) {
		$where[]  = 'channel = %s';
		$params[] = sanitize_key( $args['channel'] );
	}

	if ( '' !== $args['status'] ) {
		$where[]  = 'delivery_status = %s';
		$params[] = sanitize_key( $args['status'] );
	}

	if ( '' !== $args['recipient_type'] ) {
		$where[]  = 'recipient_type = %s';
		$params[] = sanitize_key( $args['recipient_type'] );
	}

	if ( '' !== $args['priority'] ) {
		$where[]  = 'priority = %s';
		$params[] = sanitize_key( $args['priority'] );
	}

	if ( ! empty( $args['campaign_id'] ) ) {
		$where[]  = 'campaign_id = %d';
		$params[] = absint( $args['campaign_id'] );
	}

	$sql = 'SELECT * FROM ' . cmp_communication_table( 'messages' );

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY created_at DESC, id DESC';

	if ( ! empty( $args['limit'] ) ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );
		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Builds communication statistics.
 *
 * @return array
 */
function cmp_get_communication_statistics() {
	global $wpdb;

	$total_messages = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'messages' ) );
	$email_sent     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'messages' ) . ' WHERE channel = %s AND delivery_status = %s', 'email', 'sent' ) );
	$whatsapp_sent  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'messages' ) . ' WHERE channel = %s AND delivery_status = %s', 'whatsapp', 'sent' ) );
	$failed         = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'messages' ) . ' WHERE delivery_status = %s', 'failed' ) );
	$read_count     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'messages' ) . ' WHERE read_at IS NOT NULL' );
	$campaign_total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'campaigns' ) );
	$campaign_done  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'campaigns' ) . ' WHERE status = %s', 'completed' ) );
	$campaign_scheduled = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'campaigns' ) . ' WHERE status = %s', 'scheduled' ) );
	$template_total  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'templates' ) . ' WHERE status <> "archived"' );
	$rule_total      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_communication_table( 'automation_rules' ) . ' WHERE status <> "archived"' );

	return array(
		'total_messages'    => $total_messages,
		'email_sent'        => $email_sent,
		'whatsapp_sent'     => $whatsapp_sent,
		'failed'            => $failed,
		'read_count'        => $read_count,
		'delivery_rate'     => $total_messages > 0 ? round( ( ( $email_sent + $whatsapp_sent ) / $total_messages ) * 100, 2 ) : 0,
		'open_rate'         => $total_messages > 0 ? round( ( $read_count / $total_messages ) * 100, 2 ) : 0,
		'campaign_total'    => $campaign_total,
		'campaign_done'     => $campaign_done,
		'campaign_scheduled'=> $campaign_scheduled,
		'template_total'    => $template_total,
		'rule_total'        => $rule_total,
	);
}

/**
 * Returns a message preview URL for WhatsApp.
 *
 * @param string $phone Phone number.
 * @param string $message Message body.
 * @return string
 */
function cmp_communication_build_whatsapp_url( $phone, $message ) {
	$phone_keys = cmp_phone_match_keys( $phone );

	if ( empty( $phone_keys ) ) {
		return '';
	}

	return 'https://wa.me/' . rawurlencode( $phone_keys[0] ) . '?text=' . rawurlencode( wp_strip_all_tags( $message ) );
}

/**
 * Sends or logs a communication message.
 *
 * @param object $recipient Recipient object.
 * @param string $channel Channel.
 * @param string $subject Subject.
 * @param string $message Message body.
 * @param array  $context Extra context.
 * @return array
 */
function cmp_communication_dispatch_message( $recipient, $channel, $subject, $message, $context = array() ) {
	$channel = sanitize_key( $channel );
	$subject = sanitize_text_field( $subject );
	$message = (string) $message;
	$context = is_array( $context ) ? $context : array();
	$attachments = array();

	if ( ! empty( $context['attachment_path'] ) && file_exists( $context['attachment_path'] ) ) {
		$attachments[] = $context['attachment_path'];
	}

	if ( 'whatsapp' === $channel ) {
		$rendered_body = cmp_render_communication_template( $message, $context );
		$phone = isset( $recipient->phone ) ? $recipient->phone : '';
		$action_url = cmp_communication_build_whatsapp_url( $phone, $rendered_body );
		$settings = cmp_get_communication_center_settings();
		$gateway_type = isset( $settings['whatsapp_gateway_type'] ) ? $settings['whatsapp_gateway_type'] : 'wa_link';

		$direct_api_success = false;
		$status = 'sent';
		$response = __( 'WhatsApp message prepared.', 'class-manager-pro' );

		// If direct API is configured (Meta Cloud API or Custom Webhook Gateway)
		if ( in_array( $gateway_type, array( 'custom_webhook', 'meta_cloud' ), true ) && ! empty( $phone ) ) {
			$api_result = cmp_send_direct_whatsapp_message( $phone, $rendered_body, $context );
			if ( ! empty( $api_result['success'] ) ) {
				$direct_api_success = true;
				$status = 'sent';
				$response = ! empty( $api_result['response'] ) ? (string) $api_result['response'] : __( 'Dispatched via WhatsApp API Gateway.', 'class-manager-pro' );
			} else {
				$status = 'failed';
				$response = ! empty( $api_result['error'] ) ? (string) $api_result['error'] : __( 'WhatsApp API call failed.', 'class-manager-pro' );
			}
		}

		cmp_log_communication_message(
			array_merge(
				$context,
				array(
					'channel'          => 'whatsapp',
					'message_type'     => cmp_field( $context, 'message_type', 'notification' ),
					'recipient_type'   => isset( $recipient->recipient_type ) ? $recipient->recipient_type : 'student',
					'recipient_name'   => isset( $recipient->name ) ? $recipient->name : '',
					'recipient_email'  => isset( $recipient->email ) ? $recipient->email : '',
					'recipient_phone'  => $phone,
					'subject'          => $subject,
					'body'             => $rendered_body,
					'status'           => $status,
					'delivery_status'  => $status,
					'priority'         => cmp_field( $context, 'priority', 'normal' ),
					'action_url'       => $action_url,
					'provider_response'=> $response,
					'sent_at'          => cmp_current_datetime(),
				)
			)
		);

		return array(
			'success'    => 'failed' !== $status,
			'message'    => $response,
			'action_url' => $action_url,
			'direct_api' => $direct_api_success,
		);
	}

	$body = cmp_render_communication_template( $message, $context );
	$body = cmp_prepare_email_body( $body );

	if ( empty( $recipient->email ) ) {
		return array(
			'success' => false,
			'message' => __( 'Email address is missing.', 'class-manager-pro' ),
		);
	}

	$sent = ! empty( $attachments )
		? wp_mail( sanitize_email( $recipient->email ), $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), $attachments )
		: wp_mail( sanitize_email( $recipient->email ), $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

	cmp_log_communication_message(
		array_merge(
			$context,
			array(
				'channel'          => 'email',
				'message_type'     => cmp_field( $context, 'message_type', 'notification' ),
				'recipient_type'   => isset( $recipient->recipient_type ) ? $recipient->recipient_type : 'student',
				'recipient_name'   => isset( $recipient->name ) ? $recipient->name : '',
				'recipient_email'  => isset( $recipient->email ) ? $recipient->email : '',
				'recipient_phone'  => isset( $recipient->phone ) ? $recipient->phone : '',
				'subject'          => $subject,
				'body'             => $body,
				'status'           => $sent ? 'sent' : 'failed',
				'delivery_status'  => $sent ? 'sent' : 'failed',
				'priority'         => cmp_field( $context, 'priority', 'normal' ),
				'attachment_url'   => isset( $context['attachment_url'] ) ? esc_url_raw( $context['attachment_url'] ) : '',
				'provider_response'=> $sent ? __( 'Email sent.', 'class-manager-pro' ) : __( 'Email could not be sent.', 'class-manager-pro' ),
				'sent_at'          => $sent ? cmp_current_datetime() : null,
			)
		)
	);

	return array(
		'success' => (bool) $sent,
		'message' => $sent ? __( 'Email sent.', 'class-manager-pro' ) : __( 'Email could not be sent.', 'class-manager-pro' ),
	);
}

/**
 * Runs scheduled campaigns that are due.
 *
 * @return void
 */
function cmp_run_due_communication_campaigns() {
	global $wpdb;

	if ( ! wp_doing_cron() && ! cmp_communication_user_can_access() ) {
		return;
	}

	$now = cmp_current_datetime();
	$campaigns = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_communication_table( 'campaigns' ) . ' WHERE status = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC, id ASC',
			'scheduled',
			$now
		)
	);

	foreach ( $campaigns as $campaign ) {
		cmp_run_communication_campaign( (int) $campaign->id );
	}
}

/**
 * Saves a communication message read status.
 *
 * @param int  $message_id Message ID.
 * @param bool $read Whether message is read.
 * @return true|WP_Error
 */
function cmp_toggle_communication_message_read( $message_id, $read = true ) {
	global $wpdb;

	$message_id = absint( $message_id );

	if ( ! $message_id ) {
		return new WP_Error( 'cmp_communication_message_missing', __( 'Message not found.', 'class-manager-pro' ) );
	}

	$updated = $wpdb->update(
		cmp_communication_table( 'messages' ),
		array(
			'read_at' => $read ? cmp_current_datetime() : null,
		),
		array( 'id' => $message_id ),
		array( '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_communication_message_failed', __( 'Could not update message state.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Processes a newly registered student.
 *
 * @param object $student Student.
 * @param object $batch Batch.
 * @param array  $options Registration options.
 * @param array  $data Raw data.
 * @return void
 */
function cmp_communication_handle_student_registered( $student, $batch, $options, $data ) {
	unset( $options, $data );

	$teacher = ! empty( $batch->teacher_user_id ) ? get_user_by( 'id', (int) $batch->teacher_user_id ) : null;

	if ( $student && ! empty( $student->email ) ) {
		$template = cmp_get_communication_templates( array( 'type' => 'welcome', 'channel' => 'email', 'limit' => 1 ) );
		$message = ! empty( $template ) ? $template[0] : null;
		$body = $message ? $message->body : __( '<p>Hello {{name}},</p><p>Welcome to {{batch_name}}.</p><p>{{signature}}</p>', 'class-manager-pro' );
		$subject = $message && ! empty( $message->subject ) ? $message->subject : __( 'Welcome to {{batch_name}}', 'class-manager-pro' );
		cmp_communication_dispatch_message(
			cmp_communication_student_recipient( $student ),
			'email',
			cmp_render_communication_template( $subject, array( 'student' => $student, 'batch' => $batch ) ),
			$body,
			array(
				'student'     => $student,
				'batch'       => $batch,
				'class'       => cmp_get_class( (int) $batch->class_id ),
				'teacher'     => $teacher,
				'message_type'=> 'welcome',
			)
		);
	}

	// Direct WhatsApp Welcome message via API if enabled
	$comm_settings = cmp_get_communication_center_settings();
	if ( ! empty( $comm_settings['whatsapp_auto_welcome'] ) && $student && ! empty( $student->phone ) ) {
		$wa_template = cmp_get_communication_templates( array( 'type' => 'welcome', 'channel' => 'whatsapp', 'limit' => 1 ) );
		$wa_body = ! empty( $wa_template ) ? $wa_template[0]->body : __( "Hello {{name}},\nWelcome to {{batch_name}}!\nWe are excited to have you on board.", 'class-manager-pro' );
		cmp_communication_dispatch_message(
			cmp_communication_student_recipient( $student ),
			'whatsapp',
			__( 'Welcome to Class', 'class-manager-pro' ),
			$wa_body,
			array(
				'student'      => $student,
				'batch'        => $batch,
				'class'        => cmp_get_class( (int) $batch->class_id ),
				'teacher'      => $teacher,
				'message_type' => 'welcome',
			)
		);
	}

	// Only send teacher notification if opted in (prevents email spam)
	$notify_teacher = (bool) get_option( 'cmp_notify_teacher_on_admission', 0 );
	if ( $notify_teacher && $teacher ) {
		cmp_communication_dispatch_message(
			(object) array(
				'recipient_type' => 'teacher',
				'name'           => $teacher->display_name,
				'email'          => $teacher->user_email,
				'phone'          => '',
			),
			'email',
			sprintf( __( 'New Student Joined: %s', 'class-manager-pro' ), $batch->batch_name ),
			sprintf( __( 'A new student joined %1$s.\n\nStudent: %2$s', 'class-manager-pro' ), $batch->batch_name, $student->name ),
			array(
				'student'      => $student,
				'batch'        => $batch,
				'class'        => cmp_get_class( (int) $batch->class_id ),
				'teacher'      => $teacher,
				'message_type' => 'student_joined',
			)
		);
	}
}

/**
 * Handles fee reminder logging.
 *
 * @param object $student Student.
 * @param string $channel Channel.
 * @param string $status Status.
 * @param string $response_message Response message.
 * @return void
 */
function cmp_communication_handle_fee_reminder_logged( $student, $channel, $status, $response_message ) {
	unset( $status, $response_message );

	if ( ! $student ) {
		return;
	}

	$body = cmp_build_fee_reminder_message( $student, $channel );
	cmp_log_communication_message(
		array(
			'campaign_id'     => 0,
			'template_id'     => 0,
			'channel'         => sanitize_key( $channel ),
			'message_type'    => 'pending_fee',
			'recipient_type'  => 'student',
			'recipient_name'  => $student->name,
			'recipient_email' => $student->email,
			'recipient_phone' => $student->phone,
			'subject'         => cmp_build_fee_reminder_subject( $student ),
			'body'            => $body,
			'status'          => 'sent',
			'delivery_status' => 'sent',
			'priority'        => 'high',
			'sent_at'         => cmp_current_datetime(),
			'context'         => array(
				'student_id' => (int) $student->id,
				'batch_id'   => (int) $student->batch_id,
			),
		)
	);
}

/**
 * Handles payment notifications.
 *
 * @param object $payment Payment row.
 * @param array  $payload Payload.
 * @return void
 */
function cmp_communication_handle_payment_recorded( $payment, $payload ) {
	unset( $payload );

	if ( ! $payment || empty( $payment->student_id ) ) {
		return;
	}

	$student = cmp_get_student( (int) $payment->student_id );

	if ( ! $student ) {
		return;
	}

	$subject = sprintf( __( 'Payment received for %s', 'class-manager-pro' ), $student->name );
	$body    = sprintf( __( 'Payment of %1$s received for %2$s.', 'class-manager-pro' ), cmp_format_money( cmp_get_payment_display_total( $payment ) ), $student->name );

	if ( ! empty( $student->email ) ) {
		cmp_communication_dispatch_message(
			cmp_communication_student_recipient( $student ),
			'email',
			$subject,
			$body,
			array(
				'student'      => $student,
				'payment'      => $payment,
				'message_type' => 'payment_confirmation',
			)
		);
		return;
	}

	cmp_log_communication_message(
		array(
			'channel'         => 'email',
			'message_type'    => 'payment_confirmation',
			'recipient_type'  => 'student',
			'recipient_name'  => $student->name,
			'recipient_email' => '',
			'recipient_phone' => $student->phone,
			'subject'         => $subject,
			'body'            => $body,
			'status'          => 'failed',
			'delivery_status' => 'failed',
			'priority'        => 'normal',
			'sent_at'         => null,
			'context'         => array(
				'payment_id' => (int) $payment->id,
				'student_id' => (int) $payment->student_id,
				'reason'     => 'missing_email',
			),
		)
	);
}

/**
 * Handles batch announcement logs.
 *
 * @param array  $summary Summary payload.
 * @param object $batch Batch.
 * @param string $subject Subject.
 * @param string $message Message.
 * @param bool   $send_whatsapp WhatsApp flag.
 * @param string $email_format Email format.
 * @return void
 */
function cmp_communication_handle_batch_announcement_sent( $summary, $batch, $subject, $message, $send_whatsapp, $email_format ) {
	unset( $email_format );

	if ( ! $batch ) {
		return;
	}

	cmp_log_communication_message(
		array(
			'campaign_id'     => 0,
			'template_id'     => 0,
			'channel'         => $send_whatsapp ? 'whatsapp' : 'email',
			'message_type'    => 'announcement',
			'recipient_type'  => 'batch',
			'recipient_name'  => $batch->batch_name,
			'recipient_email' => '',
			'recipient_phone' => '',
			'subject'         => $subject,
			'body'            => $message,
			'status'          => 'sent',
			'delivery_status' => 'sent',
			'priority'        => 'normal',
			'sent_at'         => cmp_current_datetime(),
			'context'         => array(
				'batch_id'   => (int) $batch->id,
				'class_id'   => (int) $batch->class_id,
				'recipients' => isset( $summary['recipients'] ) ? (int) $summary['recipients'] : 0,
			),
		)
	);
}

/**
 * Handles batch completion notifications.
 *
 * @param array $payload Batch payload.
 * @return void
 */
function cmp_communication_handle_batch_completed( $payload ) {
	if ( empty( $payload['batch'] ) || ! is_object( $payload['batch'] ) ) {
		return;
	}

	$batch = $payload['batch'];
	$teacher = ! empty( $batch->teacher_user_id ) ? get_user_by( 'id', (int) $batch->teacher_user_id ) : null;

	$admin_email = sanitize_email( get_bloginfo( 'admin_email' ) );

	if ( '' !== $admin_email ) {
		cmp_communication_dispatch_message(
			(object) array(
				'recipient_type' => 'admin',
				'name'           => get_bloginfo( 'name' ),
				'email'          => $admin_email,
				'phone'          => '',
			),
			'email',
			sprintf( __( 'Batch Completed: %s', 'class-manager-pro' ), $batch->batch_name ),
			sprintf( __( 'Batch %1$s completed. Please prepare certificates.', 'class-manager-pro' ), $batch->batch_name ),
			array(
				'batch'        => $batch,
				'message_type' => 'certificate_reminder',
				'priority'     => 'high',
			)
		);
	}

	if ( $teacher && ! empty( $teacher->user_email ) ) {
		cmp_communication_dispatch_message(
			(object) array(
				'recipient_type' => 'teacher',
				'name'           => $teacher->display_name,
				'email'          => $teacher->user_email,
				'phone'          => '',
			),
			'email',
			sprintf( __( 'Certificate Reminder: %s', 'class-manager-pro' ), $batch->batch_name ),
			sprintf( __( 'Batch %1$s completed. Please review certificates for the enrolled students.', 'class-manager-pro' ), $batch->batch_name ),
			array(
				'batch'        => $batch,
				'teacher'      => $teacher,
				'message_type' => 'certificate_reminder',
			)
		);
	}
}

/**
 * Handles any batch status change.
 *
 * @param array $payload Batch payload.
 * @return void
 */
function cmp_communication_handle_batch_status_changed( $payload ) {
	if ( empty( $payload['status'] ) || empty( $payload['batch'] ) || ! is_object( $payload['batch'] ) ) {
		return;
	}

	if ( 'completed' === sanitize_key( $payload['status'] ) ) {
		cmp_communication_handle_batch_completed( $payload );
	}
}

/**
 * Returns recent communication history entries.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_communication_history( $args = array() ) {
	return cmp_get_communication_messages( $args );
}

/**
 * Returns a structured communication summary.
 *
 * @return array
 */
function cmp_get_communication_dashboard_summary() {
	$stats = cmp_get_communication_statistics();

	return array(
		'cards' => array(
			'total_messages' => $stats['total_messages'],
			'email_sent'     => $stats['email_sent'],
			'whatsapp_sent'  => $stats['whatsapp_sent'],
			'delivery_rate'   => $stats['delivery_rate'],
			'open_rate'       => $stats['open_rate'],
			'campaign_total'  => $stats['campaign_total'],
		),
		'stats' => $stats,
	);
}

/**
 * Generates a searchable communication summary count.
 *
 * @param array $args Filters.
 * @return int
 */
function cmp_count_communication_history( $args = array() ) {
	global $wpdb;

	$query = cmp_get_communication_messages( array_merge( $args, array( 'limit' => 0 ) ) );

	return is_array( $query ) ? count( $query ) : 0;
}

/**
 * Returns a communication section slug for a page.
 *
 * @param string $page_slug Page slug.
 * @return string
 */
function cmp_communication_section_from_page( $page_slug ) {
	$map = array(
		'cmp-communication-center' => 'dashboard',
		'cmp-communication-center-email' => 'email',
		'cmp-communication-center-whatsapp' => 'whatsapp',
		'cmp-communication-center-campaigns' => 'campaigns',
		'cmp-communication-center-templates' => 'templates',
		'cmp-communication-center-automation' => 'automation',
		'cmp-communication-center-history' => 'history',
		'cmp-communication-center-statistics' => 'statistics',
		'cmp-communication-center-settings' => 'settings',
	);

	$page_slug = sanitize_key( $page_slug );

	return isset( $map[ $page_slug ] ) ? $map[ $page_slug ] : 'dashboard';
}

/**
 * Returns the Communication Center page list.
 *
 * @return array
 */
function cmp_communication_section_tabs() {
	return array(
		array( 'key' => 'dashboard', 'label' => __( 'Overview', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center' ) ),
		array( 'key' => 'email', 'label' => __( 'Email Center', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-email' ) ),
		array( 'key' => 'whatsapp', 'label' => __( 'WhatsApp Center', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-whatsapp' ) ),
		array( 'key' => 'campaigns', 'label' => __( 'Campaigns', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-campaigns' ) ),
		array( 'key' => 'templates', 'label' => __( 'Templates', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-templates' ) ),
		array( 'key' => 'automation', 'label' => __( 'Automation', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-automation' ) ),
		array( 'key' => 'history', 'label' => __( 'History', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-history' ) ),
		array( 'key' => 'statistics', 'label' => __( 'Statistics', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-statistics' ) ),
		array( 'key' => 'settings', 'label' => __( 'Settings', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-communication-center-settings' ) ),
	);
}

/**
 * Returns the Communication Center empty-state default message.
 *
 * @return string
 */
function cmp_communication_empty_state() {
	return __( 'Nothing has been sent yet. Create a template, send a campaign, or trigger an automation to populate the history.', 'class-manager-pro' );
}

/**
 * Sends a direct WhatsApp message through Meta Cloud API or Custom Gateway Webhook.
 *
 * @param string $phone Student or recipient phone.
 * @param string $message Message content.
 * @param array  $context Extra context.
 * @return array
 */
function cmp_send_direct_whatsapp_message( $phone, $message, $context = array() ) {
	$settings     = cmp_get_communication_center_settings();
	$gateway_type = isset( $settings['whatsapp_gateway_type'] ) ? $settings['whatsapp_gateway_type'] : 'wa_link';

	// Clean phone number: digits only
	$clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
	if ( empty( $clean_phone ) ) {
		return array(
			'success' => false,
			'error'   => __( 'Invalid or missing phone number.', 'class-manager-pro' ),
		);
	}

	// Prepend default 91 country code if 10 digits
	if ( 10 === strlen( $clean_phone ) ) {
		$clean_phone = '91' . $clean_phone;
	}

	$message_text = trim( (string) $message );
	if ( '' === $message_text ) {
		return array(
			'success' => false,
			'error'   => __( 'Message text cannot be empty.', 'class-manager-pro' ),
		);
	}

	if ( 'wa_link' === $gateway_type ) {
		$action_url = cmp_communication_build_whatsapp_url( $clean_phone, $message_text );
		return array(
			'success'    => true,
			'mode'       => 'link',
			'action_url' => $action_url,
			'message'    => __( 'Click-to-chat WhatsApp link prepared.', 'class-manager-pro' ),
		);
	}

	if ( 'meta_cloud' === $gateway_type ) {
		$phone_id = isset( $settings['whatsapp_phone_number_id'] ) ? trim( $settings['whatsapp_phone_number_id'] ) : '';
		$token    = isset( $settings['whatsapp_api_token'] ) ? trim( $settings['whatsapp_api_token'] ) : '';
		$api_url  = ! empty( $settings['whatsapp_api_url'] ) ? $settings['whatsapp_api_url'] : "https://graph.facebook.com/v19.0/{$phone_id}/messages";

		if ( empty( $phone_id ) || empty( $token ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Meta Cloud API Phone Number ID or Token missing.', 'class-manager-pro' ),
			);
		}

		$payload = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $clean_phone,
			'type'              => 'text',
			'text'              => array(
				'preview_url' => false,
				'body'        => $message_text,
			),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 20,
			)
		);
	} else {
		// Generic / Custom Webhook Gateway (Wati, UltraMsg, AiSensy, custom endpoint)
		$api_url = isset( $settings['whatsapp_api_url'] ) ? trim( $settings['whatsapp_api_url'] ) : '';
		$token   = isset( $settings['whatsapp_api_token'] ) ? trim( $settings['whatsapp_api_token'] ) : '';

		if ( empty( $api_url ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Custom WhatsApp Gateway Webhook URL is missing.', 'class-manager-pro' ),
			);
		}

		$payload = array(
			'phone'        => $clean_phone,
			'message'      => $message_text,
			'student_name' => isset( $context['student']->name ) ? $context['student']->name : '',
			'batch_name'   => isset( $context['batch']->batch_name ) ? $context['batch']->batch_name : '',
			'event'        => isset( $context['message_type'] ) ? $context['message_type'] : 'direct_message',
			'timestamp'    => current_time( 'mysql' ),
		);

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
			$headers['X-Api-Key']     = $token;
		}

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => 20,
			)
		);
	}

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code >= 200 && $code < 300 ) {
		return array(
			'success'  => true,
			'mode'     => 'api',
			'response' => $body,
		);
	}

	return array(
		'success' => false,
		'error'   => sprintf( __( 'WhatsApp API returned HTTP %1$d: %2$s', 'class-manager-pro' ), $code, substr( $body, 0, 160 ) ),
	);
}

/**
 * Maybe sends direct WhatsApp welcome message on student registration.
 *
 * @param object $student Student.
 * @param object $batch Batch.
 * @return array|false
 */
function cmp_maybe_send_direct_whatsapp_welcome( $student, $batch ) {
	$settings = cmp_get_communication_center_settings();
	if ( empty( $settings['whatsapp_auto_welcome'] ) ) {
		return false;
	}

	$phone = isset( $student->phone ) ? $student->phone : '';
	if ( empty( $phone ) ) {
		return false;
	}

	$templates = cmp_get_communication_templates( array( 'type' => 'welcome', 'channel' => 'whatsapp', 'limit' => 1 ) );
	$msg_body  = ! empty( $templates ) ? $templates[0]->body : __( "Hello {{name}},\nWelcome to {{batch_name}}!\nWe are excited to have you on board.", 'class-manager-pro' );

	$context = array(
		'student'      => $student,
		'batch'        => $batch,
		'message_type' => 'welcome',
	);

	$rendered = cmp_render_communication_template( $msg_body, $context );
	return cmp_send_direct_whatsapp_message( $phone, $rendered, $context );
}

/**
 * Registers REST API webhook routes for WhatsApp API integrations.
 */
function cmp_register_whatsapp_webhook_routes() {
	register_rest_route(
		'cmp/v1',
		'/whatsapp-webhook',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'cmp_handle_whatsapp_webhook_verification',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'cmp_handle_whatsapp_webhook_event',
				'permission_callback' => '__return_true',
			),
		)
	);
}

/**
 * Handles incoming verification handshake for WhatsApp API (Meta Cloud Webhook challenge).
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function cmp_handle_whatsapp_webhook_verification( WP_REST_Request $request ) {
	$settings     = cmp_get_communication_center_settings();
	$saved_secret = ! empty( $settings['whatsapp_webhook_secret'] ) ? $settings['whatsapp_webhook_secret'] : '';

	$mode      = $request->get_param( 'hub_mode' ) ?: $request->get_param( 'hub.mode' );
	$token     = $request->get_param( 'hub_verify_token' ) ?: $request->get_param( 'hub.verify_token' );
	$challenge = $request->get_param( 'hub_challenge' ) ?: $request->get_param( 'hub.challenge' );

	if ( 'subscribe' === $mode && ! empty( $token ) && ( $token === $saved_secret || empty( $saved_secret ) ) ) {
		header( 'Content-Type: text/plain' );
		echo esc_html( $challenge );
		exit;
	}

	return new WP_REST_Response(
		array(
			'status'  => 'active',
			'service' => 'Class Manager Pro WhatsApp Webhook Gateway',
			'gateway' => $settings['whatsapp_gateway_type'],
		),
		200
	);
}

/**
 * Handles incoming WhatsApp webhook events (delivery reports and incoming student messages).
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function cmp_handle_whatsapp_webhook_event( WP_REST_Request $request ) {
	$data = $request->get_json_params();
	if ( empty( $data ) ) {
		$data = $request->get_params();
	}

	global $wpdb;

	// 1. Handle Meta Cloud API webhook events
	if ( ! empty( $data['entry'][0]['changes'][0]['value']['statuses'][0] ) ) {
		$status_obj   = $data['entry'][0]['changes'][0]['value']['statuses'][0];
		$raw_status   = sanitize_key( isset( $status_obj['status'] ) ? $status_obj['status'] : '' );
		$recipient_id = sanitize_text_field( isset( $status_obj['recipient_id'] ) ? $status_obj['recipient_id'] : '' );

		$normalized_status = in_array( $raw_status, array( 'delivered', 'read', 'failed', 'sent' ), true ) ? $raw_status : 'sent';

		if ( ! empty( $recipient_id ) ) {
			$clean_phone = substr( preg_replace( '/[^0-9]/', '', $recipient_id ), -10 );
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . cmp_communication_table( 'messages' ) . '
					SET delivery_status = %s, read_at = %s
					WHERE channel = "whatsapp" AND recipient_phone LIKE %s
					ORDER BY id DESC LIMIT 1',
					$normalized_status,
					'read' === $normalized_status ? cmp_current_datetime() : null,
					'%' . $wpdb->esc_like( $clean_phone )
				)
			);
		}
	}

	// 2. Handle generic webhook format: { "phone": "...", "status": "delivered", "message": "..." }
	if ( ! empty( $data['phone'] ) ) {
		$clean_phone  = substr( preg_replace( '/[^0-9]/', '', (string) $data['phone'] ), -10 );
		$status       = sanitize_key( cmp_field( $data, 'status', 'delivered' ) );
		$incoming_msg = sanitize_textarea_field( cmp_field( $data, 'message', '' ) );

		if ( ! empty( $incoming_msg ) ) {
			$student = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id, name, email, phone FROM ' . cmp_table( 'students' ) . ' WHERE phone LIKE %s ORDER BY id DESC LIMIT 1',
					'%' . $wpdb->esc_like( $clean_phone )
				)
			);

			cmp_log_communication_message(
				array(
					'channel'          => 'whatsapp',
					'message_type'     => 'incoming',
					'recipient_type'   => 'student',
					'recipient_name'   => $student ? $student->name : __( 'Student', 'class-manager-pro' ),
					'recipient_email'  => $student ? $student->email : '',
					'recipient_phone'  => $clean_phone,
					'subject'          => __( 'Incoming WhatsApp Reply', 'class-manager-pro' ),
					'body'             => $incoming_msg,
					'status'           => 'received',
					'delivery_status'  => 'delivered',
					'priority'         => 'normal',
					'sent_at'          => cmp_current_datetime(),
					'read_at'          => cmp_current_datetime(),
				)
			);
		}
	}

	return new WP_REST_Response( array( 'success' => true ), 200 );
}
