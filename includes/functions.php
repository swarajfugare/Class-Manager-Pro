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
add_action( 'admin_post_cmp_import_detected_razorpay_keys', 'cmp_handle_import_detected_razorpay_keys' );
add_action( 'admin_post_cmp_import_razorpay_data', 'cmp_handle_import_razorpay_data' );
add_action( 'admin_post_cmp_manual_import_razorpay', 'cmp_handle_manual_import_razorpay' );
add_action( 'admin_post_cmp_import_razorpay_page_to_batch', 'cmp_handle_import_razorpay_page_to_batch' );
add_action( 'admin_post_cmp_reset_plugin_data', 'cmp_handle_reset_plugin_data' );
add_action( 'admin_post_cmp_save_sms_settings', 'cmp_handle_save_sms_settings' );
add_action( 'admin_post_cmp_send_fee_reminders', 'cmp_handle_send_fee_reminders' );
add_action( 'admin_post_cmp_save_attendance_settings', 'cmp_handle_save_attendance_settings' );
add_action( 'admin_post_cmp_save_attendance', 'cmp_handle_save_attendance' );
add_action( 'admin_init', 'cmp_handle_csv_export' );
add_action( 'init', 'cmp_schedule_daily_fee_reminders' );
add_action( 'cmp_daily_fee_reminders', 'cmp_run_scheduled_fee_reminders' );
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
 * Returns the first non-empty scalar from a list.
 *
 * @param array $values Candidate values.
 * @return string
 */
function cmp_first_scalar_value( $values ) {
	foreach ( $values as $value ) {
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			return trim( (string) $value );
		}
	}

	return '';
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
 * Allowed attendance statuses.
 *
 * @return array
 */
function cmp_attendance_statuses() {
	return array( 'present', 'absent', 'leave' );
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
 * Returns the configured or detected Razorpay credentials.
 *
 * @return array
 */
function cmp_get_razorpay_credentials() {
	$key_id = trim( (string) get_option( 'cmp_razorpay_key_id', '' ) );
	$secret = trim( (string) get_option( 'cmp_razorpay_secret', '' ) );

	if ( '' !== $key_id && '' !== $secret ) {
		return array(
			'key_id' => $key_id,
			'secret' => $secret,
			'source' => 'class-manager-pro',
		);
	}

	if ( function_exists( 'cmp_detect_wordpress_razorpay' ) ) {
		$detected = cmp_detect_wordpress_razorpay();

		if ( ! empty( $detected ) ) {
			$first = reset( $detected );

			if ( is_array( $first ) && ! empty( $first['key_id'] ) && ! empty( $first['secret'] ) ) {
				return array(
					'key_id' => (string) $first['key_id'],
					'secret' => (string) $first['secret'],
					'source' => (string) key( $detected ),
				);
			}
		}
	}

	return array(
		'key_id' => '',
		'secret' => '',
		'source' => '',
	);
}

/**
 * Returns the effective fee for a batch.
 *
 * @param object|int|null $batch Batch object or ID.
 * @return float
 */
function cmp_get_batch_effective_fee( $batch ) {
	if ( is_numeric( $batch ) ) {
		$batch = cmp_get_batch( (int) $batch );
	}

	if ( ! $batch || ! is_object( $batch ) ) {
		return 0;
	}

	if ( ! empty( $batch->is_free ) ) {
		return 0;
	}

	if ( isset( $batch->batch_fee ) && (float) $batch->batch_fee > 0 ) {
		return (float) $batch->batch_fee;
	}

	if ( isset( $batch->class_total_fee ) && (float) $batch->class_total_fee > 0 ) {
		return (float) $batch->class_total_fee;
	}

	$class = ! empty( $batch->class_id ) ? cmp_get_class( (int) $batch->class_id ) : null;

	return $class ? (float) $class->total_fee : 0;
}

/**
 * Normalizes an entity name for fuzzy import matching.
 *
 * @param string $text Raw text.
 * @return string
 */
function cmp_normalize_name_key( $text ) {
	$text = strtolower( sanitize_text_field( (string) $text ) );
	$text = preg_replace( '/[|:>]+/', ' ', $text );
	$text = preg_replace( '/\b(fee|fees|payment|payments|link|page|form|admission|registration)\b/', ' ', $text );
	$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
	$text = trim( preg_replace( '/\s+/', ' ', $text ) );

	return $text;
}

/**
 * Cleans a human-facing title.
 *
 * @param string $text Raw text.
 * @return string
 */
function cmp_clean_title_text( $text ) {
	$text = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $text ) ) );

	return '' === $text ? __( 'Imported Batch', 'class-manager-pro' ) : $text;
}

/**
 * Converts a normalized phrase to display case.
 *
 * @param string $text Text.
 * @return string
 */
function cmp_import_title_case( $text ) {
	$text = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $text ) ) );

	if ( '' === $text ) {
		return '';
	}

	return ucwords( strtolower( $text ) );
}

/**
 * Builds a stable class name from a Razorpay page title.
 *
 * @param string $title Razorpay page title.
 * @return string
 */
function cmp_guess_import_class_name( $title ) {
	$title = strtolower( cmp_clean_title_text( $title ) );
	$title = preg_replace( '/[|:>\/_-]+/', ' ', $title );
	$title = preg_replace( '/\b(fee|fees|payment|payments|link|page|form|admission|registration|razorpay)\b/', ' ', $title );
	$title = trim( preg_replace( '/\s+/', ' ', $title ) );

	if ( preg_match( '/^(.+?\bclass\b)(?:\s+batch\b.*|\s+\d+.*)?$/', $title, $matches ) ) {
		return cmp_import_title_case( $matches[1] );
	}

	$base = preg_replace( '/\bbatch\b.*$/', '', $title );
	$base = preg_replace( '/\b(group|slot|morning|evening|weekend|weekday)\b.*$/', '', $base );
	$base = preg_replace( '/\b\d+\b.*$/', '', $base );
	$base = trim( preg_replace( '/\s+/', ' ', $base ) );

	if ( '' === $base ) {
		$base = $title;
	}

	if ( ! preg_match( '/\bclass\b/', $base ) ) {
		$base .= ' class';
	}

	return cmp_import_title_case( $base );
}

/**
 * Builds a batch name from a Razorpay page title and class name.
 *
 * @param string $title Raw title.
 * @param string $class_name Class name.
 * @return string
 */
function cmp_guess_import_batch_name( $title, $class_name ) {
	$clean_title = cmp_clean_title_text( $title );
	$title_key   = cmp_normalize_name_key( $clean_title );
	$class_key   = cmp_normalize_name_key( $class_name );
	$class_base  = trim( preg_replace( '/\s+class$/', '', $class_key ) );

	if ( $title_key === $class_key || ( $class_base && $title_key === $class_base ) ) {
		return cmp_import_title_case( $clean_title );
	}

	if ( $class_key && 0 === strpos( $title_key, $class_key ) ) {
		$remainder = trim( substr( $title_key, strlen( $class_key ) ) );

		if ( '' !== $remainder ) {
			return cmp_import_title_case( 0 === strpos( $remainder, 'batch ' ) ? $remainder : 'Batch ' . $remainder );
		}
	}

	if ( $class_base && $class_base !== $class_key && 0 === strpos( $title_key, $class_base ) ) {
		$remainder = trim( substr( $title_key, strlen( $class_base ) ) );

		if ( '' !== $remainder ) {
			return cmp_import_title_case( 0 === strpos( $remainder, 'batch ' ) ? $remainder : 'Batch ' . $remainder );
		}
	}

	if ( preg_match( '/\bbatch\s*([a-z0-9 -]+)$/i', $clean_title, $matches ) ) {
		return cmp_import_title_case( 'Batch ' . trim( $matches[1] ) );
	}

	return cmp_import_title_case( $clean_title );
}

/**
 * Builds smart class and batch names from Razorpay naming.
 *
 * @param string $title Source title.
 * @param array  $notes Optional notes array.
 * @return array
 */
