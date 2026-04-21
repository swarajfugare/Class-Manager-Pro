<?php
/**
 * Shared helpers, CRUD actions, AJAX handlers, and CSV exports.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_cmp_save_class', 'cmp_handle_save_class' );
add_action( 'admin_post_cmp_delete_class', 'cmp_handle_delete_class' );
add_action( 'admin_post_cmp_save_batch', 'cmp_handle_save_batch' );
add_action( 'admin_post_cmp_delete_batch', 'cmp_handle_delete_batch' );
add_action( 'admin_post_cmp_save_student', 'cmp_handle_save_student' );
add_action( 'admin_post_cmp_delete_student', 'cmp_handle_delete_student' );
add_action( 'admin_post_cmp_save_payment', 'cmp_handle_save_payment' );
add_action( 'admin_post_cmp_save_settings', 'cmp_handle_save_settings' );
add_action( 'admin_init', 'cmp_handle_csv_export' );
add_action( 'wp_ajax_cmp_filter_all_data', 'cmp_ajax_filter_all_data' );
add_action( 'wp_ajax_cmp_filter_students', 'cmp_ajax_filter_students' );

/**
 * Returns a plugin table name.
 *
 * @param string $name Unprefixed CMP table suffix.
 * @return string
 */
function cmp_table( $name ) {
	global $wpdb;

	return $wpdb->prefix . 'cmp_' . $name;
}

/**
 * Stops execution for non-admin users.
 */
function cmp_require_manage_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access Class Manager Pro.', 'class-manager-pro' ) );
	}
}

/**
 * Renders the current admin notice from query args.
 */
function cmp_render_notice() {
	$message = sanitize_text_field( cmp_field( $_GET, 'cmp_message' ) );
	$type    = sanitize_key( cmp_field( $_GET, 'cmp_notice', 'success' ) );

	if ( '' === $message ) {
		return;
	}

	$class = 'error' === $type ? 'notice notice-error' : 'notice notice-success';
	printf(
		'<div class="%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $class ),
		esc_html( $message )
	);
}

/**
 * Builds a plugin admin URL.
 *
 * @param string $page Page slug.
 * @param array  $args Extra query args.
 * @return string
 */
function cmp_admin_url( $page, $args = array() ) {
	return add_query_arg(
		array_merge( array( 'page' => $page ), $args ),
		admin_url( 'admin.php' )
	);
}

/**
 * Redirects back to an admin page with a notice.
 *
 * @param string $page Page slug.
 * @param string $message Message.
 * @param string $type Notice type.
 * @param array  $args Extra args.
 */
function cmp_redirect( $page, $message, $type = 'success', $args = array() ) {
	wp_safe_redirect(
		cmp_admin_url(
			$page,
			array_merge(
				$args,
				array(
					'cmp_notice'  => $type,
					'cmp_message' => $message,
				)
			)
		)
	);
	exit;
}

/**
 * Sanitizes a form return page and keeps redirects inside plugin pages.
 *
 * @param string $page Raw page slug.
 * @param string $fallback Fallback page.
 * @return string
 */
function cmp_clean_return_page( $page, $fallback ) {
	$page = sanitize_key( is_scalar( $page ) ? (string) $page : '' );

	return 0 === strpos( $page, 'cmp-' ) ? $page : $fallback;
}

/**
 * Reads a scalar field from request data.
 *
 * @param array  $data Source array.
 * @param string $key Field key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function cmp_field( $data, $key, $default = '' ) {
	if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
		return $default;
	}

	return wp_unslash( $data[ $key ] );
}

/**
 * Normalizes a money input.
 *
 * @param mixed $value Raw value.
 * @return float
 */
function cmp_money_value( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '0';
	$value = preg_replace( '/[^0-9.\-]/', '', $value );

	return max( 0, round( (float) $value, 2 ) );
}

/**
 * Formats money for display.
 *
 * @param mixed $value Amount.
 * @return string
 */
function cmp_format_money( $value ) {
	return number_format_i18n( (float) $value, 2 );
}

/**
 * Current local MySQL datetime.
 *
 * @return string
 */
function cmp_current_datetime() {
	return current_time( 'mysql' );
}

/**
 * Allowed batch statuses.
 *
 * @return array
 */
function cmp_batch_statuses() {
	return array( 'active', 'completed' );
}

/**
 * Allowed student statuses.
 *
 * @return array
 */
function cmp_student_statuses() {
	return array( 'active', 'completed', 'dropped' );
}

/**
 * Allowed payment modes.
 *
 * @return array
 */
function cmp_payment_modes() {
	return array( 'razorpay', 'upi', 'cash', 'manual' );
}

/**
 * Sanitizes an enum value against an allow-list.
 *
 * @param string $value Raw value.
 * @param array  $allowed Allowed values.
 * @param string $default Default value.
 * @return string
 */
function cmp_clean_enum( $value, $allowed, $default ) {
	$value = sanitize_key( $value );

	return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * Generates a unique public token for a batch.
 *
 * @return string
 */
function cmp_generate_batch_public_token() {
	global $wpdb;

	do {
		$token  = wp_generate_password( 24, false, false );
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) . ' WHERE public_token = %s',
				$token
			)
		);
	} while ( $exists );

	return $token;
}

/**
 * Fetches all classes.
 *
 * @return array
 */
function cmp_get_classes() {
	global $wpdb;

	return $wpdb->get_results( "SELECT * FROM " . cmp_table( 'classes' ) . ' ORDER BY name ASC' );
}

