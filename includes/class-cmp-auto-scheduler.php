<?php
/**
 * Class CMP_Auto_Scheduler
 *
 * Smart cron-based automation for reminders, sync, reports.
 *
 * @package ClassManagerPro
 */
class CMP_Auto_Scheduler {

	const OPTION_KEY = 'cmp_auto_schedule';

	public static function init() {
		add_action( 'cmp_daily_automation', array( __CLASS__, 'run_daily_tasks' ) );
		add_action( 'cmp_weekly_automation', array( __CLASS__, 'run_weekly_tasks' ) );
		add_action( 'cmp_monthly_automation', array( __CLASS__, 'run_monthly_tasks' ) );
		add_action( 'wp_ajax_cmp_run_automation_now', array( __CLASS__, 'ajax_run_now' ) );
		add_action( 'wp_ajax_cmp_update_schedule', array( __CLASS__, 'ajax_update_schedule' ) );
	}

	public static function setup_default_schedule() {
		if ( ! wp_next_scheduled( 'cmp_daily_automation' ) ) {
			wp_schedule_event( time(), 'daily', 'cmp_daily_automation' );
		}
		if ( ! wp_next_scheduled( 'cmp_weekly_automation' ) ) {
			wp_schedule_event( strtotime( 'next sunday 6:00' ), 'weekly', 'cmp_weekly_automation' );
		}
		if ( ! wp_next_scheduled( 'cmp_monthly_automation' ) ) {
			wp_schedule_event( strtotime( 'first day of next month 6:00' ), 'monthly', 'cmp_monthly_automation' );
		}
	}

	public static function clear_all() {
		foreach ( array( 'cmp_daily_automation', 'cmp_weekly_automation', 'cmp_monthly_automation' ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	public static function run_daily_tasks() {
		$schedule = get_option( self::OPTION_KEY, self::get_default_schedule() );

		if ( ! empty( $schedule['fee_reminders'] ) ) {
			self::send_fee_reminders();
		}

		if ( ! empty( $schedule['attendance_alerts'] ) ) {
			self::check_attendance_alerts();
		}

		if ( ! empty( $schedule['razorpay_sync'] ) ) {
			self::sync_razorpay_payments();
		}

		if ( ! empty( $schedule['cleanup'] ) ) {
			self::cleanup_old_data();
		}

		CMP_Admin_Notifications::add( __( 'Daily automation tasks completed.', 'class-manager-pro' ), 'info', 'automation' );
	}

	public static function run_weekly_tasks() {
		$schedule = get_option( self::OPTION_KEY, self::get_default_schedule() );

		if ( ! empty( $schedule['weekly_report'] ) ) {
			self::send_weekly_report();
		}

		if ( ! empty( $schedule['backup'] ) ) {
			CMP_Backup_Manager::create_backup();
		}

		CMP_Admin_Notifications::add( __( 'Weekly automation tasks completed.', 'class-manager-pro' ), 'info', 'automation' );
	}

	public static function run_monthly_tasks() {
		$schedule = get_option( self::OPTION_KEY, self::get_default_schedule() );

		if ( ! empty( $schedule['monthly_report'] ) ) {
			self::send_monthly_report();
		}

		CMP_Admin_Notifications::add( __( 'Monthly automation tasks completed.', 'class-manager-pro' ), 'info', 'automation' );
	}

	public static function send_fee_reminders() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$due_date = date( 'Y-m-d', strtotime( '+3 days' ) );

		$students = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, b.fee_due_date FROM {$prefix}students s
				 INNER JOIN {$prefix}batches b ON s.batch_id = b.id
				 WHERE s.status = 'Active' AND b.fee_due_date = %s AND (s.total_fee - s.paid_fee) > 0
				 LIMIT 200",
				$due_date
			)
		);

		foreach ( $students as $student ) {
			$remaining = (float) $student->total_fee - (float) $student->paid_fee;

			if ( function_exists( 'cmp_send_whatsapp_message' ) ) {
				$template_id = get_option( 'cmp_fee_reminder_template_id', '' );
				if ( $template_id ) {
					cmp_send_whatsapp_message( $student->phone, $template_id, array(
						'{{name}}' => $student->name,
						'{{amount}}' => cmp_format_money( $remaining ),
					) );
				}
			}
		}

		CMP_Admin_Notifications::add(
			sprintf( __( 'Fee reminders sent to %d students.', 'class-manager-pro' ), count( $students ) ),
			'success',
			'automation'
		);
	}

