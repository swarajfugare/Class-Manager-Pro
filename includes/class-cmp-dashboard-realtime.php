<?php
/**
 * Class CMP_Dashboard_Realtime
 *
 * Auto-refreshing dashboard widgets with live data.
 *
 * @package ClassManagerPro
 */
class CMP_Dashboard_Realtime {

	public static function init() {
		add_action( 'wp_ajax_cmp_dashboard_realtime_data', array( __CLASS__, 'ajax_realtime_data' ) );
		add_action( 'wp_ajax_cmp_get_recent_activity', array( __CLASS__, 'ajax_recent_activity' ) );
		add_action( 'wp_ajax_cmp_get_live_metrics', array( __CLASS__, 'ajax_live_metrics' ) );
		add_action( 'cmp_dashboard_after_metrics', array( __CLASS__, 'render_realtime_toggle' ) );
	}

	public static function ajax_realtime_data() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$range = sanitize_key( $_POST['range'] ?? 'today' );

		$metrics = CMP_Cache::remember( 'dashboard_metrics_' . $range, function() use ( $range ) {
			return self::get_live_metrics( $range );
		}, array(), 60 );

		$activity = self::get_recent_activity( 10 );

		wp_send_json_success( array(
			'metrics'  => $metrics,
			'activity' => $activity,
			'time'     => current_time( 'mysql' ),
		) );
	}

	public static function ajax_live_metrics() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$range = sanitize_key( $_POST['range'] ?? 'today' );
		wp_send_json_success( array( 'metrics' => self::get_live_metrics( $range ) ) );
	}

	public static function ajax_recent_activity() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$limit = min( 20, absint( $_POST['limit'] ?? 10 ) );
		wp_send_json_success( array( 'activity' => self::get_recent_activity( $limit ) ) );
	}

	public static function get_live_metrics( $range = 'today' ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';
		$date   = self::get_range_date( $range );

		$total_students = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}students WHERE status = 'Active'" );
		$total_batches  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}batches" );
		$total_classes  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}classes" );
		$total_revenue  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}payments WHERE is_deleted = 0" );
		$pending_fees   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_fee - paid_fee),0) FROM {$prefix}students WHERE status = 'Active'" );

		$new_students = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}students WHERE created_at >= %s", $date )
		);

		$new_payments = (float) $wpdb->get_var(
			$wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}payments WHERE is_deleted = 0 AND payment_date >= %s", $date )
		);

		$online_payments = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}payments WHERE payment_mode = 'Online' AND is_deleted = 0" );
		$offline_payments = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}payments WHERE payment_mode = 'Offline' AND is_deleted = 0" );

		return array(
			'total_students'   => $total_students,
			'total_batches'    => $total_batches,
			'total_classes'    => $total_classes,
			'total_revenue'    => $total_revenue,
			'pending_fees'     => $pending_fees,
			'new_students'     => $new_students,
			'new_payments'     => $new_payments,
			'online_payments'  => $online_payments,
			'offline_payments' => $offline_payments,
			'default_per_page' => cmp_get_default_per_page(),
		);
	}

	public static function get_recent_activity( $limit = 10 ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$items = array();

		// Recent students.
		$students = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, name, created_at, 'student' as type FROM {$prefix}students ORDER BY created_at DESC LIMIT %d", $limit )
		);

		// Recent payments.
		$payments = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, student_name, amount, payment_date, 'payment' as type FROM {$prefix}payments WHERE is_deleted = 0 ORDER BY payment_date DESC LIMIT %d", $limit )
		);

		// Combine and sort.
		$all = array_merge( $students, $payments );
		usort( $all, function( $a, $b ) {
			return strtotime( $b->created_at ?? $b->payment_date ) - strtotime( $a->created_at ?? $a->payment_date );
		} );

		return array_slice( $all, 0, $limit );
	}

	public static function get_range_date( $range ) {
		$today = date( 'Y-m-d' );
		switch ( $range ) {
			case 'today':
				return $today . ' 00:00:00';
			case 'week':
				return date( 'Y-m-d', strtotime( '-7 days' ) ) . ' 00:00:00';
			case 'month':
				return date( 'Y-m-d', strtotime( '-30 days' ) ) . ' 00:00:00';
			case 'all':
				return '1970-01-01 00:00:00';
			default:
				return $today . ' 00:00:00';
		}
	}

	public static function render_realtime_toggle() {
		?>
		<div class="cmp-realtime-toggle">
			<label class="cmp-switch">
				<input type="checkbox" id="cmp-realtime-enabled" checked>
				<span class="cmp-slider"></span>
			</label>
			<span class="cmp-realtime-label"><?php esc_html_e( 'Auto-refresh', 'class-manager-pro' ); ?></span>
			<span class="cmp-last-updated"></span>
		</div>
		<?php
	}
}
