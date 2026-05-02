<?php
/**
 * Class CMP_Performance_Monitor
 *
 * Query performance tracking and slow query alerts.
 *
 * @package ClassManagerPro
 */
class CMP_Performance_Monitor {

	const LOG_OPTION = 'cmp_performance_logs';
	const MAX_LOGS   = 500;
	const SLOW_THRESHOLD = 1.0; // seconds

	private static $query_times = array();
	private static $query_count = 0;
	private static $start_time = 0;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'start_monitoring' ) );
		add_action( 'shutdown', array( __CLASS__, 'record_page_load' ) );
		add_filter( 'query', array( __CLASS__, 'track_query' ) );
		add_action( 'wp_ajax_cmp_get_performance_stats', array( __CLASS__, 'ajax_stats' ) );
		add_action( 'wp_ajax_cmp_clear_performance_logs', array( __CLASS__, 'ajax_clear' ) );
	}

	public static function start_monitoring() {
		self::$start_time = microtime( true );
	}

	public static function track_query( $query ) {
		if ( false === strpos( $query, 'cmp_' ) ) {
			return $query;
		}

		self::$query_count++;

		$start = microtime( true );
		add_filter( 'query', function( $q ) use ( $start, $query ) {
			if ( $q === $query ) {
				$time = microtime( true ) - $start;
				self::$query_times[] = array(
					'query' => substr( $query, 0, 200 ),
					'time'  => round( $time, 4 ),
					'time_ms' => round( $time * 1000, 2 ),
					'slow'  => $time > self::SLOW_THRESHOLD,
				);
			}
			return $q;
		}, PHP_INT_MAX );

		return $query;
	}

	public static function record_page_load() {
		if ( ! cmp_is_cmp_admin_page() ) {
			return;
		}

		$total_time = microtime( true ) - self::$start_time;
		$slow_queries = array_filter( self::$query_times, function( $q ) {
			return $q['slow'];
		} );

		$log = array(
			'time'         => current_time( 'mysql' ),
			'page'         => sanitize_key( $_GET['page'] ?? 'unknown' ),
			'query_count'  => self::$query_count,
			'total_time'   => round( $total_time, 3 ),
			'slow_queries' => count( $slow_queries ),
			'peak_memory'  => round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ),
		);

		$logs = get_option( self::LOG_OPTION, array() );
		array_unshift( $logs, $log );
		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, 0, self::MAX_LOGS );
		}
		update_option( self::LOG_OPTION, $logs, false );

		// Alert on very slow page loads.
		if ( $total_time > 5.0 ) {
			CMP_Admin_Notifications::add(
				sprintf( __( 'Slow page detected: %s took %.2fs with %d queries', 'class-manager-pro' ), $log['page'], $total_time, self::$query_count ),
				'warning',
				'performance'
			);
		}
	}

	public static function log_error( $message, $file, $line, $errno ) {
		$logs = get_option( self::LOG_OPTION, array() );
		array_unshift( $logs, array(
			'time'    => current_time( 'mysql' ),
			'type'    => 'error',
			'message' => sanitize_text_field( $message ),
			'file'    => sanitize_text_field( basename( $file ) ),
			'line'    => (int) $line,
			'errno'   => (int) $errno,
		) );
		update_option( self::LOG_OPTION, array_slice( $logs, 0, self::MAX_LOGS ), false );
	}

	public static function get_logs() {
		return get_option( self::LOG_OPTION, array() );
	}

	public static function clear_logs() {
		delete_option( self::LOG_OPTION );
	}

	public static function ajax_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$logs = self::get_logs();
		$recent = array_slice( $logs, 0, 50 );

		$avg_time = 0;
		$avg_queries = 0;
		if ( count( $recent ) > 0 ) {
			$avg_time = round( array_sum( array_column( $recent, 'total_time' ) ) / count( $recent ), 3 );
			$avg_queries = round( array_sum( array_column( $recent, 'query_count' ) ) / count( $recent ), 1 );
		}

		wp_send_json_success( array(
			'recent'      => $recent,
			'avg_time'    => $avg_time,
			'avg_queries' => $avg_queries,
			'total_logs'  => count( $logs ),
		) );
	}

	public static function ajax_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );
		self::clear_logs();
		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'class-manager-pro' ) ) );
	}
}