function cmp_guess_class_batch_names( $title, $notes = array() ) {
	$note_class = trim( sanitize_text_field( isset( $notes['class_name'] ) ? $notes['class_name'] : ( isset( $notes['course_name'] ) ? $notes['course_name'] : '' ) ) );
	$note_batch = trim( sanitize_text_field( isset( $notes['batch_name'] ) ? $notes['batch_name'] : ( isset( $notes['form_name'] ) ? $notes['form_name'] : '' ) ) );

	if ( '' !== $note_class || '' !== $note_batch ) {
		$class_name = cmp_clean_title_text( '' !== $note_class ? $note_class : cmp_guess_import_class_name( $note_batch ) );

		return array(
			'class_name' => $class_name,
			'batch_name' => cmp_clean_title_text( '' !== $note_batch ? $note_batch : cmp_guess_import_batch_name( $note_class, $class_name ) ),
		);
	}

	$title      = cmp_clean_title_text( $title );
	$class_name = cmp_guess_import_class_name( $title );

	return array(
		'class_name' => $class_name,
		'batch_name' => cmp_guess_import_batch_name( $title, $class_name ),
	);
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

	$sql    = 'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id';
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
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id WHERE b.id = %d',
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
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee FROM ' . cmp_table( 'batches' ) . ' b LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id WHERE b.public_token = %s',
			$token
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
 * Builds comparable tokens from a Razorpay URL or payment-link identifier.
 *
 * @param mixed $value Razorpay URL, short URL, or identifier.
 * @return array
 */
function cmp_razorpay_reference_candidates( $value ) {
	if ( ! is_scalar( $value ) ) {
		return array();
	}

	$raw = trim( (string) $value );

	if ( '' === $raw ) {
		return array();
	}

	$values     = array( $raw );
	$decoded    = html_entity_decode( $raw, ENT_QUOTES, get_bloginfo( 'charset' ) );
	$candidates = array();

	if ( $decoded !== $raw ) {
		$values[] = $decoded;
	}

	foreach ( $values as $candidate ) {
		$candidate = trim( (string) $candidate );

		if ( '' === $candidate ) {
			continue;
		}

		$candidates[] = $candidate;

		$without_query = preg_replace( '/[?#].*$/', '', $candidate );

		if ( '' !== $without_query ) {
			$candidates[] = untrailingslashit( $without_query );
		}

		$parts = wp_parse_url( $candidate );
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

		if ( is_array( $parts ) && ! empty( $parts['host'] ) && '' !== $path ) {
			$candidates[] = strtolower( $parts['host'] ) . '/' . $path;
		}

		if ( '' !== $path ) {
			$candidates[] = $path;
			$segments     = explode( '/', $path );
			$last_segment = end( $segments );

			if ( is_string( $last_segment ) && strlen( $last_segment ) >= 4 ) {
				$candidates[] = $last_segment;
			}
		}

		if ( preg_match_all( '/(?:plink|pay|order)_[A-Za-z0-9_]+/', $candidate, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$candidates[] = $match;
			}
		}
	}

	$candidates = array_map( 'strtolower', array_filter( array_map( 'trim', $candidates ) ) );

	return array_values( array_unique( $candidates ) );
}

/**
 * Finds a batch by a saved Razorpay payment page/link reference.
 *
 * @param array|string $references Razorpay references from webhook payload.
 * @return object|null
 */
function cmp_get_batch_by_razorpay_reference( $references ) {
	global $wpdb;

	$references = is_array( $references ) ? $references : array( $references );
	$needles    = array();

	foreach ( $references as $reference ) {
		$needles = array_merge( $needles, cmp_razorpay_reference_candidates( $reference ) );
	}

	$needles = array_values( array_unique( $needles ) );

	if ( empty( $needles ) ) {
		return null;
	}

	$batches = $wpdb->get_results(
		'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		WHERE ( b.razorpay_link IS NOT NULL AND b.razorpay_link <> "" ) OR ( b.razorpay_page_id IS NOT NULL AND b.razorpay_page_id <> "" )
		ORDER BY b.created_at DESC, b.id DESC'
	);

	foreach ( $batches as $batch ) {
		$haystack = array_merge(
			cmp_razorpay_reference_candidates( $batch->razorpay_link ),
			cmp_razorpay_reference_candidates( $batch->razorpay_page_id )
		);

		if ( array_intersect( $needles, $haystack ) ) {
			return $batch;
		}
	}

	return null;
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
			c.total_fee AS class_total_fee,
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

	$class_id         = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_name       = sanitize_text_field( cmp_field( $data, 'batch_name' ) );
	$start_date       = sanitize_text_field( cmp_field( $data, 'start_date' ) );
	$fee_due_date     = sanitize_text_field( cmp_field( $data, 'fee_due_date' ) );
	$start_date       = '' === $start_date ? null : $start_date;
	$fee_due_date     = '' === $fee_due_date ? null : $fee_due_date;
	$razorpay_link    = esc_url_raw( cmp_field( $data, 'razorpay_link' ) );
	$razorpay_page_id = sanitize_text_field( cmp_field( $data, 'razorpay_page_id' ) );
	$is_free          = ! empty( $data['is_free'] ) ? 1 : 0;
	$batch_fee        = $is_free ? 0 : cmp_money_value( cmp_field( $data, 'batch_fee', 0 ) );

	if ( ! $class_id || ! cmp_get_class( $class_id ) || '' === $batch_name ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Valid class and batch name are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->insert(
		cmp_table( 'batches' ),
		array(
			'class_id'         => $class_id,
			'batch_name'       => $batch_name,
			'start_date'       => $start_date,
			'fee_due_date'     => $fee_due_date,
			'status'           => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_batch_statuses(), 'active' ),
			'public_token'     => cmp_generate_batch_public_token(),
			'razorpay_link'    => $razorpay_link,
			'batch_fee'        => $batch_fee,
			'is_free'          => $is_free,
			'razorpay_page_id' => $razorpay_page_id,
			'created_at'       => cmp_current_datetime(),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s' )
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

	$id               = absint( $id );
	$class_id         = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_name       = sanitize_text_field( cmp_field( $data, 'batch_name' ) );
	$start_date       = sanitize_text_field( cmp_field( $data, 'start_date' ) );
	$fee_due_date     = sanitize_text_field( cmp_field( $data, 'fee_due_date' ) );
	$start_date       = '' === $start_date ? null : $start_date;
	$fee_due_date     = '' === $fee_due_date ? null : $fee_due_date;
	$batch            = cmp_get_batch( $id );
	$token            = $batch && ! empty( $batch->public_token ) ? $batch->public_token : cmp_generate_batch_public_token();
	$razorpay_link    = esc_url_raw( cmp_field( $data, 'razorpay_link' ) );
	$razorpay_page_id = sanitize_text_field( cmp_field( $data, 'razorpay_page_id' ) );
	$is_free          = ! empty( $data['is_free'] ) ? 1 : 0;
	$batch_fee        = $is_free ? 0 : cmp_money_value( cmp_field( $data, 'batch_fee', 0 ) );

	if ( ! $id || ! $class_id || ! cmp_get_class( $class_id ) || '' === $batch_name ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Valid batch details are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->update(
		cmp_table( 'batches' ),
		array(
			'class_id'         => $class_id,
			'batch_name'       => $batch_name,
			'start_date'       => $start_date,
			'fee_due_date'     => $fee_due_date,
			'status'           => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_batch_statuses(), 'active' ),
			'public_token'     => $token,
			'razorpay_link'    => $razorpay_link,
			'batch_fee'        => $batch_fee,
			'is_free'          => $is_free,
			'razorpay_page_id' => $razorpay_page_id,
		),
		array( 'id' => $id ),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s' ),
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
 * Returns normalized phone keys used for short-lived intake matching.
 *
 * @param string $phone Phone number.
 * @return array
 */
function cmp_phone_match_keys( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );

	if ( '' === $digits ) {
		return array();
	}

	$keys = array( $digits );

	if ( strlen( $digits ) > 10 ) {
		$keys[] = substr( $digits, -10 );
	}

	if ( 10 === strlen( $digits ) ) {
		$keys[] = '91' . $digits;
	}

	return array_values( array_unique( $keys ) );
}

/**
 * Returns common phone formats for database contact matching.
 *
 * @param string $phone Phone number.
 * @return array
 */
function cmp_phone_match_values( $phone ) {
	$raw    = sanitize_text_field( $phone );
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	$values = array();

	if ( '' !== $raw ) {
		$values[] = $raw;
	}

	if ( '' !== $digits ) {
		$values[] = $digits;

		if ( strlen( $digits ) > 10 ) {
			$values[] = substr( $digits, -10 );
		}

		if ( 10 === strlen( $digits ) ) {
			$values[] = '91' . $digits;
			$values[] = '+91' . $digits;
		}

		if ( 12 === strlen( $digits ) && 0 === strpos( $digits, '91' ) ) {
			$values[] = '+' . $digits;
		}
	}

	return array_values( array_unique( array_filter( $values ) ) );
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

	$phone_values = cmp_phone_match_values( $phone );

	if ( $phone_values ) {
		$where[] = 'phone IN (' . implode( ', ', array_fill( 0, count( $phone_values ), '%s' ) ) . ')';
		$params = array_merge( $params, $phone_values );
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

	$phone_values = cmp_phone_match_values( $phone );

	if ( $phone_values ) {
		$where[] = 'phone IN (' . implode( ', ', array_fill( 0, count( $phone_values ), '%s' ) ) . ')';
		$params = array_merge( $params, $phone_values );
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
 * Finds a student in a batch by phone, email, or exact normalized name.
 *
 * @param int    $batch_id Batch ID.
 * @param string $name Student name.
 * @param string $email Email.
 * @param string $phone Phone.
 * @return object|null
 */
function cmp_find_student_in_batch_by_identity( $batch_id, $name = '', $email = '', $phone = '' ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$where    = array();
	$params   = array( $batch_id );

	if ( '' !== trim( (string) $name ) ) {
		$where[]  = 'LOWER(name) = %s';
		$params[] = strtolower( sanitize_text_field( $name ) );
	}

	if ( '' !== trim( (string) $email ) ) {
		$where[]  = 'email = %s';
		$params[] = sanitize_email( $email );
	}

	$phone_values = cmp_phone_match_values( $phone );

	if ( $phone_values ) {
		$where[] = 'phone IN (' . implode( ', ', array_fill( 0, count( $phone_values ), '%s' ) ) . ')';
		$params = array_merge( $params, $phone_values );
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

	$email = strtolower( trim( (string) $email ) );

	foreach ( cmp_phone_match_keys( $phone ) as $phone_key ) {
		set_transient( 'cmp_intake_phone_' . md5( $phone_key ), $data, DAY_IN_SECONDS * 2 );
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
	$email = strtolower( trim( (string) $email ) );

	foreach ( cmp_phone_match_keys( $phone ) as $phone_key ) {
		$data = get_transient( 'cmp_intake_phone_' . md5( $phone_key ) );

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
	$fee   = cmp_get_batch_effective_fee( $batch );

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
				'total_fee' => (float) $existing->total_fee ? (float) $existing->total_fee : $fee,
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
			'total_fee' => $fee,
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
			'SELECT s.*, c.name AS class_name, b.batch_name, b.batch_fee, b.fee_due_date FROM ' . cmp_table( 'students' ) . ' s LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id WHERE s.id = %d',
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

	$sql = 'SELECT s.*, c.name AS class_name, b.batch_name, b.batch_fee, b.fee_due_date, (s.total_fee - s.paid_fee) AS remaining_fee
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

	$total_fee_raw = cmp_field( $data, 'total_fee', '' );
	$total_fee     = '' !== (string) $total_fee_raw ? cmp_money_value( $total_fee_raw ) : cmp_get_batch_effective_fee( $batch );
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

/**
 * Schedules the daily reminder job.
 */
function cmp_schedule_daily_fee_reminders() {
	if ( ! wp_next_scheduled( 'cmp_daily_fee_reminders' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cmp_daily_fee_reminders' );
	}
}

/**
 * Clears the daily reminder job.
 */
function cmp_clear_scheduled_fee_reminders() {
	$timestamp = wp_next_scheduled( 'cmp_daily_fee_reminders' );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'cmp_daily_fee_reminders' );
	}
}

/**
 * Finds a class by normalized name.
 *
 * @param string $name Class name.
 * @return object|null
 */
function cmp_find_class_by_name( $name ) {
	$needle  = cmp_normalize_name_key( $name );
	$classes = cmp_get_classes();

	foreach ( $classes as $class ) {
		if ( $needle === cmp_normalize_name_key( $class->name ) ) {
			return $class;
		}
	}

	return null;
}

/**
 * Finds a batch by Razorpay page ID.
 *
 * @param string $page_id Razorpay payment-link ID.
 * @return object|null
 */
function cmp_get_batch_by_razorpay_page_id( $page_id ) {
	global $wpdb;

	$page_id = sanitize_text_field( $page_id );

	if ( '' === $page_id ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee
			FROM ' . cmp_table( 'batches' ) . ' b
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			WHERE b.razorpay_page_id = %s
			LIMIT 1',
			$page_id
		)
	);
}

/**
 * Finds a batch by class and normalized batch name.
 *
 * @param int    $class_id Class ID.
 * @param string $batch_name Batch name.
 * @return object|null
 */
function cmp_find_batch_by_name( $class_id, $batch_name ) {
	$class_id = absint( $class_id );
	$needle   = cmp_normalize_name_key( $batch_name );
	$batches  = cmp_get_batches( $class_id );

	foreach ( $batches as $batch ) {
		if ( $needle === cmp_normalize_name_key( $batch->batch_name ) ) {
			return $batch;
		}
	}

	return null;
}

/**
 * Ensures an import class exists.
 *
 * @param string $name Class name.
 * @param string $description Optional description.
 * @return int|WP_Error
 */
function cmp_ensure_import_class( $name, $description = '' ) {
	$name     = cmp_clean_title_text( $name );
	$existing = cmp_find_class_by_name( $name );

	if ( $existing ) {
		return (int) $existing->id;
	}

	return cmp_insert_class(
		array(
			'name'        => $name,
			'description' => $description,
			'total_fee'   => 0,
		)
	);
}

/**
 * Ensures an import batch exists.
 *
 * @param int   $class_id Class ID.
 * @param array $data Batch data.
 * @return array|WP_Error
 */
function cmp_ensure_import_batch( $class_id, $data ) {
	$class_id         = absint( $class_id );
	$batch_name       = cmp_clean_title_text( cmp_field( $data, 'batch_name' ) );
	$razorpay_page_id = sanitize_text_field( cmp_field( $data, 'razorpay_page_id' ) );
	$existing         = $razorpay_page_id ? cmp_get_batch_by_razorpay_page_id( $razorpay_page_id ) : null;

	if ( ! $existing ) {
		$existing = cmp_find_batch_by_name( $class_id, $batch_name );
	}

	$payload = array(
		'class_id'         => $class_id,
		'batch_name'       => $batch_name,
		'start_date'       => cmp_field( $data, 'start_date' ),
		'fee_due_date'     => cmp_field( $data, 'fee_due_date' ),
		'status'           => cmp_field( $data, 'status', 'active' ),
		'razorpay_link'    => cmp_field( $data, 'razorpay_link' ),
		'batch_fee'        => cmp_field( $data, 'batch_fee', 0 ),
		'is_free'          => ! empty( $data['is_free'] ) ? 1 : 0,
		'razorpay_page_id' => $razorpay_page_id,
	);

	if ( $existing ) {
		$payload['start_date']    = '' !== (string) $payload['start_date'] ? $payload['start_date'] : $existing->start_date;
		$payload['fee_due_date']  = '' !== (string) $payload['fee_due_date'] ? $payload['fee_due_date'] : $existing->fee_due_date;
		$payload['razorpay_link'] = '' !== (string) $payload['razorpay_link'] ? $payload['razorpay_link'] : $existing->razorpay_link;
		$payload['batch_fee']     = cmp_money_value( $payload['batch_fee'] );
		$payload['batch_fee']     = $payload['batch_fee'] > 0 ? $payload['batch_fee'] : (float) $existing->batch_fee;
		$result                   = cmp_update_batch( (int) $existing->id, $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'class_id' => $class_id,
			'batch_id' => (int) $existing->id,
			'batch'    => cmp_get_batch( (int) $existing->id ),
			'created'  => false,
		);
	}

	$result = cmp_insert_batch( $payload );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'class_id' => $class_id,
		'batch_id' => (int) $result,
		'batch'    => cmp_get_batch( (int) $result ),
		'created'  => true,
	);
}

/**
 * Converts Razorpay minor units into a display amount.
 *
 * @param mixed $amount Minor-unit amount.
 * @return float
 */
function cmp_razorpay_minor_to_major( $amount ) {
	return is_numeric( $amount ) ? round( (float) $amount / 100, 2 ) : 0;
}

/**
 * Makes a GET request to Razorpay.
 *
 * @param string $path API path.
 * @param array  $query Query args.
 * @return array|WP_Error
 */
function cmp_razorpay_api_get( $path, $query = array() ) {
	$credentials = cmp_get_razorpay_credentials();

	if ( empty( $credentials['key_id'] ) || empty( $credentials['secret'] ) ) {
		return new WP_Error( 'cmp_razorpay_credentials_missing', __( 'Razorpay API keys are not configured.', 'class-manager-pro' ) );
	}

	$url = 'https://api.razorpay.com/v1/' . ltrim( $path, '/' );

	if ( ! empty( $query ) ) {
		$url = add_query_arg( $query, $url );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $credentials['key_id'] . ':' . $credentials['secret'] ),
				'Accept'        => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		return new WP_Error(
			'cmp_razorpay_api_error',
			isset( $body['error']['description'] ) ? sanitize_text_field( $body['error']['description'] ) : __( 'Could not fetch data from Razorpay.', 'class-manager-pro' )
		);
	}

	return $body;
}

/**
 * Fetches a paginated Razorpay collection.
 *
 * @param string $path API path.
 * @param string $items_key Response items key.
 * @return array|WP_Error
 */
function cmp_razorpay_fetch_collection( $path, $items_key ) {
	$items = array();
	$skip  = 0;
	$count = 100;

	do {
		$response = cmp_razorpay_api_get(
			$path,
			array(
				'count' => $count,
				'skip'  => $skip,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$page_items = array();

		if ( isset( $response[ $items_key ] ) && is_array( $response[ $items_key ] ) ) {
			$page_items = $response[ $items_key ];
		} elseif ( isset( $response['items'] ) && is_array( $response['items'] ) ) {
			$page_items = $response['items'];
		}

		$items = array_merge( $items, $page_items );
		$skip += count( $page_items );
	} while ( count( $page_items ) === $count );

	return $items;
}

/**
 * Returns a useful import title from a Razorpay entity.
 *
 * @param array $entity Razorpay entity.
 * @return string
 */
function cmp_razorpay_entity_title( $entity ) {
	$notes = isset( $entity['notes'] ) && is_array( $entity['notes'] ) ? $entity['notes'] : array();

	foreach ( array( 'form_name', 'batch_name', 'class_name', 'course_name', 'description', 'reference_id', 'receipt', 'id' ) as $key ) {
		if ( isset( $notes[ $key ] ) && is_scalar( $notes[ $key ] ) && '' !== trim( (string) $notes[ $key ] ) ) {
			return cmp_clean_title_text( $notes[ $key ] );
		}

		if ( isset( $entity[ $key ] ) && is_scalar( $entity[ $key ] ) && '' !== trim( (string) $entity[ $key ] ) ) {
			return cmp_clean_title_text( $entity[ $key ] );
		}
	}

	return __( 'Razorpay Imports', 'class-manager-pro' );
}

/**
 * Returns a fallback batch for unmapped imports.
 *
 * @return object|null
 */
function cmp_get_or_create_fallback_batch() {
	$class_id = cmp_ensure_import_class( __( 'Razorpay Imports', 'class-manager-pro' ), __( 'Created automatically for imported Razorpay data that had no clear batch mapping.', 'class-manager-pro' ) );

	if ( is_wp_error( $class_id ) ) {
		return null;
	}

	$batch = cmp_find_batch_by_name( (int) $class_id, __( 'General Intake', 'class-manager-pro' ) );

	if ( $batch ) {
		return $batch;
	}

	$result = cmp_insert_batch(
		array(
			'class_id'     => (int) $class_id,
			'batch_name'   => __( 'General Intake', 'class-manager-pro' ),
			'status'       => 'active',
			'batch_fee'    => 0,
			'is_free'      => 0,
			'razorpay_link' => '',
		)
	);

	if ( is_wp_error( $result ) ) {
		return null;
	}

	return cmp_get_batch( (int) $result );
}

/**
 * Imports or updates a Razorpay payment link as a batch.
 *
 * @param array $payment_link Razorpay payment link.
 * @return array|WP_Error
 */
function cmp_import_razorpay_payment_link( $payment_link ) {
	$notes        = isset( $payment_link['notes'] ) && is_array( $payment_link['notes'] ) ? $payment_link['notes'] : array();
	$title        = cmp_razorpay_entity_title( $payment_link );
	$names        = cmp_guess_class_batch_names( $title, $notes );
	$class_id     = cmp_ensure_import_class( $names['class_name'], __( 'Created automatically from Razorpay import.', 'class-manager-pro' ) );
	$batch_fee    = cmp_money_value( isset( $notes['batch_fee'] ) ? $notes['batch_fee'] : 0 );
	$fee_due_date = sanitize_text_field( isset( $notes['fee_due_date'] ) ? $notes['fee_due_date'] : '' );
	$start_date   = sanitize_text_field( isset( $notes['start_date'] ) ? $notes['start_date'] : '' );

	if ( is_wp_error( $class_id ) ) {
		return $class_id;
	}

	if ( $batch_fee <= 0 && ! empty( $payment_link['amount'] ) ) {
		$batch_fee = cmp_razorpay_minor_to_major( $payment_link['amount'] );
	}

	$result = cmp_ensure_import_batch(
		(int) $class_id,
		array(
			'batch_name'       => $names['batch_name'],
			'start_date'       => $start_date,
			'fee_due_date'     => $fee_due_date,
			'status'           => 'active',
			'razorpay_link'    => isset( $payment_link['short_url'] ) ? esc_url_raw( $payment_link['short_url'] ) : '',
			'batch_fee'        => $batch_fee,
			'is_free'          => 0,
			'razorpay_page_id' => isset( $payment_link['id'] ) ? sanitize_text_field( $payment_link['id'] ) : '',
		)
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $result;
}

/**
 * Determines whether a Razorpay payment is complete.
 *
 * @param array $payment Razorpay payment entity.
 * @return bool
 */
function cmp_is_successful_razorpay_payment( $payment ) {
	$status = isset( $payment['status'] ) && is_scalar( $payment['status'] ) ? strtolower( (string) $payment['status'] ) : '';

	if ( 'captured' !== $status ) {
		return false;
	}

	if ( isset( $payment['captured'] ) && true !== filter_var( $payment['captured'], FILTER_VALIDATE_BOOLEAN ) ) {
		return false;
	}

	if ( ! empty( $payment['error_code'] ) || ! empty( $payment['error_description'] ) ) {
		return false;
	}

	if ( empty( $payment['amount'] ) || cmp_razorpay_minor_to_major( $payment['amount'] ) <= 0 ) {
		return false;
	}

	return true;
}

/**
 * Reads the payment-link/page ID from a Razorpay payment entity.
 *
 * @param array $payment Razorpay payment entity.
 * @return string
 */
function cmp_razorpay_payment_link_id_from_payment( $payment ) {
	if ( ! is_array( $payment ) ) {
		return '';
	}

	$notes = isset( $payment['notes'] ) && is_array( $payment['notes'] ) ? $payment['notes'] : array();

	foreach ( array( $payment, $notes ) as $source ) {
		foreach ( array( 'payment_link_id', 'razorpay_page_id', 'payment_page_id', 'page_id' ) as $key ) {
			if ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) && '' !== trim( (string) $source[ $key ] ) ) {
				return sanitize_text_field( $source[ $key ] );
			}
		}
	}

	return '';
}

/**
 * Keeps only captured, paid Razorpay payments, optionally for one page.
 *
 * @param array  $payments Razorpay payment rows.
 * @param string $page_id Optional payment-link/page ID.
 * @return array
 */
function cmp_filter_successful_razorpay_payments( $payments, $page_id = '' ) {
	$page_id  = sanitize_text_field( $page_id );
	$filtered = array();
	$seen     = array();

	foreach ( $payments as $payment ) {
		if ( ! is_array( $payment ) || ! cmp_is_successful_razorpay_payment( $payment ) ) {
			continue;
		}

		if ( '' !== $page_id && $page_id !== cmp_razorpay_payment_link_id_from_payment( $payment ) ) {
			continue;
		}

		$payment_id = isset( $payment['id'] ) && is_scalar( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

		if ( '' !== $payment_id ) {
			if ( isset( $seen[ $payment_id ] ) ) {
				continue;
			}

			$seen[ $payment_id ] = true;
		}

		$filtered[] = $payment;
	}

	return $filtered;
}

/**
 * Returns payment-link IDs that have at least one successful payment.
 *
 * @param array $payments Successful Razorpay payments.
 * @return array
 */
function cmp_successful_razorpay_payment_link_ids( $payments ) {
	$page_ids = array();

	foreach ( $payments as $payment ) {
		$page_id = cmp_razorpay_payment_link_id_from_payment( $payment );

		if ( '' !== $page_id ) {
			$page_ids[] = $page_id;
		}
	}

	return array_values( array_unique( $page_ids ) );
}

/**
 * Creates or updates a student from imported Razorpay payment context.
 *
 * @param array       $payment Razorpay payment entity.
 * @param object|null $batch Batch object.
 * @return int|WP_Error
 */
function cmp_upsert_import_student_from_payment( $payment, $batch = null ) {
	global $wpdb;

	$notes     = isset( $payment['notes'] ) && is_array( $payment['notes'] ) ? $payment['notes'] : array();
	$name      = sanitize_text_field( cmp_first_scalar_value( array( $notes['name'] ?? '', $notes['student_name'] ?? '', $notes['customer_name'] ?? '', $payment['name'] ?? '', $payment['customer_name'] ?? '' ) ) );
	$email     = sanitize_email( cmp_first_scalar_value( array( $notes['email'] ?? '', $notes['student_email'] ?? '', $notes['customer_email'] ?? '', $payment['email'] ?? '', $payment['customer_email'] ?? '' ) ) );
	$phone     = sanitize_text_field( cmp_first_scalar_value( array( $notes['phone'] ?? '', $notes['mobile'] ?? '', $notes['contact'] ?? '', $notes['student_phone'] ?? '', $notes['student_mobile'] ?? '', $payment['contact'] ?? '', $payment['phone'] ?? '', $payment['customer_contact'] ?? '' ) ) );
	$total_fee = cmp_money_value( isset( $notes['total_fee'] ) ? $notes['total_fee'] : 0 );

	if ( '' === $name ) {
		$name = '' !== $email ? $email : $phone;
	}

	if ( '' === $phone ) {
		$phone = '' !== $email ? $email : sanitize_text_field( isset( $payment['id'] ) ? $payment['id'] : '' );
	}

	if ( ! $batch ) {
		$batch = cmp_get_or_create_fallback_batch();
	}

	if ( ! $batch ) {
		return new WP_Error( 'cmp_import_batch_missing', __( 'A batch could not be created for the imported payment.', 'class-manager-pro' ) );
	}

	if ( $total_fee <= 0 ) {
		$total_fee = cmp_get_batch_effective_fee( $batch );
	}

	$student = cmp_find_student_in_batch_by_identity( (int) $batch->id, $name, $email, $phone );

	if ( ! $student ) {
		$student = cmp_find_student_by_contact( $phone, $email );
	}

	if ( $student ) {
		$notes_text = cmp_append_note( $student->notes, __( 'Matched during Razorpay import.', 'class-manager-pro' ) );

		$wpdb->update(
			cmp_table( 'students' ),
			array(
				'name'      => $name,
				'phone'     => $phone,
				'email'     => $email,
				'class_id'  => (int) $batch->class_id,
				'batch_id'  => (int) $batch->id,
				'total_fee' => $student->total_fee > 0 ? (float) $student->total_fee : $total_fee,
				'notes'     => $notes_text,
			),
			array( 'id' => (int) $student->id ),
			array( '%s', '%s', '%s', '%d', '%d', '%f', '%s' ),
			array( '%d' )
		);

		return (int) $student->id;
	}

	return cmp_insert_student(
		array(
			'name'      => $name,
			'phone'     => $phone,
			'email'     => $email,
			'class_id'  => (int) $batch->class_id,
			'batch_id'  => (int) $batch->id,
			'total_fee' => $total_fee,
			'paid_fee'  => 0,
			'status'    => 'active',
			'notes'     => __( 'Created during Razorpay import.', 'class-manager-pro' ),
		)
	);
}

/**
 * Imports a captured Razorpay payment.
 *
 * @param array $payment Razorpay payment.
 * @param array $link_map Imported link map.
 * @return array
 */
function cmp_import_razorpay_payment( $payment, $link_map = array() ) {
	if ( ! cmp_is_successful_razorpay_payment( $payment ) ) {
		return array( 'status' => 'skipped' );
	}

	$amount = cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 );

	if ( $amount <= 0 ) {
		return array( 'status' => 'skipped' );
	}

	$transaction_id = isset( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

	if ( '' !== $transaction_id && cmp_payment_exists( $transaction_id ) ) {
		return array( 'status' => 'duplicate' );
	}

	$payment_link_id = cmp_razorpay_payment_link_id_from_payment( $payment );
	$batch           = $payment_link_id && isset( $link_map[ $payment_link_id ]['batch'] ) ? $link_map[ $payment_link_id ]['batch'] : null;

	if ( ! $batch ) {
		$batch = cmp_get_batch_by_razorpay_reference(
			array(
				$payment_link_id,
				isset( $payment['description'] ) ? $payment['description'] : '',
				isset( $payment['payment_link_reference_id'] ) ? $payment['payment_link_reference_id'] : '',
			)
		);
	}

	if ( ! $batch ) {
		$title       = cmp_razorpay_entity_title( $payment );
		$link_result = cmp_import_razorpay_payment_link(
			array(
				'id'          => $payment_link_id,
				'description' => $title,
				'short_url'   => '',
				'amount'      => isset( $payment['amount'] ) ? $payment['amount'] : 0,
				'notes'       => isset( $payment['notes'] ) && is_array( $payment['notes'] ) ? $payment['notes'] : array(),
			)
		);

		if ( ! is_wp_error( $link_result ) && ! empty( $link_result['batch_id'] ) ) {
			$batch = cmp_get_batch( (int) $link_result['batch_id'] );
		}
	}

	$student_id = cmp_upsert_import_student_from_payment( $payment, $batch );

	if ( is_wp_error( $student_id ) ) {
		return array(
			'status'  => 'error',
			'message' => $student_id->get_error_message(),
		);
	}

	$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
		? gmdate( 'Y-m-d H:i:s', (int) $payment['created_at'] )
		: cmp_current_datetime();

	$result = cmp_insert_payment(
		array(
			'student_id'     => (int) $student_id,
			'amount'         => $amount,
			'payment_mode'   => 'razorpay',
			'transaction_id' => $transaction_id,
			'payment_date'   => $payment_date,
		)
	);

	if ( is_wp_error( $result ) ) {
		return array(
			'status'  => 'error',
			'message' => $result->get_error_message(),
		);
	}

	return array(
		'status'     => 'imported',
		'student_id' => (int) $student_id,
		'payment_id' => (int) $result,
	);
}

/**
 * Imports a captured Razorpay payment into a specific batch.
 *
 * @param array  $payment Razorpay payment.
 * @param object $batch Batch row.
 * @return array
 */
function cmp_import_razorpay_payment_into_batch( $payment, $batch ) {
	if ( ! $batch || ! cmp_is_successful_razorpay_payment( $payment ) ) {
		return array( 'status' => 'skipped' );
	}

	$transaction_id = isset( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

	if ( '' !== $transaction_id && cmp_payment_exists( $transaction_id ) ) {
		cmp_upsert_import_student_from_payment( $payment, $batch );

		return array( 'status' => 'duplicate' );
	}

	$student_id = cmp_upsert_import_student_from_payment( $payment, $batch );

	if ( is_wp_error( $student_id ) ) {
		return array(
			'status'  => 'error',
			'message' => $student_id->get_error_message(),
		);
	}

	$amount = cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 );

	if ( $amount <= 0 ) {
		return array( 'status' => 'skipped' );
	}

	$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
		? gmdate( 'Y-m-d H:i:s', (int) $payment['created_at'] )
		: cmp_current_datetime();

	$result = cmp_insert_payment(
		array(
			'student_id'     => (int) $student_id,
			'amount'         => $amount,
			'payment_mode'   => 'razorpay',
			'transaction_id' => $transaction_id,
			'payment_date'   => $payment_date,
		)
	);

	if ( is_wp_error( $result ) ) {
		return array(
			'status'  => 'error',
			'message' => $result->get_error_message(),
		);
	}

	return array(
		'status'     => 'imported',
		'student_id' => (int) $student_id,
		'payment_id' => (int) $result,
	);
}

/**
 * Gets all Razorpay payment links for selection screens.
 *
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_links_for_admin() {
	return cmp_razorpay_fetch_collection( 'payment_links/', 'payment_links' );
}

/**
 * Fetches a single Razorpay payment link.
 *
 * @param string $page_id Payment link ID.
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_link( $page_id ) {
	return cmp_razorpay_api_get( 'payment_links/' . rawurlencode( sanitize_text_field( $page_id ) ) );
}

/**
 * Returns successful captured payments for a Razorpay payment link.
 *
 * @param string $page_id Payment link ID.
 * @return array|WP_Error
 */
function cmp_get_successful_razorpay_payments_for_link( $page_id ) {
	$page_id  = sanitize_text_field( $page_id );
	$payments = cmp_razorpay_fetch_collection( 'payments', 'items' );

	if ( is_wp_error( $payments ) ) {
		return $payments;
	}

	return cmp_filter_successful_razorpay_payments( $payments, $page_id );
}

/**
 * Extracts displayable student data from a Razorpay payment.
 *
 * @param array $payment Razorpay payment.
 * @return array
 */
function cmp_payment_student_preview( $payment ) {
	$notes = isset( $payment['notes'] ) && is_array( $payment['notes'] ) ? $payment['notes'] : array();
	$name  = cmp_first_scalar_value( array( $notes['name'] ?? '', $notes['student_name'] ?? '', $notes['customer_name'] ?? '', $payment['name'] ?? '', $payment['customer_name'] ?? '' ) );
	$email = cmp_first_scalar_value( array( $notes['email'] ?? '', $notes['student_email'] ?? '', $notes['customer_email'] ?? '', $payment['email'] ?? '', $payment['customer_email'] ?? '' ) );
	$phone = cmp_first_scalar_value( array( $notes['phone'] ?? '', $notes['mobile'] ?? '', $notes['contact'] ?? '', $notes['student_phone'] ?? '', $notes['student_mobile'] ?? '', $payment['contact'] ?? '', $payment['phone'] ?? '', $payment['customer_contact'] ?? '' ) );

	if ( '' === trim( (string) $name ) ) {
		$name = '' !== trim( (string) $email ) ? $email : $phone;
	}

	return array(
		'name'   => sanitize_text_field( $name ),
		'email'  => sanitize_email( $email ),
		'phone'  => sanitize_text_field( $phone ),
		'amount' => cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 ),
		'id'     => isset( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '',
		'date'   => isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $payment['created_at'] ) : '',
	);
}

/**
 * Counts unique students represented by a list of Razorpay payments.
 *
 * @param array $payments Razorpay payments.
 * @return int
 */
function cmp_count_unique_razorpay_students( $payments ) {
	$seen          = array();
	$active_groups = array();
	$next_group_id = 0;

	foreach ( $payments as $payment ) {
		$preview = cmp_payment_student_preview( $payment );
		$keys    = array();

		if ( '' !== $preview['email'] ) {
			$keys[] = 'email:' . strtolower( $preview['email'] );
		}

		if ( '' !== $preview['name'] ) {
			$keys[] = 'name:' . cmp_normalize_name_key( $preview['name'] );
		}

		if ( '' !== $preview['phone'] ) {
			$phone_keys = cmp_phone_match_keys( $preview['phone'] );

			if ( $phone_keys ) {
				$keys[] = 'phone:' . $phone_keys[0];
			}
		}

		if ( empty( $keys ) && '' !== $preview['id'] ) {
			$keys[] = 'payment:' . $preview['id'];
		}

		$matched_groups = array();

		foreach ( $keys as $key ) {
			if ( isset( $seen[ $key ] ) ) {
				$matched_groups[] = (int) $seen[ $key ];
			}
		}

		$matched_groups = array_values( array_unique( array_filter( $matched_groups ) ) );

		if ( $matched_groups ) {
			$group = min( $matched_groups );

			if ( count( $matched_groups ) > 1 ) {
				foreach ( $seen as $seen_key => $seen_group ) {
					if ( in_array( (int) $seen_group, $matched_groups, true ) ) {
						$seen[ $seen_key ] = $group;
					}
				}

				foreach ( $matched_groups as $matched_group ) {
					unset( $active_groups[ $matched_group ] );
				}
			}
		} else {
			++$next_group_id;
			$group = $next_group_id;
		}

		$active_groups[ $group ] = true;

		foreach ( $keys as $key ) {
			$seen[ $key ] = $group;
		}
	}

	return count( $active_groups );
}

/**
 * Imports all successful payments from a Razorpay page into a selected batch.
 *
 * @param string $page_id Razorpay payment link ID.
 * @param int    $batch_id Batch ID.
 * @return array|WP_Error
 */
function cmp_import_razorpay_page_to_batch( $page_id, $batch_id ) {
	$page_id = sanitize_text_field( $page_id );
	$batch   = cmp_get_batch( absint( $batch_id ) );

	if ( '' === $page_id || ! $batch ) {
		return new WP_Error( 'cmp_import_page_invalid', __( 'Please choose a valid Razorpay page and batch.', 'class-manager-pro' ) );
	}

	$link = cmp_get_razorpay_payment_link( $page_id );

	if ( is_wp_error( $link ) ) {
		return $link;
	}

	cmp_update_batch(
		(int) $batch->id,
		array(
			'class_id'         => (int) $batch->class_id,
			'batch_name'       => $batch->batch_name,
			'start_date'       => $batch->start_date,
			'fee_due_date'     => $batch->fee_due_date,
			'status'           => $batch->status,
			'razorpay_link'    => isset( $link['short_url'] ) ? esc_url_raw( $link['short_url'] ) : $batch->razorpay_link,
			'batch_fee'        => (float) $batch->batch_fee,
			'is_free'          => (int) $batch->is_free,
			'razorpay_page_id' => $page_id,
		)
	);

	$batch    = cmp_get_batch( (int) $batch->id );
	$payments = cmp_get_successful_razorpay_payments_for_link( $page_id );

	if ( is_wp_error( $payments ) ) {
		return $payments;
	}

	$summary = array(
		'imported'  => 0,
		'duplicate' => 0,
		'skipped'   => 0,
		'failed'    => 0,
	);

	foreach ( $payments as $payment ) {
		$result = cmp_import_razorpay_payment_into_batch( $payment, $batch );
		$status = isset( $result['status'] ) ? $result['status'] : 'failed';

		if ( isset( $summary[ $status ] ) ) {
			++$summary[ $status ];
		} else {
			++$summary['failed'];
		}
	}

	return $summary;
}

/**
 * Builds a batch map from imported Razorpay payment links.
 *
 * @param array $links Payment-link entities.
 * @param array $paid_page_ids Optional IDs that have successful captured payments.
 * @return array
 */
function cmp_build_razorpay_link_map( $links, $paid_page_ids = null ) {
	$map           = array();
	$restricted    = null !== $paid_page_ids;
	$paid_page_ids = array_map( 'sanitize_text_field', (array) $paid_page_ids );
	$paid_page_ids = array_fill_keys( array_filter( $paid_page_ids ), true );

	foreach ( $links as $link ) {
		$link_id = isset( $link['id'] ) ? sanitize_text_field( $link['id'] ) : '';

		if ( $restricted && ( '' === $link_id || ! isset( $paid_page_ids[ $link_id ] ) ) ) {
			continue;
		}

		$result = cmp_import_razorpay_payment_link( $link );

		if ( ! is_wp_error( $result ) && '' !== $link_id ) {
			$map[ $link_id ] = $result;
		}
	}

	return $map;
}

/**
 * Imports payment links and payments from Razorpay.
 *
 * @return array|WP_Error
 */
function cmp_import_all_razorpay_data() {
	$links = cmp_razorpay_fetch_collection( 'payment_links/', 'payment_links' );

	if ( is_wp_error( $links ) ) {
		return $links;
	}

	$payments = cmp_razorpay_fetch_collection( 'payments', 'items' );

	if ( is_wp_error( $payments ) ) {
		return $payments;
	}

	$successful_payments = cmp_filter_successful_razorpay_payments( $payments );
	$paid_page_ids       = cmp_successful_razorpay_payment_link_ids( $successful_payments );
	$link_map            = cmp_build_razorpay_link_map( $links, $paid_page_ids );

	$summary = array(
		'classes'         => 0,
		'batches'         => count( $link_map ),
		'payments'        => 0,
		'students'        => 0,
		'skipped'         => max( 0, count( $payments ) - count( $successful_payments ) ),
		'failed'          => 0,
		'successful_seen' => count( $successful_payments ),
	);

	foreach ( $link_map as $result ) {
		if ( ! empty( $result['created'] ) ) {
			++$summary['classes'];
		}
	}

	foreach ( $successful_payments as $payment ) {
		$result = cmp_import_razorpay_payment( $payment, $link_map );

		if ( 'imported' === $result['status'] ) {
			++$summary['payments'];
			if ( ! empty( $result['student_id'] ) ) {
				++$summary['students'];
			}
		} elseif ( 'skipped' === $result['status'] || 'duplicate' === $result['status'] ) {
			++$summary['skipped'];
		} else {
			++$summary['failed'];
		}
	}

	return $summary;
}

/**
 * Saves detected Razorpay keys into plugin settings.
 */
function cmp_handle_import_detected_razorpay_keys() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_import_detected_razorpay_keys' );

	if ( ! function_exists( 'cmp_detect_wordpress_razorpay' ) ) {
		cmp_redirect( 'cmp-settings', __( 'No WordPress Razorpay integration could be detected.', 'class-manager-pro' ), 'error' );
	}

	$detected = cmp_detect_wordpress_razorpay();

	if ( empty( $detected ) ) {
		cmp_redirect( 'cmp-settings', __( 'No WordPress Razorpay integration could be detected.', 'class-manager-pro' ), 'error' );
	}

	foreach ( $detected as $source => $keys ) {
		update_option( 'cmp_razorpay_key_id', sanitize_text_field( $keys['key_id'] ) );
		update_option( 'cmp_razorpay_secret', sanitize_text_field( $keys['secret'] ) );
		cmp_redirect( 'cmp-settings', sprintf( __( 'Razorpay keys imported from %s.', 'class-manager-pro' ), $source ) );
	}
}

/**
 * Imports all Razorpay data.
 */
function cmp_handle_import_razorpay_data() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_import_razorpay_data' );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$result = cmp_import_all_razorpay_data();

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-settings', $result->get_error_message(), 'error' );
	}

	cmp_redirect(
		'cmp-settings',
		sprintf(
			/* translators: 1: batches 2: payments 3: skipped 4: failed */
			__( 'Razorpay import finished. %1$d batches synced, %2$d payments imported, %3$d skipped, %4$d failed.', 'class-manager-pro' ),
			(int) $result['batches'],
			(int) $result['payments'],
			(int) $result['skipped'],
			(int) $result['failed']
		)
	);
}

/**
 * Imports a single Razorpay entity manually.
 */
function cmp_handle_manual_import_razorpay() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_manual_import_razorpay' );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$import_type = sanitize_key( cmp_field( $_POST, 'import_type' ) );
	$razorpay_id = sanitize_text_field( cmp_field( $_POST, 'razorpay_id' ) );

	if ( '' === $razorpay_id ) {
		cmp_redirect( 'cmp-settings', __( 'Please enter a Razorpay ID to import.', 'class-manager-pro' ), 'error' );
	}

	if ( 'payment_link' === $import_type ) {
		$response = cmp_razorpay_api_get( 'payment_links/' . rawurlencode( $razorpay_id ) );
		$result   = is_wp_error( $response ) ? $response : cmp_import_razorpay_payment_link( $response );
	} elseif ( 'payment' === $import_type ) {
		$response = cmp_razorpay_api_get( 'payments/' . rawurlencode( $razorpay_id ) );
		$result   = is_wp_error( $response ) ? $response : cmp_import_razorpay_payment( $response );
	} else {
		$result = new WP_Error( 'cmp_invalid_manual_import', __( 'Unsupported manual import type.', 'class-manager-pro' ) );
	}

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-settings', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-settings', __( 'Razorpay item imported successfully.', 'class-manager-pro' ) );
}

/**
 * Imports the selected Razorpay page into an admin-selected batch.
 */
function cmp_handle_import_razorpay_page_to_batch() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_import_razorpay_page_to_batch' );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$page_id  = sanitize_text_field( cmp_field( $_POST, 'razorpay_page_id' ) );
	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );

	$result = cmp_import_razorpay_page_to_batch( $page_id, $batch_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-razorpay-import', $result->get_error_message(), 'error', array( 'razorpay_page_id' => $page_id ) );
	}

	cmp_redirect(
		'cmp-razorpay-import',
		sprintf(
			/* translators: 1: imported 2: duplicate 3: skipped 4: failed */
			__( 'Razorpay page import completed. %1$d payments imported, %2$d duplicates skipped, %3$d non-success payments skipped, %4$d failed.', 'class-manager-pro' ),
			(int) $result['imported'],
			(int) $result['duplicate'],
			(int) $result['skipped'],
			(int) $result['failed']
		),
		'success',
		array( 'razorpay_page_id' => $page_id )
	);
}

/**
 * Deletes all custom plugin records while keeping settings.
 */
function cmp_handle_reset_plugin_data() {
	global $wpdb;

	cmp_require_manage_options();
	check_admin_referer( 'cmp_reset_plugin_data' );

	$confirmation = sanitize_text_field( cmp_field( $_POST, 'reset_confirmation' ) );

	if ( 'DELETE' !== $confirmation ) {
		cmp_redirect( 'cmp-settings', __( 'Type DELETE to confirm clearing all Class Manager Pro data.', 'class-manager-pro' ), 'error' );
	}

	foreach ( array( 'reminders', 'attendance', 'payments', 'students', 'batches', 'classes' ) as $table ) {
		$table_name = cmp_table( $table );
		$wpdb->query( "DELETE FROM {$table_name}" );
		$wpdb->query( "ALTER TABLE {$table_name} AUTO_INCREMENT = 1" );
	}

	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->esc_like( '_transient_cmp_intake_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_cmp_intake_' ) . '%'
		)
	);

	cmp_redirect( 'cmp-settings', __( 'All Class Manager Pro records were deleted. Settings and Razorpay keys were kept.', 'class-manager-pro' ) );
}

