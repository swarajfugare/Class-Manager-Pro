<?php
/**
 * Operations Center helpers for Class Manager Pro.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_cmp_save_operations_event', 'cmp_handle_save_operations_event' );
add_action( 'admin_post_cmp_delete_operations_event', 'cmp_handle_delete_operations_event' );
add_action( 'admin_post_cmp_save_operations_document', 'cmp_handle_save_operations_document' );
add_action( 'admin_post_cmp_delete_operations_document', 'cmp_handle_delete_operations_document' );
add_action( 'admin_post_cmp_save_operations_goal', 'cmp_handle_save_operations_goal' );
add_action( 'admin_post_cmp_delete_operations_goal', 'cmp_handle_delete_operations_goal' );
add_action( 'admin_post_cmp_save_operations_settings', 'cmp_handle_save_operations_settings' );
add_action( 'admin_post_cmp_export_operations_ics', 'cmp_handle_export_operations_ics' );
add_action( 'wp_ajax_cmp_save_operations_event_ajax', 'cmp_ajax_save_operations_event' );
add_action( 'wp_ajax_cmp_delete_operations_event_ajax', 'cmp_ajax_delete_operations_event' );
add_action( 'wp_ajax_cmp_get_operations_calendar_events', 'cmp_ajax_get_operations_calendar_events' );
add_action( 'wp_ajax_cmp_save_operations_document_ajax', 'cmp_ajax_save_operations_document' );
add_action( 'wp_ajax_cmp_delete_operations_document_ajax', 'cmp_ajax_delete_operations_document' );
add_action( 'wp_ajax_cmp_save_operations_goal_ajax', 'cmp_ajax_save_operations_goal' );
add_action( 'wp_ajax_cmp_delete_operations_goal_ajax', 'cmp_ajax_delete_operations_goal' );
add_action( 'wp_ajax_cmp_save_operations_settings_ajax', 'cmp_ajax_save_operations_settings' );
add_action( 'wp_ajax_cmp_filter_operations_section', 'cmp_ajax_filter_operations_section' );
add_action( 'wp_ajax_cmp_search_operations_center', 'cmp_ajax_search_operations_center' );
add_action( 'init', 'cmp_schedule_operations_center_reminders' );
add_action( 'cmp_operations_center_reminder_cron', 'cmp_process_operations_center_reminders' );

/**
 * Returns the operations center settings option key.
 *
 * @return string
 */
function cmp_operations_center_settings_option() {
	return 'cmp_operations_center_settings';
}

/**
 * Returns the operations center default settings.
 *
 * @return array
 */
function cmp_operations_center_default_settings() {
	return array(
		'calendar_default_view' => 'dayGridMonth',
		'working_hours_start'    => '09:00',
		'working_hours_end'      => '19:00',
		'ics_feed_title'         => __( 'Class Manager Pro Operations Calendar', 'class-manager-pro' ),
		'reminder_subject'       => __( 'Operations Center reminder', 'class-manager-pro' ),
		'reminder_email'         => __( 'A reminder is due for {{title}}.', 'class-manager-pro' ),
	);
}

/**
 * Returns the current operations center settings.
 *
 * @return array
 */
function cmp_get_operations_center_settings() {
	$settings = get_option( cmp_operations_center_settings_option(), array() );

	return wp_parse_args(
		is_array( $settings ) ? $settings : array(),
		cmp_operations_center_default_settings()
	);
}

/**
 * Updates operations center settings.
 *
 * @param array $data Settings payload.
 * @return true|WP_Error
 */
function cmp_update_operations_center_settings( $data ) {
	$settings = cmp_get_operations_center_settings();

	$settings['calendar_default_view'] = in_array( cmp_field( $data, 'calendar_default_view', 'dayGridMonth' ), array( 'dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listWeek' ), true ) ? sanitize_key( cmp_field( $data, 'calendar_default_view', 'dayGridMonth' ) ) : 'dayGridMonth';
	$settings['working_hours_start']    = preg_match( '/^\d{2}:\d{2}$/', (string) cmp_field( $data, 'working_hours_start', '09:00' ) ) ? sanitize_text_field( cmp_field( $data, 'working_hours_start', '09:00' ) ) : '09:00';
	$settings['working_hours_end']      = preg_match( '/^\d{2}:\d{2}$/', (string) cmp_field( $data, 'working_hours_end', '19:00' ) ) ? sanitize_text_field( cmp_field( $data, 'working_hours_end', '19:00' ) ) : '19:00';
	$settings['ics_feed_title']         = sanitize_text_field( cmp_field( $data, 'ics_feed_title', $settings['ics_feed_title'] ) );
	$settings['reminder_subject']       = sanitize_text_field( cmp_field( $data, 'reminder_subject', $settings['reminder_subject'] ) );
	$settings['reminder_email']         = sanitize_textarea_field( cmp_field( $data, 'reminder_email', $settings['reminder_email'] ) );

	$updated = update_option( cmp_operations_center_settings_option(), $settings );

	if ( false === $updated ) {
		return new WP_Error( 'cmp_operations_settings_failed', __( 'Could not save operations settings.', 'class-manager-pro' ) );
	}

	cmp_operations_bump_cache_version();

	return true;
}

/**
 * Saves Operations Center settings and returns a structured response.
 *
 * @param array $data Settings payload.
 * @return array|WP_Error
 */
function cmp_save_operations_center_settings_record( $data ) {
	$result = cmp_update_operations_center_settings( $data );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'message' => __( 'Operations settings saved successfully.', 'class-manager-pro' ),
	);
}

/**
 * Handles Operations Center settings save via admin-post.
 *
 * @return void
 */
function cmp_handle_save_operations_settings() {
	cmp_require_manage_options();
	check_admin_referer( 'cmp_save_operations_settings' );

	$result = cmp_save_operations_center_settings_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-operations-center-settings', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-operations-center-settings', $result['message'] );
}

/**
 * Handles Operations Center settings save via AJAX.
 *
 * @return void
 */
