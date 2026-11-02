<?php
/**
 * Direct WhatsApp API Integration & Webhook Handler for Class Manager Pro.
 *
 * Supports Meta WhatsApp Cloud API and Custom WhatsApp Gateway Webhooks (Wati, AiSensy, UltraMsg, WA-Sender, etc.)
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'cmp_register_whatsapp_webhook_routes' );
add_action( 'wp_ajax_cmp_test_whatsapp_connection', 'cmp_ajax_test_whatsapp_connection' );
add_action( 'wp_ajax_cmp_send_student_direct_whatsapp', 'cmp_ajax_send_student_direct_whatsapp' );
add_action( 'admin_post_cmp_save_whatsapp_settings', 'cmp_handle_save_whatsapp_settings' );

/**
 * Saves WhatsApp integration settings from admin settings page.
 */
function cmp_handle_save_whatsapp_settings() {
	if ( function_exists( 'cmp_require_manage_options' ) ) {
		cmp_require_manage_options();
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized user.', 'class-manager-pro' ) );
	}

	check_admin_referer( 'cmp_save_whatsapp_settings' );

	update_option( 'cmp_whatsapp_enabled', ! empty( $_POST['whatsapp_enabled'] ) ? '1' : '0' );
	update_option( 'cmp_whatsapp_auto_welcome', ! empty( $_POST['whatsapp_auto_welcome'] ) ? '1' : '0' );
	update_option( 'cmp_whatsapp_gateway_type', sanitize_key( cmp_field( $_POST, 'whatsapp_gateway_type', 'meta_cloud' ) ) );
	update_option( 'cmp_whatsapp_api_url', esc_url_raw( cmp_field( $_POST, 'whatsapp_api_url' ) ) );
	update_option( 'cmp_whatsapp_api_token', sanitize_text_field( cmp_field( $_POST, 'whatsapp_api_token' ) ) );
	update_option( 'cmp_whatsapp_phone_number_id', sanitize_text_field( cmp_field( $_POST, 'whatsapp_phone_number_id' ) ) );
	update_option( 'cmp_whatsapp_sender', sanitize_text_field( cmp_field( $_POST, 'whatsapp_sender' ) ) );
	update_option( 'cmp_whatsapp_webhook_verify_token', sanitize_text_field( cmp_field( $_POST, 'whatsapp_webhook_verify_token' ) ) );
	update_option( 'cmp_whatsapp_welcome_template', sanitize_textarea_field( cmp_field( $_POST, 'whatsapp_welcome_template' ) ) );

	if ( function_exists( 'cmp_redirect' ) ) {
		cmp_redirect( add_query_arg( array( 'tab' => 'whatsapp' ), 'cmp-settings' ), __( 'WhatsApp integration settings saved successfully.', 'class-manager-pro' ) );
	} else {
		wp_safe_redirect( admin_url( 'admin.php?page=cmp-settings&tab=whatsapp&cmp_notice=' . rawurlencode( __( 'WhatsApp integration settings saved successfully.', 'class-manager-pro' ) ) ) );
		exit;
	}
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
 * Handles incoming verification handshake for Meta WhatsApp Cloud API webhook.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|void
 */
function cmp_handle_whatsapp_webhook_verification( WP_REST_Request $request ) {
	$saved_token = trim( (string) get_option( 'cmp_whatsapp_webhook_verify_token', get_option( 'cmp_notification_auth_token', '' ) ) );

	$mode      = $request->get_param( 'hub_mode' ) ?: $request->get_param( 'hub.mode' );
	$token     = $request->get_param( 'hub_verify_token' ) ?: $request->get_param( 'hub.verify_token' );
	$challenge = $request->get_param( 'hub_challenge' ) ?: $request->get_param( 'hub.challenge' );

	if ( 'subscribe' === $mode && ! empty( $token ) && ( '' === $saved_token || $token === $saved_token ) ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $challenge );
		exit;
	}

	return new WP_REST_Response(
		array(
			'success' => false,
			'message' => __( 'Verification token mismatch.', 'class-manager-pro' ),
		),
		403
	);
}

