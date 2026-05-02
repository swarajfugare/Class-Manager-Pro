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

	return 'COALESCE(NULLIF(' . $payment_alias . '.final_amount, 0), ' . $payment_alias . '.amount)';
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
 * @param bool  $apply_gateway_charge Whether the Razorpay charge should be added.
 * @return array
 */
function cmp_get_payment_financial_breakdown( $amount, $apply_gateway_charge = false ) {
	$amount = round( (float) cmp_money_value( $amount ), 2 );
	$charge = $apply_gateway_charge ? cmp_calculate_razorpay_charge_amount( $amount ) : 0;

	return array(
		'original_amount' => $amount,
		'charge_amount'   => round( (float) $charge, 2 ),
		'final_amount'    => round( $amount + (float) $charge, 2 ),
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
		if ( isset( $payment->final_amount ) && (float) $payment->final_amount > 0 ) {
			return (float) $payment->final_amount;
		}

		return isset( $payment->amount ) ? (float) $payment->amount : 0;
	}

	if ( is_array( $payment ) ) {
		if ( isset( $payment['final_amount'] ) && (float) $payment['final_amount'] > 0 ) {
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
				WHEN final_amount <= 0 THEN ROUND(
					CASE
						WHEN charge_amount > 0 AND original_amount > 0 THEN original_amount + charge_amount
						ELSE amount
					END,
					2
				)
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

	if ( $existing ) {
		$result = $wpdb->update(
			cmp_table( 'interested_students' ),
			array(
				'name'            => $name,
				'phone'           => $phone,
				'email'           => $email,
				'class_id'        => $class_id ? $class_id : (int) $existing->class_id,
				'batch_id'        => $batch_id ? $batch_id : (int) $existing->batch_id,
				'payment_status'  => '' !== $payment_status ? $payment_status : $existing->payment_status,
				'payment_source'  => '' !== $payment_source ? $payment_source : $existing->payment_source,
				'attempt_amount'  => $attempt_amount,
				'transaction_id'  => $transaction_id,
				'notes'           => '' !== $notes ? cmp_append_note( $existing->notes, $notes ) : $existing->notes,
				'payment_meta'    => '' !== $payment_meta ? $payment_meta : $existing->payment_meta,
				'last_attempt_at' => $last_attempt_at,
			),
			array(
				'id' => (int) $existing->id,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'cmp_interested_student_update_failed', __( 'The interested student could not be updated.', 'class-manager-pro' ) );
		}

		return (int) $existing->id;
	}

	$result = $wpdb->insert(
		cmp_table( 'interested_students' ),
		array(
			'name'            => $name,
			'phone'           => $phone,
			'email'           => $email,
			'class_id'        => $class_id,
			'batch_id'        => $batch_id,
			'payment_status'  => '' !== $payment_status ? $payment_status : 'failed',
			'payment_source'  => $payment_source,
			'attempt_amount'  => $attempt_amount,
			'transaction_id'  => $transaction_id,
			'notes'           => $notes,
			'payment_meta'    => $payment_meta,
			'last_attempt_at' => $last_attempt_at,
			'created_at'      => cmp_current_datetime(),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_interested_student_insert_failed', __( 'The interested student could not be saved.', 'class-manager-pro' ) );
	}

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

	$sql .= ' ORDER BY i.last_attempt_at DESC, i.id DESC';

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
		'not_answering'           => __( 'Not answering', 'class-manager-pro' ),
		'interested'              => __( 'Interested', 'class-manager-pro' ),
		'ready_to_take_admission' => __( 'Ready to take admission', 'class-manager-pro' ),
		'call_later'              => __( 'Call later', 'class-manager-pro' ),
		'not_interested'          => __( 'Not interested', 'class-manager-pro' ),
	);
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
function cmp_update_interested_student_follow_up( $lead_id, $status, $notes ) {
	global $wpdb;

	$lead   = cmp_get_interested_student( $lead_id );
	$status = sanitize_key( (string) $status );
	$notes  = sanitize_textarea_field( (string) $notes );

	if ( ! $lead ) {
		return new WP_Error( 'cmp_interested_student_missing', __( 'Interested student not found.', 'class-manager-pro' ) );
	}

	if ( ! cmp_current_user_can_manage_interested_student( $lead ) ) {
		return new WP_Error( 'cmp_interested_student_forbidden', __( 'You do not have permission to update this interested student.', 'class-manager-pro' ) );
	}

	$allowed_statuses = array_keys( cmp_get_interested_student_follow_up_statuses() );

	if ( ! in_array( $status, $allowed_statuses, true ) ) {
		$status = '';
	}

	$updated = $wpdb->update(
		cmp_table( 'interested_students' ),
		array(
			'follow_up_status'     => $status,
			'follow_up_notes'      => $notes,
			'follow_up_updated_at' => cmp_current_datetime(),
			'follow_up_updated_by' => get_current_user_id(),
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

	return true;
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
		echo '<a href="' . esc_url( $email_url ) . '">' . esc_html__( 'Send Mail', 'class-manager-pro' ) . '</a>';
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
	?>
	<div class="cmp-table-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Class / Batch', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Payment Attempt', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Follow-up', 'class-manager-pro' ); ?></th>
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
								<strong><?php echo esc_html( $lead->name ); ?></strong>
								<br><span class="cmp-muted"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $lead->payment_status ) ) ); ?></span>
							</td>
							<td><?php echo esc_html( $lead->phone ? $lead->phone : __( 'Not set', 'class-manager-pro' ) ); ?></td>
							<td><?php echo esc_html( $lead->email ? $lead->email : __( 'Not set', 'class-manager-pro' ) ); ?></td>
							<td>
								<?php echo esc_html( ! empty( $lead->class_name ) ? $lead->class_name : __( 'Not assigned', 'class-manager-pro' ) ); ?>
								<br><span class="cmp-muted"><?php echo esc_html( ! empty( $lead->batch_name ) ? $lead->batch_name : __( 'Batch not assigned', 'class-manager-pro' ) ); ?></span>
							</td>
							<td>
								<?php if ( (float) $lead->attempt_amount > 0 ) : ?>
									<?php echo esc_html( cmp_format_money( $lead->attempt_amount ) ); ?>
									<br>
								<?php endif; ?>
								<span class="cmp-muted"><?php echo esc_html( cmp_get_interested_student_source_label( $lead->payment_source ) ); ?></span>
								<br><span class="cmp-muted"><?php echo esc_html( $lead->last_attempt_at ); ?></span>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
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
							<td class="cmp-actions"><?php cmp_render_interested_student_actions( $lead, $context ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
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
			SET expires_at = %s
			WHERE batch_id = %d
				AND used_at IS NULL
				AND expires_at > %s',
			wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - MINUTE_IN_SECONDS ),
			$batch_id,
			cmp_current_datetime()
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
