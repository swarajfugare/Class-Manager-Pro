<?php
/**
 * Next-version feature helpers for Class Manager Pro.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_cmp_generate_batch_registration_link', 'cmp_handle_generate_batch_registration_link' );
add_action( 'admin_post_cmp_close_batch_registration_link', 'cmp_handle_close_batch_registration_link' );
add_action( 'admin_post_cmp_send_batch_announcement', 'cmp_handle_send_batch_announcement' );
add_action( 'admin_post_cmp_retry_batch_announcement_failed_emails', 'cmp_handle_retry_batch_announcement_failed_emails' );
add_action( 'admin_post_cmp_send_interested_student_email', 'cmp_handle_send_interested_student_email' );
add_action( 'admin_post_cmp_save_interested_student_follow_up', 'cmp_handle_save_interested_student_follow_up' );
add_action( 'admin_post_cmp_export_interested_students', 'cmp_handle_export_interested_students' );
add_action( 'wp_ajax_cmp_send_interested_student_email_ajax', 'cmp_ajax_send_interested_student_email' );
add_action( 'wp_ajax_cmp_save_interested_student_follow_up_ajax', 'cmp_ajax_save_interested_student_follow_up' );

/**
 * Returns the reporting amount SQL expression for payment revenue.
 *
 * @param string $payment_alias Payments table alias.
 * @return string
 */
function cmp_payment_reporting_amount_sql( $payment_alias = 'p' ) {
	$payment_alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $payment_alias );

	if ( '' === $payment_alias ) {
		$payment_alias = 'p';
	}

	return 'COALESCE(NULLIF(' . $payment_alias . '.final_amount, 0), ROUND(GREATEST(' . $payment_alias . '.amount - COALESCE(' . $payment_alias . '.charge_amount, 0), 0), 2), ' . $payment_alias . '.amount)';
}

/**
 * Calculates the Razorpay gateway charge amount.
 *
 * @param float $amount Base amount.
 * @return float
 */
function cmp_calculate_razorpay_charge_amount( $amount ) {
	$amount = cmp_money_value( $amount );

	if ( $amount <= 0 ) {
		return 0;
	}

	return round( $amount * 0.0236, 2 );
}

/**
 * Builds stored payment financial values.
 *
 * @param float $amount Base amount applied to the student fee.
 * @param bool  $apply_gateway_charge Whether the Razorpay charge should be deducted.
 * @return array
 */
function cmp_get_payment_financial_breakdown( $amount, $apply_gateway_charge = false ) {
	$amount = round( (float) cmp_money_value( $amount ), 2 );
	$charge = $apply_gateway_charge ? cmp_calculate_razorpay_charge_amount( $amount ) : 0;

	return array(
		'original_amount' => $amount,
		'charge_amount'   => round( (float) $charge, 2 ),
		'final_amount'    => round( max( $amount - (float) $charge, 0 ), 2 ),
	);
}

/**
 * Returns whether a stored payment includes a gateway charge.
 *
 * @param object|array|null $payment Payment row.
 * @return bool
 */
function cmp_payment_has_gateway_charge( $payment ) {
	if ( is_object( $payment ) ) {
		return isset( $payment->charge_amount ) && (float) $payment->charge_amount > 0;
	}

	if ( is_array( $payment ) ) {
		return isset( $payment['charge_amount'] ) && (float) $payment['charge_amount'] > 0;
	}

	return false;
}

/**
 * Returns the displayed total amount for one payment row.
 *
 * @param object|array|null $payment Payment row.
 * @return float
 */
function cmp_get_payment_display_total( $payment ) {
	if ( is_object( $payment ) ) {
		if ( isset( $payment->final_amount ) ) {
			return (float) $payment->final_amount;
		}

		return isset( $payment->amount ) ? (float) $payment->amount : 0;
	}

	if ( is_array( $payment ) ) {
		if ( array_key_exists( 'final_amount', $payment ) ) {
			return (float) $payment['final_amount'];
		}

		return isset( $payment['amount'] ) ? (float) $payment['amount'] : 0;
	}

	return 0;
}

/**
 * Backfills payment financial columns after schema upgrades.
 *
 * @return void
 */
function cmp_backfill_payment_financial_fields() {
	global $wpdb;

	$table = cmp_table( 'payments' );
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	if ( ! is_string( $found ) || $table !== $found ) {
		return;
	}

	$wpdb->query(
		"UPDATE {$table}
		SET
			original_amount = CASE
				WHEN original_amount <= 0 THEN amount
				ELSE original_amount
			END,
			charge_amount = CASE
				WHEN charge_amount < 0 OR charge_amount IS NULL THEN 0
				ELSE charge_amount
			END,
			final_amount = CASE
				WHEN amount > 0 THEN ROUND(GREATEST(amount - COALESCE(charge_amount, 0), 0), 2)
				ELSE final_amount
			END"
	);
}

/**
 * Builds a contact-match SQL fragment for interested students.
 *
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @param string $table_alias SQL table alias.
 * @return array
 */
function cmp_get_interested_student_contact_match_parts( $phone = '', $email = '', $table_alias = 'i' ) {
	$table_alias  = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table_alias );
	$phone_values = cmp_phone_match_values( $phone );
	$where        = array();
	$params       = array();

	if ( $phone_values ) {
		$where[] = $table_alias . '.phone IN (' . implode( ', ', array_fill( 0, count( $phone_values ), '%s' ) ) . ')';
		$params  = array_merge( $params, $phone_values );
	}

	if ( '' !== trim( (string) $email ) ) {
		$where[]  = $table_alias . '.email = %s';
		$params[] = sanitize_email( $email );
	}

	return array(
		'where'  => $where,
		'params' => $params,
	);
}

/**
 * Finds an interested student record by contact and optional context.
 *
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @param int    $batch_id Optional batch ID.
 * @param int    $class_id Optional class ID.
 * @return object|null
 */
function cmp_find_interested_student_by_contact( $phone = '', $email = '', $batch_id = 0, $class_id = 0 ) {
	global $wpdb;

	$batch_id      = absint( $batch_id );
	$class_id      = absint( $class_id );
	$contact_parts = cmp_get_interested_student_contact_match_parts( $phone, $email, 'i' );
	$where         = array();
	$params        = array();

	if ( empty( $contact_parts['where'] ) ) {
		return null;
	}

	if ( $batch_id ) {
		$where[]  = 'i.batch_id = %d';
		$params[] = $batch_id;
	} elseif ( $class_id ) {
		$where[]  = 'i.class_id = %d';
		$params[] = $class_id;
	}

	$where[] = '(' . implode( ' OR ', $contact_parts['where'] ) . ')';
	$params  = array_merge( $params, $contact_parts['params'] );

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT i.* FROM ' . cmp_table( 'interested_students' ) . ' i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY i.last_attempt_at DESC, i.id DESC LIMIT 1',
			$params
		)
	);
}

/**
 * Saves or updates an interested student record.
 *
 * @param array $data Interested student data.
 * @return int|WP_Error
 */