/**
 * Saves reminder settings.
 */
function cmp_handle_save_sms_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_sms_settings' );

	$provider = cmp_clean_enum( cmp_field( $_POST, 'notification_provider', 'log_only' ), array( 'log_only', 'custom_webhook' ), 'log_only' );
	$channels = cmp_clean_enum( cmp_field( $_POST, 'notification_channels', 'both' ), array( 'sms', 'whatsapp', 'both' ), 'both' );
	$days     = absint( cmp_field( $_POST, 'reminder_days', 7 ) );

	update_option( 'cmp_notifications_enabled', ! empty( $_POST['sms_enabled'] ) ? '1' : '0' );
	update_option( 'cmp_sms_enabled', ! empty( $_POST['sms_enabled'] ) ? '1' : '0' );
	update_option( 'cmp_notification_provider', $provider );
	update_option( 'cmp_notification_webhook_url', esc_url_raw( cmp_field( $_POST, 'notification_webhook_url' ) ) );
	update_option( 'cmp_notification_auth_token', sanitize_text_field( cmp_field( $_POST, 'notification_auth_token' ) ) );
	update_option( 'cmp_notification_sender', sanitize_text_field( cmp_field( $_POST, 'sms_sender' ) ) );
	update_option( 'cmp_notification_channels', $channels );
	update_option( 'cmp_reminder_days', in_array( $days, array( 1, 3, 7, 14 ), true ) ? $days : 7 );
	update_option( 'cmp_whatsapp_template', sanitize_textarea_field( cmp_field( $_POST, 'whatsapp_template' ) ) );
	update_option( 'cmp_sms_template', sanitize_textarea_field( cmp_field( $_POST, 'sms_template' ) ) );

	cmp_redirect( 'cmp-settings', __( 'Reminder settings saved successfully.', 'class-manager-pro' ) );
}

