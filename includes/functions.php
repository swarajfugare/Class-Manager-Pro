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
add_action( 'admin_post_cmp_manual_import_razorpay', 'cmp_handle_manual_import_razorpay' );
add_action( 'admin_post_cmp_import_razorpay_page_to_batch', 'cmp_handle_import_razorpay_page_to_batch' );
add_action( 'admin_post_cmp_import_batch_sheet_data', 'cmp_handle_import_batch_sheet_data' );
add_action( 'admin_post_cmp_download_batch_import_template', 'cmp_handle_download_batch_import_template' );
add_action( 'admin_post_cmp_reset_plugin_data', 'cmp_handle_reset_plugin_data' );
add_action( 'admin_post_cmp_save_expense', 'cmp_handle_save_expense' );
add_action( 'admin_post_cmp_delete_expense', 'cmp_handle_delete_expense' );
add_action( 'admin_post_cmp_save_sms_settings', 'cmp_handle_save_sms_settings' );
add_action( 'admin_post_cmp_send_fee_reminders', 'cmp_handle_send_fee_reminders' );
add_action( 'admin_post_cmp_save_attendance_settings', 'cmp_handle_save_attendance_settings' );
add_action( 'admin_post_cmp_save_attendance', 'cmp_handle_save_attendance' );
add_action( 'admin_post_cmp_save_automation_settings', 'cmp_handle_save_automation_settings' );
add_action( 'admin_post_cmp_sync_razorpay_payments', 'cmp_handle_sync_razorpay_payments' );
add_action( 'admin_post_cmp_enroll_student_next_course', 'cmp_handle_enroll_student_next_course' );
add_action( 'admin_init', 'cmp_handle_csv_export' );
add_action( 'init', 'cmp_schedule_daily_fee_reminders' );
add_action( 'init', 'cmp_schedule_razorpay_payment_sync' );
add_action( 'cmp_daily_fee_reminders', 'cmp_run_scheduled_fee_reminders' );
add_action( 'cmp_razorpay_payment_sync', 'cmp_run_scheduled_razorpay_payment_sync' );
add_action( 'wp_ajax_cmp_filter_all_data', 'cmp_ajax_filter_all_data' );
add_action( 'wp_ajax_cmp_filter_students', 'cmp_ajax_filter_students' );
add_action( 'wp_ajax_cmp_delete_item', 'cmp_delete_item' );
add_action( 'wp_ajax_cmp_admin_entity_action', 'cmp_ajax_admin_entity_action' );
add_action( 'wp_ajax_cmp_bulk_student_action', 'cmp_ajax_bulk_student_action' );
add_action( 'wp_ajax_cmp_save_attendance_quick', 'cmp_ajax_save_attendance_quick' );

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
 * Stops AJAX execution for non-admin users with a JSON response.
 *
 * @return void
 */
function cmp_require_manage_options_ajax() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_send_json_error(
		array(
			'message' => __( 'You do not have permission to access Class Manager Pro.', 'class-manager-pro' ),
		),
		403
	);
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

	$class = 'notice notice-success';

	if ( 'error' === $type ) {
		$class = 'notice notice-error';
	} elseif ( 'warning' === $type ) {
		$class = 'notice notice-warning';
	}
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
 * Returns the plugin log file path.
 *
 * @return string
 */
function cmp_get_log_file_path() {
	$upload_dir = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) ) {
		return '';
	}

	return trailingslashit( $upload_dir['basedir'] ) . 'cmp-logs.txt';
}

/**
 * Writes a line to the plugin log file.
 *
 * @param string $level Log level.
 * @param string $message Message text.
 * @param array  $context Optional context.
 * @return void
 */
function cmp_log_event( $level, $message, $context = array() ) {
	$path = cmp_get_log_file_path();

	if ( '' === $path ) {
		return;
	}

	$timestamp = current_time( 'mysql' );
	$context   = is_array( $context ) && ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
	$line      = sprintf( "[%s] %s: %s%s\n", $timestamp, strtoupper( sanitize_key( $level ) ), sanitize_text_field( $message ), $context );

	wp_mkdir_p( dirname( $path ) );
	file_put_contents( $path, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

/**
 * Normalizes a list of IDs.
 *
 * @param array $values Raw values.
 * @return array
 */
function cmp_clean_absint_array( $values ) {
	if ( ! is_array( $values ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
}

/**
 * Stores a duplicate lead warning for the current admin user.
 *
 * @param string $message Warning message.
 * @return void
 */
function cmp_set_duplicate_lead_notice( $message ) {
	$user_id = get_current_user_id();

	if ( ! $user_id || '' === trim( (string) $message ) ) {
		return;
	}

	set_transient( 'cmp_duplicate_lead_notice_' . $user_id, sanitize_text_field( $message ), MINUTE_IN_SECONDS * 10 );
}

/**
 * Pops a duplicate lead warning for the current admin user.
 *
 * @return string
 */
function cmp_pop_duplicate_lead_notice() {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '';
	}

	$key     = 'cmp_duplicate_lead_notice_' . $user_id;
	$message = (string) get_transient( $key );

	if ( '' !== $message ) {
		delete_transient( $key );
	}

	return sanitize_text_field( $message );
}

/**
 * Stores an admin audit log entry.
 *
 * @param string $action Action key.
 * @param string $object_type Object type.
 * @param int    $object_id Object ID.
 * @param string $message Optional log message.
 * @return int|WP_Error
 */
function cmp_log_admin_action( $action, $object_type, $object_id = 0, $message = '' ) {
	global $wpdb;

	$result = $wpdb->insert(
		cmp_table( 'admin_logs' ),
		array(
			'user_id'     => get_current_user_id(),
			'action'      => sanitize_key( $action ),
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => absint( $object_id ),
			'message'     => sanitize_textarea_field( $message ),
			'created_at'  => cmp_current_datetime(),
		),
		array( '%d', '%s', '%s', '%d', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_admin_log_failed', __( 'Could not save admin log entry.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Returns allowed dashboard date filters.
 *
 * @return array
 */
function cmp_dashboard_ranges() {
	return array( 'today', 'week', 'month' );
}

/**
 * Returns the selected dashboard range.
 *
 * @param string $range Requested range.
 * @return string
 */
function cmp_get_dashboard_range( $range = '' ) {
	$range = '' !== $range ? $range : cmp_field( $_GET, 'range', 'month' );
	$range = sanitize_key( $range );

	return in_array( $range, cmp_dashboard_ranges(), true ) ? $range : 'month';
}

/**
 * Returns date bounds for a dashboard or sync range.
 *
 * @param string $range Range key.
 * @return array
 */
function cmp_get_date_range_bounds( $range ) {
	$range      = cmp_get_dashboard_range( $range );
	$now        = current_time( 'timestamp' );
	$start_date = current_datetime();
	$end_date   = current_datetime();

	if ( 'today' === $range ) {
		$start_date->setTime( 0, 0, 0 );
		$end_date->setTime( 23, 59, 59 );
	} elseif ( 'week' === $range ) {
		$start_of_week = (int) get_option( 'start_of_week', 1 );
		$current_day   = (int) $start_date->format( 'w' );
		$delta         = $current_day - $start_of_week;

		if ( $delta < 0 ) {
			$delta += 7;
		}

		$start_date->modify( '-' . $delta . ' days' );
		$start_date->setTime( 0, 0, 0 );
		$end_date = clone $start_date;
		$end_date->modify( '+6 days' );
		$end_date->setTime( 23, 59, 59 );
	} else {
		$start_date->setDate( (int) wp_date( 'Y', $now ), (int) wp_date( 'm', $now ), 1 );
		$start_date->setTime( 0, 0, 0 );
		$end_date = clone $start_date;
		$end_date->modify( 'last day of this month' );
		$end_date->setTime( 23, 59, 59 );
	}

	return array(
		'start' => $start_date->format( 'Y-m-d H:i:s' ),
		'end'   => $end_date->format( 'Y-m-d H:i:s' ),
	);
}

/**
 * Returns the current admin page number.
 *
 * @return int
 */
function cmp_get_current_page_number() {
	return max( 1, absint( cmp_field( $_GET, 'paged', 1 ) ) );
}

/**
 * Returns the default records-per-page count.
 *
 * @return int
 */
function cmp_get_default_per_page() {
	return 20;
}

/**
 * Builds pagination data for list screens.
 *
 * @param int $total Total rows.
 * @param int $paged Current page.
 * @param int $per_page Rows per page.
 * @return array
 */
function cmp_get_pagination_data( $total, $paged, $per_page ) {
	$total    = max( 0, absint( $total ) );
	$paged    = max( 1, absint( $paged ) );
	$per_page = max( 1, absint( $per_page ) );

	return array(
		'total'       => $total,
		'paged'       => $paged,
		'per_page'    => $per_page,
		'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		'offset'      => ( $paged - 1 ) * $per_page,
	);
}

/**
 * Renders simple pagination links.
 *
 * @param array $pagination Pagination data.
 * @param array $extra_args Query args to preserve.
 * @return void
 */
function cmp_render_pagination( $pagination, $extra_args = array() ) {
	$total_pages = isset( $pagination['total_pages'] ) ? absint( $pagination['total_pages'] ) : 1;
	$paged       = isset( $pagination['paged'] ) ? absint( $pagination['paged'] ) : 1;

	if ( $total_pages <= 1 ) {
		return;
	}

	$page = sanitize_key( cmp_field( $_GET, 'page' ) );
	?>
	<nav class="cmp-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'class-manager-pro' ); ?>">
		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg(
						array_merge(
							array(
								'page'  => $page,
								'paged' => '%#%',
							),
							$extra_args
						),
						admin_url( 'admin.php' )
					),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => __( '&laquo; Previous', 'class-manager-pro' ),
					'next_text' => __( 'Next &raquo;', 'class-manager-pro' ),
					'type'      => 'plain',
				)
			)
		);
		?>
	</nav>
	<?php
}

/**
 * Inserts an activity log row.
 *
 * @param array $data Activity payload.
 * @return int|WP_Error
 */
function cmp_log_activity( $data ) {
	global $wpdb;

	$result = $wpdb->insert(
		cmp_table( 'activity_logs' ),
		array(
			'student_id'  => absint( cmp_field( $data, 'student_id', 0 ) ),
			'batch_id'    => absint( cmp_field( $data, 'batch_id', 0 ) ),
			'class_id'    => absint( cmp_field( $data, 'class_id', 0 ) ),
			'payment_id'  => absint( cmp_field( $data, 'payment_id', 0 ) ),
			'action'      => sanitize_key( cmp_field( $data, 'action' ) ),
			'message'     => sanitize_textarea_field( cmp_field( $data, 'message' ) ),
			'context'     => wp_json_encode( isset( $data['context'] ) ? $data['context'] : array() ),
			'created_by'  => get_current_user_id(),
			'created_at'  => cmp_current_datetime(),
		),
		array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_activity_log_failed', __( 'Could not save activity log.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Returns activity logs for a batch or student.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_activity_logs( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'batch_id'   => 0,
			'student_id' => 0,
			'limit'      => 20,
		)
	);

	$where  = array();
	$params = array();

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 'l.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( ! empty( $args['student_id'] ) ) {
		$where[]  = 'l.student_id = %d';
		$params[] = absint( $args['student_id'] );
	}

	$sql = 'SELECT l.id, l.student_id, l.batch_id, l.class_id, l.payment_id, l.action, l.message, l.context, l.created_at,
		s.name AS student_name, s.unique_id AS student_unique_id, b.batch_name, c.name AS class_name
		FROM ' . cmp_table( 'activity_logs' ) . ' l
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = l.student_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = l.batch_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = l.class_id';

	if ( ! empty( $where ) ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY l.created_at DESC, l.id DESC';

	if ( ! empty( $args['limit'] ) ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );
	}

	return ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Returns the lifetime value for a student.
 *
 * @param int $student_id Student ID.
 * @return float
 */
function cmp_get_student_ltv( $student_id ) {
	global $wpdb;

	return (float) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) . ' WHERE student_id = %d',
			absint( $student_id )
		)
	);
}

/**
 * Returns a human label for an admin log action.
 *
 * @param string $action Action key.
 * @return string
 */
function cmp_get_admin_log_action_label( $action ) {
	$labels = array(
		'add_class'      => __( 'Add Class', 'class-manager-pro' ),
		'edit_class'     => __( 'Edit Class', 'class-manager-pro' ),
		'delete_class'   => __( 'Delete Class', 'class-manager-pro' ),
		'add_batch'      => __( 'Add Batch', 'class-manager-pro' ),
		'edit_batch'     => __( 'Edit Batch', 'class-manager-pro' ),
		'delete_batch'   => __( 'Delete Batch', 'class-manager-pro' ),
		'add_student'    => __( 'Add Student', 'class-manager-pro' ),
		'edit_student'   => __( 'Edit Student', 'class-manager-pro' ),
		'delete_student' => __( 'Delete Student', 'class-manager-pro' ),
		'add_payment'    => __( 'Payment Added', 'class-manager-pro' ),
	);

	$action = sanitize_key( $action );

	if ( isset( $labels[ $action ] ) ) {
		return $labels[ $action ];
	}

	return ucwords( str_replace( '_', ' ', $action ) );
}

/**
 * Returns recent admin activity logs.
 *
 * @param int $limit Max rows.
 * @return array
 */
function cmp_get_admin_logs( $limit = 10 ) {
	global $wpdb;

	$limit = absint( $limit );
	$sql   = 'SELECT l.id, l.user_id, l.action, l.object_type, l.object_id, l.message, l.created_at,
		u.display_name AS admin_name
		FROM ' . cmp_table( 'admin_logs' ) . ' l
		LEFT JOIN ' . $wpdb->users . ' u ON u.ID = l.user_id
		ORDER BY l.created_at DESC, l.id DESC';

	if ( $limit > 0 ) {
		$sql = $wpdb->prepare( $sql . ' LIMIT %d', $limit );
	}

	return $wpdb->get_results( $sql );
}

/**
 * Stores a teacher activity log entry.
 *
 * @param array $data Teacher log payload.
 * @return int|WP_Error
 */