function cmp_upsert_interested_student( $data ) {
	global $wpdb;

	$name           = sanitize_text_field( cmp_field( $data, 'name' ) );
	$phone          = sanitize_text_field( cmp_field( $data, 'phone' ) );
	$email          = sanitize_email( cmp_field( $data, 'email' ) );
	$class_id       = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_id       = absint( cmp_field( $data, 'batch_id', 0 ) );
	$payment_status = sanitize_key( cmp_field( $data, 'payment_status', 'failed' ) );
	$payment_source = sanitize_key( cmp_field( $data, 'payment_source', '' ) );
	$attempt_amount = round( (float) cmp_money_value( cmp_field( $data, 'attempt_amount', 0 ) ), 2 );
	$transaction_id = sanitize_text_field( cmp_field( $data, 'transaction_id' ) );
	$notes          = sanitize_textarea_field( cmp_field( $data, 'notes' ) );
	$last_attempt_at = sanitize_text_field( cmp_field( $data, 'last_attempt_at', cmp_current_datetime() ) );
	$payment_meta    = cmp_field( $data, 'payment_meta', '' );
	$lead_source     = cmp_normalize_lead_source_name( cmp_field( $data, 'lead_source', '' ) );
	$city_raw        = sanitize_text_field( cmp_field( $data, 'city_original', cmp_field( $data, 'city', '' ) ) );
	$city_data       = cmp_normalize_city_name( $city_raw );
	$follow_up_status = cmp_normalize_interested_student_follow_up_status( cmp_field( $data, 'follow_up_status', '' ) );
	$follow_up_notes  = sanitize_textarea_field( cmp_field( $data, 'follow_up_notes', '' ) );
	$last_contact_at  = sanitize_text_field( cmp_field( $data, 'last_contact_at', cmp_current_datetime() ) );
	$next_follow_up   = sanitize_text_field( cmp_field( $data, 'next_follow_up_at', '' ) );
	$assigned_teacher = absint( cmp_field( $data, 'assigned_teacher_user_id', 0 ) );
	$email_status     = sanitize_key( cmp_field( $data, 'email_status', '' ) );
	$whatsapp_status  = sanitize_key( cmp_field( $data, 'whatsapp_status', '' ) );
	$contact_attempts = absint( cmp_field( $data, 'contact_attempts', 0 ) );

	if ( '' === $name ) {
		if ( '' !== $email ) {
			$email_parts = explode( '@', $email );
			$name        = sanitize_text_field( isset( $email_parts[0] ) ? $email_parts[0] : $email );
		} else {
			$name = sanitize_text_field( $phone );
		}
	}

	if ( '' === $name || ( '' === $phone && '' === $email ) ) {
		return new WP_Error( 'cmp_interested_student_invalid', __( 'Interested students require a name and at least one contact detail.', 'class-manager-pro' ) );
	}

	if ( is_array( $payment_meta ) ) {
		$payment_meta = wp_json_encode( $payment_meta );
	}

	$payment_meta = is_string( $payment_meta ) ? $payment_meta : '';
	$existing     = cmp_find_interested_student_by_contact( $phone, $email, $batch_id, $class_id );
	$contact_attempts = $contact_attempts > 0 ? $contact_attempts : ( $existing ? ( (int) $existing->contact_attempts + 1 ) : 1 );
	$last_contact_at  = '' !== $last_contact_at ? $last_contact_at : cmp_current_datetime();
	$follow_up_status = '' !== $follow_up_status ? $follow_up_status : ( $existing && ! empty( $existing->follow_up_status ) ? cmp_normalize_interested_student_follow_up_status( $existing->follow_up_status ) : 'follow_up_pending' );

	if ( $existing ) {
		$result = $wpdb->update(
			cmp_table( 'interested_students' ),
			array(
				'name'                    => $name,
				'phone'                   => $phone,
				'email'                   => $email,
				'class_id'                => $class_id ? $class_id : (int) $existing->class_id,
				'batch_id'                => $batch_id ? $batch_id : (int) $existing->batch_id,
				'payment_status'          => '' !== $payment_status ? $payment_status : $existing->payment_status,
				'payment_source'          => '' !== $payment_source ? $payment_source : $existing->payment_source,
				'attempt_amount'          => $attempt_amount,
				'transaction_id'          => $transaction_id,
				'notes'                   => '' !== $notes ? cmp_append_note( $existing->notes, $notes ) : $existing->notes,
				'follow_up_status'        => '' !== $follow_up_status ? $follow_up_status : $existing->follow_up_status,
				'follow_up_notes'         => '' !== $follow_up_notes ? cmp_append_note( $existing->follow_up_notes, $follow_up_notes ) : $existing->follow_up_notes,
				'payment_meta'            => '' !== $payment_meta ? $payment_meta : $existing->payment_meta,
				'last_attempt_at'         => $last_attempt_at,
				'last_contact_at'         => $last_contact_at,
				'next_follow_up_at'       => '' !== $next_follow_up ? $next_follow_up : $existing->next_follow_up_at,
				'assigned_teacher_user_id'=> $assigned_teacher ? $assigned_teacher : (int) $existing->assigned_teacher_user_id,
				'contact_attempts'        => $contact_attempts,
				'email_status'            => '' !== $email_status ? $email_status : $existing->email_status,
				'whatsapp_status'         => '' !== $whatsapp_status ? $whatsapp_status : $existing->whatsapp_status,
				'lead_source'             => '' !== $lead_source ? $lead_source : ( isset( $existing->lead_source ) ? $existing->lead_source : '' ),
				'city_original'           => '' !== $city_data['original'] ? $city_data['original'] : ( isset( $existing->city_original ) ? $existing->city_original : '' ),
				'city_normalized'         => '' !== $city_data['normalized'] ? $city_data['normalized'] : ( isset( $existing->city_normalized ) ? $existing->city_normalized : '' ),
			),
			array(
				'id' => (int) $existing->id,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'cmp_interested_student_update_failed', __( 'The interested student could not be updated.', 'class-manager-pro' ) );
		}

		cmp_log_interested_student_timeline(
			(int) $existing->id,
			'updated',
			'' !== $notes ? $notes : __( 'Interested student details updated.', 'class-manager-pro' ),
			array(
				'status'                 => $follow_up_status,
				'lead_source'            => $lead_source,
				'assigned_teacher_user_id' => $assigned_teacher ? $assigned_teacher : (int) $existing->assigned_teacher_user_id,
				'email_status'           => '' !== $email_status ? $email_status : $existing->email_status,
				'whatsapp_status'        => '' !== $whatsapp_status ? $whatsapp_status : $existing->whatsapp_status,
				'contact_attempts'       => $contact_attempts,
			)
		);

		return (int) $existing->id;
	}

	$result = $wpdb->insert(
		cmp_table( 'interested_students' ),
		array(
			'name'                    => $name,
			'phone'                   => $phone,
			'email'                   => $email,
			'class_id'                => $class_id,
			'batch_id'                => $batch_id,
			'payment_status'          => '' !== $payment_status ? $payment_status : 'failed',
			'payment_source'          => $payment_source,
			'attempt_amount'          => $attempt_amount,
			'transaction_id'          => $transaction_id,
			'notes'                   => $notes,
			'follow_up_status'        => $follow_up_status,
			'follow_up_notes'         => $follow_up_notes,
			'payment_meta'            => $payment_meta,
			'last_attempt_at'         => $last_attempt_at,
			'last_contact_at'         => $last_contact_at,
			'next_follow_up_at'       => '' !== $next_follow_up ? $next_follow_up : null,
			'assigned_teacher_user_id'=> $assigned_teacher,
			'contact_attempts'        => $contact_attempts,
			'email_status'            => $email_status,
			'whatsapp_status'         => $whatsapp_status,
			'lead_source'             => $lead_source,
			'city_original'           => $city_data['original'],
			'city_normalized'         => $city_data['normalized'],
			'created_at'              => cmp_current_datetime(),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_interested_student_insert_failed', __( 'The interested student could not be saved.', 'class-manager-pro' ) );
	}

	cmp_log_interested_student_timeline(
		(int) $wpdb->insert_id,
		'created',
		'' !== $notes ? $notes : __( 'Interested student created.', 'class-manager-pro' ),
		array(
			'status'                 => $follow_up_status,
			'lead_source'            => $lead_source,
			'assigned_teacher_user_id' => $assigned_teacher,
			'email_status'           => $email_status,
			'whatsapp_status'        => $whatsapp_status,
			'contact_attempts'       => $contact_attempts,
		)
	);

	return (int) $wpdb->insert_id;
}

/**
 * Records a failed payment attempt as an interested student.
 *
 * @param array $data Interested student data.
 * @return int|WP_Error
 */
function cmp_record_failed_payment_interest( $data ) {
	$data = wp_parse_args(
		$data,
		array(
			'payment_status' => 'failed',
			'payment_source' => '',
			'attempt_amount' => 0,
			'transaction_id' => '',
			'notes'          => __( 'Captured from a failed payment attempt.', 'class-manager-pro' ),
			'payment_meta'   => '',
		)
	);

	return cmp_upsert_interested_student( $data );
}

/**
 * Removes interested student rows after a successful payment.
 *
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @param int    $batch_id Optional batch ID.
 * @param int    $class_id Optional class ID.
 * @return int
 */
function cmp_remove_interested_students_by_contact( $phone = '', $email = '', $batch_id = 0, $class_id = 0 ) {
	global $wpdb;

	$batch_id      = absint( $batch_id );
	$class_id      = absint( $class_id );
	$contact_parts = cmp_get_interested_student_contact_match_parts( $phone, $email, 'i' );
	$where         = array();
	$params        = array();

	if ( empty( $contact_parts['where'] ) ) {
		return 0;
	}

	$where[] = '(' . implode( ' OR ', $contact_parts['where'] ) . ')';
	$params  = array_merge( $params, $contact_parts['params'] );

	if ( $batch_id && $class_id ) {
		$where[]  = '(i.batch_id = %d OR (i.batch_id = 0 AND i.class_id = %d))';
		$params[] = $batch_id;
		$params[] = $class_id;
	} elseif ( $batch_id ) {
		$where[]  = 'i.batch_id = %d';
		$params[] = $batch_id;
	} elseif ( $class_id ) {
		$where[]  = 'i.class_id = %d';
		$params[] = $class_id;
	}

	$sql = 'DELETE i FROM ' . cmp_table( 'interested_students' ) . ' i WHERE ' . implode( ' AND ', $where );

	$result = $wpdb->query( $wpdb->prepare( $sql, $params ) );

	return false === $result ? 0 : (int) $result;
}

/**
 * Removes interested student rows that match a saved student.
 *
 * @param object|int $student Student object or ID.
 * @param int        $batch_id Optional batch ID override.
 * @param int        $class_id Optional class ID override.
 * @return int
 */
function cmp_remove_interested_students_for_student( $student, $batch_id = 0, $class_id = 0 ) {
	if ( is_numeric( $student ) ) {
		$student = cmp_get_student( (int) $student );
	}

	if ( ! $student ) {
		return 0;
	}

	return cmp_remove_interested_students_by_contact(
		isset( $student->phone ) ? $student->phone : '',
		isset( $student->email ) ? $student->email : '',
		$batch_id ? $batch_id : ( isset( $student->batch_id ) ? (int) $student->batch_id : 0 ),
		$class_id ? $class_id : ( isset( $student->class_id ) ? (int) $student->class_id : 0 )
	);
}

/**
 * Returns query parts for interested student list screens.
 *
 * @param array $args Filters.
 * @return array
 */
function cmp_get_interested_students_query_parts( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'          => '',
			'class_id'        => 0,
			'batch_id'        => 0,
			'teacher_user_id' => 0,
			'follow_up_status' => '',
			'email_status'    => '',
			'whatsapp_status' => '',
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['search'] ) {
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]  = '(i.name LIKE %s OR i.phone LIKE %s OR i.email LIKE %s OR i.transaction_id LIKE %s OR c.name LIKE %s OR b.batch_name LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ( ! empty( $args['class_id'] ) ) {
		$where[]  = 'i.class_id = %d';
		$params[] = absint( $args['class_id'] );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 'i.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( ! empty( $args['teacher_user_id'] ) ) {
		$where[]  = 'b.teacher_user_id = %d';
		$params[] = absint( $args['teacher_user_id'] );
	}

	if ( '' !== cmp_field( $args, 'follow_up_status', '' ) ) {
		$where[]  = 'i.follow_up_status = %s';
		$params[] = cmp_normalize_interested_student_follow_up_status( cmp_field( $args, 'follow_up_status', '' ) );
	}

	if ( '' !== cmp_field( $args, 'email_status', '' ) ) {
		$where[]  = 'i.email_status = %s';
		$params[] = sanitize_key( cmp_field( $args, 'email_status', '' ) );
	}

	if ( '' !== cmp_field( $args, 'whatsapp_status', '' ) ) {
		$where[]  = 'i.whatsapp_status = %s';
		$params[] = sanitize_key( cmp_field( $args, 'whatsapp_status', '' ) );
	}

	return array(
		'where'  => $where,
		'params' => $params,
	);
}

/**
 * Fetches interested students.
 *
 * @param array $args Query filters.
 * @return array
 */
function cmp_get_interested_students( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'          => '',
			'class_id'        => 0,
			'batch_id'        => 0,
			'teacher_user_id' => 0,
			'follow_up_status' => '',
			'email_status'    => '',
			'whatsapp_status' => '',
			'limit'           => 0,
			'offset'          => 0,
		)
	);

	$query_parts = cmp_get_interested_students_query_parts( $args );
	$sql         = 'SELECT
			i.*,
			c.name AS class_name,
			b.batch_name,
			b.teacher_user_id
		FROM ' . cmp_table( 'interested_students' ) . ' i
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = i.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = i.batch_id';

	if ( ! empty( $query_parts['where'] ) ) {
		$sql .= ' WHERE ' . implode( ' AND ', $query_parts['where'] );
	}

	$sql .= ' ORDER BY COALESCE(i.next_follow_up_at, i.last_contact_at, i.last_attempt_at) ASC, i.last_attempt_at DESC, i.id DESC';

	if ( ! empty( $args['limit'] ) ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	return ! empty( $query_parts['params'] ) ? $wpdb->get_results( $wpdb->prepare( $sql, $query_parts['params'] ) ) : $wpdb->get_results( $sql );
}

/**
 * Counts interested students.
 *
 * @param array $args Query filters.
 * @return int
 */
function cmp_get_interested_students_count( $args = array() ) {
	global $wpdb;

	$query_parts = cmp_get_interested_students_query_parts( $args );
	$sql         = 'SELECT COUNT(*) FROM ' . cmp_table( 'interested_students' ) . ' i
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = i.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = i.batch_id';

	if ( ! empty( $query_parts['where'] ) ) {
		$sql .= ' WHERE ' . implode( ' AND ', $query_parts['where'] );
	}

	return (int) ( ! empty( $query_parts['params'] ) ? $wpdb->get_var( $wpdb->prepare( $sql, $query_parts['params'] ) ) : $wpdb->get_var( $sql ) );
}

/**
 * Returns interested students for one batch.
 *
 * @param int $batch_id Batch ID.
 * @param int $limit Optional limit.
 * @return array
 */
function cmp_get_interested_students_for_batch( $batch_id, $limit = 0 ) {
	return cmp_get_interested_students(
		array(
			'batch_id' => absint( $batch_id ),
			'limit'    => absint( $limit ),
		)
	);
}

/**
 * Returns the available follow-up statuses for interested students.
 *
 * @return array
 */
function cmp_get_interested_student_follow_up_statuses() {
	return array(
		''                        => __( 'Select status', 'class-manager-pro' ),
		'follow_up_pending'       => __( 'Follow-up pending', 'class-manager-pro' ),
		'contacted'               => __( 'Contacted', 'class-manager-pro' ),
		'follow_up_scheduled'     => __( 'Follow-up scheduled', 'class-manager-pro' ),
		'converted'               => __( 'Converted', 'class-manager-pro' ),
		'unreachable'             => __( 'Unreachable', 'class-manager-pro' ),
		'not_answering'           => __( 'Not answering', 'class-manager-pro' ),
		'interested'              => __( 'Interested', 'class-manager-pro' ),
		'ready_to_take_admission' => __( 'Ready to take admission', 'class-manager-pro' ),
		'call_later'              => __( 'Call later', 'class-manager-pro' ),
		'not_interested'          => __( 'Not interested', 'class-manager-pro' ),
	);
}

/**
 * Normalizes legacy interested-student follow-up values to the current set.
 *
 * @param string $status Raw status value.
 * @return string
 */