/**
 * Saves attendance settings.
 */
function cmp_handle_save_attendance_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_attendance_settings' );

	update_option( 'cmp_attendance_enabled', ! empty( $_POST['attendance_enabled'] ) ? '1' : '0' );
	update_option(
		'cmp_default_attendance_status',
		cmp_clean_enum( cmp_field( $_POST, 'default_attendance_status', 'present' ), cmp_attendance_statuses(), 'present' )
	);

	cmp_redirect( 'cmp-settings', __( 'Attendance settings saved successfully.', 'class-manager-pro' ) );
}

/**
 * Returns whether attendance is enabled.
 *
 * @return bool
 */
function cmp_is_attendance_enabled() {
	return '1' === (string) get_option( 'cmp_attendance_enabled', '1' );
}

/**
 * Gets attendance for a batch and date keyed by student ID.
 *
 * @param int    $batch_id Batch ID.
 * @param string $date Attendance date.
 * @return array
 */
function cmp_get_batch_attendance( $batch_id, $date ) {
	global $wpdb;

	$rows   = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_table( 'attendance' ) . ' WHERE batch_id = %d AND attendance_date = %s',
			absint( $batch_id ),
			sanitize_text_field( $date )
		)
	);
	$result = array();

	foreach ( $rows as $row ) {
		$result[ (int) $row->student_id ] = $row;
	}

	return $result;
}

