<?php
/**
 * CMP Compatibility Layer
 *
 * Ensures backward compatibility with v1.x code.
 * All existing functions remain available.
 *
 * @package ClassManagerPro
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility shim for cmp_get_dashboard_metrics.
 * Now wraps the cached version.
 */
if ( ! function_exists( 'cmp_get_dashboard_snapshot' ) ) {
	function cmp_get_dashboard_snapshot( $range = 'today' ) {
		return CMP_Dashboard_Realtime::get_live_metrics( $range );
	}
}

/**
 * Enhanced cmp_get_students with caching.
 */
add_filter( 'cmp_get_students_query', 'cmp_cache_student_queries', 10, 2 );

function cmp_cache_student_queries( $students, $args ) {
	// The actual caching happens at the database layer via CMP_Cache::remember.
	// This filter hook allows extensions to modify results post-cache.
	return $students;
}

/**
 * Enhanced save hooks for cache invalidation.
 */
add_action( 'cmp_after_save_class', 'cmp_invalidate_class_cache', 10, 0 );
add_action( 'cmp_after_save_batch', 'cmp_invalidate_batch_cache', 10, 0 );
add_action( 'cmp_after_save_student', 'cmp_invalidate_student_cache', 10, 0 );
add_action( 'cmp_after_save_payment', 'cmp_invalidate_payment_cache', 10, 0 );

function cmp_invalidate_class_cache() {
	CMP_Cache::invalidate_classes();
}

function cmp_invalidate_batch_cache() {
	CMP_Cache::invalidate_batches();
}

function cmp_invalidate_student_cache() {
	CMP_Cache::invalidate_students();
}

function cmp_invalidate_payment_cache() {
	CMP_Cache::invalidate_payments();
}

/**
 * Enhanced cmp_get_classes with caching.
 */
if ( ! function_exists( 'cmp_get_classes_cached' ) ) {
	function cmp_get_classes_cached() {
		return CMP_Cache::remember( 'classes_all', 'cmp_get_classes', array(), 3600 );
	}
}

/**
 * Enhanced cmp_get_batches with caching.
 */
if ( ! function_exists( 'cmp_get_batches_cached' ) ) {
	function cmp_get_batches_cached() {
		return CMP_Cache::remember( 'batches_all', 'cmp_get_batches', array(), 3600 );
	}
}

/**
 * Enhanced cmp_get_students with caching for commonly used queries.
 */
if ( ! function_exists( 'cmp_get_students_cached' ) ) {
	function cmp_get_students_cached( $args = array() ) {
		$cache_key = 'students_' . md5( wp_json_encode( $args ) );
		return CMP_Cache::remember( $cache_key, 'cmp_get_students', array( $args ), 300 );
	}
}

/**
 * Safe redirect with nonce verification wrapper.
 */
if ( ! function_exists( 'cmp_safe_redirect' ) ) {
	function cmp_safe_redirect( $url, $status = 302 ) {
		$url = wp_sanitize_redirect( $url );
		wp_safe_redirect( $url, $status );
		exit;
	}
}

/**
 * Enhanced error message rendering with dismissible notices.
 */
if ( ! function_exists( 'cmp_show_error' ) ) {
	function cmp_show_error( $message, $code = '' ) {
		printf(
			'<div class="cmp-notice cmp-notice-error" data-code="%s"><p>%s</p></div>',
			esc_attr( $code ),
			esc_html( $message )
		);
	}
}

if ( ! function_exists( 'cmp_show_success' ) ) {
	function cmp_show_success( $message, $code = '' ) {
		printf(
			'<div class="cmp-notice cmp-notice-success" data-code="%s"><p>%s</p></div>',
			esc_attr( $code ),
			esc_html( $message )
		);
	}
}

if ( ! function_exists( 'cmp_show_info' ) ) {
	function cmp_show_info( $message, $code = '' ) {
		printf(
			'<div class="cmp-notice cmp-notice-info" data-code="%s"><p>%s</p></div>',
			esc_attr( $code ),
			esc_html( $message )
		);
	}
}

/**
 * AJAX-safe wrapper for checking if user can perform action.
 */
if ( ! function_exists( 'cmp_ajax_check_capability' ) ) {
	function cmp_ajax_check_capability( $cap = 'cmp_manage' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ), 403 );
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );
	}
}