function cmp_normalize_interested_student_follow_up_status( $status ) {
	$status = sanitize_key( (string) $status );
	$map = array(
		'not_answering'           => 'follow_up_pending',
		'interested'              => 'contacted',
		'ready_to_take_admission' => 'converted',
		'call_later'              => 'follow_up_scheduled',
		'pending'                 => 'follow_up_pending',
		'pending_follow_up'       => 'follow_up_pending',
		'follow_up_later'         => 'follow_up_scheduled',
	);

	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

/**
 * Writes a timeline entry for an interested student.
 *
 * @param int    $lead_id Interested student ID.
 * @param string $event_type Event type.
 * @param string $notes Notes for the event.
 * @param array  $context Additional context.
 * @return int|WP_Error|null
 */
function cmp_log_interested_student_timeline( $lead_id, $event_type, $notes = '', $context = array() ) {
	global $wpdb;

	$lead_id = absint( $lead_id );
	$event_type = sanitize_key( $event_type );
	$notes = sanitize_textarea_field( (string) $notes );
	$context = is_array( $context ) ? $context : array();

	if ( ! $lead_id || '' === $event_type ) {
		return null;
	}

	$result = $wpdb->insert(
		cmp_table( 'interested_student_timelines' ),
		array(
			'lead_id'    => $lead_id,
			'event_type' => $event_type,
			'status'     => sanitize_key( cmp_field( $context, 'status', '' ) ),
			'channel'    => sanitize_key( cmp_field( $context, 'channel', '' ) ),
			'notes'      => $notes,
			'context'    => wp_json_encode( $context ),
			'created_by' => get_current_user_id(),
			'created_at' => cmp_current_datetime(),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_interested_timeline_failed', __( 'The CRM timeline entry could not be saved.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Returns a human-readable label for a CRM timeline event.
 *
 * @param string $event_type Event type.
 * @return string
 */
function cmp_get_interested_student_timeline_event_label( $event_type ) {
	$labels = array(
		'created'        => __( 'Lead Created', 'class-manager-pro' ),
		'updated'        => __( 'Lead Updated', 'class-manager-pro' ),
		'follow_up'      => __( 'Follow-up Updated', 'class-manager-pro' ),
		'email_sent'     => __( 'Email Sent', 'class-manager-pro' ),
		'email_failed'   => __( 'Email Failed', 'class-manager-pro' ),
		'converted'      => __( 'Converted', 'class-manager-pro' ),
		'assigned'       => __( 'Teacher Assigned', 'class-manager-pro' ),
		'note'           => __( 'Note Added', 'class-manager-pro' ),
	);

	$event_type = sanitize_key( $event_type );

	return isset( $labels[ $event_type ] ) ? $labels[ $event_type ] : ucwords( str_replace( array( '_', '-' ), ' ', $event_type ) );
}

/**
 * Returns a timeline of CRM activity for an interested student.
 *
 * @param int $lead_id Interested student ID.
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_interested_student_timeline( $lead_id, $limit = 12 ) {
	global $wpdb;

	$lead_id = absint( $lead_id );
	$limit   = max( 1, absint( $limit ) );

	if ( ! $lead_id ) {
		return array();
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT t.*, u.display_name AS created_by_name
			FROM ' . cmp_table( 'interested_student_timelines' ) . ' t
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = t.created_by
			WHERE t.lead_id = %d
			ORDER BY t.created_at DESC, t.id DESC
			LIMIT %d',
			$lead_id,
			$limit
		)
	);

	foreach ( $rows as &$row ) {
		$row->timeline_event_label = cmp_get_interested_student_timeline_event_label( $row->event_type );
		$status_map                = cmp_get_interested_student_follow_up_statuses();
		$row->status_label         = isset( $row->status ) && '' !== $row->status && isset( $status_map[ $row->status ] ) ? $status_map[ $row->status ] : ( isset( $row->status ) && '' !== $row->status ? ucwords( str_replace( array( '_', '-' ), ' ', $row->status ) ) : '' );
		$row->context              = ! empty( $row->context ) ? json_decode( (string) $row->context, true ) : array();
	}
	unset( $row );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Returns one interested student row with batch and teacher context.
 *
 * @param int $lead_id Interested student ID.
 * @return object|null
 */
function cmp_get_interested_student( $lead_id ) {
	global $wpdb;

	$lead_id = absint( $lead_id );

	if ( ! $lead_id ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT
				i.*,
				c.name AS class_name,
				b.batch_name,
				b.teacher_user_id
			FROM ' . cmp_table( 'interested_students' ) . ' i
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = i.class_id
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = i.batch_id
			WHERE i.id = %d
			LIMIT 1',
			$lead_id
		)
	);
}

/**
 * Returns whether the current user can manage an interested student row.
 *
 * @param object|null $lead Interested student row.
 * @return bool
 */
function cmp_current_user_can_manage_interested_student( $lead ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	if ( ! $lead || empty( $lead->batch_id ) || ! get_current_user_id() ) {
		return false;
	}

	return ! empty( $lead->teacher_user_id ) && (int) $lead->teacher_user_id === get_current_user_id();
}

/**
 * Reads plugin return arguments from the current request.
 *
 * @return array
 */
function cmp_get_return_request_args() {
	$args = array();

	foreach ( $_REQUEST as $key => $value ) {
		if ( ! is_scalar( $value ) || 'return_page' === $key || 0 !== strpos( (string) $key, 'return_' ) ) {
			continue;
		}

		$args[ substr( (string) $key, 7 ) ] = wp_unslash( (string) $value );
	}

	return $args;
}

/**
 * Outputs hidden return arguments for form submissions.
 *
 * @param string $return_page Return page slug.
 * @param array  $return_args Return query arguments.
 * @return void
 */
function cmp_render_return_hidden_fields( $return_page, $return_args = array() ) {
	$return_page = cmp_clean_return_page( $return_page, 'cmp-interested-students' );
	?>
	<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
	<?php foreach ( $return_args as $key => $value ) : ?>
		<?php if ( is_scalar( $value ) ) : ?>
			<input type="hidden" name="return_<?php echo esc_attr( sanitize_key( $key ) ); ?>" value="<?php echo esc_attr( wp_unslash( (string) $value ) ); ?>">
		<?php endif; ?>
	<?php endforeach; ?>
	<?php
}

/**
 * Returns the teacher display name for one interested student row.
 *
 * @param object $lead Interested student row.
 * @return string
 */
function cmp_get_interested_student_teacher_name( $lead ) {
	$teacher_user_id = isset( $lead->teacher_user_id ) ? absint( $lead->teacher_user_id ) : 0;

	if ( ! $teacher_user_id && get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
		$teacher_user_id = get_current_user_id();
	}

	return $teacher_user_id ? cmp_get_teacher_label( $teacher_user_id ) : __( 'our team', 'class-manager-pro' );
}

/**
 * Returns the follow-up email subject for an interested student.
 *
 * @param object $lead Interested student row.
 * @return string
 */
function cmp_build_interested_student_email_subject( $lead ) {
	$teacher_name = cmp_get_interested_student_teacher_name( $lead );
	$label        = ! empty( $lead->batch_name ) ? $lead->batch_name : ( ! empty( $lead->class_name ) ? $lead->class_name : __( 'your registration', 'class-manager-pro' ) );

	return sprintf(
		/* translators: 1: teacher name 2: batch or class label */
		__( 'Follow-up from %1$s for %2$s', 'class-manager-pro' ),
		$teacher_name,
		$label
	);
}

/**
 * Builds the follow-up email body for an interested student.
 *
 * @param object $lead Interested student row.
 * @return string
 */
function cmp_build_interested_student_email_body( $lead ) {
	$teacher_name = cmp_get_interested_student_teacher_name( $lead );
	$batch_name   = ! empty( $lead->batch_name ) ? $lead->batch_name : __( 'your batch', 'class-manager-pro' );
	$class_name   = ! empty( $lead->class_name ) ? $lead->class_name : __( 'your class', 'class-manager-pro' );

	return sprintf(
		/* translators: 1: student name 2: teacher name 3: class name 4: batch name */
		__(
			'Hello %1$s,

%2$s is following up regarding your admission for %3$s / %4$s.

We noticed that your payment attempt did not complete. Reply to this email if you want help finishing your admission.',
			'class-manager-pro'
		),
		isset( $lead->name ) ? $lead->name : __( 'there', 'class-manager-pro' ),
		$teacher_name,
		$class_name,
		$batch_name
	);
}

/**
 * Sends one interested-student follow-up email through WordPress SMTP.
 *
 * @param int $lead_id Interested student ID.
 * @return array
 */
function cmp_send_interested_student_follow_up_email_by_id( $lead_id ) {
	global $wpdb;

	$lead = cmp_get_interested_student( $lead_id );

	if ( ! $lead ) {
		return array(
			'success' => false,
			'message' => __( 'Interested student not found.', 'class-manager-pro' ),
		);
	}

	if ( ! cmp_current_user_can_manage_interested_student( $lead ) ) {
		return array(
			'success' => false,
			'message' => __( 'You do not have permission to contact this interested student.', 'class-manager-pro' ),
		);
	}

	if ( empty( $lead->email ) ) {
		return array(
			'success' => false,
			'message' => __( 'Email address is missing.', 'class-manager-pro' ),
		);
	}

	$sent = wp_mail(
		sanitize_email( $lead->email ),
		cmp_build_interested_student_email_subject( $lead ),
		cmp_prepare_email_body( cmp_build_interested_student_email_body( $lead ) ),
		array( 'Content-Type: text/html; charset=UTF-8' )
	);

	$timestamp = cmp_current_datetime();
	$wpdb->update(
		cmp_table( 'interested_students' ),
		array(
			'email_status'     => $sent ? 'sent' : 'failed',
			'last_contact_at'  => $timestamp,
			'contact_attempts'  => (int) $lead->contact_attempts + 1,
			'follow_up_status'  => $sent ? ( ! empty( $lead->follow_up_status ) ? cmp_normalize_interested_student_follow_up_status( $lead->follow_up_status ) : 'contacted' ) : $lead->follow_up_status,
		),
		array( 'id' => (int) $lead->id ),
		array( '%s', '%s', '%d', '%s' ),
		array( '%d' )
	);

	cmp_log_interested_student_timeline(
		(int) $lead->id,
		$sent ? 'email_sent' : 'email_failed',
		$sent ? __( 'Follow-up email sent successfully.', 'class-manager-pro' ) : __( 'Follow-up email could not be sent.', 'class-manager-pro' ),
		array(
			'channel'           => 'email',
			'status'            => $sent ? 'sent' : 'failed',
			'email_status'      => $sent ? 'sent' : 'failed',
			'contact_attempts'  => (int) $lead->contact_attempts + 1,
			'last_contact_at'   => $timestamp,
		)
	);

	return array(
		'success' => (bool) $sent,
		'message' => $sent ? __( 'Interested-student email sent successfully.', 'class-manager-pro' ) : __( 'Interested-student email could not be sent.', 'class-manager-pro' ),
	);
}

/**
 * Saves a follow-up status and note for one interested student.
 *
 * @param int    $lead_id Interested student ID.
 * @param string $status Follow-up status.
 * @param string $notes Follow-up notes.
 * @return true|WP_Error
 */
function cmp_update_interested_student_follow_up( $lead_id, $status, $notes, $meta = array() ) {
	global $wpdb;

	$lead   = cmp_get_interested_student( $lead_id );
	$status = cmp_normalize_interested_student_follow_up_status( sanitize_key( (string) $status ) );
	$notes  = sanitize_textarea_field( (string) $notes );
	$meta   = wp_parse_args(
		is_array( $meta ) ? $meta : array(),
		array(
			'last_contact_at'          => cmp_current_datetime(),
			'next_follow_up_at'        => '',
			'assigned_teacher_user_id' => 0,
			'contact_attempts'         => null,
			'email_status'             => '',
			'whatsapp_status'          => '',
		)
	);

	if ( ! $lead ) {
		return new WP_Error( 'cmp_interested_student_missing', __( 'Interested student not found.', 'class-manager-pro' ) );
	}

	if ( ! cmp_current_user_can_manage_interested_student( $lead ) ) {
		return new WP_Error( 'cmp_interested_student_forbidden', __( 'You do not have permission to update this interested student.', 'class-manager-pro' ) );
	}

	$allowed_statuses = array_keys( cmp_get_interested_student_follow_up_statuses() );

	if ( ! in_array( $status, $allowed_statuses, true ) ) {
		$status = ! empty( $lead->follow_up_status ) ? cmp_normalize_interested_student_follow_up_status( $lead->follow_up_status ) : 'follow_up_pending';
	}

	$updated = $wpdb->update(
		cmp_table( 'interested_students' ),
		array(
			'follow_up_status'          => $status,
			'follow_up_notes'           => $notes,
			'follow_up_updated_at'      => cmp_current_datetime(),
			'follow_up_updated_by'      => get_current_user_id(),
			'last_contact_at'           => '' !== sanitize_text_field( cmp_field( $meta, 'last_contact_at', '' ) ) ? sanitize_text_field( cmp_field( $meta, 'last_contact_at', '' ) ) : cmp_current_datetime(),
			'next_follow_up_at'         => '' !== sanitize_text_field( cmp_field( $meta, 'next_follow_up_at', '' ) ) ? sanitize_text_field( cmp_field( $meta, 'next_follow_up_at', '' ) ) : $lead->next_follow_up_at,
			'assigned_teacher_user_id'  => absint( cmp_field( $meta, 'assigned_teacher_user_id', 0 ) ) ? absint( cmp_field( $meta, 'assigned_teacher_user_id', 0 ) ) : (int) $lead->assigned_teacher_user_id,
			'contact_attempts'          => null !== cmp_field( $meta, 'contact_attempts', null ) ? absint( cmp_field( $meta, 'contact_attempts', 0 ) ) : ( (int) $lead->contact_attempts + 1 ),
			'email_status'              => '' !== sanitize_key( cmp_field( $meta, 'email_status', '' ) ) ? sanitize_key( cmp_field( $meta, 'email_status', '' ) ) : $lead->email_status,
			'whatsapp_status'           => '' !== sanitize_key( cmp_field( $meta, 'whatsapp_status', '' ) ) ? sanitize_key( cmp_field( $meta, 'whatsapp_status', '' ) ) : $lead->whatsapp_status,
		),
		array(
			'id' => (int) $lead->id,
		),
		array( '%s', '%s', '%s', '%d' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_interested_student_follow_up_failed', __( 'The follow-up details could not be saved.', 'class-manager-pro' ) );
	}

	cmp_log_interested_student_timeline(
		(int) $lead->id,
		'follow_up',
		'' !== $notes ? $notes : __( 'Follow-up details updated.', 'class-manager-pro' ),
		array_merge(
			array(
				'status'                 => $status,
				'notes'                  => $notes,
				'last_contact_at'        => isset( $meta['last_contact_at'] ) ? sanitize_text_field( cmp_field( $meta, 'last_contact_at', '' ) ) : cmp_current_datetime(),
				'next_follow_up_at'      => isset( $meta['next_follow_up_at'] ) ? sanitize_text_field( cmp_field( $meta, 'next_follow_up_at', '' ) ) : '',
				'assigned_teacher_user_id' => isset( $meta['assigned_teacher_user_id'] ) ? absint( cmp_field( $meta, 'assigned_teacher_user_id', 0 ) ) : (int) $lead->assigned_teacher_user_id,
				'contact_attempts'       => isset( $meta['contact_attempts'] ) && null !== cmp_field( $meta, 'contact_attempts', null ) ? absint( cmp_field( $meta, 'contact_attempts', 0 ) ) : ( (int) $lead->contact_attempts + 1 ),
				'email_status'           => isset( $meta['email_status'] ) ? sanitize_key( cmp_field( $meta, 'email_status', '' ) ) : $lead->email_status,
				'whatsapp_status'        => isset( $meta['whatsapp_status'] ) ? sanitize_key( cmp_field( $meta, 'whatsapp_status', '' ) ) : $lead->whatsapp_status,
			),
			is_array( $meta ) ? $meta : array()
		)
	);

	if ( ! empty( $meta['assigned_teacher_user_id'] ) && (int) $meta['assigned_teacher_user_id'] !== (int) $lead->assigned_teacher_user_id ) {
		cmp_log_interested_student_timeline(
			(int) $lead->id,
			'assigned',
			sprintf(
				/* translators: %s: teacher label */
				__( 'Assigned to %s.', 'class-manager-pro' ),
				cmp_get_teacher_label( absint( $meta['assigned_teacher_user_id'] ) )
			),
			array(
				'status' => $status,
			)
		);
	}

	return true;
}

/**
 * Handles CRM exports for interested students.
 *
 * @return void
 */
function cmp_handle_export_interested_students() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_export_interested_students' );

	$format = sanitize_key( cmp_field( $_POST, 'format', 'csv' ) );
	$scope  = sanitize_key( cmp_field( $_POST, 'scope', 'filtered' ) );
	$filters = array(
		'search'          => sanitize_text_field( cmp_field( $_POST, 'search', '' ) ),
		'class_id'        => absint( cmp_field( $_POST, 'class_id', 0 ) ),
		'batch_id'        => absint( cmp_field( $_POST, 'batch_id', 0 ) ),
		'follow_up_status'=> sanitize_key( cmp_field( $_POST, 'follow_up_status', '' ) ),
		'email_status'    => sanitize_key( cmp_field( $_POST, 'email_status', '' ) ),
		'whatsapp_status' => sanitize_key( cmp_field( $_POST, 'whatsapp_status', '' ) ),
	);
	$selected_ids = cmp_clean_absint_array( explode( ',', (string) cmp_field( $_POST, 'selected_ids', '' ) ) );

	$export_args = $filters;
	$export_args['limit'] = 0;

	if ( 'all' === $scope ) {
		$export_args = array(
			'search'           => '',
			'class_id'         => 0,
			'batch_id'         => 0,
			'follow_up_status' => '',
			'email_status'     => '',
			'whatsapp_status'  => '',
			'limit'            => 0,
		);
	} elseif ( 'selected' === $scope ) {
		$export_args['selected_ids'] = $selected_ids;
	} elseif ( in_array( $scope, array( 'follow_up_pending', 'contacted', 'converted', 'not_interested' ), true ) ) {
		$export_args['follow_up_status'] = $scope;
	}

	$rows = cmp_get_interested_students_export_rows( $export_args );

	if ( empty( $rows ) ) {
		wp_die( esc_html__( 'No interested students matched the export criteria.', 'class-manager-pro' ) );
	}

	$headers = array(
		'Lead ID',
		'Student Name',
		'Phone',
		'Email',
		'Class',
		'Batch',
		'Follow-up Status',
		'Follow-up Notes',
		'Last Contact Date',
		'Next Follow-up Date',
		'Assigned Teacher',
		'Contact Attempts',
		'Email Status',
		'WhatsApp Status',
		'Lead Source',
		'Original City',
		'Normalized City',
		'Payment Status',
		'Attempt Amount',
		'Transaction ID',
		'Created At',
	);

	if ( 'xlsx' === $format || 'excel' === $format ) {
		$file = cmp_build_xlsx_binary( $headers, $rows, __( 'Interested Students', 'class-manager-pro' ) );
		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ) );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename=class-manager-pro-interested-students-' . gmdate( 'Y-m-d' ) . '.xlsx' );
		echo $file; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=class-manager-pro-interested-students-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, $headers );

	foreach ( $rows as $row ) {
		fputcsv( $output, array_values( $row ) );
	}

	fclose( $output );
	exit;
}

/**
 * Returns interested-student export rows.
 *
 * @param array $args Export arguments.
 * @return array
 */
function cmp_get_interested_students_export_rows( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'search'           => '',
			'class_id'         => 0,
			'batch_id'         => 0,
			'follow_up_status' => '',
			'email_status'     => '',
			'whatsapp_status'   => '',
			'selected_ids'     => array(),
			'limit'            => 0,
		)
	);

	$selected_ids = cmp_clean_absint_array( cmp_field( $args, 'selected_ids', array() ) );
	if ( empty( $selected_ids ) && isset( $args['selected_ids'] ) && is_array( $args['selected_ids'] ) ) {
		$selected_ids = cmp_clean_absint_array( $args['selected_ids'] );
	}
	$rows         = array();

	if ( ! empty( $selected_ids ) ) {
		$placeholders = implode( ', ', array_fill( 0, count( $selected_ids ), '%d' ) );
		$sql          = 'SELECT
				i.*,
				c.name AS class_name,
				b.batch_name,
				b.teacher_user_id
			FROM ' . cmp_table( 'interested_students' ) . ' i
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = i.class_id
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = i.batch_id
			WHERE i.id IN (' . $placeholders . ')
			ORDER BY i.created_at DESC, i.id DESC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $selected_ids ) );
	} else {
		$rows = cmp_get_interested_students( $args );
	}

	$follow_up_statuses = cmp_get_interested_student_follow_up_statuses();
	$teacher_ids        = array();

	foreach ( $rows as $row ) {
		if ( ! empty( $row->teacher_user_id ) ) {
			$teacher_ids[] = (int) $row->teacher_user_id;
		}
	}

	$teacher_labels = array();

	foreach ( array_unique( $teacher_ids ) as $teacher_id ) {
		$teacher_labels[ $teacher_id ] = cmp_get_teacher_label( $teacher_id );
	}

	$export_rows = array();

	foreach ( $rows as $row ) {
		$status_key = isset( $row->follow_up_status ) ? cmp_normalize_interested_student_follow_up_status( $row->follow_up_status ) : '';

		$export_rows[] = array(
			'lead_id'            => (int) $row->id,
			'student_name'       => isset( $row->name ) ? $row->name : '',
			'phone'              => isset( $row->phone ) ? $row->phone : '',
			'email'              => isset( $row->email ) ? $row->email : '',
			'class_name'         => isset( $row->class_name ) ? $row->class_name : '',
			'batch_name'         => isset( $row->batch_name ) ? $row->batch_name : '',
			'follow_up_status'   => $status_key,
			'follow_up_status_label' => isset( $follow_up_statuses[ $status_key ] ) ? $follow_up_statuses[ $status_key ] : ucwords( str_replace( array( '_', '-' ), ' ', $status_key ) ),
			'follow_up_notes'    => isset( $row->follow_up_notes ) ? $row->follow_up_notes : '',
			'last_contact_at'    => isset( $row->last_contact_at ) ? $row->last_contact_at : '',
			'next_follow_up_at'  => isset( $row->next_follow_up_at ) ? $row->next_follow_up_at : '',
			'assigned_teacher'   => isset( $row->teacher_user_id ) && isset( $teacher_labels[ (int) $row->teacher_user_id ] ) ? $teacher_labels[ (int) $row->teacher_user_id ] : '',
			'contact_attempts'   => isset( $row->contact_attempts ) ? (int) $row->contact_attempts : 0,
			'email_status'       => isset( $row->email_status ) ? $row->email_status : '',
			'whatsapp_status'    => isset( $row->whatsapp_status ) ? $row->whatsapp_status : '',
			'lead_source'        => isset( $row->lead_source ) ? $row->lead_source : '',
			'city_original'      => isset( $row->city_original ) ? $row->city_original : '',
			'city_normalized'    => isset( $row->city_normalized ) ? $row->city_normalized : '',
			'payment_status'     => isset( $row->payment_status ) ? $row->payment_status : '',
			'attempt_amount'     => isset( $row->attempt_amount ) ? (float) $row->attempt_amount : 0,
			'transaction_id'     => isset( $row->transaction_id ) ? $row->transaction_id : '',
			'created_at'         => isset( $row->created_at ) ? $row->created_at : '',
		);
	}

	return $export_rows;
}