/**
 * Gets attendance counts for a batch and date.
 *
 * @param int    $batch_id Batch ID.
 * @param string $date Attendance date.
 * @return array
 */
function cmp_get_batch_attendance_summary( $batch_id, $date ) {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT status, COUNT(*) AS total FROM ' . cmp_table( 'attendance' ) . ' WHERE batch_id = %d AND attendance_date = %s GROUP BY status',
			absint( $batch_id ),
			sanitize_text_field( $date )
		),
		OBJECT_K
	);

	return array(
		'present' => isset( $rows['present'] ) ? (int) $rows['present']->total : 0,
		'absent'  => isset( $rows['absent'] ) ? (int) $rows['absent']->total : 0,
		'leave'   => isset( $rows['leave'] ) ? (int) $rows['leave']->total : 0,
	);
}

/**
 * Saves attendance entries for a batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $date Date.
 * @param array  $entries Attendance entries.
 * @return void
 */
function cmp_save_batch_attendance( $batch_id, $date, $entries ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$date     = sanitize_text_field( $date );

	foreach ( $entries as $student_id => $entry ) {
		$student_id = absint( $student_id );
		$status     = cmp_clean_enum( isset( $entry['status'] ) ? $entry['status'] : '', cmp_attendance_statuses(), (string) get_option( 'cmp_default_attendance_status', 'present' ) );
		$notes      = sanitize_textarea_field( isset( $entry['notes'] ) ? $entry['notes'] : '' );

		if ( ! $student_id ) {
			continue;
		}

		$wpdb->replace(
			cmp_table( 'attendance' ),
			array(
				'batch_id'         => $batch_id,
				'student_id'       => $student_id,
				'attendance_date'  => $date,
				'status'           => $status,
				'notes'            => $notes,
				'created_at'       => cmp_current_datetime(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}

/**
 * Saves batch attendance from the admin form.
 */
function cmp_handle_save_attendance() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_attendance' );

	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	$date     = sanitize_text_field( cmp_field( $_POST, 'attendance_date', current_time( 'Y-m-d' ) ) );
	$entries  = isset( $_POST['attendance'] ) && is_array( $_POST['attendance'] ) ? wp_unslash( $_POST['attendance'] ) : array();

	if ( ! $batch_id || ! cmp_get_batch( $batch_id ) ) {
		cmp_redirect( 'cmp-batches', __( 'Attendance could not be saved because the batch was not found.', 'class-manager-pro' ), 'error' );
	}

	cmp_save_batch_attendance( $batch_id, $date, $entries );

	cmp_redirect(
		'cmp-batches',
		__( 'Attendance saved successfully.', 'class-manager-pro' ),
		'success',
		array(
			'action'          => 'view',
			'id'              => $batch_id,
			'attendance_date' => $date,
		)
	);
}

/**
 * Gets students who should receive fee reminders.
 *
 * @param int $days Reminder lead time.
 * @return array
 */
function cmp_get_fee_reminder_targets( $days ) {
	global $wpdb;

	$days = max( 0, absint( $days ) );

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT s.*, c.name AS class_name, b.batch_name, b.razorpay_link, b.fee_due_date
			FROM ' . cmp_table( 'students' ) . ' s
			INNER JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
			WHERE s.status = %s
				AND GREATEST(s.total_fee - s.paid_fee, 0) > 0
				AND b.fee_due_date IS NOT NULL
				AND b.fee_due_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
			ORDER BY b.fee_due_date ASC, s.id ASC',
			'active',
			$days
		)
	);
}

/**
 * Builds a fee reminder message.
 *
 * @param object $student Student row.
 * @param string $channel sms|whatsapp.
 * @return string
 */
function cmp_build_fee_reminder_message( $student, $channel ) {
	$template = 'whatsapp' === $channel
		? (string) get_option( 'cmp_whatsapp_template', '' )
		: (string) get_option( 'cmp_sms_template', '' );

	if ( '' === trim( $template ) ) {
		$template = __( 'Hello {student_name}, your pending fee for {class_name} - {batch_name} is Rs {pending_fee}. Due date: {due_date}. Payment link: {payment_link}', 'class-manager-pro' );
	}

	return strtr(
		$template,
		array(
			'{student_name}' => $student->name,
			'{class_name}'   => $student->class_name,
			'{batch_name}'   => $student->batch_name,
			'{pending_fee}'  => cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) ),
			'{due_date}'     => $student->fee_due_date ? $student->fee_due_date : __( 'Not set', 'class-manager-pro' ),
			'{payment_link}' => $student->razorpay_link ? $student->razorpay_link : __( 'Please contact the admin.', 'class-manager-pro' ),
		)
	);
}