function cmp_log_teacher_action( $data ) {
	global $wpdb;

	$teacher_user_id = absint( cmp_field( $data, 'teacher_user_id', get_current_user_id() ) );
	$action          = sanitize_key( cmp_field( $data, 'action' ) );

	if ( ! $teacher_user_id || '' === $action ) {
		return new WP_Error( 'cmp_teacher_log_invalid', __( 'Valid teacher log details are required.', 'class-manager-pro' ) );
	}

	$result = $wpdb->insert(
		cmp_table( 'teacher_logs' ),
		array(
			'teacher_user_id' => $teacher_user_id,
			'batch_id'        => absint( cmp_field( $data, 'batch_id', 0 ) ),
			'student_id'      => absint( cmp_field( $data, 'student_id', 0 ) ),
			'action'          => $action,
			'message'         => sanitize_textarea_field( cmp_field( $data, 'message' ) ),
			'created_at'      => cmp_current_datetime(),
		),
		array( '%d', '%d', '%d', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_teacher_log_failed', __( 'Could not save teacher activity log.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Returns a human label for a teacher log action.
 *
 * @param string $action Action key.
 * @return string
 */
function cmp_get_teacher_log_action_label( $action ) {
	$labels = array(
		'batch_viewed'   => __( 'Batch Viewed', 'class-manager-pro' ),
		'student_viewed' => __( 'Student Viewed', 'class-manager-pro' ),
	);

	$action = sanitize_key( $action );

	if ( isset( $labels[ $action ] ) ) {
		return $labels[ $action ];
	}

	return ucwords( str_replace( '_', ' ', $action ) );
}

/**
 * Returns teacher activity logs.
 *
 * @param int $teacher_user_id Optional teacher user ID.
 * @param int $limit Max rows. Pass 0 for all rows.
 * @return array
 */
function cmp_get_teacher_logs( $teacher_user_id = 0, $limit = 100 ) {
	global $wpdb;

	$teacher_user_id = absint( $teacher_user_id );
	$limit           = absint( $limit );
	$where           = array();
	$params          = array();
	$sql             = 'SELECT l.id, l.teacher_user_id, l.batch_id, l.student_id, l.action, l.message, l.created_at,
		u.display_name AS teacher_name,
		b.batch_name,
		s.name AS student_name,
		s.unique_id AS student_unique_id
		FROM ' . cmp_table( 'teacher_logs' ) . ' l
		LEFT JOIN ' . $wpdb->users . ' u ON u.ID = l.teacher_user_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = l.batch_id
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = l.student_id';

	if ( $teacher_user_id ) {
		$where[]  = 'l.teacher_user_id = %d';
		$params[] = $teacher_user_id;
	}

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY l.created_at DESC, l.id DESC';

	if ( $limit > 0 ) {
		$sql .= ' LIMIT %d';
		$params[] = $limit;
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Returns students with pending fees for follow-up.
 *
 * @param int $limit Max rows.
 * @return array
 */
function cmp_get_students_with_pending_fees( $limit = 10 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT s.id, s.unique_id, s.name, s.phone, s.email, s.total_fee, s.paid_fee, c.name AS class_name, b.batch_name, b.fee_due_date
			FROM ' . cmp_table( 'students' ) . ' s
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
			WHERE s.total_fee > s.paid_fee
			ORDER BY ( s.total_fee - s.paid_fee ) DESC, s.created_at DESC
			LIMIT %d',
			$limit
		)
	);
}

/**
 * Returns class demand values for analytics.
 *
 * @param int $limit Max rows.
 * @return array
 */
function cmp_get_course_demand_heatmap( $limit = 10 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT COALESCE(c.name, %s) AS class_name, COUNT(s.id) AS total_students
			FROM ' . cmp_table( 'classes' ) . ' c
			LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.class_id = c.id
			GROUP BY c.id, c.name
			HAVING COUNT(s.id) > 0
			ORDER BY total_students DESC, c.name ASC
			LIMIT %d',
			__( 'Unassigned', 'class-manager-pro' ),
			$limit
		)
	);

	$labels = array();
	$values = array();

	foreach ( $rows as $row ) {
		$labels[] = $row->class_name;
		$values[] = (int) $row->total_students;
	}

	return array(
		'labels' => $labels,
		'values' => $values,
	);
}

/**
 * Returns a payment status tag for a student.
 *
 * @param object $student Student row.
 * @return array
 */
function cmp_get_student_payment_status( $student ) {
	$total_fee = isset( $student->total_fee ) ? (float) $student->total_fee : 0;
	$paid_fee  = isset( $student->paid_fee ) ? (float) $student->paid_fee : 0;

	if ( $total_fee <= 0 || $paid_fee >= $total_fee ) {
		return array(
			'key'   => 'paid',
			'label' => __( 'Paid', 'class-manager-pro' ),
		);
	}

	if ( $paid_fee > 0 ) {
		return array(
			'key'   => 'partial',
			'label' => __( 'Partial', 'class-manager-pro' ),
		);
	}

	return array(
		'key'   => 'pending',
		'label' => __( 'Pending', 'class-manager-pro' ),
	);
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
 * Allowed expense categories.
 *
 * @return array
 */
function cmp_expense_categories() {
	return array( 'teacher_payment', 'meta_ads', 'ad_material', 'other' );
}

/**
 * Human labels for expense categories.
 *
 * @return array
 */
function cmp_expense_category_labels() {
	return array(
		'teacher_payment' => __( 'Teacher Payment', 'class-manager-pro' ),
		'meta_ads'        => __( 'Meta Ads', 'class-manager-pro' ),
		'ad_material'     => __( 'Ad Material', 'class-manager-pro' ),
		'other'           => __( 'Other', 'class-manager-pro' ),
	);
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
 * Returns WordPress users available for teacher assignment.
 *
 * @param int $include_user_id Optional selected legacy user ID.
 * @return array
 */
function cmp_get_teacher_users( $include_user_id = 0 ) {
	return cmp_get_tutor_instructors( $include_user_id );
}

/**
 * Formats a teacher user label.
 *
 * @param int $user_id User ID.
 * @return string
 */
function cmp_get_teacher_label( $user_id ) {
	$user = $user_id ? get_userdata( (int) $user_id ) : null;

	if ( ! $user ) {
		return __( 'Not assigned', 'class-manager-pro' );
	}

	return trim( $user->display_name . ( $user->user_email ? ' (' . $user->user_email . ')' : '' ) );
}

/**
 * Returns whether the current teacher has assigned batches.
 *
 * @return bool
 */
function cmp_current_user_has_teacher_batches() {
	global $wpdb;

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) . ' WHERE teacher_user_id = %d',
			$user_id
		)
	);
}

/**
 * Checks teacher/admin access to a batch.
 *
 * @param int $batch_id Batch ID.
 * @return bool
 */
function cmp_current_user_can_manage_batch_attendance( $batch_id ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	$batch = cmp_get_batch( $batch_id );

	return $batch && get_current_user_id() && (int) $batch->teacher_user_id === get_current_user_id();
}

/**
 * Gets batches visible in the teacher console.
 *
 * @return array
 */
function cmp_get_current_teacher_batches() {
	global $wpdb;

	if ( current_user_can( 'manage_options' ) ) {
		return cmp_get_batches();
	}

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return array();
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee
			FROM ' . cmp_table( 'batches' ) . ' b
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			WHERE b.teacher_user_id = %d
			ORDER BY b.created_at DESC, b.id DESC',
			$user_id
		)
	);
}

/**
 * Returns whether a batch can accept another student.
 *
 * @param int $batch_id Batch ID.
 * @param int $exclude_student_id Optional student ID to exclude from the count.
 * @return true|WP_Error
 */
function cmp_validate_batch_capacity( $batch_id, $exclude_student_id = 0 ) {
	global $wpdb;

	$batch = cmp_get_batch( $batch_id );

	if ( ! $batch ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Batch not found.', 'class-manager-pro' ) );
	}

	if ( empty( $batch->intake_limit ) ) {
		return true;
	}

	$sql    = 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d';
	$params = array( absint( $batch_id ) );

	if ( $exclude_student_id ) {
		$sql     .= ' AND id <> %d';
		$params[] = absint( $exclude_student_id );
	}

	$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

	if ( $count >= (int) $batch->intake_limit ) {
		return new WP_Error( 'cmp_batch_full', __( 'This batch has reached its intake limit.', 'class-manager-pro' ) );
	}

	return true;
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

	return $wpdb->get_results( 'SELECT id, name, description, total_fee, next_course_id, created_at FROM ' . cmp_table( 'classes' ) . ' ORDER BY name ASC' );
}

/**
 * Fetches a single class.
 *
 * @param int $id Class ID.
 * @return object|null
 */
function cmp_get_class( $id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id, name, description, total_fee, next_course_id, created_at FROM ' . cmp_table( 'classes' ) . ' WHERE id = %d',
			absint( $id )
		)
	);
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
			'name'           => $name,
			'description'    => sanitize_textarea_field( cmp_field( $data, 'description' ) ),
			'total_fee'      => cmp_money_value( cmp_field( $data, 'total_fee', 0 ) ),
			'next_course_id' => absint( cmp_field( $data, 'next_course_id', 0 ) ),
			'created_at'     => cmp_current_datetime(),
		),
		array( '%s', '%s', '%f', '%d', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not save class.', 'class-manager-pro' ) );
	}

	$class_id = (int) $wpdb->insert_id;

	cmp_log_admin_action(
		'add_class',
		'class',
		$class_id,
		sprintf(
			/* translators: %s: class name */
			__( 'Class "%s" added.', 'class-manager-pro' ),
			$name
		)
	);

	return $class_id;
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
			'name'           => $name,
			'description'    => sanitize_textarea_field( cmp_field( $data, 'description' ) ),
			'total_fee'      => cmp_money_value( cmp_field( $data, 'total_fee', 0 ) ),
			'next_course_id' => absint( cmp_field( $data, 'next_course_id', 0 ) ),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%f', '%d' ),
		array( '%d' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not update class.', 'class-manager-pro' ) );
	}

	cmp_log_admin_action(
		'edit_class',
		'class',
		$id,
		sprintf(
			/* translators: %s: class name */
			__( 'Class "%s" updated.', 'class-manager-pro' ),
			$name
		)
	);

	return true;
}

/**
 * Returns whether a database table exists.
 *
 * @param string $table_name Table name.
 * @return bool
 */
function cmp_table_exists( $table_name ) {
	global $wpdb;

	$table_name = sanitize_text_field( $table_name );

	if ( '' === $table_name ) {
		return false;
	}

	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

	return is_string( $found ) && $found === $table_name;
}

/**
 * Deletes all records connected to a student.
 *
 * @param int $student_id Student ID.
 * @return true|WP_Error
 */
function cmp_delete_student_records( $student_id ) {
	global $wpdb;

	$student_id = absint( $student_id );

	if ( ! $student_id ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Invalid student.', 'class-manager-pro' ) );
	}

	$wpdb->delete( cmp_table( 'activity_logs' ), array( 'student_id' => $student_id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'payments' ), array( 'student_id' => $student_id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'attendance' ), array( 'student_id' => $student_id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'reminders' ), array( 'student_id' => $student_id ), array( '%d' ) );

	if ( cmp_table_exists( cmp_table( 'teacher_logs' ) ) ) {
		$wpdb->delete( cmp_table( 'teacher_logs' ), array( 'student_id' => $student_id ), array( '%d' ) );
	}

	$deleted = $wpdb->delete( cmp_table( 'students' ), array( 'id' => $student_id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not delete student.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Deletes all records connected to a batch, including linked students.
 *
 * @param int $batch_id Batch ID.
 * @return true|WP_Error
 */
function cmp_delete_batch_records( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );

	if ( ! $batch_id ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Invalid batch.', 'class-manager-pro' ) );
	}

	$student_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT id FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d',
			$batch_id
		)
	);

	foreach ( $student_ids as $student_id ) {
		$result = cmp_delete_student_records( $student_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	$wpdb->delete( cmp_table( 'attendance' ), array( 'batch_id' => $batch_id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'reminders' ), array( 'batch_id' => $batch_id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'expenses' ), array( 'batch_id' => $batch_id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'activity_logs' ), array( 'batch_id' => $batch_id ), array( '%d' ) );

	if ( cmp_table_exists( cmp_table( 'teacher_logs' ) ) ) {
		$wpdb->delete( cmp_table( 'teacher_logs' ), array( 'batch_id' => $batch_id ), array( '%d' ) );
	}

	$deleted = $wpdb->delete( cmp_table( 'batches' ), array( 'id' => $batch_id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not delete batch.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Deletes a class and all related batches, students, and linked records.
 *
 * @param int $id Class ID.
 * @return true|WP_Error
 */
function cmp_delete_class( $id ) {
	global $wpdb;

	$id    = absint( $id );
	$class = cmp_get_class( $id );

	if ( ! $id ) {
		return new WP_Error( 'cmp_invalid_class', __( 'Invalid class.', 'class-manager-pro' ) );
	}

	$batch_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT id FROM ' . cmp_table( 'batches' ) . ' WHERE class_id = %d',
			$id
		)
	);

	foreach ( $batch_ids as $batch_id ) {
		$result = cmp_delete_batch_records( $batch_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	$orphan_student_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT id FROM ' . cmp_table( 'students' ) . ' WHERE class_id = %d',
			$id
		)
	);

	foreach ( $orphan_student_ids as $student_id ) {
		$result = cmp_delete_student_records( $student_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	$wpdb->delete( cmp_table( 'expenses' ), array( 'class_id' => $id ), array( '%d' ) );
	$wpdb->delete( cmp_table( 'activity_logs' ), array( 'class_id' => $id ), array( '%d' ) );
	$deleted = $wpdb->delete( cmp_table( 'classes' ), array( 'id' => $id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not delete class.', 'class-manager-pro' ) );
	}

	cmp_log_admin_action(
		'delete_class',
		'class',
		$id,
		sprintf(
			/* translators: %s: class name */
			__( 'Class "%s" deleted.', 'class-manager-pro' ),
			$class && ! empty( $class->name ) ? $class->name : __( 'Unknown', 'class-manager-pro' )
		)
	);

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

		if ( preg_match_all( '/(?:plink|pay|order|page|ppage)_[A-Za-z0-9_]+/', $candidate, $matches ) ) {
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

	$payment_revenue = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) );
	$manual_income   = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(manual_income), 0) FROM ' . cmp_table( 'batches' ) );
	$total_expense   = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'expenses' ) );

	return array(
		'total_batches'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) ),
		'active_batches' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) . ' WHERE status = %s', 'active' ) ),
		'total_students' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) ),
		'total_revenue'  => $payment_revenue + $manual_income,
		'total_expense'  => $total_expense,
		'net_income'     => $payment_revenue + $manual_income - $total_expense,
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
			COALESCE(st.total_fee, 0) AS total_fee,
			COALESCE(st.paid_fee, 0) AS paid_fee,
			(COALESCE(pay.revenue, 0) + COALESCE(b.manual_income, 0)) AS revenue,
			COALESCE(exp.total_expense, 0) AS total_expense,
			COALESCE(exp.teacher_payment, 0) AS teacher_payment,
			COALESCE(exp.ads_spend, 0) AS ads_spend,
			CASE
				WHEN COALESCE(st.total_fee, 0) > 0 THEN ROUND((COALESCE(st.paid_fee, 0) / st.total_fee) * 100, 2)
				ELSE 0
			END AS completion_percentage,
			((COALESCE(pay.revenue, 0) + COALESCE(b.manual_income, 0)) - COALESCE(exp.total_expense, 0)) AS net_income
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		LEFT JOIN (
			SELECT batch_id,
				COUNT(*) AS student_count,
				SUM(total_fee) AS total_fee,
				SUM(paid_fee) AS paid_fee,
				SUM(GREATEST(total_fee - paid_fee, 0)) AS pending_fee
			FROM ' . cmp_table( 'students' ) . '
			GROUP BY batch_id
		) st ON st.batch_id = b.id
		LEFT JOIN (
			SELECT s.batch_id, SUM(p.amount) AS revenue
			FROM ' . cmp_table( 'payments' ) . ' p
			INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			GROUP BY s.batch_id
		) pay ON pay.batch_id = b.id
		LEFT JOIN (
			SELECT batch_id,
				SUM(amount) AS total_expense,
				SUM(CASE WHEN category = \'teacher_payment\' THEN amount ELSE 0 END) AS teacher_payment,
				SUM(CASE WHEN category IN (\'meta_ads\', \'ad_material\') THEN amount ELSE 0 END) AS ads_spend
			FROM ' . cmp_table( 'expenses' ) . '
			GROUP BY batch_id
		) exp ON exp.batch_id = b.id
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
	$batch    = cmp_get_batch( $batch_id );
	$fees     = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(total_fee), 0) AS total_fee, COALESCE(SUM(paid_fee), 0) AS paid_fee
			FROM ' . cmp_table( 'students' ) . '
			WHERE batch_id = %d',
			$batch_id
		)
	);
	$revenue  = (float) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(p.amount), 0)
			FROM ' . cmp_table( 'payments' ) . ' p
			INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			WHERE s.batch_id = %d',
			$batch_id
		)
	);
	$revenue += $batch ? (float) $batch->manual_income : 0;
	$expenses = cmp_get_batch_expense_totals( $batch_id );

	return array(
		'student_count'          => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d', $batch_id ) ),
		'revenue'                => $revenue,
		'pending_fee'            => (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(GREATEST(total_fee - paid_fee, 0)), 0) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d', $batch_id ) ),
		'total_fee'              => $fees ? (float) $fees->total_fee : 0,
		'paid_fee'               => $fees ? (float) $fees->paid_fee : 0,
		'completion_percentage'  => $fees && (float) $fees->total_fee > 0 ? round( ( (float) $fees->paid_fee / (float) $fees->total_fee ) * 100, 2 ) : 0,
		'expense'                => (float) $expenses['total_expense'],
		'teacher_pay'            => (float) $expenses['teacher_payment'],
		'ads_spend'              => (float) $expenses['ads_spend'],
		'net_income'             => $revenue - (float) $expenses['total_expense'],
	);
}

