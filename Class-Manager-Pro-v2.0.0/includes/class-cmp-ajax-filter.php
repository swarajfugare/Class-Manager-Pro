<?php
/**
 * Class CMP_AJAX_Filter
 *
 * Live AJAX-powered filtering for all CMP list views.
 * Eliminates page reloads when filtering data.
 *
 * @package ClassManagerPro
 */
class CMP_AJAX_Filter {

	/**
	 * Initialize AJAX filtering.
	 */
	public static function init() {
		add_action( 'wp_ajax_cmp_filter_students', array( __CLASS__, 'ajax_filter_students' ) );
		add_action( 'wp_ajax_cmp_filter_payments', array( __CLASS__, 'ajax_filter_payments' ) );
		add_action( 'wp_ajax_cmp_filter_batches', array( __CLASS__, 'ajax_filter_batches' ) );
		add_action( 'wp_ajax_cmp_refresh_dashboard', array( __CLASS__, 'ajax_refresh_dashboard' ) );
		add_action( 'wp_ajax_cmp_quick_search', array( __CLASS__, 'ajax_quick_search' ) );
	}

	/**
	 * AJAX filter students.
	 */
	public static function ajax_filter_students() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$filters = array(
			'search'    => sanitize_text_field( $_POST['search'] ?? '' ),
			'class_id'  => absint( $_POST['class_id'] ?? 0 ),
			'batch_id'  => absint( $_POST['batch_id'] ?? 0 ),
			'status'    => sanitize_key( $_POST['status'] ?? '' ),
			'page'      => max( 1, absint( $_POST['page'] ?? 1 ) ),
		);

		$per_page   = cmp_get_default_per_page();
		$pagination = cmp_get_pagination_data( cmp_get_students_count( $filters ), $filters['page'], $per_page );
		$row_args   = array_merge(
			$filters,
			array(
				'limit'  => $pagination['per_page'],
				'offset' => $pagination['offset'],
			)
		);

		ob_start();
		echo cmp_render_student_rows( $row_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$html = ob_get_clean();

		ob_start();
		cmp_render_pagination( $pagination, $filters );
		$pagination_html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'       => $html,
				'pagination' => $pagination_html,
				'total'      => $pagination['total'],
				'page'       => $filters['page'],
				'pages'      => $pagination['total_pages'],
			)
		);
	}

	/**
	 * AJAX filter payments.
	 */
	public static function ajax_filter_payments() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$filters = array(
			'search'            => sanitize_text_field( $_POST['search'] ?? '' ),
			'payment_mode'      => sanitize_key( $_POST['payment_mode'] ?? '' ),
			'balance_status'    => sanitize_key( $_POST['balance_status'] ?? '' ),
			'assignment_status' => sanitize_key( $_POST['assignment_status'] ?? '' ),
			'deleted_status'    => sanitize_key( $_POST['deleted_status'] ?? 'active' ),
			'page'              => max( 1, absint( $_POST['page'] ?? 1 ) ),
		);

		$per_page   = cmp_get_default_per_page();
		$pagination = cmp_get_pagination_data( cmp_get_payments_count( $filters ), $filters['page'], $per_page );
		$payments   = cmp_get_payments(
			array_merge(
				$filters,
				array(
					'limit'  => $pagination['per_page'],
					'offset' => $pagination['offset'],
				)
			)
		);

		ob_start();
		cmp_render_payment_list_section( $payments, $pagination, $filters, $filters['deleted_status'] === 'trash' ? 'cmp-payments-trash' : 'cmp-payments' );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'       => $html,
				'pagination' => '',
				'total'      => $pagination['total'],
				'page'       => $filters['page'],
			)
		);
	}

	/**
	 * AJAX filter batches.
	 */
	public static function ajax_filter_batches() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$class_id = absint( $_POST['class_id'] ?? 0 );
		$status   = sanitize_key( $_POST['status'] ?? '' );

		$batches = cmp_get_batches_with_metrics( $class_id ?: null, $status ?: null );

		ob_start();
		if ( empty( $batches ) ) {
			echo '<p>' . esc_html__( 'No batches found.', 'class-manager-pro' ) . '</p>';
		} else {
			?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Pending', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $batches as $batch ) : ?>
						<tr>
							<td><?php echo esc_html( $batch->batch_name ); ?></td>
							<td><?php echo esc_html( $batch->class_name ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $batch->student_count ) ); ?></td>
							<td><?php echo esc_html( cmp_format_money( (float) $batch->revenue ) ); ?></td>
							<td><?php echo esc_html( cmp_format_money( (float) $batch->pending_amount ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id ) ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'class-manager-pro' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html, 'count' => count( $batches ) ) );
	}

	/**
	 * AJAX refresh dashboard metrics.
	 */
	public static function ajax_refresh_dashboard() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$range     = sanitize_key( $_POST['range'] ?? 'today' );
		$dashboard = CMP_Cache::remember( 'dashboard_' . $range, 'cmp_get_dashboard_snapshot', array( $range ), 300 );

		wp_send_json_success(
			array(
				'metrics' => $dashboard['metrics'],
				'chart_data' => $dashboard['chart_data'],
			)
		);
	}

	/**
	 * AJAX quick search across all entities.
	 */
	public static function ajax_quick_search() {
		if ( ! current_user_can( 'cmp_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'class-manager-pro' ) ) );
		}

		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$query = sanitize_text_field( $_POST['q'] ?? '' );
		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$results = array();

		// Search students.
		$students = cmp_get_students( array( 'search' => $query, 'limit' => 5 ) );
		foreach ( $students as $student ) {
			$results[] = array(
				'type'  => 'student',
				'id'    => $student->id,
				'title' => $student->name,
				'subtitle' => $student->unique_id . ' | ' . $student->phone,
				'url'   => cmp_admin_url( 'cmp-students', array( 'action' => 'view', 'id' => (int) $student->id ) ),
				'icon'  => 'dashicons-groups',
			);
		}

		// Search batches.
		$batches = cmp_get_batches();
		foreach ( $batches as $batch ) {
			if ( false !== stripos( $batch->batch_name, $query ) || false !== stripos( $batch->class_name, $query ) ) {
				$results[] = array(
					'type'     => 'batch',
					'id'       => $batch->id,
					'title'    => $batch->batch_name,
					'subtitle' => $batch->class_name,
					'url'      => cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id ) ),
					'icon'     => 'dashicons-calendar-alt',
				);
			}
		}

		// Search payments.
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';
		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.student_name, p.transaction_id, p.amount
				 FROM {$prefix}payments p
				 WHERE (p.student_name LIKE %s OR p.transaction_id LIKE %s) AND p.is_deleted = 0
				 LIMIT 5",
				'%' . $wpdb->esc_like( $query ) . '%',
				'%' . $wpdb->esc_like( $query ) . '%'
			)
		);

		foreach ( $payments as $payment ) {
			$results[] = array(
				'type'     => 'payment',
				'id'       => $payment->id,
				'title'    => '#' . $payment->id . ' - ' . $payment->student_name,
				'subtitle' => ( $payment->transaction_id ?: __( 'No transaction ID', 'class-manager-pro' ) ) . ' | ' . cmp_format_money( (float) $payment->amount ),
				'url'      => cmp_admin_url( 'cmp-payments', array( 'action' => 'view', 'id' => (int) $payment->id ) ),
				'icon'     => 'dashicons-money-alt',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}
}