/**
 * Builds a spreadsheet binary from array rows.
 *
 * @param array  $headers Column headers.
 * @param array  $rows Export rows.
 * @param string $sheet_title Sheet title.
 * @return string|WP_Error
 */
function cmp_build_xlsx_binary( $headers, $rows, $sheet_title = 'Sheet1' ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'cmp_xlsx_unsupported', __( 'Excel export is not available on this server.', 'class-manager-pro' ) );
	}

	$headers     = array_values( array_map( 'strval', is_array( $headers ) ? $headers : array() ) );
	$rows        = is_array( $rows ) ? array_values( $rows ) : array();
	$sheet_title = substr( preg_replace( '/[^A-Za-z0-9 ]+/', '', sanitize_text_field( (string) $sheet_title ) ), 0, 31 );

	if ( '' === $sheet_title ) {
		$sheet_title = 'Sheet1';
	}

	$temp_file = wp_tempnam( 'cmp-interested-students.xlsx' );

	if ( ! $temp_file ) {
		return new WP_Error( 'cmp_xlsx_temp_file_failed', __( 'The Excel export could not be prepared.', 'class-manager-pro' ) );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		@unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		return new WP_Error( 'cmp_xlsx_zip_failed', __( 'The Excel export could not be created.', 'class-manager-pro' ) );
	}

	$zip->addFromString(
		'[Content_Types].xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
		. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
		. '<Default Extension="xml" ContentType="application/xml"/>'
		. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
		. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
		. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
		. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
		. '</Types>'
	);
	$zip->addFromString(
		'_rels/.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
		. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
		. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
		. '</Relationships>'
	);
	$zip->addFromString(
		'docProps/app.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
		. '<Application>Class Manager Pro</Application>'
		. '<DocSecurity>0</DocSecurity>'
		. '<ScaleCrop>false</ScaleCrop>'
		. '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
		. '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . esc_html( $sheet_title ) . '</vt:lpstr></vt:vector></TitlesOfParts>'
		. '</Properties>'
	);
	$zip->addFromString(
		'docProps/core.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
		. '<dc:creator>Class Manager Pro</dc:creator>'
		. '<cp:lastModifiedBy>Class Manager Pro</cp:lastModifiedBy>'
		. '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate( 'c' ) . '</dcterms:created>'
		. '<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate( 'c' ) . '</dcterms:modified>'
		. '</cp:coreProperties>'
	);
	$zip->addFromString(
		'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
		. '</Relationships>'
	);
	$zip->addFromString(
		'xl/workbook.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<sheets><sheet name="' . esc_attr( $sheet_title ) . '" sheetId="1" r:id="rId1"/></sheets>'
		. '</workbook>'
	);

	$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
	$all_rows = array_merge( array( array_values( $headers ) ), array_map( 'array_values', $rows ) );

	foreach ( $all_rows as $row_index => $row_values ) {
		$xml .= '<row r="' . ( $row_index + 1 ) . '">';
		foreach ( $row_values as $column_index => $cell_value ) {
			$column_letter = cmp_xlsx_column_letter( $column_index + 1 );
			$value         = cmp_xlsx_escape_value( $cell_value );
			$xml          .= '<c r="' . $column_letter . ( $row_index + 1 ) . '" t="inlineStr"><is><t xml:space="preserve">' . $value . '</t></is></c>';
		}
		$xml .= '</row>';
	}

	$xml .= '</sheetData></worksheet>';
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $xml );
	$zip->close();

	$data = file_get_contents( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	@unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

	if ( false === $data || ! is_string( $data ) ) {
		return new WP_Error( 'cmp_xlsx_read_failed', __( 'The Excel export could not be read back from disk.', 'class-manager-pro' ) );
	}

	return $data;
}

/**
 * Escapes a value for inline spreadsheet text.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function cmp_xlsx_escape_value( $value ) {
	return esc_html( (string) $value );
}

/**
 * Converts a 1-based column index to an Excel column letter.
 *
 * @param int $index Column index.
 * @return string
 */