/**
 * Checks whether a batch name already exists inside a class.
 *
 * @param string $batch_name Batch name.
 * @param int    $class_id Class ID.
 * @param int    $exclude_id Optional batch ID to exclude.
 * @return true|WP_Error
 */
function cmp_validate_unique_batch_name( $batch_name, $class_id, $exclude_id = 0 ) {
	global $wpdb;

	$batch_name = sanitize_text_field( $batch_name );
	$class_id   = absint( $class_id );
	$exclude_id = absint( $exclude_id );

	if ( '' === $batch_name || ! $class_id ) {
		return true;
	}

	$sql    = 'SELECT id FROM ' . cmp_table( 'batches' ) . ' WHERE batch_name = %s AND class_id = %d';
	$params = array( $batch_name, $class_id );

	if ( $exclude_id ) {
		$sql     .= ' AND id != %d';
		$params[] = $exclude_id;
	}

	$duplicate_id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

	if ( $duplicate_id ) {
		return new WP_Error( 'cmp_duplicate_batch_name', __( 'Batch with this name already exists in this class', 'class-manager-pro' ) );
	}

	return true;
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
	$course_id        = absint( cmp_field( $data, 'course_id', 0 ) );
	$start_date       = sanitize_text_field( cmp_field( $data, 'start_date' ) );
	$fee_due_date     = sanitize_text_field( cmp_field( $data, 'fee_due_date' ) );
	$start_date       = '' === $start_date ? null : $start_date;
	$fee_due_date     = '' === $fee_due_date ? null : $fee_due_date;
	$razorpay_link    = esc_url_raw( cmp_field( $data, 'razorpay_link' ) );
	$razorpay_page_id = sanitize_text_field( cmp_field( $data, 'razorpay_page_id' ) );
	$is_free          = ! empty( $data['is_free'] ) ? 1 : 0;
	$batch_fee        = $is_free ? 0 : cmp_money_value( cmp_field( $data, 'batch_fee', 0 ) );
	$teacher_user_id  = absint( cmp_field( $data, 'teacher_user_id', 0 ) );
	$intake_limit     = absint( cmp_field( $data, 'intake_limit', 0 ) );
	$class_days       = sanitize_text_field( cmp_field( $data, 'class_days' ) );
	$manual_income    = cmp_money_value( cmp_field( $data, 'manual_income', 0 ) );

	if ( ! $class_id || ! cmp_get_class( $class_id ) || '' === $batch_name ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Valid class and batch name are required.', 'class-manager-pro' ) );
	}

	$duplicate_check = cmp_validate_unique_batch_name( $batch_name, $class_id );

	if ( is_wp_error( $duplicate_check ) ) {
		return $duplicate_check;
	}

	$result = $wpdb->insert(
		cmp_table( 'batches' ),
		array(
			'class_id'         => $class_id,
			'batch_name'       => $batch_name,
			'course_id'        => $course_id,
			'start_date'       => $start_date,
			'fee_due_date'     => $fee_due_date,
			'status'           => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_batch_statuses(), 'active' ),
			'public_token'     => cmp_generate_batch_public_token(),
			'razorpay_link'    => $razorpay_link,
			'batch_fee'        => $batch_fee,
			'is_free'          => $is_free,
			'teacher_user_id'  => $teacher_user_id,
			'intake_limit'     => $intake_limit,
			'class_days'       => $class_days,
			'manual_income'    => $manual_income,
			'razorpay_page_id' => $razorpay_page_id,
			'created_at'       => cmp_current_datetime(),
		),
		array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%f', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not save batch.', 'class-manager-pro' ) );
	}

	$batch_id = (int) $wpdb->insert_id;

	cmp_log_activity(
		array(
			'batch_id' => $batch_id,
			'class_id' => $class_id,
			'action'   => 'batch_created',
			'message'  => sprintf(
				/* translators: %s: batch name */
				__( 'Batch "%s" created.', 'class-manager-pro' ),
				$batch_name
			),
			'context'  => array(
				'course_id' => $course_id,
			),
		)
	);

	cmp_log_admin_action(
		'add_batch',
		'batch',
		$batch_id,
		sprintf(
			/* translators: %s: batch name */
			__( 'Batch "%s" added.', 'class-manager-pro' ),
			$batch_name
		)
	);

	return $batch_id;
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
	$batch            = cmp_get_batch( $id );
	$class_id         = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_name       = sanitize_text_field( cmp_field( $data, 'batch_name' ) );
	$course_id        = absint( cmp_field( $data, 'course_id', $batch && isset( $batch->course_id ) ? $batch->course_id : 0 ) );
	$start_date       = sanitize_text_field( cmp_field( $data, 'start_date' ) );
	$fee_due_date     = sanitize_text_field( cmp_field( $data, 'fee_due_date' ) );
	$start_date       = '' === $start_date ? null : $start_date;
	$fee_due_date     = '' === $fee_due_date ? null : $fee_due_date;
	$token            = $batch && ! empty( $batch->public_token ) ? $batch->public_token : cmp_generate_batch_public_token();
	$razorpay_link    = esc_url_raw( cmp_field( $data, 'razorpay_link' ) );
	$razorpay_page_id = sanitize_text_field( cmp_field( $data, 'razorpay_page_id' ) );
	$is_free          = ! empty( $data['is_free'] ) ? 1 : 0;
	$batch_fee        = $is_free ? 0 : cmp_money_value( cmp_field( $data, 'batch_fee', 0 ) );
	$teacher_user_id  = absint( cmp_field( $data, 'teacher_user_id', 0 ) );
	$intake_limit     = absint( cmp_field( $data, 'intake_limit', 0 ) );
	$class_days       = sanitize_text_field( cmp_field( $data, 'class_days' ) );
	$manual_income    = cmp_money_value( cmp_field( $data, 'manual_income', 0 ) );

	if ( ! $id || ! $class_id || ! cmp_get_class( $class_id ) || '' === $batch_name ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Valid batch details are required.', 'class-manager-pro' ) );
	}

	$duplicate_check = cmp_validate_unique_batch_name( $batch_name, $class_id, $id );

	if ( is_wp_error( $duplicate_check ) ) {
		return $duplicate_check;
	}

	$result = $wpdb->update(
		cmp_table( 'batches' ),
		array(
			'class_id'         => $class_id,
			'batch_name'       => $batch_name,
			'course_id'        => $course_id,
			'start_date'       => $start_date,
			'fee_due_date'     => $fee_due_date,
			'status'           => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_batch_statuses(), 'active' ),
			'public_token'     => $token,
			'razorpay_link'    => $razorpay_link,
			'batch_fee'        => $batch_fee,
			'is_free'          => $is_free,
			'teacher_user_id'  => $teacher_user_id,
			'intake_limit'     => $intake_limit,
			'class_days'       => $class_days,
			'manual_income'    => $manual_income,
			'razorpay_page_id' => $razorpay_page_id,
		),
		array( 'id' => $id ),
		array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%f', '%s' ),
		array( '%d' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not update batch.', 'class-manager-pro' ) );
	}

	cmp_log_activity(
		array(
			'batch_id' => $id,
			'class_id' => $class_id,
			'action'   => 'batch_updated',
			'message'  => sprintf(
				/* translators: %s: batch name */
				__( 'Batch "%s" updated.', 'class-manager-pro' ),
				$batch_name
			),
			'context'  => array(
				'course_id' => $course_id,
			),
		)
	);

	cmp_log_admin_action(
		'edit_batch',
		'batch',
		$id,
		sprintf(
			/* translators: %s: batch name */
			__( 'Batch "%s" updated.', 'class-manager-pro' ),
			$batch_name
		)
	);

	return true;
}

/**
 * Deletes a batch and its related student data.
 *
 * @param int $id Batch ID.
 * @return true|WP_Error
 */
function cmp_delete_batch( $id ) {
	$id    = absint( $id );
	$batch = cmp_get_batch( $id );

	if ( ! $id ) {
		return new WP_Error( 'cmp_invalid_batch', __( 'Invalid batch.', 'class-manager-pro' ) );
	}

	$result = cmp_delete_batch_records( $id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	cmp_log_admin_action(
		'delete_batch',
		'batch',
		$id,
		sprintf(
			/* translators: %s: batch name */
			__( 'Batch "%s" deleted.', 'class-manager-pro' ),
			$batch && ! empty( $batch->batch_name ) ? $batch->batch_name : __( 'Unknown', 'class-manager-pro' )
		)
	);

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

		if ( function_exists( 'cmp_store_public_duplicate_warning' ) && ! empty( $batch->public_token ) ) {
			cmp_store_public_duplicate_warning( $batch->public_token, __( 'Student already exists. Existing record was updated.', 'class-manager-pro' ) );
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
			'SELECT s.*, c.name AS class_name, c.next_course_id AS class_next_course_id, b.batch_name, b.batch_fee, b.fee_due_date, b.razorpay_link, b.course_id, b.teacher_user_id
			FROM ' . cmp_table( 'students' ) . ' s
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
			WHERE s.id = %d',
			absint( $id )
		)
	);
}

/**
 * Builds the shared student query conditions.
 *
 * @param array $args Filters.
 * @return array
 */
function cmp_get_student_query_parts( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'   => '',
			'class_id' => 0,
			'batch_id' => 0,
			'status'   => '',
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

	if ( ! empty( $args['class_id'] ) ) {
		$where[]  = 's.class_id = %d';
		$params[] = absint( $args['class_id'] );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 's.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( '' !== $args['status'] ) {
		$where[]  = 's.status = %s';
		$params[] = cmp_clean_enum( $args['status'], cmp_student_statuses(), 'active' );
	}

	return array(
		'where'  => $where,
		'params' => $params,
	);
}

/**
 * Returns a student count for list pagination.
 *
 * @param array $args Filters.
 * @return int
 */
function cmp_get_students_count( $args = array() ) {
	global $wpdb;

	$query_parts = cmp_get_student_query_parts( $args );
	$sql         = 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' s';

	if ( ! empty( $query_parts['where'] ) ) {
		$sql .= ' WHERE ' . implode( ' AND ', $query_parts['where'] );
	}

	return (int) ( ! empty( $query_parts['params'] ) ? $wpdb->get_var( $wpdb->prepare( $sql, $query_parts['params'] ) ) : $wpdb->get_var( $sql ) );
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
			'offset'   => 0,
		)
	);

	$query_parts = cmp_get_student_query_parts( $args );
	$where       = $query_parts['where'];
	$params      = $query_parts['params'];

	$sql = 'SELECT s.*, c.name AS class_name, c.next_course_id AS class_next_course_id, b.batch_name, b.batch_fee, b.fee_due_date, b.razorpay_link, (s.total_fee - s.paid_fee) AS remaining_fee
		FROM ' . cmp_table( 'students' ) . ' s
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id';

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY s.created_at DESC, s.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
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

	$existing = cmp_find_student_in_batch_by_identity( $batch_id, $name, $email, $phone );

	if ( ! $existing ) {
		$existing = cmp_find_student_by_contact( $phone, $email );
	}

	if ( $existing ) {
		$update_result = cmp_update_student(
			(int) $existing->id,
			array(
				'name'      => $name,
				'phone'     => $phone,
				'email'     => $email,
				'class_id'  => $class_id,
				'batch_id'  => $batch_id,
				'total_fee' => cmp_field( $data, 'total_fee', '' ),
				'paid_fee'  => cmp_field( $data, 'paid_fee', (float) $existing->paid_fee ),
				'status'    => cmp_field( $data, 'status', $existing->status ),
				'notes'     => cmp_field( $data, 'notes', $existing->notes ),
			)
		);

		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		cmp_set_duplicate_lead_notice( __( 'Student already exists. Existing record was updated.', 'class-manager-pro' ) );

		return (int) $existing->id;
	}

	$capacity = cmp_validate_batch_capacity( $batch_id );

	if ( is_wp_error( $capacity ) ) {
		return $capacity;
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
		cmp_log_event( 'error', 'Student insert failed.', array( 'class_id' => $class_id, 'batch_id' => $batch_id, 'phone' => $phone, 'email' => $email ) );
		return new WP_Error( 'cmp_db_error', __( 'Could not save student.', 'class-manager-pro' ) );
	}

	$student_id = (int) $wpdb->insert_id;

	cmp_log_activity(
		array(
			'student_id' => $student_id,
			'batch_id'   => $batch_id,
			'class_id'   => $class_id,
			'action'     => 'student_created',
			'message'    => sprintf(
				/* translators: %s: student name */
				__( 'Student "%s" created.', 'class-manager-pro' ),
				$name
			),
		)
	);
	cmp_log_activity(
		array(
			'student_id' => $student_id,
			'batch_id'   => $batch_id,
			'class_id'   => $class_id,
			'action'     => 'batch_assigned',
			'message'    => sprintf(
				/* translators: 1: student name 2: batch name */
				__( '%1$s assigned to batch "%2$s".', 'class-manager-pro' ),
				$name,
				$batch->batch_name
			),
		)
	);
	cmp_log_admin_action(
		'add_student',
		'student',
		$student_id,
		sprintf(
			/* translators: %s: student name */
			__( 'Student "%s" added.', 'class-manager-pro' ),
			$name
		)
	);
	cmp_sync_student_tutor_enrollment( $student_id );

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
	$student  = cmp_get_student( $id );
	$name     = sanitize_text_field( cmp_field( $data, 'name' ) );
	$phone    = sanitize_text_field( cmp_field( $data, 'phone' ) );
	$email    = sanitize_email( cmp_field( $data, 'email' ) );
	$class_id = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_id = absint( cmp_field( $data, 'batch_id', 0 ) );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $id || '' === $name || '' === $phone || ! cmp_get_class( $class_id ) || ! $batch || (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Valid student details are required.', 'class-manager-pro' ) );
	}

	$capacity = cmp_validate_batch_capacity( $batch_id, $id );

	if ( is_wp_error( $capacity ) ) {
		return $capacity;
	}

	$result = $wpdb->update(
		cmp_table( 'students' ),
		array(
			'name'      => $name,
			'phone'     => $phone,
			'email'     => $email,
			'class_id'  => $class_id,
			'batch_id'  => $batch_id,
			'total_fee' => '' !== (string) cmp_field( $data, 'total_fee', '' ) ? cmp_money_value( cmp_field( $data, 'total_fee', 0 ) ) : ( $student ? (float) $student->total_fee : 0 ),
			'paid_fee'  => '' !== (string) cmp_field( $data, 'paid_fee', '' ) ? cmp_money_value( cmp_field( $data, 'paid_fee', 0 ) ) : ( $student ? (float) $student->paid_fee : 0 ),
			'status'    => cmp_clean_enum( cmp_field( $data, 'status', 'active' ), cmp_student_statuses(), 'active' ),
			'notes'     => sanitize_textarea_field( '' !== (string) cmp_field( $data, 'notes', '' ) ? cmp_field( $data, 'notes' ) : ( $student ? $student->notes : '' ) ),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s' ),
		array( '%d' )
	);

	if ( false === $result ) {
		cmp_log_event( 'error', 'Student update failed.', array( 'student_id' => $id, 'class_id' => $class_id, 'batch_id' => $batch_id ) );
		return new WP_Error( 'cmp_db_error', __( 'Could not update student.', 'class-manager-pro' ) );
	}

	if ( $student && ( (int) $student->batch_id !== $batch_id || (int) $student->class_id !== $class_id ) ) {
		cmp_log_activity(
			array(
				'student_id' => $id,
				'batch_id'   => $batch_id,
				'class_id'   => $class_id,
				'action'     => 'batch_assigned',
				'message'    => sprintf(
					/* translators: 1: student name 2: batch name */
					__( '%1$s assigned to batch "%2$s".', 'class-manager-pro' ),
					$name,
					$batch->batch_name
				),
				'context'    => array(
					'previous_batch_id' => (int) $student->batch_id,
					'previous_class_id' => (int) $student->class_id,
				),
			)
		);
	}

	cmp_log_activity(
		array(
			'student_id' => $id,
			'batch_id'   => $batch_id,
			'class_id'   => $class_id,
			'action'     => 'student_updated',
			'message'    => sprintf(
				/* translators: %s: student name */
				__( 'Student "%s" updated.', 'class-manager-pro' ),
				$name
			),
		)
	);
	cmp_log_admin_action(
		'edit_student',
		'student',
		$id,
		sprintf(
			/* translators: %s: student name */
			__( 'Student "%s" updated.', 'class-manager-pro' ),
			$name
		)
	);
	cmp_sync_student_tutor_enrollment( $id );

	return true;
}