/**
 * Fetches a single class.
 *
 * @param int $id Class ID.
 * @return object|null
 */
function cmp_get_class( $id ) {
	global $wpdb;

	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . cmp_table( 'classes' ) . ' WHERE id = %d', absint( $id ) ) );
}

/**
 * Creates a class.
 *
 * @param array $data Class data.
 * @return int|WP_Error
 */
function cmp_insert_class( $data ) {
	global $wpdb;

	$name = sanitize_text_field( cmp_field( $data, 'name' ) );

	if ( '' === $name ) {
		return new WP_Error( 'cmp_class_name_required', __( 'Class name is required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->insert(
		cmp_table( 'classes' ),
		array(
			'name'        => $name,
			'description' => sanitize_textarea_field( cmp_field( $data, 'description' ) ),
			'total_fee'   => cmp_money_value( cmp_field( $data, 'total_fee', 0 ) ),
			'created_at'  => cmp_current_datetime(),
		),
		array( '%s', '%s', '%f', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not save class.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Updates a class.
 *
 * @param int   $id Class ID.
 * @param array $data Class data.
 * @return true|WP_Error
 */
function cmp_update_class( $id, $data ) {
	global $wpdb;

	$id   = absint( $id );
	$name = sanitize_text_field( cmp_field( $data, 'name' ) );

	if ( ! $id || '' === $name ) {
		return new WP_Error( 'cmp_invalid_class', __( 'Valid class details are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->update(
		cmp_table( 'classes' ),
		array(
			'name'        => $name,
			'description' => sanitize_textarea_field( cmp_field( $data, 'description' ) ),
			'total_fee'   => cmp_money_value( cmp_field( $data, 'total_fee', 0 ) ),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%f' ),
		array( '%d' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not update class.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Deletes a class when it is not referenced.
 *
 * @param int $id Class ID.
 * @return true|WP_Error
 */
function cmp_delete_class( $id ) {
	global $wpdb;

	$id = absint( $id );

	if ( ! $id ) {
		return new WP_Error( 'cmp_invalid_class', __( 'Invalid class.', 'class-manager-pro' ) );
	}

	$batch_count  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) . ' WHERE class_id = %d', $id ) );
	$student_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE class_id = %d', $id ) );

	if ( $batch_count || $student_count ) {
		return new WP_Error( 'cmp_class_in_use', __( 'This class has batches or students and cannot be deleted.', 'class-manager-pro' ) );
	}

	$wpdb->delete( cmp_table( 'classes' ), array( 'id' => $id ), array( '%d' ) );

	return true;
}

/**
 * Fetches all batches, optionally by class.
 *
 * @param int $class_id Optional class ID.
 * @return array
 */
function cmp_get_batches( $class_id = 0 ) {
	global $wpdb;

	$sql    = 'SELECT b.*, c.name AS class_name FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id';
	$params = array();

	if ( $class_id ) {
		$sql     .= ' WHERE b.class_id = %d';
		$params[] = absint( $class_id );
	}

	$sql .= ' ORDER BY b.created_at DESC';

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Fetches a single batch.
 *
 * @param int $id Batch ID.
 * @return object|null
 */
function cmp_get_batch( $id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT b.*, c.name AS class_name FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id WHERE b.id = %d',
			absint( $id )
		)
	);
}

/**
 * Fetches a batch by public token.
 *
 * @param string $token Public batch token.
 * @return object|null
 */
function cmp_get_batch_by_token( $token ) {
	global $wpdb;

	$token = sanitize_text_field( $token );

	if ( '' === $token ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id WHERE b.public_token = %s',
			$token
		)
	);
}

/**
 * Fetches a batch by its saved Razorpay page link URL.
 *
 * @param string $url Razorpay link URL.
 * @return object|null
 */
function cmp_get_batch_by_razorpay_link( $url ) {
	global $wpdb;

	$url = esc_url_raw( trim( (string) $url ) );

	if ( '' === $url ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id WHERE b.razorpay_link = %s LIMIT 1',
			$url
		)
	);
}

/**
 * Builds the public student form URL for a batch.
 *
 * @param object|int $batch Batch row or ID.
 * @return string
 */
function cmp_get_batch_public_form_url( $batch ) {
	$batch = is_object( $batch ) ? $batch : cmp_get_batch( (int) $batch );

	if ( ! $batch || empty( $batch->public_token ) ) {
		return '';
	}

	return add_query_arg(
		array(
			'cmp_batch_form' => $batch->public_token,
		),
		home_url( '/' )
	);
}

/**
 * Batch summary metrics for list and detail views.
 *
 * @return array
 */
function cmp_get_batch_overview_metrics() {
	global $wpdb;

	return array(
		'total_batches'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) ),
		'active_batches' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) . ' WHERE status = %s', 'active' ) ),
		'total_students' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) ),
		'total_revenue'  => (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) ),
	);
}

/**
 * Returns batches with linked student and revenue metrics.
 *
 * @return array
 */
function cmp_get_batches_with_metrics() {
	global $wpdb;

	return $wpdb->get_results(
		'SELECT
			b.*,
			c.name AS class_name,
			COALESCE(st.student_count, 0) AS student_count,
			COALESCE(st.pending_fee, 0) AS pending_fee,
			COALESCE(pay.revenue, 0) AS revenue
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		LEFT JOIN (
			SELECT batch_id, COUNT(*) AS student_count, SUM(GREATEST(total_fee - paid_fee, 0)) AS pending_fee
			FROM ' . cmp_table( 'students' ) . '
			GROUP BY batch_id
		) st ON st.batch_id = b.id
		LEFT JOIN (
			SELECT s.batch_id, SUM(p.amount) AS revenue
			FROM ' . cmp_table( 'payments' ) . ' p
			INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			GROUP BY s.batch_id
		) pay ON pay.batch_id = b.id
		ORDER BY b.created_at DESC, b.id DESC'
	);
}

