<?php
/**
 * Class CMP_Admin_Notifications
 *
 * Real-time notification center for CMP admins.
 *
 * @package ClassManagerPro
 */
class CMP_Admin_Notifications {

	const OPTION_KEY = 'cmp_admin_notifications';
	const MAX_STORED = 100;

	public static function init() {
		add_action( 'wp_ajax_cmp_get_notifications', array( __CLASS__, 'ajax_get' ) );
		add_action( 'wp_ajax_cmp_dismiss_notification', array( __CLASS__, 'ajax_dismiss' ) );
		add_action( 'wp_ajax_cmp_mark_all_read', array( __CLASS__, 'ajax_mark_all_read' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_floating_badge' ) );
	}

	/**
	 * Add a notification.
	 *
	 * @param string $message Message text.
	 * @param string $type    Type: success, warning, error, info.
	 * @param string $context Optional context/page.
	 * @param int    $user_id Optional user ID (0 = all admins).
	 */
	public static function add( $message, $type = 'info', $context = '', $user_id = 0 ) {
		$notifications = get_option( self::OPTION_KEY, array() );

		$notification = array(
			'id'        => wp_generate_password( 8, false ),
			'message'   => sanitize_text_field( $message ),
			'type'      => sanitize_key( $type ),
			'context'   => sanitize_key( $context ),
			'user_id'   => $user_id,
			'time'      => current_time( 'mysql' ),
			'timestamp' => time(),
			'read'      => false,
			'dismissed' => false,
		);

		array_unshift( $notifications, $notification );

		// Keep only the most recent.
		if ( count( $notifications ) > self::MAX_STORED ) {
			$notifications = array_slice( $notifications, 0, self::MAX_STORED );
		}

		update_option( self::OPTION_KEY, $notifications, false );

		// Also log to admin activity log.
		if ( function_exists( 'cmp_log_admin_action' ) ) {
			cmp_log_admin_action( array(
				'action'      => 'notification',
				'object_type' => 'system',
				'object_id'   => 0,
				'message'     => $message,
			) );
		}

		return $notification['id'];
	}

	/**
	 * Get notifications for current user.
	 *
	 * @return array
	 */
	public static function get_for_user() {
		$notifications = get_option( self::OPTION_KEY, array() );
		$current_id    = get_current_user_id();
		$is_admin      = current_user_can( 'manage_options' );

		return array_filter( $notifications, function( $n ) use ( $current_id, $is_admin ) {
			if ( $n['dismissed'] ) {
				return false;
			}
			if ( 0 === $n['user_id'] ) {
				return true;
			}
			if ( $n['user_id'] === $current_id ) {
				return true;
			}
			return $is_admin;
		} );
	}

	/**
	 * Get unread count.
	 *
	 * @return int
	 */
	public static function get_unread_count() {
		$all = self::get_for_user();
		return count( array_filter( $all, function( $n ) {
			return ! $n['read'];
		} ) );
	}

	/**
	 * Mark as read.
	 *
	 * @param string $id Notification ID.
	 */
	public static function mark_read( $id ) {
		$notifications = get_option( self::OPTION_KEY, array() );
		foreach ( $notifications as &$n ) {
			if ( $n['id'] === $id ) {
				$n['read'] = true;
				break;
			}
		}
		update_option( self::OPTION_KEY, $notifications, false );
	}

	/**
	 * Dismiss notification.
	 *
	 * @param string $id Notification ID.
	 */
	public static function dismiss( $id ) {
		$notifications = get_option( self::OPTION_KEY, array() );
		foreach ( $notifications as &$n ) {
			if ( $n['id'] === $id ) {
				$n['dismissed'] = true;
				break;
			}
		}
		update_option( self::OPTION_KEY, $notifications, false );
	}

	/**
	 * Mark all as read.
	 */
	public static function mark_all_read() {
		$notifications = get_option( self::OPTION_KEY, array() );
		foreach ( $notifications as &$n ) {
			$n['read'] = true;
		}
		update_option( self::OPTION_KEY, $notifications, false );
	}

	/**
	 * AJAX get notifications.
	 */
	public static function ajax_get() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$notifications = self::get_for_user();
		wp_send_json_success( array(
			'notifications' => array_values( $notifications ),
			'unread_count'  => self::get_unread_count(),
		) );
	}

	/**
	 * AJAX dismiss.
	 */
	public static function ajax_dismiss() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$id = sanitize_text_field( $_POST['id'] ?? '' );
		self::dismiss( $id );
		wp_send_json_success( array( 'unread_count' => self::get_unread_count() ) );
	}

	/**
	 * AJAX mark all read.
	 */
	public static function ajax_mark_all_read() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		self::mark_all_read();
		wp_send_json_success( array( 'unread_count' => 0 ) );
	}

	/**
	 * Render floating notification badge in admin.
	 */
	public static function render_floating_badge() {
		if ( ! cmp_is_cmp_admin_page() || ! current_user_can( 'cmp_manage' ) ) {
			return;
		}

		$count = self::get_unread_count();
		if ( 0 === $count ) {
			return;
		}

		printf(
			'<div id="cmp-notification-badge" class="cmp-notification-badge" data-count="%d">
				<span class="dashicons dashicons-bell"></span>
				<span class="cmp-notification-count">%d</span>
			</div>',
			$count,
			$count
		);
	}
}