/**
 * Deletes a student and their linked records.
 *
 * @param int $id Student ID.
 * @return true|WP_Error
 */
function cmp_delete_student( $id ) {
	$id      = absint( $id );
	$student = cmp_get_student( $id );

	if ( ! $id ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Invalid student.', 'class-manager-pro' ) );
	}

	cmp_log_admin_action(
		'delete_student',
		'student',
		$id,
		sprintf(
			/* translators: %s: student name */
			__( 'Student "%s" deleted.', 'class-manager-pro' ),
			$student && ! empty( $student->name ) ? $student->name : __( 'Unknown', 'class-manager-pro' )
		)
	);

	return cmp_delete_student_records( $id );
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
	$student        = cmp_get_student( $student_id );

	if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $payment_date ) ) {
		$payment_date .= ':00';
	}

	if ( ! $student_id || ! $student || $amount <= 0 ) {
		cmp_log_event( 'error', 'Payment validation failed.', array( 'student_id' => $student_id, 'amount' => $amount ) );
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
		cmp_log_event( 'error', 'Payment insert failed.', array( 'student_id' => $student_id, 'amount' => $amount, 'transaction_id' => $transaction_id ) );
		return new WP_Error( 'cmp_db_error', __( 'Could not save payment.', 'class-manager-pro' ) );
	}

	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . cmp_table( 'students' ) . ' SET paid_fee = paid_fee + %f WHERE id = %d',
			$amount,
			$student_id
		)
	);

	$payment_id = (int) $wpdb->insert_id;

	cmp_log_activity(
		array(
			'student_id' => $student_id,
			'batch_id'   => (int) $student->batch_id,
			'class_id'   => (int) $student->class_id,
			'payment_id' => $payment_id,
			'action'     => 'payment_added',
			'message'    => sprintf(
				/* translators: 1: amount 2: payment mode */
				__( 'Payment of %1$s added via %2$s.', 'class-manager-pro' ),
				cmp_format_money( $amount ),
				ucfirst( $payment_mode )
			),
			'context'    => array(
				'transaction_id' => $transaction_id,
			),
		)
	);
	cmp_log_admin_action(
		'add_payment',
		'payment',
		$payment_id,
		sprintf(
			/* translators: 1: amount 2: student name */
			__( 'Payment of %1$s added for %2$s.', 'class-manager-pro' ),
			cmp_format_money( $amount ),
			$student->name
		)
	);

	return $payment_id;
}

/**
 * Builds payment query conditions.
 *
 * @param array $args Filters.
 * @return array
 */
function cmp_get_payment_query_parts( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'payment_mode' => '',
			'student_id'   => 0,
			'batch_id'     => 0,
		)
	);

	$where  = array();
	$params = array();

	if ( '' !== $args['payment_mode'] ) {
		$where[]  = 'p.payment_mode = %s';
		$params[] = cmp_clean_enum( $args['payment_mode'], cmp_payment_modes(), 'manual' );
	}

	if ( ! empty( $args['student_id'] ) ) {
		$where[]  = 'p.student_id = %d';
		$params[] = absint( $args['student_id'] );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 's.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	return array(
		'where'  => $where,
		'params' => $params,
	);
}

/**
 * Returns a payment count for list pagination.
 *
 * @param array $args Filters.
 * @return int
 */
function cmp_get_payments_count( $args = array() ) {
	global $wpdb;

	$query_parts = cmp_get_payment_query_parts( $args );
	$sql         = 'SELECT COUNT(*) FROM ' . cmp_table( 'payments' ) . ' p LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id';

	if ( ! empty( $query_parts['where'] ) ) {
		$sql .= ' WHERE ' . implode( ' AND ', $query_parts['where'] );
	}

	return (int) ( ! empty( $query_parts['params'] ) ? $wpdb->get_var( $wpdb->prepare( $sql, $query_parts['params'] ) ) : $wpdb->get_var( $sql ) );
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
			'offset'       => 0,
		)
	);

	$query_parts = cmp_get_payment_query_parts( $args );
	$where       = $query_parts['where'];
	$params      = $query_parts['params'];

	$sql = 'SELECT p.*, s.name AS student_name, s.phone AS student_phone, s.unique_id AS student_unique_id
		FROM ' . cmp_table( 'payments' ) . ' p
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id';

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY p.payment_date DESC, p.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * Inserts a batch expense.
 *
 * @param array $data Expense data.
 * @return int|WP_Error
 */
function cmp_insert_expense( $data ) {
	global $wpdb;

	$batch_id = absint( cmp_field( $data, 'batch_id', 0 ) );
	$batch    = cmp_get_batch( $batch_id );
	$amount   = cmp_money_value( cmp_field( $data, 'amount', 0 ) );

	if ( ! $batch || $amount <= 0 ) {
		return new WP_Error( 'cmp_invalid_expense', __( 'Valid batch and expense amount are required.', 'class-manager-pro' ) );
	}

	$expense_date = sanitize_text_field( cmp_field( $data, 'expense_date', current_time( 'Y-m-d' ) ) );

	if ( '' === $expense_date ) {
		$expense_date = current_time( 'Y-m-d' );
	}

	$result = $wpdb->insert(
		cmp_table( 'expenses' ),
		array(
			'class_id'     => (int) $batch->class_id,
			'batch_id'     => $batch_id,
			'category'     => cmp_clean_enum( cmp_field( $data, 'category', 'other' ), cmp_expense_categories(), 'other' ),
			'amount'       => $amount,
			'expense_date' => $expense_date,
			'notes'        => sanitize_textarea_field( cmp_field( $data, 'notes' ) ),
			'created_at'   => cmp_current_datetime(),
		),
		array( '%d', '%d', '%s', '%f', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		cmp_log_event( 'error', 'Expense insert failed.', array( 'batch_id' => $batch_id, 'category' => cmp_field( $data, 'category', 'other' ), 'amount' => $amount ) );
		return new WP_Error( 'cmp_db_error', __( 'Could not save expense.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Deletes a batch expense.
 *
 * @param int $expense_id Expense ID.
 * @return true|WP_Error
 */
function cmp_delete_expense( $expense_id ) {
	global $wpdb;

	$expense_id = absint( $expense_id );

	if ( ! $expense_id ) {
		return new WP_Error( 'cmp_invalid_expense', __( 'Invalid expense.', 'class-manager-pro' ) );
	}

	$wpdb->delete( cmp_table( 'expenses' ), array( 'id' => $expense_id ), array( '%d' ) );

	return true;
}

/**
 * Returns expenses for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return array
 */
function cmp_get_batch_expenses( $batch_id ) {
	global $wpdb;

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_table( 'expenses' ) . ' WHERE batch_id = %d ORDER BY expense_date DESC, id DESC',
			absint( $batch_id )
		)
	);
}

/**
 * Returns expense totals for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return array
 */
function cmp_get_batch_expense_totals( $batch_id ) {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT category, COALESCE(SUM(amount), 0) AS total FROM ' . cmp_table( 'expenses' ) . ' WHERE batch_id = %d GROUP BY category',
			absint( $batch_id )
		),
		OBJECT_K
	);

	$teacher_payment = isset( $rows['teacher_payment'] ) ? (float) $rows['teacher_payment']->total : 0;
	$meta_ads        = isset( $rows['meta_ads'] ) ? (float) $rows['meta_ads']->total : 0;
	$ad_material     = isset( $rows['ad_material'] ) ? (float) $rows['ad_material']->total : 0;
	$other           = isset( $rows['other'] ) ? (float) $rows['other']->total : 0;

	return array(
		'teacher_payment' => $teacher_payment,
		'meta_ads'        => $meta_ads,
		'ad_material'     => $ad_material,
		'ads_spend'       => $meta_ads + $ad_material,
		'other'           => $other,
		'total_expense'   => $teacher_payment + $meta_ads + $ad_material + $other,
	);
}

/**
 * Returns overall finance totals.
 *
 * @return array
 */
function cmp_get_finance_summary() {
	global $wpdb;

	$payment_income = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) );
	$manual_income  = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(manual_income), 0) FROM ' . cmp_table( 'batches' ) );
	$expense_rows   = $wpdb->get_results( 'SELECT category, COALESCE(SUM(amount), 0) AS total FROM ' . cmp_table( 'expenses' ) . ' GROUP BY category', OBJECT_K );

	$teacher_payment = isset( $expense_rows['teacher_payment'] ) ? (float) $expense_rows['teacher_payment']->total : 0;
	$meta_ads        = isset( $expense_rows['meta_ads'] ) ? (float) $expense_rows['meta_ads']->total : 0;
	$ad_material     = isset( $expense_rows['ad_material'] ) ? (float) $expense_rows['ad_material']->total : 0;
	$other           = isset( $expense_rows['other'] ) ? (float) $expense_rows['other']->total : 0;
	$total_income    = $payment_income + $manual_income;
	$total_expense   = $teacher_payment + $meta_ads + $ad_material + $other;

	return array(
		'payment_income'  => $payment_income,
		'manual_income'   => $manual_income,
		'total_income'    => $total_income,
		'teacher_payment' => $teacher_payment,
		'meta_ads'        => $meta_ads,
		'ad_material'     => $ad_material,
		'ads_spend'       => $meta_ads + $ad_material,
		'other_expense'   => $other,
		'total_expense'   => $total_expense,
		'net_income'      => $total_income - $total_expense,
	);
}

/**
 * Returns dashboard metric values.
 *
 * @param string $range Optional date range.
 * @return array
 */
function cmp_get_dashboard_metrics( $range = 'month' ) {
	global $wpdb;

	$finance = cmp_get_finance_summary();
	$range   = cmp_get_dashboard_range( $range );
	$bounds  = cmp_get_date_range_bounds( $range );
	$label   = 'today' === $range ? __( 'Today', 'class-manager-pro' ) : ( 'week' === $range ? __( 'This Week', 'class-manager-pro' ) : __( 'This Month', 'class-manager-pro' ) );
	$student_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE created_at BETWEEN %s AND %s',
			$bounds['start'],
			$bounds['end']
		)
	);
	$revenue = (float) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) . ' WHERE payment_date BETWEEN %s AND %s',
			$bounds['start'],
			$bounds['end']
		)
	);

	return array(
		'total_classes'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'classes' ) ),
		'total_batches'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) ),
		'total_students'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) ),
		'filtered_students' => $student_count,
		'total_revenue'   => (float) $finance['total_income'],
		'filtered_revenue' => $revenue,
		'pending_fees'    => (float) $wpdb->get_var( 'SELECT COALESCE(SUM(GREATEST(total_fee - paid_fee, 0)), 0) FROM ' . cmp_table( 'students' ) ),
		'total_expense'   => (float) $finance['total_expense'],
		'net_income'      => (float) $finance['net_income'],
		'range'           => $range,
		'range_label'     => $label,
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
 * Fetches students by a list of IDs.
 *
 * @param array $student_ids Student IDs.
 * @return array
 */
function cmp_get_students_by_ids( $student_ids ) {
	global $wpdb;

	$student_ids = cmp_clean_absint_array( $student_ids );

	if ( empty( $student_ids ) ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $student_ids ), '%d' ) );
	$sql          = 'SELECT s.id, s.unique_id, s.name, s.phone, s.email, s.class_id, s.batch_id, s.total_fee, s.paid_fee, s.status, s.notes, s.created_at,
		c.name AS class_name, c.next_course_id AS class_next_course_id, b.batch_name, b.batch_fee, b.fee_due_date, b.razorpay_link
		FROM ' . cmp_table( 'students' ) . ' s
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
		WHERE s.id IN (' . $placeholders . ')
		ORDER BY s.name ASC';

	return $wpdb->get_results( $wpdb->prepare( $sql, $student_ids ) );
}

/**
 * Builds CSV content for a selected student list.
 *
 * @param array $student_ids Student IDs.
 * @return string
 */
function cmp_build_students_csv_content( $student_ids ) {
	$students = cmp_get_students_by_ids( $student_ids );
	$output   = fopen( 'php://temp', 'r+' );

	if ( ! $output ) {
		return '';
	}

	fputcsv( $output, array( 'Student ID', 'Student Name', 'Phone', 'Email', 'Class', 'Batch', 'Total Fee', 'Paid Fee', 'Remaining Fee', 'Status' ) );

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

	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );

	return is_string( $csv ) ? $csv : '';
}

/**
 * Fetches classes by a list of IDs.
 *
 * @param array $class_ids Class IDs.
 * @return array
 */
function cmp_get_classes_by_ids( $class_ids ) {
	global $wpdb;

	$class_ids = cmp_clean_absint_array( $class_ids );

	if ( empty( $class_ids ) ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $class_ids ), '%d' ) );
	$sql          = 'SELECT id, name, description, total_fee, next_course_id, created_at
		FROM ' . cmp_table( 'classes' ) . '
		WHERE id IN (' . $placeholders . ')
		ORDER BY name ASC';

	return $wpdb->get_results( $wpdb->prepare( $sql, $class_ids ) );
}

/**
 * Builds CSV content for a selected class list.
 *
 * @param array $class_ids Class IDs.
 * @return string
 */
function cmp_build_classes_csv_content( $class_ids ) {
	$classes = cmp_get_classes_by_ids( $class_ids );
	$output  = fopen( 'php://temp', 'r+' );

	if ( ! $output ) {
		return '';
	}

	fputcsv( $output, array( 'Class Name', 'Description', 'Default Fee', 'Next Course', 'Created' ) );

	foreach ( $classes as $class ) {
		fputcsv(
			$output,
			array(
				$class->name,
				$class->description,
				$class->total_fee,
				! empty( $class->next_course_id ) ? cmp_get_tutor_course_title( (int) $class->next_course_id ) : '',
				$class->created_at,
			)
		);
	}

	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );

	return is_string( $csv ) ? $csv : '';
}

/**
 * Fetches batches by a list of IDs.
 *
 * @param array $batch_ids Batch IDs.
 * @return array
 */
function cmp_get_batches_by_ids( $batch_ids ) {
	global $wpdb;

	$batch_ids = cmp_clean_absint_array( $batch_ids );

	if ( empty( $batch_ids ) ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $batch_ids ), '%d' ) );
	$sql          = 'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee,
		COALESCE(st.student_count, 0) AS student_count
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		LEFT JOIN (
			SELECT batch_id, COUNT(*) AS student_count
			FROM ' . cmp_table( 'students' ) . '
			GROUP BY batch_id
		) st ON st.batch_id = b.id
		WHERE b.id IN (' . $placeholders . ')
		ORDER BY b.created_at DESC, b.id DESC';

	return $wpdb->get_results( $wpdb->prepare( $sql, $batch_ids ) );
}

/**
 * Builds CSV content for a selected batch list.
 *
 * @param array $batch_ids Batch IDs.
 * @return string
 */