function cmp_ajax_save_operations_settings() {
	cmp_require_manage_options_ajax();
	check_ajax_referer( 'cmp_save_operations_settings', 'nonce' );

	$result = cmp_save_operations_center_settings_record( wp_unslash( $_POST ) );

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
			'message' => $result['message'],
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> 'settings',
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Returns whether the current user may access the Operations Center.
 *
 * @return bool
 */
function cmp_operations_user_can_access() {
	return current_user_can( 'manage_options' ) || cmp_current_user_has_teacher_batches();
}

/**
 * Stops execution for unauthorized Operations Center access.
 */
function cmp_require_operations_center_access() {
	if ( ! cmp_operations_user_can_access() ) {
		wp_die( esc_html__( 'You do not have permission to access Operations Center.', 'class-manager-pro' ) );
	}
}

/**
 * Stops AJAX execution for unauthorized Operations Center access.
 */
function cmp_require_operations_center_access_ajax() {
	if ( cmp_operations_user_can_access() ) {
		return;
	}

	wp_send_json_error(
		array(
			'message' => __( 'You do not have permission to access Operations Center.', 'class-manager-pro' ),
		),
		403
	);
}

/**
 * Returns whether the current view should be restricted to the teacher scope.
 *
 * @return bool
 */
function cmp_operations_is_teacher_view() {
	return ! current_user_can( 'manage_options' ) && cmp_current_user_has_teacher_batches();
}

/**
 * Returns a cache version for Operations Center payloads.
 *
 * @return int
 */
function cmp_operations_cache_version() {
	return max( 1, absint( get_option( 'cmp_operations_cache_version', 1 ) ) );
}

/**
 * Bumps the Operations Center cache version.
 *
 * @return void
 */
function cmp_operations_bump_cache_version() {
	$version = cmp_operations_cache_version() + 1;

	update_option( 'cmp_operations_cache_version', $version, false );
}

/**
 * Builds a cache key for Operations Center data.
 *
 * @param string $suffix Cache namespace.
 * @param array  $args Context args.
 * @return string
 */
function cmp_operations_cache_key( $suffix, $args = array() ) {
	$suffix = sanitize_key( $suffix );

	return 'cmp_ops_' . $suffix . '_' . md5( wp_json_encode( $args ) . '|' . cmp_operations_cache_version() . '|' . get_current_user_id() );
}

/**
 * Returns a teacher-visible batch ID list for scoped queries.
 *
 * @return array
 */
function cmp_operations_scope_batch_ids() {
	if ( current_user_can( 'manage_options' ) ) {
		return array();
	}

	$batches = cmp_get_current_teacher_batches();
	$ids     = array();

	foreach ( $batches as $batch ) {
		$ids[] = (int) $batch->id;
	}

	return cmp_clean_absint_array( $ids );
}

/**
 * Returns the allowed event types.
 *
 * @return array
 */
function cmp_operations_event_types() {
	return array(
		'class'     => __( 'Class', 'class-manager-pro' ),
		'batch'     => __( 'Batch', 'class-manager-pro' ),
		'teacher'   => __( 'Teacher', 'class-manager-pro' ),
		'marketing' => __( 'Marketing', 'class-manager-pro' ),
		'business'  => __( 'Business', 'class-manager-pro' ),
		'holiday'   => __( 'Holiday', 'class-manager-pro' ),
		'reminder'  => __( 'Reminder', 'class-manager-pro' ),
		'other'     => __( 'Other', 'class-manager-pro' ),
	);
}

/**
 * Returns the allowed event statuses.
 *
 * @return array
 */
function cmp_operations_event_statuses() {
	return array(
		'planned'     => __( 'Planned', 'class-manager-pro' ),
		'scheduled'   => __( 'Scheduled', 'class-manager-pro' ),
		'in_progress' => __( 'In Progress', 'class-manager-pro' ),
		'completed'   => __( 'Completed', 'class-manager-pro' ),
		'cancelled'   => __( 'Cancelled', 'class-manager-pro' ),
	);
}

/**
 * Returns the allowed event priorities.
 *
 * @return array
 */
function cmp_operations_event_priorities() {
	return array(
		'low'    => __( 'Low', 'class-manager-pro' ),
		'normal' => __( 'Normal', 'class-manager-pro' ),
		'high'   => __( 'High', 'class-manager-pro' ),
		'urgent' => __( 'Urgent', 'class-manager-pro' ),
	);
}

/**
 * Returns the allowed recurrence frequencies.
 *
 * @return array
 */
function cmp_operations_recurrence_frequencies() {
	return array(
		'none'    => __( 'None', 'class-manager-pro' ),
		'daily'   => __( 'Daily', 'class-manager-pro' ),
		'weekly'  => __( 'Weekly', 'class-manager-pro' ),
		'monthly' => __( 'Monthly', 'class-manager-pro' ),
	);
}

/**
 * Returns the allowed document types.
 *
 * @return array
 */
function cmp_operations_document_types() {
	return array(
		'pdf'      => __( 'PDF', 'class-manager-pro' ),
		'material' => __( 'Material List', 'class-manager-pro' ),
		'homework' => __( 'Homework', 'class-manager-pro' ),
		'recording'=> __( 'Recording Link', 'class-manager-pro' ),
		'reference'=> __( 'Reference Files', 'class-manager-pro' ),
		'teacher'  => __( 'Teacher Files', 'class-manager-pro' ),
		'batch'    => __( 'Batch Document', 'class-manager-pro' ),
		'other'    => __( 'Other', 'class-manager-pro' ),
	);
}

/**
 * Returns the allowed goal types.
 *
 * @return array
 */
function cmp_operations_goal_types() {
	return array(
		'revenue'       => __( 'Monthly Revenue Goal', 'class-manager-pro' ),
		'admissions'    => __( 'Admission Goal', 'class-manager-pro' ),
		'teacher'       => __( 'Teacher Goal', 'class-manager-pro' ),
		'marketing'     => __( 'Marketing Goal', 'class-manager-pro' ),
		'expense'       => __( 'Expense Goal', 'class-manager-pro' ),
		'batch'         => __( 'Batch Goal', 'class-manager-pro' ),
		'completion'    => __( 'Completion Goal', 'class-manager-pro' ),
		'attendance'    => __( 'Attendance Goal', 'class-manager-pro' ),
		'other'         => __( 'Other', 'class-manager-pro' ),
	);
}

/**
 * Returns color mappings for event types.
 *
 * @return array
 */
function cmp_operations_event_colors() {
	return array(
		'class'     => '#2271b1',
		'batch'     => '#0a7f5a',
		'teacher'   => '#8c5fbf',
		'marketing' => '#dba617',
		'business'  => '#007cba',
		'holiday'   => '#d63638',
		'reminder'  => '#c94f7c',
		'other'     => '#6c757d',
	);
}

/**
 * Returns a normalized event color.
 *
 * @param string $event_type Event type.
 * @return string
 */
function cmp_operations_event_color( $event_type ) {
	$colors = cmp_operations_event_colors();
	$event_type = sanitize_key( $event_type );

	return isset( $colors[ $event_type ] ) ? $colors[ $event_type ] : $colors['other'];
}

/**
 * Returns a human label for an Operations Center status.
 *
 * @param string $status Status key.
 * @return string
 */
function cmp_operations_status_label( $status ) {
	$labels = cmp_operations_event_statuses();
	$status = sanitize_key( $status );

	if ( isset( $labels[ $status ] ) ) {
		return $labels[ $status ];
	}

	return ucwords( str_replace( '_', ' ', $status ) );
}

/**
 * Returns a human label for an Operations Center priority.
 *
 * @param string $priority Priority key.
 * @return string
 */
function cmp_operations_priority_label( $priority ) {
	$labels = cmp_operations_event_priorities();
	$priority = sanitize_key( $priority );

	if ( isset( $labels[ $priority ] ) ) {
		return $labels[ $priority ];
	}

	return ucwords( str_replace( '_', ' ', $priority ) );
}

/**
 * Returns a human label for an Operations Center event type.
 *
 * @param string $event_type Event type.
 * @return string
 */
function cmp_operations_event_type_label( $event_type ) {
	$labels = cmp_operations_event_types();
	$event_type = sanitize_key( $event_type );

	if ( isset( $labels[ $event_type ] ) ) {
		return $labels[ $event_type ];
	}

	return ucwords( str_replace( '_', ' ', $event_type ) );
}

/**
 * Normalizes a datetime value for storage.
 *
 * @param mixed $value Raw value.
 * @param bool  $all_day Whether the event is all-day.
 * @return string
 */
function cmp_operations_normalize_datetime( $value, $all_day = false ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return '';
	}

	$timestamp = strtotime( $value );

	if ( false === $timestamp ) {
		return '';
	}

	return $all_day ? gmdate( 'Y-m-d 00:00:00', $timestamp ) : gmdate( 'Y-m-d H:i:s', $timestamp );
}

/**
 * Converts a storage datetime to an ISO string for the UI.
 *
 * @param string $value Datetime string.
 * @return string
 */
function cmp_operations_to_iso( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	$timestamp = strtotime( $value );

	return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i:s', $timestamp );
}

/**
 * Returns the Operations Center reminder cron hook.
 *
 * @return string
 */
function cmp_operations_reminder_hook() {
	return 'cmp_operations_center_reminder_cron';
}

/**
 * Schedules reminder processing.
 *
 * @return void
 */
function cmp_schedule_operations_center_reminders() {
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}

	if ( ! wp_next_scheduled( cmp_operations_reminder_hook() ) ) {
		wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'hourly', cmp_operations_reminder_hook() );
	}
}

/**
 * Returns the operations events table name.
 *
 * @return string
 */
function cmp_operations_events_table() {
	return cmp_table( 'operations_events' );
}

/**
 * Returns the operations documents table name.
 *
 * @return string
 */
function cmp_operations_documents_table() {
	return cmp_table( 'operations_documents' );
}

/**
 * Returns the operations goals table name.
 *
 * @return string
 */
function cmp_operations_goals_table() {
	return cmp_table( 'operations_goals' );
}

/**
 * Returns whether the Operations Center schema exists.
 *
 * @return bool
 */