/**
 * Handles incoming WhatsApp webhook events (status receipts, incoming messages).
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function cmp_handle_whatsapp_webhook_event( WP_REST_Request $request ) {
	$data = $request->get_json_params();

	if ( empty( $data ) || ! is_array( $data ) ) {
		$raw  = $request->get_body();
		$data = json_decode( $raw, true );
	}

	if ( empty( $data ) || ! is_array( $data ) ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => 'Empty payload' ), 400 );
	}

	// 1. Meta WhatsApp Cloud webhook format
	if ( isset( $data['entry'][0]['changes'][0]['value'] ) ) {
		$value = $data['entry'][0]['changes'][0]['value'];

		// Delivery status updates (sent, delivered, read, failed)
		if ( ! empty( $value['statuses'] ) && is_array( $value['statuses'] ) ) {
			foreach ( $value['statuses'] as $status_event ) {
				$msg_id    = isset( $status_event['id'] ) ? sanitize_text_field( $status_event['id'] ) : '';
				$recipient = isset( $status_event['recipient_id'] ) ? sanitize_text_field( $status_event['recipient_id'] ) : '';
				$status    = isset( $status_event['status'] ) ? sanitize_key( $status_event['status'] ) : '';

				if ( function_exists( 'cmp_log_admin_action' ) ) {
					cmp_log_admin_action(
						'whatsapp_status_' . $status,
						'whatsapp',
						0,
						sprintf( __( 'WhatsApp status "%1$s" for %2$s (ID: %3$s)', 'class-manager-pro' ), $status, $recipient, $msg_id )
					);
				}
			}
		}

		// Incoming student message
		if ( ! empty( $value['messages'] ) && is_array( $value['messages'] ) ) {
			foreach ( $value['messages'] as $incoming ) {
				$from = isset( $incoming['from'] ) ? sanitize_text_field( $incoming['from'] ) : '';
				$text = isset( $incoming['text']['body'] ) ? sanitize_text_field( $incoming['text']['body'] ) : '';

				if ( function_exists( 'cmp_log_admin_action' ) ) {
					cmp_log_admin_action(
						'whatsapp_reply',
						'whatsapp',
						0,
						sprintf( __( 'Incoming WhatsApp message from %1$s: %2$s', 'class-manager-pro' ), $from, $text )
					);
				}
			}
		}

		return new WP_REST_Response( array( 'success' => true, 'status' => 'handled' ), 200 );
	}

	// 2. Generic / Custom Gateway webhook format (AiSensy, Wati, UltraMsg, custom bot)
	$phone   = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : ( isset( $data['to'] ) ? sanitize_text_field( $data['to'] ) : '' );
	$status  = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : ( isset( $data['event'] ) ? sanitize_key( $data['event'] ) : 'received' );
	$message = isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : '';

	if ( function_exists( 'cmp_log_admin_action' ) ) {
		cmp_log_admin_action(
			'whatsapp_webhook_' . $status,
			'whatsapp',
			0,
			sprintf( __( 'WhatsApp Gateway Webhook (%1$s) - Phone: %2$s | Details: %3$s', 'class-manager-pro' ), $status, $phone, $message )
		);
	}

	return new WP_REST_Response( array( 'success' => true, 'status' => 'acknowledged' ), 200 );
}

/**
 * Gets configured WhatsApp settings.
 *
 * @return array
 */
function cmp_get_whatsapp_settings() {
	return array(
		'enabled'              => '1' === (string) get_option( 'cmp_whatsapp_enabled', '0' ),
		'auto_welcome'         => '1' === (string) get_option( 'cmp_whatsapp_auto_welcome', '1' ),
		'gateway_type'         => (string) get_option( 'cmp_whatsapp_gateway_type', 'meta_cloud' ),
		'api_url'              => (string) get_option( 'cmp_whatsapp_api_url', '' ),
		'api_token'            => (string) get_option( 'cmp_whatsapp_api_token', '' ),
		'phone_number_id'      => (string) get_option( 'cmp_whatsapp_phone_number_id', '' ),
		'sender'               => (string) get_option( 'cmp_whatsapp_sender', get_bloginfo( 'name' ) ),
		'webhook_verify_token' => (string) get_option( 'cmp_whatsapp_webhook_verify_token', '' ),
		'welcome_template'     => (string) get_option( 'cmp_whatsapp_welcome_template', cmp_get_default_whatsapp_welcome_template() ),
	);
}

