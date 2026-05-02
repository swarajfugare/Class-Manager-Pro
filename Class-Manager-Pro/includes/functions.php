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
add_action( 'admin_post_cmp_delete_payment', 'cmp_handle_delete_payment' );
add_action( 'admin_post_cmp_restore_payment', 'cmp_handle_restore_payment' );
add_action( 'admin_post_cmp_force_delete_payment', 'cmp_handle_force_delete_payment' );
add_action( 'admin_post_cmp_save_settings', 'cmp_handle_save_settings' );
add_action( 'admin_post_cmp_import_detected_razorpay_keys', 'cmp_handle_import_detected_razorpay_keys' );
add_action( 'admin_post_cmp_manual_import_razorpay', 'cmp_handle_manual_import_razorpay' );
add_action( 'admin_post_cmp_import_razorpay_page_to_batch', 'cmp_handle_import_razorpay_page_to_batch' );
add_action( 'admin_post_cmp_import_batch_sheet_data', 'cmp_handle_import_batch_sheet_data' );
add_action( 'admin_post_cmp_import_students_file', 'cmp_handle_import_students_file' );
add_action( 'admin_post_cmp_download_batch_import_template', 'cmp_handle_download_batch_import_template' );
add_action( 'admin_post_cmp_save_expense', 'cmp_handle_save_expense' );
add_action( 'admin_post_cmp_delete_expense', 'cmp_handle_delete_expense' );
add_action( 'admin_post_cmp_save_sms_settings', 'cmp_handle_save_sms_settings' );
add_action( 'admin_post_cmp_send_fee_reminders', 'cmp_handle_send_fee_reminders' );
add_action( 'admin_post_cmp_send_student_follow_up_email', 'cmp_handle_send_student_follow_up_email' );
add_action( 'admin_post_cmp_save_attendance_settings', 'cmp_handle_save_attendance_settings' );
add_action( 'admin_post_cmp_save_attendance', 'cmp_handle_save_attendance' );
add_action( 'admin_post_cmp_sync_razorpay_payments', 'cmp_handle_sync_razorpay_payments' );
add_action( 'admin_post_cmp_enroll_student_next_course', 'cmp_handle_enroll_student_next_course' );
add_action( 'admin_init', 'cmp_handle_csv_export' );
add_action( 'init', 'cmp_schedule_daily_fee_reminders' );
add_action( 'init', 'cmp_disable_razorpay_payment_sync_automation' );
add_action( 'cmp_daily_fee_reminders', 'cmp_run_scheduled_fee_reminders' );
add_action( 'wp_ajax_cmp_filter_all_data', 'cmp_ajax_filter_all_data' );
add_action( 'wp_ajax_cmp_filter_students', 'cmp_ajax_filter_students' );
add_action( 'wp_ajax_cmp_delete_item', 'cmp_delete_item' );
add_action( 'wp_ajax_cmp_admin_entity_action', 'cmp_ajax_admin_entity_action' );
add_action( 'wp_ajax_cmp_bulk_student_action', 'cmp_ajax_bulk_student_action' );
add_action( 'wp_ajax_cmp_save_attendance_quick', 'cmp_ajax_save_attendance_quick' );
add_action( 'wp_ajax_cmp_send_student_follow_up_email', 'cmp_ajax_send_student_follow_up_email' );
add_action( 'wp_ajax_cmp_import_razorpay_page_to_batch', 'cmp_ajax_import_razorpay_page_to_batch' );
add_action( 'wp_ajax_cmp_import_students_file', 'cmp_ajax_import_students_file' );
add_action( 'wp_ajax_cmp_process_student_import_chunk', 'cmp_ajax_process_student_import_chunk' );

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
 * Stores a payment operation notice for the current request.
 *
 * @param string $message Notice message.
 * @param string $type Notice type.
 * @return void
 */
function cmp_set_payment_operation_notice( $message, $type = 'warning' ) {
	$GLOBALS['cmp_payment_operation_notice'] = array(
		'message' => sanitize_text_field( $message ),
		'type'    => sanitize_key( $type ),
	);
}

/**
 * Pops a payment operation notice for the current request.
 *
 * @return array
 */