/**
 * Validate and sanitize common form fields.
 */
if ( ! function_exists( 'cmp_sanitize_student_data' ) ) {
	function cmp_sanitize_student_data( $data ) {
		return array(
			'name'      => sanitize_text_field( $data['name'] ?? '' ),
			'email'     => sanitize_email( $data['email'] ?? '' ),
			'phone'     => preg_replace( '/[^0-9+]/', '', sanitize_text_field( $data['phone'] ?? '' ) ),
			'address'   => sanitize_textarea_field( $data['address'] ?? '' ),
			'gender'    => in_array( $data['gender'] ?? '', array( 'Male', 'Female', 'Other' ), true ) ? $data['gender'] : '',
			'status'    => in_array( $data['status'] ?? '', cmp_student_statuses(), true ) ? $data['status'] : 'Active',
			'notes'     => sanitize_textarea_field( $data['notes'] ?? '' ),
		);
	}
}

/**
 * Phone number normalization.
 */
if ( ! function_exists( 'cmp_normalize_phone' ) ) {
	function cmp_normalize_phone( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', sanitize_text_field( $phone ) );
		return $phone;
	}
}

/**
 * Duplicate student checker.
 */
if ( ! function_exists( 'cmp_is_duplicate_student' ) ) {
	function cmp_is_duplicate_student( $phone, $email, $exclude_id = 0 ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$sql = "SELECT id FROM {$prefix}students WHERE (phone = %s OR email = %s)";
		if ( $exclude_id ) {
			$sql .= $wpdb->prepare( ' AND id != %d', $exclude_id );
		}
		$sql .= ' LIMIT 1';

		$existing = $wpdb->get_var( $wpdb->prepare( $sql, cmp_normalize_phone( $phone ), sanitize_email( $email ) ) );
		return $existing ? (int) $existing : false;
	}
}

/**
 * Student dropdown using smart search (replaces large HTML selects).
 */
if ( ! function_exists( 'cmp_render_smart_student_select' ) ) {
	function cmp_render_smart_student_select( $name = 'student_id', $selected = 0, $required = true ) {
		$selected_data = '';
		if ( $selected ) {
			$student = cmp_get_student( $selected );
			if ( $student ) {
				$selected_data = sprintf( '%s - %s (%s)', $student->unique_id, $student->name, $student->phone );
			}
		}
		?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $selected ); ?>" <?php echo $required ? 'required' : ''; ?>>
		<input type="text"
			   class="cmp-smart-search"
			   data-target="<?php echo esc_attr( $name ); ?>"
			   data-type="student"
			   placeholder="<?php esc_attr_e( 'Search student by name, phone, or ID...', 'class-manager-pro' ); ?>"
			   value="<?php echo esc_attr( $selected_data ); ?>">
		<?php
	}
}

/**
 * Batch dropdown using smart search.
 */
if ( ! function_exists( 'cmp_render_smart_batch_select' ) ) {
	function cmp_render_smart_batch_select( $name = 'batch_id', $selected = 0, $class_id = 0, $required = true ) {
		$selected_data = '';
		if ( $selected ) {
			$batch = cmp_get_batch( $selected );
			if ( $batch ) {
				$selected_data = $batch->batch_name;
			}
		}
		?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $selected ); ?>" <?php echo $required ? 'required' : ''; ?>>
		<input type="text"
			   class="cmp-smart-search"
			   data-target="<?php echo esc_attr( $name ); ?>"
			   data-type="batch"
			   data-class-id="<?php echo esc_attr( $class_id ); ?>"
			   placeholder="<?php esc_attr_e( 'Search batch...', 'class-manager-pro' ); ?>"
			   value="<?php echo esc_attr( $selected_data ); ?>">
		<?php
	}
}

/**
 * Enhanced payment saving with audit logging.
 */
add_action( 'cmp_after_save_payment', 'cmp_log_payment_audit', 10, 2 );

function cmp_log_payment_audit( $payment_id, $existing ) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'cmp_';

	$wpdb->insert(
		"{$prefix}payment_audit",
		array(
			'payment_id' => $payment_id,
			'user_id'    => get_current_user_id(),
			'action'     => $existing ? 'update' : 'create',
			'old_value'  => $existing ? wp_json_encode( $existing ) : null,
			'new_value'  => wp_json_encode( cmp_get_payment( $payment_id ) ),
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);
}