function cmp_xlsx_column_letter( $index ) {
	$index  = max( 1, absint( $index ) );
	$letter = '';

	while ( $index > 0 ) {
		$mod    = ( $index - 1 ) % 26;
		$letter = chr( 65 + $mod ) . $letter;
		$index  = (int) ( ( $index - $mod - 1 ) / 26 );
	}

	return $letter;
}

/**
 * Handles interested-student email sending.
 *
 * @return void
 */
function cmp_handle_send_interested_student_email() {
	$lead_id      = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	$return_page  = cmp_clean_return_page( cmp_field( $_REQUEST, 'return_page', 'cmp-interested-students' ), 'cmp-interested-students' );
	$return_args  = cmp_get_return_request_args();

	if ( empty( $return_args ) && 'cmp-teacher-console' === $return_page ) {
		$return_args = array(
			'teacher_view' => 'interested',
		);
	}

	check_admin_referer( 'cmp_send_interested_student_email_' . $lead_id );
	$result = cmp_send_interested_student_follow_up_email_by_id( $lead_id );

	cmp_redirect( $return_page, $result['message'], ! empty( $result['success'] ) ? 'success' : 'error', $return_args );
}

/**
 * AJAX handler for interested-student email sends.
 *
 * @return void
 */
function cmp_ajax_send_interested_student_email() {
	$lead_id = absint( cmp_field( $_POST, 'lead_id', 0 ) );

	if ( ! current_user_can( 'manage_options' ) && ! cmp_current_user_can_manage_interested_student_follow_up( $lead_id ) ) {
		cmp_require_manage_options_ajax();
	}

	check_ajax_referer( 'cmp_send_interested_student_email_' . $lead_id, 'nonce' );

	$result = cmp_send_interested_student_follow_up_email_by_id( $lead_id );

	if ( ! empty( $result['success'] ) ) {
		wp_send_json_success( $result );
	}

	wp_send_json_error( $result, 400 );
}

/**
 * Handles interested-student follow-up updates.
 *
 * @return void
 */
function cmp_handle_save_interested_student_follow_up() {
	$lead_id      = absint( cmp_field( $_POST, 'id', 0 ) );
	$return_page  = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-interested-students' ), 'cmp-interested-students' );
	$return_args  = cmp_get_return_request_args();

	check_admin_referer( 'cmp_save_interested_student_follow_up_' . $lead_id );
	$result = cmp_update_interested_student_follow_up(
		$lead_id,
		cmp_field( $_POST, 'follow_up_status' ),
		cmp_field( $_POST, 'follow_up_notes' )
	);

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $return_page, $result->get_error_message(), 'error', $return_args );
	}

	cmp_redirect( $return_page, __( 'Follow-up details saved successfully.', 'class-manager-pro' ), 'success', $return_args );
}

/**
 * AJAX handler for interested-student follow-up updates.
 *
 * @return void
 */
function cmp_ajax_save_interested_student_follow_up() {
	if ( ! current_user_can( 'manage_options' ) ) {
		$lead_id = absint( cmp_field( $_POST, 'id', 0 ) );

		if ( ! cmp_current_user_can_manage_interested_student_follow_up( $lead_id ) ) {
			cmp_require_manage_options_ajax();
		}
	}

	$lead_id = absint( cmp_field( $_POST, 'id', 0 ) );
	check_ajax_referer( 'cmp_save_interested_student_follow_up_' . $lead_id, 'nonce' );
	$result  = cmp_update_interested_student_follow_up(
		$lead_id,
		cmp_field( $_POST, 'follow_up_status' ),
		cmp_field( $_POST, 'follow_up_notes' )
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Follow-up details saved successfully.', 'class-manager-pro' ),
			'id'      => $lead_id,
			'refresh'  => array(
				'action' => 'cmp_filter_interested_students',
				'target' => '#cmp-interested-table-wrap',
			),
		)
	);
}

/**
 * Converts a payment source key into a label.
 *
 * @param string $source Source key.
 * @return string
 */
function cmp_get_interested_student_source_label( $source ) {
	$labels = array(
		'razorpay_csv' => __( 'Razorpay CSV', 'class-manager-pro' ),
		'razorpay_api' => __( 'Razorpay API', 'class-manager-pro' ),
		'razorpay_webhook' => __( 'Razorpay Webhook', 'class-manager-pro' ),
	);

	$source = sanitize_key( (string) $source );

	return isset( $labels[ $source ] ) ? $labels[ $source ] : ucfirst( str_replace( '_', ' ', $source ? $source : 'manual' ) );
}

/**
 * Builds a follow-up message for an interested student.
 *
 * @param object $lead Interested student row.
 * @return string
 */
function cmp_build_interested_student_follow_up_message( $lead ) {
	$name       = isset( $lead->name ) ? $lead->name : __( 'there', 'class-manager-pro' );
	$class_name = ! empty( $lead->class_name ) ? $lead->class_name : __( 'your class', 'class-manager-pro' );
	$batch_name = ! empty( $lead->batch_name ) ? $lead->batch_name : __( 'the selected batch', 'class-manager-pro' );

	return sprintf(
		/* translators: 1: student name 2: class name 3: batch name */
		__( 'Hello %1$s, we noticed your payment attempt for %2$s / %3$s did not complete. Reply here if you want help to finish your registration.', 'class-manager-pro' ),
		$name,
		$class_name,
		$batch_name
	);
}

/**
 * Returns a click-to-call URL for an interested student.
 *
 * @param object $lead Interested student row.
 * @return string
 */
function cmp_get_interested_student_call_url( $lead ) {
	$phone = isset( $lead->phone ) ? preg_replace( '/[^0-9+]/', '', (string) $lead->phone ) : '';

	return '' !== $phone ? 'tel:' . $phone : '';
}

/**
 * Returns a WhatsApp URL for an interested student.
 *
 * @param object $lead Interested student row.
 * @return string
 */
function cmp_get_interested_student_whatsapp_url( $lead ) {
	$phone_keys = isset( $lead->phone ) ? cmp_phone_match_keys( $lead->phone ) : array();

	if ( empty( $phone_keys ) ) {
		return '';
	}

	return 'https://wa.me/' . rawurlencode( $phone_keys[0] ) . '?text=' . rawurlencode( cmp_build_interested_student_follow_up_message( $lead ) );
}

/**
 * Returns the admin action URL for an interested-student follow-up email.
 *
 * @param object $lead Interested student row.
 * @param string $return_page Return page slug.
 * @param array  $return_args Return query arguments.
 * @return string
 */
function cmp_get_interested_student_email_url( $lead, $return_page = 'cmp-interested-students', $return_args = array() ) {
	if ( empty( $lead->email ) || empty( $lead->id ) ) {
		return '';
	}

	$redirect_args = array();

	foreach ( $return_args as $key => $value ) {
		if ( is_scalar( $value ) ) {
			$redirect_args[ 'return_' . sanitize_key( $key ) ] = wp_unslash( (string) $value );
		}
	}

	$url = add_query_arg(
		array_merge(
			array(
				'action'      => 'cmp_send_interested_student_email',
				'id'          => (int) $lead->id,
				'return_page' => cmp_clean_return_page( $return_page, 'cmp-interested-students' ),
			),
			$redirect_args
		),
		admin_url( 'admin-post.php' )
	);

	return wp_nonce_url( $url, 'cmp_send_interested_student_email_' . (int) $lead->id );
}

/**
 * Renders follow-up actions for one interested student row.
 *
 * @param object $lead Interested student row.
 * @param array  $context Render context.
 * @return void
 */
function cmp_render_interested_student_actions( $lead, $context = array() ) {
	$call_url     = cmp_get_interested_student_call_url( $lead );
	$whatsapp_url = cmp_get_interested_student_whatsapp_url( $lead );
	$email_url    = cmp_get_interested_student_email_url(
		$lead,
		isset( $context['return_page'] ) ? $context['return_page'] : 'cmp-interested-students',
		isset( $context['return_args'] ) && is_array( $context['return_args'] ) ? $context['return_args'] : array()
	);

	if ( $call_url ) {
		echo '<a href="' . esc_url( $call_url ) . '">' . esc_html__( 'Call', 'class-manager-pro' ) . '</a> ';
	}

	if ( $whatsapp_url ) {
		echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'WhatsApp', 'class-manager-pro' ) . '</a> ';
	}

	if ( $email_url ) {
		echo '<a href="' . esc_url( $email_url ) . '" class="cmp-send-interested-email-link" data-cmp-interested-email="1" data-cmp-lead-id="' . esc_attr( ! empty( $lead->id ) ? (int) $lead->id : 0 ) . '">' . esc_html__( 'Send Mail', 'class-manager-pro' ) . '</a>';
	}
}

/**
 * Renders a reusable interested students table.
 *
 * @param array  $rows Interested student rows.
 * @param string $empty_message Empty-state message.
 * @param array  $context Render context.
 * @return void
 */