function cmp_operations_schema_needs_upgrade() {
	global $wpdb;

	$tables = array(
		cmp_operations_events_table(),
		cmp_operations_documents_table(),
		cmp_operations_goals_table(),
	);

	foreach ( $tables as $table ) {
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! is_string( $found ) || $table !== $found ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the Operations Center query scope SQL.
 *
 * @param string $alias Table alias.
 * @param array  $args Scope args.
 * @return array
 */
function cmp_operations_scope_sql( $alias = 'e', $args = array() ) {
	$alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $alias );
	$alias = '' === $alias ? 'e' : $alias;
	$where = array();
	$params = array();

	if ( ! current_user_can( 'manage_options' ) ) {
		$batch_ids = cmp_operations_scope_batch_ids();

		if ( empty( $batch_ids ) ) {
			$where[] = '1 = 0';

			return array(
				'where'  => $where,
				'params' => $params,
			);
		}

		$placeholders = implode( ', ', array_fill( 0, count( $batch_ids ), '%d' ) );
		$where[] = '(' . $alias . '.batch_id IN (' . $placeholders . ') OR ' . $alias . '.teacher_user_id = %d OR ' . $alias . '.assigned_user_id = %d)';
		$params = array_merge( $params, $batch_ids, array( get_current_user_id(), get_current_user_id() ) );
	}

	$batch_id = absint( cmp_field( $args, 'batch_id', 0 ) );
	$teacher_user_id = absint( cmp_field( $args, 'teacher_user_id', 0 ) );

	if ( $batch_id > 0 ) {
		$where[]  = $alias . '.batch_id = %d';
		$params[] = $batch_id;
	}

	if ( $teacher_user_id > 0 ) {
		$where[]  = $alias . '.teacher_user_id = %d';
		$params[] = $teacher_user_id;
	}

	return array(
		'where'  => $where,
		'params' => $params,
	);
}

/**
 * Builds a date range where clause.
 *
 * @param string $alias Table alias.
 * @param string $start Start date.
 * @param string $end End date.
 * @return array
 */
function cmp_operations_range_sql( $alias, $start, $end ) {
	$where = array();
	$params = array();

	if ( '' !== trim( (string) $start ) ) {
		$where[]  = '(' . $alias . '.start_at IS NULL OR ' . $alias . '.start_at <= %s)';
		$params[] = $end;
	}

	if ( '' !== trim( (string) $end ) ) {
		$where[]  = '(' . $alias . '.end_at IS NULL OR ' . $alias . '.end_at >= %s)';
		$params[] = $start;
	}

	return array(
		'where'  => $where,
		'params' => $params,
	);
}

/**
 * Parses a recurrence rule.
 *
 * @param string $rule Rule payload.
 * @return array
 */
function cmp_operations_parse_recurrence_rule( $rule ) {
	$rule = trim( (string) $rule );

	if ( '' === $rule ) {
		return array(
			'freq'     => 'none',
			'interval' => 1,
			'count'    => 0,
			'until'    => '',
		);
	}

	$decoded = json_decode( $rule, true );

	if ( is_array( $decoded ) ) {
		return wp_parse_args(
			$decoded,
			array(
				'freq'     => 'none',
				'interval' => 1,
				'count'    => 0,
				'until'    => '',
			)
		);
	}

	return array(
		'freq'     => sanitize_key( $rule ),
		'interval' => 1,
		'count'    => 0,
		'until'    => '',
	);
}

/**
 * Returns true if an event overlaps a requested range.
 *
 * @param object $event Event row.
 * @param string $start Range start.
 * @param string $end Range end.
 * @return bool
 */
function cmp_operations_event_overlaps_range( $event, $start, $end ) {
	if ( ! $event ) {
		return false;
	}

	$event_start = ! empty( $event->start_at ) ? strtotime( $event->start_at ) : 0;
	$event_end   = ! empty( $event->end_at ) ? strtotime( $event->end_at ) : $event_start;
	$range_start = strtotime( $start );
	$range_end   = strtotime( $end );

	if ( false === $range_start || false === $range_end ) {
		return true;
	}

	if ( ! $event_start ) {
		return true;
	}

	if ( $event_end < $range_start ) {
		return false;
	}

	if ( $event_start > $range_end ) {
		return false;
	}

	return true;
}

/**
 * Expands a recurring event into calendar items.
 *
 * @param object $event Event row.
 * @param string $start Range start.
 * @param string $end Range end.
 * @return array
 */
function cmp_operations_expand_event_occurrences( $event, $start, $end ) {
	$rule = cmp_operations_parse_recurrence_rule( isset( $event->recurrence_rule ) ? $event->recurrence_rule : '' );
	$freq = sanitize_key( cmp_field( $rule, 'freq', 'none' ) );
	$interval = max( 1, absint( cmp_field( $rule, 'interval', 1 ) ) );
	$count = max( 0, absint( cmp_field( $rule, 'count', 0 ) ) );
	$until = trim( (string) cmp_field( $rule, 'until', '' ) );
	$items = array();
	$base_start = ! empty( $event->start_at ) ? strtotime( $event->start_at ) : false;
	$base_end = ! empty( $event->end_at ) ? strtotime( $event->end_at ) : false;

	if ( 'none' === $freq || ! $base_start ) {
		if ( cmp_operations_event_overlaps_range( $event, $start, $end ) ) {
			$items[] = $event;
		}

		return $items;
	}

	$range_start = strtotime( $start );
	$range_end   = strtotime( $end );
	$until_ts    = '' !== $until ? strtotime( $until . ' 23:59:59' ) : 0;
	$max_runs    = $count > 0 ? $count : 24;
	$seed        = 0;
	$cursor      = $base_start;
	$duration    = $base_end && $base_end > $base_start ? ( $base_end - $base_start ) : 0;

	while ( $seed < $max_runs ) {
		if ( $until_ts && $cursor > $until_ts ) {
			break;
		}

		if ( $cursor > $range_end ) {
			break;
		}

		$occurrence_end = $duration > 0 ? $cursor + $duration : $cursor;

		if ( $occurrence_end >= $range_start && $cursor <= $range_end ) {
			$copy = clone $event;
			$copy->id = $event->id . '-' . $seed;
			$copy->start_at = gmdate( 'Y-m-d H:i:s', $cursor );
			$copy->end_at = $duration > 0 ? gmdate( 'Y-m-d H:i:s', $occurrence_end ) : '';
			$copy->occurrence_index = $seed;
			$copy->is_recurring_instance = 1;
			$items[] = $copy;
		}

		++$seed;

		if ( 'daily' === $freq ) {
			$cursor = strtotime( '+' . $interval . ' day', $cursor );
		} elseif ( 'weekly' === $freq ) {
			$cursor = strtotime( '+' . $interval . ' week', $cursor );
		} elseif ( 'monthly' === $freq ) {
			$cursor = strtotime( '+' . $interval . ' month', $cursor );
		} else {
			break;
		}
	}

	return $items;
}

/**
 * Returns raw operations event rows.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_event_rows( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'         => '',
			'event_type'     => '',
			'status'         => '',
			'priority'       => '',
			'batch_id'       => 0,
			'teacher_user_id'=> 0,
			'start'          => '',
			'end'            => '',
			'limit'          => 0,
			'offset'         => 0,
		)
	);

	$query = 'SELECT e.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name
		FROM ' . cmp_operations_events_table() . ' e
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = e.batch_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = e.class_id
		LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.teacher_user_id';
	$where = array();
	$params = array();
	$scope = cmp_operations_scope_sql( 'e', $args );
	$where = array_merge( $where, $scope['where'] );
	$params = array_merge( $params, $scope['params'] );

	if ( '' !== trim( (string) $args['search'] ) ) {
		$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		$where[] = '(e.title LIKE %s OR e.notes LIKE %s OR e.location LIKE %s OR b.batch_name LIKE %s OR c.name LIKE %s OR u.display_name LIKE %s)';
		$params   = array_merge( $params, array( $like, $like, $like, $like, $like, $like ) );
	}

	if ( '' !== trim( (string) $args['event_type'] ) ) {
		$where[]  = 'e.event_type = %s';
		$params[] = sanitize_key( $args['event_type'] );
	}

	if ( '' !== trim( (string) $args['status'] ) ) {
		$where[]  = 'e.status = %s';
		$params[] = sanitize_key( $args['status'] );
	}

	if ( '' !== trim( (string) $args['priority'] ) ) {
		$where[]  = 'e.priority = %s';
		$params[] = sanitize_key( $args['priority'] );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 'e.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( ! empty( $args['teacher_user_id'] ) ) {
		$where[]  = 'e.teacher_user_id = %d';
		$params[] = absint( $args['teacher_user_id'] );
	}

	if ( '' !== trim( (string) $args['start'] ) && '' !== trim( (string) $args['end'] ) ) {
		$range = cmp_operations_range_sql( 'e', sanitize_text_field( $args['start'] ), sanitize_text_field( $args['end'] ) );
		$where = array_merge( $where, $range['where'] );
		$params = array_merge( $params, $range['params'] );
	}

	$sql = $query;

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY COALESCE(e.start_at, e.created_at) DESC, e.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Returns formatted calendar events for FullCalendar.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_calendar_events( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'start' => current_time( 'Y-m-01 00:00:00' ),
			'end'   => current_time( 'Y-m-t 23:59:59' ),
		)
	);

	$rows = cmp_get_operations_event_rows( $args );
	$events = array();

	foreach ( $rows as $row ) {
		$occurrences = cmp_operations_expand_event_occurrences( $row, sanitize_text_field( $args['start'] ), sanitize_text_field( $args['end'] ) );

		foreach ( $occurrences as $occurrence ) {
			$events[] = array(
				'id'            => (string) $occurrence->id,
				'title'         => sanitize_text_field( $occurrence->title ),
				'start'         => cmp_operations_to_iso( $occurrence->start_at ),
				'end'           => cmp_operations_to_iso( $occurrence->end_at ),
				'allDay'        => ! empty( $occurrence->all_day ),
				'editable'      => empty( $occurrence->is_recurring_instance ),
				'backgroundColor'=> cmp_operations_event_color( $occurrence->event_type ),
				'borderColor'   => cmp_operations_event_color( $occurrence->event_type ),
				'textColor'     => '#ffffff',
				'extendedProps' => array(
					'eventType'     => sanitize_key( $occurrence->event_type ),
					'status'        => sanitize_key( $occurrence->status ),
					'priority'      => sanitize_key( $occurrence->priority ),
					'batchId'       => (int) $occurrence->batch_id,
					'classId'       => (int) $occurrence->class_id,
					'teacherUserId' => (int) $occurrence->teacher_user_id,
					'assignedUserId'=> (int) $occurrence->assigned_user_id,
					'location'      => sanitize_text_field( $occurrence->location ),
					'notes'         => sanitize_textarea_field( $occurrence->notes ),
					'recurrenceRule'=> sanitize_text_field( $occurrence->recurrence_rule ),
					'batchName'     => sanitize_text_field( $occurrence->batch_name ),
					'className'     => sanitize_text_field( $occurrence->class_name ),
					'teacherName'   => sanitize_text_field( $occurrence->teacher_name ),
					'reminderAt'    => sanitize_text_field( $occurrence->reminder_at ),
					'reminderChannel'=> sanitize_text_field( $occurrence->reminder_channel ),
				),
			);
		}
	}

	return $events;
}

/**
 * Returns a dashboard snapshot for Operations Center.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_dashboard_snapshot( $args = array() ) {
	$cache_key = cmp_operations_cache_key( 'dashboard', $args );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$today_start = current_time( 'Y-m-d 00:00:00' );
	$today_end   = current_time( 'Y-m-d 23:59:59' );
	$tomorrow    = gmdate( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) );
	$tomorrow_start = $tomorrow . ' 00:00:00';
	$tomorrow_end   = $tomorrow . ' 23:59:59';
	$month_end   = gmdate( 'Y-m-d 23:59:59', strtotime( '+30 days', current_time( 'timestamp' ) ) );

	$event_args = array(
		'start' => $today_start,
		'end'   => $month_end,
		'limit' => 120,
	);
	if ( ! empty( $args['teacher_user_id'] ) ) {
		$event_args['teacher_user_id'] = absint( $args['teacher_user_id'] );
	}

	$events = cmp_get_operations_event_rows( $event_args );
	$today_classes = array();
	$tomorrow_classes = array();
	$teacher_schedule_today = array();
	$marketing_tasks_today = array();
	$pending_operations = array();
	$upcoming_holidays = array();

	foreach ( $events as $event ) {
		if ( cmp_operations_event_overlaps_range( $event, $today_start, $today_end ) && in_array( $event->event_type, array( 'class', 'batch' ), true ) ) {
			$today_classes[] = $event;
		}

		if ( cmp_operations_event_overlaps_range( $event, $tomorrow_start, $tomorrow_end ) && in_array( $event->event_type, array( 'class', 'batch' ), true ) ) {
			$tomorrow_classes[] = $event;
		}

		if ( cmp_operations_event_overlaps_range( $event, $today_start, $today_end ) && ! empty( $event->teacher_user_id ) ) {
			$teacher_schedule_today[] = $event;
		}

		if ( cmp_operations_event_overlaps_range( $event, $today_start, $today_end ) && 'marketing' === $event->event_type ) {
			$marketing_tasks_today[] = $event;
		}

		if ( cmp_operations_event_overlaps_range( $event, $today_start, $month_end ) && ! in_array( $event->status, array( 'completed', 'cancelled' ), true ) ) {
			$pending_operations[] = $event;
		}

		if ( 'holiday' === $event->event_type && cmp_operations_event_overlaps_range( $event, $today_start, $month_end ) ) {
			$upcoming_holidays[] = $event;
		}
	}

	$upcoming_batches = array_slice( cmp_get_batches_with_metrics(), 0, 8 );
	$goal_rows = cmp_get_operations_goal_rows( array( 'limit' => 8 ) );
	$recent_activities = array_slice( cmp_get_admin_logs( 12 ), 0, 8 );

	$total_target = 0;
	$total_current = 0;

	foreach ( $goal_rows as $goal ) {
		$total_target += (float) $goal->target_value;
		$total_current += (float) $goal->current_value;
	}

	$snapshot = array(
		'today_classes'         => $today_classes,
		'tomorrow_classes'      => $tomorrow_classes,
		'upcoming_batches'      => $upcoming_batches,
		'teacher_schedule_today'=> $teacher_schedule_today,
		'marketing_tasks_today' => $marketing_tasks_today,
		'pending_operations'    => $pending_operations,
		'upcoming_holidays'     => $upcoming_holidays,
		'business_goals'        => $goal_rows,
		'goal_completion'       => $total_target > 0 ? round( ( $total_current / $total_target ) * 100, 2 ) : 0,
		'recent_activities'     => $recent_activities,
		'counts'                => array(
			'today_classes'    => count( $today_classes ),
			'tomorrow_classes' => count( $tomorrow_classes ),
			'upcoming_batches' => count( $upcoming_batches ),
			'pending_ops'      => count( $pending_operations ),
			'holiday_count'    => count( $upcoming_holidays ),
			'goal_count'       => count( $goal_rows ),
		),
	);

	set_transient( $cache_key, $snapshot, 5 * MINUTE_IN_SECONDS );

	return $snapshot;
}

/**
 * Returns a batch schedule payload.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_batch_schedule_rows( $args = array() ) {
	$cache_key = cmp_operations_cache_key( 'batch_schedule', $args );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$batches = cmp_get_batches_with_metrics();
	$rows = array();

	foreach ( $batches as $batch ) {
		$event_count = 0;
		$doc_count   = 0;

		$events = cmp_get_operations_event_rows(
			array(
				'batch_id' => (int) $batch->id,
				'limit'    => 50,
			)
		);

		$docs = cmp_get_operations_document_rows(
			array(
				'batch_id' => (int) $batch->id,
				'limit'    => 50,
			)
		);

		$event_count = count( $events );
		$doc_count   = count( $docs );

		$batch->operation_events = $event_count;
		$batch->document_count   = $doc_count;
		$batch->meet_link        = ! empty( $batch->meet_link ) ? $batch->meet_link : '';
		$rows[] = $batch;
	}

	set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );

	return $rows;
}

/**
 * Returns a teacher schedule payload.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_teacher_schedule_rows( $args = array() ) {
	$teacher_id = absint( cmp_field( $args, 'teacher_user_id', 0 ) );

	if ( ! $teacher_id && ! current_user_can( 'manage_options' ) ) {
		$teacher_id = get_current_user_id();
	}

	$cache_key = cmp_operations_cache_key( 'teacher_schedule', array( 'teacher_user_id' => $teacher_id ) );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$batches = $teacher_id ? cmp_get_batches_for_teacher_user_id( $teacher_id ) : cmp_get_current_teacher_batches();
	$events  = cmp_get_operations_event_rows(
		array(
			'teacher_user_id' => $teacher_id,
			'limit'           => 80,
		)
	);
	$rows = array(
		'teacher_id'   => $teacher_id,
		'batches'      => $batches,
		'events_today' => array(),
		'upcoming'     => array(),
		'documents'    => array(),
	);

	$today_start = current_time( 'Y-m-d 00:00:00' );
	$today_end   = current_time( 'Y-m-d 23:59:59' );
	$future_end  = gmdate( 'Y-m-d 23:59:59', strtotime( '+30 days', current_time( 'timestamp' ) ) );

	foreach ( $events as $event ) {
		if ( cmp_operations_event_overlaps_range( $event, $today_start, $today_end ) ) {
			$rows['events_today'][] = $event;
		} elseif ( cmp_operations_event_overlaps_range( $event, $today_start, $future_end ) ) {
			$rows['upcoming'][] = $event;
		}
	}

	foreach ( $batches as $batch ) {
		$rows['documents'][ (int) $batch->id ] = cmp_get_operations_document_rows(
			array(
				'batch_id' => (int) $batch->id,
				'limit'    => 10,
			)
		);
	}

	set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );

	return $rows;
}

/**
 * Returns operations document rows.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_document_rows( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'     => '',
			'batch_id'   => 0,
			'class_id'   => 0,
			'limit'      => 0,
			'offset'     => 0,
		)
	);

	$query = 'SELECT d.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name
		FROM ' . cmp_operations_documents_table() . ' d
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = d.batch_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = d.class_id
		LEFT JOIN ' . $wpdb->users . ' u ON u.ID = d.created_by';
	$where = array();
	$params = array();
	$scope = cmp_operations_scope_sql( 'd', $args );
	$where = array_merge( $where, $scope['where'] );
	$params = array_merge( $params, $scope['params'] );

	if ( '' !== trim( (string) $args['search'] ) ) {
		$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		$where[] = '(d.title LIKE %s OR d.file_name LIKE %s OR d.notes LIKE %s OR b.batch_name LIKE %s OR c.name LIKE %s)';
		$params = array_merge( $params, array( $like, $like, $like, $like, $like ) );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 'd.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( ! empty( $args['class_id'] ) ) {
		$where[]  = 'd.class_id = %d';
		$params[] = absint( $args['class_id'] );
	}

	$sql = $query;

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY d.created_at DESC, d.version_number DESC, d.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Returns goal rows.
 *
 * @param array $args Query args.
 * @return array
 */
function cmp_get_operations_goal_rows( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'         => '',
			'goal_type'      => '',
			'period'         => '',
			'status'         => '',
			'batch_id'       => 0,
			'teacher_user_id'=> 0,
			'limit'          => 0,
			'offset'         => 0,
		)
	);

	$query = 'SELECT g.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name
		FROM ' . cmp_operations_goals_table() . ' g
		LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = g.batch_id
		LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
		LEFT JOIN ' . $wpdb->users . ' u ON u.ID = g.teacher_user_id';
	$where = array();
	$params = array();
	$scope = cmp_operations_scope_sql( 'g', $args );
	$where = array_merge( $where, $scope['where'] );
	$params = array_merge( $params, $scope['params'] );

	if ( '' !== trim( (string) $args['search'] ) ) {
		$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		$where[] = '(g.title LIKE %s OR g.notes LIKE %s OR b.batch_name LIKE %s OR c.name LIKE %s OR u.display_name LIKE %s)';
		$params = array_merge( $params, array( $like, $like, $like, $like, $like ) );
	}

	if ( '' !== trim( (string) $args['goal_type'] ) ) {
		$where[]  = 'g.goal_type = %s';
		$params[] = sanitize_key( $args['goal_type'] );
	}

	if ( '' !== trim( (string) $args['period'] ) ) {
		$where[]  = 'g.period = %s';
		$params[] = sanitize_key( $args['period'] );
	}

	if ( '' !== trim( (string) $args['status'] ) ) {
		$where[]  = 'g.status = %s';
		$params[] = sanitize_key( $args['status'] );
	}

	if ( ! empty( $args['batch_id'] ) ) {
		$where[]  = 'g.batch_id = %d';
		$params[] = absint( $args['batch_id'] );
	}

	if ( ! empty( $args['teacher_user_id'] ) ) {
		$where[]  = 'g.teacher_user_id = %d';
		$params[] = absint( $args['teacher_user_id'] );
	}

	$sql = $query;

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}

	$sql .= ' ORDER BY g.created_at DESC, g.id DESC';

	if ( $args['limit'] ) {
		$sql .= ' LIMIT ' . absint( $args['limit'] );

		if ( ! empty( $args['offset'] ) ) {
			$sql .= ' OFFSET ' . absint( $args['offset'] );
		}
	}

	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Returns a grouped search result payload.
 *
 * @param string $search Search term.
 * @return array
 */