/**
 * Returns the default WhatsApp welcome message template.
 *
 * @return string
 */
function cmp_get_default_whatsapp_welcome_template() {
	return "Hello {{name}},\n\nWelcome to {{site_name}}! 🎓\nYour admission has been confirmed for *{{batch_name}}* ({{class_name}}).\n\n• Fee Status: {{fee_status}}\n• Portal Login: {{login_url}}\n\nIf you have any questions, feel free to reply directly to this message. We wish you a wonderful learning journey!";
}

/**
 * Formats a phone number for WhatsApp API delivery (standardizes to digits, prepends Indian country code if 10-digit).
 *
 * @param string $phone Raw phone number.
 * @return string
 */
function cmp_format_whatsapp_phone( $phone ) {
	$clean = preg_replace( '/[^0-9]/', '', (string) $phone );

	if ( empty( $clean ) ) {
		return '';
	}

	// If 10 digits (standard Indian mobile number without country code), prepend 91
	if ( 10 === strlen( $clean ) ) {
		$clean = '91' . $clean;
	}

	return $clean;
}

/**
 * Sends a direct WhatsApp message to a student/phone through the configured WhatsApp API.
 *
 * @param string $phone Student phone number.
 * @param string $message Text message.
 * @param array  $context Extra metadata context.
 * @return array Result array with 'success' and 'message'.
 */
function cmp_send_direct_whatsapp_message( $phone, $message, $context = array() ) {
	$settings     = cmp_get_whatsapp_settings();
	$gateway_type = $settings['gateway_type'];
	$clean_phone  = cmp_format_whatsapp_phone( $phone );

	if ( empty( $clean_phone ) ) {
		return array(
			'success' => false,
			'message' => __( 'Invalid or missing phone number.', 'class-manager-pro' ),
		);
	}

	$message_text = trim( (string) $message );
	if ( '' === $message_text ) {
		return array(
			'success' => false,
			'message' => __( 'Message text cannot be empty.', 'class-manager-pro' ),
		);
	}

	// Mode 1: Fallback wa.me direct chat link
	if ( 'wa_link' === $gateway_type ) {
		$link = 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode( $message_text );
		return array(
			'success'    => true,
			'mode'       => 'link',
			'action_url' => $link,
			'message'    => __( 'Direct WhatsApp link generated.', 'class-manager-pro' ),
		);
	}

	// Mode 2: Meta WhatsApp Cloud API (Graph API)
	if ( 'meta_cloud' === $gateway_type ) {
		$phone_id = trim( $settings['phone_number_id'] );
		$token    = trim( $settings['api_token'] );
		$api_url  = ! empty( $settings['api_url'] ) ? trim( $settings['api_url'] ) : "https://graph.facebook.com/v19.0/{$phone_id}/messages";

		if ( empty( $phone_id ) || empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Meta Cloud API Phone Number ID or Access Token is missing in Settings.', 'class-manager-pro' ),
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

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $body['messages'][0]['id'] ) ) {
			if ( function_exists( 'cmp_log_admin_action' ) ) {
				cmp_log_admin_action(
					'whatsapp_sent',
					'student',
					isset( $context['student_id'] ) ? (int) $context['student_id'] : 0,
					sprintf( __( 'Direct WhatsApp sent to %1$s (Meta ID: %2$s)', 'class-manager-pro' ), $clean_phone, $body['messages'][0]['id'] )
				);
			}

			return array(
				'success'    => true,
				'message_id' => $body['messages'][0]['id'],
				'message'    => __( 'WhatsApp message delivered via Cloud API.', 'class-manager-pro' ),
			);
		}

		$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : sprintf( __( 'HTTP error %d', 'class-manager-pro' ), $code );
		return array(
			'success' => false,
			'message' => $error_msg,
		);
	}

	// Mode 3: Custom WhatsApp Gateway Webhook (Wati, UltraMsg, AiSensy, WA-Sender, custom bot)
	$api_url = trim( $settings['api_url'] );
	$token   = trim( $settings['api_token'] );

	if ( empty( $api_url ) ) {
		return array(
			'success' => false,
			'message' => __( 'Custom WhatsApp Gateway Webhook URL is missing in Settings.', 'class-manager-pro' ),
		);
	}

	$payload = array(
		'phone'        => $clean_phone,
		'message'      => $message_text,
		'student_name' => isset( $context['student_name'] ) ? $context['student_name'] : '',
		'batch_name'   => isset( $context['batch_name'] ) ? $context['batch_name'] : '',
		'class_name'   => isset( $context['class_name'] ) ? $context['class_name'] : '',
		'event'        => isset( $context['event'] ) ? $context['event'] : 'direct_student_message',
		'sender'       => $settings['sender'],
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

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code >= 200 && $code < 300 ) {
		if ( function_exists( 'cmp_log_admin_action' ) ) {
			cmp_log_admin_action(
				'whatsapp_sent',
				'student',
				isset( $context['student_id'] ) ? (int) $context['student_id'] : 0,
				sprintf( __( 'Direct WhatsApp sent to %1$s via Gateway Webhook', 'class-manager-pro' ), $clean_phone )
			);
		}

		return array(
			'success' => true,
			'message' => __( 'WhatsApp message dispatched to Gateway Webhook.', 'class-manager-pro' ),
		);
	}

	return array(
		'success' => false,
		'message' => sprintf( __( 'Gateway responded with HTTP %d', 'class-manager-pro' ), $code ),
	);
}

