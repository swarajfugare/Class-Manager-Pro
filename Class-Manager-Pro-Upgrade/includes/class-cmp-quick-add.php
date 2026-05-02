<?php
/**
 * Class CMP_Quick_Add
 *
 * Modal-based quick add for students, payments, classes.
 *
 * @package ClassManagerPro
 */
class CMP_Quick_Add {

	public static function init() {
		add_action( 'wp_ajax_cmp_quick_add_student', array( __CLASS__, 'ajax_add_student' ) );
		add_action( 'wp_ajax_cmp_quick_add_payment', array( __CLASS__, 'ajax_add_payment' ) );
		add_action( 'wp_ajax_cmp_quick_add_class', array( __CLASS__, 'ajax_add_class' ) );
		add_action( 'wp_ajax_cmp_quick_add_batch', array( __CLASS__, 'ajax_add_batch' ) );
	}

	public static function ajax_add_student() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$class_id = absint( $_POST['class_id'] ?? 0 );
		$batch_id = absint( $_POST['batch_id'] ?? 0 );
		$total_fee = floatval( $_POST['total_fee'] ?? 0 );

		if ( ! $class_id || ! $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Class and batch are required.', 'class-manager-pro' ) ) );
		}

		// Use existing save handler for validation consistency.
		$_POST['total_fee'] = $total_fee;
		
		ob_start();
		$result = cmp_save_student( 0 ); // This is the existing function.
		ob_end_clean();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		CMP_Cache::invalidate_students();
		CMP_Admin_Notifications::add( sprintf( __( 'Student added: #%d', 'class-manager-pro' ), $result ), 'success' );

		wp_send_json_success( array(
			'student_id' => $result,
			'message'    => __( 'Student added successfully!', 'class-manager-pro' ),
		) );
	}

	public static function ajax_add_payment() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$student_id = absint( $_POST['student_id'] ?? 0 );
		$amount     = floatval( $_POST['amount'] ?? 0 );

		if ( ! $student_id || $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Valid student and amount are required.', 'class-manager-pro' ) ) );
		}

		$_POST['amount'] = $amount;
		
		ob_start();
		$result = cmp_save_payment( 0 );
		ob_end_clean();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		CMP_Cache::invalidate_payments();
		CMP_Admin_Notifications::add( sprintf( __( 'Payment added: #%d', 'class-manager-pro' ), $result ), 'success' );

		wp_send_json_success( array(
			'payment_id' => $result,
			'message'    => __( 'Payment recorded successfully!', 'class-manager-pro' ),
		) );
	}

	public static function ajax_add_class() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Class name is required.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$wpdb->insert(
			"{$prefix}classes",
			array( 'name' => $name ),
			array( '%s' )
		);

		CMP_Cache::invalidate_classes();

		wp_send_json_success( array(
			'class_id' => $wpdb->insert_id,
			'message'  => __( 'Class added successfully!', 'class-manager-pro' ),
		) );
	}

	public static function ajax_add_batch() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$class_id   = absint( $_POST['class_id'] ?? 0 );
		$batch_name = sanitize_text_field( $_POST['batch_name'] ?? '' );

		if ( ! $class_id || ! $batch_name ) {
			wp_send_json_error( array( 'message' => __( 'Class and batch name are required.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$wpdb->insert(
			"{$prefix}batches",
			array(
				'class_id'    => $class_id,
				'batch_name'  => $batch_name,
				'fee_amount'  => floatval( $_POST['fee_amount'] ?? 0 ),
				'fee_due_date'=> sanitize_text_field( $_POST['fee_due_date'] ?? '' ),
				'class_days'  => sanitize_text_field( $_POST['class_days'] ?? '' ),
			),
			array( '%d', '%s', '%f', '%s', '%s' )
		);

		CMP_Cache::invalidate_batches();

		wp_send_json_success( array(
			'batch_id' => $wpdb->insert_id,
			'message'  => __( 'Batch added successfully!', 'class-manager-pro' ),
		) );
	}
}