/**
 * Returns batch-specific metrics.
 *
 * @param int $batch_id Batch ID.
 * @return array
 */
function cmp_get_batch_metrics( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );

	return array(
		'student_count' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d', $batch_id ) ),
		'revenue'       => (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(p.amount), 0)
				FROM ' . cmp_table( 'payments' ) . ' p
				INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
				WHERE s.batch_id = %d',
				$batch_id
			)
		),
		'pending_fee'   => (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(GREATEST(total_fee - paid_fee, 0)), 0) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d', $batch_id ) ),
	);
}

/**
 * Creates a batch.
 *
 * @param array $data Batch data.
 * @return int|WP_Error
 */
function cmp_insert_batch( $data ) {
	global $wpdb;

	$class_id   = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_name = sanitize_text_field( cmp_field( $data, 'batch_name' ) );
	$start_date = sanitize_text_field( cmp_field( $data, 'start_date' ) );
	$start_date = '' === $start_date ? null : $start_date;
	$razorpay_link = esc_url_raw( cmp_field( $data, 'razorpay_link' ) );

	if ( ! $class_id || ! cmp_get_class( $class_id ) || '' === $batch_name ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Valid class and batch name are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->insert(
		cmp_table( 'batches' ),
		array(
			'class_id'   => $class_id,
			'batch_name' => $batch_name,
			'start_date' => $start_date,
			'status'     => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_batch_statuses(), 'active' ),
			'public_token' => cmp_generate_batch_public_token(),
			'razorpay_link' => $razorpay_link,
			'created_at' => cmp_current_datetime(),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not save batch.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Updates a batch.
 *
 * @param int   $id Batch ID.
 * @param array $data Batch data.
 * @return true|WP_Error
 */
function cmp_update_batch( $id, $data ) {
	global $wpdb;

	$id         = absint( $id );
	$class_id   = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_name = sanitize_text_field( cmp_field( $data, 'batch_name' ) );
	$start_date = sanitize_text_field( cmp_field( $data, 'start_date' ) );
	$start_date = '' === $start_date ? null : $start_date;
	$batch      = cmp_get_batch( $id );
	$token      = $batch && ! empty( $batch->public_token ) ? $batch->public_token : cmp_generate_batch_public_token();
	$razorpay_link = esc_url_raw( cmp_field( $data, 'razorpay_link' ) );

	if ( ! $id || ! $class_id || ! cmp_get_class( $class_id ) || '' === $batch_name ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Valid batch details are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->update(
		cmp_table( 'batches' ),
		array(
			'class_id'   => $class_id,
			'batch_name' => $batch_name,
			'start_date' => $start_date,
			'status'     => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_batch_statuses(), 'active' ),
			'public_token' => $token,
			'razorpay_link' => $razorpay_link,
		),
		array( 'id' => $id ),
		array( '%d', '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not update batch.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Deletes a batch when it is not referenced.
 *
 * @param int $id Batch ID.
 * @return true|WP_Error
 */
function cmp_delete_batch( $id ) {
	global $wpdb;

	$id = absint( $id );

	if ( ! $id ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Invalid batch.', 'class-manager-pro' ) );
	}

	$student_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d', $id ) );

	if ( $student_count ) {
		return new WP_Error( 'cmp_batch_in_use', __( 'This batch has students and cannot be deleted.', 'class-manager-pro' ) );
	}

	$wpdb->delete( cmp_table( 'batches' ), array( 'id' => $id ), array( '%d' ) );

	return true;
}

/**
 * Generates a unique student ID like STU001.
 *
 * @return string
 */
function cmp_generate_student_unique_id() {
	global $wpdb;

	$students = cmp_table( 'students' );
	$next_id  = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$students}" ) + 1;

	do {
		$unique_id = 'STU' . str_pad( (string) $next_id, 3, '0', STR_PAD_LEFT );
		$exists    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$students} WHERE unique_id = %s", $unique_id ) );
		++$next_id;
	} while ( $exists );

	return $unique_id;
}

/**
 * Finds a student by phone or email.
 *
 * @param string $phone Phone.
 * @param string $email Email.
 * @return object|null
 */
function cmp_find_student_by_contact( $phone = '', $email = '' ) {
	global $wpdb;

	$where  = array();
	$params = array();

	if ( '' !== $phone ) {
		$where[]  = 'phone = %s';
		$params[] = sanitize_text_field( $phone );
	}

	if ( '' !== $email ) {
		$where[]  = 'email = %s';
		$params[] = sanitize_email( $email );
	}

	if ( empty( $where ) ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_table( 'students' ) . ' WHERE ' . implode( ' OR ', $where ) . ' ORDER BY id DESC LIMIT 1',
			$params
		)
	);
}

/**
 * Finds a student by phone or email within a specific batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @return object|null
 */
function cmp_find_student_in_batch_by_contact( $batch_id, $phone = '', $email = '' ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$where    = array();
	$params   = array( $batch_id );

	if ( '' !== $phone ) {
		$where[]  = 'phone = %s';
		$params[] = sanitize_text_field( $phone );
	}

	if ( '' !== $email ) {
		$where[]  = 'email = %s';
		$params[] = sanitize_email( $email );
	}

	if ( ! $batch_id || empty( $where ) ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d AND (' . implode( ' OR ', $where ) . ') ORDER BY id DESC LIMIT 1',
			$params
		)
	);
}

/**
 * Appends a line of note text to existing notes.
 *
 * @param string $existing Existing notes.
 * @param string $line Line to append.
 * @return string
 */
function cmp_append_note( $existing, $line ) {
	$existing = trim( (string) $existing );
	$line     = trim( (string) $line );

	if ( '' === $line ) {
		return $existing;
	}

	return '' === $existing ? $line : $existing . "\n" . $line;
}

/**
 * Stores a short-lived intake map for webhook matching after public submissions.
 *
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @param int    $student_id Student ID.
 * @param int    $batch_id Batch ID.
 */
function cmp_store_recent_intake_match( $phone, $email, $student_id, $batch_id ) {
	$data = array(
		'student_id' => (int) $student_id,
		'batch_id'   => (int) $batch_id,
	);

	$phone = preg_replace( '/\D+/', '', (string) $phone );
	$email = strtolower( trim( (string) $email ) );

	if ( '' !== $phone ) {
		set_transient( 'cmp_intake_phone_' . md5( $phone ), $data, DAY_IN_SECONDS * 2 );
	}

	if ( '' !== $email ) {
		set_transient( 'cmp_intake_email_' . md5( $email ), $data, DAY_IN_SECONDS * 2 );
	}
}

/**
 * Returns the most recent temporary intake mapping for a contact.
 *
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @return array|null
 */
function cmp_get_recent_intake_match( $phone, $email ) {
	$phone = preg_replace( '/\D+/', '', (string) $phone );
	$email = strtolower( trim( (string) $email ) );

	if ( '' !== $phone ) {
		$data = get_transient( 'cmp_intake_phone_' . md5( $phone ) );

		if ( is_array( $data ) ) {
			return $data;
		}
	}

	if ( '' !== $email ) {
		$data = get_transient( 'cmp_intake_email_' . md5( $email ) );

		if ( is_array( $data ) ) {
			return $data;
		}
	}

	return null;
}

/**
 * Creates or updates a student inside a batch from the public intake form.
 *
 * @param object $batch Batch row.
 * @param array  $data Form data.
 * @return int|WP_Error
 */
function cmp_register_public_student_for_batch( $batch, $data ) {
	$class = cmp_get_class( (int) $batch->class_id );
	$name  = sanitize_text_field( cmp_field( $data, 'name' ) );
	$phone = sanitize_text_field( cmp_field( $data, 'phone' ) );
	$email = sanitize_email( cmp_field( $data, 'email' ) );
	$notes = sanitize_textarea_field( cmp_field( $data, 'notes' ) );

	if ( '' === $name || '' === $phone || ! $class ) {
		return new WP_Error( 'cmp_public_student_invalid', __( 'Name and phone are required.', 'class-manager-pro' ) );
	}

	$notes = cmp_append_note( $notes, __( 'Submitted through batch student form.', 'class-manager-pro' ) );

	$existing = cmp_find_student_in_batch_by_contact( (int) $batch->id, $phone, $email );

	if ( $existing ) {
		$result = cmp_update_student(
			(int) $existing->id,
			array(
				'name'      => $name,
				'phone'     => $phone,
				'email'     => $email,
				'class_id'  => (int) $batch->class_id,
				'batch_id'  => (int) $batch->id,
				'total_fee' => (float) $existing->total_fee ? (float) $existing->total_fee : (float) $class->total_fee,
				'paid_fee'  => (float) $existing->paid_fee,
				'status'    => in_array( $existing->status, cmp_student_statuses(), true ) ? $existing->status : 'active',
				'notes'     => cmp_append_note( $existing->notes, $notes ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		cmp_store_recent_intake_match( $phone, $email, (int) $existing->id, (int) $batch->id );

		return (int) $existing->id;
	}

	$student_id = cmp_insert_student(
		array(
			'name'      => $name,
			'phone'     => $phone,
			'email'     => $email,
			'class_id'  => (int) $batch->class_id,
			'batch_id'  => (int) $batch->id,
			'total_fee' => (float) $class->total_fee,
			'paid_fee'  => 0,
			'status'    => 'active',
			'notes'     => $notes,
		)
	);

	if ( ! is_wp_error( $student_id ) ) {
		cmp_store_recent_intake_match( $phone, $email, (int) $student_id, (int) $batch->id );
	}

	return $student_id;
}

/**
 * Fetches a single student.
 *
 * @param int $id Student ID.
 * @return object|null
 */
function cmp_get_student( $id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT s.*, c.name AS class_name, b.batch_name FROM ' . cmp_table( 'students' ) . ' s LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id WHERE s.id = %d',
			absint( $id )
		)
	);
}

/**
 * Builds and runs the student list query.
 *
 * @param array $args Filters.
 * @return array
 */
function cmp_get_students( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'   => '',
			'class_id' => 0,
			'batch_id' => 0,
			'status'   => '',
			'limit'    => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['search'] ) {
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]  = '(s.name LIKE %s OR s.phone LIKE %s OR s.email LIKE %s OR s.unique_id LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ( $args['class_id'] ) {
		$where[]  = 's.class_id = %d';
		$params[] = absint( $args['class_id'] );
	}

	if ( $args['batch_id'] ) {
		$where[]  = 's.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( '' !== $args['status'] ) {
		$where[]  = 's.status = %s';
		$params[] = cmp_clean_enum( $args['status'], cmp_student_statuses(), 'active' );
	}

	$sql = 'SELECT s.*, c.name AS class_name, b.batch_name, (s.total_fee - s.paid_fee) AS remaining_fee
		FROM ' . cmp_table( 'students' ) . ' s
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id';

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY s.created_at DESC, s.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Creates a student and records an optional initial payment.
 *
 * @param array $data Student data.
 * @return int|WP_Error
 */
function cmp_insert_student( $data ) {
	global $wpdb;

	$name     = sanitize_text_field( cmp_field( $data, 'name' ) );
	$phone    = sanitize_text_field( cmp_field( $data, 'phone' ) );
	$email    = sanitize_email( cmp_field( $data, 'email' ) );
	$class_id = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_id = absint( cmp_field( $data, 'batch_id', 0 ) );
	$batch    = cmp_get_batch( $batch_id );

	if ( '' === $name || '' === $phone || ! cmp_get_class( $class_id ) || ! $batch || (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Valid student, class, and batch details are required.', 'class-manager-pro' ) );
	}

	$class         = cmp_get_class( $class_id );
	$total_fee_raw = cmp_field( $data, 'total_fee', '' );
	$total_fee     = '' !== (string) $total_fee_raw ? cmp_money_value( $total_fee_raw ) : (float) $class->total_fee;
	$initial_paid  = cmp_money_value( cmp_field( $data, 'paid_fee', 0 ) );
	$student_status = cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_student_statuses(), 'active' );

	$result = $wpdb->insert(
		cmp_table( 'students' ),
		array(
			'unique_id'  => cmp_generate_student_unique_id(),
			'name'       => $name,
			'phone'      => $phone,
			'email'      => $email,
			'class_id'   => $class_id,
			'batch_id'   => $batch_id,
			'total_fee'  => $total_fee,
			'paid_fee'   => 0,
			'status'     => $student_status,
			'notes'      => sanitize_textarea_field( cmp_field( $data, 'notes' ) ),
			'created_at' => cmp_current_datetime(),
		),
		array( '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not save student.', 'class-manager-pro' ) );
	}

	$student_id = (int) $wpdb->insert_id;

	if ( $initial_paid > 0 ) {
		cmp_insert_payment(
			array(
				'student_id'     => $student_id,
				'amount'         => $initial_paid,
				'payment_mode'   => 'manual',
				'transaction_id' => '',
				'payment_date'   => cmp_current_datetime(),
			)
		);
	}

	return $student_id;
}

/**
 * Updates a student profile. Payment history is not rewritten here.
 *
 * @param int   $id Student ID.
 * @param array $data Student data.
 * @return true|WP_Error
 */
function cmp_update_student( $id, $data ) {
	global $wpdb;

	$id       = absint( $id );
	$name     = sanitize_text_field( cmp_field( $data, 'name' ) );
	$phone    = sanitize_text_field( cmp_field( $data, 'phone' ) );
	$email    = sanitize_email( cmp_field( $data, 'email' ) );
	$class_id = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_id = absint( cmp_field( $data, 'batch_id', 0 ) );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $id || '' === $name || '' === $phone || ! cmp_get_class( $class_id ) || ! $batch || (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Valid student details are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->update(
		cmp_table( 'students' ),
		array(
			'name'      => $name,
			'phone'     => $phone,
			'email'     => $email,
			'class_id'  => $class_id,
			'batch_id'  => $batch_id,
			'total_fee' => cmp_money_value( cmp_field( $data, 'total_fee', 0 ) ),
			'paid_fee'  => cmp_money_value( cmp_field( $data, 'paid_fee', 0 ) ),
			'status'    => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_student_statuses(), 'active' ),
			'notes'     => sanitize_textarea_field( cmp_field( $data, 'notes' ) ),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s' ),
		array( '%d' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not update student.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Deletes a student and their payment records.
 *
 * @param int $id Student ID.
 * @return true|WP_Error
 */
function cmp_delete_student( $id ) {
	global $wpdb;

	$id = absint( $id );

	if ( ! $id ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Invalid student.', 'class-manager-pro' ) );
	}

	$wpdb->delete( cmp_table( 'payments' ), array( 'student_id' => $id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'students' ), array( 'id' => $id ), array( '%d' ) );

	return true;
}

/**
 * Checks whether a payment transaction already exists.
 *
 * @param string $transaction_id Transaction ID.
 * @return bool
 */
function cmp_payment_exists( $transaction_id ) {
	global $wpdb;

	if ( '' === $transaction_id ) {
		return false;
	}

	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'payments' ) . ' WHERE transaction_id = %s',
			sanitize_text_field( $transaction_id )
		)
	);
}

/**
 * Creates a payment and increments student paid_fee.
 *
 * @param array $data Payment data.
 * @return int|WP_Error
 */
function cmp_insert_payment( $data ) {
	global $wpdb;

	$student_id     = absint( cmp_field( $data, 'student_id', 0 ) );
	$amount         = cmp_money_value( cmp_field( $data, 'amount', 0 ) );
	$payment_mode   = cmp_clean_enum( cmp_field( $data, 'payment_mode', 'manual' ), cmp_payment_modes(), 'manual' );
	$transaction_id = sanitize_text_field( cmp_field( $data, 'transaction_id' ) );
	$raw_date       = cmp_field( $data, 'payment_date', '' );
	$payment_date   = '' !== $raw_date ? sanitize_text_field( $raw_date ) : cmp_current_datetime();
	$payment_date   = str_replace( 'T', ' ', $payment_date );

	if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $payment_date ) ) {
		$payment_date .= ':00';
	}

	if ( ! $student_id || ! cmp_get_student( $student_id ) || $amount <= 0 ) {
		return new WP_Error( 'cmp_invalid_payment', __( 'Valid student and payment amount are required.', 'class-manager-pro' ) );
	}

	if ( '' !== $transaction_id && cmp_payment_exists( $transaction_id ) ) {
		return new WP_Error( 'cmp_duplicate_payment', __( 'This transaction has already been recorded.', 'class-manager-pro' ) );
	}

	$result = $wpdb->insert(
		cmp_table( 'payments' ),
		array(
			'student_id'     => $student_id,
			'amount'         => $amount,
			'payment_mode'   => $payment_mode,
			'transaction_id' => $transaction_id,
			'payment_date'   => $payment_date,
			'created_at'     => cmp_current_datetime(),
		),
		array( '%d', '%f', '%s', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not save payment.', 'class-manager-pro' ) );
	}

	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . cmp_table( 'students' ) . ' SET paid_fee = paid_fee + %f WHERE id = %d',
			$amount,
			$student_id
		)
	);

	return (int) $wpdb->insert_id;
}