function cmp_pop_payment_operation_notice() {
	$notice = isset( $GLOBALS['cmp_payment_operation_notice'] ) && is_array( $GLOBALS['cmp_payment_operation_notice'] )
		? $GLOBALS['cmp_payment_operation_notice']
		: array();

	unset( $GLOBALS['cmp_payment_operation_notice'] );

	return array(
		'message' => sanitize_text_field( cmp_field( $notice, 'message' ) ),
		'type'    => sanitize_key( cmp_field( $notice, 'type', 'warning' ) ),
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
 * Captures a normalized payment snapshot for audit history.
 *
 * @param object|array|null $payment Payment row.
 * @return array
 */
function cmp_get_payment_audit_snapshot( $payment ) {
	if ( is_array( $payment ) ) {
		$payment = (object) $payment;
	}

	if ( ! is_object( $payment ) ) {
		return array();
	}

	return array(
		'id'             => isset( $payment->id ) ? (int) $payment->id : 0,
		'student_id'     => isset( $payment->student_id ) ? (int) $payment->student_id : 0,
		'class_id'       => isset( $payment->class_id ) ? (int) $payment->class_id : 0,
		'batch_id'       => isset( $payment->batch_id ) ? (int) $payment->batch_id : 0,
		'amount'         => isset( $payment->amount ) ? round( (float) $payment->amount, 2 ) : 0,
		'payment_mode'   => isset( $payment->payment_mode ) ? sanitize_key( $payment->payment_mode ) : '',
		'transaction_id' => isset( $payment->transaction_id ) ? sanitize_text_field( $payment->transaction_id ) : '',
		'payment_date'   => isset( $payment->payment_date ) ? sanitize_text_field( $payment->payment_date ) : '',
		'is_deleted'     => ! empty( $payment->is_deleted ) ? 1 : 0,
	);
}

/**
 * Stores a payment audit entry.
 *
 * @param int              $payment_id Payment ID.
 * @param int              $student_id Student ID.
 * @param string           $action_type Action type.
 * @param object|array|null $old_value Old payment snapshot.
 * @param object|array|null $new_value New payment snapshot.
 * @return int|WP_Error
 */
function cmp_log_payment_audit( $payment_id, $student_id, $action_type, $old_value = null, $new_value = null ) {
	global $wpdb;

	$result = $wpdb->insert(
		cmp_table( 'payment_audit_logs' ),
		array(
			'payment_id'     => absint( $payment_id ),
			'student_id'     => absint( $student_id ),
			'old_value'      => ! empty( $old_value ) ? wp_json_encode( cmp_get_payment_audit_snapshot( $old_value ) ) : '',
			'new_value'      => ! empty( $new_value ) ? wp_json_encode( cmp_get_payment_audit_snapshot( $new_value ) ) : '',
			'action_type'    => sanitize_key( $action_type ),
			'admin_user_id'  => get_current_user_id(),
			'created_at'     => cmp_current_datetime(),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_payment_audit_failed', __( 'Could not save payment audit history.', 'class-manager-pro' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Returns payment audit rows.
 *
 * @param int $payment_id Payment ID.
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_payment_audit_history( $payment_id, $limit = 20 ) {
	global $wpdb;

	$payment_id = absint( $payment_id );
	$limit      = max( 1, absint( $limit ) );

	if ( ! $payment_id ) {
		return array();
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT a.*, u.display_name AS admin_name
			FROM ' . cmp_table( 'payment_audit_logs' ) . ' a
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = a.admin_user_id
			WHERE a.payment_id = %d
			ORDER BY a.created_at DESC, a.id DESC
			LIMIT %d',
			$payment_id,
			$limit
		)
	);
}

/**
 * Formats a payment audit snapshot for admin tables.
 *
 * @param string $value Stored audit snapshot JSON.
 * @return string
 */
function cmp_get_payment_audit_snapshot_summary( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';

	if ( '' === trim( $value ) ) {
		return __( 'Not available', 'class-manager-pro' );
	}

	$data = json_decode( $value, true );

	if ( ! is_array( $data ) ) {
		return sanitize_text_field( $value );
	}

	$parts = array(
		sprintf(
			/* translators: %s: payment amount */
			__( 'Amount: %s', 'class-manager-pro' ),
			cmp_format_money( cmp_field( $data, 'amount', 0 ) )
		),
		sprintf(
			/* translators: %s: payment mode */
			__( 'Mode: %s', 'class-manager-pro' ),
			ucfirst( sanitize_key( cmp_field( $data, 'payment_mode', 'manual' ) ) )
		),
		sprintf(
			/* translators: %s: payment date */
			__( 'Date: %s', 'class-manager-pro' ),
			sanitize_text_field( cmp_field( $data, 'payment_date', __( 'Not set', 'class-manager-pro' ) ) )
		),
	);

	if ( (float) cmp_field( $data, 'charge_amount', 0 ) > 0 ) {
		$parts[] = sprintf(
			/* translators: %s: payment final amount */
			__( 'Final: %s', 'class-manager-pro' ),
			cmp_format_money( cmp_field( $data, 'final_amount', cmp_field( $data, 'amount', 0 ) ) )
		);
	}

	$transaction_id = sanitize_text_field( cmp_field( $data, 'transaction_id' ) );

	if ( '' !== $transaction_id ) {
		$parts[] = sprintf(
			/* translators: %s: transaction ID */
			__( 'Transaction: %s', 'class-manager-pro' ),
			$transaction_id
		);
	}

	if ( ! empty( $data['is_deleted'] ) ) {
		$parts[] = __( 'Status: Trashed', 'class-manager-pro' );
	}

	return implode( ' | ', $parts );
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

	$where[] = cmp_get_failed_payment_placeholder_student_where_sql( 's' );

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
 * Returns a reusable SQL condition that excludes legacy failed-payment placeholder students.
 *
 * @param string $student_alias Students table alias.
 * @return string
 */
function cmp_get_failed_payment_placeholder_student_where_sql( $student_alias = 's' ) {
	$student_alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $student_alias );

	if ( '' === $student_alias ) {
		$student_alias = 's';
	}

	return '(' . $student_alias . '.id IS NULL OR NOT (
		COALESCE(' . $student_alias . '.batch_id, 0) = 0
		AND COALESCE(' . $student_alias . '.paid_fee, 0) <= 0
		AND (
			LOWER(COALESCE(' . $student_alias . '.notes, \'\')) LIKE \'%payment import status: failed%\'
			OR LOWER(COALESCE(' . $student_alias . '.notes, \'\')) LIKE \'%pending payment import student%\'
		)
	))';
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
			'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) . ' WHERE student_id = %d AND is_deleted = 0',
			absint( $student_id )
		)
	);
}

/**
 * Returns the SQL expression for a payment's class context.
 *
 * @param string $payment_alias Payment table alias.
 * @param string $student_alias Student table alias.
 * @return string
 */
function cmp_payment_class_sql( $payment_alias = 'p', $student_alias = 's' ) {
	return 'COALESCE(NULLIF(' . $payment_alias . '.class_id, 0), ' . $student_alias . '.class_id, 0)';
}

/**
 * Returns the SQL expression for a payment's batch context.
 *
 * @param string $payment_alias Payment table alias.
 * @param string $student_alias Student table alias.
 * @return string
 */
function cmp_payment_batch_sql( $payment_alias = 'p', $student_alias = 's' ) {
	return 'COALESCE(NULLIF(' . $payment_alias . '.batch_id, 0), ' . $student_alias . '.batch_id, 0)';
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
		'edit_payment'   => __( 'Payment Updated', 'class-manager-pro' ),
		'delete_payment' => __( 'Payment Moved to Trash', 'class-manager-pro' ),
		'restore_payment' => __( 'Payment Restored', 'class-manager-pro' ),
		'force_delete_payment' => __( 'Payment Permanently Deleted', 'class-manager-pro' ),
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

	if ( cmp_is_pending_payment_student( $student ) ) {
		return array(
			'key'   => 'pending',
			'label' => __( 'Pending Payment', 'class-manager-pro' ),
		);
	}

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
 * Returns whether a student came from a failed payment import.
 *
 * @param object $student Student row.
 * @return bool
 */
function cmp_is_pending_payment_student( $student ) {
	$batch_id = isset( $student->batch_id ) ? absint( $student->batch_id ) : 0;
	$notes    = isset( $student->notes ) ? strtolower( (string) $student->notes ) : '';

	if ( $batch_id > 0 || '' === $notes ) {
		return false;
	}

	foreach ( array( 'payment import status: failed', 'pending payment import student' ) as $marker ) {
		if ( false !== strpos( $notes, $marker ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns a display-ready batch label for a student row.
 *
 * @param object $student Student row.
 * @return string
 */
function cmp_get_student_batch_label( $student ) {
	if ( ! empty( $student->batch_name ) ) {
		return (string) $student->batch_name;
	}

	if ( cmp_is_pending_payment_student( $student ) ) {
		return __( 'Pending Payment', 'class-manager-pro' );
	}

	return __( 'Not assigned', 'class-manager-pro' );
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
 * Returns teacher user IDs that are assigned on any batch.
 *
 * @return array
 */
function cmp_get_assigned_teacher_user_ids() {
	global $wpdb;

	return cmp_clean_absint_array(
		$wpdb->get_col( 'SELECT DISTINCT teacher_user_id FROM ' . cmp_table( 'batches' ) . ' WHERE teacher_user_id > 0 ORDER BY teacher_user_id ASC' )
	);
}

/**
 * Returns WordPress users available for teacher assignment.
 *
 * @param int $include_user_id Optional selected legacy user ID.
 * @return array
 */
function cmp_get_teacher_users( $include_user_id = 0 ) {
	$include_user_id = absint( $include_user_id );
	$users           = cmp_get_tutor_instructors( $include_user_id );
	$user_map        = array();
	$user_ids        = cmp_get_assigned_teacher_user_ids();

	if ( $include_user_id ) {
		$user_ids[] = $include_user_id;
	}

	foreach ( $users as $user ) {
		if ( empty( $user->ID ) ) {
			continue;
		}

		$user_map[ (int) $user->ID ] = $user;
	}

	foreach ( array_values( array_unique( cmp_clean_absint_array( $user_ids ) ) ) as $user_id ) {
		if ( isset( $user_map[ $user_id ] ) ) {
			continue;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			continue;
		}

		$user_map[ $user_id ] = (object) array(
			'ID'           => (int) $user->ID,
			'display_name' => $user->display_name,
			'user_email'   => $user->user_email,
			'roles'        => (array) $user->roles,
		);
	}

	if ( empty( $user_map ) ) {
		return array();
	}

	uasort(
		$user_map,
		static function ( $left, $right ) {
			$left_label  = strtolower( sanitize_text_field( isset( $left->display_name ) ? $left->display_name : '' ) );
			$right_label = strtolower( sanitize_text_field( isset( $right->display_name ) ? $right->display_name : '' ) );

			if ( $left_label === $right_label ) {
				return (int) $left->ID <=> (int) $right->ID;
			}

			return strcmp( $left_label, $right_label );
		}
	);

	return array_values( $user_map );
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
 * Returns the admin edit link for a WordPress user when available.
 *
 * @param int $user_id User ID.
 * @return string
 */
function cmp_get_user_edit_link( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! $user_id || ! current_user_can( 'manage_options' ) || ! function_exists( 'get_edit_user_link' ) ) {
		return '';
	}

	$link = get_edit_user_link( $user_id );

	return is_string( $link ) ? esc_url_raw( $link ) : '';
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
 * Gets batches assigned to a specific teacher user.
 *
 * @param int $teacher_user_id Teacher user ID.
 * @return array
 */
function cmp_get_batches_for_teacher_user_id( $teacher_user_id ) {
	global $wpdb;

	$teacher_user_id = absint( $teacher_user_id );

	if ( ! $teacher_user_id ) {
		return array();
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT b.*, c.name AS class_name, c.total_fee AS class_total_fee, COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0) AS effective_fee
			FROM ' . cmp_table( 'batches' ) . ' b
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			WHERE b.teacher_user_id = %d
			ORDER BY b.created_at DESC, b.id DESC',
			$teacher_user_id
		)
	);
}

/**
 * Returns aggregate teacher performance metrics keyed by teacher user ID.
 *
 * @param array $teacher_user_ids Optional teacher user IDs.
 * @return array
 */
function cmp_get_teacher_metrics_map( $teacher_user_ids = array() ) {
	global $wpdb;

	$teacher_user_ids = cmp_clean_absint_array( $teacher_user_ids );
	$where            = 'WHERE b.teacher_user_id > 0';
	$params           = array();

	if ( ! empty( $teacher_user_ids ) ) {
		$where   .= ' AND b.teacher_user_id IN (' . implode( ', ', array_fill( 0, count( $teacher_user_ids ), '%d' ) ) . ')';
		$params   = $teacher_user_ids;
	}

	$sql = 'SELECT
			b.teacher_user_id,
			COUNT(DISTINCT b.id) AS assigned_batches,
			COALESCE(SUM(st.student_count), 0) AS total_students,
			COALESCE(SUM(st.pending_students), 0) AS pending_students,
			COALESCE(SUM(st.pending_amount), 0) AS pending_amount,
			COALESCE(SUM(leads.interested_students), 0) AS interested_students,
			COALESCE(SUM(exp.teacher_payment), 0) AS teacher_payment_total,
			(COALESCE(SUM(pay.revenue), 0) + COALESCE(SUM(b.manual_income), 0)) AS total_revenue
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN (
			SELECT
				batch_id,
				COUNT(*) AS student_count,
				SUM(CASE WHEN GREATEST(total_fee - paid_fee, 0) > 0 THEN 1 ELSE 0 END) AS pending_students,
				SUM(GREATEST(total_fee - paid_fee, 0)) AS pending_amount
			FROM ' . cmp_table( 'students' ) . '
			GROUP BY batch_id
		) st ON st.batch_id = b.id
		LEFT JOIN (
			SELECT
				batch_id,
				COUNT(*) AS interested_students
			FROM ' . cmp_table( 'interested_students' ) . '
			GROUP BY batch_id
		) leads ON leads.batch_id = b.id
		LEFT JOIN (
			SELECT
				batch_id,
				SUM(CASE WHEN category = \'teacher_payment\' THEN amount ELSE 0 END) AS teacher_payment
			FROM ' . cmp_table( 'expenses' ) . '
			GROUP BY batch_id
		) exp ON exp.batch_id = b.id
		LEFT JOIN (
			SELECT
				' . cmp_payment_batch_sql( 'p', 's' ) . ' AS batch_id,
				SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . ') AS revenue
			FROM ' . cmp_table( 'payments' ) . ' p
			INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			WHERE p.is_deleted = 0
			GROUP BY batch_id
		) pay ON pay.batch_id = b.id
		' . $where . '
		GROUP BY b.teacher_user_id';

	$rows    = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
	$metrics = array();

	foreach ( $rows as $row ) {
		$metrics[ (int) $row->teacher_user_id ] = array(
			'assigned_batches' => (int) $row->assigned_batches,
			'total_students'   => (int) $row->total_students,
			'pending_students' => (int) $row->pending_students,
			'pending_amount'   => (float) $row->pending_amount,
			'interested_students' => (int) $row->interested_students,
			'teacher_payment_total' => (float) $row->teacher_payment_total,
			'total_revenue'    => (float) $row->total_revenue,
		);
	}

	return $metrics;
}

/**
 * Returns assigned batch labels keyed by teacher user ID.
 *
 * @param array $teacher_user_ids Optional teacher user IDs.
 * @param int   $limit_per_teacher Optional batch label limit per teacher.
 * @return array
 */
function cmp_get_teacher_batch_labels_map( $teacher_user_ids = array(), $limit_per_teacher = 0 ) {
	global $wpdb;

	$teacher_user_ids = cmp_clean_absint_array( $teacher_user_ids );
	$limit_per_teacher = absint( $limit_per_teacher );
	$where            = 'WHERE b.teacher_user_id > 0';
	$params           = array();

	if ( ! empty( $teacher_user_ids ) ) {
		$where   .= ' AND b.teacher_user_id IN (' . implode( ', ', array_fill( 0, count( $teacher_user_ids ), '%d' ) ) . ')';
		$params   = $teacher_user_ids;
	}

	$sql  = 'SELECT b.teacher_user_id, b.id, b.batch_name, c.name AS class_name
		FROM ' . cmp_table( 'batches' ) . ' b
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		' . $where . '
		ORDER BY b.teacher_user_id ASC, b.created_at DESC, b.id DESC';
	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
	$map  = array();

	foreach ( $rows as $row ) {
		$teacher_user_id = (int) $row->teacher_user_id;

		if ( ! isset( $map[ $teacher_user_id ] ) ) {
			$map[ $teacher_user_id ] = array();
		}

		if ( $limit_per_teacher > 0 && count( $map[ $teacher_user_id ] ) >= $limit_per_teacher ) {
			continue;
		}

		$map[ $teacher_user_id ][] = trim(
			sprintf(
				'%1$s / %2$s',
				$row->class_name ? $row->class_name : __( 'Unassigned', 'class-manager-pro' ),
				$row->batch_name
			)
		);
	}

	return $map;
}

/**
 * Returns teacher rows enriched with performance metrics.
 *
 * @param array $teacher_user_ids Optional teacher user IDs.
 * @param int   $limit_batch_labels Optional batch label limit per teacher.
 * @param bool  $assigned_only Whether to keep only assigned teachers.
 * @return array
 */
function cmp_get_teacher_overview_rows( $teacher_user_ids = array(), $limit_batch_labels = 3, $assigned_only = false ) {
	$teacher_user_ids  = cmp_clean_absint_array( $teacher_user_ids );
	$metrics_map       = cmp_get_teacher_metrics_map( $teacher_user_ids );
	$batch_labels_map  = cmp_get_teacher_batch_labels_map( $teacher_user_ids, $limit_batch_labels );
	$teacher_users     = cmp_get_teacher_users();
	$teacher_user_map  = array();
	$rows              = array();

	foreach ( $teacher_users as $teacher_user ) {
		$teacher_user_map[ (int) $teacher_user->ID ] = $teacher_user;
	}

	if ( ! empty( $teacher_user_ids ) ) {
		foreach ( $teacher_user_ids as $teacher_user_id ) {
			if ( isset( $teacher_user_map[ $teacher_user_id ] ) ) {
				continue;
			}

			$legacy_user = get_userdata( $teacher_user_id );

			if ( ! $legacy_user ) {
				continue;
			}

			$teacher_user_map[ $teacher_user_id ] = (object) array(
				'ID'           => (int) $legacy_user->ID,
				'display_name' => $legacy_user->display_name,
				'user_email'   => $legacy_user->user_email,
				'roles'        => (array) $legacy_user->roles,
			);
		}
	}

	foreach ( $teacher_user_map as $teacher_user_id => $teacher_user ) {
		$metrics = isset( $metrics_map[ $teacher_user_id ] ) ? $metrics_map[ $teacher_user_id ] : array(
			'assigned_batches' => 0,
			'total_students'   => 0,
			'pending_students' => 0,
			'pending_amount'   => 0,
			'interested_students' => 0,
			'teacher_payment_total' => 0,
			'total_revenue'    => 0,
		);

		if ( $assigned_only && empty( $metrics['assigned_batches'] ) ) {
			continue;
		}

		$rows[] = (object) array(
			'ID'               => (int) $teacher_user->ID,
			'display_name'     => $teacher_user->display_name,
			'user_email'       => $teacher_user->user_email,
			'assigned_batches' => (int) $metrics['assigned_batches'],
			'total_students'   => (int) $metrics['total_students'],
			'pending_students' => (int) $metrics['pending_students'],
			'pending_amount'   => (float) $metrics['pending_amount'],
			'interested_students' => (int) $metrics['interested_students'],
			'teacher_payment_total' => (float) $metrics['teacher_payment_total'],
			'total_revenue'    => (float) $metrics['total_revenue'],
			'batch_labels'     => isset( $batch_labels_map[ $teacher_user_id ] ) ? $batch_labels_map[ $teacher_user_id ] : array(),
		);
	}

	usort(
		$rows,
		static function ( $left, $right ) {
			if ( (int) $left->assigned_batches !== (int) $right->assigned_batches ) {
				return (int) $right->assigned_batches <=> (int) $left->assigned_batches;
			}

			if ( (int) $left->total_students !== (int) $right->total_students ) {
				return (int) $right->total_students <=> (int) $left->total_students;
			}

			return strcmp(
				strtolower( sanitize_text_field( $left->display_name ) ),
				strtolower( sanitize_text_field( $right->display_name ) )
			);
		}
	);

	return $rows;
}

/**
 * Returns teacher batch performance rows.
 *
 * @param int $teacher_user_id Teacher user ID.
 * @return array
 */
function cmp_get_teacher_batch_performance( $teacher_user_id ) {
	global $wpdb;

	$teacher_user_id = absint( $teacher_user_id );

	if ( ! $teacher_user_id ) {
		return array();
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT
				b.*,
				c.name AS class_name,
				COALESCE(st.student_count, 0) AS student_count,
				COALESCE(st.pending_students, 0) AS pending_students,
				COALESCE(st.pending_amount, 0) AS pending_amount,
				COALESCE(leads.interested_students, 0) AS interested_students,
				COALESCE(exp.teacher_payment, 0) AS teacher_payment,
				(COALESCE(pay.revenue, 0) + COALESCE(b.manual_income, 0)) AS revenue
			FROM ' . cmp_table( 'batches' ) . ' b
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			LEFT JOIN (
				SELECT
					batch_id,
					COUNT(*) AS student_count,
					SUM(CASE WHEN GREATEST(total_fee - paid_fee, 0) > 0 THEN 1 ELSE 0 END) AS pending_students,
					SUM(GREATEST(total_fee - paid_fee, 0)) AS pending_amount
				FROM ' . cmp_table( 'students' ) . '
				GROUP BY batch_id
			) st ON st.batch_id = b.id
			LEFT JOIN (
				SELECT
					batch_id,
					COUNT(*) AS interested_students
				FROM ' . cmp_table( 'interested_students' ) . '
				GROUP BY batch_id
			) leads ON leads.batch_id = b.id
			LEFT JOIN (
				SELECT
					batch_id,
					SUM(CASE WHEN category = \'teacher_payment\' THEN amount ELSE 0 END) AS teacher_payment
				FROM ' . cmp_table( 'expenses' ) . '
				GROUP BY batch_id
			) exp ON exp.batch_id = b.id
			LEFT JOIN (
				SELECT
					' . cmp_payment_batch_sql( 'p', 's' ) . ' AS batch_id,
					SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . ') AS revenue
				FROM ' . cmp_table( 'payments' ) . ' p
				INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
				WHERE p.is_deleted = 0
				GROUP BY batch_id
			) pay ON pay.batch_id = b.id
			WHERE b.teacher_user_id = %d
			ORDER BY b.created_at DESC, b.id DESC',
			$teacher_user_id
		)
	);
}

/**
 * Returns the current number of students assigned to a batch.
 *
 * @param int $batch_id Batch ID.
 * @return int
 */
function cmp_get_batch_student_count( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );

	if ( ! $batch_id ) {
		return 0;
	}

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE batch_id = %d',
			$batch_id
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

	$payment_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT id FROM ' . cmp_table( 'payments' ) . ' WHERE student_id = %d',
			$student_id
		)
	);

	foreach ( $payment_ids as $payment_id ) {
		$result = cmp_force_delete_payment( (int) $payment_id, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

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

	$payment_revenue = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(' . cmp_payment_reporting_amount_sql( 'payments' ) . '), 0) FROM ' . cmp_table( 'payments' ) . ' payments WHERE is_deleted = 0' );
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
			SELECT ' . cmp_payment_batch_sql( 'p', 's' ) . ' AS batch_id, SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . ') AS revenue
			FROM ' . cmp_table( 'payments' ) . ' p
			INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			WHERE p.is_deleted = 0
			GROUP BY batch_id
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
			'SELECT COALESCE(SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . '), 0)
			FROM ' . cmp_table( 'payments' ) . ' p
			LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			WHERE p.is_deleted = 0 AND ' . cmp_payment_batch_sql( 'p', 's' ) . ' = %d',
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
 * Prevents duplicate student records inside the same batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $name Student name.
 * @param string $email Student email.
 * @param string $phone Student phone.
 * @param int    $exclude_student_id Optional student ID to ignore.
 * @return true|WP_Error
 */
function cmp_validate_unique_student_in_batch( $batch_id, $name = '', $email = '', $phone = '', $exclude_student_id = 0 ) {
	$existing = cmp_find_student_in_batch_by_identity( $batch_id, $name, $email, $phone );

	if ( ! $existing ) {
		return true;
	}

	if ( $exclude_student_id && (int) $existing->id === absint( $exclude_student_id ) ) {
		return true;
	}

	return new WP_Error( 'cmp_duplicate_student_batch', __( 'This student is already enrolled in the selected batch.', 'class-manager-pro' ) );
}

/**
 * Finds an unassigned student by class and contact details.
 *
 * @param int    $class_id Class ID.
 * @param string $phone Phone number.
 * @param string $email Email address.
 * @return object|null
 */
function cmp_find_unassigned_student_by_contact( $class_id, $phone = '', $email = '' ) {
	global $wpdb;

	$class_id = absint( $class_id );
	$where    = array();
	$params   = array( $class_id );

	$phone_values = cmp_phone_match_values( $phone );

	if ( $phone_values ) {
		$where[] = 'phone IN (' . implode( ', ', array_fill( 0, count( $phone_values ), '%s' ) ) . ')';
		$params  = array_merge( $params, $phone_values );
	}

	if ( '' !== trim( (string) $email ) ) {
		$where[]  = 'email = %s';
		$params[] = sanitize_email( $email );
	}

	if ( ! $class_id || empty( $where ) ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_table( 'students' ) . ' WHERE class_id = %d AND batch_id = 0 AND (' . implode( ' OR ', $where ) . ') ORDER BY id DESC LIMIT 1',
			$params
		)
	);
}

/**
 * Returns a reusable student match for imports without merging records across classes.
 *
 * @param object|null $batch Batch context.
 * @param string      $name Student name.
 * @param string      $email Student email.
 * @param string      $phone Student phone.
 * @param int         $class_id Class ID.
 * @return object|null
 */
function cmp_find_reusable_import_student( $batch, $name = '', $email = '', $phone = '', $class_id = 0 ) {
	$batch    = $batch ? cmp_get_batch( (int) $batch->id ) : null;
	$class_id = $class_id ? absint( $class_id ) : ( $batch ? (int) $batch->class_id : 0 );

	if ( $batch ) {
		$student = cmp_find_student_in_batch_by_identity( (int) $batch->id, $name, $email, $phone );

		if ( $student ) {
			return $student;
		}
	}

	if ( $class_id ) {
		return cmp_find_unassigned_student_by_contact( $class_id, $phone, $email );
	}

	return null;
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
	$notice_token = sanitize_text_field( cmp_field( $data, 'notice_token', ! empty( $batch->public_token ) ? $batch->public_token : '' ) );
	$submission_note = 'temp' === sanitize_key( cmp_field( $data, 'access_type', 'permanent' ) )
		? __( 'Submitted through a temporary batch registration link.', 'class-manager-pro' )
		: __( 'Submitted through batch student form.', 'class-manager-pro' );

	if ( '' === $name || '' === $phone || ! $class ) {
		return new WP_Error( 'cmp_public_student_invalid', __( 'Name and phone are required.', 'class-manager-pro' ) );
	}

	$notes = cmp_append_note( $notes, $submission_note );

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

		if ( function_exists( 'cmp_store_public_duplicate_warning' ) && '' !== $notice_token ) {
			cmp_store_public_duplicate_warning( $notice_token, __( 'Student already exists. Existing record was updated.', 'class-manager-pro' ) );
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
 * Returns enrollments linked to the same student contact details across classes.
 *
 * @param int $student_id Student ID.
 * @return array
 */
function cmp_get_related_student_enrollments( $student_id ) {
	global $wpdb;

	$student = cmp_get_student( $student_id );

	if ( ! $student ) {
		return array();
	}

	$where  = array();
	$params = array();

	$phone_values = cmp_phone_match_values( $student->phone );

	if ( $phone_values ) {
		$where[] = 's.phone IN (' . implode( ', ', array_fill( 0, count( $phone_values ), '%s' ) ) . ')';
		$params  = array_merge( $params, $phone_values );
	}

	if ( ! empty( $student->email ) ) {
		$where[]  = 's.email = %s';
		$params[] = sanitize_email( $student->email );
	}

	if ( empty( $where ) ) {
		return array( $student );
	}

	$sql = 'SELECT s.*, c.name AS class_name, b.batch_name, b.fee_due_date
		FROM ' . cmp_table( 'students' ) . ' s
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
		WHERE ' . implode( ' OR ', $where ) . '
		ORDER BY s.created_at DESC, s.id DESC';

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
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

	$where[] = cmp_get_failed_payment_placeholder_student_where_sql( 's' );

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

	$duplicate_check = cmp_validate_unique_student_in_batch( $batch_id, $name, $email, $phone );

	if ( is_wp_error( $duplicate_check ) ) {
		return $duplicate_check;
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
				'class_id'       => $class_id,
				'batch_id'       => $batch_id,
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

	$duplicate_check = cmp_validate_unique_student_in_batch( $batch_id, $name, $email, $phone, $id );

	if ( is_wp_error( $duplicate_check ) ) {
		return $duplicate_check;
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

	if ( $student && ( (int) $student->batch_id !== $batch_id || (int) $student->class_id !== $class_id ) ) {
		cmp_recalculate_student_paid_fee( $id );
	}

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
 * Returns whether a student has any saved payments.
 *
 * @param int $student_id Student ID.
 * @return bool
 */
function cmp_student_has_payments( $student_id ) {
	return cmp_get_student_context_payment_total( $student_id, 0, 0, 0, 'active', true ) > 0;
}

/**
 * Returns the total payments for a student's current class and batch context.
 *
 * @param int $student_id Student ID.
 * @param int $class_id Class ID.
 * @param int $batch_id Batch ID.
 * @param int $exclude_payment_id Payment ID to exclude.
 * @param string $deleted_status Payment deleted status.
 * @param bool   $ignore_context Whether to ignore class and batch filters.
 * @return float
 */
function cmp_get_student_context_payment_total( $student_id, $class_id, $batch_id, $exclude_payment_id = 0, $deleted_status = 'active', $ignore_context = false ) {
	global $wpdb;

	$where  = array( 'student_id = %d' );
	$params = array( absint( $student_id ) );

	if ( ! $ignore_context ) {
		$where[]  = 'class_id = %d';
		$where[]  = 'batch_id = %d';
		$params[] = absint( $class_id );
		$params[] = absint( $batch_id );
	}

	if ( $exclude_payment_id > 0 ) {
		$where[]  = 'id <> %d';
		$params[] = absint( $exclude_payment_id );
	}

	if ( 'trash' === $deleted_status ) {
		$where[] = 'is_deleted = 1';
	} elseif ( 'all' !== $deleted_status ) {
		$where[] = 'is_deleted = 0';
	}

	$sql = 'SELECT COALESCE(SUM(amount), 0) FROM ' . cmp_table( 'payments' ) . ' WHERE ' . implode( ' AND ', $where );

	return (float) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
}

/**
 * Normalizes a payment date input.
 *
 * @param mixed $raw_date Raw date value.
 * @return string
 */
function cmp_normalize_payment_datetime( $raw_date ) {
	$raw_date = is_scalar( $raw_date ) ? trim( (string) $raw_date ) : '';

	if ( '' === $raw_date ) {
		return cmp_current_datetime();
	}

	$raw_date = str_replace( 'T', ' ', sanitize_text_field( $raw_date ) );
	$timezone = wp_timezone();
	$formats  = array( 'Y-m-d H:i:s', 'Y-m-d H:i' );

	foreach ( $formats as $format ) {
		$date = DateTimeImmutable::createFromFormat( $format, $raw_date, $timezone );

		if ( false === $date ) {
			continue;
		}

		$errors = DateTimeImmutable::getLastErrors();

		if ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) {
			continue;
		}

		return $date->format( 'Y-m-d H:i:s' );
	}

	return '';
}

/**
 * Returns the maximum fee allowed for a payment context.
 *
 * @param object|null $student Student row.
 * @param int         $class_id Class ID.
 * @param int         $batch_id Batch ID.
 * @return float
 */
function cmp_get_payment_context_total_fee( $student, $class_id, $batch_id ) {
	$student = is_object( $student ) ? $student : null;
	$batch   = $batch_id ? cmp_get_batch( $batch_id ) : null;
	$batch_fee = cmp_get_batch_effective_fee( $batch );

	if ( $student && (int) $student->class_id === absint( $class_id ) && (int) $student->batch_id === absint( $batch_id ) && (float) $student->total_fee > 0 ) {
		return (float) $student->total_fee;
	}

	if ( $batch_fee > 0 ) {
		return $batch_fee;
	}

	return $student ? (float) $student->total_fee : 0;
}

/**
 * Returns the remaining allowed amount for a payment context.
 *
 * @param int $student_id Student ID.
 * @param int $class_id Class ID.
 * @param int $batch_id Batch ID.
 * @param int $exclude_payment_id Payment ID to exclude.
 * @return float
 */
function cmp_get_payment_remaining_allowed_amount( $student_id, $class_id, $batch_id, $exclude_payment_id = 0 ) {
	$student    = cmp_get_student( $student_id );
	$total_fee  = cmp_get_payment_context_total_fee( $student, $class_id, $batch_id );
	$paid_total = cmp_get_student_context_payment_total( $student_id, $class_id, $batch_id, $exclude_payment_id );

	return max( 0, round( $total_fee - $paid_total, 2 ) );
}

/**
 * Finds an exact duplicate payment entry.
 *
 * @param array $data Payment data.
 * @param int   $exclude_payment_id Payment ID to exclude.
 * @return object|null
 */
function cmp_find_duplicate_payment_entry( $data, $exclude_payment_id = 0 ) {
	global $wpdb;

	$student_id     = absint( cmp_field( $data, 'student_id', 0 ) );
	$class_id       = absint( cmp_field( $data, 'class_id', 0 ) );
	$batch_id       = absint( cmp_field( $data, 'batch_id', 0 ) );
	$amount         = round( (float) cmp_money_value( cmp_field( $data, 'amount', 0 ) ), 2 );
	$payment_mode   = cmp_clean_enum( cmp_field( $data, 'payment_mode', 'manual' ), cmp_payment_modes(), 'manual' );
	$transaction_id = sanitize_text_field( cmp_field( $data, 'transaction_id' ) );
	$payment_date   = cmp_normalize_payment_datetime( cmp_field( $data, 'payment_date', '' ) );
	$where          = array();
	$params         = array();

	if ( ! $student_id || $amount <= 0 || '' === $payment_date ) {
		return null;
	}

	if ( '' !== $transaction_id ) {
		return cmp_get_payment_by_transaction_id( $transaction_id, $exclude_payment_id );
	}

	$where[]  = 'student_id = %d';
	$where[]  = 'class_id = %d';
	$where[]  = 'batch_id = %d';
	$where[]  = 'amount = %f';
	$where[]  = 'payment_mode = %s';
	$where[]  = 'payment_date = %s';
	$params[] = $student_id;
	$params[] = $class_id;
	$params[] = $batch_id;
	$params[] = $amount;
	$params[] = $payment_mode;
	$params[] = $payment_date;

	if ( $exclude_payment_id > 0 ) {
		$where[]  = 'id <> %d';
		$params[] = absint( $exclude_payment_id );
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id, student_id, is_deleted
			FROM ' . cmp_table( 'payments' ) . '
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY id DESC
			LIMIT 1',
			$params
		)
	);
}

/**
 * Recalculates a student's paid_fee from payment rows for the current context.
 *
 * @param int $student_id Student ID.
 * @return true|WP_Error
 */
function cmp_recalculate_student_paid_fee( $student_id ) {
	global $wpdb;

	$student_id = absint( $student_id );
	$student    = cmp_get_student( $student_id );

	if ( ! $student ) {
		return new WP_Error( 'cmp_invalid_student', __( 'Student not found.', 'class-manager-pro' ) );
	}

	$paid_fee = cmp_get_student_context_payment_total( $student_id, (int) $student->class_id, (int) $student->batch_id );
	$updated  = $wpdb->update(
		cmp_table( 'students' ),
		array(
			'paid_fee' => $paid_fee,
		),
		array(
			'id' => $student_id,
		),
		array( '%f' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_student_paid_fee_sync_failed', __( 'Could not refresh student payment totals.', 'class-manager-pro' ) );
	}

	return true;
}

/**
 * Returns a single payment row with student and context labels.
 *
 * @param int $id Payment ID.
 * @param string $deleted_status Payment deleted status.
 * @return object|null
 */
function cmp_get_payment( $id, $deleted_status = 'all' ) {
	global $wpdb;

	$class_context_sql = cmp_payment_class_sql( 'p', 's' );
	$batch_context_sql = cmp_payment_batch_sql( 'p', 's' );
	$where             = array( 'p.id = %d' );
	$params            = array( absint( $id ) );

	if ( 'trash' === $deleted_status ) {
		$where[] = 'p.is_deleted = 1';
	} elseif ( 'active' === $deleted_status ) {
		$where[] = 'p.is_deleted = 0';
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT
				p.*,
				s.name AS student_name,
				s.phone AS student_phone,
				s.unique_id AS student_unique_id,
				s.email AS student_email,
				s.class_id AS student_class_id,
				s.batch_id AS student_batch_id,
				c.name AS class_name,
				b.batch_name
			FROM ' . cmp_table( 'payments' ) . ' p
			LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = ' . $class_context_sql . '
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = ' . $batch_context_sql . '
			WHERE ' . implode( ' AND ', $where ),
			$params
		)
	);
}

/**
 * Builds a delete URL for a payment row.
 *
 * @param int    $payment_id Payment ID.
 * @param string $return_page Page to return to after deletion.
 * @param array  $args Extra query args.
 * @return string
 */
function cmp_get_payment_delete_url( $payment_id, $return_page = 'cmp-payments', $args = array() ) {
	$payment_id = absint( $payment_id );
	$return_page = cmp_clean_return_page( $return_page, 'cmp-payments' );
	$args        = is_array( $args ) ? $args : array();

	$url = add_query_arg(
		array_merge(
			$args,
			array(
				'action'      => 'cmp_delete_payment',
				'id'          => $payment_id,
				'return_page' => $return_page,
			)
		),
		admin_url( 'admin-post.php' )
	);

	return wp_nonce_url( $url, 'cmp_delete_payment_' . $payment_id );
}

/**
 * Builds a restore URL for a payment row.
 *
 * @param int    $payment_id Payment ID.
 * @param string $return_page Page to return to.
 * @param array  $args Extra query args.
 * @return string
 */
function cmp_get_payment_restore_url( $payment_id, $return_page = 'cmp-payments-trash', $args = array() ) {
	$payment_id  = absint( $payment_id );
	$return_page = cmp_clean_return_page( $return_page, 'cmp-payments-trash' );
	$args        = is_array( $args ) ? $args : array();

	$url = add_query_arg(
		array_merge(
			$args,
			array(
				'action'      => 'cmp_restore_payment',
				'id'          => $payment_id,
				'return_page' => $return_page,
			)
		),
		admin_url( 'admin-post.php' )
	);

	return wp_nonce_url( $url, 'cmp_restore_payment_' . $payment_id );
}

/**
 * Builds a permanent delete URL for a payment row.
 *
 * @param int    $payment_id Payment ID.
 * @param string $return_page Page to return to.
 * @param array  $args Extra query args.
 * @return string
 */
function cmp_get_payment_force_delete_url( $payment_id, $return_page = 'cmp-payments-trash', $args = array() ) {
	$payment_id  = absint( $payment_id );
	$return_page = cmp_clean_return_page( $return_page, 'cmp-payments-trash' );
	$args        = is_array( $args ) ? $args : array();

	$url = add_query_arg(
		array_merge(
			$args,
			array(
				'action'      => 'cmp_force_delete_payment',
				'id'          => $payment_id,
				'return_page' => $return_page,
			)
		),
		admin_url( 'admin-post.php' )
	);

	return wp_nonce_url( $url, 'cmp_force_delete_payment_' . $payment_id );
}

/**
 * Builds a view URL for a payment row.
 *
 * @param int    $payment_id Payment ID.
 * @param string $page Page slug.
 * @return string
 */
function cmp_get_payment_view_url( $payment_id, $page = 'cmp-payments' ) {
	return cmp_admin_url(
		cmp_clean_return_page( $page, 'cmp-payments' ),
		array(
			'action' => 'view',
			'id'     => absint( $payment_id ),
		)
	);
}

/**
 * Builds an edit URL for a payment row.
 *
 * @param int    $payment_id Payment ID.
 * @param string $page Page slug.
 * @return string
 */
function cmp_get_payment_edit_url( $payment_id, $page = 'cmp-payments' ) {
	return cmp_admin_url(
		cmp_clean_return_page( $page, 'cmp-payments' ),
		array(
			'action' => 'edit',
			'id'     => absint( $payment_id ),
		)
	);
}

/**
 * Returns whether a payment belongs to a student's current enrollment context.
 *
 * @param object $payment Payment row.
 * @param object $student Student row.
 * @return bool
 */
function cmp_payment_matches_student_context( $payment, $student ) {
	if ( ! $payment || ! $student ) {
		return false;
	}

	return (int) $payment->class_id === (int) $student->class_id && (int) $payment->batch_id === (int) $student->batch_id;
}

/**
 * Returns a payment by transaction ID.
 *
 * @param string $transaction_id Transaction ID.
 * @param int    $exclude_payment_id Payment ID to exclude.
 * @return object|null
 */
function cmp_get_payment_by_transaction_id( $transaction_id, $exclude_payment_id = 0 ) {
	global $wpdb;

	$transaction_id = sanitize_text_field( $transaction_id );

	if ( '' === $transaction_id ) {
		return null;
	}

	$where  = array( 'transaction_id = %s' );
	$params = array( $transaction_id );

	if ( $exclude_payment_id > 0 ) {
		$where[]  = 'id <> %d';
		$params[] = absint( $exclude_payment_id );
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id, student_id, transaction_id, is_deleted
			FROM ' . cmp_table( 'payments' ) . '
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY id DESC
			LIMIT 1',
			$params
		)
	);
}

/**
 * Checks whether a payment transaction already exists.
 *
 * @param string $transaction_id Transaction ID.
 * @param int    $exclude_payment_id Payment ID to exclude.
 * @return bool
 */
function cmp_payment_exists( $transaction_id, $exclude_payment_id = 0 ) {
	return (bool) cmp_get_payment_by_transaction_id( $transaction_id, $exclude_payment_id );
}

/**
 * Validates and normalizes payment payload data.
 *
 * @param array       $data Payment data.
 * @param object|null $existing_payment Existing payment row.
 * @return array|WP_Error
 */
function cmp_prepare_payment_payload( $data, $existing_payment = null ) {
	$existing_payment = is_object( $existing_payment ) ? $existing_payment : null;
	$exclude_id       = $existing_payment ? (int) $existing_payment->id : 0;
	$student_id       = $existing_payment ? (int) $existing_payment->student_id : absint( cmp_field( $data, 'student_id', 0 ) );
	$student          = cmp_get_student( $student_id );
	$class_id         = $existing_payment ? (int) $existing_payment->class_id : absint( cmp_field( $data, 'class_id', $student ? (int) $student->class_id : 0 ) );
	$batch_id         = $existing_payment ? (int) $existing_payment->batch_id : absint( cmp_field( $data, 'batch_id', $student ? (int) $student->batch_id : 0 ) );
	$batch            = $batch_id ? cmp_get_batch( $batch_id ) : null;
	$amount           = cmp_money_value( cmp_field( $data, 'amount', $existing_payment ? (float) $existing_payment->amount : 0 ) );
	$payment_mode     = cmp_clean_enum( cmp_field( $data, 'payment_mode', $existing_payment ? $existing_payment->payment_mode : 'manual' ), cmp_payment_modes(), 'manual' );
	$transaction_id   = sanitize_text_field( cmp_field( $data, 'transaction_id', $existing_payment ? $existing_payment->transaction_id : '' ) );
	$payment_date     = cmp_normalize_payment_datetime( cmp_field( $data, 'payment_date', $existing_payment ? $existing_payment->payment_date : '' ) );
	$apply_gateway_charge = ! empty(
		cmp_field(
			$data,
			'apply_gateway_charge',
			$existing_payment && isset( $existing_payment->charge_amount ) && (float) $existing_payment->charge_amount > 0 ? 1 : 0
		)
	);

	if ( ! $student_id || ! $student || $amount <= 0 ) {
		cmp_log_event( 'error', 'Payment validation failed.', array( 'student_id' => $student_id, 'amount' => $amount ) );
		return new WP_Error( 'cmp_invalid_payment', __( 'Valid student and payment amount are required.', 'class-manager-pro' ) );
	}

	if ( '' === $payment_date ) {
		return new WP_Error( 'cmp_invalid_payment_date', __( 'Enter a valid payment date.', 'class-manager-pro' ) );
	}

	if ( ! $class_id || ! $batch_id || ! $batch || (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_invalid_payment_context', __( 'Payment class and batch do not match.', 'class-manager-pro' ) );
	}

	if ( ! $existing_payment && ( (int) $student->class_id !== $class_id || (int) $student->batch_id !== $batch_id ) ) {
		return new WP_Error( 'cmp_invalid_payment_context', __( 'Manual payments must use the student\'s current class and batch.', 'class-manager-pro' ) );
	}

	$allowed_amount = cmp_get_payment_remaining_allowed_amount( $student_id, $class_id, $batch_id, $exclude_id );

	if ( $allowed_amount <= 0 ) {
		return new WP_Error( 'cmp_payment_not_allowed', __( 'This student has no remaining fee balance for the selected batch.', 'class-manager-pro' ) );
	}

	if ( $amount > $allowed_amount ) {
		$amount = $allowed_amount;
		cmp_set_payment_operation_notice( __( 'Entered amount exceeds allowed fee. Adjusted automatically.', 'class-manager-pro' ), 'warning' );
	}

	if ( '' !== $transaction_id && cmp_payment_exists( $transaction_id, $exclude_id ) ) {
		return new WP_Error( 'cmp_duplicate_payment', __( 'This transaction has already been recorded.', 'class-manager-pro' ) );
	}

	$duplicate = cmp_find_duplicate_payment_entry(
		array(
			'student_id'     => $student_id,
			'class_id'       => $class_id,
			'batch_id'       => $batch_id,
			'amount'         => $amount,
			'payment_mode'   => $payment_mode,
			'transaction_id' => $transaction_id,
			'payment_date'   => $payment_date,
		),
		$exclude_id
	);

	if ( $duplicate ) {
		return new WP_Error(
			'cmp_duplicate_payment',
			! empty( $duplicate->is_deleted )
				? __( 'A matching payment already exists in Trash. Restore it instead of creating a duplicate.', 'class-manager-pro' )
				: __( 'This payment entry already exists.', 'class-manager-pro' )
		);
	}

	$financials = cmp_get_payment_financial_breakdown( $amount, $apply_gateway_charge );

	return array(
		'student'        => $student,
		'student_id'     => $student_id,
		'class_id'       => $class_id,
		'batch_id'       => $batch_id,
		'amount'         => round( (float) $amount, 2 ),
		'original_amount' => (float) $financials['original_amount'],
		'charge_amount'  => (float) $financials['charge_amount'],
		'final_amount'   => (float) $financials['final_amount'],
		'apply_gateway_charge' => (bool) $apply_gateway_charge,
		'payment_mode'   => $payment_mode,
		'transaction_id' => $transaction_id,
		'payment_date'   => $payment_date,
	);
}

/**
 * Moves a payment row to trash.
 *
 * @param int $id Payment ID.
 * @return true|WP_Error
 */
function cmp_delete_payment( $id ) {
	global $wpdb;

	$id      = absint( $id );
	$payment = cmp_get_payment( $id, 'active' );

	if ( ! $id || ! $payment ) {
		return new WP_Error( 'cmp_invalid_payment', __( 'Payment not found.', 'class-manager-pro' ) );
	}

	$deleted = $wpdb->update(
		cmp_table( 'payments' ),
		array(
			'is_deleted' => 1,
		),
		array(
			'id' => $id,
		),
		array( '%d' ),
		array( '%d' )
	);

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not move payment to Trash.', 'class-manager-pro' ) );
	}

	$updated_payment = cmp_get_payment( $id, 'trash' );
	cmp_recalculate_student_paid_fee( (int) $payment->student_id );
	cmp_log_payment_audit( $id, (int) $payment->student_id, 'delete', $payment, $updated_payment ? $updated_payment : $payment );

	cmp_log_activity(
		array(
			'student_id' => (int) $payment->student_id,
			'batch_id'   => (int) $payment->batch_id,
			'class_id'   => (int) $payment->class_id,
			'action'     => 'payment_deleted',
			'message'    => sprintf(
				/* translators: 1: amount 2: student name */
				__( 'Payment of %1$s moved to Trash for %2$s.', 'class-manager-pro' ),
				cmp_format_money( $payment->amount ),
				$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
			),
			'context'    => array(
				'payment_id'     => $id,
				'transaction_id' => $payment->transaction_id,
			),
		)
	);
	cmp_log_admin_action(
		'delete_payment',
		'payment',
		$id,
		sprintf(
			/* translators: 1: amount 2: student name */
			__( 'Payment of %1$s moved to Trash for %2$s.', 'class-manager-pro' ),
			cmp_format_money( $payment->amount ),
			$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
		)
	);

	return true;
}

/**
 * Restores a payment from trash.
 *
 * @param int $id Payment ID.
 * @return true|WP_Error
 */
function cmp_restore_payment( $id ) {
	global $wpdb;

	$id      = absint( $id );
	$payment = cmp_get_payment( $id, 'trash' );

	if ( ! $id || ! $payment ) {
		return new WP_Error( 'cmp_invalid_payment', __( 'Payment not found in Trash.', 'class-manager-pro' ) );
	}

	$payload = cmp_prepare_payment_payload(
		array(
			'student_id'     => (int) $payment->student_id,
			'class_id'       => (int) $payment->class_id,
			'batch_id'       => (int) $payment->batch_id,
			'amount'         => (float) $payment->amount,
			'apply_gateway_charge' => ! empty( $payment->charge_amount ) ? 1 : 0,
			'payment_mode'   => $payment->payment_mode,
			'transaction_id' => $payment->transaction_id,
			'payment_date'   => $payment->payment_date,
		),
		(object) array(
			'id'             => (int) $payment->id,
			'student_id'     => (int) $payment->student_id,
			'class_id'       => (int) $payment->class_id,
			'batch_id'       => (int) $payment->batch_id,
			'amount'         => 0,
			'charge_amount'  => isset( $payment->charge_amount ) ? (float) $payment->charge_amount : 0,
			'payment_mode'   => $payment->payment_mode,
			'transaction_id' => $payment->transaction_id,
			'payment_date'   => $payment->payment_date,
		)
	);

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$restored = $wpdb->update(
		cmp_table( 'payments' ),
		array(
			'amount'          => (float) $payload['amount'],
			'original_amount' => (float) $payload['original_amount'],
			'charge_amount'   => (float) $payload['charge_amount'],
			'final_amount'    => (float) $payload['final_amount'],
			'payment_mode'    => $payload['payment_mode'],
			'payment_date'    => $payload['payment_date'],
			'is_deleted'      => 0,
		),
		array(
			'id' => $id,
		),
		array( '%f', '%f', '%f', '%f', '%s', '%s', '%d' ),
		array( '%d' )
	);

	if ( false === $restored ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not restore payment.', 'class-manager-pro' ) );
	}

	$updated_payment = cmp_get_payment( $id, 'active' );
	cmp_recalculate_student_paid_fee( (int) $payment->student_id );
	cmp_log_payment_audit( $id, (int) $payment->student_id, 'restore', $payment, $updated_payment ? $updated_payment : $payment );
	cmp_log_activity(
		array(
			'student_id' => (int) $payment->student_id,
			'batch_id'   => (int) $payment->batch_id,
			'class_id'   => (int) $payment->class_id,
			'payment_id' => $id,
			'action'     => 'payment_restored',
			'message'    => sprintf(
				/* translators: 1: amount 2: student name */
				__( 'Payment of %1$s restored for %2$s.', 'class-manager-pro' ),
				cmp_format_money( $payload['amount'] ),
				$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
			),
			'context'    => array(
				'transaction_id' => $payment->transaction_id,
			),
		)
	);
	cmp_log_admin_action(
		'restore_payment',
		'payment',
		$id,
		sprintf(
			/* translators: 1: amount 2: student name */
			__( 'Payment of %1$s restored for %2$s.', 'class-manager-pro' ),
			cmp_format_money( $payload['amount'] ),
			$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
		)
	);

	return true;
}

/**
 * Permanently deletes a payment row.
 *
 * @param int $id Payment ID.
 * @return true|WP_Error
 */
function cmp_force_delete_payment( $id, $refresh_student = true ) {
	global $wpdb;

	$id      = absint( $id );
	$payment = cmp_get_payment( $id, 'all' );

	if ( ! $id || ! $payment ) {
		return new WP_Error( 'cmp_invalid_payment', __( 'Payment not found.', 'class-manager-pro' ) );
	}

	$deleted = $wpdb->delete( cmp_table( 'payments' ), array( 'id' => $id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not permanently delete payment.', 'class-manager-pro' ) );
	}

	if ( $refresh_student && empty( $payment->is_deleted ) ) {
		cmp_recalculate_student_paid_fee( (int) $payment->student_id );
	}

	cmp_log_payment_audit( $id, (int) $payment->student_id, 'force_delete', $payment, null );
	cmp_log_activity(
		array(
			'student_id' => (int) $payment->student_id,
			'batch_id'   => (int) $payment->batch_id,
			'class_id'   => (int) $payment->class_id,
			'payment_id' => $id,
			'action'     => 'payment_permanently_deleted',
			'message'    => sprintf(
				/* translators: 1: amount 2: student name */
				__( 'Payment of %1$s permanently deleted for %2$s.', 'class-manager-pro' ),
				cmp_format_money( $payment->amount ),
				$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
			),
			'context'    => array(
				'transaction_id' => $payment->transaction_id,
			),
		)
	);
	cmp_log_admin_action(
		'force_delete_payment',
		'payment',
		$id,
		sprintf(
			/* translators: 1: amount 2: student name */
			__( 'Payment of %1$s permanently deleted for %2$s.', 'class-manager-pro' ),
			cmp_format_money( $payment->amount ),
			$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
		)
	);

	return true;
}

/**
 * Creates a payment and refreshes student paid_fee.
 *
 * @param array $data Payment data.
 * @return int|WP_Error
 */
function cmp_insert_payment( $data ) {
	global $wpdb;

	$payload = cmp_prepare_payment_payload( $data );

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$result = $wpdb->insert(
		cmp_table( 'payments' ),
		array(
			'student_id'     => (int) $payload['student_id'],
			'class_id'       => (int) $payload['class_id'],
			'batch_id'       => (int) $payload['batch_id'],
			'amount'         => (float) $payload['amount'],
			'original_amount' => (float) $payload['original_amount'],
			'charge_amount'  => (float) $payload['charge_amount'],
			'final_amount'   => (float) $payload['final_amount'],
			'payment_mode'   => $payload['payment_mode'],
			'transaction_id' => $payload['transaction_id'],
			'payment_date'   => $payload['payment_date'],
			'is_deleted'     => 0,
			'created_at'     => cmp_current_datetime(),
		),
		array( '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%d', '%s' )
	);

	if ( false === $result ) {
		cmp_log_event( 'error', 'Payment insert failed.', array( 'student_id' => $payload['student_id'], 'amount' => $payload['amount'], 'transaction_id' => $payload['transaction_id'] ) );
		return new WP_Error( 'cmp_db_error', __( 'Could not save payment.', 'class-manager-pro' ) );
	}

	$payment_id = (int) $wpdb->insert_id;
	$payment    = cmp_get_payment( $payment_id, 'active' );

	cmp_recalculate_student_paid_fee( (int) $payload['student_id'] );
	cmp_remove_interested_students_for_student( $payload['student'], (int) $payload['batch_id'], (int) $payload['class_id'] );
	cmp_log_payment_audit( $payment_id, (int) $payload['student_id'], 'add', null, $payment );

	cmp_log_activity(
		array(
			'student_id' => (int) $payload['student_id'],
			'batch_id'   => (int) $payload['batch_id'],
			'class_id'   => (int) $payload['class_id'],
			'payment_id' => $payment_id,
			'action'     => 'payment_added',
			'message'    => sprintf(
				/* translators: 1: amount 2: payment mode */
				__( 'Payment of %1$s added via %2$s.', 'class-manager-pro' ),
				cmp_format_money( $payload['amount'] ),
				ucfirst( $payload['payment_mode'] )
			),
			'context'    => array(
				'transaction_id' => $payload['transaction_id'],
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
			cmp_format_money( $payload['amount'] ),
			$payload['student']->name
		)
	);

	return $payment_id;
}

/**
 * Updates an existing payment.
 *
 * @param int   $id Payment ID.
 * @param array $data Payment data.
 * @return true|WP_Error
 */
function cmp_update_payment( $id, $data ) {
	global $wpdb;

	$id      = absint( $id );
	$payment = cmp_get_payment( $id, 'active' );

	if ( ! $id || ! $payment ) {
		return new WP_Error( 'cmp_invalid_payment', __( 'Payment not found.', 'class-manager-pro' ) );
	}

	$payload = cmp_prepare_payment_payload( $data, $payment );

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$updated = $wpdb->update(
		cmp_table( 'payments' ),
		array(
			'amount'          => (float) $payload['amount'],
			'original_amount' => (float) $payload['original_amount'],
			'charge_amount'   => (float) $payload['charge_amount'],
			'final_amount'    => (float) $payload['final_amount'],
			'payment_mode'    => $payload['payment_mode'],
			'transaction_id'  => $payload['transaction_id'],
			'payment_date'    => $payload['payment_date'],
		),
		array(
			'id' => $id,
		),
		array( '%f', '%f', '%f', '%f', '%s', '%s', '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'cmp_db_error', __( 'Could not update payment.', 'class-manager-pro' ) );
	}

	$updated_payment = cmp_get_payment( $id, 'active' );
	cmp_recalculate_student_paid_fee( (int) $payment->student_id );
	cmp_remove_interested_students_for_student( $payload['student'], (int) $payload['batch_id'], (int) $payload['class_id'] );
	cmp_log_payment_audit( $id, (int) $payment->student_id, 'edit', $payment, $updated_payment ? $updated_payment : $payment );
	cmp_log_activity(
		array(
			'student_id' => (int) $payment->student_id,
			'batch_id'   => (int) $payment->batch_id,
			'class_id'   => (int) $payment->class_id,
			'payment_id' => $id,
			'action'     => 'payment_updated',
			'message'    => sprintf(
				/* translators: 1: amount 2: payment mode */
				__( 'Payment updated to %1$s via %2$s.', 'class-manager-pro' ),
				cmp_format_money( $payload['amount'] ),
				ucfirst( $payload['payment_mode'] )
			),
			'context'    => array(
				'transaction_id' => $payload['transaction_id'],
			),
		)
	);
	cmp_log_admin_action(
		'edit_payment',
		'payment',
		$id,
		sprintf(
			/* translators: 1: amount 2: student name */
			__( 'Payment of %1$s updated for %2$s.', 'class-manager-pro' ),
			cmp_format_money( $payload['amount'] ),
			$payment->student_name ? $payment->student_name : __( 'Unknown student', 'class-manager-pro' )
		)
	);

	return true;
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
			'search'         => '',
			'payment_mode'   => '',
			'student_id'     => 0,
			'batch_id'       => 0,
			'balance_status' => '',
			'assignment_status' => '',
			'deleted_status' => 'active',
		)
	);

	$where  = array();
	$params = array();

	if ( 'trash' === $args['deleted_status'] ) {
		$where[] = 'p.is_deleted = 1';
	} elseif ( 'all' !== $args['deleted_status'] ) {
		$where[] = 'p.is_deleted = 0';
	}

	if ( '' !== $args['search'] ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';

		$where[] = '(CAST(p.id AS CHAR) LIKE %s OR p.transaction_id LIKE %s OR s.name LIKE %s OR s.unique_id LIKE %s OR s.phone LIKE %s OR s.email LIKE %s OR c.name LIKE %s OR b.batch_name LIKE %s)';
		$params  = array_merge( $params, array( $like, $like, $like, $like, $like, $like, $like, $like ) );
	}

	if ( '' !== $args['payment_mode'] ) {
		$where[]  = 'p.payment_mode = %s';
		$params[] = cmp_clean_enum( $args['payment_mode'], cmp_payment_modes(), 'manual' );
	}

	if ( ! empty( $args['student_id'] ) ) {
		$where[]  = 'p.student_id = %d';
		$params[] = absint( $args['student_id'] );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = cmp_payment_batch_sql( 'p', 's' ) . ' = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( 'unassigned' === $args['assignment_status'] ) {
		$where[] = cmp_payment_batch_sql( 'p', 's' ) . ' = 0';
	} elseif ( 'assigned' === $args['assignment_status'] ) {
		$where[] = cmp_payment_batch_sql( 'p', 's' ) . ' > 0';
	}

	if ( 'pending' === $args['balance_status'] ) {
		$where[] = 'GREATEST(s.total_fee - s.paid_fee, 0) > 0';
	} elseif ( 'paid' === $args['balance_status'] ) {
		$where[] = 'GREATEST(s.total_fee - s.paid_fee, 0) <= 0';
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
	$class_context_sql = cmp_payment_class_sql( 'p', 's' );
	$batch_context_sql = cmp_payment_batch_sql( 'p', 's' );
	$sql         = 'SELECT COUNT(*) FROM ' . cmp_table( 'payments' ) . ' p
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = ' . $class_context_sql . '
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = ' . $batch_context_sql;

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
			'search'         => '',
			'payment_mode'   => '',
			'student_id'     => 0,
			'batch_id'       => 0,
			'balance_status' => '',
			'assignment_status' => '',
			'deleted_status' => 'active',
			'limit'          => 0,
			'offset'         => 0,
		)
	);

	$query_parts = cmp_get_payment_query_parts( $args );
	$where       = $query_parts['where'];
	$params      = $query_parts['params'];
	$class_context_sql = cmp_payment_class_sql( 'p', 's' );
	$batch_context_sql = cmp_payment_batch_sql( 'p', 's' );

	$sql = 'SELECT
			p.*,
			s.name AS student_name,
			s.phone AS student_phone,
			s.unique_id AS student_unique_id,
			s.email AS student_email,
			s.total_fee AS student_total_fee,
			s.paid_fee AS student_paid_fee,
			c.name AS class_name,
			b.batch_name
		FROM ' . cmp_table( 'payments' ) . ' p
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = ' . $class_context_sql . '
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = ' . $batch_context_sql;

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

	$payment_income = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(' . cmp_payment_reporting_amount_sql( 'payments' ) . '), 0) FROM ' . cmp_table( 'payments' ) . ' payments WHERE is_deleted = 0' );
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
			'SELECT COALESCE(SUM(' . cmp_payment_reporting_amount_sql( 'payments' ) . '), 0) FROM ' . cmp_table( 'payments' ) . ' payments WHERE is_deleted = 0 AND payment_date BETWEEN %s AND %s',
			$bounds['start'],
			$bounds['end']
		)
	);
	$payment_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . cmp_table( 'payments' ) . ' WHERE is_deleted = 0 AND payment_date BETWEEN %s AND %s',
			$bounds['start'],
			$bounds['end']
		)
	);
	$total_students = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) );
	$total_paid_fee = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(paid_fee), 0) FROM ' . cmp_table( 'students' ) );
	$total_fee      = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(total_fee), 0) FROM ' . cmp_table( 'students' ) );
	$pending_fees   = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(GREATEST(total_fee - paid_fee, 0)), 0) FROM ' . cmp_table( 'students' ) );
	$pending_students = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE GREATEST(total_fee - paid_fee, 0) > 0' );
	$completed_students = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE status = %s', 'completed' ) );
	$active_students    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . cmp_table( 'students' ) . ' WHERE status = %s', 'active' ) );
	$total_teachers     = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT teacher_user_id) FROM ' . cmp_table( 'batches' ) . ' WHERE teacher_user_id > 0' );
	$collection_rate    = $total_fee > 0 ? round( ( $total_paid_fee / $total_fee ) * 100, 2 ) : 0;

	return array(
		'total_classes'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'classes' ) ),
		'total_batches'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . cmp_table( 'batches' ) ),
		'total_students'  => $total_students,
		'filtered_students' => $student_count,
		'active_students' => $active_students,
		'completed_students' => $completed_students,
		'total_teachers'  => $total_teachers,
		'total_revenue'   => (float) $finance['total_income'],
		'filtered_revenue' => $revenue,
		'filtered_payments' => $payment_count,
		'pending_fees'    => $pending_fees,
		'pending_students' => $pending_students,
		'collection_rate' => $collection_rate,
		'total_expense'   => (float) $finance['total_expense'],
		'net_income'      => (float) $finance['net_income'],
		'range'           => $range,
		'range_label'     => $label,
	);
}