function cmp_get_operations_search_results( $search ) {
	global $wpdb;

	$search = sanitize_text_field( $search );
	$cache_key = cmp_operations_cache_key( 'search', array( 'search' => $search ) );
	$cached = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$like = '%' . $wpdb->esc_like( $search ) . '%';
	$results = array(
		'events'    => array(),
		'batches'   => array(),
		'documents' => array(),
		'goals'     => array(),
		'teachers'  => array(),
	);

	if ( '' !== $search ) {
		$results['events'] = cmp_get_operations_event_rows(
			array(
				'search' => $search,
				'limit'  => 8,
			)
		);
		$results['documents'] = cmp_get_operations_document_rows(
			array(
				'search' => $search,
				'limit'  => 8,
			)
		);
		$results['goals'] = cmp_get_operations_goal_rows(
			array(
				'search' => $search,
				'limit'  => 8,
			)
		);
		$results['batches'] = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT b.id, b.batch_name, b.status, c.name AS class_name, b.start_date
				FROM ' . cmp_table( 'batches' ) . ' b
				LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
				WHERE b.batch_name LIKE %s OR c.name LIKE %s
				ORDER BY b.created_at DESC
				LIMIT 8',
				$like,
				$like
			)
		);
		$results['teachers'] = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT u.ID, u.display_name, u.user_email
				FROM ' . $wpdb->users . ' u
				WHERE u.display_name LIKE %s OR u.user_email LIKE %s
				ORDER BY u.display_name ASC
				LIMIT 8',
				$like,
				$like
			)
		);
	}

	set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

	return $results;
}