/**
 * Fetches payment rows.
 *
 * @param array $args Filters.
 * @return array
 */
function cmp_get_payments( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'payment_mode' => '',
			'student_id'   => 0,
			'batch_id'     => 0,
			'limit'        => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['payment_mode'] ) {
		$where[]  = 'p.payment_mode = %s';
		$params[] = cmp_clean_enum( $args['payment_mode'], cmp_payment_modes(), 'manual' );
	}

	if ( $args['student_id'] ) {
		$where[]  = 'p.student_id = %d';
		$params[] = absint( $args['student_id'] );
	}

	if ( $args['batch_id'] ) {
		$where[]  = 's.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	$sql = 'SELECT p.*, s.name AS student_name, s.phone AS student_phone, s.unique_id AS student_unique_id
		FROM ' . cmp_table( 'payments' ) . ' p
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id';

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY p.payment_date DESC, p.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Returns dashboard metric values.
 *
 * @return array
 */
function cmp_get_dashboard_metrics() {
	global $wpdb;

	return array(
		'total_classes'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'classes' ) ),
		'total_batches'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) ),
		'total_students' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) ),
		'total_revenue'  => (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) ),
		'pending_fees'   => (float) $wpdb->get_var( 'SELECT COALESCE(SUM(GREATEST(total_fee - paid_fee, 0)), 0) FROM ' . cmp_table( 'students' ) ),
	);
}