/**
 * Checks whether a reminder was already logged for today.
 *
 * @param int    $student_id Student ID.
 * @param int    $batch_id Batch ID.
 * @param string $channel Channel.
 * @return bool
 */
function cmp_reminder_already_logged( $student_id, $batch_id, $channel ) {
	global $wpdb;

	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'reminders' ) . ' WHERE student_id = %d AND batch_id = %d AND reminder_date = %s AND channel = %s AND status = %s',
			absint( $student_id ),
			absint( $batch_id ),
			current_time( 'Y-m-d' ),
			sanitize_key( $channel ),
			'sent'
		)
	);
}

/**
 * Logs a reminder attempt.
 *
 * @param object $student Student row.
 * @param string $channel Channel.
 * @param string $status Result status.
 * @param string $response_message Provider response.
 * @return void
 */
function cmp_log_reminder( $student, $channel, $status, $response_message = '' ) {
	global $wpdb;

	$wpdb->replace(
		cmp_table( 'reminders' ),
		array(
			'student_id'        => (int) $student->id,
			'batch_id'          => (int) $student->batch_id,
			'reminder_date'     => current_time( 'Y-m-d' ),
			'due_date'          => $student->fee_due_date,
			'channel'           => sanitize_key( $channel ),
			'status'            => sanitize_key( $status ),
			'provider'          => sanitize_key( (string) get_option( 'cmp_notification_provider', 'log_only' ) ),
			'response_message'  => sanitize_textarea_field( $response_message ),
			'created_at'        => cmp_current_datetime(),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Sends a reminder to the configured provider.
 *
 * @param object $student Student row.
 * @param string $channel Channel.
 * @param string $message Message text.
 * @return array
 */
function cmp_send_fee_reminder( $student, $channel, $message ) {
	$provider = sanitize_key( (string) get_option( 'cmp_notification_provider', 'log_only' ) );

	if ( '' === trim( (string) $student->phone ) ) {
		return array(
			'success' => false,
			'message' => __( 'Student phone number is missing.', 'class-manager-pro' ),
		);
	}

	if ( 'custom_webhook' !== $provider ) {
		return array(
			'success' => true,
			'message' => __( 'Reminder logged without external delivery.', 'class-manager-pro' ),
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
				array(
					'channel'      => $channel,
					'sender'       => (string) get_option( 'cmp_notification_sender', '' ),
					'student_name' => $student->name,
					'phone'        => $student->phone,
					'email'        => $student->email,
					'class_name'   => $student->class_name,
					'batch_name'   => $student->batch_name,
					'due_date'     => $student->fee_due_date,
					'pending_fee'  => max( 0, (float) $student->total_fee - (float) $student->paid_fee ),
					'payment_link' => $student->razorpay_link,
					'message'      => $message,
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
		'message' => wp_remote_retrieve_body( $response ),
	);
}

/**
 * Processes reminder delivery for due and overdue students.
 *
 * @return array
 */
function cmp_process_fee_reminders() {
	$enabled = '1' === (string) get_option( 'cmp_notifications_enabled', get_option( 'cmp_sms_enabled', '0' ) );
	$days    = absint( get_option( 'cmp_reminder_days', 7 ) );
	$targets = $enabled ? cmp_get_fee_reminder_targets( $days ) : array();
	$counts  = array(
		'sent'    => 0,
		'failed'  => 0,
		'skipped' => 0,
	);

	if ( ! $enabled ) {
		return $counts;
	}

	$channel_setting = sanitize_key( (string) get_option( 'cmp_notification_channels', 'both' ) );
	$channels        = 'both' === $channel_setting ? array( 'sms', 'whatsapp' ) : array( $channel_setting );

	foreach ( $targets as $student ) {
		foreach ( $channels as $channel ) {
			if ( cmp_reminder_already_logged( (int) $student->id, (int) $student->batch_id, $channel ) ) {
				++$counts['skipped'];
				continue;
			}

			$message = cmp_build_fee_reminder_message( $student, $channel );
			$result  = cmp_send_fee_reminder( $student, $channel, $message );

			if ( ! empty( $result['success'] ) ) {
				cmp_log_reminder( $student, $channel, 'sent', $result['message'] );
				++$counts['sent'];
			} else {
				cmp_log_reminder( $student, $channel, 'failed', $result['message'] );
				++$counts['failed'];
			}
		}
	}

	return $counts;
}

/**
 * Runs scheduled reminder processing.
 */
function cmp_run_scheduled_fee_reminders() {
	cmp_process_fee_reminders();
}

/**
 * Sends reminders immediately from the settings page.
 */
function cmp_handle_send_fee_reminders() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_send_fee_reminders' );

	$counts = cmp_process_fee_reminders();

	cmp_redirect(
		'cmp-settings',
		sprintf(
			/* translators: 1: sent count 2: failed count 3: skipped count */
			__( 'Reminder run completed. %1$d sent, %2$d failed, %3$d skipped.', 'class-manager-pro' ),
			(int) $counts['sent'],
			(int) $counts['failed'],
			(int) $counts['skipped']
		)
	);
}