/**
 * Inserts or updates an operations event.
 *
 * @param array       $data Event data.
 * @param object|null $existing Existing row.
 * @return array|WP_Error
 */
function cmp_prepare_operations_event_payload( $data, $existing = null ) {
	$existing = is_object( $existing ) ? $existing : null;
	$batch_id  = absint( cmp_field( $data, 'batch_id', $existing ? (int) $existing->batch_id : 0 ) );
	$batch     = $batch_id ? cmp_get_batch( $batch_id ) : null;
	$event_type = cmp_clean_enum( cmp_field( $data, 'event_type', $existing ? $existing->event_type : 'other' ), array_keys( cmp_operations_event_types() ), 'other' );
	$status     = cmp_clean_enum( cmp_field( $data, 'status', $existing ? $existing->status : 'planned' ), array_keys( cmp_operations_event_statuses() ), 'planned' );
	$priority   = cmp_clean_enum( cmp_field( $data, 'priority', $existing ? $existing->priority : 'normal' ), array_keys( cmp_operations_event_priorities() ), 'normal' );
	$title      = sanitize_text_field( cmp_field( $data, 'title', $existing ? $existing->title : '' ) );
	$class_id   = absint( cmp_field( $data, 'class_id', $existing ? (int) $existing->class_id : ( $batch ? (int) $batch->class_id : 0 ) ) );
	$teacher_user_id = absint( cmp_field( $data, 'teacher_user_id', $existing ? (int) $existing->teacher_user_id : ( $batch ? (int) $batch->teacher_user_id : 0 ) ) );
	$assigned_user_id = absint( cmp_field( $data, 'assigned_user_id', $existing ? (int) $existing->assigned_user_id : $teacher_user_id ) );
	$all_day    = ! empty( cmp_field( $data, 'all_day', $existing ? (int) $existing->all_day : 0 ) ) ? 1 : 0;
	$start_at   = cmp_operations_normalize_datetime( cmp_field( $data, 'start_at', $existing ? $existing->start_at : '' ), $all_day );
	$end_at     = cmp_operations_normalize_datetime( cmp_field( $data, 'end_at', $existing ? $existing->end_at : '' ), $all_day );
	$location   = sanitize_text_field( cmp_field( $data, 'location', $existing ? $existing->location : '' ) );
	$meet_link  = esc_url_raw( cmp_field( $data, 'meet_link', $existing ? $existing->meet_link : '' ) );
	$notes      = sanitize_textarea_field( cmp_field( $data, 'notes', $existing ? $existing->notes : '' ) );
	$recurrence_freq = cmp_clean_enum( cmp_field( $data, 'recurrence_freq', 'none' ), array_keys( cmp_operations_recurrence_frequencies() ), 'none' );
	$recurrence_interval = max( 1, absint( cmp_field( $data, 'recurrence_interval', 1 ) ) );
	$recurrence_until = sanitize_text_field( cmp_field( $data, 'recurrence_until', '' ) );
	$color = sanitize_hex_color( cmp_field( $data, 'color', cmp_operations_event_color( $event_type ) ) );
	$reminder_at = cmp_operations_normalize_datetime( cmp_field( $data, 'reminder_at', $existing ? $existing->reminder_at : '' ), false );
	$reminder_channel = cmp_clean_enum( cmp_field( $data, 'reminder_channel', $existing ? $existing->reminder_channel : 'email' ), array( 'email', 'whatsapp', 'admin' ), 'email' );
	$source_type = sanitize_key( cmp_field( $data, 'source_type', $existing ? $existing->source_type : 'manual' ) );
	$source_id   = absint( cmp_field( $data, 'source_id', $existing ? (int) $existing->source_id : 0 ) );

	if ( '' === $title ) {
		return new WP_Error( 'cmp_operations_invalid_title', __( 'A title is required.', 'class-manager-pro' ) );
	}

	if ( $batch && ! $class_id ) {
		$class_id = (int) $batch->class_id;
	}

	$recurrence_rule = wp_json_encode(
		array(
			'freq'     => $recurrence_freq,
			'interval' => $recurrence_interval,
			'count'    => absint( cmp_field( $data, 'recurrence_count', 0 ) ),
			'until'    => $recurrence_until,
		)
	);

	if ( ! $color ) {
		$color = cmp_operations_event_color( $event_type );
	}

	return array(
		'title'           => $title,
		'event_type'      => $event_type,
		'status'          => $status,
		'priority'        => $priority,
		'source_type'     => $source_type,
		'source_id'       => $source_id,
		'class_id'        => $class_id,
		'batch_id'        => $batch_id,
		'teacher_user_id' => $teacher_user_id,
		'assigned_user_id'=> $assigned_user_id,
		'start_at'        => '' !== $start_at ? $start_at : null,
		'end_at'          => '' !== $end_at ? $end_at : null,
		'all_day'         => $all_day,
		'recurrence_rule' => $recurrence_rule,
		'location'        => $location,
		'meet_link'       => $meet_link,
		'notes'           => $notes,
		'color'           => $color,
		'reminder_at'     => '' !== $reminder_at ? $reminder_at : null,
		'reminder_channel'=> $reminder_channel,
		'updated_by'      => get_current_user_id(),
	);
}