/**
 * Student list overview metrics.
 *
 * @return array
 */
function cmp_get_student_overview_metrics() {
	global $wpdb;

	return array(
		'total_students'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) ),
		'active_students'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE status = %s', 'active' ) ),
		'completed_students'=> (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE status = %s', 'completed' ) ),
		'pending_fee'       => (float) $wpdb->get_var( 'SELECT COALESCE(SUM(GREATEST(total_fee - paid_fee, 0)), 0) FROM ' . cmp_table( 'students' ) ),
	);
}

/**
 * Monthly revenue series.
 *
 * @param int $months Number of months.
 * @return array
 */
function cmp_get_monthly_revenue( $months = 6 ) {
	global $wpdb;

	$months = max( 1, absint( $months ) );
	$now    = current_time( 'timestamp' );
	$start  = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months', $now ) );
	$rows   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE_FORMAT(payment_date, '%%Y-%%m') AS month_key, SUM(amount) AS total FROM " . cmp_table( 'payments' ) . ' WHERE payment_date >= %s GROUP BY month_key',
			$start
		),
		OBJECT_K
	);

	$labels = array();
	$values = array();

	for ( $i = $months - 1; $i >= 0; --$i ) {
		$timestamp = strtotime( '-' . $i . ' months', $now );
		$key       = gmdate( 'Y-m', $timestamp );
		$labels[]  = gmdate( 'M Y', $timestamp );
		$values[]  = isset( $rows[ $key ] ) ? round( (float) $rows[ $key ]->total, 2 ) : 0;
	}

	return array(
		'labels' => $labels,
		'values' => $values,
	);
}