	public static function check_attendance_alerts() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
		$batches_without_attendance = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.batch_name FROM {$prefix}batches b
				 WHERE b.status = 'active'
				 AND b.id NOT IN (
					SELECT DISTINCT batch_id FROM {$prefix}attendance WHERE attendance_date = %s
				 )
				 LIMIT 50",
				$yesterday
			)
		);

		foreach ( $batches_without_attendance as $batch ) {
			CMP_Admin_Notifications::add(
				sprintf( __( 'Batch "%s" has no attendance marked for yesterday (%s).', 'class-manager-pro' ), $batch->batch_name, $yesterday ),
				'warning',
				'automation'
			);
		}
	}

	public static function sync_razorpay_payments() {
		if ( ! function_exists( 'cmp_sync_razorpay_transactions_page' ) ) {
			return;
		}

		$key    = get_option( 'cmp_razorpay_key', '' );
		$secret = get_option( 'cmp_razorpay_secret', '' );

		if ( ! $key || ! $secret ) {
			return;
		}

		// Sync last 24 hours.
		$synced = cmp_sync_razorpay_transactions_page( $key, $secret, 1, 50 );

		if ( $synced > 0 ) {
			CMP_Admin_Notifications::add(
				sprintf( __( 'Razorpay sync: %d transactions imported.', 'class-manager-pro' ), $synced ),
				'success',
				'automation'
			);
		}
	}

	public static function cleanup_old_data() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		// Soft-deleted payments older than 90 days.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}payments WHERE is_deleted = 1 AND updated_at < NOW() - INTERVAL %d DAY",
				90
			)
		);

		// Old admin logs (keep 90 days).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}admin_logs WHERE created_at < NOW() - INTERVAL %d DAY",
				90
			)
		);

		// Old teacher logs.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}teacher_logs WHERE logged_at < NOW() - INTERVAL %d DAY",
				90
			)
		);
	}

	public static function send_weekly_report() {
		$admin_email = get_option( 'cmp_admin_email', get_option( 'admin_email' ) );
		if ( ! $admin_email ) {
			return;
		}

		// Generate quick summary.
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$total_students = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}students WHERE status = 'Active'" );
		$total_revenue  = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$prefix}payments WHERE is_deleted = 0" );
		$pending_fees   = (float) $wpdb->get_var( "SELECT SUM(total_fee - paid_fee) FROM {$prefix}students WHERE status = 'Active'" );
		$new_students   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}students WHERE created_at > NOW() - INTERVAL 7 DAY" );

		$subject = __( '[Class Manager Pro] Weekly Summary', 'class-manager-pro' );
		$body    = sprintf(
			"Weekly Summary for %s\n\nActive Students: %s\nTotal Revenue: %s\nPending Fees: %s\nNew Students (7 days): %s",
			get_bloginfo( 'name' ),
			number_format_i18n( $total_students ),
			cmp_format_money( $total_revenue ),
			cmp_format_money( $pending_fees ),
			number_format_i18n( $new_students )
		);

		wp_mail( $admin_email, $subject, $body );
	}

	public static function send_monthly_report() {
		self::send_weekly_report(); // Same format, just monthly.
	}

	public static function get_default_schedule() {
		return array(
			'fee_reminders'      => true,
			'attendance_alerts'  => true,
			'razorpay_sync'      => false,
			'cleanup'            => true,
			'weekly_report'      => true,
			'monthly_report'     => true,
			'backup'             => true,
		);
	}

	public static function ajax_run_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$type = sanitize_key( $_POST['type'] ?? '' );
		switch ( $type ) {
			case 'daily':
				self::run_daily_tasks();
				break;
			case 'weekly':
				self::run_weekly_tasks();
				break;
			case 'monthly':
				self::run_monthly_tasks();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid schedule type.', 'class-manager-pro' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Automation ran successfully.', 'class-manager-pro' ) ) );
	}

	public static function ajax_update_schedule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$schedule = map_deep( $_POST['schedule'] ?? array(), 'rest_sanitize_boolean' );
		update_option( self::OPTION_KEY, array_merge( self::get_default_schedule(), $schedule ), false );

		wp_send_json_success( array( 'message' => __( 'Schedule updated.', 'class-manager-pro' ) ) );
	}
}