/**
 * Saves an operations event.
 *
 * @param array $data Event data.
 * @return array|WP_Error
 */
function cmp_save_operations_event_record( $data ) {
	global $wpdb;

	$id = absint( cmp_field( $data, 'id', 0 ) );
	$existing = $id ? cmp_get_operations_event( $id ) : null;
	$payload = cmp_prepare_operations_event_payload( $data, $existing );

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$payload['created_by'] = $existing ? (int) $existing->created_by : get_current_user_id();
	$payload['created_at']  = $existing ? $existing->created_at : cmp_current_datetime();
	$payload['updated_at']  = cmp_current_datetime();

	if ( $id ) {
		$result = $wpdb->update(
			cmp_operations_events_table(),
			$payload,
			array( 'id' => $id )
		);
	} else {
		$result = $wpdb->insert(
			cmp_operations_events_table(),
			$payload
		);
	}

	if ( false === $result ) {
		return new WP_Error( 'cmp_operations_event_failed', __( 'Could not save event.', 'class-manager-pro' ) );
	}

	$event_id = $id ? $id : (int) $wpdb->insert_id;
	$event = cmp_get_operations_event( $event_id );
	cmp_operations_bump_cache_version();
	cmp_log_admin_action( $id ? 'edit_operations_event' : 'add_operations_event', 'operations_event', $event_id, $event ? $event->title : __( 'Operations event saved.', 'class-manager-pro' ) );

	if ( $event && 'batch' === $event->source_type && $event->source_id > 0 ) {
		cmp_sync_operations_batch_event( (int) $event->source_id, 'save' );
	}

	return array(
		'id'      => $event_id,
		'event'   => $event,
		'message' => $id ? __( 'Event updated successfully.', 'class-manager-pro' ) : __( 'Event created successfully.', 'class-manager-pro' ),
	);
}

/**
 * Deletes an operations event.
 *
 * @param int $id Event ID.
 * @return true|WP_Error
 */
function cmp_delete_operations_event_record( $id ) {
	global $wpdb;

	$id = absint( $id );
	$event = cmp_get_operations_event( $id );

	if ( ! $id || ! $event ) {
		return new WP_Error( 'cmp_operations_event_missing', __( 'Event not found.', 'class-manager-pro' ) );
	}

	$deleted = $wpdb->delete( cmp_operations_events_table(), array( 'id' => $id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_operations_event_failed', __( 'Could not delete event.', 'class-manager-pro' ) );
	}

	cmp_operations_bump_cache_version();
	cmp_log_admin_action( 'delete_operations_event', 'operations_event', $id, $event->title );

	if ( 'batch' === $event->source_type && $event->source_id > 0 ) {
		cmp_sync_operations_batch_event( (int) $event->source_id, 'delete' );
	}

	return true;
}

/**
 * Fetches a single operations event.
 *
 * @param int $id Event ID.
 * @return object|null
 */
function cmp_get_operations_event( $id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT e.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name
			FROM ' . cmp_operations_events_table() . ' e
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = e.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = e.class_id
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.teacher_user_id
			WHERE e.id = %d',
			absint( $id )
		)
	);
}

/**
 * Returns the current batch calendar event for a batch.
 *
 * @param int $batch_id Batch ID.
 * @return object|null
 */
function cmp_get_operations_batch_event( $batch_id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . cmp_operations_events_table() . ' WHERE source_type = %s AND source_id = %d AND event_type = %s ORDER BY id DESC LIMIT 1',
			'batch',
			absint( $batch_id ),
			'batch'
		)
	);
}

/**
 * Syncs a batch event into Operations Center.
 *
 * @param int    $batch_id Batch ID.
 * @param string $mode save|delete.
 * @return void
 */
function cmp_sync_operations_batch_event( $batch_id, $mode = 'save' ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $batch_id ) {
		return;
	}

	if ( 'delete' === $mode || ! $batch ) {
		$wpdb->delete(
			cmp_operations_events_table(),
			array(
				'source_type' => 'batch',
				'source_id'   => $batch_id,
				'event_type'  => 'batch',
			),
			array( '%s', '%d', '%s' )
		);
		cmp_operations_bump_cache_version();
		return;
	}

	$start_at = '';
	if ( ! empty( $batch->start_date ) ) {
		$start_at = $batch->start_date . ' 09:00:00';
	}

	$payload = array(
		'title'           => sprintf( '%s - %s', $batch->class_name ? $batch->class_name : __( 'Batch', 'class-manager-pro' ), $batch->batch_name ),
		'event_type'      => 'batch',
		'status'          => 'completed' === $batch->status ? 'completed' : 'scheduled',
		'priority'        => 'normal',
		'source_type'     => 'batch',
		'source_id'       => $batch_id,
		'class_id'        => (int) $batch->class_id,
		'batch_id'        => $batch_id,
		'teacher_user_id' => (int) $batch->teacher_user_id,
		'assigned_user_id'=> (int) $batch->teacher_user_id,
		'start_at'        => '' !== $start_at ? $start_at : null,
		'end_at'          => null,
		'all_day'         => 1,
		'recurrence_rule' => wp_json_encode( array( 'freq' => 'none', 'interval' => 1, 'count' => 0, 'until' => '' ) ),
		'location'        => '',
		'meet_link'       => '',
		'notes'           => sprintf( '%s: %s', __( 'Auto-generated from batch', 'class-manager-pro' ), $batch->batch_name ),
		'color'           => cmp_operations_event_color( 'batch' ),
		'reminder_at'     => ! empty( $batch->fee_due_date ) ? $batch->fee_due_date . ' 09:00:00' : null,
		'reminder_channel'=> 'email',
		'created_by'      => get_current_user_id(),
		'updated_by'      => get_current_user_id(),
		'created_at'      => cmp_current_datetime(),
		'updated_at'      => cmp_current_datetime(),
	);

	$existing = cmp_get_operations_batch_event( $batch_id );

	if ( $existing ) {
		$wpdb->update(
			cmp_operations_events_table(),
			$payload,
			array( 'id' => (int) $existing->id )
		);
	} else {
		$wpdb->insert(
			cmp_operations_events_table(),
			$payload
		);
	}

	cmp_operations_bump_cache_version();
}

