<?php
/**
 * Class CMP_Security
 *
 * Enhanced security layer with rate limiting, validation,
 * and hardened access controls.
 *
 * @package ClassManagerPro
 */

class CMP_Security {

	/** @var string Option prefix for rate limits */
	const RATE_PREFIX = 'cmp_rate_';

	/** @var int Max requests per window */
	const MAX_REQUESTS = 10;

	/** @var int Rate limit window in seconds */
	const RATE_WINDOW = 60;

	/**
	 * Initialize security features.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'setup_security_headers' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'protect_rest_endpoints' ) );
		add_filter( 'cmp_before_process_request', array( __CLASS__, 'validate_request_integrity' ) );
		add_action( 'wp_ajax_cmp_smart_search', array( __CLASS__, 'ajax_check_rate_limit' ), 1 );
		add_action( 'wp_ajax_nopriv_cmp_public_action', array( __CLASS__, 'check_public_rate_limit' ), 1 );
	}

	/**
	 * Set security headers on CMP pages.
	 */
	public static function setup_security_headers() {
		if ( ! is_admin() || ! cmp_is_cmp_admin_page() ) {
			return;
		}
		
		// Prevent clickjacking.
		if ( ! headers_sent() ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
	}

	/**
	 * Protect REST endpoints with additional checks.
	 */
	public static function protect_rest_endpoints() {
		// Ensure all CMP REST endpoints require authentication.
		register_rest_route(
			'cmp/v2',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'CMP_Health_Check', 'rest_health_status' ),
				'permission_callback' => array( __CLASS__, 'check_admin_access' ),
			)
		);
	}

	/**
	 * Check if user has admin-level CMP access.
	 *
	 * @return bool
	 */
	public static function check_admin_access() {
		return current_user_can( 'cmp_manage' );
	}

	/**
	 * Validate request integrity - check nonces, capabilities, and input.
	 *
	 * @param array $request Request data.
	 * @return array|WP_Error
	 */
	public static function validate_request_integrity( $request ) {
		if ( ! is_array( $request ) ) {
			return new WP_Error( 'invalid_request', __( 'Invalid request format.', 'class-manager-pro' ) );
		}

		// Check for required nonce on all modifying requests.
		if ( ! empty( $request['action'] ) && false !== strpos( $request['action'], 'save_' ) ) {
			if ( empty( $request['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $request['_wpnonce'] ), $request['action'] ) ) {
				return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh the page.', 'class-manager-pro' ) );
			}
		}

		return $request;
	}

	/**
	 * Check rate limit for AJAX endpoints.
	 */
	public static function ajax_check_rate_limit() {
		if ( ! self::check_rate_limit( 'ajax_' . get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'class-manager-pro' ) ), 429 );
		}
	}

	/**
	 * Check public endpoint rate limit.
	 */
	public static function check_public_rate_limit() {
		$ip      = self::get_client_ip();
		$identifier = 'public_' . md5( $ip );
		
		if ( ! self::check_rate_limit( $identifier ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'class-manager-pro' ) ), 429 );
		}
	}

	/**
	 * Check if request is within rate limit.
	 *
	 * @param string $identifier Unique identifier for the rate limit bucket.
	 * @return bool
	 */
	public static function check_rate_limit( $identifier ) {
		$transient_key = self::RATE_PREFIX . $identifier;
		$requests      = get_transient( $transient_key );

		if ( false === $requests ) {
			$requests = array(
				'count' => 1,
				'time'  => time(),
			);
			set_transient( $transient_key, $requests, self::RATE_WINDOW );
			return true;
		}

		$elapsed = time() - $requests['time'];

		if ( $elapsed > self::RATE_WINDOW ) {
			$requests = array(
				'count' => 1,
				'time'  => time(),
			);
			set_transient( $transient_key, $requests, self::RATE_WINDOW );
			return true;
		}

		if ( $requests['count'] >= self::MAX_REQUESTS ) {
			return false;
		}

		$requests['count']++;
		set_transient( $transient_key, $requests, self::RATE_WINDOW );

		return true;
	}

	/**
	 * Enhanced sanitize for file uploads.
	 *
	 * @param array $file $_FILES array element.
	 * @return array|WP_Error
	 */
	public static function validate_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'class-manager-pro' ) );
		}

		$allowed_types = array(
			'text/csv',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'text/plain',
		);

		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid file type. Only CSV and Excel files are allowed.', 'class-manager-pro' ) );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'xlsx', 'xls' ), true ) ) {
			return new WP_Error( 'invalid_extension', __( 'Invalid file extension.', 'class-manager-pro' ) );
		}

		// Check file size (max 10MB).
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			return new WP_Error( 'file_too_large', __( 'File is too large. Maximum size is 10MB.', 'class-manager-pro' ) );
		}

		return $file;
	}

	/**
	 * Sanitize dynamic IN clause values.
	 *
	 * @param array  $ids Array of IDs.
	 * @param string $type Type: 'int' or 'string'.
	 * @return string Sanitized comma-separated values.
	 */
	public static function sanitize_in_clause( $ids, $type = 'int' ) {
		if ( ! is_array( $ids ) ) {
			return '';
		}

		$ids = array_filter( $ids );

		if ( 'int' === $type ) {
			$ids = array_map( 'intval', $ids );
			$ids = array_filter( $ids );
			return implode( ',', $ids );
		}

		global $wpdb;
		$sanitized = array_map( array( $wpdb, '_real_escape' ), $ids );
		return "'" . implode( "','", $sanitized ) . "'";
	}

	/**
	 * Enhanced SQL injection protection for LIKE queries.
	 *
	 * @param string $search Search term.
	 * @return string
	 */
	public static function sanitize_like( $search ) {
		global $wpdb;
		return '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
	}

	/**
	 * Verify webhook signature for Razorpay.
	 *
	 * @param string $payload Raw request body.
	 * @param string $signature Header signature.
	 * @param string $secret Webhook secret.
	 * @return bool
	 */
	public static function verify_webhook_signature( $payload, $signature, $secret ) {
		if ( empty( $secret ) || empty( $signature ) ) {
			return false;
		}
		
		$expected = hash_hmac( 'sha256', $payload, $secret );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Log suspicious activity.
	 *
	 * @param string $action Action attempted.
	 * @param string $reason Reason for flagging.
	 */
	public static function log_suspicious_activity( $action, $reason ) {
		CMP_Performance_Monitor::log_error(
			'Security: ' . $action . ' - ' . $reason,
			__FILE__,
			__LINE__,
			E_USER_WARNING
		);
		
		// Optionally email admin on repeated violations.
		$violations = (int) get_option( 'cmp_security_violations', 0 );
		update_option( 'cmp_security_violations', $violations + 1, false );
		
		if ( $violations > 0 && $violations % 10 === 0 ) {
			$admin_email = get_option( 'admin_email' );
			wp_mail(
				$admin_email,
				__( '[Class Manager Pro] Security Alert', 'class-manager-pro' ),
				sprintf(
					"Multiple security violations detected (%d).\n\nAction: %s\nReason: %s\nIP: %s\nTime: %s",
					$violations,
					$action,
					$reason,
					self::get_client_ip(),
					current_time( 'mysql' )
				)
			);
		}
	}
}
