<?php
/**
 * Class CMP_Health_Check
 *
 * Data integrity scanner and repair tool.
 *
 * @package ClassManagerPro
 */
class CMP_Health_Check {

	/** @var string Transient key for last scan results */
	const RESULTS_KEY = 'cmp_health_check_results';

	/** @var string Cron hook */
	const CRON_HOOK = 'cmp_health_check_scan';

	/**
	 * Initialize health check system.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scan' ) );
		add_action( 'wp_ajax_cmp_run_health_check', array( __CLASS__, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_cmp_repair_health_issue', array( __CLASS__, 'ajax_repair_issue' ) );
	}

	/**
	 * Schedule periodic scans.
	 */
	public static function schedule_scan() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule scans.
	 */
	public static function unschedule_scan() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run a complete health scan.
	 *
	 * @return array
	 */
	public static function run_scan() {
		$issues = array();

		$issues = array_merge( $issues, self::check_orphaned_payments() );
		$issues = array_merge( $issues, self::check_orphaned_students() );
		$issues = array_merge( $issues, self::check_orphaned_attendance() );
		$issues = array_merge( $issues, self::check_orphaned_expenses() );
		$issues = array_merge( $issues, self::check_duplicate_students() );
		$issues = array_merge( $issues, self::check_invalid_fee_amounts() );
		$issues = array_merge( $issues, self::check_missing_unique_ids() );
		$issues = array_merge( $issues, self::check_expired_tokens() );
		$issues = array_merge( $issues, self::check_database_integrity() );
		$issues = array_merge( $issues, self::check_performance_bottlenecks() );

		$results = array(
			'timestamp' => current_time( 'mysql' ),
			'total'     => count( $issues ),
			'critical'  => count( array_filter( $issues, function( $i ) { return 'critical' === $i['severity']; } ) ),
			'warning'   => count( array_filter( $issues, function( $i ) { return 'warning' === $i['severity']; } ) ),
			'info'      => count( array_filter( $issues, function( $i ) { return 'info' === $i['severity']; } ) ),
			'issues'    => $issues,
		);

		set_transient( self::RESULTS_KEY, $results, WEEK_IN_SECONDS );

		return $results;
	}

	/**
	 * Get cached scan results.
	 *
	 * @return array
	 */
	public static function get_results() {
		$results = get_transient( self::RESULTS_KEY );
		if ( false === $results ) {
			return self::run_scan();
		}
		return $results;
	}