/**
 * Inserts or updates a document version.
 *
 * @param array       $data Document payload.
 * @param object|null $existing Existing document row.
 * @return array|WP_Error
 */
function cmp_prepare_operations_document_payload( $data, $existing = null ) {
	$batch_id = absint( cmp_field( $data, 'batch_id', $existing ? (int) $existing->batch_id : 0 ) );
	$batch    = $batch_id ? cmp_get_batch( $batch_id ) : null;
	$title    = sanitize_text_field( cmp_field( $data, 'title', $existing ? $existing->title : '' ) );
	$document_type = cmp_clean_enum( cmp_field( $data, 'document_type', $existing ? $existing->document_type : 'other' ), array_keys( cmp_operations_document_types() ), 'other' );
	$notes    = sanitize_textarea_field( cmp_field( $data, 'notes', $existing ? $existing->notes : '' ) );
	$is_teacher_visible = ! empty( cmp_field( $data, 'is_teacher_visible', $existing ? (int) $existing->is_teacher_visible : 1 ) ) ? 1 : 0;
	$file_url  = esc_url_raw( cmp_field( $data, 'file_url', $existing ? $existing->file_url : '' ) );
	$file_name = sanitize_text_field( cmp_field( $data, 'file_name', $existing ? $existing->file_name : '' ) );
	$file_mime = sanitize_text_field( cmp_field( $data, 'file_mime', $existing ? $existing->file_mime : '' ) );
	$version_number = $existing ? max( 1, (int) $existing->version_number + 1 ) : max( 1, absint( cmp_field( $data, 'version_number', 1 ) ) );
	$parent_document_id = $existing ? (int) ( ! empty( $existing->parent_document_id ) ? $existing->parent_document_id : $existing->id ) : absint( cmp_field( $data, 'parent_document_id', 0 ) );

	if ( '' === $title ) {
		return new WP_Error( 'cmp_operations_document_invalid', __( 'Document title is required.', 'class-manager-pro' ) );
	}

	if ( ! $batch ) {
		return new WP_Error( 'cmp_operations_document_invalid', __( 'A valid batch is required for each document.', 'class-manager-pro' ) );
	}

	if ( isset( $_FILES['document_file'] ) && ! empty( $_FILES['document_file']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload(
			$_FILES['document_file'],
			array( 'test_form' => false )
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'cmp_operations_document_upload_failed', $upload['error'] );
		}

		$file_url  = isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : $file_url;
		$file_name = isset( $upload['file'] ) ? sanitize_text_field( wp_basename( $upload['file'] ) ) : $file_name;
		$file_mime = isset( $upload['type'] ) ? sanitize_text_field( $upload['type'] ) : $file_mime;
	}

	return array(
		'batch_id'           => $batch_id,
		'class_id'           => (int) $batch->class_id,
		'document_type'      => $document_type,
		'title'              => $title,
		'file_url'           => $file_url,
		'file_name'          => $file_name,
		'file_mime'          => $file_mime,
		'version_number'     => $version_number,
		'parent_document_id' => $parent_document_id,
		'notes'              => $notes,
		'is_teacher_visible' => $is_teacher_visible,
		'updated_by'         => get_current_user_id(),
	);
}

/**
 * Saves a document version row.
 *
 * @param array $data Document data.
 * @return array|WP_Error
 */
function cmp_save_operations_document_record( $data ) {
	global $wpdb;

	$id = absint( cmp_field( $data, 'id', 0 ) );
	$existing = $id ? cmp_get_operations_document( $id ) : null;
	$payload = cmp_prepare_operations_document_payload( $data, $existing );

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$payload['created_by'] = $existing ? (int) $existing->created_by : get_current_user_id();
	$payload['created_at']  = $existing ? $existing->created_at : cmp_current_datetime();
	$payload['updated_at']  = cmp_current_datetime();

	$result = $wpdb->insert(
		cmp_operations_documents_table(),
		$payload
	);

	if ( false === $result ) {
		return new WP_Error( 'cmp_operations_document_failed', __( 'Could not save document.', 'class-manager-pro' ) );
	}

	$document_id = (int) $wpdb->insert_id;
	cmp_operations_bump_cache_version();
	cmp_log_admin_action( 'add_operations_document', 'operations_document', $document_id, $payload['title'] );

	return array(
		'id'      => $document_id,
		'message' => $existing ? __( 'Document version added successfully.', 'class-manager-pro' ) : __( 'Document saved successfully.', 'class-manager-pro' ),
	);
}

/**
 * Fetches a single operations document.
 *
 * @param int $id Document ID.
 * @return object|null
 */
function cmp_get_operations_document( $id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT d.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name
			FROM ' . cmp_operations_documents_table() . ' d
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = d.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = d.class_id
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = d.created_by
			WHERE d.id = %d',
			absint( $id )
		)
	);
}

/**
 * Deletes an operations document row.
 *
 * @param int $id Document ID.
 * @return true|WP_Error
 */
function cmp_delete_operations_document_record( $id ) {
	global $wpdb;

	$id = absint( $id );
	$document = cmp_get_operations_document( $id );

	if ( ! $id || ! $document ) {
		return new WP_Error( 'cmp_operations_document_missing', __( 'Document not found.', 'class-manager-pro' ) );
	}

	$deleted = $wpdb->delete( cmp_operations_documents_table(), array( 'id' => $id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_operations_document_failed', __( 'Could not delete document.', 'class-manager-pro' ) );
	}

	cmp_operations_bump_cache_version();
	cmp_log_admin_action( 'delete_operations_document', 'operations_document', $id, $document->title );

	return true;
}

/**
 * Inserts or updates a goal row.
 *
 * @param array       $data Goal payload.
 * @param object|null $existing Existing row.
 * @return array|WP_Error
 */
function cmp_prepare_operations_goal_payload( $data, $existing = null ) {
	$existing = is_object( $existing ) ? $existing : null;
	$goal_type = cmp_clean_enum( cmp_field( $data, 'goal_type', $existing ? $existing->goal_type : 'other' ), array_keys( cmp_operations_goal_types() ), 'other' );
	$period    = cmp_clean_enum( cmp_field( $data, 'period', $existing ? $existing->period : 'monthly' ), array( 'monthly', 'quarterly', 'yearly' ), 'monthly' );
	$title     = sanitize_text_field( cmp_field( $data, 'title', $existing ? $existing->title : '' ) );
	$target    = cmp_money_value( cmp_field( $data, 'target_value', $existing ? $existing->target_value : 0 ) );
	$current   = cmp_money_value( cmp_field( $data, 'current_value', $existing ? $existing->current_value : 0 ) );
	$start_date = sanitize_text_field( cmp_field( $data, 'start_date', $existing ? $existing->start_date : '' ) );
	$end_date   = sanitize_text_field( cmp_field( $data, 'end_date', $existing ? $existing->end_date : '' ) );
	$status     = cmp_clean_enum( cmp_field( $data, 'status', $existing ? $existing->status : 'active' ), array( 'active', 'paused', 'completed', 'cancelled' ), 'active' );
	$priority   = cmp_clean_enum( cmp_field( $data, 'priority', $existing ? $existing->priority : 'normal' ), array_keys( cmp_operations_event_priorities() ), 'normal' );
	$batch_id   = absint( cmp_field( $data, 'batch_id', $existing ? (int) $existing->batch_id : 0 ) );
	$teacher_user_id = absint( cmp_field( $data, 'teacher_user_id', $existing ? (int) $existing->teacher_user_id : 0 ) );
	$notes      = sanitize_textarea_field( cmp_field( $data, 'notes', $existing ? $existing->notes : '' ) );

	if ( '' === $title ) {
		return new WP_Error( 'cmp_operations_goal_invalid', __( 'Goal title is required.', 'class-manager-pro' ) );
	}

	return array(
		'goal_type'      => $goal_type,
		'period'         => $period,
		'title'          => $title,
		'target_value'   => $target,
		'current_value'  => $current,
		'start_date'     => '' !== $start_date ? $start_date : null,
		'end_date'       => '' !== $end_date ? $end_date : null,
		'status'         => $status,
		'priority'       => $priority,
		'batch_id'       => $batch_id,
		'teacher_user_id'=> $teacher_user_id,
		'notes'          => $notes,
		'updated_by'     => get_current_user_id(),
	);
}

/**
 * Saves a goal record.
 *
 * @param array $data Goal data.
 * @return array|WP_Error
 */
function cmp_save_operations_goal_record( $data ) {
	global $wpdb;

	$id = absint( cmp_field( $data, 'id', 0 ) );
	$existing = $id ? cmp_get_operations_goal( $id ) : null;
	$payload = cmp_prepare_operations_goal_payload( $data, $existing );

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$payload['created_by'] = $existing ? (int) $existing->created_by : get_current_user_id();
	$payload['created_at']  = $existing ? $existing->created_at : cmp_current_datetime();
	$payload['updated_at']  = cmp_current_datetime();

	if ( $id ) {
		$result = $wpdb->update(
			cmp_operations_goals_table(),
			$payload,
			array( 'id' => $id )
		);
	} else {
		$result = $wpdb->insert(
			cmp_operations_goals_table(),
			$payload
		);
	}

	if ( false === $result ) {
		return new WP_Error( 'cmp_operations_goal_failed', __( 'Could not save goal.', 'class-manager-pro' ) );
	}

	$goal_id = $id ? $id : (int) $wpdb->insert_id;
	cmp_operations_bump_cache_version();
	cmp_log_admin_action( $id ? 'edit_operations_goal' : 'add_operations_goal', 'operations_goal', $goal_id, $payload['title'] );

	return array(
		'id'      => $goal_id,
		'message' => $id ? __( 'Goal updated successfully.', 'class-manager-pro' ) : __( 'Goal created successfully.', 'class-manager-pro' ),
	);
}

/**
 * Fetches a single operations goal.
 *
 * @param int $id Goal ID.
 * @return object|null
 */
function cmp_get_operations_goal( $id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT g.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name
			FROM ' . cmp_operations_goals_table() . ' g
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = g.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = b.class_id
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = g.teacher_user_id
			WHERE g.id = %d',
			absint( $id )
		)
	);
}

