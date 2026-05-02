<?php
/**
 * Class CMP_Bulk_Processor
 *
 * Background bulk operation processor with progress tracking.
 *
 * @package ClassManagerPro
 */
class CMP_Bulk_Processor {

	const OPTION_PREFIX = 'cmp_bulk_job_';

	public static function init() {
		add_action( 'wp_ajax_cmp_start_bulk_job', array( __CLASS__, 'ajax_start_job' ) );
		add_action( 'wp_ajax_cmp_get_bulk_progress', array( __CLASS__, 'ajax_get_progress' ) );
		add_action( 'cmp_bulk_job_processor', array( __CLASS__, 'process_job_chunk' ), 10, 1 );
	}

	public static function create_job( $type, $ids, $params = array() ) {
		$job_id = wp_generate_password( 12, false );
		$job    = array(
			'id'         => $job_id,
			'type'       => $type,
			'ids'        => $ids,
			'params'     => $params,
			'total'      => count( $ids ),
			'processed'  => 0,
			'success'    => 0,
			'failed'     => 0,
			'status'     => 'pending',
			'created_at' => time(),
			'updated_at' => time(),
			'messages'   => array(),
		);

		update_option( self::OPTION_PREFIX . $job_id, $job, false );
		return $job_id;
	}

	public static function get_job( $job_id ) {
		return get_option( self::OPTION_PREFIX . sanitize_key( $job_id ), null );
	}

	public static function update_job( $job_id, $updates ) {
		$job = self::get_job( $job_id );
		if ( ! $job ) {
			return false;
		}
		$job = array_merge( $job, $updates, array( 'updated_at' => time() ) );
		update_option( self::OPTION_PREFIX . $job_id, $job, false );
		return true;
	}

	public static function delete_job( $job_id ) {
		delete_option( self::OPTION_PREFIX . sanitize_key( $job_id ) );
	}

	public static function process_job_chunk( $job_id ) {
		$job = self::get_job( $job_id );
		if ( ! $job || 'pending' !== $job['status'] ) {
			return;
		}

		self::update_job( $job_id, array( 'status' => 'running' ) );

		$chunk_size = 25;
		$ids        = array_slice( $job['ids'], $job['processed'], $chunk_size );

		foreach ( $ids as $id ) {
			$result = self::process_single_item( $job['type'], $id, $job['params'] );
			$job['processed']++;
			if ( true === $result ) {
				$job['success']++;
			} else {
				$job['failed']++;
				$job['messages'][] = $result;
			}
		}

		$job['updated_at'] = time();

		if ( $job['processed'] >= $job['total'] ) {
			$job['status'] = 'completed';
			CMP_Admin_Notifications::add(
				sprintf( __( 'Bulk job completed: %d success, %d failed', 'class-manager-pro' ), $job['success'], $job['failed'] ),
				'info',
				'bulk'
			);
		}

		self::update_job( $job_id, $job );

		// Schedule next chunk if not done.
		if ( 'completed' !== $job['status'] ) {
			wp_schedule_single_event( time() + 2, 'cmp_bulk_job_processor', array( $job_id ) );
		}
	}

	public static function process_single_item( $type, $id, $params ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		switch ( $type ) {
			case 'delete_students':
				$wpdb->delete( "{$prefix}students", array( 'id' => $id ), array( '%d' ) );
				return true;

			case 'soft_delete_payments':
				$wpdb->update( "{$prefix}payments", array( 'is_deleted' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
				cmp_update_student_paid_fee( $id );
				return true;

			case 'restore_payments':
				$wpdb->update( "{$prefix}payments", array( 'is_deleted' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
				return true;

			case 'change_status':
				$status = sanitize_key( $params['status'] ?? '' );
				$wpdb->update( "{$prefix}students", array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
				return true;

			case 'resend_confirmation':
				$student = cmp_get_student( $id );
				if ( $student ) {
					cmp_resend_student_email( $student );
					return true;
				}
				return 'Student not found';

			default:
				return 'Unknown job type';
		}
	}

	public static function ajax_start_job() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$type   = sanitize_key( $_POST['job_type'] ?? '' );
		$ids    = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		$params = map_deep( $_POST['params'] ?? array(), 'sanitize_text_field' );

		$job_id = self::create_job( $type, $ids, $params );

		// Start processing immediately.
		wp_schedule_single_event( time(), 'cmp_bulk_job_processor', array( $job_id ) );

		wp_send_json_success( array( 'job_id' => $job_id, 'message' => __( 'Bulk job started.', 'class-manager-pro' ) ) );
	}

	public static function ajax_get_progress() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$job_id = sanitize_key( $_POST['job_id'] ?? '' );
		$job    = self::get_job( $job_id );

		if ( ! $job ) {
			wp_send_json_error( array( 'message' => __( 'Job not found.', 'class-manager-pro' ) ) );
		}

		wp_send_json_success( array(
			'job'      => $job,
			'progress' => $job['total'] > 0 ? round( $job['processed'] / $job['total'] * 100, 1 ) : 0,
			'done'     => 'completed' === $job['status'],
		) );
	}
}