/**
 * Student growth series.
 *
 * @param int $months Number of months.
 * @return array
 */
function cmp_get_student_growth( $months = 6 ) {
	global $wpdb;

	$months = max( 1, absint( $months ) );
	$now    = current_time( 'timestamp' );
	$start  = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months', $now ) );
	$rows   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month_key, COUNT(*) AS total FROM " . cmp_table( 'students' ) . ' WHERE created_at >= %s GROUP BY month_key',
			$start
		),
		OBJECT_K
	);

	$labels = array();
	$values = array();

	for ( $i = $months - 1; $i >= 0; --$i ) {
		$timestamp = strtotime( '-' . $i . ' months', $now );
		$key       = gmdate( 'Y-m', $timestamp );
		$labels[]  = gmdate( 'M Y', $timestamp );
		$values[]  = isset( $rows[ $key ] ) ? (int) $rows[ $key ]->total : 0;
	}

	return array(
		'labels' => $labels,
		'values' => $values,
	);
}

/**
 * Class-wise revenue.
 *
 * @return array
 */
function cmp_get_class_revenue() {
	global $wpdb;

	$rows = $wpdb->get_results(
		'SELECT COALESCE(c.name, \'Unassigned\') AS class_name, SUM(p.amount) AS total
		FROM ' . cmp_table( 'payments' ) . ' p
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
		GROUP BY c.id, c.name
		ORDER BY total DESC'
	);

	$labels = array();
	$values = array();

	foreach ( $rows as $row ) {
		$labels[] = $row->class_name;
		$values[] = round( (float) $row->total, 2 );
	}

	return array(
		'labels' => $labels,
		'values' => $values,
	);
}

