<?php
/**
 * Class CMP_Smart_Search
 *
 * AJAX-powered autocomplete search for all entities.
 * Replaces large dropdowns with searchable inputs.
 *
 * @package ClassManagerPro
 */
class CMP_Smart_Search {

	/**
	 * Initialize smart search.
	 */
	public static function init() {
		add_action( 'wp_ajax_cmp_smart_search_students', array( __CLASS__, 'ajax_search_students' ) );
		add_action( 'wp_ajax_cmp_smart_search_batches', array( __CLASS__, 'ajax_search_batches' ) );
		add_action( 'wp_ajax_cmp_smart_search_teachers', array( __CLASS__, 'ajax_search_teachers' ) );
		add_action( 'wp_ajax_cmp_student_lookup', array( __CLASS__, 'ajax_student_lookup' ) );
	}

	/**
	 * Search students with smart autocomplete.
	 */
	public static function ajax_search_students() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$search = sanitize_text_field( $_GET['term'] ?? '' );
		if ( strlen( $search ) < 1 ) {
			wp_send_json( array() );
		}

		$students = cmp_get_students(
			array(
				'search' => $search,
				'limit'  => 20,
			)
		);

		$results = array();
		foreach ( $students as $student ) {
			$label = sprintf(
				'%s - %s (%s) / %s',
				$student->unique_id,
				$student->name,
				$student->class_name ?: __( 'Unassigned', 'class-manager-pro' ),
				cmp_get_student_batch_label( $student )
			);

			$results[] = array(
				'id'       => $student->id,
				'label'    => $label,
				'value'    => $student->name,
				'phone'    => $student->phone,
				'email'    => $student->email,
				'class_id' => $student->class_id,
				'batch_id' => $student->batch_id,
				'total_fee'=> $student->total_fee,
				'paid_fee' => $student->paid_fee,
			);
		}

		wp_send_json( $results );
	}

	/**
	 * Search batches with smart autocomplete.
	 */
	public static function ajax_search_batches() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$search = sanitize_text_field( $_GET['term'] ?? '' );
		$class_id = absint( $_GET['class_id'] ?? 0 );

		$batches = cmp_get_batches();
		$results = array();

		foreach ( $batches as $batch ) {
			if ( $class_id && (int) $batch->class_id !== $class_id ) {
				continue;
			}
			if ( $search && false === stripos( $batch->batch_name, $search ) && false === stripos( $batch->class_name, $search ) ) {
				continue;
			}

			$results[] = array(
				'id'        => $batch->id,
				'label'     => sprintf( '%s / %s', $batch->class_name, $batch->batch_name ),
				'value'     => $batch->batch_name,
				'class_id'  => $batch->class_id,
				'batch_fee' => cmp_get_batch_effective_fee( $batch ),
			);
		}

		wp_send_json( array_slice( $results, 0, 20 ) );
	}

	/**
	 * Search teachers.
	 */
	public static function ajax_search_teachers() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$search = sanitize_text_field( $_GET['term'] ?? '' );
		$users = cmp_get_teacher_users( 0, $search );

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'    => $user->ID,
				'label' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
				'value' => $user->display_name,
			);
		}

		wp_send_json( $results );
	}

	/**
	 * Lookup single student by ID.
	 */
	public static function ajax_student_lookup() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$student_id = absint( $_POST['student_id'] ?? 0 );
		$student    = cmp_get_student( $student_id );

		if ( ! $student ) {
			wp_send_json_error( array( 'message' => __( 'Student not found.', 'class-manager-pro' ) ) );
		}

		$remaining = max( 0, (float) $student->total_fee - (float) $student->paid_fee );

		wp_send_json_success(
			array(
				'id'         => $student->id,
				'name'       => $student->name,
				'phone'      => $student->phone,
				'email'      => $student->email,
				'class_id'   => $student->class_id,
				'batch_id'   => $student->batch_id,
				'total_fee'  => $student->total_fee,
				'paid_fee'   => $student->paid_fee,
				'pending'    => $remaining,
				'class_name' => $student->class_name,
				'batch_name' => $student->batch_name,
			)
		);
	}
}