/**
 * Sends automated WhatsApp welcome message to a student upon batch admission.
 *
 * @param object $student Student record.
 * @param object $batch Batch record.
 * @return array|false
 */
function cmp_maybe_send_direct_whatsapp_welcome( $student, $batch ) {
	$settings = cmp_get_whatsapp_settings();

	if ( ! $settings['enabled'] || ! $settings['auto_welcome'] ) {
		return false;
	}

	$phone = isset( $student->phone ) ? $student->phone : '';
	if ( empty( $phone ) ) {
		return false;
	}

	$student_id = isset( $student->id ) ? (int) $student->id : 0;
	$class_id   = isset( $batch->class_id ) ? (int) $batch->class_id : ( isset( $student->class_id ) ? (int) $student->class_id : 0 );
	$class_name = ! empty( $batch->class_name ) ? $batch->class_name : ( function_exists( 'cmp_get_class' ) && $class_id ? ( $c = cmp_get_class( $class_id ) ) ? $c->name : '' : '' );
	$batch_name = ! empty( $batch->batch_name ) ? $batch->batch_name : '';

	$total_fee   = isset( $student->total_fee ) ? (float) $student->total_fee : 0.0;
	$paid_fee    = isset( $student->paid_fee ) ? (float) $student->paid_fee : 0.0;
	$pending_fee = max( 0, $total_fee - $paid_fee );

	$fee_status = ( $pending_fee <= 0 && $total_fee > 0 ) ? __( 'Fully Paid', 'class-manager-pro' ) : ( $paid_fee > 0 ? sprintf( __( 'Partial (₹%s Due)', 'class-manager-pro' ), number_format_i18n( $pending_fee ) ) : ( $total_fee > 0 ? sprintf( __( 'Pending ₹%s', 'class-manager-pro' ), number_format_i18n( $pending_fee ) ) : __( 'Free / Included', 'class-manager-pro' ) ) );

	$replacements = array(
		'{{name}}'        => ! empty( $student->name ) ? $student->name : __( 'Student', 'class-manager-pro' ),
		'{{student_name}}'=> ! empty( $student->name ) ? $student->name : __( 'Student', 'class-manager-pro' ),
		'{{class_name}}'  => $class_name,
		'{{batch_name}}'  => $batch_name,
		'{{total_fee}}'   => function_exists( 'cmp_format_money' ) ? cmp_format_money( $total_fee ) : '₹' . $total_fee,
		'{{paid_fee}}'    => function_exists( 'cmp_format_money' ) ? cmp_format_money( $paid_fee ) : '₹' . $paid_fee,
		'{{pending_fee}}' => function_exists( 'cmp_format_money' ) ? cmp_format_money( $pending_fee ) : '₹' . $pending_fee,
		'{{fee_status}}'  => $fee_status,
		'{{login_url}}'   => wp_login_url(),
		'{{site_name}}'   => get_bloginfo( 'name' ),
		'{{due_date}}'    => ! empty( $batch->fee_due_date ) ? $batch->fee_due_date : __( 'N/A', 'class-manager-pro' ),
	);

	$template = $settings['welcome_template'];
	$message  = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

	$context = array(
		'student_id'   => $student_id,
		'student_name' => $student->name,
		'batch_name'   => $batch_name,
		'class_name'   => $class_name,
		'event'        => 'student_admission_welcome',
	);

	return cmp_send_direct_whatsapp_message( $phone, $message, $context );
}