/**
 * Student status counts.
 *
 * @return array
 */
function cmp_get_student_status_counts() {
	global $wpdb;

	$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . cmp_table( 'students' ) . ' GROUP BY status', OBJECT_K );

	$labels = array( 'active', 'completed', 'dropped' );
	$values = array();

	foreach ( $labels as $label ) {
		$values[] = isset( $rows[ $label ] ) ? (int) $rows[ $label ]->total : 0;
	}

	return array(
		'labels' => array_map( 'ucfirst', $labels ),
		'values' => $values,
	);
}

/**
 * Reads common student filters from a request-like array.
 *
 * @param array|null $source Source data.
 * @return array
 */
function cmp_read_student_filters( $source = null ) {
	$source = null === $source ? $_REQUEST : $source;

	return array(
		'search'   => sanitize_text_field( cmp_field( $source, 'search' ) ),
		'class_id' => absint( cmp_field( $source, 'class_id', 0 ) ),
		'batch_id' => absint( cmp_field( $source, 'batch_id', 0 ) ),
		'status'   => sanitize_key( cmp_field( $source, 'status' ) ),
	);
}

/**
 * Renders all-data table rows.
 *
 * @param array $filters Filters.
 * @return string
 */
function cmp_render_all_data_rows( $filters = array() ) {
	$students = cmp_get_students( $filters );

	ob_start();

	if ( empty( $students ) ) {
		echo '<tr><td colspan="7">' . esc_html__( 'No records found.', 'class-manager-pro' ) . '</td></tr>';
		return ob_get_clean();
	}

	foreach ( $students as $student ) {
		$remaining = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
		echo '<tr>';
		echo '<td>' . esc_html( $student->name ) . '<br><span class="cmp-muted">' . esc_html( $student->unique_id ) . '</span></td>';
		echo '<td>' . esc_html( $student->phone ) . '</td>';
		echo '<td>' . esc_html( $student->class_name ) . '</td>';
		echo '<td>' . esc_html( $student->batch_name ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->total_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->paid_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $remaining ) ) . '</td>';
		echo '</tr>';
	}

	return ob_get_clean();
}

/**
 * Renders student table rows.
 *
 * @param array $filters Filters.
 * @return string
 */
function cmp_render_student_rows( $filters = array() ) {
	$students = cmp_get_students( $filters );

	ob_start();

	if ( empty( $students ) ) {
		echo '<tr><td colspan="10">' . esc_html__( 'No students found.', 'class-manager-pro' ) . '</td></tr>';
		return ob_get_clean();
	}

	foreach ( $students as $student ) {
		$remaining   = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
		$view_url    = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
		$edit_url    = cmp_admin_url( 'cmp-students', array( 'action' => 'edit', 'id' => (int) $student->id ) );
		$payment_url = cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id ) ) . '#cmp-add-payment';
		$delete_url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=cmp_delete_student&id=' . (int) $student->id ),
			'cmp_delete_student_' . (int) $student->id
		);

		echo '<tr>';
		echo '<td>' . esc_html( $student->name ) . '<br><span class="cmp-muted">' . esc_html( $student->unique_id ) . '</span></td>';
		echo '<td>' . esc_html( $student->phone ) . '</td>';
		echo '<td>' . esc_html( $student->email ) . '</td>';
		echo '<td>' . esc_html( $student->class_name ) . '</td>';
		echo '<td>' . esc_html( $student->batch_name ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->total_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->paid_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $remaining ) ) . '</td>';
		echo '<td><span class="cmp-status cmp-status-' . esc_attr( $student->status ) . '">' . esc_html( ucfirst( $student->status ) ) . '</span></td>';
		echo '<td class="cmp-actions">';
		echo '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'class-manager-pro' ) . '</a> ';
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'class-manager-pro' ) . '</a> ';
		echo '<a href="' . esc_url( $payment_url ) . '">' . esc_html__( 'Payment', 'class-manager-pro' ) . '</a> ';
		echo '<a class="cmp-delete-link" href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'class-manager-pro' ) . '</a>';
		echo '</td>';
		echo '</tr>';
	}

	return ob_get_clean();
}

/**
 * AJAX handler for All Data filtering.
 */
function cmp_ajax_filter_all_data() {
	cmp_require_manage_options();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	wp_send_json_success(
		array(
			'html' => cmp_render_all_data_rows( cmp_read_student_filters() ),
		)
	);
}

/**
 * AJAX handler for Students filtering.
 */
function cmp_ajax_filter_students() {
	cmp_require_manage_options();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	wp_send_json_success(
		array(
			'html' => cmp_render_student_rows( cmp_read_student_filters() ),
		)
	);
}

/**
 * Exports students/all-data/payments as CSV.
 */