/**
 * Deletes an operations goal.
 *
 * @param int $id Goal ID.
 * @return true|WP_Error
 */
function cmp_delete_operations_goal_record( $id ) {
	global $wpdb;

	$id = absint( $id );
	$goal = cmp_get_operations_goal( $id );

	if ( ! $id || ! $goal ) {
		return new WP_Error( 'cmp_operations_goal_missing', __( 'Goal not found.', 'class-manager-pro' ) );
	}

	$deleted = $wpdb->delete( cmp_operations_goals_table(), array( 'id' => $id ), array( '%d' ) );

	if ( false === $deleted ) {
		return new WP_Error( 'cmp_operations_goal_failed', __( 'Could not delete goal.', 'class-manager-pro' ) );
	}

	cmp_operations_bump_cache_version();
	cmp_log_admin_action( 'delete_operations_goal', 'operations_goal', $id, $goal->title );

	return true;
}

/**
 * Returns a goal progress percentage.
 *
 * @param object $goal Goal row.
 * @return float
 */
function cmp_operations_goal_progress( $goal ) {
	if ( ! $goal || empty( $goal->target_value ) ) {
		return 0;
	}

	return round( max( 0, ( (float) $goal->current_value / (float) $goal->target_value ) * 100 ), 2 );
}

/**
 * Generates an ICS calendar string.
 *
 * @param array $args Query args.
 * @return string
 */
function cmp_generate_operations_ics( $args = array() ) {
	$events = cmp_get_operations_calendar_events( $args );
	$settings = cmp_get_operations_center_settings();
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Class Manager Pro//Operations Center//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . sanitize_text_field( cmp_field( $settings, 'ics_feed_title', 'Class Manager Pro Operations Calendar' ) ),
	);

	foreach ( $events as $event ) {
		$uid = md5( (string) $event['id'] . '|' . $event['title'] . '|' . $event['start'] );
		$summary = str_replace( array( "\r", "\n" ), ' ', sanitize_text_field( $event['title'] ) );
		$start = gmdate( 'Ymd\THis\Z', strtotime( str_replace( 'T', ' ', $event['start'] ) ) );
		$end_value = ! empty( $event['end'] ) ? $event['end'] : $event['start'];
		$end = gmdate( 'Ymd\THis\Z', strtotime( str_replace( 'T', ' ', $end_value ) ) );
		$description = isset( $event['extendedProps']['notes'] ) ? sanitize_textarea_field( $event['extendedProps']['notes'] ) : '';
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $uid . '@class-manager-pro';
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
		$lines[] = 'SUMMARY:' . $summary;
		$lines[] = 'DTSTART:' . $start;
		$lines[] = 'DTEND:' . $end;
		if ( '' !== $description ) {
			$lines[] = 'DESCRIPTION:' . str_replace( array( "\r", "\n" ), ' ', $description );
		}
		$lines[] = 'END:VEVENT';
	}

	$lines[] = 'END:VCALENDAR';

	return implode( "\r\n", $lines ) . "\r\n";
}

/**
 * Processes due reminders.
 *
 * @return void
 */
function cmp_process_operations_center_reminders() {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT e.*, b.batch_name, c.name AS class_name, u.display_name AS teacher_name, u.user_email AS teacher_email
			FROM ' . cmp_operations_events_table() . ' e
			LEFT JOIN ' . cmp_table( 'batches' ) . ' b ON b.id = e.batch_id
			LEFT JOIN ' . cmp_table( 'classes' ) . ' c ON c.id = e.class_id
			LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.teacher_user_id
			WHERE e.event_type = %s AND e.reminder_at IS NOT NULL AND e.reminder_at <= %s AND (e.reminder_sent_at IS NULL OR e.reminder_sent_at = %s) AND e.status <> %s
			ORDER BY e.reminder_at ASC, e.id ASC
			LIMIT 50',
			'reminder',
			cmp_current_datetime(),
			'0000-00-00 00:00:00',
			'cancelled'
		)
	);

	foreach ( $rows as $row ) {
		$recipient = null;

		if ( ! empty( $row->teacher_user_id ) ) {
			$recipient = get_userdata( (int) $row->teacher_user_id );
		}

		if ( ! $recipient && ! empty( $row->assigned_user_id ) ) {
			$recipient = get_userdata( (int) $row->assigned_user_id );
		}

		if ( ! $recipient ) {
			$recipient = (object) array(
				'name'  => __( 'Admin', 'class-manager-pro' ),
				'phone' => '',
				'email' => get_option( 'admin_email' ),
			);
		}

		$message = ! empty( $row->notes ) ? $row->notes : sprintf( __( 'Reminder for %s', 'class-manager-pro' ), $row->title );
		$settings = cmp_get_operations_center_settings();
		$result = cmp_send_generic_notification_message(
			$recipient,
			'email',
			! empty( $settings['reminder_subject'] ) ? $settings['reminder_subject'] : __( 'Operations Center reminder', 'class-manager-pro' ),
			$message,
			array(
				'email_format' => 'plain',
			)
		);

		$wpdb->update(
			cmp_operations_events_table(),
			array(
				'reminder_sent_at' => cmp_current_datetime(),
				'status'           => ! empty( $result['success'] ) ? 'completed' : 'planned',
				'updated_at'       => cmp_current_datetime(),
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		cmp_log_admin_action(
			'process_operations_reminder',
			'operations_event',
			(int) $row->id,
			! empty( $result['message'] ) ? $result['message'] : $message
		);
	}

	cmp_operations_bump_cache_version();
}

/**
 * Returns HTML for a search result item.
 *
 * @param object $row Row object.
 * @param string $type Result type.
 * @return string
 */
function cmp_operations_search_item_label( $row, $type ) {
	$type = sanitize_key( $type );

	if ( 'events' === $type ) {
		return sprintf( '%1$s - %2$s', cmp_operations_event_type_label( $row->event_type ), $row->title );
	}

	if ( 'documents' === $type ) {
		return sprintf( '%1$s - %2$s', $row->batch_name ? $row->batch_name : __( 'Document', 'class-manager-pro' ), $row->title );
	}

	if ( 'goals' === $type ) {
		return sprintf( '%1$s - %2$s', cmp_operations_goal_types()[ $row->goal_type ] ?? __( 'Goal', 'class-manager-pro' ), $row->title );
	}

	if ( 'batches' === $type ) {
		return sprintf( '%1$s - %2$s', $row->class_name ? $row->class_name : __( 'Batch', 'class-manager-pro' ), $row->batch_name );
	}

	if ( 'teachers' === $type ) {
		return sprintf( '%1$s - %2$s', __( 'Teacher', 'class-manager-pro' ), $row->display_name );
	}

	return '';
}