function cmp_render_interested_students_table( $rows, $empty_message = '', $context = array() ) {
	$empty_message = '' !== $empty_message ? $empty_message : __( 'No interested students found.', 'class-manager-pro' );
	$context       = wp_parse_args(
		$context,
		array(
			'return_page' => cmp_clean_return_page( cmp_field( $_GET, 'page', 'cmp-interested-students' ), 'cmp-interested-students' ),
			'return_args' => array(),
		)
	);
	$follow_up_statuses = cmp_get_interested_student_follow_up_statuses();
	ob_start();
	?>
	<div class="cmp-table-scroll">
		<table class="widefat striped" id="cmp-interested-table">
			<thead>
				<tr>
					<th><input type="checkbox" id="cmp-interested-select-all" data-cmp-select-all="#cmp-interested-table .cmp-interested-select" aria-label="<?php esc_attr_e( 'Select all interested students', 'class-manager-pro' ); ?>"></th>
					<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Contact', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Batch / Teacher', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'CRM Status', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Timeline', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php echo esc_html( $empty_message ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $lead ) : ?>
						<tr>
							<td>
								<input type="checkbox" class="cmp-interested-select" value="<?php echo esc_attr( (int) $lead->id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'class-manager-pro' ), $lead->name ) ); ?>">
							</td>
							<td>
								<strong><?php echo esc_html( $lead->name ); ?></strong>
								<br><span class="cmp-muted"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $lead->payment_status ) ) ); ?></span>
								<?php if ( ! empty( $lead->follow_up_status ) ) : ?>
									<br><span class="cmp-status cmp-status-<?php echo esc_attr( $lead->follow_up_status ); ?>"><?php echo esc_html( isset( $follow_up_statuses[ $lead->follow_up_status ] ) ? $follow_up_statuses[ $lead->follow_up_status ] : ucwords( str_replace( array( '_', '-' ), ' ', $lead->follow_up_status ) ) ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $lead->phone ? $lead->phone : __( 'Not set', 'class-manager-pro' ) ); ?>
								<br><span class="cmp-muted"><?php echo esc_html( $lead->email ? $lead->email : __( 'Not set', 'class-manager-pro' ) ); ?></span>
								<?php if ( ! empty( $lead->contact_attempts ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( sprintf( __( 'Attempts: %d', 'class-manager-pro' ), (int) $lead->contact_attempts ) ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $lead->email_status ) || ! empty( $lead->whatsapp_status ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( trim( sprintf( __( 'Email: %1$s | WhatsApp: %2$s', 'class-manager-pro' ), $lead->email_status ? ucfirst( $lead->email_status ) : __( 'None', 'class-manager-pro' ), $lead->whatsapp_status ? ucfirst( $lead->whatsapp_status ) : __( 'None', 'class-manager-pro' ) ) ) ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( ! empty( $lead->class_name ) ? $lead->class_name : __( 'Not assigned', 'class-manager-pro' ) ); ?>
								<br><span class="cmp-muted"><?php echo esc_html( ! empty( $lead->batch_name ) ? $lead->batch_name : __( 'Batch not assigned', 'class-manager-pro' ) ); ?></span>
								<?php if ( ! empty( $lead->teacher_user_id ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( cmp_get_teacher_label( (int) $lead->teacher_user_id ) ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="cmp_save_interested_student_follow_up_ajax">
									<input type="hidden" name="action" value="cmp_save_interested_student_follow_up">
									<input type="hidden" name="id" value="<?php echo esc_attr( (int) $lead->id ); ?>">
									<?php wp_nonce_field( 'cmp_save_interested_student_follow_up_' . (int) $lead->id ); ?>
									<?php cmp_render_return_hidden_fields( $context['return_page'], $context['return_args'] ); ?>
									<p>
										<select name="follow_up_status">
											<?php foreach ( $follow_up_statuses as $status_key => $status_label ) : ?>
												<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( isset( $lead->follow_up_status ) ? $lead->follow_up_status : '', $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</p>
									<p>
										<textarea name="follow_up_notes" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Add follow-up notes', 'class-manager-pro' ); ?>"><?php echo esc_textarea( isset( $lead->follow_up_notes ) ? $lead->follow_up_notes : '' ); ?></textarea>
									</p>
									<?php if ( ! empty( $lead->follow_up_updated_at ) ) : ?>
										<p class="cmp-muted"><?php echo esc_html( sprintf( __( 'Updated: %s', 'class-manager-pro' ), $lead->follow_up_updated_at ) ); ?></p>
									<?php endif; ?>
									<p><button type="submit" class="button button-small"><?php esc_html_e( 'Save Follow-up', 'class-manager-pro' ); ?></button></p>
								</form>
							</td>
							<td>
								<?php $timeline = cmp_get_interested_student_timeline( (int) $lead->id, 3 ); ?>
								<?php if ( ! empty( $timeline ) ) : ?>
									<details class="cmp-crm-timeline">
										<summary><?php esc_html_e( 'Recent activity', 'class-manager-pro' ); ?></summary>
										<ul class="cmp-crm-timeline-list">
											<?php foreach ( $timeline as $item ) : ?>
												<li>
													<strong><?php echo esc_html( $item->timeline_event_label ); ?></strong>
													<?php if ( ! empty( $item->status_label ) ) : ?>
														<span class="cmp-muted"><?php echo esc_html( $item->status_label ); ?></span>
													<?php endif; ?>
													<br><span class="cmp-muted"><?php echo esc_html( $item->created_at ); ?></span>
													<?php if ( ! empty( $item->notes ) ) : ?>
														<br><span class="cmp-muted"><?php echo esc_html( $item->notes ); ?></span>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php else : ?>
									<span class="cmp-muted"><?php esc_html_e( 'No timeline yet.', 'class-manager-pro' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="cmp-actions"><?php cmp_render_interested_student_actions( $lead, $context ); ?></td>
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
 * Migrates legacy failed-import student rows into interested students.
 *
 * @return void
 */
function cmp_migrate_failed_import_students_to_interested() {
	global $wpdb;

	$rows = $wpdb->get_results(
		'SELECT s.*
		FROM ' . cmp_table( 'students' ) . ' s
		WHERE s.batch_id = 0
			AND s.paid_fee <= 0
			AND (
				LOWER(COALESCE(s.notes, \'\')) LIKE \'%payment import status: failed%\'
				OR LOWER(COALESCE(s.notes, \'\')) LIKE \'%pending payment import student%\'
			)
		ORDER BY s.id ASC'
	);

	if ( empty( $rows ) ) {
		return;
	}

	foreach ( $rows as $row ) {
		$result = cmp_record_failed_payment_interest(
			array(
				'name'         => $row->name,
				'phone'        => $row->phone,
				'email'        => $row->email,
				'class_id'     => (int) $row->class_id,
				'batch_id'     => 0,
				'payment_source' => 'razorpay_csv',
				'attempt_amount' => 0,
				'notes'        => $row->notes,
			)
		);

		if ( is_wp_error( $result ) ) {
			continue;
		}

		$wpdb->delete( cmp_table( 'students' ), array( 'id' => (int) $row->id ), array( '%d' ) );
	}
}

/**
 * Returns the supported announcement email formats.
 *
 * @return array
 */
function cmp_get_announcement_email_formats() {
	return array(
		'plain' => __( 'Plain Text', 'class-manager-pro' ),
		'html'  => __( 'HTML Email', 'class-manager-pro' ),
	);
}

/**
 * Sanitizes an announcement email format value.
 *
 * @param string $email_format Requested format.
 * @return string
 */
function cmp_clean_announcement_email_format( $email_format ) {
	$email_format = sanitize_key( (string) $email_format );

	return array_key_exists( $email_format, cmp_get_announcement_email_formats() ) ? $email_format : 'plain';
}

/**
 * Returns the allowed HTML tags for batch announcements.
 *
 * @return array
 */
function cmp_get_announcement_email_allowed_html() {
	return array(
		'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'style' => true, 'class' => true ),
		'b'      => array( 'style' => true, 'class' => true ),
		'blockquote' => array( 'style' => true, 'class' => true ),
		'br'     => array(),
		'div'    => array( 'style' => true, 'class' => true, 'align' => true ),
		'em'     => array( 'style' => true, 'class' => true ),
		'h1'     => array( 'style' => true, 'class' => true ),
		'h2'     => array( 'style' => true, 'class' => true ),
		'h3'     => array( 'style' => true, 'class' => true ),
		'h4'     => array( 'style' => true, 'class' => true ),
		'h5'     => array( 'style' => true, 'class' => true ),
		'h6'     => array( 'style' => true, 'class' => true ),
		'hr'     => array( 'style' => true, 'class' => true ),
		'img'    => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true, 'class' => true ),
		'li'     => array( 'style' => true, 'class' => true ),
		'ol'     => array( 'style' => true, 'class' => true ),
		'p'      => array( 'style' => true, 'class' => true, 'align' => true ),
		'span'   => array( 'style' => true, 'class' => true ),
		'strong' => array( 'style' => true, 'class' => true ),
		'table'  => array( 'style' => true, 'class' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'width' => true ),
		'tbody'  => array( 'style' => true, 'class' => true ),
		'td'     => array( 'style' => true, 'class' => true, 'colspan' => true, 'rowspan' => true, 'align' => true, 'valign' => true, 'width' => true ),
		'tfoot'  => array( 'style' => true, 'class' => true ),
		'th'     => array( 'style' => true, 'class' => true, 'colspan' => true, 'rowspan' => true, 'align' => true, 'valign' => true, 'width' => true ),
		'thead'  => array( 'style' => true, 'class' => true ),
		'tr'     => array( 'style' => true, 'class' => true ),
		'ul'     => array( 'style' => true, 'class' => true ),
	);
}

/**
 * Sanitizes a batch announcement message for storage and sending.
 *
 * @param string $message Announcement content.
 * @param string $email_format Announcement email format.
 * @return string
 */
function cmp_sanitize_announcement_message( $message, $email_format = 'plain' ) {
	$message      = (string) $message;
	$email_format = cmp_clean_announcement_email_format( $email_format );

	if ( 'html' === $email_format ) {
		return trim( (string) wp_kses( $message, cmp_get_announcement_email_allowed_html() ) );
	}

	return sanitize_textarea_field( $message );
}

/**
 * Prepares an announcement email body.
 *
 * @param string $message Announcement content.
 * @param string $email_format Announcement email format.
 * @return string
 */
function cmp_prepare_announcement_email_body( $message, $email_format = 'plain' ) {
	$email_format = cmp_clean_announcement_email_format( $email_format );
	$message      = cmp_sanitize_announcement_message( $message, $email_format );

	if ( 'html' === $email_format ) {
		return '' !== trim( $message ) ? $message : cmp_prepare_email_body( $message );
	}

	return cmp_prepare_email_body( $message );
}

/**
 * Returns whether WhatsApp notification delivery is enabled.
 *
 * @return bool
 */
function cmp_is_whatsapp_delivery_enabled() {
	return '1' === (string) get_option( 'cmp_notifications_enabled', get_option( 'cmp_sms_enabled', '0' ) );
}

/**
 * Sends a generic announcement message through email or the configured webhook.
 *
 * @param object $recipient Recipient-like object.
 * @param string $channel email|whatsapp.
 * @param string $subject Subject line.
 * @param string $message Message body.
 * @param array  $context Extra context.
 * @return array
 */
function cmp_send_generic_notification_message( $recipient, $channel, $subject, $message, $context = array() ) {
	$channel      = sanitize_key( $channel );
	$subject      = sanitize_text_field( $subject );
	$message      = (string) $message;
	$email_format = cmp_clean_announcement_email_format( cmp_field( $context, 'email_format', 'plain' ) );

	if ( 'email' === $channel ) {
		if ( empty( $recipient->email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Email address is missing.', 'class-manager-pro' ),
			);
		}

		$sent = wp_mail(
			sanitize_email( $recipient->email ),
			$subject,
			cmp_prepare_announcement_email_body( $message, $email_format ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		return array(
			'success' => (bool) $sent,
			'message' => $sent ? __( 'Email sent.', 'class-manager-pro' ) : __( 'Email could not be sent.', 'class-manager-pro' ),
		);
	}

	if ( empty( $recipient->phone ) ) {
		return array(
			'success' => false,
			'message' => __( 'Phone number is missing.', 'class-manager-pro' ),
		);
	}

	if ( 'html' === $email_format ) {
		$message = trim( wp_strip_all_tags( $message ) );
	}

	$provider = sanitize_key( (string) get_option( 'cmp_notification_provider', 'log_only' ) );

	if ( 'custom_webhook' !== $provider ) {
		return array(
			'success' => true,
			'message' => __( 'Notification logged without external delivery.', 'class-manager-pro' ),
		);
	}

	$webhook_url = (string) get_option( 'cmp_notification_webhook_url', '' );

	if ( '' === $webhook_url ) {
		return array(
			'success' => false,
			'message' => __( 'Notification webhook URL is missing.', 'class-manager-pro' ),
		);
	}

	$response = wp_remote_post(
		$webhook_url,
		array(
			'timeout' => 20,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-CMP-Token'  => (string) get_option( 'cmp_notification_auth_token', '' ),
			),
			'body'    => wp_json_encode(
				array_merge(
					array(
						'channel'      => $channel,
						'subject'      => $subject,
						'message'      => $message,
						'sender'       => (string) get_option( 'cmp_notification_sender', '' ),
						'student_name' => isset( $recipient->name ) ? $recipient->name : '',
						'phone'        => isset( $recipient->phone ) ? $recipient->phone : '',
						'email'        => isset( $recipient->email ) ? $recipient->email : '',
						'class_name'   => isset( $recipient->class_name ) ? $recipient->class_name : '',
						'batch_name'   => isset( $recipient->batch_name ) ? $recipient->batch_name : '',
					),
					is_array( $context ) ? $context : array()
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => $response->get_error_message(),
		);
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	return array(
		'success' => $code >= 200 && $code < 300,
		'message' => (string) wp_remote_retrieve_body( $response ),
	);
}

/**
 * Returns saved announcements for one batch.
 *
 * @param int $batch_id Batch ID.
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_batch_announcements( $batch_id, $limit = 20 ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$limit    = max( 1, absint( $limit ) );

	if ( ! $batch_id ) {
		return array();
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT a.*, b.batch_name, b.class_id, u.display_name AS created_by_name
			FROM ' . cmp_table( 'batch_announcements' ) . ' a
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = a.batch_id
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = a.created_by
			WHERE a.batch_id = %d
			ORDER BY a.created_at DESC, a.id DESC
			LIMIT %d',
			$batch_id,
			$limit
		)
	);
}

/**
 * Returns one announcement history row.
 *
 * @param int $announcement_id Announcement ID.
 * @return object|null
 */
function cmp_get_batch_announcement( $announcement_id ) {
	global $wpdb;

	$announcement_id = absint( $announcement_id );

	if ( ! $announcement_id ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT a.*, b.batch_name, b.class_id, u.display_name AS created_by_name
			FROM ' . cmp_table( 'batch_announcements' ) . ' a
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = a.batch_id
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = a.created_by
			WHERE a.id = %d
			LIMIT 1',
			$announcement_id
		)
	);
}

/**
 * Sends a batch announcement to all students in the batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $subject Subject line.
 * @param string $message Announcement body.
 * @param bool   $send_whatsapp Whether WhatsApp delivery should be attempted.
 * @param string $email_format Email body format.
 * @return array|WP_Error
 */
function cmp_send_batch_announcement( $batch_id, $subject, $message, $send_whatsapp = false, $email_format = 'plain' ) {
	global $wpdb;

	$batch_id      = absint( $batch_id );
	$subject       = sanitize_text_field( $subject );
	$send_whatsapp = (bool) $send_whatsapp;
	$email_format  = cmp_clean_announcement_email_format( $email_format );
	$message       = cmp_sanitize_announcement_message( $message, $email_format );
	$batch         = cmp_get_batch( $batch_id );
	$students      = $batch ? cmp_get_students( array( 'batch_id' => $batch_id ) ) : array();

	if ( ! $batch ) {
		return new WP_Error( 'cmp_batch_invalid', __( 'Batch not found.', 'class-manager-pro' ) );
	}

	if ( '' === trim( $message ) ) {
		return new WP_Error( 'cmp_announcement_empty', __( 'Please enter an announcement message.', 'class-manager-pro' ) );
	}

	if ( '' === $subject ) {
		$subject = sprintf(
			/* translators: %s: batch name */
			__( 'Announcement for %s', 'class-manager-pro' ),
			$batch->batch_name
		);
	}

	if ( empty( $students ) ) {
		return new WP_Error( 'cmp_announcement_no_students', __( 'This batch does not have any students yet.', 'class-manager-pro' ) );
	}

	$summary = array(
		'recipients'      => count( $students ),
		'email_sent'      => 0,
		'email_failed'    => 0,
		'whatsapp_sent'   => 0,
		'whatsapp_failed' => 0,
		'failed_email_recipients' => array(),
	);

	foreach ( $students as $student ) {
		$email_result = cmp_send_generic_notification_message(
			$student,
			'email',
			$subject,
			$message,
			array(
				'action'       => 'batch_announcement',
				'batch_id'     => $batch_id,
				'email_format' => $email_format,
			)
		);

		if ( ! empty( $email_result['success'] ) ) {
			++$summary['email_sent'];
		} else {
			++$summary['email_failed'];
			$summary['failed_email_recipients'][] = array(
				'student_id'  => isset( $student->id ) ? (int) $student->id : 0,
				'name'        => isset( $student->name ) ? $student->name : '',
				'email'       => isset( $student->email ) ? $student->email : '',
				'phone'       => isset( $student->phone ) ? $student->phone : '',
				'class_name'  => isset( $student->class_name ) ? $student->class_name : '',
				'batch_name'  => isset( $student->batch_name ) ? $student->batch_name : $batch->batch_name,
				'batch_id'    => $batch_id,
			);
		}

		if ( $send_whatsapp ) {
			$whatsapp_result = cmp_send_generic_notification_message(
				$student,
				'whatsapp',
				$subject,
				$message,
				array(
					'action'       => 'batch_announcement',
					'batch_id'     => $batch_id,
					'email_format' => $email_format,
				)
			);

			if ( ! empty( $whatsapp_result['success'] ) ) {
				++$summary['whatsapp_sent'];
			} else {
				++$summary['whatsapp_failed'];
			}
		}
	}

	$wpdb->insert(
		cmp_table( 'batch_announcements' ),
		array(
			'batch_id'                => $batch_id,
			'subject'                 => $subject,
			'message'                 => $message,
			'email_format'            => $email_format,
			'channels'                => $send_whatsapp ? 'email,whatsapp' : 'email',
			'recipients'              => (int) $summary['recipients'],
			'email_sent'              => (int) $summary['email_sent'],
			'email_failed'            => (int) $summary['email_failed'],
			'failed_email_recipients' => wp_json_encode( $summary['failed_email_recipients'] ),
			'whatsapp_sent'           => (int) $summary['whatsapp_sent'],
			'whatsapp_failed'         => (int) $summary['whatsapp_failed'],
			'retry_count'             => 0,
			'last_retry_at'           => null,
			'created_by'              => get_current_user_id(),
			'created_at'              => cmp_current_datetime(),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s' )
	);

	$summary['announcement_id'] = (int) $wpdb->insert_id;

	cmp_log_activity(
		array(
			'batch_id' => $batch_id,
			'class_id' => (int) $batch->class_id,
			'action'   => 'batch_announcement_sent',
			'message'  => sprintf(
				/* translators: 1: batch name 2: recipient count */
				__( 'Announcement sent for batch "%1$s" to %2$d students.', 'class-manager-pro' ),
				$batch->batch_name,
				(int) $summary['recipients']
			),
		)
	);
	cmp_log_admin_action(
		'send_batch_announcement',
		'batch',
		$batch_id,
		sprintf(
			/* translators: 1: batch name 2: recipient count */
			__( 'Announcement sent for batch "%1$s" to %2$d students.', 'class-manager-pro' ),
			$batch->batch_name,
			(int) $summary['recipients']
		)
	);

	do_action(
		'cmp_batch_announcement_sent',
		$summary,
		$batch,
		$subject,
		$message,
		$send_whatsapp,
		$email_format
	);

	return $summary;
}

/**
 * Handles the batch announcement submission.
 *
 * @return void
 */
function cmp_handle_send_batch_announcement() {
	cmp_require_manage_options();

	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_send_batch_announcement_' . $batch_id );

	$result = cmp_send_batch_announcement(
		$batch_id,
		cmp_field( $_POST, 'subject' ),
		wp_unslash( (string) cmp_field( $_POST, 'message' ) ),
		! empty( $_POST['send_whatsapp'] ) && cmp_is_whatsapp_delivery_enabled(),
		cmp_field( $_POST, 'email_format', 'plain' )
	);

	if ( is_wp_error( $result ) ) {
		cmp_redirect(
			'cmp-batches',
			$result->get_error_message(),
			'error',
			array(
				'action' => 'view',
				'id'     => $batch_id,
				'tab'    => 'announcements',
			)
		);
	}

	$message = sprintf(
		/* translators: 1: email sent count 2: email failed count */
		__( 'Announcement sent. Email sent: %1$d. Email failed: %2$d.', 'class-manager-pro' ),
		(int) $result['email_sent'],
		(int) $result['email_failed']
	);

	if ( ! empty( $_POST['send_whatsapp'] ) ) {
		$message .= ' ' . sprintf(
			/* translators: 1: WhatsApp sent count 2: WhatsApp failed count */
			__( 'WhatsApp sent: %1$d. WhatsApp failed: %2$d.', 'class-manager-pro' ),
			(int) $result['whatsapp_sent'],
			(int) $result['whatsapp_failed']
		);
	}

	cmp_redirect(
		'cmp-batches',
		$message,
		'success',
		array(
			'action' => 'view',
			'id'     => $batch_id,
			'tab'    => 'announcements',
		)
	);
}

/**
 * Retries failed announcement emails for one history row.
 *
 * @param int $announcement_id Announcement ID.
 * @return array|WP_Error
 */
function cmp_retry_batch_announcement_failed_emails( $announcement_id ) {
	global $wpdb;

	$announcement = cmp_get_batch_announcement( $announcement_id );

	if ( ! $announcement ) {
		return new WP_Error( 'cmp_announcement_missing', __( 'Announcement history entry not found.', 'class-manager-pro' ) );
	}

	$failed_recipients = json_decode( (string) $announcement->failed_email_recipients, true );

	if ( empty( $failed_recipients ) || ! is_array( $failed_recipients ) ) {
		return new WP_Error( 'cmp_announcement_no_failed_emails', __( 'There are no failed emails left to resend.', 'class-manager-pro' ) );
	}

	$email_format = isset( $announcement->email_format ) ? $announcement->email_format : 'plain';
	$remaining    = array();
	$retried_sent = 0;
	$retried_fail = 0;

	foreach ( $failed_recipients as $recipient_data ) {
		$recipient = (object) wp_parse_args(
			is_array( $recipient_data ) ? $recipient_data : array(),
			array(
				'name'       => '',
				'email'      => '',
				'phone'      => '',
				'class_name' => '',
				'batch_name' => $announcement->batch_name,
			)
		);

		$result = cmp_send_generic_notification_message(
			$recipient,
			'email',
			$announcement->subject,
			$announcement->message,
			array(
				'action'          => 'batch_announcement_retry',
				'batch_id'        => (int) $announcement->batch_id,
				'announcement_id' => (int) $announcement->id,
				'email_format'    => $email_format,
			)
		);

		if ( ! empty( $result['success'] ) ) {
			++$retried_sent;
			continue;
		}

		++$retried_fail;
		$remaining[] = (array) $recipient;
	}

	$updated = $wpdb->update(
		cmp_table( 'batch_announcements' ),
		array(
			'email_sent'              => (int) $announcement->email_sent + $retried_sent,
			'email_failed'            => count( $remaining ),
			'failed_email_recipients' => wp_json_encode( $remaining ),
			'retry_count'             => (int) $announcement->retry_count + 1,
			'last_retry_at'           => cmp_current_datetime(),
		),
		array(
			'id' => (int) $announcement->id,
		),
		array( '%d', '%d', '%s', '%d', '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_announcement_retry_failed', __( 'Failed-email retry results could not be saved.', 'class-manager-pro' ) );
	}

	return array(
		'sent'      => $retried_sent,
		'failed'    => $retried_fail,
		'remaining' => count( $remaining ),
		'batch_id'  => (int) $announcement->batch_id,
	);
}

/**
 * Handles failed-email retry actions for announcements.
 *
 * @return void
 */
function cmp_handle_retry_batch_announcement_failed_emails() {
	cmp_require_manage_options();

	$announcement_id = absint( cmp_field( $_POST, 'announcement_id', 0 ) );
	$batch_id        = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_retry_batch_announcement_failed_emails_' . $announcement_id );

	$result = cmp_retry_batch_announcement_failed_emails( $announcement_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect(
			'cmp-batches',
			$result->get_error_message(),
			'error',
			array(
				'action' => 'view',
				'id'     => $batch_id,
				'tab'    => 'announcements',
			)
		);
	}

	cmp_redirect(
		'cmp-batches',
		sprintf(
			/* translators: 1: sent count 2: failed count 3: remaining count */
			__( 'Failed emails retried. Sent: %1$d. Failed: %2$d. Remaining: %3$d.', 'class-manager-pro' ),
			(int) $result['sent'],
			(int) $result['failed'],
			(int) $result['remaining']
		),
		'success',
		array(
			'action' => 'view',
			'id'     => (int) $result['batch_id'],
			'tab'    => 'announcements',
		)
	);
}

/**
 * Generates a unique temporary registration token.
 *
 * @return string
 */
function cmp_generate_batch_registration_token() {
	global $wpdb;

	do {
		$token  = wp_generate_password( 32, false, false );
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . cmp_table( 'batch_registration_tokens' ) . ' WHERE token = %s',
				$token
			)
		);
	} while ( $exists > 0 );

	return $token;
}

/**
 * Returns the most recent temporary registration token for a batch.
 *
 * @param int  $batch_id Batch ID.
 * @param bool $active_only Whether only active tokens should be returned.
 * @return object|null
 */
function cmp_get_batch_registration_token_for_batch( $batch_id, $active_only = true ) {
	global $wpdb;

	$batch_id = absint( $batch_id );

	if ( ! $batch_id ) {
		return null;
	}

	$where  = array( 't.batch_id = %d' );
	$params = array( $batch_id );

	if ( $active_only ) {
		$where[] = 't.used_at IS NULL';
		$where[] = 't.closed_at IS NULL';
		$where[] = 't.expires_at >= %s';
		$params[] = cmp_current_datetime();
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT t.*, b.batch_name, b.class_id, c.name AS class_name
			FROM ' . cmp_table( 'batch_registration_tokens' ) . ' t
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = t.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY t.created_at DESC, t.id DESC
			LIMIT 1',
			$params
		)
	);
}

/**
 * Returns a temporary registration token row by token value.
 *
 * @param string $token Token value.
 * @param bool   $allow_used Whether used tokens are allowed.
 * @param bool   $allow_expired Whether expired tokens are allowed.
 * @return object|null
 */
function cmp_get_batch_registration_token_record( $token, $allow_used = false, $allow_expired = false ) {
	global $wpdb;

	$token = sanitize_text_field( $token );

	if ( '' === $token ) {
		return null;
	}

	$where  = array( 't.token = %s' );
	$params = array( $token );

	if ( ! $allow_used ) {
		$where[] = 't.used_at IS NULL';
	}

	if ( ! $allow_expired ) {
		$where[]  = 't.expires_at >= %s';
		$params[] = cmp_current_datetime();
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT t.*, b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee
			FROM ' . cmp_table( 'batch_registration_tokens' ) . ' t
			INNER JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = t.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY t.id DESC
			LIMIT 1',
			$params
		)
	);
}

/**
 * Creates a new temporary registration link for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return object|WP_Error
 */
function cmp_create_batch_registration_token( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $batch ) {
		return new WP_Error( 'cmp_batch_invalid', __( 'Batch not found.', 'class-manager-pro' ) );
	}

	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . cmp_table( 'batch_registration_tokens' ) . '
			SET expires_at = %s
			WHERE batch_id = %d
				AND used_at IS NULL
				AND expires_at > %s',
			wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - MINUTE_IN_SECONDS ),
			$batch_id,
			cmp_current_datetime()
		)
	);

	$token      = cmp_generate_batch_registration_token();
	$expires_at = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( 10 * MINUTE_IN_SECONDS ) );
	$result     = $wpdb->insert(
		cmp_table( 'batch_registration_tokens' ),
		array(
			'batch_id'        => $batch_id,
			'token'           => $token,
			'expires_at'      => $expires_at,
			'used_student_id' => 0,
			'created_by'      => get_current_user_id(),
			'created_at'      => cmp_current_datetime(),
		),
		array( '%d', '%s', '%s', '%d', '%d', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_registration_link_failed', __( 'The temporary registration link could not be generated.', 'class-manager-pro' ) );
	}

	return cmp_get_batch_registration_token_record( $token, true, true );
}

/**
 * Closes any active temporary registration links for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return int|WP_Error
 */
function cmp_close_batch_registration_link( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $batch ) {
		return new WP_Error( 'cmp_batch_invalid', __( 'Batch not found.', 'class-manager-pro' ) );
	}

	$result = $wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . cmp_table( 'batch_registration_tokens' ) . '
			SET expires_at = %s,
				closed_at = %s
			WHERE batch_id = %d
				AND used_at IS NULL
				AND closed_at IS NULL',
			wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - MINUTE_IN_SECONDS ),
			cmp_current_datetime(),
			$batch_id
		)
	);

	return false === $result ? new WP_Error( 'cmp_registration_link_close_failed', __( 'The temporary registration link could not be closed.', 'class-manager-pro' ) ) : (int) $result;
}

/**
 * Marks a temporary registration token as used.
 *
 * @param string $token Token value.
 * @param int    $student_id Created student ID.
 * @return void
 */
function cmp_mark_batch_registration_token_used( $token, $student_id = 0 ) {
	global $wpdb;

	$token = sanitize_text_field( $token );

	if ( '' === $token ) {
		return;
	}

	$wpdb->update(
		cmp_table( 'batch_registration_tokens' ),
		array(
			'used_at'         => cmp_current_datetime(),
			'used_student_id' => absint( $student_id ),
		),
		array(
			'token' => $token,
		),
		array( '%s', '%d' ),
		array( '%s' )
	);
}

/**
 * Builds the frontend URL for a temporary registration token.
 *
 * @param object $token_row Token row.
 * @return string
 */
function cmp_get_batch_temporary_registration_url( $token_row ) {
	if ( ! $token_row || empty( $token_row->token ) ) {
		return '';
	}

	return add_query_arg(
		array(
			'token' => sanitize_text_field( $token_row->token ),
		),
		home_url( '/register/' )
	);
}

/**
 * Handles the admin action that generates a temporary registration link.
 *
 * @return void
 */
function cmp_handle_generate_batch_registration_link() {
	cmp_require_manage_options();

	$batch_id = absint( cmp_field( $_REQUEST, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_generate_batch_registration_link_' . $batch_id );

	$result = cmp_create_batch_registration_token( $batch_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect(
			'cmp-batches',
			$result->get_error_message(),
			'error',
			array(
				'action' => 'view',
				'id'     => $batch_id,
			)
		);
	}

	cmp_redirect(
		'cmp-batches',
		__( 'Temporary registration link generated. It stays active for 10 minutes or until one successful registration uses it.', 'class-manager-pro' ),
		'success',
		array(
			'action' => 'view',
			'id'     => $batch_id,
		)
	);
}

/**
 * Handles manual link-close requests for temporary registration links.
 *
 * @return void
 */
function cmp_handle_close_batch_registration_link() {
	cmp_require_manage_options();

	$batch_id = absint( cmp_field( $_REQUEST, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_close_batch_registration_link_' . $batch_id );

	$result = cmp_close_batch_registration_link( $batch_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect(
			'cmp-batches',
			$result->get_error_message(),
			'error',
			array(
				'action' => 'view',
				'id'     => $batch_id,
			)
		);
	}

	cmp_redirect(
		'cmp-batches',
		$result > 0 ? __( 'Temporary registration link closed successfully.', 'class-manager-pro' ) : __( 'No active temporary registration link was open for this batch.', 'class-manager-pro' ),
		$result > 0 ? 'success' : 'warning',
		array(
			'action' => 'view',
			'id'     => $batch_id,
		)
	);
}

/**
 * Returns a normalized registration-link status payload.
 *
 * @param object|null $token_row Registration token row.
 * @return array
 */
function cmp_get_batch_registration_link_status( $token_row ) {
	$now = current_time( 'timestamp' );

	if ( ! $token_row ) {
		return array(
			'key'         => 'inactive',
			'label'       => __( 'Inactive', 'class-manager-pro' ),
			'description' => __( 'No registration link is active right now.', 'class-manager-pro' ),
		);
	}

	if ( ! empty( $token_row->used_at ) ) {
		return array(
			'key'         => 'used_successfully',
			'label'       => __( 'Used Successfully', 'class-manager-pro' ),
			'description' => __( 'This temporary link has already completed one successful registration.', 'class-manager-pro' ),
		);
	}

	if ( ! empty( $token_row->closed_at ) ) {
		return array(
			'key'         => 'closed_by_admin',
			'label'       => __( 'Closed by Admin', 'class-manager-pro' ),
			'description' => __( 'This temporary link was manually closed by an administrator.', 'class-manager-pro' ),
		);
	}

	$expires_at = ! empty( $token_row->expires_at ) ? strtotime( (string) $token_row->expires_at ) : 0;

	if ( $expires_at && $expires_at < $now ) {
		return array(
			'key'         => 'expired',
			'label'       => __( 'Expired', 'class-manager-pro' ),
			'description' => __( 'This temporary link has expired.', 'class-manager-pro' ),
		);
	}

	return array(
		'key'         => 'active',
		'label'       => __( 'Active', 'class-manager-pro' ),
		'description' => __( 'This temporary link is active and can still be used.', 'class-manager-pro' ),
	);
}

/**
 * Returns the registration status for a free batch permanent link.
 *
 * @param object|null $batch Batch row.
 * @return array
 */
function cmp_get_batch_permanent_registration_status( $batch ) {
	$batch = $batch ? cmp_get_batch( (int) $batch->id ) : null;

	if ( ! $batch ) {
		return array(
			'key'         => 'inactive',
			'label'       => __( 'Inactive', 'class-manager-pro' ),
			'description' => __( 'The batch could not be loaded.', 'class-manager-pro' ),
		);
	}

	if ( empty( $batch->is_free ) ) {
		return array(
			'key'         => 'paid_only',
			'label'       => __( 'Paid Batch Only', 'class-manager-pro' ),
			'description' => __( 'Permanent links are available only for free batches.', 'class-manager-pro' ),
		);
	}

	if ( empty( $batch->permanent_registration_enabled ) ) {
		return array(
			'key'         => 'disabled',
			'label'       => __( 'Disabled', 'class-manager-pro' ),
			'description' => __( 'Permanent registration is turned off for this batch.', 'class-manager-pro' ),
		);
	}

	$batch_count = cmp_get_batch_student_count( (int) $batch->id );

	if ( ! empty( $batch->intake_limit ) && $batch_count >= (int) $batch->intake_limit ) {
		return array(
			'key'         => 'full',
			'label'       => __( 'Batch Full', 'class-manager-pro' ),
			'description' => __( 'This batch has reached its maximum student limit.', 'class-manager-pro' ),
		);
	}

	return array(
		'key'         => 'active',
		'label'       => __( 'Open', 'class-manager-pro' ),
		'description' => __( 'Permanent registration is open for this free batch.', 'class-manager-pro' ),
	);
}

/**
 * Returns a QR image URL for a registration link.
 *
 * @param string $url Registration URL.
 * @param int    $size QR image size.
 * @return string
 */
function cmp_get_registration_qr_url( $url, $size = 220 ) {
	$url  = esc_url_raw( $url );
	$size = max( 120, absint( $size ) );

	if ( '' === $url ) {
		return '';
	}

	return add_query_arg(
		array(
			'size' => $size . 'x' . $size,
			'data' => $url,
		),
		'https://api.qrserver.com/v1/create-qr-code/'
	);
}

/**
 * Builds a batch admissions overview.
 *
 * @param int $batch_id Batch ID.
 * @return array
 */
function cmp_get_batch_registration_overview( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$batch    = cmp_get_batch( $batch_id );
	$now      = cmp_current_datetime();
	$today    = current_time( 'Y-m-d' );
	$temp_row = cmp_get_batch_registration_token_for_batch( $batch_id );
	$last_row  = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT l.created_at
			FROM ' . cmp_table( 'activity_logs' ) . ' l
			WHERE l.batch_id = %d AND l.action = %s
			ORDER BY l.created_at DESC, l.id DESC
			LIMIT 1',
			$batch_id,
			'registration_recorded'
		)
	);
	$total_registrations = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*)
			FROM ' . cmp_table( 'activity_logs' ) . ' l
			WHERE l.batch_id = %d AND l.action = %s',
			$batch_id,
			'registration_recorded'
		)
	);
	$today_registrations = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*)
			FROM ' . cmp_table( 'activity_logs' ) . ' l
			WHERE l.batch_id = %d AND l.action = %s AND DATE(l.created_at) = %s',
			$batch_id,
			'registration_recorded',
			$today
		)
	);
	$active_temp_links = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'batch_registration_tokens' ) . '
			WHERE batch_id = %d AND used_at IS NULL AND closed_at IS NULL AND expires_at >= %s',
			$batch_id,
			$now
		)
	);
	$permanent_enabled = $batch && ! empty( $batch->is_free ) && ! empty( $batch->permanent_registration_enabled );
	$student_count = $batch ? cmp_get_batch_student_count( $batch_id ) : 0;
	$capacity_limit = $batch ? (int) $batch->intake_limit : 0;
	$batch_full = $capacity_limit > 0 && $student_count >= $capacity_limit;
	$status_key = 'inactive';
	$status_label = __( 'Inactive', 'class-manager-pro' );

	if ( $batch && 'active' === (string) $batch->status ) {
		if ( $batch_full ) {
			$status_key   = 'full';
			$status_label = __( 'Batch Full', 'class-manager-pro' );
		} elseif ( $active_temp_links > 0 || $permanent_enabled ) {
			$status_key   = 'active';
			$status_label = __( 'Open', 'class-manager-pro' );
		} else {
			$status_key   = 'closed';
			$status_label = __( 'Closed', 'class-manager-pro' );
		}
	}

	return array(
		'batch_id'              => $batch_id,
		'student_count'         => $student_count,
		'capacity_limit'        => $capacity_limit,
		'capacity_remaining'    => $capacity_limit > 0 ? max( 0, $capacity_limit - $student_count ) : 0,
		'batch_full'            => $batch_full,
		'total_registrations'   => $total_registrations,
		'successful_registrations' => $total_registrations,
		'today_registrations'   => $today_registrations,
		'pending_registrations' => $active_temp_links,
		'temporary_links'       => $active_temp_links,
		'permanent_links'       => $permanent_enabled ? 1 : 0,
		'last_registration_at'  => $last_row ? $last_row->created_at : '',
		'registration_status'   => $status_key,
		'registration_status_label' => $status_label,
		'temporary_status'      => cmp_get_batch_registration_link_status( $temp_row ),
		'temporary_token'       => $temp_row,
		'permanent_status'      => cmp_get_batch_permanent_registration_status( $batch ),
	);
}