/**
 * Returns students with outstanding or pending-payment balances.
 *
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_dashboard_pending_students( $limit = 8 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );
	$where = array(
		'GREATEST(s.total_fee - s.paid_fee, 0) > 0',
		cmp_get_failed_payment_placeholder_student_where_sql( 's' ),
	);

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT s.*, c.name AS class_name, b.batch_name, GREATEST(s.total_fee - s.paid_fee, 0) AS pending_fee
			FROM ' . cmp_table( 'students' ) . ' s
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY pending_fee DESC, s.created_at DESC, s.id DESC
			LIMIT %d',
			$limit
		)
	);
}

/**
 * Returns the most recently created students.
 *
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_recent_students( $limit = 8 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );
	$where = cmp_get_failed_payment_placeholder_student_where_sql( 's' );

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT s.*, c.name AS class_name, b.batch_name
			FROM ' . cmp_table( 'students' ) . ' s
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = s.class_id
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = s.batch_id
			WHERE ' . $where . '
			ORDER BY s.created_at DESC, s.id DESC
			LIMIT %d',
			$limit
		)
	);
}

/**
 * Returns course completion insights grouped by class.
 *
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_dashboard_course_completion_rows( $limit = 8 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT
				c.id,
				c.name AS class_name,
				COUNT(s.id) AS total_students,
				SUM(CASE WHEN s.status = %s THEN 1 ELSE 0 END) AS completed_students,
				SUM(CASE WHEN s.status = %s THEN 1 ELSE 0 END) AS active_students,
				SUM(CASE WHEN s.status = %s THEN 1 ELSE 0 END) AS dropped_students,
				CASE
					WHEN COUNT(s.id) > 0 THEN ROUND((SUM(CASE WHEN s.status = %s THEN 1 ELSE 0 END) / COUNT(s.id)) * 100, 2)
					ELSE 0
				END AS completion_rate
			FROM ' . cmp_table( 'classes' ) . ' c
			LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.class_id = c.id
			GROUP BY c.id, c.name
			HAVING COUNT(s.id) > 0
			ORDER BY completion_rate DESC, total_students DESC, c.name ASC
			LIMIT %d',
			'completed',
			'active',
			'dropped',
			'completed',
			$limit
		)
	);
}

/**
 * Returns batch-wise performance rows for overview screens.
 *
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_dashboard_batch_performance_rows( $limit = 8 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT
				b.id,
				b.batch_name,
				b.status,
				c.name AS class_name,
				COALESCE(st.student_count, 0) AS student_count,
				COALESCE(st.pending_students, 0) AS pending_students,
				COALESCE(st.pending_amount, 0) AS pending_amount,
				COALESCE(st.payment_completion, 0) AS payment_completion,
				(COALESCE(pay.revenue, 0) + COALESCE(b.manual_income, 0)) AS revenue
			FROM ' . cmp_table( 'batches' ) . ' b
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			LEFT JOIN (
				SELECT
					batch_id,
					COUNT(*) AS student_count,
					SUM(CASE WHEN GREATEST(total_fee - paid_fee, 0) > 0 THEN 1 ELSE 0 END) AS pending_students,
					SUM(GREATEST(total_fee - paid_fee, 0)) AS pending_amount,
					CASE
						WHEN COUNT(*) > 0 THEN ROUND((SUM(CASE WHEN total_fee <= 0 OR paid_fee >= total_fee THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2)
						ELSE 0
					END AS payment_completion
				FROM ' . cmp_table( 'students' ) . '
				GROUP BY batch_id
			) st ON st.batch_id = b.id
			LEFT JOIN (
				SELECT ' . cmp_payment_batch_sql( 'p', 's' ) . ' AS batch_id, SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . ') AS revenue
				FROM ' . cmp_table( 'payments' ) . ' p
				INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
				WHERE p.is_deleted = 0
				GROUP BY batch_id
			) pay ON pay.batch_id = b.id
			ORDER BY revenue DESC, pending_amount DESC, student_count DESC, b.created_at DESC
			LIMIT %d',
			$limit
		)
	);
}

/**
 * Returns class revenue overview rows.
 *
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_dashboard_class_revenue_rows( $limit = 6 ) {
	global $wpdb;

	$limit = max( 1, absint( $limit ) );

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT
				c.id,
				c.name AS class_name,
				COALESCE(st.student_count, 0) AS student_count,
				COALESCE(st.pending_amount, 0) AS pending_amount,
				COALESCE(pay.revenue, 0) AS revenue
			FROM ' . cmp_table( 'classes' ) . ' c
			LEFT JOIN (
				SELECT class_id, COUNT(*) AS student_count, SUM(GREATEST(total_fee - paid_fee, 0)) AS pending_amount
				FROM ' . cmp_table( 'students' ) . '
				GROUP BY class_id
			) st ON st.class_id = c.id
			LEFT JOIN (
				SELECT ' . cmp_payment_class_sql( 'p', 's' ) . ' AS class_id, SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . ') AS revenue
				FROM ' . cmp_table( 'payments' ) . ' p
				INNER JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
				WHERE p.is_deleted = 0
				GROUP BY class_id
			) pay ON pay.class_id = c.id
			WHERE COALESCE(st.student_count, 0) > 0 OR COALESCE(pay.revenue, 0) > 0
			ORDER BY revenue DESC, student_count DESC, c.name ASC
			LIMIT %d',
			$limit
		)
	);
}

/**
 * Returns teacher overview rows for dashboard or analytics.
 *
 * @param int $limit Maximum rows.
 * @return array
 */