function cmp_build_batches_csv_content( $batch_ids ) {
	$batches = cmp_get_batches_by_ids( $batch_ids );
	$output  = fopen( 'php://temp', 'r+' );

	if ( ! $output ) {
		return '';
	}

	fputcsv( $output, array( 'Batch Name', 'Class', 'Tutor Course', 'Teacher', 'Start Date', 'Status', 'Batch Fee', 'Fee Due Date', 'Students', 'Created' ) );

	foreach ( $batches as $batch ) {
		fputcsv(
			$output,
			array(
				$batch->batch_name,
				$batch->class_name,
				! empty( $batch->course_id ) ? cmp_get_tutor_course_title( (int) $batch->course_id ) : '',
				cmp_get_teacher_label( (int) $batch->teacher_user_id ),
				$batch->start_date,
				$batch->status,
				cmp_get_batch_effective_fee( $batch ),
				$batch->fee_due_date,
				(int) $batch->student_count,
				$batch->created_at,
			)
		);
	}

	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );

	return is_string( $csv ) ? $csv : '';
}

/**
 * Builds CSV content for admin activity logs.
 *
 * @return string
 */
function cmp_build_admin_logs_csv_content() {
	$logs   = cmp_get_admin_logs( 0 );
	$output = fopen( 'php://temp', 'r+' );

	if ( ! $output ) {
		return '';
	}

	fputcsv( $output, array( 'Timestamp', 'Admin', 'Action', 'Details' ) );

	foreach ( $logs as $log ) {
		fputcsv(
			$output,
			array(
				$log->created_at,
				$log->admin_name ? $log->admin_name : __( 'System', 'class-manager-pro' ),
				cmp_get_admin_log_action_label( $log->action ),
				$log->message ? $log->message : sprintf( __( '%1$s #%2$d', 'class-manager-pro' ), ucfirst( $log->object_type ), (int) $log->object_id ),
			)
		);
	}

	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );

	return is_string( $csv ) ? $csv : '';
}

/**
 * Builds CSV content for teacher activity logs.
 *
 * @param int $teacher_user_id Teacher user ID.
 * @return string
 */
function cmp_build_teacher_logs_csv_content( $teacher_user_id ) {
	$logs   = cmp_get_teacher_logs( $teacher_user_id, 0 );
	$output = fopen( 'php://temp', 'r+' );

	if ( ! $output ) {
		return '';
	}

	fputcsv( $output, array( 'Timestamp', 'Teacher', 'Action', 'Batch', 'Student', 'Details' ) );

	foreach ( $logs as $log ) {
		fputcsv(
			$output,
			array(
				$log->created_at,
				$log->teacher_name ? $log->teacher_name : __( 'Unknown Teacher', 'class-manager-pro' ),
				cmp_get_teacher_log_action_label( $log->action ),
				$log->batch_name,
				$log->student_name ? $log->student_name . ( $log->student_unique_id ? ' (' . $log->student_unique_id . ')' : '' ) : '',
				$log->message,
			)
		);
	}

	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );

	return is_string( $csv ) ? $csv : '';
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
		echo '<tr data-cmp-row-id="student-' . esc_attr( (int) $student->id ) . '">';
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
		echo '<tr><td colspan="12">' . esc_html__( 'No students found.', 'class-manager-pro' ) . '</td></tr>';
		return ob_get_clean();
	}

	foreach ( $students as $student ) {
		$remaining      = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
		$payment_status = cmp_get_student_payment_status( $student );
		$view_url       = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
		$edit_url       = cmp_admin_url( 'cmp-students', array( 'action' => 'edit', 'id' => (int) $student->id ) );
		$payment_url    = cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id ) ) . '#cmp-add-payment';
		$profile_url    = cmp_get_student_profile_url( $student );
		$email_url      = cmp_get_email_reminder_url( $student );
		$delete_url     = wp_nonce_url(
			admin_url( 'admin-post.php?action=cmp_delete_student&id=' . (int) $student->id ),
			'cmp_delete_student_' . (int) $student->id
		);

		echo '<tr data-cmp-row-id="student-' . esc_attr( (int) $student->id ) . '">';
		echo '<td><input type="checkbox" class="cmp-student-select" value="' . esc_attr( (int) $student->id ) . '"></td>';
		echo '<td>' . esc_html( $student->name ) . '<br><span class="cmp-muted">' . esc_html( $student->unique_id ) . '</span></td>';
		echo '<td>' . esc_html( $student->phone ) . '</td>';
		echo '<td>' . esc_html( $student->email ) . '</td>';
		echo '<td>' . esc_html( $student->class_name ) . '</td>';
		echo '<td>' . esc_html( $student->batch_name ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->total_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->paid_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $remaining ) ) . '</td>';
		echo '<td><span class="cmp-status cmp-status-' . esc_attr( $payment_status['key'] ) . '">' . esc_html( $payment_status['label'] ) . '</span></td>';
		echo '<td><span class="cmp-status cmp-status-' . esc_attr( $student->status ) . '">' . esc_html( ucfirst( $student->status ) ) . '</span></td>';
		echo '<td class="cmp-actions">';
		echo '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'class-manager-pro' ) . '</a> ';
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'class-manager-pro' ) . '</a> ';
		echo '<a href="' . esc_url( $payment_url ) . '">' . esc_html__( 'Payment', 'class-manager-pro' ) . '</a> ';
		if ( $profile_url ) {
			echo '<a href="' . esc_url( $profile_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View Profile', 'class-manager-pro' ) . '</a> ';
		}
		if ( $email_url ) {
			echo '<a href="' . esc_url( $email_url ) . '">' . esc_html__( 'Email Reminder', 'class-manager-pro' ) . '</a> ';
		}
		echo '<a class="cmp-delete-link" href="' . esc_url( $delete_url ) . '" data-id="' . esc_attr( (int) $student->id ) . '" data-type="student" data-cmp-ajax-delete="1" data-cmp-entity-type="student" data-cmp-entity-id="' . esc_attr( (int) $student->id ) . '" data-cmp-confirm="' . esc_attr__( 'Delete this student?', 'class-manager-pro' ) . '" data-cmp-feedback="#cmp-student-bulk-feedback">' . esc_html__( 'Delete', 'class-manager-pro' ) . '</a>';
		echo '</td>';
		echo '</tr>';
	}

	return ob_get_clean();
}

/**
 * AJAX handler for All Data filtering.
 */
function cmp_ajax_filter_all_data() {
	cmp_require_manage_options_ajax();
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
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	wp_send_json_success(
		array(
			'html' => cmp_render_student_rows( cmp_read_student_filters() ),
		)
	);
}

/**
 * Returns CSV content for an entity type.
 *
 * @param string $entity_type Entity type.
 * @param array  $ids Entity IDs.
 * @return string
 */
function cmp_get_admin_entity_csv_content( $entity_type, $ids ) {
	switch ( sanitize_key( $entity_type ) ) {
		case 'class':
			return cmp_build_classes_csv_content( $ids );

		case 'batch':
			return cmp_build_batches_csv_content( $ids );

		case 'student':
			return cmp_build_students_csv_content( $ids );
	}

	return '';
}

/**
 * Returns the CSV filename for an entity type.
 *
 * @param string $entity_type Entity type.
 * @return string
 */
function cmp_get_admin_entity_export_filename( $entity_type ) {
	switch ( sanitize_key( $entity_type ) ) {
		case 'class':
			return 'class-manager-pro-selected-classes-' . gmdate( 'Y-m-d' ) . '.csv';

		case 'batch':
			return 'class-manager-pro-selected-batches-' . gmdate( 'Y-m-d' ) . '.csv';

		case 'student':
			return 'class-manager-pro-selected-students-' . gmdate( 'Y-m-d' ) . '.csv';
	}

	return 'class-manager-pro-export-' . gmdate( 'Y-m-d' ) . '.csv';
}

/**
 * Returns a human message for deleted entities.
 *
 * @param string $entity_type Entity type.
 * @param int    $deleted_count Deleted count.
 * @return string
 */
function cmp_get_admin_entity_delete_message( $entity_type, $deleted_count ) {
	$deleted_count = absint( $deleted_count );

	switch ( sanitize_key( $entity_type ) ) {
		case 'class':
			return sprintf( _n( '%d class deleted.', '%d classes deleted.', $deleted_count, 'class-manager-pro' ), $deleted_count );

		case 'batch':
			return sprintf( _n( '%d batch deleted.', '%d batches deleted.', $deleted_count, 'class-manager-pro' ), $deleted_count );

		case 'student':
			return sprintf( _n( '%d student deleted.', '%d students deleted.', $deleted_count, 'class-manager-pro' ), $deleted_count );
	}

	return sprintf( _n( '%d record deleted.', '%d records deleted.', $deleted_count, 'class-manager-pro' ), $deleted_count );
}

/**
 * Deletes an entity by type.
 *
 * @param string $entity_type Entity type.
 * @param int    $entity_id Entity ID.
 * @return true|WP_Error
 */
function cmp_delete_admin_entity( $entity_type, $entity_id ) {
	switch ( sanitize_key( $entity_type ) ) {
		case 'class':
			return cmp_delete_class( $entity_id );

		case 'batch':
			return cmp_delete_batch( $entity_id );

		case 'student':
			return cmp_delete_student( $entity_id );
	}

	return new WP_Error( 'cmp_invalid_entity_type', __( 'Invalid entity type.', 'class-manager-pro' ) );
}

/**
 * AJAX delete handler for classes, batches, and students.
 *
 * @return void
 */
function cmp_delete_item() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_delete_nonce', 'nonce' );

	$entity_id   = absint( cmp_field( $_POST, 'id', 0 ) );
	$entity_type = sanitize_key( cmp_field( $_POST, 'type' ) );

	if ( ! $entity_id || ! in_array( $entity_type, array( 'student', 'batch', 'class' ), true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid delete request.', 'class-manager-pro' ),
			),
			400
		);
	}

	$result = cmp_delete_admin_entity( $entity_type, $entity_id );

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
			'message'     => cmp_get_admin_entity_delete_message( $entity_type, 1 ),
			'deleted_row' => $entity_type . '-' . $entity_id,
		)
	);
}

/**
 * Processes admin entity AJAX actions.
 *
 * @param string $forced_entity_type Optional forced entity type.
 * @return void
 */
function cmp_process_admin_entity_ajax_action( $forced_entity_type = '' ) {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	$entity_type = $forced_entity_type ? sanitize_key( $forced_entity_type ) : sanitize_key( cmp_field( $_POST, 'entity_type' ) );
	$task        = sanitize_key( cmp_field( $_POST, 'task', cmp_field( $_POST, 'bulk_action' ) ) );
	$ids_source  = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : ( isset( $_POST['student_ids'] ) ? wp_unslash( $_POST['student_ids'] ) : array() );
	$entity_ids  = cmp_clean_absint_array( $ids_source );

	if ( empty( $entity_ids ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Please select at least one record.', 'class-manager-pro' ),
			),
			400
		);
	}

	if ( 'change_batch' === $task ) {
		if ( 'student' !== $entity_type ) {
			wp_send_json_error(
				array(
					'message' => __( 'This bulk action is not supported for the selected entity.', 'class-manager-pro' ),
				),
				400
			);
		}

		$target_batch = cmp_get_sync_target_batch( absint( cmp_field( $_POST, 'target_class_id', 0 ) ), absint( cmp_field( $_POST, 'target_batch_id', 0 ) ) );

		if ( is_wp_error( $target_batch ) ) {
			wp_send_json_error(
				array(
					'message' => $target_batch->get_error_message(),
				),
				400
			);
		}

		$students = cmp_get_students_by_ids( $entity_ids );
		$updated  = 0;

		foreach ( $students as $student ) {
			$result = cmp_update_student(
				(int) $student->id,
				array(
					'name'      => $student->name,
					'phone'     => $student->phone,
					'email'     => $student->email,
					'class_id'  => (int) $target_batch->class_id,
					'batch_id'  => (int) $target_batch->id,
					'total_fee' => (float) $student->total_fee,
					'paid_fee'  => (float) $student->paid_fee,
					'status'    => $student->status,
					'notes'     => $student->notes,
				)
			);

			if ( ! is_wp_error( $result ) ) {
				++$updated;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: updated students */
					_n( '%d student moved to the selected batch.', '%d students moved to the selected batch.', $updated, 'class-manager-pro' ),
					$updated
				),
				'reload'  => true,
			)
		);
	}

	if ( 'export' === $task ) {
		$csv = cmp_get_admin_entity_csv_content( $entity_type, $entity_ids );

		if ( '' === $csv ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not export the selected records.', 'class-manager-pro' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Export ready.', 'class-manager-pro' ),
				'filename' => cmp_get_admin_entity_export_filename( $entity_type ),
				'csv'      => $csv,
			)
		);
	}

	if ( 'delete' === $task ) {
		$deleted         = 0;
		$deleted_rows    = array();
		$failed_messages = array();

		foreach ( $entity_ids as $entity_id ) {
			$result = cmp_delete_admin_entity( $entity_type, $entity_id );

			if ( is_wp_error( $result ) ) {
				$failed_messages[] = $result->get_error_message();
				continue;
			}

			++$deleted;
			$deleted_rows[] = sanitize_key( $entity_type ) . '-' . absint( $entity_id );
		}

		if ( ! $deleted ) {
			wp_send_json_error(
				array(
					'message' => ! empty( $failed_messages ) ? $failed_messages[0] : __( 'Could not delete the selected records.', 'class-manager-pro' ),
				),
				400
			);
		}

		$message = cmp_get_admin_entity_delete_message( $entity_type, $deleted );

		if ( count( $failed_messages ) > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: skipped records */
				_n( '%d record skipped.', '%d records skipped.', count( $failed_messages ), 'class-manager-pro' ),
				count( $failed_messages )
			);
			$message .= ' ' . $failed_messages[0];
		}

		wp_send_json_success(
			array(
				'message'      => $message,
				'deleted_rows' => $deleted_rows,
				'reload'       => count( $entity_ids ) > 1,
			)
		);
	}

	wp_send_json_error(
		array(
			'message' => __( 'Invalid bulk action.', 'class-manager-pro' ),
		),
		400
	);
}

/**
 * AJAX handler for admin entity actions.
 */
function cmp_ajax_admin_entity_action() {
	cmp_process_admin_entity_ajax_action();
}

/**
 * AJAX handler for student bulk actions.
 */