/**
 * Returns recent registration history for a batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $search Search term.
 * @param int    $limit Max rows.
 * @return array
 */
function cmp_get_batch_registration_history( $batch_id, $search = '', $limit = 12 ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$limit    = max( 1, absint( $limit ) );
	$search   = trim( sanitize_text_field( $search ) );

	if ( ! $batch_id ) {
		return array();
	}

	$where  = array( 'l.batch_id = %d', 'l.action = %s' );
	$params = array( $batch_id, 'registration_recorded' );

	if ( '' !== $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '('
			. 's.name LIKE %s OR s.phone LIKE %s OR s.email LIKE %s OR b.batch_name LIKE %s OR c.name LIKE %s OR l.message LIKE %s OR l.context LIKE %s'
			. ')';
		array_push( $params, $like, $like, $like, $like, $like, $like, $like );
	}

	$sql = 'SELECT l.*, s.name AS student_name, s.phone AS student_phone, s.email AS student_email, s.unique_id AS student_unique_id, b.batch_name, c.name AS class_name
		FROM ' . cmp_table( 'activity_logs' ) . ' l
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = l.student_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = l.batch_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = l.class_id
		WHERE ' . implode( ' AND ', $where ) . '
		ORDER BY l.created_at DESC, l.id DESC
		LIMIT ' . $limit;

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	if ( ! is_array( $rows ) ) {
		return array();
	}

	foreach ( $rows as $row ) {
		$context = array();

		if ( ! empty( $row->context ) ) {
			$decoded = json_decode( $row->context, true );
			$context = is_array( $decoded ) ? $decoded : array();
		}

		$row->registration_method = sanitize_key( cmp_field( $context, 'registration_method', '' ) );
		$row->registration_method_label = cmp_get_registration_method_label( $row->registration_method );
		$row->registration_source = sanitize_key( cmp_field( $context, 'registration_source', '' ) );
		$row->registration_source_label = cmp_get_registration_source_label( $row->registration_source );
		$row->registration_status = sanitize_key( cmp_field( $context, 'registration_status', 'successful' ) );
		$row->registration_status_label = ucwords( str_replace( array( '_', '-' ), ' ', $row->registration_status ) );
		$row->allow_capacity_override = ! empty( $context['allow_capacity_override'] );
	}

	return $rows;
}