function cmp_get_dashboard_teacher_rows( $limit = 6 ) {
	$rows = cmp_get_teacher_overview_rows( array(), 3, true );

	if ( $limit > 0 ) {
		$rows = array_slice( $rows, 0, absint( $limit ) );
	}

	return $rows;
}

/**
 * Builds the data required by the dashboard page.
 *
 * @param string $range Optional date range.
 * @return array
 */
function cmp_get_dashboard_snapshot( $range = 'month' ) {
	$range = cmp_get_dashboard_range( $range );

	return array(
		'metrics'                  => cmp_get_dashboard_metrics( $range ),
		'pending_students'         => cmp_get_dashboard_pending_students( 8 ),
		'recent_students'          => cmp_get_recent_students( 8 ),
		'course_completion_rows'   => cmp_get_dashboard_course_completion_rows( 8 ),
		'batch_performance_rows'   => cmp_get_dashboard_batch_performance_rows( 8 ),
		'class_revenue_rows'       => cmp_get_dashboard_class_revenue_rows( 6 ),
		'teacher_rows'             => cmp_get_dashboard_teacher_rows( 6 ),
		'chart_data'               => array(
			'dashboardRevenue' => cmp_get_monthly_revenue( 6 ),
			'studentStatus'    => cmp_get_student_status_counts(),
		),
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
			"SELECT DATE_FORMAT(payment_date, '%%Y-%%m') AS month_key, SUM(" . cmp_payment_reporting_amount_sql( 'payments' ) . ') AS total FROM ' . cmp_table( 'payments' ) . ' payments WHERE is_deleted = 0 AND payment_date >= %s GROUP BY month_key',
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
		'SELECT COALESCE(c.name, \'Unassigned\') AS class_name, SUM(' . cmp_payment_reporting_amount_sql( 'p' ) . ') AS total
		FROM ' . cmp_table( 'payments' ) . ' p
		LEFT JOIN ' . cmp_table( 'students' ) . ' s ON s.id = p.student_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = ' . cmp_payment_class_sql( 'p', 's' ) . '
		WHERE p.is_deleted = 0
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
		echo '<tr><td colspan="8">' . esc_html__( 'No records found.', 'class-manager-pro' ) . '</td></tr>';
		return ob_get_clean();
	}

	foreach ( $students as $student ) {
		$remaining = max( 0, (float) $student->total_fee - (float) $student->paid_fee );
		echo '<tr data-cmp-row-id="student-' . esc_attr( (int) $student->id ) . '">';
		echo '<td>' . esc_html( $student->name ) . '<br><span class="cmp-muted">' . esc_html( $student->unique_id ) . '</span></td>';
		echo '<td>' . esc_html( $student->phone ) . '</td>';
		echo '<td>' . esc_html( $student->email ) . '</td>';
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
		$batch_label    = cmp_get_student_batch_label( $student );
		$view_url       = cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) );
		$edit_url       = cmp_admin_url( 'cmp-students', array( 'action' => 'edit', 'id' => (int) $student->id ) );
		$payment_url    = cmp_admin_url( 'cmp-payments', array( 'student_id' => (int) $student->id ) ) . '#cmp-add-payment';
		$profile_url    = cmp_get_student_profile_url( $student );
		$email_url      = cmp_get_email_reminder_url(
			$student,
			'cmp-students',
			array(
				'action' => 'view',
				'id'     => (int) $student->id,
			)
		);
		$whatsapp_url   = cmp_get_whatsapp_reminder_url( $student );
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
		echo '<td>' . esc_html( $batch_label ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->total_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $student->paid_fee ) ) . '</td>';
		echo '<td>' . esc_html( cmp_format_money( $remaining ) ) . '</td>';
		echo '<td><span class="cmp-status cmp-status-' . esc_attr( $payment_status['key'] ) . '">' . esc_html( $payment_status['label'] ) . '</span></td>';
		echo '<td><span class="cmp-status cmp-status-' . esc_attr( $student->status ) . '" data-cmp-status-badge="student-status-' . esc_attr( (int) $student->id ) . '">' . esc_html( ucfirst( $student->status ) ) . '</span></td>';
		echo '<td class="cmp-actions">';
		echo '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'class-manager-pro' ) . '</a> ';
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'class-manager-pro' ) . '</a> ';
		echo '<a href="' . esc_url( $payment_url ) . '">' . esc_html__( 'Payment', 'class-manager-pro' ) . '</a> ';
		if ( $profile_url ) {
			echo '<a href="' . esc_url( $profile_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View Profile', 'class-manager-pro' ) . '</a> ';
		}
		if ( $email_url ) {
			echo '<a class="cmp-send-email-link" href="' . esc_url( $email_url ) . '" data-cmp-send-email="1" data-cmp-student-id="' . esc_attr( (int) $student->id ) . '" data-cmp-feedback="#cmp-student-bulk-feedback">' . esc_html__( 'Send Email', 'class-manager-pro' ) . '</a> ';
		}
		if ( $whatsapp_url ) {
			echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'WhatsApp', 'class-manager-pro' ) . '</a> ';
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

		case 'payment':
			return sprintf( _n( '%d payment moved to Trash.', '%d payments moved to Trash.', $deleted_count, 'class-manager-pro' ), $deleted_count );
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

		case 'payment':
			return cmp_delete_payment( $entity_id );
	}

	return new WP_Error( 'cmp_invalid_entity_type', __( 'Invalid entity type.', 'class-manager-pro' ) );
}

/**
 * AJAX delete handler for classes, batches, students, and payments.
 *
 * @return void
 */
function cmp_delete_item() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_delete_nonce', 'nonce' );

	$entity_id   = absint( cmp_field( $_POST, 'id', 0 ) );
	$entity_type = sanitize_key( cmp_field( $_POST, 'type' ) );

	if ( ! $entity_id || ! in_array( $entity_type, array( 'student', 'batch', 'class', 'payment' ), true ) ) {
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
			'entity_type' => $entity_type,
		)
	);
}

/**
 * Sends follow-up emails to a list of students.
 *
 * @param array $student_ids Student IDs.
 * @return array
 */
function cmp_bulk_send_student_emails( $student_ids ) {
	$students = cmp_get_students_by_ids( $student_ids );
	$summary  = array(
		'sent'    => 0,
		'skipped' => 0,
		'failed'  => 0,
	);

	foreach ( $students as $student ) {
		if ( empty( $student->email ) ) {
			++$summary['skipped'];
			continue;
		}

		$result = cmp_send_fee_reminder( $student, 'email', cmp_build_fee_reminder_message( $student, 'email' ) );

		if ( ! empty( $result['success'] ) ) {
			cmp_log_reminder( $student, 'email', 'sent', $result['message'] );
			++$summary['sent'];
			continue;
		}

		cmp_log_reminder( $student, 'email', 'failed', isset( $result['message'] ) ? $result['message'] : '' );
		++$summary['failed'];
	}

	return $summary;
}

/**
 * Clears pending fee balances for a list of students by inserting manual payments.
 *
 * @param array $student_ids Student IDs.
 * @return array
 */
function cmp_bulk_clear_student_payments( $student_ids ) {
	$students = cmp_get_students_by_ids( $student_ids );
	$summary  = array(
		'cleared'      => 0,
		'already_paid' => 0,
		'failed'       => 0,
	);

	foreach ( $students as $student ) {
		$balance = max( 0, (float) $student->total_fee - (float) $student->paid_fee );

		if ( $balance <= 0 ) {
			++$summary['already_paid'];
			continue;
		}

		$result = cmp_insert_payment(
			array(
				'student_id'     => (int) $student->id,
				'class_id'       => (int) $student->class_id,
				'batch_id'       => (int) $student->batch_id,
				'amount'         => $balance,
				'payment_mode'   => 'manual',
				'transaction_id' => '',
				'payment_date'   => cmp_current_datetime(),
			)
		);

		if ( is_wp_error( $result ) ) {
			++$summary['failed'];
			continue;
		}

		++$summary['cleared'];
	}

	return $summary;
}

/**
 * Updates the status for a list of students.
 *
 * @param array  $student_ids Student IDs.
 * @param string $status Target status.
 * @return int
 */
function cmp_bulk_update_student_status( $student_ids, $status ) {
	global $wpdb;

	$students = cmp_get_students_by_ids( $student_ids );
	$updated  = 0;
	$status   = cmp_clean_enum( $status, cmp_student_statuses(), 'active' );

	foreach ( $students as $student ) {
		$result = $wpdb->update(
			cmp_table( 'students' ),
			array(
				'status' => $status,
			),
			array(
				'id' => (int) $student->id,
			),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			cmp_log_activity(
				array(
					'student_id' => (int) $student->id,
					'batch_id'   => (int) $student->batch_id,
					'class_id'   => (int) $student->class_id,
					'action'     => 'student_updated',
					'message'    => sprintf(
						/* translators: 1: student name 2: status */
						__( 'Student "%1$s" status changed to %2$s.', 'class-manager-pro' ),
						$student->name,
						ucfirst( $status )
					),
				)
			);
			++$updated;
		}
	}

	return $updated;
}

/**
 * Updates the status for a list of batches.
 *
 * @param array  $batch_ids Batch IDs.
 * @param string $status Target status.
 * @return int
 */
function cmp_bulk_update_batch_status( $batch_ids, $status ) {
	$batches = cmp_get_batches_by_ids( $batch_ids );
	$updated = 0;

	foreach ( $batches as $batch ) {
		$result = cmp_update_batch(
			(int) $batch->id,
			array(
				'class_id'         => (int) $batch->class_id,
				'batch_name'       => $batch->batch_name,
				'course_id'        => (int) $batch->course_id,
				'start_date'       => $batch->start_date,
				'fee_due_date'     => $batch->fee_due_date,
				'status'           => $status,
				'razorpay_link'    => $batch->razorpay_link,
				'batch_fee'        => (float) $batch->batch_fee,
				'is_free'          => (int) $batch->is_free,
				'teacher_user_id'  => (int) $batch->teacher_user_id,
				'intake_limit'     => (int) $batch->intake_limit,
				'class_days'       => $batch->class_days,
				'manual_income'    => (float) $batch->manual_income,
				'razorpay_page_id' => $batch->razorpay_page_id,
			)
		);

		if ( ! is_wp_error( $result ) ) {
			++$updated;
		}
	}

	return $updated;
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

	if ( in_array( $task, array( 'change_batch', 'move_to_batch' ), true ) ) {
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
				'entity_type' => 'student',
			)
		);
	}

	if ( 'send_mail' === $task ) {
		if ( 'student' !== $entity_type ) {
			wp_send_json_error(
				array(
					'message' => __( 'This bulk action is not supported for the selected entity.', 'class-manager-pro' ),
				),
				400
			);
		}

		$summary = cmp_bulk_send_student_emails( $entity_ids );
		$message = sprintf(
			/* translators: %d: email recipients */
			__( 'Emails sent to %d users.', 'class-manager-pro' ),
			(int) $summary['sent']
		);

		if ( $summary['skipped'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: skipped students */
				_n( '%d student skipped because email is missing.', '%d students skipped because email is missing.', $summary['skipped'], 'class-manager-pro' ),
				(int) $summary['skipped']
			);
		}

		if ( $summary['failed'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: failed students */
				_n( '%d email failed.', '%d emails failed.', $summary['failed'], 'class-manager-pro' ),
				(int) $summary['failed']
			);
		}

		wp_send_json_success(
			array(
				'message'     => $message,
				'entity_type' => 'student',
			)
		);
	}

	if ( 'clear_payment' === $task ) {
		if ( 'student' !== $entity_type ) {
			wp_send_json_error(
				array(
					'message' => __( 'This bulk action is not supported for the selected entity.', 'class-manager-pro' ),
				),
				400
			);
		}

		$summary = cmp_bulk_clear_student_payments( $entity_ids );
		$message = sprintf(
			/* translators: %d: cleared students */
			_n( 'Payment cleared for %d student.', 'Payments cleared for %d students.', $summary['cleared'], 'class-manager-pro' ),
			(int) $summary['cleared']
		);

		if ( $summary['already_paid'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: already paid students */
				_n( '%d student was already fully paid.', '%d students were already fully paid.', $summary['already_paid'], 'class-manager-pro' ),
				(int) $summary['already_paid']
			);
		}

		if ( $summary['failed'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: failed students */
				_n( '%d student could not be updated.', '%d students could not be updated.', $summary['failed'], 'class-manager-pro' ),
				(int) $summary['failed']
			);
		}

		wp_send_json_success(
			array(
				'message'     => $message,
				'entity_type' => 'student',
			)
		);
	}

	if ( 'change_status' === $task ) {
		$target_status = sanitize_key( cmp_field( $_POST, 'target_status' ) );

		if ( 'student' === $entity_type ) {
			if ( ! in_array( $target_status, cmp_student_statuses(), true ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Choose a valid student status.', 'class-manager-pro' ),
					),
					400
				);
			}

			$updated = cmp_bulk_update_student_status( $entity_ids, $target_status );
		} elseif ( 'batch' === $entity_type ) {
			if ( ! in_array( $target_status, cmp_batch_statuses(), true ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Choose a valid batch status.', 'class-manager-pro' ),
					),
					400
				);
			}

			$updated = cmp_bulk_update_batch_status( $entity_ids, $target_status );
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'This bulk action is not supported for the selected entity.', 'class-manager-pro' ),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: updated records */
					_n( '%d record updated.', '%d records updated.', $updated, 'class-manager-pro' ),
					$updated
				),
				'entity_type'          => $entity_type,
				'updated_rows'         => array_map(
					static function ( $entity_id ) use ( $entity_type ) {
						return sanitize_key( $entity_type ) . '-' . absint( $entity_id );
					},
					$entity_ids
				),
				'updated_status'       => $target_status,
				'updated_status_label' => ucfirst( $target_status ),
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
				'entity_type'  => $entity_type,
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
		fputcsv( $output, array( 'Payment ID', 'Student Name', 'Student ID', 'Phone', 'Email', 'Class', 'Batch', 'Amount', 'Original Amount', 'Charge Amount', 'Final Amount', 'Payment Mode', 'Transaction ID', 'Date' ) );
		$payments = cmp_get_payments(
			array(
				'search'           => sanitize_text_field( cmp_field( $_GET, 'search' ) ),
				'payment_mode'   => sanitize_key( cmp_field( $_GET, 'payment_mode' ) ),
				'balance_status' => sanitize_key( cmp_field( $_GET, 'balance_status' ) ),
				'assignment_status' => sanitize_key( cmp_field( $_GET, 'assignment_status' ) ),
			)
		);

		foreach ( $payments as $payment ) {
			fputcsv(
				$output,
				array(
					$payment->id,
					$payment->student_name,
					$payment->student_unique_id,
					$payment->student_phone,
					$payment->student_email,
					$payment->class_name,
					$payment->batch_name,
					$payment->amount,
					isset( $payment->original_amount ) ? $payment->original_amount : $payment->amount,
					isset( $payment->charge_amount ) ? $payment->charge_amount : 0,
					isset( $payment->final_amount ) ? $payment->final_amount : $payment->amount,
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
 * Sends a single student follow-up email and returns a structured result.
 *
 * @param int $student_id Student ID.
 * @return array
 */
function cmp_send_student_follow_up_email_by_id( $student_id ) {
	$student_id = absint( $student_id );

	if ( ! $student_id ) {
		return array(
			'success' => false,
			'message' => __( 'Student not found.', 'class-manager-pro' ),
		);
	}

	$student = cmp_get_student( $student_id );

	if ( ! $student ) {
		return array(
			'success' => false,
			'message' => __( 'Student not found.', 'class-manager-pro' ),
		);
	}

	if ( empty( $student->email ) ) {
		return array(
			'success' => false,
			'message' => __( 'Student email address is missing.', 'class-manager-pro' ),
		);
	}

	$result = cmp_send_fee_reminder( $student, 'email', cmp_build_fee_reminder_message( $student, 'email' ) );

	if ( ! empty( $result['success'] ) ) {
		cmp_log_reminder( $student, 'email', 'sent', $result['message'] );

		return array(
			'success'    => true,
			'message'    => __( 'Email sent successfully.', 'class-manager-pro' ),
			'student_id' => (int) $student_id,
		);
	}

	cmp_log_reminder( $student, 'email', 'failed', isset( $result['message'] ) ? $result['message'] : '' );

	return array(
		'success'    => false,
		'message'    => __( 'Email could not be sent.', 'class-manager-pro' ),
		'student_id' => (int) $student_id,
	);
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

	$id          = absint( cmp_field( $_POST, 'id', 0 ) );
	$return_page = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-payments' ), 'cmp-payments' );
	$return_args = array();
	$result      = $id ? cmp_update_payment( $id, $_POST ) : cmp_insert_payment( $_POST );

	if ( is_wp_error( $result ) ) {
		if ( $id ) {
			$return_args = array(
				'action' => 'edit',
				'id'     => $id,
			);
		}

		cmp_redirect( $return_page, $result->get_error_message(), 'error', $return_args );
	}

	$payment_id      = $id ? $id : (int) $result;
	$success_message = $id ? __( 'Payment updated successfully.', 'class-manager-pro' ) : __( 'Payment saved successfully.', 'class-manager-pro' );
	$notice          = cmp_pop_payment_operation_notice();

	if ( $payment_id ) {
		$return_args = array(
			'action' => 'view',
			'id'     => $payment_id,
		);
	}

	if ( ! empty( $notice['message'] ) ) {
		cmp_redirect( $return_page, trim( $notice['message'] . ' ' . $success_message ), ! empty( $notice['type'] ) ? $notice['type'] : 'warning', $return_args );
	}

	cmp_redirect( $return_page, $success_message, 'success', $return_args );
}

/**
 * Handles payment deletion.
 */
function cmp_handle_delete_payment() {
	cmp_require_manage_options();

	$id          = absint( cmp_field( $_GET, 'id', 0 ) );
	$return_page = cmp_clean_return_page( cmp_field( $_GET, 'return_page', 'cmp-payments' ), 'cmp-payments' );
	$return_args = array();
	$student_id  = absint( cmp_field( $_GET, 'student_id', 0 ) );

	check_admin_referer( 'cmp_delete_payment_' . $id );

	if ( 'cmp-students' === $return_page && $student_id ) {
		$return_args = array(
			'action' => 'view',
			'id'     => $student_id,
		);
	}

	$result = cmp_delete_payment( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $return_page, $result->get_error_message(), 'error', $return_args );
	}

	cmp_redirect( $return_page, __( 'Payment moved to Trash successfully.', 'class-manager-pro' ), 'success', $return_args );
}

/**
 * Handles payment restore.
 */
function cmp_handle_restore_payment() {
	cmp_require_manage_options();

	$id          = absint( cmp_field( $_GET, 'id', 0 ) );
	$return_page = cmp_clean_return_page( cmp_field( $_GET, 'return_page', 'cmp-payments-trash' ), 'cmp-payments-trash' );
	$return_args = array();

	check_admin_referer( 'cmp_restore_payment_' . $id );

	$result = cmp_restore_payment( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $return_page, $result->get_error_message(), 'error', $return_args );
	}

	$notice = cmp_pop_payment_operation_notice();

	if ( 'cmp-payments' === $return_page ) {
		$return_args = array(
			'action' => 'view',
			'id'     => $id,
		);
	}

	if ( ! empty( $notice['message'] ) ) {
		cmp_redirect( $return_page, trim( $notice['message'] . ' ' . __( 'Payment restored successfully.', 'class-manager-pro' ) ), ! empty( $notice['type'] ) ? $notice['type'] : 'warning', $return_args );
	}

	cmp_redirect( $return_page, __( 'Payment restored successfully.', 'class-manager-pro' ), 'success', $return_args );
}

/**
 * Handles permanent payment deletion.
 */
function cmp_handle_force_delete_payment() {
	cmp_require_manage_options();

	$id          = absint( cmp_field( $_GET, 'id', 0 ) );
	$return_page = cmp_clean_return_page( cmp_field( $_GET, 'return_page', 'cmp-payments-trash' ), 'cmp-payments-trash' );

	check_admin_referer( 'cmp_force_delete_payment_' . $id );

	$result = cmp_force_delete_payment( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $return_page, $result->get_error_message(), 'error' );
	}

	cmp_redirect( $return_page, __( 'Payment permanently deleted.', 'class-manager-pro' ), 'success' );
}

/**
 * Handles expense form submission.
 */