function cmp_handle_csv_export() {
	if ( ! isset( $_GET['cmp_export'] ) ) {
		return;
	}

	cmp_require_manage_options();

	$type = sanitize_key( cmp_field( $_GET, 'cmp_export' ) );

	if ( ! in_array( $type, array( 'all-data', 'students', 'payments' ), true ) ) {
		return;
	}

	check_admin_referer( 'cmp_export_' . $type );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=class-manager-pro-' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );

	if ( 'payments' === $type ) {
		fputcsv( $output, array( 'Student Name', 'Student ID', 'Phone', 'Amount', 'Payment Mode', 'Transaction ID', 'Date' ) );
		$payments = cmp_get_payments(
			array(
				'payment_mode' => sanitize_key( cmp_field( $_GET, 'payment_mode' ) ),
			)
		);

		foreach ( $payments as $payment ) {
			fputcsv(
				$output,
				array(
					$payment->student_name,
					$payment->student_unique_id,
					$payment->student_phone,
					$payment->amount,
					$payment->payment_mode,
					$payment->transaction_id,
					$payment->payment_date,
				)
			);
		}
	} else {
		fputcsv( $output, array( 'Student ID', 'Student Name', 'Phone', 'Email', 'Class', 'Batch', 'Total Fee', 'Paid Fee', 'Remaining Fee', 'Status' ) );
		$students = cmp_get_students( cmp_read_student_filters( $_GET ) );

		foreach ( $students as $student ) {
			fputcsv(
				$output,
				array(
					$student->unique_id,
					$student->name,
					$student->phone,
					$student->email,
					$student->class_name,
					$student->batch_name,
					$student->total_fee,
					$student->paid_fee,
					max( 0, (float) $student->total_fee - (float) $student->paid_fee ),
					$student->status,
				)
			);
		}
	}

	fclose( $output );
	exit;
}

/**
 * Handles class form submission.
 */
function cmp_handle_save_class() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_class' );

	$id     = absint( cmp_field( $_POST, 'id', 0 ) );
	$result = $id ? cmp_update_class( $id, $_POST ) : cmp_insert_class( $_POST );
	$page   = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-classes' ), 'cmp-classes' );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $page, $result->get_error_message(), 'error', $id && 'cmp-classes' === $page ? array( 'action' => 'edit', 'id' => $id ) : array() );
	}

	cmp_redirect( $page, __( 'Class saved successfully.', 'class-manager-pro' ) );
}

/**
 * Handles class deletion.
 */
function cmp_handle_delete_class() {
	cmp_require_manage_options();

	$id = absint( cmp_field( $_GET, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_class_' . $id );

	$result = cmp_delete_class( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-classes', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-classes', __( 'Class deleted successfully.', 'class-manager-pro' ) );
}

/**
 * Handles batch form submission.
 */
function cmp_handle_save_batch() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_batch' );

	$id     = absint( cmp_field( $_POST, 'id', 0 ) );
	$result = $id ? cmp_update_batch( $id, $_POST ) : cmp_insert_batch( $_POST );
	$page   = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-batches' ), 'cmp-batches' );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $page, $result->get_error_message(), 'error', $id && 'cmp-batches' === $page ? array( 'action' => 'edit', 'id' => $id ) : array() );
	}

	$batch_id = $id ? $id : (int) $result;
	$args     = 'cmp-batches' === $page ? array( 'action' => 'view', 'id' => $batch_id ) : array();

	cmp_redirect( $page, __( 'Batch saved successfully.', 'class-manager-pro' ), 'success', $args );
}

/**
 * Handles batch deletion.
 */
function cmp_handle_delete_batch() {
	cmp_require_manage_options();

	$id = absint( cmp_field( $_GET, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_batch_' . $id );

	$result = cmp_delete_batch( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-batches', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-batches', __( 'Batch deleted successfully.', 'class-manager-pro' ) );
}

/**
 * Handles student form submission.
 */
function cmp_handle_save_student() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_student' );

	$id     = absint( cmp_field( $_POST, 'id', 0 ) );
	$result = $id ? cmp_update_student( $id, $_POST ) : cmp_insert_student( $_POST );

	if ( is_wp_error( $result ) ) {
		$page = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-students' ), 'cmp-students' );
		cmp_redirect( $page, $result->get_error_message(), 'error', $id ? array( 'action' => 'edit', 'id' => $id ) : array() );
	}

	$page = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-students' ), 'cmp-students' );
	cmp_redirect( $page, __( 'Student saved successfully.', 'class-manager-pro' ) );
}

/**
 * Handles student deletion.
 */
function cmp_handle_delete_student() {
	cmp_require_manage_options();

	$id = absint( cmp_field( $_GET, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_student_' . $id );

	$result = cmp_delete_student( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-students', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-students', __( 'Student deleted successfully.', 'class-manager-pro' ) );
}

/**
 * Handles payment form submission.
 */
function cmp_handle_save_payment() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_payment' );

	$result = cmp_insert_payment( $_POST );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-payments', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-payments', __( 'Payment saved successfully.', 'class-manager-pro' ) );
}

/**
 * Handles settings form submission.
 */
function cmp_handle_save_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_settings' );

	update_option( 'cmp_razorpay_key_id', sanitize_text_field( cmp_field( $_POST, 'razorpay_key_id' ) ) );
	update_option( 'cmp_razorpay_secret', sanitize_text_field( cmp_field( $_POST, 'razorpay_secret' ) ) );
	update_option( 'cmp_razorpay_webhook_secret', sanitize_text_field( cmp_field( $_POST, 'razorpay_webhook_secret' ) ) );

	cmp_redirect( 'cmp-settings', __( 'Settings saved successfully.', 'class-manager-pro' ) );
}
