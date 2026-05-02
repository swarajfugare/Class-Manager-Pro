<?php
/**
 * Class CMP_Inline_Edit
 *
 * Quick inline editing for student and payment fields.
 *
 * @package ClassManagerPro
 */
class CMP_Inline_Edit {

	/**
	 * Initialize inline editing.
	 */
	public static function init() {
		add_action( 'wp_ajax_cmp_inline_edit_student', array( __CLASS__, 'ajax_edit_student' ) );
		add_action( 'wp_ajax_cmp_inline_edit_payment', array( __CLASS__, 'ajax_edit_payment' ) );
		add_action( 'wp_ajax_cmp_inline_edit_batch', array( __CLASS__, 'ajax_edit_batch' ) );
		add_action( 'wp_ajax_cmp_inline_edit_class', array( __CLASS__, 'ajax_edit_class' ) );
		add_action( 'wp_ajax_cmp_bulk_inline_update', array( __CLASS__, 'ajax_bulk_inline_update' ) );
	}

	/**
	 * Edit student inline.
	 */
	public static function ajax_edit_student() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$student_id = absint( $_POST['student_id'] ?? 0 );
		$field      = sanitize_key( $_POST['field'] ?? '' );
		$value      = sanitize_text_field( $_POST['value'] ?? '' );

		$allowed_fields = array( 'name', 'phone', 'email', 'status', 'notes' );
		if ( ! in_array( $field, $allowed_fields, true ) || ! $student_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field or student.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$update = array( $field => $value );
		$format = array( '%s' );

		if ( 'status' === $field ) {
			$valid_statuses = cmp_student_statuses();
			if ( ! in_array( $value, $valid_statuses, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid status.', 'class-manager-pro' ) ) );
			}
		}

		$result = $wpdb->update(
			"{$prefix}students",
			$update,
			array( 'id' => $student_id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Update failed.', 'class-manager-pro' ) ) );
		}

		CMP_Cache::invalidate_students();
		CMP_Admin_Notifications::add( sprintf( __( 'Student #%d updated successfully.', 'class-manager-pro' ), $student_id ), 'success' );

		wp_send_json_success(
			array(
				'field' => $field,
				'value' => $value,
				'message' => __( 'Updated!', 'class-manager-pro' ),
			)
		);
	}

	/**
	 * Edit payment inline.
	 */
	public static function ajax_edit_payment() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$payment_id = absint( $_POST['payment_id'] ?? 0 );
		$field      = sanitize_key( $_POST['field'] ?? '' );
		$value      = sanitize_text_field( $_POST['value'] ?? '' );

		$allowed_fields = array( 'amount', 'payment_mode', 'transaction_id', 'payment_date' );
		if ( ! in_array( $field, $allowed_fields, true ) || ! $payment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field or payment.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		// Get old value for audit.
		$old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}payments WHERE id = %d", $payment_id ) );
		if ( ! $old ) {
			wp_send_json_error( array( 'message' => __( 'Payment not found.', 'class-manager-pro' ) ) );
		}

		$update = array();
		$format = array();

		switch ( $field ) {
			case 'amount':
				$update['amount'] = max( 0.01, floatval( $value ) );
				$format[] = '%f';
				break;
			case 'payment_mode':
				$update['payment_mode'] = sanitize_key( $value );
				$format[] = '%s';
				break;
			case 'transaction_id':
				$update['transaction_id'] = sanitize_text_field( $value );
				$format[] = '%s';
				break;
			case 'payment_date':
				$update['payment_date'] = sanitize_text_field( $value );
				$format[] = '%s';
				break;
		}

		$result = $wpdb->update(
			"{$prefix}payments",
			$update,
			array( 'id' => $payment_id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Update failed.', 'class-manager-pro' ) ) );
		}

		// Update student paid_fee.
		if ( 'amount' === $field ) {
			$new_amount = floatval( $value );
			$student_id = (int) $old->student_id;
			cmp_update_student_paid_fee( $student_id );
		}

		CMP_Cache::invalidate_payments();
		CMP_Admin_Notifications::add( sprintf( __( 'Payment #%d updated.', 'class-manager-pro' ), $payment_id ), 'success' );

		wp_send_json_success( array( 'message' => __( 'Updated!', 'class-manager-pro' ) ) );
	}

	/**
	 * Edit batch inline.
	 */
	public static function ajax_edit_batch() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$batch_id = absint( $_POST['batch_id'] ?? 0 );
		$field    = sanitize_key( $_POST['field'] ?? '' );
		$value    = sanitize_text_field( $_POST['value'] ?? '' );

		$allowed_fields = array( 'batch_name', 'status', 'start_date', 'class_days' );
		if ( ! in_array( $field, $allowed_fields, true ) || ! $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field or batch.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$format = 'status' === $field ? '%s' : '%s';

		$result = $wpdb->update(
			"{$prefix}batches",
			array( $field => $value ),
			array( 'id' => $batch_id ),
			array( $format ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Update failed.', 'class-manager-pro' ) ) );
		}

		CMP_Cache::invalidate_batches();
		CMP_Admin_Notifications::add( sprintf( __( 'Batch #%d updated.', 'class-manager-pro' ), $batch_id ), 'success' );

		wp_send_json_success( array( 'message' => __( 'Updated!', 'class-manager-pro' ) ) );
	}

	/**
	 * Edit class inline.
	 */
	public static function ajax_edit_class() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$class_id = absint( $_POST['class_id'] ?? 0 );
		$field    = sanitize_key( $_POST['field'] ?? '' );
		$value    = sanitize_text_field( $_POST['value'] ?? '' );

		if ( 'name' !== $field || ! $class_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field or class.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		$result = $wpdb->update(
			"{$prefix}classes",
			array( 'name' => $value ),
			array( 'id' => $class_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Update failed.', 'class-manager-pro' ) ) );
		}

		CMP_Cache::invalidate_classes();

		wp_send_json_success( array( 'message' => __( 'Updated!', 'class-manager-pro' ) ) );
	}

	/**
	 * Bulk inline update.
	 */
	public static function ajax_bulk_inline_update() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$entity   = sanitize_key( $_POST['entity'] ?? '' );
		$ids      = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		$field    = sanitize_key( $_POST['field'] ?? '' );
		$value    = sanitize_text_field( $_POST['value'] ?? '' );

		if ( empty( $ids ) || empty( $entity ) || empty( $field ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'class-manager-pro' ) ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';
		$table  = "{$prefix}{$entity}s";
		$format = '%s';

		// Build safe IN clause.
		$ids_in = implode( ',', array_filter( $ids ) );
		if ( empty( $ids_in ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid IDs.', 'class-manager-pro' ) ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$field} = %s WHERE id IN ({$ids_in})", $value ) );

		CMP_Cache::invalidate_all();

		wp_send_json_success(
			array(
				'updated' => $result,
				'message' => sprintf( __( '%d items updated.', 'class-manager-pro' ), $result ),
			)
		);
	}
}