function cmp_handle_save_expense() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_expense' );

	$batch_id    = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	$return_tab  = sanitize_key( cmp_field( $_POST, 'return_tab', 'expenses' ) );
	$return_args = $batch_id ? array( 'action' => 'view', 'id' => $batch_id ) : array();

	if ( in_array( $return_tab, array( 'students', 'attendance', 'expenses' ), true ) ) {
		$return_args['tab'] = $return_tab;
	}

	$result = cmp_insert_expense( $_POST );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-batches', $result->get_error_message(), 'error', $return_args );
	}

	cmp_redirect( 'cmp-batches', __( 'Expense saved successfully.', 'class-manager-pro' ), 'success', $return_args );
}

/**
 * Handles expense deletion.
 */
function cmp_handle_delete_expense() {
	cmp_require_manage_options();

	$expense_id   = absint( cmp_field( $_GET, 'id', 0 ) );
	$batch_id     = absint( cmp_field( $_GET, 'batch_id', 0 ) );
	$return_tab   = sanitize_key( cmp_field( $_GET, 'tab', 'expenses' ) );
	$return_args  = $batch_id ? array( 'action' => 'view', 'id' => $batch_id ) : array();

	if ( in_array( $return_tab, array( 'students', 'attendance', 'expenses' ), true ) ) {
		$return_args['tab'] = $return_tab;
	}

	check_admin_referer( 'cmp_delete_expense_' . $expense_id );

	$result = cmp_delete_expense( $expense_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-batches', $result->get_error_message(), 'error', $return_args );
	}

	cmp_redirect( 'cmp-batches', __( 'Expense deleted successfully.', 'class-manager-pro' ), 'success', $return_args );
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
 * Disables the legacy Razorpay sync automation and clears pending cron jobs.
 *
 * @return void
 */
function cmp_disable_razorpay_payment_sync_automation() {
	static $disabled = false;

	if ( $disabled ) {
		return;
	}

	$disabled = true;

	update_option( 'cmp_automation_sync_enabled', '0' );
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
 * Imports all captured payments for a Razorpay Payment Page.
 *
 * @param string $page_id Razorpay Payment Page ID.
 * @param string $page_name Optional Payment Page name.
 * @return array|WP_Error
 */
function cmp_import_razorpay_payment_page( $page_id, $page_name = '' ) {
	$page_id   = sanitize_text_field( $page_id );
	$page_name = cmp_clean_title_text( $page_name );

	if ( '' === $page_id ) {
		return new WP_Error( 'cmp_import_page_invalid', __( 'Please enter a Razorpay Payment Page ID.', 'class-manager-pro' ) );
	}

	$payments = cmp_get_successful_razorpay_payments_for_link( $page_id, $page_name );

	if ( is_wp_error( $payments ) ) {
		return $payments;
	}

	if ( empty( $payments ) ) {
		return new WP_Error( 'cmp_import_page_empty', __( 'No payments found for this Payment Page ID', 'class-manager-pro' ) );
	}

	$summary = array(
		'fetched'   => count( $payments ),
		'imported'  => 0,
		'duplicate' => 0,
		'skipped'   => 0,
		'failed'    => 0,
	);

	foreach ( $payments as $payment ) {
		$result = cmp_import_razorpay_payment( $payment );
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
	$name      = cmp_prepare_import_student_name( cmp_first_scalar_value( array( $notes['name'] ?? '', $notes['student_name'] ?? '', $notes['customer_name'] ?? '', $payment['name'] ?? '', $payment['customer_name'] ?? '' ) ) );
	$email     = sanitize_email( cmp_first_scalar_value( array( $notes['email'] ?? '', $notes['student_email'] ?? '', $notes['customer_email'] ?? '', $payment['email'] ?? '', $payment['customer_email'] ?? '' ) ) );
	$phone     = cmp_clean_import_phone_number( cmp_first_scalar_value( array( $notes['phone'] ?? '', $notes['mobile'] ?? '', $notes['contact'] ?? '', $notes['student_phone'] ?? '', $notes['student_mobile'] ?? '', $payment['contact'] ?? '', $payment['phone'] ?? '', $payment['customer_contact'] ?? '' ) ) );
	$total_fee = cmp_money_value( isset( $notes['total_fee'] ) ? $notes['total_fee'] : 0 );
	$amount    = cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 );

	if ( '' === $name ) {
		if ( '' !== $email ) {
			$email_parts = explode( '@', $email );
			$name        = sanitize_text_field( isset( $email_parts[0] ) ? $email_parts[0] : $email );
		} else {
			$name = $phone;
		}
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
		$total_fee = $amount > 0 ? $amount : cmp_get_batch_effective_fee( $batch );
	}

	if ( $amount > 0 ) {
		$total_fee = max( $amount, $total_fee );
	}

	$student = cmp_find_reusable_import_student( $batch, $name, $email, $phone, (int) $batch->class_id );

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
				'total_fee' => (float) $student->paid_fee > 0 && (float) $student->total_fee > 0 ? (float) $student->total_fee : $total_fee,
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

	$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
		? wp_date( 'Y-m-d H:i:s', (int) $payment['created_at'] )
		: cmp_current_datetime();
	$payment_student = cmp_get_student( (int) $student_id );
	$payment_class_id = $batch ? (int) $batch->class_id : ( $payment_student ? (int) $payment_student->class_id : 0 );
	$payment_batch_id = $batch ? (int) $batch->id : ( $payment_student ? (int) $payment_student->batch_id : 0 );

	$result = cmp_insert_payment(
		array(
			'student_id'     => (int) $student_id,
			'class_id'       => $payment_class_id,
			'batch_id'       => $payment_batch_id,
			'amount'         => $amount,
			'apply_gateway_charge' => 1,
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

	$amount = cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 );

	if ( $amount <= 0 ) {
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

	$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
		? wp_date( 'Y-m-d H:i:s', (int) $payment['created_at'] )
		: cmp_current_datetime();

	$result = cmp_insert_payment(
		array(
			'student_id'     => (int) $student_id,
			'class_id'       => (int) $batch->class_id,
			'batch_id'       => (int) $batch->id,
			'amount'         => $amount,
			'apply_gateway_charge' => 1,
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
 * Gets a readable Payment Page title from a Razorpay payment.
 *
 * @param array $payment Razorpay payment row.
 * @return string
 */
function cmp_get_razorpay_payment_page_title_from_payment( $payment ) {
	$meta = cmp_razorpay_entity_meta( $payment );

	foreach ( array( $meta['payment_page_name'] ?? '', $meta['page_name'] ?? '', $meta['form_name'] ?? '', $meta['batch_name'] ?? '', $payment['description'] ?? '' ) as $candidate ) {
		$candidate = cmp_clean_title_text( $candidate );

		if ( '' !== $candidate ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Returns whether a captured payment belongs to the selected Payment Page.
 *
 * @param array  $payment Razorpay payment row.
 * @param string $page_id Payment Page ID.
 * @param string $page_name Payment Page name.
 * @return bool
 */
function cmp_razorpay_payment_matches_page( $payment, $page_id, $page_name = '' ) {
	$page_id        = sanitize_text_field( $page_id );
	$page_name      = cmp_clean_title_text( $page_name );
	$meta           = cmp_razorpay_entity_meta( $payment );
	$description    = isset( $payment['description'] ) && is_scalar( $payment['description'] ) ? sanitize_text_field( $payment['description'] ) : '';
	$reference      = cmp_normalize_razorpay_page_reference( $page_id );
	$search_tokens  = cmp_get_razorpay_page_reference_tokens( $page_id, $page_name );
	$search_fields  = array(
		isset( $payment['id'] ) ? $payment['id'] : '',
		cmp_razorpay_payment_link_id_from_payment( $payment ),
		$description,
		isset( $payment['payment_link_reference_id'] ) ? $payment['payment_link_reference_id'] : '',
		isset( $payment['reference_id'] ) ? $payment['reference_id'] : '',
		isset( $meta['payment_link_id'] ) ? $meta['payment_link_id'] : '',
		isset( $meta['razorpay_page_id'] ) ? $meta['razorpay_page_id'] : '',
		isset( $meta['payment_page_id'] ) ? $meta['payment_page_id'] : '',
		isset( $meta['page_id'] ) ? $meta['page_id'] : '',
		isset( $meta['page_slug'] ) ? $meta['page_slug'] : '',
		isset( $meta['payment_page_title'] ) ? $meta['payment_page_title'] : '',
		isset( $meta['page_title'] ) ? $meta['page_title'] : '',
		isset( $meta['payment_page_url'] ) ? $meta['payment_page_url'] : '',
		isset( $meta['payment_link'] ) ? $meta['payment_link'] : '',
		isset( $meta['short_url'] ) ? $meta['short_url'] : '',
		isset( $meta['form_name'] ) ? $meta['form_name'] : '',
		wp_json_encode( $meta ),
	);

	if ( '' !== $page_id && $page_id === cmp_razorpay_payment_link_id_from_payment( $payment ) ) {
		return true;
	}

	if ( ! empty( $reference['payment_link_id'] ) && $reference['payment_link_id'] === cmp_razorpay_payment_link_id_from_payment( $payment ) ) {
		return true;
	}

	foreach ( $search_fields as $field ) {
		foreach ( $search_tokens as $token ) {
			if ( cmp_razorpay_value_matches_token( $field, $token ) ) {
				return true;
			}
		}
	}

	return '' !== $page_name && '' !== $description && false !== stripos( $description, $page_name );
}

/**
 * Builds an index of Payment Pages discovered from successful payments.
 *
 * @param array|null $payments Optional payment collection.
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_page_index( $payments = null ) {
	if ( null === $payments ) {
		$payments = cmp_razorpay_fetch_collection( 'payments', 'items' );
	}

	if ( is_wp_error( $payments ) ) {
		return $payments;
	}

	$index = array();

	foreach ( cmp_filter_successful_razorpay_payments( $payments ) as $payment ) {
		$page_id = cmp_razorpay_payment_link_id_from_payment( $payment );

		if ( '' === $page_id || isset( $index[ $page_id ] ) ) {
			continue;
		}

		$page_name        = cmp_get_razorpay_payment_page_title_from_payment( $payment );
		$index[ $page_id ] = array(
			'id'          => $page_id,
			'description' => __( 'Razorpay Payment Page', 'class-manager-pro' ),
			'notes'       => array(
				'form_name' => '' !== $page_name ? $page_name : $page_id,
			),
		);
	}

	return $index;
}

/**
 * Gets Razorpay payment pages for selection screens.
 *
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_links_for_admin() {
	$index = cmp_get_razorpay_payment_page_index();

	if ( is_wp_error( $index ) ) {
		return $index;
	}

	return array_values( $index );
}

/**
 * Returns a Payment Page reference without requesting page editor content.
 *
 * @param string $page_id Payment Page ID.
 * @return array|WP_Error
 */
function cmp_get_razorpay_payment_link( $page_id ) {
	$page_id   = sanitize_text_field( $page_id );
	$reference = cmp_normalize_razorpay_page_reference( $page_id );

	if ( '' === $page_id ) {
		return new WP_Error( 'cmp_razorpay_page_missing', __( 'Please enter or choose a Razorpay page ID.', 'class-manager-pro' ) );
	}

	if ( ! empty( $reference['payment_link_id'] ) ) {
		$link = cmp_razorpay_api_get( 'payment_links/' . rawurlencode( $reference['payment_link_id'] ) );

		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}

	$links = cmp_razorpay_fetch_optional_collection( 'payment_links', 'items' );

	foreach ( $links as $link ) {
		if ( cmp_razorpay_payment_link_matches_reference( $link, $reference ) ) {
			$link_id = isset( $link['id'] ) ? sanitize_text_field( $link['id'] ) : '';

			if ( '' === $link_id ) {
				return $link;
			}

			$full_link = cmp_razorpay_api_get( 'payment_links/' . rawurlencode( $link_id ) );

			return is_wp_error( $full_link ) ? $link : $full_link;
		}
	}

	$public_page = cmp_get_razorpay_public_page_metadata( $page_id );

	return array(
		'id'          => ! empty( $reference['payment_link_id'] ) ? $reference['payment_link_id'] : $page_id,
		'description' => ! empty( $public_page['title'] ) ? $public_page['title'] : __( 'Razorpay Payment Page', 'class-manager-pro' ),
		'notes'       => array(
			'form_name'        => ! empty( $public_page['title'] ) ? $public_page['title'] : $page_id,
			'payment_page_id'  => ! empty( $public_page['payment_page_id'] ) ? $public_page['payment_page_id'] : $reference['payment_page_id'],
			'page_slug'        => ! empty( $public_page['page_slug'] ) ? $public_page['page_slug'] : $reference['page_slug'],
			'payment_page_url' => ! empty( $public_page['canonical_url'] ) ? $public_page['canonical_url'] : ( ! empty( $public_page['url'] ) ? $public_page['url'] : '' ),
		),
	);
}

/**
 * Normalizes a Razorpay Payment Link/Page reference from an ID, URL, or slug.
 *
 * @param string $reference Raw reference.
 * @return array
 */
function cmp_normalize_razorpay_page_reference( $reference ) {
	$reference = trim( sanitize_text_field( (string) $reference ) );
	$details   = array(
		'raw'             => $reference,
		'url'             => '',
		'payment_link_id' => '',
		'payment_page_id' => '',
		'page_slug'       => '',
		'tokens'          => array(),
	);

	if ( '' === $reference ) {
		return $details;
	}

	if ( preg_match( '/\b(plink_[A-Za-z0-9]+)\b/i', $reference, $matches ) ) {
		$details['payment_link_id'] = sanitize_text_field( $matches[1] );
	}

	if ( preg_match( '/\b(pl_[A-Za-z0-9]+)\b/i', $reference, $matches ) ) {
		$details['payment_page_id'] = sanitize_text_field( $matches[1] );
	}

	$candidate_urls = array();

	if ( false !== strpos( $reference, 'http://' ) || false !== strpos( $reference, 'https://' ) ) {
		$candidate_urls[] = esc_url_raw( $reference );
	} elseif ( preg_match( '#^(pages\.razorpay\.com|rzp\.io)/#i', $reference ) ) {
		$candidate_urls[] = esc_url_raw( 'https://' . ltrim( $reference, '/' ) );
	}

	foreach ( $candidate_urls as $candidate_url ) {
		$parts = wp_parse_url( $candidate_url );

		if ( empty( $parts['host'] ) ) {
			continue;
		}

		$details['url'] = $candidate_url;
		$host           = strtolower( (string) $parts['host'] );
		$path           = isset( $parts['path'] ) ? trim( (string) $parts['path'], '/' ) : '';
		$segments       = '' !== $path ? array_values( array_filter( explode( '/', $path ), 'strlen' ) ) : array();

		if ( false !== strpos( $host, 'pages.razorpay.com' ) && ! empty( $segments ) ) {
			$first_segment = sanitize_text_field( $segments[0] );

			if ( 0 === stripos( $first_segment, 'pl_' ) && '' === $details['payment_page_id'] ) {
				$details['payment_page_id'] = $first_segment;
			} elseif ( '' === $details['page_slug'] ) {
				$details['page_slug'] = $first_segment;
			}
		}

		if ( false !== strpos( $host, 'rzp.io' ) && ! empty( $segments ) && '' === $details['page_slug'] ) {
			$details['page_slug'] = sanitize_text_field( end( $segments ) );
		}
	}

	if ( '' === $details['page_slug'] && '' === $details['payment_link_id'] && '' === $details['payment_page_id'] && preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{2,}$/', $reference ) ) {
		$details['page_slug'] = $reference;
	}

	$tokens = array(
		$details['raw'],
		$details['url'],
		$details['payment_link_id'],
		$details['payment_page_id'],
		$details['page_slug'],
	);

	if ( '' !== $details['payment_page_id'] ) {
		$tokens[] = 'https://pages.razorpay.com/' . $details['payment_page_id'] . '/view';
		$tokens[] = $details['payment_page_id'] . '/view';
	}

	if ( '' !== $details['page_slug'] ) {
		$tokens[] = sanitize_title( $details['page_slug'] );
	}

	$details['tokens'] = array_values( array_unique( array_filter( array_map( 'strval', $tokens ) ) ) );

	return $details;
}

/**
 * Fetches public metadata for a Razorpay Payment Page reference.
 *
 * @param string $reference Raw page reference.
 * @return array
 */
function cmp_get_razorpay_public_page_metadata( $reference ) {
	$normalized = cmp_normalize_razorpay_page_reference( $reference );
	$cache_key  = 'cmp_rzp_public_page_' . md5( wp_json_encode( $normalized ) );
	$cached     = get_transient( $cache_key );
	$urls       = array();

	if ( is_array( $cached ) ) {
		return $cached;
	}

	if ( ! empty( $normalized['url'] ) ) {
		$urls[] = $normalized['url'];
	}

	if ( ! empty( $normalized['payment_page_id'] ) ) {
		$urls[] = 'https://pages.razorpay.com/' . rawurlencode( $normalized['payment_page_id'] ) . '/view';
	}

	if ( ! empty( $normalized['page_slug'] ) ) {
		$urls[] = 'https://pages.razorpay.com/' . rawurlencode( $normalized['page_slug'] );
	}

	foreach ( array_unique( array_filter( $urls ) ) as $url ) {
		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout'     => 20,
				'redirection' => 5,
				'headers'     => array(
					'Accept' => 'text/html,application/xhtml+xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			continue;
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( '' === trim( $body ) ) {
			continue;
		}

		$canonical = '';
		$title     = '';

		if ( preg_match( '/<link[^>]+rel=[\'"]canonical[\'"][^>]+href=[\'"]([^\'"]+)[\'"]/i', $body, $matches ) ) {
			$canonical = esc_url_raw( $matches[1] );
		} elseif ( preg_match( '/<meta[^>]+property=[\'"]og:url[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $body, $matches ) ) {
			$canonical = esc_url_raw( $matches[1] );
		}

		if ( preg_match( '/<title>(.*?)<\/title>/is', $body, $matches ) ) {
			$title = cmp_clean_title_text( wp_strip_all_tags( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) ) );
		}

		$resolved = cmp_normalize_razorpay_page_reference( $canonical ? $canonical : $url );

		$result = array(
			'url'             => esc_url_raw( $url ),
			'canonical_url'   => $canonical,
			'payment_page_id' => ! empty( $resolved['payment_page_id'] ) ? $resolved['payment_page_id'] : $normalized['payment_page_id'],
			'page_slug'       => ! empty( $resolved['page_slug'] ) ? $resolved['page_slug'] : $normalized['page_slug'],
			'title'           => $title,
		);

		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );

		return $result;
	}

	set_transient( $cache_key, array(), HOUR_IN_SECONDS );

	return array();
}

/**
 * Returns searchable tokens for a Razorpay Payment Link/Page reference.
 *
 * @param string $page_id Raw page reference.
 * @param string $page_name Optional page name.
 * @return array
 */
function cmp_get_razorpay_page_reference_tokens( $page_id, $page_name = '' ) {
	$reference = cmp_normalize_razorpay_page_reference( $page_id );
	$tokens    = isset( $reference['tokens'] ) && is_array( $reference['tokens'] ) ? $reference['tokens'] : array();
	$page_name = cmp_clean_title_text( $page_name );

	if ( '' !== $page_name ) {
		$tokens[] = $page_name;
		$tokens[] = sanitize_title( $page_name );
	}

	return array_values( array_unique( array_filter( array_map( 'strval', $tokens ) ) ) );
}

/**
 * Returns whether a searchable Razorpay value matches a reference token.
 *
 * @param string $value Searchable value.
 * @param string $token Reference token.
 * @return bool
 */
function cmp_razorpay_value_matches_token( $value, $token ) {
	$value = trim( strtolower( sanitize_text_field( (string) $value ) ) );
	$token = trim( strtolower( sanitize_text_field( (string) $token ) ) );

	if ( '' === $value || '' === $token ) {
		return false;
	}

	if ( $value === $token || false !== strpos( $value, $token ) ) {
		return true;
	}

	return sanitize_title( $value ) === sanitize_title( $token );
}

/**
 * Returns whether a Payment Link matches the supplied reference.
 *
 * @param array        $payment_link Payment Link entity.
 * @param array|string $reference Normalized or raw reference.
 * @param string       $page_name Optional page name.
 * @return bool
 */
function cmp_razorpay_payment_link_matches_reference( $payment_link, $reference, $page_name = '' ) {
	if ( ! is_array( $payment_link ) ) {
		return false;
	}

	$reference = is_array( $reference ) ? $reference : cmp_normalize_razorpay_page_reference( $reference );
	$tokens    = cmp_get_razorpay_page_reference_tokens( isset( $reference['raw'] ) ? $reference['raw'] : '', $page_name );
	$meta      = cmp_razorpay_entity_meta( $payment_link );
	$fields    = array(
		isset( $payment_link['id'] ) ? $payment_link['id'] : '',
		isset( $payment_link['short_url'] ) ? $payment_link['short_url'] : '',
		isset( $payment_link['reference_id'] ) ? $payment_link['reference_id'] : '',
		isset( $payment_link['description'] ) ? $payment_link['description'] : '',
		isset( $meta['form_name'] ) ? $meta['form_name'] : '',
		isset( $meta['page_slug'] ) ? $meta['page_slug'] : '',
		isset( $meta['payment_page_id'] ) ? $meta['payment_page_id'] : '',
		wp_json_encode( $meta ),
	);

	if ( ! empty( $reference['payment_link_id'] ) && isset( $payment_link['id'] ) && $reference['payment_link_id'] === sanitize_text_field( $payment_link['id'] ) ) {
		return true;
	}

	foreach ( $fields as $field ) {
		foreach ( $tokens as $token ) {
			if ( cmp_razorpay_value_matches_token( $field, $token ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Fetches captured payments directly attached to a Razorpay Payment Link.
 *
 * @param array $payment_link Payment Link entity.
 * @return array
 */
function cmp_get_captured_razorpay_payments_from_link_entity( $payment_link ) {
	$payments   = array();
	$seen       = array();
	$link_id    = isset( $payment_link['id'] ) ? sanitize_text_field( $payment_link['id'] ) : '';
	$link_notes = cmp_razorpay_entity_meta( $payment_link );
	$link_items = isset( $payment_link['payments'] ) && is_array( $payment_link['payments'] ) ? $payment_link['payments'] : array();

	foreach ( $link_items as $linked_payment ) {
		$payment_id = cmp_first_scalar_value(
			array(
				isset( $linked_payment['payment_id'] ) ? $linked_payment['payment_id'] : '',
				isset( $linked_payment['id'] ) ? $linked_payment['id'] : '',
			)
		);

		if ( '' === $payment_id || isset( $seen[ $payment_id ] ) ) {
			continue;
		}

		$payment = cmp_razorpay_api_get( 'payments/' . rawurlencode( $payment_id ) );

		if ( is_wp_error( $payment ) ) {
			$payment = array(
				'id'         => $payment_id,
				'status'     => isset( $linked_payment['status'] ) ? $linked_payment['status'] : '',
				'amount'     => isset( $linked_payment['amount'] ) ? $linked_payment['amount'] : 0,
				'created_at' => isset( $linked_payment['created_at'] ) ? $linked_payment['created_at'] : 0,
				'name'       => isset( $payment_link['customer']['name'] ) ? $payment_link['customer']['name'] : '',
				'email'      => isset( $payment_link['customer']['email'] ) ? $payment_link['customer']['email'] : '',
				'contact'    => isset( $payment_link['customer']['contact'] ) ? $payment_link['customer']['contact'] : '',
				'notes'      => $link_notes,
			);
		}

		if ( empty( $payment['payment_link_id'] ) && '' !== $link_id ) {
			$payment['payment_link_id'] = $link_id;
		}

		if ( empty( $payment['description'] ) && ! empty( $payment_link['description'] ) ) {
			$payment['description'] = sanitize_text_field( $payment_link['description'] );
		}

		if ( empty( $payment['notes'] ) || ! is_array( $payment['notes'] ) ) {
			$payment['notes'] = array();
		}

		foreach ( $link_notes as $note_key => $note_value ) {
			if ( ! isset( $payment['notes'][ $note_key ] ) || '' === trim( (string) $payment['notes'][ $note_key ] ) ) {
				$payment['notes'][ $note_key ] = $note_value;
			}
		}

		if ( ! cmp_is_successful_razorpay_payment( $payment ) ) {
			continue;
		}

		$seen[ $payment_id ] = true;
		$payments[]          = $payment;
	}

	return $payments;
}

/**
 * Finds Payment Link payments that match an entered ID, URL, or slug.
 *
 * @param string $page_id Raw page reference.
 * @param string $page_name Optional page name.
 * @return array
 */
function cmp_get_direct_razorpay_link_payments( $page_id, $page_name = '' ) {
	$reference = cmp_normalize_razorpay_page_reference( $page_id );
	$links     = array();
	$seen      = array();

	if ( ! empty( $reference['payment_link_id'] ) ) {
		$link = cmp_razorpay_api_get( 'payment_links/' . rawurlencode( $reference['payment_link_id'] ) );

		if ( ! is_wp_error( $link ) ) {
			$links[] = $link;
		}
	}

	if ( empty( $links ) ) {
		foreach ( cmp_razorpay_fetch_optional_collection( 'payment_links', 'items' ) as $payment_link ) {
			if ( ! cmp_razorpay_payment_link_matches_reference( $payment_link, $reference, $page_name ) ) {
				continue;
			}

			$link_id = isset( $payment_link['id'] ) ? sanitize_text_field( $payment_link['id'] ) : '';

			if ( '' !== $link_id ) {
				if ( isset( $seen[ $link_id ] ) ) {
					continue;
				}

				$seen[ $link_id ] = true;
				$full_link        = cmp_razorpay_api_get( 'payment_links/' . rawurlencode( $link_id ) );
				$links[]          = is_wp_error( $full_link ) ? $payment_link : $full_link;
				continue;
			}

			$links[] = $payment_link;
		}
	}

	$payments      = array();
	$seen_payments = array();

	foreach ( $links as $payment_link ) {
		foreach ( cmp_get_captured_razorpay_payments_from_link_entity( $payment_link ) as $payment ) {
			$payment_id = isset( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

			if ( '' !== $payment_id && isset( $seen_payments[ $payment_id ] ) ) {
				continue;
			}

			if ( '' !== $payment_id ) {
				$seen_payments[ $payment_id ] = true;
			}

			$payments[] = $payment;
		}
	}

	return $payments;
}

/**
 * Returns successful captured payments for a selected Payment Page.
 *
 * @param string $page_id Payment Page ID.
 * @param string $page_name Optional Payment Page name.
 * @return array|WP_Error
 */
function cmp_get_successful_razorpay_payments_for_link( $page_id, $page_name = '' ) {
	$page_id   = sanitize_text_field( $page_id );
	$page_name = cmp_clean_title_text( $page_name );
	$page_link = cmp_get_razorpay_payment_link( $page_id );

	if ( ! is_wp_error( $page_link ) ) {
		$page_meta = cmp_razorpay_entity_meta( $page_link );

		if ( '' === $page_name ) {
			$page_name = cmp_clean_title_text(
				cmp_first_scalar_value(
					array(
						$page_meta['form_name'] ?? '',
						$page_meta['payment_page_title'] ?? '',
						$page_meta['page_title'] ?? '',
						isset( $page_link['description'] ) ? $page_link['description'] : '',
					)
				)
			);
		}
	}

	$payments  = cmp_get_direct_razorpay_link_payments( $page_id, $page_name );

	if ( ! empty( $payments ) ) {
		cmp_log_event(
			'info',
			'Razorpay payments fetched directly from Payment Link.',
			array(
				'page_id'   => $page_id,
				'page_name' => $page_name,
				'count'     => count( $payments ),
			)
		);

		return $payments;
	}

	$payments = array();
	$seen     = array();
	$skip     = 0;
	$count    = 100;

	do {
		$response = cmp_razorpay_api_get(
			'payments',
			array(
				'count' => $count,
				'skip'  => $skip,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$page_items = isset( $response['items'] ) && is_array( $response['items'] ) ? $response['items'] : array();

		cmp_log_event(
			'info',
			'Razorpay payments API response.',
			array(
				'page_id'   => $page_id,
				'page_name' => $page_name,
				'skip'      => $skip,
				'response'  => $response,
			)
		);

		foreach ( $page_items as $payment ) {
			if ( ! cmp_is_successful_razorpay_payment( $payment ) ) {
				continue;
			}

			if ( ! cmp_razorpay_payment_matches_page( $payment, $page_id, $page_name ) ) {
				continue;
			}

			$payment_id = isset( $payment['id'] ) && is_scalar( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

			if ( '' !== $payment_id ) {
				if ( isset( $seen[ $payment_id ] ) ) {
					continue;
				}

				$seen[ $payment_id ] = true;
			}

			$payments[] = $payment;
		}

		$skip += count( $page_items );
	} while ( count( $page_items ) === $count );

	cmp_log_event(
		'info',
		'Razorpay payments filtered for Payment Page.',
		array(
			'page_id'        => $page_id,
			'page_name'      => $page_name,
			'filtered_count' => count( $payments ),
		)
	);

	return $payments;
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
	$amount = cmp_razorpay_minor_to_major( isset( $payment['amount'] ) ? $payment['amount'] : 0 );
	$financials = cmp_get_payment_financial_breakdown( $amount, true );

	if ( '' === trim( (string) $name ) ) {
		$name = '' !== trim( (string) $email ) ? $email : $phone;
	}

	return array(
		'name'   => sanitize_text_field( $name ),
		'email'  => sanitize_email( $email ),
		'phone'  => sanitize_text_field( $phone ),
		'amount' => $amount,
		'original_amount' => (float) $financials['original_amount'],
		'charge_amount' => (float) $financials['charge_amount'],
		'final_amount' => (float) $financials['final_amount'],
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

	$page_name = cmp_clean_title_text( $batch->batch_name );

	$batch_update = cmp_update_batch(
		(int) $batch->id,
		array(
			'class_id'         => (int) $batch->class_id,
			'batch_name'       => $batch->batch_name,
			'course_id'        => (int) $batch->course_id,
			'start_date'       => $batch->start_date,
			'fee_due_date'     => $batch->fee_due_date,
			'status'           => $batch->status,
			'razorpay_link'    => $batch->razorpay_link,
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
	$payments = cmp_get_successful_razorpay_payments_for_link( $page_id, $page_name );

	if ( is_wp_error( $payments ) ) {
		return $payments;
	}

	if ( empty( $payments ) ) {
		return new WP_Error( 'cmp_import_page_empty', __( 'No captured payments were found for this Razorpay Payment Link/Page.', 'class-manager-pro' ) );
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
 * Builds the success message for a Razorpay page import.
 *
 * @param array $summary Import summary.
 * @return string
 */
function cmp_get_razorpay_page_import_message( $summary ) {
	return sprintf(
		/* translators: 1: imported 2: duplicate 3: skipped 4: failed */
		__( 'Razorpay page import completed. %1$d payments imported, %2$d duplicates skipped, %3$d non-success payments skipped, %4$d failed.', 'class-manager-pro' ),
		isset( $summary['imported'] ) ? (int) $summary['imported'] : 0,
		isset( $summary['duplicate'] ) ? (int) $summary['duplicate'] : 0,
		isset( $summary['skipped'] ) ? (int) $summary['skipped'] : 0,
		isset( $summary['failed'] ) ? (int) $summary['failed'] : 0
	);
}

/**
 * Processes a Razorpay page import request and returns summary data.
 *
 * @param string $page_id Razorpay page ID.
 * @param int    $batch_id Target batch ID.
 * @return array|WP_Error
 */
function cmp_process_razorpay_page_import_request( $page_id, $batch_id ) {
	$result = cmp_import_razorpay_page_to_batch( $page_id, $batch_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'summary' => $result,
		'message' => cmp_get_razorpay_page_import_message( $result ),
	);
}

/**
 * Normalizes a student import header to an internal key.
 *
 * @param string $header Header text.
 * @return string
 */
function cmp_student_import_header_key( $header ) {
	$key = strtolower( sanitize_text_field( ltrim( (string) $header, "\xEF\xBB\xBF" ) ) );
	$key = preg_replace( '/[^a-z0-9]+/', '_', $key );
	$key = trim( preg_replace( '/_+/', '_', $key ), '_' );

	$map = array(
		'name'                 => 'name',
		'full_name'            => 'name',
		'student_name'         => 'name',
		'customer_name'        => 'name',
		'email'                => 'email',
		'student_email'        => 'email',
		'customer_email'       => 'email',
		'contact'              => 'phone',
		'phone'                => 'phone',
		'phone_no'             => 'phone',
		'phone_number'         => 'phone',
		'mobile'               => 'phone',
		'mobile_no'            => 'phone',
		'mobile_number'        => 'phone',
		'student_phone'        => 'phone',
		'student_mobile'       => 'phone',
		'student_contact'      => 'phone',
		'customer_contact'     => 'phone',
		'amount'               => 'amount',
		'amount_paid'          => 'amount',
		'paid_amount'          => 'amount',
		'total_payment_amount' => 'amount',
		'item_payment_amount'  => 'amount',
		'item_amount'          => 'amount',
		'payment_amount'       => 'amount',
		'total_amount'         => 'amount',
		'payment_id'           => 'payment_id',
		'paymentid'            => 'payment_id',
		'transaction_id'       => 'payment_id',
		'id'                   => 'payment_id',
		'payment_status'       => 'status',
		'status'               => 'status',
		'payment_date'         => 'payment_date',
		'date'                 => 'payment_date',
		'created_at'           => 'payment_date',
		'payment_page_id'      => 'payment_page_id',
		'page_id'              => 'payment_page_id',
		'payment_page_title'   => 'payment_page_title',
		'page_title'           => 'payment_page_title',
		'course'               => 'course',
		'batch'                => 'batch',
		'class'                => 'class',
	);

	return isset( $map[ $key ] ) ? $map[ $key ] : $key;
}

/**
 * Returns the known column keys for student import files.
 *
 * @return array
 */
function cmp_student_import_known_headers() {
	return array(
		'name',
		'email',
		'phone',
		'amount',
		'total_amount',
		'payment_id',
		'status',
		'payment_date',
		'payment_page_id',
		'payment_page_title',
		'course',
		'batch',
		'class',
	);
}

/**
 * Returns the default positional column order for student import files.
 *
 * @return array
 */
function cmp_student_import_default_headers() {
	return array( 'name', 'email', 'phone', 'amount', 'payment_id', 'status', 'payment_date', 'payment_page_id', 'payment_page_title' );
}

/**
 * Normalizes raw matrix row values from CSV or spreadsheet readers.
 *
 * @param array $values Raw values.
 * @return array
 */
function cmp_normalize_student_import_matrix_row( $values ) {
	$normalized = array();

	foreach ( (array) $values as $index => $value ) {
		$normalized[ (int) $index ] = is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	ksort( $normalized );

	return array_values( $normalized );
}

/**
 * Determines whether the first row of an import looks like a header row.
 *
 * @param array $values Raw row values.
 * @return bool
 */
function cmp_student_import_looks_like_header_row( $values ) {
	$recognized = 0;
	$known      = cmp_student_import_known_headers();

	foreach ( (array) $values as $value ) {
		$key = cmp_student_import_header_key( $value );

		if ( in_array( $key, $known, true ) ) {
			++$recognized;
		}
	}

	return $recognized >= 2;
}

/**
 * Maps one matrix row into a row structure with numeric and named keys.
 *
 * @param array $values Row values.
 * @param array $headers Active headers.
 * @return array
 */
function cmp_map_student_import_matrix_row( $values, $headers ) {
	$row = array();

	foreach ( (array) $values as $index => $value ) {
		$row[ (int) $index ] = $value;
	}

	foreach ( (array) $headers as $index => $header_key ) {
		if ( '' === $header_key ) {
			continue;
		}

		$row[ $header_key ] = isset( $values[ $index ] ) ? $values[ $index ] : '';
	}

	return $row;
}

/**
 * Builds import rows from CSV/spreadsheet matrix data.
 *
 * @param array  $matrix Matrix data.
 * @param string $source Source type.
 * @return array
 */
function cmp_build_student_import_rows_from_matrix( $matrix, $source = 'csv' ) {
	$headers = array();
	$rows    = array();

	foreach ( (array) $matrix as $matrix_row ) {
		$values = cmp_normalize_student_import_matrix_row( $matrix_row );

		if ( ! isset( $values[0] ) ) {
			continue;
		}

		if ( empty( array_filter( $values, 'strlen' ) ) ) {
			continue;
		}

		if ( empty( $headers ) ) {
			if ( cmp_student_import_looks_like_header_row( $values ) ) {
				$headers = array_map( 'cmp_student_import_header_key', $values );
				continue;
			}

			$headers = cmp_student_import_default_headers();
		}

		$row = cmp_map_student_import_matrix_row( $values, $headers );

		if ( ! empty( $row ) ) {
			$rows[] = $row;
		}
	}

	return $rows;
}

/**
 * Loads PhpSpreadsheet autoloading when available.
 *
 * @return bool
 */
function cmp_maybe_load_phpspreadsheet_autoload() {
	if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
		return true;
	}

	$candidates = array(
		CMP_PLUGIN_DIR . 'vendor/autoload.php',
		dirname( rtrim( CMP_PLUGIN_DIR, '/\\' ) ) . '/vendor/autoload.php',
		trailingslashit( WP_CONTENT_DIR ) . 'vendor/autoload.php',
		trailingslashit( ABSPATH ) . 'vendor/autoload.php',
	);

	foreach ( array_unique( $candidates ) as $candidate ) {
		if ( ! is_string( $candidate ) || '' === $candidate || ! file_exists( $candidate ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_exists
			continue;
		}

		require_once $candidate;

		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return true;
		}
	}

	return class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' );
}

/**
 * Parses spreadsheet rows with PhpSpreadsheet when available.
 *
 * @param string $file_path Temporary file path.
 * @param string $file_type Spreadsheet type.
 * @return array|WP_Error
 */
function cmp_parse_student_import_spreadsheet( $file_path, $file_type ) {
	$file_type = strtolower( sanitize_key( $file_type ) );

	if ( ! cmp_maybe_load_phpspreadsheet_autoload() ) {
		return new WP_Error(
			'cmp_import_' . $file_type . '_unsupported',
			__( 'Spreadsheet import is not supported on this server right now. Please upload CSV instead.', 'class-manager-pro' )
		);
	}

	try {
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file_path );
		$sheet_rows  = $spreadsheet->getSheet( 0 )->toArray( '', false, false, false );
	} catch ( Exception $exception ) {
		error_log( 'Student spreadsheet import failed: ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return new WP_Error(
			'cmp_import_' . $file_type . '_failed',
			sprintf(
				/* translators: %s: file type */
				__( 'The uploaded %s file could not be read.', 'class-manager-pro' ),
				strtoupper( $file_type )
			)
		);
	}

	return cmp_build_student_import_rows_from_matrix( $sheet_rows, $file_type );
}

/**
 * Converts an Excel column reference to a zero-based index.
 *
 * @param string $column_ref Excel column reference.
 * @return int
 */
function cmp_excel_column_reference_to_index( $column_ref ) {
	$column_ref = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $column_ref ) );
	$length     = strlen( $column_ref );
	$index      = 0;

	for ( $i = 0; $i < $length; ++$i ) {
		$index = ( $index * 26 ) + ( ord( $column_ref[ $i ] ) - 64 );
	}

	return max( 0, $index - 1 );
}

/**
 * Parses CSV rows from an uploaded student import file.
 *
 * @param string $file_path Temporary file path.
 * @return array|WP_Error
 */
function cmp_parse_student_import_csv( $file_path ) {
	$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

	if ( ! $handle ) {
		return new WP_Error( 'cmp_import_file_open_failed', __( 'The uploaded file could not be opened.', 'class-manager-pro' ) );
	}

	$matrix = array();

	while ( false !== ( $values = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
		if ( false !== $values ) {
			$matrix[] = $values;
		}
	}

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return cmp_build_student_import_rows_from_matrix( $matrix, 'csv' );
}

/**
 * Parses XLSX rows from an uploaded student import file.
 *
 * @param string $file_path Temporary file path.
 * @return array|WP_Error
 */
function cmp_parse_student_import_xlsx( $file_path ) {
	return cmp_parse_student_import_spreadsheet( $file_path, 'xlsx' );
}

/**
 * Parses XLS rows from an uploaded student import file when a spreadsheet library is available.
 *
 * @param string $file_path Temporary file path.
 * @return array|WP_Error
 */
function cmp_parse_student_import_xls( $file_path ) {
	return cmp_parse_student_import_spreadsheet( $file_path, 'xls' );
}

/**
 * Reads rows from an uploaded student import file.
 *
 * @param array $file Uploaded file array.
 * @return array|WP_Error
 */
function cmp_read_student_import_rows( $file ) {
	$file_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
	$tmp_name  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
	$error     = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

	if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
		return new WP_Error( 'cmp_import_file_missing', __( 'Please upload a valid CSV, XLS, or XLSX file.', 'class-manager-pro' ) );
	}

	if ( ! in_array( $extension, array( 'csv', 'xls', 'xlsx' ), true ) ) {
		return new WP_Error( 'cmp_import_file_type', __( 'Only CSV, XLS, and XLSX files are allowed.', 'class-manager-pro' ) );
	}

	if ( 'csv' === $extension ) {
		return cmp_parse_student_import_csv( $tmp_name );
	}

	if ( 'xlsx' === $extension ) {
		return cmp_parse_student_import_xlsx( $tmp_name );
	}

	return cmp_parse_student_import_xls( $tmp_name );
}

/**
 * Cleans imported phone numbers into a predictable 10-digit format when possible.
 *
 * @param string $phone Raw phone number.
 * @return string
 */
function cmp_clean_import_phone_number( $phone ) {
	$digits = preg_replace( '/\D+/', '', sanitize_text_field( (string) $phone ) );

	if ( '' === $digits ) {
		return '';
	}

	if ( strlen( $digits ) > 10 ) {
		$digits = substr( $digits, -10 );
	}

	return $digits;
}

/**
 * Performs a simple Devanagari-to-Latin transliteration for imported names.
 *
 * @param string $text Raw text.
 * @return string
 */
function cmp_transliterate_import_name( $text ) {
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return '';
	}

	if ( ! preg_match( '/[\x{0900}-\x{097F}]/u', $text ) ) {
		$ascii = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text ) : ''; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return '' !== trim( (string) $ascii ) ? trim( (string) $ascii ) : $text;
	}

	$independent_vowels = array(
		'अ' => 'a',
		'आ' => 'aa',
		'इ' => 'i',
		'ई' => 'ee',
		'उ' => 'u',
		'ऊ' => 'oo',
		'ए' => 'e',
		'ऐ' => 'ai',
		'ओ' => 'o',
		'औ' => 'au',
		'ऑ' => 'o',
		'ऍ' => 'e',
		'ऋ' => 'ri',
	);
	$consonants         = array(
		'क' => 'k',
		'ख' => 'kh',
		'ग' => 'g',
		'घ' => 'gh',
		'ङ' => 'ng',
		'च' => 'ch',
		'छ' => 'chh',
		'ज' => 'j',
		'झ' => 'jh',
		'ञ' => 'ny',
		'ट' => 't',
		'ठ' => 'th',
		'ड' => 'd',
		'ढ' => 'dh',
		'ण' => 'n',
		'त' => 't',
		'थ' => 'th',
		'द' => 'd',
		'ध' => 'dh',
		'न' => 'n',
		'प' => 'p',
		'फ' => 'ph',
		'ब' => 'b',
		'भ' => 'bh',
		'म' => 'm',
		'य' => 'y',
		'र' => 'r',
		'ल' => 'l',
		'व' => 'v',
		'श' => 'sh',
		'ष' => 'sh',
		'स' => 's',
		'ह' => 'h',
		'ळ' => 'l',
		'क्ष' => 'ksh',
		'ज्ञ' => 'gya',
	);
	$matras             = array(
		'ा' => 'a',
		'ि' => 'i',
		'ी' => 'i',
		'ु' => 'u',
		'ू' => 'u',
		'े' => 'e',
		'ै' => 'ai',
		'ो' => 'o',
		'ौ' => 'au',
		'ृ' => 'ri',
		'ॅ' => 'e',
		'ॉ' => 'o',
	);
	$marks              = array(
		'ं' => 'm',
		'ँ' => 'n',
		'ः' => 'h',
	);
	$chars              = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$output             = '';

	foreach ( $chars as $char ) {
		if ( isset( $independent_vowels[ $char ] ) ) {
			$output .= $independent_vowels[ $char ];
			continue;
		}

		if ( isset( $consonants[ $char ] ) ) {
			$output .= $consonants[ $char ] . 'a';
			continue;
		}

		if ( isset( $matras[ $char ] ) ) {
			if ( 'a' === substr( $output, -1 ) ) {
				$output = substr( $output, 0, -1 );
			}

			$output .= $matras[ $char ];
			continue;
		}

		if ( '्' === $char ) {
			if ( 'a' === substr( $output, -1 ) ) {
				$output = substr( $output, 0, -1 );
			}
			continue;
		}

		if ( isset( $marks[ $char ] ) ) {
			$output .= $marks[ $char ];
			continue;
		}

		if ( preg_match( '/\s/u', $char ) ) {
			$output = rtrim( $output, 'a' ) . ' ';
			continue;
		}

		$output .= $char;
	}

	$output = preg_replace( '/\ba\b/i', '', $output );
	$output = preg_replace( '/\s+/', ' ', trim( (string) $output ) );
	$output = preg_replace( '/([aeiou])\1+/', '$1', $output );

	return '' !== $output ? ucwords( strtolower( $output ) ) : sanitize_text_field( $text );
}

/**
 * Normalizes an imported student name.
 *
 * @param string $name Raw name.
 * @return string
 */
function cmp_prepare_import_student_name( $name ) {
	$name = trim( sanitize_text_field( (string) $name ) );

	if ( '' === $name ) {
		return '';
	}

	$transliterated = cmp_transliterate_import_name( $name );

	return '' !== $transliterated ? $transliterated : $name;
}

/**
 * Parses an imported payment date into a Unix timestamp.
 *
 * @param string $value Raw date value.
 * @return int
 */
function cmp_parse_import_payment_timestamp( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );

	if ( '' === $value ) {
		return 0;
	}

	if ( is_numeric( $value ) ) {
		$timestamp = (int) $value;

		return $timestamp > 1000000000 ? $timestamp : 0;
	}

	$date = DateTime::createFromFormat( 'd/m/Y H:i:s', $value, wp_timezone() );

	if ( $date instanceof DateTime ) {
		return (int) $date->getTimestamp();
	}

	$timestamp = strtotime( $value );

	return $timestamp ? (int) $timestamp : 0;
}

/**
 * Returns the first populated import row value from named keys or fallback indexes.
 *
 * @param array $row Row data.
 * @param array $keys Named keys.
 * @param array $indexes Numeric fallback indexes.
 * @return string
 */
function cmp_get_student_import_row_value( $row, $keys, $indexes = array() ) {
	foreach ( (array) $keys as $key ) {
		if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
			return trim( (string) $row[ $key ] );
		}
	}

	foreach ( (array) $indexes as $index ) {
		if ( isset( $row[ $index ] ) && is_scalar( $row[ $index ] ) && '' !== trim( (string) $row[ $index ] ) ) {
			return trim( (string) $row[ $index ] );
		}
	}

	return '';
}

/**
 * Normalizes an imported row status for student and payment handling.
 *
 * @param string $status Raw status.
 * @param string $payment_id Payment ID.
 * @param float  $amount Amount.
 * @return string
 */
function cmp_normalize_student_import_status( $status, $payment_id = '', $amount = 0 ) {
	$status             = strtolower( trim( sanitize_text_field( (string) $status ) ) );
	$captured_statuses  = array( 'captured', 'paid', 'success', 'successful', 'completed', 'complete' );
	$failed_statuses    = array( 'failed', 'fail', 'error', 'cancelled', 'canceled', 'declined', 'refunded', 'refund' );
	$student_statuses   = array( 'student', 'manual', 'batch', 'lead', 'pending', 'active', 'enrolled', 'unpaid' );
	$has_payment_record = '' !== trim( (string) $payment_id ) || (float) $amount > 0;

	if ( in_array( $status, $captured_statuses, true ) ) {
		return 'captured';
	}

	if ( in_array( $status, $failed_statuses, true ) ) {
		return 'failed';
	}

	if ( $has_payment_record ) {
		return 'captured';
	}

	if ( in_array( $status, $student_statuses, true ) ) {
		return 'student';
	}

	if ( '' === $status ) {
		return $has_payment_record ? 'captured' : 'student';
	}

	return $has_payment_record ? 'captured' : 'student';
}

/**
 * Maps one imported row into the fields needed by student/payment import.
 *
 * @param array $row Raw row values.
 * @return array
 */
function cmp_map_student_import_row( $row ) {
	$name       = cmp_prepare_import_student_name( cmp_get_student_import_row_value( $row, array( 'name', 'student_name', 'customer_name' ), array( 0 ) ) );
	$email      = sanitize_email( cmp_get_student_import_row_value( $row, array( 'email', 'student_email', 'customer_email' ), array( 1 ) ) );
	$phone      = cmp_clean_import_phone_number( cmp_get_student_import_row_value( $row, array( 'phone', 'contact', 'mobile', 'student_phone', 'student_mobile' ), array( 2 ) ) );
	$amount     = cmp_money_value( cmp_get_student_import_row_value( $row, array( 'amount', 'total_payment_amount', 'item_payment_amount', 'item_amount', 'amount_paid' ), array( 3 ) ) );
	$payment_id = sanitize_text_field( cmp_get_student_import_row_value( $row, array( 'payment_id', 'transaction_id', 'id' ), array( 4 ) ) );
	$status     = cmp_normalize_student_import_status(
		cmp_get_student_import_row_value( $row, array( 'status', 'payment_status' ), array( 5 ) ),
		$payment_id,
		$amount
	);

	if ( '' === $name ) {
		if ( '' !== $email ) {
			$email_parts = explode( '@', $email );
			$name        = sanitize_text_field( isset( $email_parts[0] ) ? $email_parts[0] : $email );
		} else {
			$name = $phone;
		}
	}

	return array(
		'name'               => $name,
		'email'              => $email,
		'phone'              => $phone,
		'amount'             => $amount,
		'payment_id'         => $payment_id,
		'status'             => $status,
		'payment_timestamp'  => cmp_parse_import_payment_timestamp( cmp_get_student_import_row_value( $row, array( 'payment_date', 'date', 'created_at' ), array( 6 ) ) ),
		'payment_page_id'    => sanitize_text_field( cmp_get_student_import_row_value( $row, array( 'payment_page_id', 'page_id' ), array( 7 ) ) ),
		'payment_page_title' => sanitize_text_field( cmp_get_student_import_row_value( $row, array( 'payment_page_title', 'page_title' ), array( 8 ) ) ),
	);
}

/**
 * Builds a payment-like entity array from an imported row.
 *
 * @param array  $mapped_row Clean mapped row.
 * @param object $batch Batch row.
 * @return array
 */
function cmp_build_student_import_payment_entity( $mapped_row, $batch ) {
	$amount     = (float) $mapped_row['amount'];
	$payment_id = '' !== trim( (string) $mapped_row['payment_id'] )
		? sanitize_text_field( $mapped_row['payment_id'] )
		: 'cmpimp_' . substr(
			md5(
				wp_json_encode(
					array(
						'batch_id'    => (int) $batch->id,
						'name'        => $mapped_row['name'],
						'email'       => $mapped_row['email'],
						'phone'       => $mapped_row['phone'],
						'amount'      => $mapped_row['amount'],
						'date'        => $mapped_row['payment_timestamp'],
						'payment_ref' => $mapped_row['payment_page_id'],
						'page_title'  => $mapped_row['payment_page_title'],
					)
				)
			),
			0,
			24
		);

	if ( $amount <= 0 ) {
		$amount = cmp_get_batch_effective_fee( $batch );
	}

	return array(
		'id'         => $payment_id,
		'status'     => 'captured',
		'amount'     => (int) round( $amount * 100 ),
		'created_at' => ! empty( $mapped_row['payment_timestamp'] ) ? (int) $mapped_row['payment_timestamp'] : time(),
		'name'       => $mapped_row['name'],
		'email'      => $mapped_row['email'],
		'contact'    => $mapped_row['phone'],
		'description'=> ! empty( $mapped_row['payment_page_title'] ) ? $mapped_row['payment_page_title'] : ( ! empty( $mapped_row['payment_page_id'] ) ? $mapped_row['payment_page_id'] : $batch->batch_name ),
		'notes'      => array(
			'name'               => $mapped_row['name'],
			'email'              => $mapped_row['email'],
			'phone'              => $mapped_row['phone'],
			'total_fee'          => $amount,
			'payment_page_id'    => $mapped_row['payment_page_id'],
			'payment_page_title' => $mapped_row['payment_page_title'],
			'batch_name'         => $batch->batch_name,
			'class_id'           => (int) $batch->class_id,
		),
	);
}

/**
 * Creates or updates a student record inside the selected batch.
 *
 * @param array  $student_data Student data.
 * @param object $batch Batch context.
 * @return int|WP_Error
 */
function cmp_upsert_import_student_to_batch( $student_data, $batch ) {
	$batch = $batch ? cmp_get_batch( (int) $batch->id ) : null;
	$class = $batch ? cmp_get_class( (int) $batch->class_id ) : null;
	$name  = cmp_prepare_import_student_name( cmp_field( $student_data, 'name' ) );
	$email = sanitize_email( cmp_field( $student_data, 'email' ) );
	$phone = cmp_clean_import_phone_number( cmp_field( $student_data, 'phone' ) );
	$notes = sanitize_textarea_field( cmp_field( $student_data, 'notes' ) );

	if ( ! $batch || ! $class ) {
		return new WP_Error( 'cmp_import_batch_invalid', __( 'A valid batch is required for student import.', 'class-manager-pro' ) );
	}

	if ( '' === $email && '' === $phone ) {
		return new WP_Error( 'cmp_import_student_contact_missing', __( 'Each imported student needs at least an email or phone number.', 'class-manager-pro' ) );
	}

	if ( '' === $name ) {
		$email_parts = explode( '@', $email );
		$name        = sanitize_text_field( isset( $email_parts[0] ) && '' !== $email_parts[0] ? $email_parts[0] : ( $phone ? $phone : $email ) );
	}

	$default_fee = cmp_money_value( cmp_field( $student_data, 'total_fee', 0 ) );

	if ( $default_fee <= 0 ) {
		$default_fee = cmp_get_batch_effective_fee( $batch );
	}

	if ( $default_fee <= 0 ) {
		$default_fee = $class ? (float) $class->total_fee : 0;
	}

	$notes    = cmp_append_note( $notes, __( 'Imported from student file.', 'class-manager-pro' ) );
	$existing = cmp_find_reusable_import_student( $batch, $name, $email, $phone, (int) $class->id );

	if ( $existing ) {
		$update_result = cmp_update_student(
			(int) $existing->id,
			array(
				'name'      => $name,
				'phone'     => $phone,
				'email'     => $email,
				'class_id'  => (int) $batch->class_id,
				'batch_id'  => (int) $batch->id,
				'total_fee' => (float) $existing->paid_fee > 0 && (float) $existing->total_fee > 0 ? (float) $existing->total_fee : $default_fee,
				'paid_fee'  => (float) $existing->paid_fee,
				'status'    => $existing->status ? $existing->status : 'active',
				'notes'     => cmp_append_note( $existing->notes, $notes ),
			)
		);

		if ( is_wp_error( $update_result ) ) {
			return $update_result;
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
			'total_fee' => $default_fee,
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
 * Creates or updates a student record without forcing batch assignment.
 *
 * @param array       $student_data Student data.
 * @param object|null $batch Optional batch context.
 * @param bool        $sync_wordpress_user Whether to sync the linked WordPress user.
 * @return int|WP_Error
 */
function cmp_upsert_unassigned_import_student( $student_data, $batch = null, $sync_wordpress_user = true ) {
	global $wpdb;

	$name     = cmp_prepare_import_student_name( cmp_field( $student_data, 'name' ) );
	$email    = sanitize_email( cmp_field( $student_data, 'email' ) );
	$phone    = cmp_clean_import_phone_number( cmp_field( $student_data, 'phone' ) );
	$class_id = absint( cmp_field( $student_data, 'class_id', 0 ) );
	$status   = cmp_clean_enum( cmp_field( $student_data, 'status', 'active' ), cmp_student_statuses(), 'active' );
	$notes    = sanitize_textarea_field( cmp_field( $student_data, 'notes' ) );

	if ( '' === $name ) {
		$email_parts = explode( '@', $email );
		$name        = sanitize_text_field( isset( $email_parts[0] ) && '' !== $email_parts[0] ? $email_parts[0] : ( $phone ? $phone : $email ) );
	}

	if ( '' === $name || ( '' === $phone && '' === $email ) || ! cmp_get_class( $class_id ) ) {
		return new WP_Error( 'cmp_import_student_invalid', __( 'Each imported student needs a valid name, class, and at least one contact detail.', 'class-manager-pro' ) );
	}

	$default_fee = cmp_money_value( cmp_field( $student_data, 'total_fee', 0 ) );

	if ( $default_fee <= 0 ) {
		$default_fee = $batch ? cmp_get_batch_effective_fee( $batch ) : 0;
	}

	if ( $default_fee <= 0 ) {
		$class = cmp_get_class( $class_id );
		$default_fee = $class ? (float) $class->total_fee : 0;
	}

	$existing = cmp_find_unassigned_student_by_contact( $class_id, $phone, $email );

	if ( $existing ) {
		$result = $wpdb->update(
			cmp_table( 'students' ),
			array(
				'name'      => $name,
				'phone'     => $phone,
				'email'     => $email,
				'class_id'  => (int) $existing->class_id ? (int) $existing->class_id : $class_id,
				'batch_id'  => (int) $existing->batch_id,
				'total_fee' => (float) $existing->total_fee > 0 ? (float) $existing->total_fee : $default_fee,
				'status'    => $existing->status ? $existing->status : $status,
				'notes'     => cmp_append_note( $existing->notes, $notes ),
			),
			array( 'id' => (int) $existing->id ),
			array( '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'cmp_import_student_update_failed', __( 'An imported student could not be updated.', 'class-manager-pro' ) );
		}

		if ( $sync_wordpress_user ) {
			cmp_sync_student_wordpress_user( (int) $existing->id );
		}

		return (int) $existing->id;
	}

	$result = $wpdb->insert(
		cmp_table( 'students' ),
		array(
			'unique_id'  => cmp_generate_student_unique_id(),
			'name'       => $name,
			'phone'      => $phone,
			'email'      => $email,
			'class_id'   => $class_id,
			'batch_id'   => 0,
			'total_fee'  => $default_fee,
			'paid_fee'   => 0,
			'status'     => $status,
			'notes'      => $notes,
			'created_at' => cmp_current_datetime(),
		),
		array( '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_import_student_insert_failed', __( 'An imported student could not be saved.', 'class-manager-pro' ) );
	}

	$student_id = (int) $wpdb->insert_id;

	cmp_log_activity(
		array(
			'student_id' => $student_id,
			'class_id'   => $class_id,
			'action'     => 'student_created',
			'message'    => sprintf(
				/* translators: %s: student name */
				__( 'Student "%s" imported without batch assignment.', 'class-manager-pro' ),
				$name
			),
		)
	);
	cmp_log_admin_action(
		'add_student',
		'student',
		$student_id,
		sprintf(
			/* translators: %s: student name */
			__( 'Student "%s" imported without batch assignment.', 'class-manager-pro' ),
			$name
		)
	);
	if ( $sync_wordpress_user ) {
		cmp_sync_student_wordpress_user( $student_id );
	}

	return $student_id;
}

/**
 * Imports one student/payment row from an uploaded file.
 *
 * @param array  $row Raw row.
 * @param int    $class_id Selected class ID.
 * @param int    $batch_id Selected batch ID.
 * @param object $batch Selected batch row.
 * @return array
 */
function cmp_import_student_file_row( $row, $class_id, $batch_id, $batch ) {
	$class_id   = absint( $class_id );
	$batch_id   = absint( $batch_id );
	$mapped_row = cmp_map_student_import_row( $row );

	if ( '' === $mapped_row['name'] && '' === $mapped_row['email'] && '' === $mapped_row['phone'] && '' === $mapped_row['payment_id'] ) {
		return array( 'status' => 'skipped' );
	}

	if ( 'captured' !== $mapped_row['status'] && '' === $mapped_row['email'] && '' === $mapped_row['phone'] ) {
		return array(
			'status'  => 'skipped',
			'message' => __( 'Row skipped because both email and phone are missing.', 'class-manager-pro' ),
		);
	}

	if ( 'captured' === $mapped_row['status'] ) {
		$result = cmp_import_razorpay_payment_into_batch( cmp_build_student_import_payment_entity( $mapped_row, $batch ), $batch );

		if ( isset( $result['status'] ) && 'error' === $result['status'] && ! empty( $result['message'] ) && false !== stripos( $result['message'], 'intake limit' ) ) {
			return array(
				'status'  => 'skipped',
				'message' => $result['message'],
			);
		}

		if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
			return array(
				'status'  => 'failed',
				'message' => isset( $result['message'] ) ? $result['message'] : __( 'Import failed for one row.', 'class-manager-pro' ),
			);
		}

		return $result;
	}

	if ( 'failed' === $mapped_row['status'] ) {
		$lead_id = cmp_record_failed_payment_interest(
			array(
				'name'           => $mapped_row['name'],
				'phone'          => $mapped_row['phone'],
				'email'          => $mapped_row['email'],
				'class_id'       => $class_id,
				'batch_id'       => $batch_id,
				'payment_status' => 'failed',
				'payment_source' => 'razorpay_csv',
				'attempt_amount' => $mapped_row['amount'],
				'transaction_id' => $mapped_row['payment_id'],
				'notes'          => __( 'Failed payment import captured as an interested student.', 'class-manager-pro' ),
				'payment_meta'   => $mapped_row,
			)
		);

		if ( is_wp_error( $lead_id ) ) {
			return array(
				'status'  => 'failed',
				'message' => $lead_id->get_error_message(),
			);
		}

		return array(
			'status'                => 'interested',
			'interested_student_id' => (int) $lead_id,
		);
	}

	$notes = __( 'Imported from student file and assigned to the selected batch.', 'class-manager-pro' );

	if ( 'captured' === $mapped_row['status'] ) {
		$notes = __( 'Imported from student file without payment entry because amount or payment ID was missing.', 'class-manager-pro' );
	}

	$student_id = cmp_upsert_import_student_to_batch(
		array(
			'name'      => $mapped_row['name'],
			'phone'     => $mapped_row['phone'],
			'email'     => $mapped_row['email'],
			'class_id'  => $class_id,
			'batch_id'  => $batch_id,
			'total_fee' => $mapped_row['amount'] > 0 ? $mapped_row['amount'] : cmp_get_batch_effective_fee( $batch ),
			'status'    => 'active',
			'notes'     => $notes,
		),
		$batch
	);

	if ( is_wp_error( $student_id ) ) {
		return array(
			'status'  => 'failed',
			'message' => $student_id->get_error_message(),
		);
	}

	return array(
		'status'     => 'imported',
		'student_id' => (int) $student_id,
	);
}

/**
 * Applies safer execution limits for student import processing.
 *
 * @return void
 */
function cmp_apply_student_import_execution_limits() {
	if ( function_exists( 'ignore_user_abort' ) ) {
		ignore_user_abort( true );
	}

	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	if ( function_exists( 'ini_set' ) ) {
		@ini_set( 'memory_limit', '256M' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

/**
 * Builds the final student import summary message.
 *
 * @param array  $summary Import summary.
 * @param string $first_error First error message.
 * @return string
 */
function cmp_get_student_import_summary_message( $summary, $first_error = '' ) {
	$message = sprintf(
		/* translators: 1: imported count 2: skipped count */
		__( 'Imported: %1$d students. Skipped: %2$d students.', 'class-manager-pro' ),
		isset( $summary['imported'] ) ? (int) $summary['imported'] : 0,
		isset( $summary['skipped'] ) ? (int) $summary['skipped'] : 0
	);

	if ( ! empty( $summary['duplicate'] ) ) {
		$message .= ' ' . sprintf(
			/* translators: %d: duplicate count */
			__( 'Duplicate payments skipped: %d.', 'class-manager-pro' ),
			(int) $summary['duplicate']
		);
	}

	if ( ! empty( $summary['interested'] ) ) {
		$message .= ' ' . sprintf(
			/* translators: %d: interested student count */
			__( 'Interested students captured from failed payments: %d.', 'class-manager-pro' ),
			(int) $summary['interested']
		);
	}

	if ( ! empty( $summary['failed'] ) ) {
		$message .= ' ' . sprintf(
			/* translators: %d: failed count */
			__( 'Failed: %d students.', 'class-manager-pro' ),
			(int) $summary['failed']
		);
	}

	if ( '' !== $first_error && ! empty( $summary['failed'] ) ) {
		$message .= ' ' . sanitize_text_field( $first_error );
	}

	return $message;
}

/**
 * Returns the student import chunk size.
 *
 * @return int
 */
function cmp_get_student_import_chunk_size() {
	return max( 1, absint( apply_filters( 'cmp_student_import_chunk_size', 100 ) ) );
}

/**
 * Builds the transient key for a student import session.
 *
 * @param string $session_id Session ID.
 * @return string
 */
function cmp_get_student_import_session_key( $session_id ) {
	return 'cmp_student_import_' . sanitize_key( $session_id );
}

/**
 * Returns the upload directory used for student import session files.
 *
 * @return string
 */
function cmp_get_student_import_session_dir() {
	$upload_dir = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) ) {
		return '';
	}

	$directory = trailingslashit( $upload_dir['basedir'] ) . 'cmp-import-sessions';

	if ( ! is_dir( $directory ) ) {
		wp_mkdir_p( $directory );
	}

	return is_dir( $directory ) ? $directory : '';
}

/**
 * Returns the JSON storage file for one student import session.
 *
 * @param string $session_id Session ID.
 * @return string
 */
function cmp_get_student_import_session_rows_file( $session_id ) {
	$session_id = sanitize_key( $session_id );
	$directory  = cmp_get_student_import_session_dir();

	if ( '' === $session_id || '' === $directory ) {
		return '';
	}

	return trailingslashit( $directory ) . $session_id . '.json';
}

/**
 * Stores parsed import rows for one session outside the transient payload.
 *
 * @param string $session_id Session ID.
 * @param array  $rows Parsed rows.
 * @return string|WP_Error
 */
function cmp_store_student_import_session_rows( $session_id, $rows ) {
	$file_path = cmp_get_student_import_session_rows_file( $session_id );
	$json      = wp_json_encode( array_values( is_array( $rows ) ? $rows : array() ) );

	if ( '' === $file_path || false === $json ) {
		return new WP_Error( 'cmp_import_session_storage_unavailable', __( 'The import session could not be prepared.', 'class-manager-pro' ) );
	}

	$result = file_put_contents( $file_path, $json, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	if ( false === $result ) {
		return new WP_Error( 'cmp_import_session_storage_unavailable', __( 'The import session could not be stored.', 'class-manager-pro' ) );
	}

	return $file_path;
}

/**
 * Loads parsed import rows for a session from file storage when available.
 *
 * @param array $session Session payload.
 * @return array
 */
function cmp_get_student_import_session_rows( $session ) {
	$file_path = isset( $session['rows_file'] ) ? (string) $session['rows_file'] : '';

	if ( '' !== $file_path && is_readable( $file_path ) ) {
		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$rows     = json_decode( (string) $contents, true );

		if ( is_array( $rows ) ) {
			return array_values( $rows );
		}
	}

	return isset( $session['rows'] ) && is_array( $session['rows'] ) ? array_values( $session['rows'] ) : array();
}

/**
 * Deletes stored session files for one import session.
 *
 * @param array $session Session payload.
 * @return void
 */
function cmp_delete_student_import_session_rows( $session ) {
	$file_path = isset( $session['rows_file'] ) ? (string) $session['rows_file'] : '';

	if ( '' === $file_path || ! file_exists( $file_path ) ) {
		return;
	}

	unlink( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
}

/**
 * Clears one import session and its stored row file.
 *
 * @param string $session_id Session ID.
 * @param array  $session Session payload.
 * @return void
 */
function cmp_clear_student_import_session( $session_id, $session = array() ) {
	delete_transient( cmp_get_student_import_session_key( $session_id ) );
	cmp_delete_student_import_session_rows( $session );
}

/**
 * Creates a resumable student import session.
 *
 * @param array $rows Parsed rows.
 * @param int   $class_id Class ID.
 * @param int   $batch_id Batch ID.
 * @return array|WP_Error
 */
function cmp_create_student_import_session( $rows, $class_id, $batch_id ) {
	$class_id = absint( $class_id );
	$batch_id = absint( $batch_id );
	$class    = cmp_get_class( $class_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $class || ! $batch || (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_import_batch_invalid', __( 'Choose a valid class and batch before importing students.', 'class-manager-pro' ) );
	}

	$rows = is_array( $rows ) ? array_values( $rows ) : array();

	if ( empty( $rows ) ) {
		return new WP_Error( 'cmp_import_rows_missing', __( 'No student rows were found in the uploaded file.', 'class-manager-pro' ) );
	}

	$session_id = sanitize_key( wp_generate_password( 20, false, false ) );
	$session    = array(
		'class_id'    => $class_id,
		'batch_id'    => $batch_id,
		'rows'        => array(),
		'rows_file'   => '',
		'offset'      => 0,
		'total_rows'  => count( $rows ),
		'first_error' => '',
		'summary'     => array(
			'imported'  => 0,
			'duplicate' => 0,
			'skipped'   => 0,
			'interested' => 0,
			'failed'    => 0,
		),
		'started_at'  => time(),
	);

	$rows_file = cmp_store_student_import_session_rows( $session_id, $rows );

	if ( is_wp_error( $rows_file ) ) {
		$session['rows'] = $rows;
	} else {
		$session['rows_file'] = $rows_file;
	}

	set_transient( cmp_get_student_import_session_key( $session_id ), $session, HOUR_IN_SECONDS * 4 );

	return array(
		'session_id' => $session_id,
		'session'    => $session,
	);
}

/**
 * Returns normalized progress information for a student import session.
 *
 * @param array $session Session payload.
 * @return array
 */
function cmp_get_student_import_progress( $session ) {
	$processed  = isset( $session['offset'] ) ? (int) $session['offset'] : 0;
	$total      = isset( $session['total_rows'] ) ? (int) $session['total_rows'] : 0;
	$percentage = $total > 0 ? min( 100, (int) round( ( $processed / $total ) * 100 ) ) : 100;

	return array(
		'processed'  => $processed,
		'total'      => $total,
		'percentage' => $percentage,
	);
}

/**
 * Processes the next chunk of a resumable student import session.
 *
 * @param string $session_id Session ID.
 * @param int    $chunk_size Chunk size.
 * @return array|WP_Error
 */
function cmp_process_student_import_session_chunk( $session_id, $chunk_size = 0 ) {
	$session_id = sanitize_key( $session_id );
	$session    = get_transient( cmp_get_student_import_session_key( $session_id ) );
	$chunk_size = $chunk_size ? absint( $chunk_size ) : cmp_get_student_import_chunk_size();

	if ( empty( $session ) || ! is_array( $session ) ) {
		return new WP_Error( 'cmp_import_session_missing', __( 'The import session expired. Please upload the file again.', 'class-manager-pro' ) );
	}

	cmp_apply_student_import_execution_limits();

	$class_id = isset( $session['class_id'] ) ? absint( $session['class_id'] ) : 0;
	$batch_id = isset( $session['batch_id'] ) ? absint( $session['batch_id'] ) : 0;
	$batch    = cmp_get_batch( $batch_id );
	$total    = isset( $session['total_rows'] ) ? (int) $session['total_rows'] : 0;
	$rows     = cmp_get_student_import_session_rows( $session );

	if ( ! $class_id || ! $batch || (int) $batch->class_id !== $class_id ) {
		cmp_clear_student_import_session( $session_id, $session );
		return new WP_Error( 'cmp_import_batch_invalid', __( 'The selected class or batch is no longer available for import.', 'class-manager-pro' ) );
	}

	if ( $total <= 0 || empty( $rows ) ) {
		cmp_clear_student_import_session( $session_id, $session );
		return new WP_Error( 'cmp_import_rows_missing', __( 'No student rows were found in the import session.', 'class-manager-pro' ) );
	}

	$offset  = isset( $session['offset'] ) ? max( 0, absint( $session['offset'] ) ) : 0;
	$summary = isset( $session['summary'] ) && is_array( $session['summary'] ) ? $session['summary'] : array(
		'imported'  => 0,
		'duplicate' => 0,
		'skipped'   => 0,
		'interested' => 0,
		'failed'    => 0,
	);

	for ( $processed = 0; $processed < $chunk_size && $offset < $total; ++$processed, ++$offset ) {
		$result = cmp_import_student_file_row( $rows[ $offset ], $class_id, $batch_id, $batch );
		$status = isset( $result['status'] ) ? $result['status'] : 'failed';

		if ( isset( $summary[ $status ] ) ) {
			++$summary[ $status ];
		} else {
			++$summary['failed'];
		}

		if ( 'failed' === $status && empty( $session['first_error'] ) && ! empty( $result['message'] ) ) {
			$session['first_error'] = sanitize_text_field( $result['message'] );
		}
	}

	$session['offset']  = $offset;
	$session['summary'] = $summary;
	$progress           = cmp_get_student_import_progress( $session );

	if ( $offset >= $total ) {
		$message = cmp_get_student_import_summary_message(
			$summary,
			isset( $session['first_error'] ) ? $session['first_error'] : ''
		);

		cmp_clear_student_import_session( $session_id, $session );

		return array(
			'stage'    => 'completed',
			'message'  => $message,
			'summary'  => $summary,
			'class_id' => $class_id,
			'batch_id' => $batch_id,
			'progress' => $progress,
		);
	}

	set_transient( cmp_get_student_import_session_key( $session_id ), $session, HOUR_IN_SECONDS * 4 );

	return array(
		'stage'    => 'processing',
		'message'  => sprintf(
			/* translators: 1: processed rows 2: total rows */
			__( 'Processing students: %1$d of %2$d rows completed.', 'class-manager-pro' ),
			$progress['processed'],
			$progress['total']
		),
		'summary'  => $summary,
		'class_id' => $class_id,
		'batch_id' => $batch_id,
		'progress' => $progress,
	);
}

/**
 * Processes a student file import request.
 *
 * @param array $uploaded_file Uploaded file array.
 * @param int   $class_id Selected class ID.
 * @param int   $batch_id Selected batch ID.
 * @return array|WP_Error
 */
function cmp_process_student_file_import_request( $uploaded_file, $class_id, $batch_id ) {
	cmp_apply_student_import_execution_limits();

	if ( empty( $uploaded_file ) || ! is_array( $uploaded_file ) ) {
		return new WP_Error( 'cmp_import_file_missing', __( 'File upload failed.', 'class-manager-pro' ) );
	}

	if ( ! isset( $uploaded_file['error'] ) || 0 !== (int) $uploaded_file['error'] ) {
		return new WP_Error( 'cmp_import_file_error', __( 'File upload failed.', 'class-manager-pro' ) );
	}

	$class_id = absint( $class_id );
	$batch_id = absint( $batch_id );
	$class    = cmp_get_class( $class_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $class || ! $batch || (int) $batch->class_id !== $class_id ) {
		return new WP_Error( 'cmp_import_batch_invalid', __( 'Choose a valid class and batch before importing students.', 'class-manager-pro' ) );
	}

	$rows = cmp_read_student_import_rows( $uploaded_file );

	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	if ( empty( $rows ) ) {
		return new WP_Error( 'cmp_import_rows_missing', __( 'No student rows were found in the uploaded file.', 'class-manager-pro' ) );
	}

	$summary      = array(
		'imported'  => 0,
		'duplicate' => 0,
		'skipped'   => 0,
		'interested' => 0,
		'failed'    => 0,
	);
	$first_error  = '';
	$chunked_rows = array_chunk( $rows, 100 );

	foreach ( $chunked_rows as $chunk ) {
		foreach ( $chunk as $row ) {
			$result = cmp_import_student_file_row( $row, $class_id, $batch_id, $batch );
			$status = isset( $result['status'] ) ? $result['status'] : 'failed';

			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			} else {
				++$summary['failed'];
			}

			if ( 'failed' === $status && '' === $first_error && ! empty( $result['message'] ) ) {
				$first_error = sanitize_text_field( $result['message'] );
			}
		}
	}

	return array(
		'summary'  => $summary,
		'message'  => cmp_get_student_import_summary_message( $summary, $first_error ),
		'class_id' => $class_id,
		'batch_id' => $batch_id,
	);
}

/**
 * Imports students from an uploaded CSV or spreadsheet.
 *
 * @return void
 */
function cmp_handle_import_students_file() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_import_students_file' );

	$uploaded_file = isset( $_FILES['import_file'] ) && is_array( $_FILES['import_file'] )
		? $_FILES['import_file']
		: ( isset( $_FILES['student_import_file'] ) && is_array( $_FILES['student_import_file'] ) ? $_FILES['student_import_file'] : array() );

	$result = cmp_process_student_file_import_request(
		$uploaded_file,
		absint( cmp_field( $_POST, 'class_id', 0 ) ),
		absint( cmp_field( $_POST, 'batch_id', 0 ) )
	);

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-razorpay-import', $result->get_error_message(), 'error' );
	}

	cmp_redirect(
		'cmp-razorpay-import',
		$result['message'],
		0 === (int) $result['summary']['failed'] && 0 === (int) $result['summary']['interested'] ? 'success' : 'warning',
		array(
			'class_id' => (int) $result['class_id'],
			'batch_id' => (int) $result['batch_id'],
		)
	);
}

/**
 * Imports students from an uploaded file via AJAX.
 */
function cmp_ajax_import_students_file() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_import_students_file' );

	$uploaded_file = isset( $_FILES['import_file'] ) && is_array( $_FILES['import_file'] )
		? $_FILES['import_file']
		: ( isset( $_FILES['student_import_file'] ) && is_array( $_FILES['student_import_file'] ) ? $_FILES['student_import_file'] : array() );

	$class_id = absint( cmp_field( $_POST, 'class_id', 0 ) );
	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	$rows     = cmp_read_student_import_rows( $uploaded_file );

	if ( is_wp_error( $rows ) ) {
		wp_send_json_error(
			array(
				'message' => $rows->get_error_message(),
			),
			400
		);
	}

	$result = cmp_create_student_import_session( $rows, $class_id, $batch_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	$initial_result = cmp_process_student_import_session_chunk( $result['session_id'] );

	if ( is_wp_error( $initial_result ) ) {
		wp_send_json_error(
			array(
				'message' => $initial_result->get_error_message(),
			),
			400
		);
	}

	if ( 'completed' !== $initial_result['stage'] ) {
		$initial_result['session_id'] = $result['session_id'];
	}

	wp_send_json_success( $initial_result );
}

/**
 * Processes the next chunk of a queued student import session.
 */
function cmp_ajax_process_student_import_chunk() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_import_students_file' );

	$result = cmp_process_student_import_session_chunk(
		sanitize_key( cmp_field( $_POST, 'session_id' ) ),
		absint( cmp_field( $_POST, 'chunk_size', 0 ) )
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success( $result );
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

	if ( 'payment_link' === $import_type || 'payment_page' === $import_type ) {
		$result = cmp_import_razorpay_payment_page( $razorpay_id );
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
	$page     = cmp_clean_return_page( cmp_field( $_POST, 'return_page', 'cmp-razorpay-import' ), 'cmp-razorpay-import' );
	$args     = array();

	if ( 'cmp-batches' === $page && $batch_id ) {
		$args = array(
			'action' => sanitize_key( cmp_field( $_POST, 'return_action', 'view' ) ),
			'id'     => $batch_id,
		);
	} elseif ( 'cmp-razorpay-import' === $page ) {
		$args = array(
			'razorpay_page_id' => $page_id,
			'batch_id'         => $batch_id,
		);
	}

	$result = cmp_process_razorpay_page_import_request( $page_id, $batch_id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $page, $result->get_error_message(), 'error', $args );
	}

	cmp_redirect( $page, $result['message'], 'success', $args );
}

/**
 * Imports a Razorpay page into a batch via AJAX.
 */
function cmp_ajax_import_razorpay_page_to_batch() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_import_razorpay_page_to_batch' );

	$result = cmp_process_razorpay_page_import_request(
		sanitize_text_field( cmp_field( $_POST, 'razorpay_page_id' ) ),
		absint( cmp_field( $_POST, 'batch_id', 0 ) )
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success( $result );
}

/**
 * Saves reminder settings.
 */
function cmp_handle_save_sms_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_sms_settings' );

	$days           = absint( cmp_field( $_POST, 'reminder_days', 7 ) );
	$email_template = isset( $_POST['email_template'] ) && is_scalar( $_POST['email_template'] ) ? wp_kses_post( wp_unslash( (string) $_POST['email_template'] ) ) : '';

	update_option( 'cmp_notifications_enabled', ! empty( $_POST['sms_enabled'] ) ? '1' : '0' );
	update_option( 'cmp_sms_enabled', ! empty( $_POST['sms_enabled'] ) ? '1' : '0' );
	update_option( 'cmp_notification_provider', 'log_only' );
	update_option( 'cmp_notification_webhook_url', '' );
	update_option( 'cmp_notification_auth_token', '' );
	update_option( 'cmp_notification_sender', '' );
	update_option( 'cmp_notification_channels', 'email' );
	update_option( 'cmp_reminder_days', in_array( $days, array( 1, 3, 7, 14 ), true ) ? $days : 7 );
	update_option( 'cmp_whatsapp_template', sanitize_textarea_field( cmp_field( $_POST, 'whatsapp_template' ) ) );
	update_option( 'cmp_sms_template', '' );
	update_option( 'cmp_email_subject', sanitize_text_field( cmp_field( $_POST, 'email_subject' ) ) );
	update_option( 'cmp_email_template', $email_template );
	update_option( 'cmp_message_template_payment_reminder', sanitize_textarea_field( cmp_field( $_POST, 'message_template_payment_reminder' ) ) );
	update_option( 'cmp_message_template_welcome', sanitize_textarea_field( cmp_field( $_POST, 'message_template_welcome' ) ) );
	update_option( 'cmp_message_template_course_info', sanitize_textarea_field( cmp_field( $_POST, 'message_template_course_info' ) ) );

	cmp_redirect( 'cmp-settings', __( 'Reminder settings saved successfully.', 'class-manager-pro' ) );
}

/**
 * Saves attendance settings.
 */
function cmp_handle_save_attendance_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_attendance_settings' );

	update_option( 'cmp_attendance_enabled', ! empty( $_POST['attendance_enabled'] ) ? '1' : '0' );
	update_option( 'cmp_default_attendance_status', 'present' );

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
 * Gets attendance marker data keyed by date for one batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $start_date Optional start date.
 * @param string $end_date Optional end date.
 * @return array
 */
function cmp_get_batch_attendance_date_markers( $batch_id, $start_date = '', $end_date = '' ) {
	global $wpdb;

	$batch_id   = absint( $batch_id );
	$start_date = sanitize_text_field( $start_date );
	$end_date   = sanitize_text_field( $end_date );

	if ( ! $batch_id ) {
		return array();
	}

	$sql      = 'SELECT attendance_date,
			COUNT(*) AS total,
			SUM(CASE WHEN status = \'present\' THEN 1 ELSE 0 END) AS present_count,
			SUM(CASE WHEN status = \'absent\' THEN 1 ELSE 0 END) AS absent_count,
			SUM(CASE WHEN status = \'leave\' THEN 1 ELSE 0 END) AS leave_count
		FROM ' . cmp_table( 'attendance' ) . '
		WHERE batch_id = %d';
	$params   = array( $batch_id );

	if ( '' !== $start_date ) {
		$sql      .= ' AND attendance_date >= %s';
		$params[] = $start_date;
	}

	if ( '' !== $end_date ) {
		$sql      .= ' AND attendance_date <= %s';
		$params[] = $end_date;
	}

	$sql  .= ' GROUP BY attendance_date ORDER BY attendance_date DESC';
	$rows  = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	$dates = array();

	foreach ( $rows as $row ) {
		$dates[ sanitize_text_field( $row->attendance_date ) ] = array(
			'total'   => (int) $row->total,
			'present' => (int) $row->present_count,
			'absent'  => (int) $row->absent_count,
			'leave'   => (int) $row->leave_count,
		);
	}

	return $dates;
}

/**
 * Builds a recent attendance date strip for one batch.
 *
 * @param int    $batch_id Batch ID.
 * @param string $selected_date Currently selected date.
 * @param int    $days Number of dates to show.
 * @return array
 */
function cmp_get_batch_attendance_date_strip( $batch_id, $selected_date, $days = 14 ) {
	$days               = max( 7, absint( $days ) );
	$selected_date      = sanitize_text_field( $selected_date );
	$selected_timestamp = strtotime( $selected_date );

	if ( ! $selected_timestamp ) {
		$selected_date      = current_time( 'Y-m-d' );
		$selected_timestamp = strtotime( $selected_date );
	}

	$start_date = wp_date( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', $selected_timestamp ) );
	$end_date   = wp_date( 'Y-m-d', $selected_timestamp );
	$markers    = cmp_get_batch_attendance_date_markers( $batch_id, $start_date, $end_date );
	$strip      = array();

	for ( $index = $days - 1; $index >= 0; --$index ) {
		$timestamp = strtotime( '-' . $index . ' days', $selected_timestamp );
		$date      = wp_date( 'Y-m-d', $timestamp );
		$strip[]   = array(
			'date'        => $date,
			'day_label'   => wp_date( 'D', $timestamp ),
			'date_label'  => wp_date( 'd M', $timestamp ),
			'is_selected' => $date === $selected_date,
			'has_records' => isset( $markers[ $date ] ),
			'summary'     => isset( $markers[ $date ] ) ? $markers[ $date ] : array(
				'total'   => 0,
				'present' => 0,
				'absent'  => 0,
				'leave'   => 0,
			),
		);
	}

	return $strip;
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
		$status     = cmp_clean_enum( isset( $entry['status'] ) ? $entry['status'] : '', cmp_attendance_statuses(), 'present' );

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
				'notes'            => '',
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
	$args     = array(
		'action'          => 'view',
		'id'              => $batch_id,
		'attendance_date' => $date,
	);

	if ( ! $batch_id || ! cmp_current_user_can_manage_batch_attendance( $batch_id ) ) {
		wp_die( esc_html__( 'You do not have permission to save attendance for this batch.', 'class-manager-pro' ) );
	}

	cmp_save_batch_attendance( $batch_id, $date, $entries );

	if ( 'cmp-teacher-console' === $page ) {
		$args = array(
			'id'              => $batch_id,
			'attendance_date' => $date,
			'teacher_view'    => sanitize_key( cmp_field( $_POST, 'teacher_view', 'attendance' ) ),
		);

		$teacher_user_id = absint( cmp_field( $_POST, 'teacher_user_id', 0 ) );

		if ( $teacher_user_id ) {
			$args['teacher_user_id'] = $teacher_user_id;
		}
	} else {
		$args['tab'] = 'attendance';
	}

	cmp_redirect(
		$page,
		__( 'Attendance saved successfully.', 'class-manager-pro' ),
		'success',
		$args
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
			'date'    => $date,
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
 * Returns the default styled message templates.
 *
 * @return array
 */
function cmp_get_default_message_templates() {
	return array(
		'payment_reminder' => __( "Hello {{name}},\n\nClass: {{course}}\nBatch: {{batch}}\nPending Fee: Rs {{amount}}\nDue Date: {{due_date}}\nPayment Link: {{payment_link}}\n\nPlease complete your payment before the due date.", 'class-manager-pro' ),
		'welcome_message'  => __( "Hello {{name}},\n\nWelcome to {{course}}.\nBatch: {{batch}}\nWe are glad to have you with us.", 'class-manager-pro' ),
		'course_info'      => __( "Hello {{name}},\n\nClass: {{course}}\nBatch: {{batch}}\nCurrent payable amount: Rs {{amount}}", 'class-manager-pro' ),
		'email_template'   => '<p>' . esc_html__( 'Hello {{name}},', 'class-manager-pro' ) . '</p><p>' . esc_html__( 'This is a reminder that Rs {{amount}} is pending for {{course}} in batch {{batch}}.', 'class-manager-pro' ) . '</p><p>' . esc_html__( 'Please contact the admin if you need any help.', 'class-manager-pro' ) . '</p>',
	);
}

/**
 * Builds template replacements for a student-facing reminder.
 *
 * @param object $student Student row.
 * @return array
 */
function cmp_get_student_template_replacements( $student ) {
	$amount = cmp_format_money( max( 0, (float) $student->total_fee - (float) $student->paid_fee ) );
	$course = ! empty( $student->class_name ) ? $student->class_name : '';
	$batch  = ! empty( $student->batch_name ) ? $student->batch_name : '';
	$due    = ! empty( $student->fee_due_date ) ? $student->fee_due_date : __( 'Not set', 'class-manager-pro' );
	$link   = ! empty( $student->razorpay_link ) ? $student->razorpay_link : __( 'Please contact the admin.', 'class-manager-pro' );

	return array(
		'{{name}}'         => $student->name,
		'{{amount}}'       => $amount,
		'{{course}}'       => $course,
		'{{batch}}'        => $batch,
		'{{due_date}}'     => $due,
		'{{payment_link}}' => $link,
		'{student_name}'   => $student->name,
		'{class_name}'     => $course,
		'{batch_name}'     => $batch,
		'{pending_fee}'    => $amount,
		'{due_date}'       => $due,
		'{payment_link}'   => $link,
	);
}

/**
 * Renders a reminder template using student details.
 *
 * @param string $template Template text.
 * @param object $student Student row.
 * @return string
 */
function cmp_render_student_template( $template, $student ) {
	return strtr( (string) $template, cmp_get_student_template_replacements( $student ) );
}

/**
 * Prepares an email body for HTML delivery.
 *
 * @param string $message Email message.
 * @return string
 */
function cmp_prepare_email_body( $message ) {
	$message   = (string) $message;
	$has_html  = $message !== wp_strip_all_tags( $message );

	if ( $has_html ) {
		return wpautop( wp_kses_post( $message ) );
	}

	return nl2br( esc_html( $message ) );
}

/**
 * Builds a fee reminder message.
 *
 * @param object $student Student row.
 * @param string $channel sms|whatsapp|email.
 * @return string
 */
function cmp_build_fee_reminder_message( $student, $channel ) {
	$defaults = cmp_get_default_message_templates();
	$legacy_whatsapp_template = 'Hello {{name}}, your pending fee for {{course}} - {{batch}} is Rs {{amount}}. Please complete the payment before the due date.';

	if ( 'whatsapp' === $channel ) {
		$template = (string) get_option( 'cmp_whatsapp_template', '' );
	} elseif ( 'email' === $channel ) {
		$template = (string) get_option( 'cmp_email_template', '' );
	} else {
		$template = (string) get_option( 'cmp_sms_template', '' );
	}

	if ( '' === trim( $template ) ) {
		$template = 'email' === $channel ? $defaults['email_template'] : (string) get_option( 'cmp_message_template_payment_reminder', $defaults['payment_reminder'] );
	}

	if ( 'whatsapp' === $channel && trim( wp_strip_all_tags( $template ) ) === $legacy_whatsapp_template ) {
		$template = $defaults['payment_reminder'];
	}

	return cmp_render_student_template( $template, $student );
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
		$template = __( 'Fee reminder for {{course}} - {{batch}}', 'class-manager-pro' );
	}

	return wp_strip_all_tags( cmp_render_student_template( $template, $student ) );
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
 * Returns a direct follow-up email URL for a student.
 *
 * @param object $student Student row.
 * @param string $return_page Return page slug.
 * @param array  $return_args Return query arguments.
 * @return string
 */
function cmp_get_email_reminder_url( $student, $return_page = '', $return_args = array() ) {
	if ( empty( $student->email ) || empty( $student->id ) ) {
		return '';
	}

	$student_id = (int) $student->id;

	if ( '' === $return_page ) {
		$return_page = cmp_clean_return_page( cmp_field( $_GET, 'page', 'cmp-students' ), 'cmp-students' );
	}

	if ( empty( $return_args ) && 'cmp-students' === $return_page ) {
		$return_args = array(
			'action' => 'view',
			'id'     => $student_id,
		);
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
				'action'      => 'cmp_send_student_follow_up_email',
				'id'          => $student_id,
				'return_page' => $return_page,
			),
			$redirect_args
		),
		admin_url( 'admin-post.php' )
	);

	return wp_nonce_url( $url, 'cmp_send_student_follow_up_email_' . $student_id );
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
 * Sends one follow-up email directly through WordPress.
 */
function cmp_handle_send_student_follow_up_email() {
	cmp_require_manage_options();

	$student_id = absint( cmp_field( $_REQUEST, 'id', 0 ) );
	$page       = cmp_clean_return_page( cmp_field( $_REQUEST, 'return_page', 'cmp-students' ), 'cmp-students' );
	$args       = array();

	foreach ( $_REQUEST as $key => $value ) {
		if ( ! is_scalar( $value ) || 'return_page' === $key || 0 !== strpos( (string) $key, 'return_' ) ) {
			continue;
		}

		$args[ substr( (string) $key, 7 ) ] = wp_unslash( (string) $value );
	}

	if ( empty( $args ) && 'cmp-students' === $page ) {
		$args = array(
			'action' => 'view',
			'id'     => $student_id,
		);
	}

	check_admin_referer( 'cmp_send_student_follow_up_email_' . $student_id );
	$result = cmp_send_student_follow_up_email_by_id( $student_id );

	cmp_redirect( $page, $result['message'], ! empty( $result['success'] ) ? 'success' : 'error', $args );
}

/**
 * Sends a single follow-up email via AJAX.
 */
function cmp_ajax_send_student_follow_up_email() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	$result = cmp_send_student_follow_up_email_by_id( absint( cmp_field( $_POST, 'student_id', 0 ) ) );

	if ( ! empty( $result['success'] ) ) {
		wp_send_json_success( $result );
	}

	wp_send_json_error( $result, 400 );
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

		$sent = wp_mail(
			sanitize_email( $student->email ),
			cmp_build_fee_reminder_subject( $student ),
			cmp_prepare_email_body( $message ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

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

	$channels = array( 'email' );

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
			__( 'Email reminder run completed. %1$d sent, %2$d failed, %3$d skipped.', 'class-manager-pro' ),
			(int) $counts['sent'],
			(int) $counts['failed'],
			(int) $counts['skipped']
		)
	);
}