function cmp_ajax_bulk_student_action() {
	cmp_process_admin_entity_ajax_action( 'student' );
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

	if ( ! in_array( $type, array( 'all-data', 'students', 'payments', 'classes', 'admin-logs', 'teacher-logs' ), true ) ) {
		return;
	}

	check_admin_referer( 'cmp_export_' . $type );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=class-manager-pro-' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );

	if ( 'admin-logs' === $type ) {
		fwrite( $output, cmp_build_admin_logs_csv_content() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $output );
		exit;
	} elseif ( 'teacher-logs' === $type ) {
		fwrite( $output, cmp_build_teacher_logs_csv_content( absint( cmp_field( $_GET, 'teacher_user_id', 0 ) ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $output );
		exit;
	} elseif ( 'classes' === $type ) {
		fputcsv( $output, array( 'Class Name', 'Description', 'Default Fee', 'Created' ) );
		$classes = cmp_get_classes();

		foreach ( $classes as $class ) {
			fputcsv(
				$output,
				array(
					$class->name,
					$class->description,
					$class->total_fee,
					$class->created_at,
				)
			);
		}
	} elseif ( 'payments' === $type ) {
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

	cmp_sync_batch_students_tutor_enrollment( $batch_id );

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
	$duplicate_notice = cmp_pop_duplicate_lead_notice();

	if ( '' !== $duplicate_notice ) {
		cmp_redirect( $page, $duplicate_notice, 'warning' );
	}

	cmp_redirect( $page, __( 'Student saved successfully.', 'class-manager-pro' ) );
}

/**
 * Enrolls a completed student into the configured next course.
 */
function cmp_handle_enroll_student_next_course() {
	cmp_require_manage_options();

	$student_id = absint( cmp_field( $_GET, 'id', 0 ) );
	$student    = cmp_get_student( $student_id );
	check_admin_referer( 'cmp_enroll_student_next_course_' . $student_id );

	if ( ! $student ) {
		cmp_redirect( 'cmp-students', __( 'Student not found.', 'class-manager-pro' ), 'error' );
	}

	if ( 'completed' !== $student->status ) {
		cmp_redirect( 'cmp-students', __( 'Only completed students can be enrolled in the next course.', 'class-manager-pro' ), 'warning', array( 'action' => 'view', 'id' => $student_id ) );
	}

	$next_course_id = ! empty( $student->class_next_course_id ) ? absint( $student->class_next_course_id ) : 0;

	if ( ! $next_course_id ) {
		cmp_redirect( 'cmp-students', __( 'No next course is configured for this class.', 'class-manager-pro' ), 'error', array( 'action' => 'view', 'id' => $student_id ) );
	}

	if ( cmp_enroll_student_in_specific_tutor_course( $student_id, $next_course_id ) ) {
		cmp_redirect( 'cmp-students', __( 'Student enrolled in the next course successfully.', 'class-manager-pro' ), 'success', array( 'action' => 'view', 'id' => $student_id ) );
	}

	cmp_redirect( 'cmp-students', __( 'Could not enroll the student in the next course.', 'class-manager-pro' ), 'error', array( 'action' => 'view', 'id' => $student_id ) );
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
 * Handles expense form submission.
 */
function cmp_handle_save_expense() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_expense' );

	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	$result   = cmp_insert_expense( $_POST );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-batches', $result->get_error_message(), 'error', $batch_id ? array( 'action' => 'view', 'id' => $batch_id ) : array() );
	}

	cmp_redirect( 'cmp-batches', __( 'Expense saved successfully.', 'class-manager-pro' ), 'success', array( 'action' => 'view', 'id' => $batch_id ) );
}

/**
 * Handles expense deletion.
 */
function cmp_handle_delete_expense() {
	cmp_require_manage_options();

	$expense_id = absint( cmp_field( $_GET, 'id', 0 ) );
	$batch_id   = absint( cmp_field( $_GET, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_delete_expense_' . $expense_id );

	$result = cmp_delete_expense( $expense_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-batches', $result->get_error_message(), 'error', $batch_id ? array( 'action' => 'view', 'id' => $batch_id ) : array() );
	}

	cmp_redirect( 'cmp-batches', __( 'Expense deleted successfully.', 'class-manager-pro' ), 'success', $batch_id ? array( 'action' => 'view', 'id' => $batch_id ) : array() );
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
 * Returns allowed Razorpay automation schedules.
 *
 * @return array
 */
function cmp_automation_intervals() {
	return array(
		'hourly'     => __( 'Hourly', 'class-manager-pro' ),
		'twicedaily' => __( 'Twice Daily', 'class-manager-pro' ),
		'daily'      => __( 'Daily', 'class-manager-pro' ),
	);
}

/**
 * Clears all pending events for a hook.
 *
 * @param string $hook Cron hook.
 * @return void
 */
function cmp_clear_scheduled_hook( $hook ) {
	$timestamp = wp_next_scheduled( $hook );

	while ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
		$timestamp = wp_next_scheduled( $hook );
	}
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
	cmp_clear_scheduled_hook( 'cmp_daily_fee_reminders' );
	cmp_clear_scheduled_hook( 'cmp_razorpay_payment_sync' );
}

/**
 * Validates and returns a Razorpay sync target batch.
 *
 * @param int $class_id Class ID.
 * @param int $batch_id Batch ID.
 * @return object|WP_Error
 */
function cmp_get_sync_target_batch( $class_id, $batch_id ) {
	$class_id = absint( $class_id );
	$batch_id = absint( $batch_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $class_id || ! $batch_id || ! $batch ) {
		return new WP_Error( 'cmp_sync_target_missing', __( 'Please choose a valid class and batch for Razorpay sync.', 'class-manager-pro' ) );
	}

	if ( (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_sync_target_invalid', __( 'The selected batch does not belong to the selected class.', 'class-manager-pro' ) );
	}

	return $batch;
}

/**
 * Schedules or clears the Razorpay payment sync automation.
 */
function cmp_schedule_razorpay_payment_sync() {
	$enabled  = '1' === (string) get_option( 'cmp_automation_sync_enabled', '0' );
	$interval = sanitize_key( (string) get_option( 'cmp_automation_sync_interval', 'hourly' ) );
	$allowed  = cmp_automation_intervals();

	if ( ! isset( $allowed[ $interval ] ) ) {
		$interval = 'hourly';
	}

	$current = wp_get_schedule( 'cmp_razorpay_payment_sync' );

	if ( ! $enabled ) {
		cmp_clear_scheduled_hook( 'cmp_razorpay_payment_sync' );
		return;
	}

	if ( $current && $current !== $interval ) {
		cmp_clear_scheduled_hook( 'cmp_razorpay_payment_sync' );
	}

	if ( ! wp_next_scheduled( 'cmp_razorpay_payment_sync' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS * 15, $interval, 'cmp_razorpay_payment_sync' );
	}
}

/**
 * Runs scheduled Razorpay payment sync.
 */
function cmp_run_scheduled_razorpay_payment_sync() {
	cmp_process_razorpay_payment_sync(
		array(
			'source'         => 'cron',
			'fixed_class_id' => absint( get_option( 'cmp_automation_sync_class_id', 0 ) ),
			'fixed_batch_id' => absint( get_option( 'cmp_automation_sync_batch_id', 0 ) ),
		)
	);
}

/**
 * Saves automation settings.
 */
function cmp_handle_save_automation_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_automation_settings' );

	$interval      = sanitize_key( cmp_field( $_POST, 'automation_sync_interval', 'hourly' ) );
	$lookback_days = absint( cmp_field( $_POST, 'automation_sync_lookback_days', 7 ) );
	$class_id      = absint( cmp_field( $_POST, 'automation_sync_class_id', 0 ) );
	$batch_id      = absint( cmp_field( $_POST, 'automation_sync_batch_id', 0 ) );
	$enabled       = ! empty( $_POST['automation_sync_enabled'] );
	$allowed       = cmp_automation_intervals();

	if ( $enabled && ( ! $class_id || ! $batch_id ) ) {
		cmp_redirect( 'cmp-settings', __( 'Choose the automation sync class and batch before enabling auto sync.', 'class-manager-pro' ), 'error' );
	}

	if ( $class_id || $batch_id ) {
		$target_batch = cmp_get_sync_target_batch( $class_id, $batch_id );

		if ( is_wp_error( $target_batch ) ) {
			cmp_redirect( 'cmp-settings', $target_batch->get_error_message(), 'error' );
		}
	}

	update_option( 'cmp_automation_sync_enabled', $enabled ? '1' : '0' );
	update_option( 'cmp_automation_sync_interval', isset( $allowed[ $interval ] ) ? $interval : 'hourly' );
	update_option( 'cmp_automation_sync_lookback_days', in_array( $lookback_days, array( 1, 7, 30, 90 ), true ) ? $lookback_days : 7 );
	update_option( 'cmp_automation_sync_class_id', $class_id );
	update_option( 'cmp_automation_sync_batch_id', $batch_id );

	cmp_schedule_razorpay_payment_sync();

	cmp_redirect( 'cmp-settings', __( 'Automation settings saved successfully.', 'class-manager-pro' ) );
}

/**
 * Runs Razorpay payment sync from the admin UI.
 */
function cmp_handle_sync_razorpay_payments() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_sync_razorpay_payments' );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$from_date = sanitize_text_field( cmp_field( $_POST, 'created_from' ) );
	$to_date   = sanitize_text_field( cmp_field( $_POST, 'created_to' ) );
	$search    = sanitize_text_field( cmp_field( $_POST, 'sync_search' ) );
	$page      = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-razorpay-import' ), 'cmp-razorpay-import' );
	$class_id  = absint( cmp_field( $_POST, 'sync_class_id', get_option( 'cmp_automation_sync_class_id', 0 ) ) );
	$batch_id  = absint( cmp_field( $_POST, 'sync_batch_id', get_option( 'cmp_automation_sync_batch_id', 0 ) ) );
	$result    = cmp_process_razorpay_payment_sync(
		array(
			'from'           => cmp_parse_sync_date_to_timestamp( $from_date, 'start' ),
			'to'             => cmp_parse_sync_date_to_timestamp( $to_date, 'end' ),
			'search'         => $search,
			'source'         => 'manual',
			'fixed_class_id' => $class_id,
			'fixed_batch_id' => $batch_id,
		)
	);
	$redirect_args = array(
		'created_from'  => $from_date,
		'created_to'    => $to_date,
		'sync_search'   => $search,
		'sync_class_id' => $class_id,
		'sync_batch_id' => $batch_id,
	);

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $page, $result->get_error_message(), 'error', $redirect_args );
	}

	cmp_redirect(
		$page,
		sprintf(
			/* translators: 1: fetched count 2: imported count 3: duplicates 4: skipped 5: failed */
			__( 'Razorpay sync completed. %1$d fetched, %2$d imported, %3$d duplicates, %4$d skipped, %5$d failed.', 'class-manager-pro' ),
			(int) $result['fetched'],
			(int) $result['imported'],
			(int) $result['duplicate'],
			(int) $result['skipped'],
			(int) $result['failed']
		),
		'success',
		$redirect_args
	);
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
		'course_id'        => absint( cmp_field( $data, 'course_id', 0 ) ),
		'start_date'       => cmp_field( $data, 'start_date' ),
		'fee_due_date'     => cmp_field( $data, 'fee_due_date' ),
		'status'           => cmp_field( $data, 'status', 'active' ),
		'razorpay_link'    => cmp_field( $data, 'razorpay_link' ),
		'batch_fee'        => cmp_field( $data, 'batch_fee', 0 ),
		'is_free'          => ! empty( $data['is_free'] ) ? 1 : 0,
		'teacher_user_id'  => absint( cmp_field( $data, 'teacher_user_id', 0 ) ),
		'intake_limit'     => absint( cmp_field( $data, 'intake_limit', 0 ) ),
		'class_days'       => cmp_field( $data, 'class_days' ),
		'manual_income'    => cmp_field( $data, 'manual_income', 0 ),
		'razorpay_page_id' => $razorpay_page_id,
	);

	if ( $existing ) {
		$payload['course_id']      = $payload['course_id'] ? $payload['course_id'] : (int) $existing->course_id;
		$payload['start_date']    = '' !== (string) $payload['start_date'] ? $payload['start_date'] : $existing->start_date;
		$payload['fee_due_date']  = '' !== (string) $payload['fee_due_date'] ? $payload['fee_due_date'] : $existing->fee_due_date;
		$payload['razorpay_link'] = '' !== (string) $payload['razorpay_link'] ? $payload['razorpay_link'] : $existing->razorpay_link;
		$payload['teacher_user_id'] = $payload['teacher_user_id'] ? $payload['teacher_user_id'] : (int) $existing->teacher_user_id;
		$payload['intake_limit'] = $payload['intake_limit'] ? $payload['intake_limit'] : (int) $existing->intake_limit;
		$payload['class_days'] = '' !== (string) $payload['class_days'] ? $payload['class_days'] : $existing->class_days;
		$payload['manual_income'] = cmp_money_value( $payload['manual_income'] );
		$payload['manual_income'] = $payload['manual_income'] > 0 ? $payload['manual_income'] : (float) $existing->manual_income;
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
		cmp_log_event( 'error', 'Razorpay API request failed.', array( 'path' => $path, 'message' => $response->get_error_message() ) );
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		cmp_log_event( 'error', 'Razorpay API returned an error response.', array( 'path' => $path, 'status' => $code ) );
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
 * Returns merged Razorpay metadata from common payload keys.
 *
 * @param array $entity Razorpay entity.
 * @return array
 */
function cmp_razorpay_entity_meta( $entity ) {
	$meta = array();

	if ( ! is_array( $entity ) ) {
		return $meta;
	}

	foreach ( array( 'notes', 'metadata' ) as $key ) {
		if ( isset( $entity[ $key ] ) && is_array( $entity[ $key ] ) ) {
			$meta = array_merge( $meta, $entity[ $key ] );
		}
	}

	return $meta;
}

/**
 * Returns whether a Razorpay payment matches a notes/metadata search.
 *
 * @param array  $payment Razorpay payment.
 * @param string $search Search text.
 * @return bool
 */
function cmp_razorpay_payment_matches_search( $payment, $search = '' ) {
	$search = trim( sanitize_text_field( $search ) );

	if ( '' === $search ) {
		return true;
	}

	$meta     = cmp_razorpay_entity_meta( $payment );
	$haystack = implode(
		' ',
		array_filter(
			array(
				isset( $payment['id'] ) ? (string) $payment['id'] : '',
				isset( $payment['description'] ) ? (string) $payment['description'] : '',
				isset( $payment['email'] ) ? (string) $payment['email'] : '',
				isset( $payment['contact'] ) ? (string) $payment['contact'] : '',
				wp_json_encode( $meta ),
			)
		)
	);

	return false !== stripos( $haystack, $search );
}

/**
 * Returns a useful import title from a Razorpay entity.
 *
 * @param array $entity Razorpay entity.
 * @return string
 */
function cmp_razorpay_entity_title( $entity ) {
	$notes = cmp_razorpay_entity_meta( $entity );

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
	$notes        = cmp_razorpay_entity_meta( $payment_link );
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

	$meta = cmp_razorpay_entity_meta( $payment );

	foreach ( array( $payment, $meta ) as $source ) {
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
 * @param string $search Optional notes/metadata search text.
 * @return array
 */
function cmp_filter_successful_razorpay_payments( $payments, $page_id = '', $search = '' ) {
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

		if ( ! cmp_razorpay_payment_matches_search( $payment, $search ) ) {
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
 * Resolves a batch from Razorpay payment notes, metadata, or link references.
 *
 * @param array $payment Razorpay payment.
 * @param array $link_map Imported link map.
 * @return object|null
 */
function cmp_resolve_batch_from_razorpay_payment( $payment, $link_map = array() ) {
	$meta            = cmp_razorpay_entity_meta( $payment );
	$payment_link_id = cmp_razorpay_payment_link_id_from_payment( $payment );
	$batch_id        = isset( $meta['batch_id'] ) ? absint( $meta['batch_id'] ) : 0;

	if ( $batch_id ) {
		$batch = cmp_get_batch( $batch_id );

		if ( $batch ) {
			return $batch;
		}
	}

	$batch_token = cmp_first_scalar_value(
		array(
			$meta['batch_token'] ?? '',
			$meta['cmp_batch_token'] ?? '',
			$meta['public_token'] ?? '',
		)
	);

	if ( '' !== $batch_token ) {
		$batch = cmp_get_batch_by_token( $batch_token );

		if ( $batch ) {
			return $batch;
		}
	}

	if ( $payment_link_id && isset( $link_map[ $payment_link_id ]['batch'] ) ) {
		return $link_map[ $payment_link_id ]['batch'];
	}

	$references = array(
		$payment_link_id,
		$meta['payment_link'] ?? '',
		$meta['payment_link_id'] ?? '',
		$meta['payment_page_id'] ?? '',
		$meta['razorpay_page_id'] ?? '',
		$meta['page_id'] ?? '',
		$meta['razorpay_link'] ?? '',
		isset( $payment['description'] ) ? $payment['description'] : '',
		isset( $payment['payment_link_reference_id'] ) ? $payment['payment_link_reference_id'] : '',
	);

	$batch = cmp_get_batch_by_razorpay_reference( $references );

	if ( $batch ) {
		return $batch;
	}

	$class_id   = isset( $meta['class_id'] ) ? absint( $meta['class_id'] ) : 0;
	$batch_name = sanitize_text_field( cmp_first_scalar_value( array( $meta['batch_name'] ?? '', $meta['form_name'] ?? '' ) ) );

	if ( $class_id && '' !== $batch_name ) {
		return cmp_find_batch_by_name( $class_id, $batch_name );
	}

	return null;
}

/**
 * Creates or updates a student from imported Razorpay payment context.
 *
 * @param array       $payment Razorpay payment entity.
 * @param object|null $batch Batch object.
 * @return int|WP_Error
 */
function cmp_upsert_import_student_from_payment( $payment, $batch = null ) {
	$notes     = cmp_razorpay_entity_meta( $payment );
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
		$update_result = cmp_update_student(
			(int) $student->id,
			array(
				'name'      => $name,
				'phone'     => $phone,
				'email'     => $email,
				'class_id'  => (int) $batch->class_id,
				'batch_id'  => (int) $batch->id,
				'total_fee' => $student->total_fee > 0 ? (float) $student->total_fee : $total_fee,
				'paid_fee'  => (float) $student->paid_fee,
				'status'    => $student->status,
				'notes'     => $notes_text,
			)
		);

		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

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
	$batch           = cmp_resolve_batch_from_razorpay_payment( $payment, $link_map );

	if ( ! $batch ) {
		$title       = cmp_razorpay_entity_title( $payment );
		$link_result = cmp_import_razorpay_payment_link(
			array(
				'id'          => $payment_link_id,
				'description' => $title,
				'short_url'   => '',
				'amount'      => isset( $payment['amount'] ) ? $payment['amount'] : 0,
				'notes'       => cmp_razorpay_entity_meta( $payment ),
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

	cmp_sync_student_tutor_enrollment( (int) $student_id );

	$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
		? wp_date( 'Y-m-d H:i:s', (int) $payment['created_at'] )
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
		return array( 'status' => 'duplicate' );
	}

	$student_id = cmp_upsert_import_student_from_payment( $payment, $batch );

	if ( is_wp_error( $student_id ) ) {
		return array(
			'status'  => 'error',
			'message' => $student_id->get_error_message(),
		);
	}

	cmp_sync_student_tutor_enrollment( (int) $student_id );

	$amount = cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 );

	if ( $amount <= 0 ) {
		return array( 'status' => 'skipped' );
	}

	$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
		? wp_date( 'Y-m-d H:i:s', (int) $payment['created_at'] )
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
 * Fetches a Razorpay collection and returns an empty array when that endpoint is unavailable.
 *
 * @param string $path API path.
 * @param string $items_key Response item key.
 * @return array
 */
function cmp_razorpay_fetch_optional_collection( $path, $items_key ) {
	$response = cmp_razorpay_fetch_collection( $path, $items_key );

	return is_wp_error( $response ) ? array() : $response;
}

/**
 * Gets Razorpay payment links/pages for selection screens.
 *
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_links_for_admin() {
	$links_response = cmp_razorpay_fetch_collection( 'payment_links/', 'payment_links' );
	$links          = is_wp_error( $links_response ) ? array() : $links_response;
	$pages          = cmp_razorpay_fetch_optional_collection( 'payment_pages/', 'payment_pages' );
	$payments       = cmp_razorpay_fetch_optional_collection( 'payments', 'items' );

	if ( is_wp_error( $links_response ) && empty( $pages ) && empty( $payments ) ) {
		return $links_response;
	}

	$items = array();
	$seen  = array();

	foreach ( array_merge( $links, $pages ) as $item ) {
		$item_id = isset( $item['id'] ) && is_scalar( $item['id'] ) ? sanitize_text_field( $item['id'] ) : '';

		if ( '' === $item_id || isset( $seen[ $item_id ] ) ) {
			continue;
		}

		$seen[ $item_id ] = true;
		$items[]          = $item;
	}

	foreach ( cmp_filter_successful_razorpay_payments( $payments ) as $payment ) {
		$page_id = cmp_razorpay_payment_link_id_from_payment( $payment );

		if ( '' === $page_id || isset( $seen[ $page_id ] ) ) {
			continue;
		}

		$seen[ $page_id ] = true;
		$items[]          = array(
			'id'          => $page_id,
			'description' => __( 'Razorpay Payment Page', 'class-manager-pro' ),
			'notes'       => array(
				'form_name' => $page_id,
			),
		);
	}

	return $items;
}

/**
 * Fetches a single Razorpay payment link/page.
 *
 * @param string $page_id Payment link/page ID.
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_link( $page_id ) {
	$page_id = sanitize_text_field( $page_id );

	if ( '' === $page_id ) {
		return new WP_Error( 'cmp_razorpay_page_missing', __( 'Please enter or choose a Razorpay page ID.', 'class-manager-pro' ) );
	}

	$response = cmp_razorpay_api_get( 'payment_links/' . rawurlencode( $page_id ) );

	if ( ! is_wp_error( $response ) ) {
		return $response;
	}

	$page_response = cmp_razorpay_api_get( 'payment_pages/' . rawurlencode( $page_id ) );

	if ( ! is_wp_error( $page_response ) ) {
		return $page_response;
	}

	return array(
		'id'          => $page_id,
		'description' => __( 'Razorpay Payment Page', 'class-manager-pro' ),
		'notes'       => array(
			'form_name' => $page_id,
		),
	);
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
 * Parses a sync date field into a Unix timestamp.
 *
 * @param string $value Date value.
 * @param string $boundary start|end.
 * @return int
 */
function cmp_parse_sync_date_to_timestamp( $value, $boundary = 'start' ) {
	$value = sanitize_text_field( $value );

	if ( '' === $value ) {
		return 0;
	}

	$date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value, wp_timezone() );

	if ( ! $date ) {
		return 0;
	}

	if ( 'end' === $boundary ) {
		$date = $date->setTime( 23, 59, 59 );
	} else {
		$date = $date->setTime( 0, 0, 0 );
	}

	return (int) $date->getTimestamp();
}

/**
 * Fetches captured Razorpay payments with created_at and notes/metadata filters.
 *
 * @param array $args Sync filters.
 * @return array|WP_Error
 */
function cmp_get_razorpay_payments_for_sync( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'from'   => 0,
			'to'     => 0,
			'search' => '',
		)
	);

	$items = array();
	$skip  = 0;
	$count = 100;

	do {
		$query = array(
			'count' => $count,
			'skip'  => $skip,
		);

		if ( ! empty( $args['from'] ) ) {
			$query['from'] = absint( $args['from'] );
		}

		if ( ! empty( $args['to'] ) ) {
			$query['to'] = absint( $args['to'] );
		}

		$response = cmp_razorpay_api_get( 'payments', $query );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$page_items = isset( $response['items'] ) && is_array( $response['items'] ) ? $response['items'] : array();

		$items = array_merge( $items, cmp_filter_successful_razorpay_payments( $page_items, '', $args['search'] ) );
		$skip += count( $page_items );
	} while ( count( $page_items ) === $count );

	return $items;
}

/**
 * Imports captured Razorpay payments from the payments API.
 *
 * @param array $args Sync filters.
 * @return array|WP_Error
 */
function cmp_process_razorpay_payment_sync( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'from'          => 0,
			'to'            => 0,
			'search'        => '',
			'lookback_days' => absint( get_option( 'cmp_automation_sync_lookback_days', 7 ) ),
			'source'        => 'manual',
			'fixed_class_id' => 0,
			'fixed_batch_id' => 0,
		)
	);

	$target_batch = cmp_get_sync_target_batch( $args['fixed_class_id'], $args['fixed_batch_id'] );

	if ( is_wp_error( $target_batch ) ) {
		cmp_log_event(
			'error',
			'Razorpay sync failed because a target batch was not configured.',
			array(
				'message' => $target_batch->get_error_message(),
				'source'  => $args['source'],
			)
		);

		return $target_batch;
	}

	if ( empty( $args['from'] ) ) {
		$last_sync = sanitize_text_field( (string) get_option( 'cmp_last_razorpay_sync_at', '' ) );

		if ( '' !== $last_sync ) {
			$args['from'] = max( 0, strtotime( '-1 hour', strtotime( $last_sync ) ) );
		}
	}

	if ( empty( $args['from'] ) ) {
		$args['from'] = strtotime( '-' . max( 1, absint( $args['lookback_days'] ) ) . ' days', current_time( 'timestamp' ) );
	}

	if ( empty( $args['to'] ) ) {
		$args['to'] = current_time( 'timestamp' );
	}

	$payments = cmp_get_razorpay_payments_for_sync( $args );

	if ( is_wp_error( $payments ) ) {
		cmp_log_event(
			'error',
			'Razorpay sync failed while fetching payments.',
			array(
				'message' => $payments->get_error_message(),
				'source'  => $args['source'],
			)
		);

		return $payments;
	}

	$summary = array(
		'fetched'   => count( $payments ),
		'imported'  => 0,
		'duplicate' => 0,
		'skipped'   => 0,
		'failed'    => 0,
	);

	foreach ( $payments as $payment ) {
		$result = cmp_import_razorpay_payment_into_batch( $payment, $target_batch );
		$status = isset( $result['status'] ) ? $result['status'] : 'failed';

		if ( isset( $summary[ $status ] ) ) {
			++$summary[ $status ];
		} else {
			++$summary['failed'];
		}

		if ( 'error' === $status && ! empty( $result['message'] ) ) {
			cmp_log_event(
				'error',
				'Razorpay payment import failed during sync.',
				array(
					'message'    => $result['message'],
					'payment_id' => isset( $payment['id'] ) ? $payment['id'] : '',
					'source'     => $args['source'],
				)
			);
		}
	}

	update_option( 'cmp_last_razorpay_sync_at', cmp_current_datetime() );
	update_option( 'cmp_last_razorpay_sync_summary', $summary );

	return $summary;
}

/**
 * Extracts displayable student data from a Razorpay payment.
 *
 * @param array $payment Razorpay payment.
 * @return array
 */
function cmp_payment_student_preview( $payment ) {
	$notes = cmp_razorpay_entity_meta( $payment );
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
		'date'   => isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $payment['created_at'] ) : '',
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

	$batch_update = cmp_update_batch(
		(int) $batch->id,
		array(
			'class_id'         => (int) $batch->class_id,
			'batch_name'       => $batch->batch_name,
			'course_id'        => (int) $batch->course_id,
			'start_date'       => $batch->start_date,
			'fee_due_date'     => $batch->fee_due_date,
			'status'           => $batch->status,
			'razorpay_link'    => isset( $link['short_url'] ) ? esc_url_raw( $link['short_url'] ) : $batch->razorpay_link,
			'batch_fee'        => (float) $batch->batch_fee,
			'is_free'          => (int) $batch->is_free,
			'teacher_user_id'  => (int) $batch->teacher_user_id,
			'intake_limit'     => (int) $batch->intake_limit,
			'class_days'       => $batch->class_days,
			'manual_income'    => (float) $batch->manual_income,
			'razorpay_page_id' => $page_id,
		)
	);

	if ( is_wp_error( $batch_update ) ) {
		return $batch_update;
	}

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
 * Normalizes a batch-sheet import header to an internal key.
 *
 * @param string $header Header text.
 * @return string
 */
function cmp_batch_sheet_header_key( $header ) {
	$key = strtolower( sanitize_text_field( ltrim( (string) $header, "\xEF\xBB\xBF" ) ) );
	$key = preg_replace( '/[^a-z0-9]+/', '_', $key );
	$key = trim( preg_replace( '/_+/', '_', $key ), '_' );

	$map = array(
		'batch_no'         => 'batch_no',
		'batch_number'     => 'batch_no',
		'batch'            => 'batch_no',
		'batch_date'       => 'batch_date',
		'date'             => 'batch_date',
		'batch_addmition'  => 'batch_admission',
		'batch_admission'  => 'batch_admission',
		'admission'        => 'batch_admission',
		'intake'           => 'batch_admission',
		'class_fee'        => 'class_fee',
		'fee'              => 'class_fee',
		'total_fee'        => 'total_fee',
		'total_income'     => 'total_fee',
		'ad_expainse'      => 'ad_expense',
		'ad_expense'       => 'ad_expense',
		'meta_ads'         => 'ad_expense',
		'teacher_payment'  => 'teacher_payment',
		'net_profit'       => 'net_profit',
	);

	return isset( $map[ $key ] ) ? $map[ $key ] : $key;
}

/**
 * Parses a batch-sheet date value.
 *
 * @param string $value Raw date text.
 * @return string|null
 */
function cmp_parse_batch_sheet_date( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );

	if ( '' === $value || '-' === $value ) {
		return null;
	}

	$timestamp = strtotime( $value );

	if ( ! $timestamp && preg_match( '/^\d{1,2}\s+[A-Za-z]+$/', $value ) ) {
		$timestamp = strtotime( $value . ' ' . current_time( 'Y' ) );
	}

	if ( ! $timestamp ) {
		return null;
	}

	return date( 'Y-m-d', $timestamp );
}

/**
 * Parses uploaded/pasted batch-sheet data into rows.
 *
 * @param array $lines Raw CSV/TSV lines.
 * @return array
 */
function cmp_parse_batch_sheet_lines( $lines ) {
	$rows    = array();
	$headers = array();

	foreach ( $lines as $index => $line ) {
		$line = is_scalar( $line ) ? trim( (string) $line ) : '';

		if ( '' === $line ) {
			continue;
		}

		$delimiter = false !== strpos( $line, "\t" ) ? "\t" : ',';
		$values    = str_getcsv( $line, $delimiter );
		$values    = array_map(
			static function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			},
			(array) $values
		);

		if ( empty( $headers ) ) {
			$headers = array_map( 'cmp_batch_sheet_header_key', $values );
			continue;
		}

		$row = array();

		foreach ( $headers as $column_index => $header_key ) {
			if ( '' === $header_key ) {
				continue;
			}

			$row[ $header_key ] = isset( $values[ $column_index ] ) ? $values[ $column_index ] : '';
		}

		if ( ! empty( $row ) ) {
			$rows[] = $row;
		}
	}

	return $rows;
}

/**
 * Imports one batch summary row from a sheet.
 *
 * @param int   $class_id Class ID.
 * @param array $row Parsed row data.
 * @return array|WP_Error
 */
function cmp_import_batch_sheet_row( $class_id, $row ) {
	global $wpdb;

	$class_id        = absint( $class_id );
	$batch_number    = sanitize_text_field( isset( $row['batch_no'] ) ? $row['batch_no'] : '' );
	$batch_name      = '' !== $batch_number ? ( is_numeric( $batch_number ) ? 'Batch ' . absint( $batch_number ) : cmp_clean_title_text( $batch_number ) ) : '';
	$start_date      = cmp_parse_batch_sheet_date( isset( $row['batch_date'] ) ? $row['batch_date'] : '' );
	$intake_limit    = absint( isset( $row['batch_admission'] ) ? $row['batch_admission'] : 0 );
	$batch_fee       = cmp_money_value( isset( $row['class_fee'] ) ? $row['class_fee'] : 0 );
	$manual_income   = cmp_money_value( isset( $row['total_fee'] ) ? $row['total_fee'] : 0 );
	$ad_expense      = cmp_money_value( isset( $row['ad_expense'] ) ? $row['ad_expense'] : 0 );
	$teacher_payment = cmp_money_value( isset( $row['teacher_payment'] ) ? $row['teacher_payment'] : 0 );

	if ( '' === $batch_name || ! $class_id ) {
		return new WP_Error( 'cmp_batch_sheet_invalid_row', __( 'Batch number and class are required for sheet import.', 'class-manager-pro' ) );
	}

	if ( $manual_income <= 0 && $batch_fee > 0 && $intake_limit > 0 ) {
		$manual_income = round( $batch_fee * $intake_limit, 2 );
	}

	$class = cmp_get_class( $class_id );

	if ( $class && $batch_fee > 0 && (float) $class->total_fee <= 0 ) {
		cmp_update_class(
			$class_id,
			array(
				'name'        => $class->name,
				'description' => $class->description,
				'total_fee'   => $batch_fee,
			)
		);
	}

	$batch_result = cmp_ensure_import_batch(
		$class_id,
		array(
			'batch_name'     => $batch_name,
			'start_date'     => $start_date,
			'status'         => 'active',
			'batch_fee'      => $batch_fee,
			'intake_limit'   => $intake_limit,
			'manual_income'  => $manual_income,
		)
	);

	if ( is_wp_error( $batch_result ) ) {
		return $batch_result;
	}

	$batch    = cmp_get_batch( (int) $batch_result['batch_id'] );
	$note_tag = __( 'Imported from batch sheet.', 'class-manager-pro' );

	foreach ( array( 'meta_ads' => $ad_expense, 'teacher_payment' => $teacher_payment ) as $category => $amount ) {
		$wpdb->delete(
			cmp_table( 'expenses' ),
			array(
				'batch_id'     => (int) $batch->id,
				'category'     => $category,
				'expense_date' => $start_date ? $start_date : current_time( 'Y-m-d' ),
				'notes'        => $note_tag,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( $amount > 0 ) {
			$expense_result = cmp_insert_expense(
				array(
					'batch_id'     => (int) $batch->id,
					'category'     => $category,
					'amount'       => $amount,
					'expense_date' => $start_date ? $start_date : current_time( 'Y-m-d' ),
					'notes'        => $note_tag,
				)
			);

			if ( is_wp_error( $expense_result ) ) {
				cmp_log_event( 'error', 'Batch sheet expense import failed.', array( 'batch_id' => (int) $batch->id, 'category' => $category ) );
			}
		}
	}

	return array(
		'batch_id'   => (int) $batch->id,
		'batch_name' => $batch_name,
		'created'    => ! empty( $batch_result['created'] ),
	);
}

/**
 * Downloads a CSV template for batch-sheet import.
 */
function cmp_handle_download_batch_import_template() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_download_batch_import_template' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=cmp-batch-import-template.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'Batch No.', 'Batch Date', 'Batch Addmition', 'Class Fee', 'Total Fee', 'Ad Expainse', 'Teacher Payment', 'Net Profit' ) );
	fputcsv( $output, array( '3', '15 March', '8', '1000', '8000', '500', '', '7500' ) );
	fputcsv( $output, array( '4', '4 April', '24', '1000', '24000', '1000', '', '23000' ) );
	fclose( $output );
	exit;
}

/**
 * Imports old batch summary data from CSV or pasted sheet rows.
 */
function cmp_handle_import_batch_sheet_data() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_import_batch_sheet_data' );

	$class_id       = absint( cmp_field( $_POST, 'import_class_id', 0 ) );
	$new_class_name = sanitize_text_field( cmp_field( $_POST, 'import_new_class_name' ) );
	$pasted_rows    = trim( (string) cmp_field( $_POST, 'sheet_rows_text' ) );

	if ( ! $class_id && '' === $new_class_name ) {
		cmp_redirect( 'cmp-batches', __( 'Choose an existing class or enter a new class name before importing.', 'class-manager-pro' ), 'error' );
	}

	if ( ! $class_id ) {
		$class_id = cmp_ensure_import_class( $new_class_name, __( 'Created from batch summary sheet import.', 'class-manager-pro' ) );

		if ( is_wp_error( $class_id ) ) {
			cmp_redirect( 'cmp-batches', $class_id->get_error_message(), 'error' );
		}
	}

	$lines = array();

	if ( '' !== $pasted_rows ) {
		$lines = preg_split( '/\r\n|\r|\n/', $pasted_rows );
	} elseif ( ! empty( $_FILES['sheet_file']['tmp_name'] ) && is_uploaded_file( $_FILES['sheet_file']['tmp_name'] ) ) {
		$lines = file( $_FILES['sheet_file']['tmp_name'], FILE_IGNORE_NEW_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file
	}

	if ( empty( $lines ) ) {
		cmp_redirect( 'cmp-batches', __( 'Upload a CSV file or paste sheet rows to import old batch data.', 'class-manager-pro' ), 'error' );
	}

	$rows = cmp_parse_batch_sheet_lines( $lines );

	if ( empty( $rows ) ) {
		cmp_redirect( 'cmp-batches', __( 'No valid rows were found in the uploaded sheet.', 'class-manager-pro' ), 'error' );
	}

	$summary = array(
		'imported' => 0,
		'failed'   => 0,
	);

	foreach ( $rows as $row ) {
		$result = cmp_import_batch_sheet_row( $class_id, $row );

		if ( is_wp_error( $result ) ) {
			++$summary['failed'];
			cmp_log_event( 'error', 'Batch sheet row import failed.', array( 'message' => $result->get_error_message(), 'row' => $row ) );
		} else {
			++$summary['imported'];
		}
	}

	cmp_redirect(
		'cmp-batches',
		sprintf(
			/* translators: 1: imported count 2: failed count */
			__( 'Batch sheet import completed. %1$d rows imported, %2$d failed.', 'class-manager-pro' ),
			(int) $summary['imported'],
			(int) $summary['failed']
		),
		0 === (int) $summary['failed'] ? 'success' : 'error'
	);
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
		$response = cmp_get_razorpay_payment_link( $razorpay_id );
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

	$confirmation = strtoupper( trim( sanitize_text_field( cmp_field( $_POST, 'reset_confirmation' ) ) ) );

	if ( 'DELETE' !== $confirmation ) {
		cmp_redirect( 'cmp-settings', __( 'Type DELETE to confirm clearing all Class Manager Pro data.', 'class-manager-pro' ), 'error' );
	}

	foreach ( array( 'teacher_logs', 'admin_logs', 'activity_logs', 'reminders', 'attendance', 'expenses', 'payments', 'students', 'batches', 'classes' ) as $table ) {
		$table_name = cmp_table( $table );

		if ( ! cmp_table_exists( $table_name ) ) {
			continue;
		}

		$deleted = $wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $deleted ) {
			cmp_redirect( 'cmp-settings', __( 'Could not delete all plugin data. Please try again.', 'class-manager-pro' ), 'error' );
		}

		$wpdb->query( "ALTER TABLE {$table_name} AUTO_INCREMENT = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
	$channels = cmp_clean_enum( cmp_field( $_POST, 'notification_channels', 'both' ), array( 'sms', 'whatsapp', 'both', 'email', 'sms_email', 'all' ), 'both' );
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
	update_option( 'cmp_email_subject', sanitize_text_field( cmp_field( $_POST, 'email_subject' ) ) );
	update_option( 'cmp_email_template', sanitize_textarea_field( cmp_field( $_POST, 'email_template' ) ) );

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
 * Gets all-time attendance totals for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return array
 */
function cmp_get_batch_attendance_totals( $batch_id ) {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT status, COUNT(*) AS total FROM ' . cmp_table( 'attendance' ) . ' WHERE batch_id = %d GROUP BY status',
			absint( $batch_id )
		),
		OBJECT_K
	);

	$present = isset( $rows['present'] ) ? (int) $rows['present']->total : 0;
	$absent  = isset( $rows['absent'] ) ? (int) $rows['absent']->total : 0;
	$leave   = isset( $rows['leave'] ) ? (int) $rows['leave']->total : 0;
	$total   = $present + $absent + $leave;

	return array(
		'present' => $present,
		'absent'  => $absent,
		'leave'   => $leave,
		'total'   => $total,
		'rate'    => $total > 0 ? round( ( $present / $total ) * 100, 2 ) : 0,
	);
}

/**
 * Gets student attendance totals for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return array
 */
function cmp_get_student_attendance_totals_for_batch( $batch_id ) {
	global $wpdb;

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT s.id, s.unique_id, s.name, s.phone,
				SUM(CASE WHEN a.status = \'present\' THEN 1 ELSE 0 END) AS present_count,
				SUM(CASE WHEN a.status = \'absent\' THEN 1 ELSE 0 END) AS absent_count,
				SUM(CASE WHEN a.status = \'leave\' THEN 1 ELSE 0 END) AS leave_count,
				COUNT(a.id) AS total_marked
			FROM ' . cmp_table( 'students' ) . ' s
			LEFT JOIN ' . cmp_table( 'attendance' ) . ' a ON a.student_id = s.id AND a.batch_id = s.batch_id
			WHERE s.batch_id = %d
			GROUP BY s.id, s.unique_id, s.name, s.phone
			ORDER BY s.name ASC',
			absint( $batch_id )
		)
	);
}

/**
 * Gets attendance analytics grouped by batch.
 *
 * @return array
 */
function cmp_get_attendance_batch_report() {
	global $wpdb;

	return $wpdb->get_results(
		'SELECT b.id, b.batch_name, c.name AS class_name,
			COUNT(DISTINCT s.id) AS student_count,
			SUM(CASE WHEN a.status = \'present\' THEN 1 ELSE 0 END) AS present_count,
			SUM(CASE WHEN a.status = \'absent\' THEN 1 ELSE 0 END) AS absent_count,
			SUM(CASE WHEN a.status = \'leave\' THEN 1 ELSE 0 END) AS leave_count,
			COUNT(a.id) AS total_marked
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.batch_id = b.id
		LEFT JOIN ' . cmp_table( 'attendance' ) . ' a ON a.batch_id = b.id AND a.student_id = s.id
		GROUP BY b.id, b.batch_name, c.name
		ORDER BY b.created_at DESC, b.id DESC'
	);
}

/**
 * Gets attendance analytics grouped by class.
 *
 * @return array
 */
function cmp_get_attendance_class_report() {
	global $wpdb;

	return $wpdb->get_results(
		'SELECT c.id, c.name AS class_name,
			COUNT(DISTINCT b.id) AS batch_count,
			COUNT(DISTINCT s.id) AS student_count,
			SUM(CASE WHEN a.status = \'present\' THEN 1 ELSE 0 END) AS present_count,
			SUM(CASE WHEN a.status = \'absent\' THEN 1 ELSE 0 END) AS absent_count,
			SUM(CASE WHEN a.status = \'leave\' THEN 1 ELSE 0 END) AS leave_count,
			COUNT(a.id) AS total_marked
		FROM ' . cmp_table( 'classes' ) . ' c
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.class_id = c.id
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.batch_id = b.id
		LEFT JOIN ' . cmp_table( 'attendance' ) . ' a ON a.batch_id = b.id AND a.student_id = s.id
		GROUP BY c.id, c.name
		ORDER BY c.name ASC'
	);
}

/**
 * Gets attendance analytics grouped by student.
 *
 * @return array
 */
function cmp_get_attendance_student_report() {
	global $wpdb;

	return $wpdb->get_results(
		'SELECT s.id, s.unique_id, s.name, s.phone, c.name AS class_name, b.batch_name,
			SUM(CASE WHEN a.status = \'present\' THEN 1 ELSE 0 END) AS present_count,
			SUM(CASE WHEN a.status = \'absent\' THEN 1 ELSE 0 END) AS absent_count,
			SUM(CASE WHEN a.status = \'leave\' THEN 1 ELSE 0 END) AS leave_count,
			COUNT(a.id) AS total_marked
		FROM ' . cmp_table( 'students' ) . ' s
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
		LEFT JOIN ' . cmp_table( 'attendance' ) . ' a ON a.batch_id = s.batch_id AND a.student_id = s.id
		GROUP BY s.id, s.unique_id, s.name, s.phone, c.name, b.batch_name
		ORDER BY s.name ASC'
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
	check_admin_referer( 'cmp_save_attendance' );

	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	$date     = sanitize_text_field( cmp_field( $_POST, 'attendance_date', current_time( 'Y-m-d' ) ) );
	$entries  = isset( $_POST['attendance'] ) && is_array( $_POST['attendance'] ) ? wp_unslash( $_POST['attendance'] ) : array();
	$page     = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-batches' ), 'cmp-batches' );

	if ( ! $batch_id || ! cmp_current_user_can_manage_batch_attendance( $batch_id ) ) {
		wp_die( esc_html__( 'You do not have permission to save attendance for this batch.', 'class-manager-pro' ) );
	}

	cmp_save_batch_attendance( $batch_id, $date, $entries );

	cmp_redirect(
		$page,
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
 * Saves batch attendance via AJAX for the batch quick view.
 *
 * @return void
 */
function cmp_ajax_save_attendance_quick() {
	check_ajax_referer( 'cmp_save_attendance', 'nonce' );

	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	$date     = sanitize_text_field( cmp_field( $_POST, 'attendance_date', current_time( 'Y-m-d' ) ) );
	$entries  = isset( $_POST['attendance'] ) && is_array( $_POST['attendance'] ) ? wp_unslash( $_POST['attendance'] ) : array();

	if ( ! $batch_id || ! cmp_current_user_can_manage_batch_attendance( $batch_id ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to save attendance for this batch.', 'class-manager-pro' ),
			),
			403
		);
	}

	cmp_save_batch_attendance( $batch_id, $date, $entries );

	wp_send_json_success(
		array(
			'message' => __( 'Attendance saved successfully.', 'class-manager-pro' ),
			'summary' => cmp_get_batch_attendance_summary( $batch_id, $date ),
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
 * @param string $channel sms|whatsapp|email.
 * @return string
 */
function cmp_build_fee_reminder_message( $student, $channel ) {
	if ( 'whatsapp' === $channel ) {
		$template = (string) get_option( 'cmp_whatsapp_template', '' );
	} elseif ( 'email' === $channel ) {
		$template = (string) get_option( 'cmp_email_template', '' );
	} else {
		$template = (string) get_option( 'cmp_sms_template', '' );
	}

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
 * Builds an email subject for fee reminders.
 *
 * @param object $student Student row.
 * @return string
 */
function cmp_build_fee_reminder_subject( $student ) {
	$template = (string) get_option( 'cmp_email_subject', '' );

	if ( '' === trim( $template ) ) {
		$template = __( 'Fee reminder for {class_name} - {batch_name}', 'class-manager-pro' );
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
 * Expands a reminder channel setting into channel slugs.
 *
 * @param string $setting Saved channel setting.
 * @return array
 */
function cmp_notification_channels_from_setting( $setting ) {
	switch ( sanitize_key( $setting ) ) {
		case 'sms':
			return array( 'sms' );
		case 'whatsapp':
			return array( 'whatsapp' );
		case 'email':
			return array( 'email' );
		case 'sms_email':
			return array( 'sms', 'email' );
		case 'all':
			return array( 'sms', 'whatsapp', 'email' );
		case 'both':
		default:
			return array( 'sms', 'whatsapp' );
	}
}

/**
 * Returns a mailto link for a student's fee reminder.
 *
 * @param object $student Student row.
 * @return string
 */
function cmp_get_email_reminder_url( $student ) {
	if ( empty( $student->email ) ) {
		return '';
	}

	return 'mailto:' . rawurlencode( sanitize_email( $student->email ) ) . '?subject=' . rawurlencode( cmp_build_fee_reminder_subject( $student ) ) . '&body=' . rawurlencode( cmp_build_fee_reminder_message( $student, 'email' ) );
}

/**
 * Returns a WhatsApp reminder URL for a student.
 *
 * @param object $student Student row.
 * @return string
 */
function cmp_get_whatsapp_reminder_url( $student ) {
	$phone_keys = isset( $student->phone ) ? cmp_phone_match_keys( $student->phone ) : array();

	if ( empty( $phone_keys ) ) {
		return '';
	}

	return 'https://wa.me/' . rawurlencode( $phone_keys[0] ) . '?text=' . rawurlencode( cmp_build_fee_reminder_message( $student, 'whatsapp' ) );
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

	if ( 'email' === $channel ) {
		if ( '' === trim( (string) $student->email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Student email address is missing.', 'class-manager-pro' ),
			);
		}

		$sent = wp_mail( sanitize_email( $student->email ), cmp_build_fee_reminder_subject( $student ), $message );

		return array(
			'success' => (bool) $sent,
			'message' => $sent ? __( 'Email reminder sent.', 'class-manager-pro' ) : __( 'Email reminder could not be sent.', 'class-manager-pro' ),
		);
	}

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
	$channels        = cmp_notification_channels_from_setting( $channel_setting );

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