/**
 * AJAX handler to test WhatsApp API connection.
 */
function cmp_ajax_test_whatsapp_connection() {
	check_ajax_referer( 'cmp_admin_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
	}

	$test_phone = sanitize_text_field( cmp_field( $_POST, 'phone' ) );
	if ( empty( $test_phone ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a test phone number.', 'class-manager-pro' ) ) );
	}

	$message = sprintf(
		__( "Hello from %1\$s! 🚀\nYour WhatsApp API integration with Class Manager Pro is working successfully!\nTime: %2\$s", 'class-manager-pro' ),
		get_bloginfo( 'name' ),
		current_time( 'Y-m-d H:i:s' )
	);

	$result = cmp_send_direct_whatsapp_message( $test_phone, $message, array( 'event' => 'connection_test' ) );

	if ( ! empty( $result['success'] ) ) {
		wp_send_json_success( array( 'message' => $result['message'], 'mode' => isset( $result['mode'] ) ? $result['mode'] : 'api', 'action_url' => isset( $result['action_url'] ) ? $result['action_url'] : '' ) );
	} else {
		wp_send_json_error( array( 'message' => isset( $result['message'] ) ? $result['message'] : __( 'Delivery failed.', 'class-manager-pro' ) ) );
	}
}

/**
 * AJAX handler to send direct WhatsApp message to a specific student.
 */
function cmp_ajax_send_student_direct_whatsapp() {
	check_ajax_referer( 'cmp_admin_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ), 403 );
	}

	$student_id = absint( cmp_field( $_POST, 'student_id', 0 ) );
	$student    = function_exists( 'cmp_get_student' ) ? cmp_get_student( $student_id ) : null;

	if ( ! $student ) {
		wp_send_json_error( array( 'message' => __( 'Student not found.', 'class-manager-pro' ) ), 404 );
	}

	if ( empty( $student->phone ) ) {
		wp_send_json_error( array( 'message' => __( 'Student has no phone number on record.', 'class-manager-pro' ) ), 400 );
	}

	$batch  = ! empty( $student->batch_id ) && function_exists( 'cmp_get_batch' ) ? cmp_get_batch( (int) $student->batch_id ) : null;
	$result = cmp_maybe_send_direct_whatsapp_welcome( $student, $batch );

	if ( false === $result ) {
		wp_send_json_error( array( 'message' => __( 'WhatsApp API delivery is disabled in Settings. Please enable it under Settings > WhatsApp API.', 'class-manager-pro' ) ), 400 );
	}

	if ( ! empty( $result['success'] ) ) {
		wp_send_json_success( array( 'message' => __( 'Direct WhatsApp message dispatched successfully!', 'class-manager-pro' ) ) );
	}

	wp_send_json_error( array( 'message' => ! empty( $result['message'] ) ? $result['message'] : __( 'Delivery failed.', 'class-manager-pro' ) ) );
}