	/**
	 * Check for payments linked to non-existent students.
	 */
	public static function check_orphaned_payments() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$orphans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.student_id FROM {$prefix}payments p
				 LEFT JOIN {$prefix}students s ON p.student_id = s.id
				 WHERE s.id IS NULL AND p.is_deleted = 0
				 LIMIT %d",
				100
			)
		);

		$issues = array();
		foreach ( $orphans as $orphan ) {
			$issues[] = array(
				'type'     => 'orphaned_payment',
				'id'       => $orphan->id,
				'severity' => 'warning',
				'message'  => sprintf( 'Payment #%d references non-existent student #%d', $orphan->id, $orphan->student_id ),
				'action'   => 'relink_or_trash',
				'details'  => array( 'payment_id' => $orphan->id, 'student_id' => $orphan->student_id ),
			);
		}

		return $issues;
	}

	/**
	 * Check for students assigned to non-existent batches.
	 */
	public static function check_orphaned_students() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$orphans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.batch_id FROM {$prefix}students s
				 LEFT JOIN {$prefix}batches b ON s.batch_id = b.id
				 WHERE b.id IS NULL AND s.batch_id > 0
				 LIMIT %d",
				100
			)
		);

		$issues = array();
		foreach ( $orphans as $orphan ) {
			$issues[] = array(
				'type'     => 'orphaned_student',
				'id'       => $orphan->id,
				'severity' => 'warning',
				'message'  => sprintf( 'Student #%d is assigned to non-existent batch #%d', $orphan->id, $orphan->batch_id ),
				'action'   => 'reassign_batch',
				'details'  => array( 'student_id' => $orphan->id, 'batch_id' => $orphan->batch_id ),
			);
		}

		return $issues;
	}

	/**
	 * Check for attendance records with invalid references.
	 */
	public static function check_orphaned_attendance() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$orphans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id FROM {$prefix}attendance a
				 LEFT JOIN {$prefix}students s ON a.student_id = s.id
				 LEFT JOIN {$prefix}batches b ON a.batch_id = b.id
				 WHERE (s.id IS NULL OR b.id IS NULL)
				 LIMIT %d",
				100
			)
		);

		$issues = array();
		foreach ( $orphans as $orphan ) {
			$issues[] = array(
				'type'     => 'orphaned_attendance',
				'id'       => $orphan->id,
				'severity' => 'info',
				'message'  => sprintf( 'Attendance record #%d has invalid references', $orphan->id ),
				'action'   => 'delete',
				'details'  => array( 'attendance_id' => $orphan->id ),
			);
		}

		return $issues;
	}

	/**
	 * Check for expenses linked to deleted batches.
	 */
	public static function check_orphaned_expenses() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$orphans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.id, e.batch_id FROM {$prefix}expenses e
				 LEFT JOIN {$prefix}batches b ON e.batch_id = b.id
				 WHERE b.id IS NULL
				 LIMIT %d",
				100
			)
		);

		$issues = array();
		foreach ( $orphans as $orphan ) {
			$issues[] = array(
				'type'     => 'orphaned_expense',
				'id'       => $orphan->id,
				'severity' => 'info',
				'message'  => sprintf( 'Expense #%d is linked to non-existent batch #%d', $orphan->id, $orphan->batch_id ),
				'action'   => 'relink_or_delete',
				'details'  => array( 'expense_id' => $orphan->id ),
			);
		}

		return $issues;
	}

	/**
	 * Check for duplicate students by phone + email.
	 */
	public static function check_duplicate_students() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$duplicates = $wpdb->get_results(
			"SELECT phone, email, COUNT(*) as count, GROUP_CONCAT(id) as ids
			 FROM {$prefix}students
			 WHERE phone != '' OR email != ''
			 GROUP BY phone, email
			 HAVING count > 1
			 LIMIT 50"
		);

		$issues = array();
		foreach ( $duplicates as $dup ) {
			$issues[] = array(
				'type'     => 'duplicate_student',
				'id'       => $dup->ids,
				'severity' => 'warning',
				'message'  => sprintf( 'Duplicate students found: %d records with phone "%s" and email "%s"', $dup->count, $dup->phone, $dup->email ),
				'action'   => 'merge',
				'details'  => array( 'ids' => explode( ',', $dup->ids ), 'phone' => $dup->phone, 'email' => $dup->email ),
			);
		}

		return $issues;
	}

	/**
	 * Check for invalid fee amounts.
	 */
	public static function check_invalid_fee_amounts() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$invalid = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, total_fee, paid_fee FROM {$prefix}students
				 WHERE total_fee < 0 OR paid_fee < 0 OR paid_fee > total_fee * 1.5
				 LIMIT %d",
				100
			)
		);

		$issues = array();
		foreach ( $invalid as $inv ) {
			$issues[] = array(
				'type'     => 'invalid_fee',
				'id'       => $inv->id,
				'severity' => 'critical',
				'message'  => sprintf( 'Student #%d has invalid fee amounts (total: %s, paid: %s)', $inv->id, $inv->total_fee, $inv->paid_fee ),
				'action'   => 'fix_amount',
				'details'  => array( 'student_id' => $inv->id, 'total_fee' => $inv->total_fee, 'paid_fee' => $inv->paid_fee ),
			);
		}

		return $issues;
	}

	/**
	 * Check for students missing unique IDs.
	 */
	public static function check_missing_unique_ids() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$missing = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}students
				 WHERE unique_id IS NULL OR unique_id = ''
				 LIMIT %d",
				100
			)
		);

		$issues = array();
		foreach ( $missing as $m ) {
			$issues[] = array(
				'type'     => 'missing_unique_id',
				'id'       => $m->id,
				'severity' => 'critical',
				'message'  => sprintf( 'Student #%d is missing a unique ID', $m->id ),
				'action'   => 'generate_id',
				'details'  => array( 'student_id' => $m->id ),
			);
		}

		return $issues;
	}

	/**
	 * Check for expired registration tokens.
	 */
	public static function check_expired_tokens() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$expired = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}registration_tokens
				 WHERE expires_at < NOW() - INTERVAL %d DAY",
				7
			)
		);

		$issues = array();
		if ( $expired > 0 ) {
			$issues[] = array(
				'type'     => 'expired_tokens',
				'id'       => 0,
				'severity' => 'info',
				'message'  => sprintf( '%d expired registration tokens can be cleaned up', $expired ),
				'action'   => 'cleanup_tokens',
				'details'  => array( 'count' => $expired ),
			);
		}

		return $issues;
	}

	/**
	 * Check database table integrity.
	 */
	public static function check_database_integrity() {
		global $wpdb;
		$tables = array( 'students', 'classes', 'batches', 'payments', 'attendance', 'expenses', 'announcements', 'registration_tokens', 'teacher_logs', 'admin_logs', 'interested_students', 'razorpay_sync_state', 'payment_audit' );
		$prefix = $wpdb->prefix . 'cmp_';
		$issues = array();

		foreach ( $tables as $table ) {
			$result = $wpdb->get_var( "CHECK TABLE {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 'OK' !== $result ) {
				$issues[] = array(
					'type'     => 'db_integrity',
					'id'       => $table,
					'severity' => 'critical',
					'message'  => sprintf( 'Table %s failed integrity check: %s', $table, $result ),
					'action'   => 'repair_table',
					'details'  => array( 'table' => $prefix . $table, 'result' => $result ),
				);
			}
		}

		return $issues;
	}

	/**
	 * Check for performance bottlenecks.
	 */
	public static function check_performance_bottlenecks() {
		$issues = array();

		// Check if caching is working.
		$stats = CMP_Cache::get_stats();
		if ( 0 === $stats['total'] ) {
			$issues[] = array(
				'type'     => 'cache_inactive',
				'id'       => 0,
				'severity' => 'warning',
				'message'  => 'Object cache is not active. Install a persistent object cache (Redis/Memcached) for best performance.',
				'action'   => 'none',
				'details'  => array(),
			);
		}

		return $issues;
	}

	/**
	 * AJAX handler to run scan.
	 */
	public static function ajax_run_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$results = self::run_scan();
		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler to repair an issue.
	 */
	public static function ajax_repair_issue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$issue_type = sanitize_key( $_POST['issue_type'] ?? '' );
		$details    = map_deep( $_POST['details'] ?? array(), 'sanitize_text_field' );

		$result = self::repair_issue( $issue_type, $details );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Re-run scan to update results.
		self::run_scan();
		wp_send_json_success( array( 'message' => __( 'Issue repaired successfully.', 'class-manager-pro' ) ) );
	}

	/**
	 * Repair a specific issue.
	 *
	 * @param string $type    Issue type.
	 * @param array  $details Issue details.
	 * @return true|WP_Error
	 */
	public static function repair_issue( $type, $details ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		switch ( $type ) {
			case 'missing_unique_id':
				$student_id = absint( $details['student_id'] );
				$unique_id  = 'STU-' . strtoupper( wp_generate_password( 6, false ) ) . '-' . $student_id;
				$wpdb->update(
					"{$prefix}students",
					array( 'unique_id' => $unique_id ),
					array( 'id' => $student_id ),
					array( '%s' ),
					array( '%d' )
				);
				break;

			case 'invalid_fee':
				$student_id = absint( $details['student_id'] );
				$total_fee  = max( 0, floatval( $details['total_fee'] ) );
				$paid_fee   = max( 0, min( floatval( $details['paid_fee'] ), $total_fee ) );
				$wpdb->update(
					"{$prefix}students",
					array( 'total_fee' => $total_fee, 'paid_fee' => $paid_fee ),
					array( 'id' => $student_id ),
					array( '%f', '%f' ),
					array( '%d' )
				);
				break;

			case 'cleanup_tokens':
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$prefix}registration_tokens WHERE expires_at < NOW() - INTERVAL %d DAY",
						7
					)
				);
				break;

			case 'orphaned_payment':
				$payment_id = absint( $details['payment_id'] );
				$wpdb->update(
					"{$prefix}payments",
					array( 'is_deleted' => 1 ),
					array( 'id' => $payment_id ),
					array( '%d' ),
					array( '%d' )
				);
				break;

			case 'orphaned_attendance':
				$attendance_id = absint( $details['attendance_id'] );
				$wpdb->delete(
					"{$prefix}attendance",
					array( 'id' => $attendance_id ),
					array( '%d' )
				);
				break;

			case 'repair_table':
				$table = sanitize_key( $details['table'] );
				$wpdb->query( "REPAIR TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				break;

			default:
				return new WP_Error( 'unknown_issue', __( 'Unknown issue type.', 'class-manager-pro' ) );
		}

		CMP_Cache::invalidate_all();
		return true;
	}

	/**
	 * REST endpoint for health status.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_health_status() {
		$results = self::get_results();
		return new WP_REST_Response(
			array(
				'healthy' => 0 === $results['critical'],
				'summary' => $results,
			),
			200
		);
	}
}
