<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cmp_smartcertify_is_available() {
	return defined( 'SMARTCERTIFY_VERSION' ) || function_exists( 'smartcertify_enqueue_assets' ) || function_exists( 'smartcertify_cmp_is_available' );
}

function cmp_smartcertify_get_version() {
	return defined( 'SMARTCERTIFY_VERSION' ) ? SMARTCERTIFY_VERSION : '';
}

function cmp_smartcertify_is_enabled() {
	return '1' === (string) get_option( 'cmp_smartcertify_integration_enabled', '0' );
}

function cmp_smartcertify_get_last_sync_status() {
	$status = sanitize_text_field( (string) get_option( 'cmp_smartcertify_last_sync_status', '' ) );
	return '' !== $status ? $status : __( 'Not started', 'class-manager-pro' );
}

function cmp_smartcertify_get_connection_status() {
	if ( ! cmp_smartcertify_is_available() ) {
		return array(
			'state' => 'missing',
			'label' => __( 'SmartCertify not detected', 'class-manager-pro' ),
		);
	}

	if ( ! cmp_smartcertify_is_enabled() ) {
		return array(
			'state' => 'disabled',
			'label' => __( 'Integration disabled', 'class-manager-pro' ),
		);
	}

	return array(
		'state' => 'connected',
		'label' => __( 'Ready to sync', 'class-manager-pro' ),
	);
}

function cmp_smartcertify_get_status_summary() {
	$connection = cmp_smartcertify_get_connection_status();

	return array(
		'enabled'          => cmp_smartcertify_is_enabled(),
		'connection_state' => $connection['state'],
		'connection_label' => $connection['label'],
		'plugin_version'   => cmp_smartcertify_get_version() ? cmp_smartcertify_get_version() : __( 'Not detected', 'class-manager-pro' ),
		'last_sync_status' => cmp_smartcertify_get_last_sync_status(),
	);
}

function cmp_smartcertify_get_class_mapping_by_class_id( $class_id ) {
	global $wpdb;

	$class_id = absint( $class_id );
	if ( ! $class_id ) {
		return null;
	}

	$table = $wpdb->prefix . 'smartcertify_integration_mappings';

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE source_system = %s AND source_type = %s AND source_id = %d LIMIT 1",
			'class-manager-pro',
			'class',
			$class_id
		)
	);
}

/**
 * Backward-compatible alias for SmartCertify-side integration code.
 *
 * @param int $class_id Source class ID.
 * @return object|null
 */
function smartcertify_cmp_get_class_mapping_by_class_id( $class_id ) {
	return cmp_smartcertify_get_class_mapping_by_class_id( $class_id );
}

function cmp_smartcertify_get_batch_auto_summary( $batch_id ) {
	global $wpdb;

	$batch_id = absint( $batch_id );
	if ( ! $batch_id || ! cmp_smartcertify_is_available() ) {
		return array(
			'generated_count' => 0,
			'queue_status'    => __( 'Not connected', 'class-manager-pro' ),
			'pending_count'   => 0,
			'processing_count'=> 0,
			'failed_count'    => 0,
		);
	}

	$auto_table = $wpdb->prefix . 'smartcertify_auto_certificates';
	$queue_table = $wpdb->prefix . 'smartcertify_auto_certificate_queue';

	$generated_count = 0;
	$pending_count   = 0;
	$processing_count = 0;
	$failed_count    = 0;
	$queue_status    = __( 'Not queued', 'class-manager-pro' );

	if ( $wpdb->get_var( "SHOW TABLES LIKE '$auto_table'" ) === $auto_table ) {
		$generated_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $auto_table WHERE source_system = %s AND source_batch_id = %d",
				'class-manager-pro',
				$batch_id
			)
		);
	}

	if ( $wpdb->get_var( "SHOW TABLES LIKE '$queue_table'" ) === $queue_table ) {
		$pending_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $queue_table WHERE source_system = %s AND source_batch_id = %d AND status = %s",
				'class-manager-pro',
				$batch_id,
				'pending'
			)
		);
		$processing_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $queue_table WHERE source_system = %s AND source_batch_id = %d AND status = %s",
				'class-manager-pro',
				$batch_id,
				'processing'
			)
		);
		$failed_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $queue_table WHERE source_system = %s AND source_batch_id = %d AND status = %s",
				'class-manager-pro',
				$batch_id,
				'failed'
			)
		);

		$queue_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $queue_table WHERE source_system = %s AND source_batch_id = %d ORDER BY id DESC LIMIT 1",
				'class-manager-pro',
				$batch_id
			)
		);

		if ( $queue_row ) {
			$queue_status = ucfirst( sanitize_text_field( $queue_row->status ) );
		}
	}

	return array(
		'generated_count'  => $generated_count,
		'queue_status'     => $queue_status,
		'pending_count'    => $pending_count,
		'processing_count' => $processing_count,
		'failed_count'     => $failed_count,
	);
}

function cmp_smartcertify_request_generate_again( $batch_id ) {
	$batch_id = absint( $batch_id );
	$batch = cmp_get_batch( $batch_id );

	if ( ! $batch ) {
		return new WP_Error( 'cmp_batch_missing', __( 'Batch not found.', 'class-manager-pro' ) );
	}

	if ( 'completed' !== (string) $batch->status ) {
		return new WP_Error( 'cmp_batch_not_completed', __( 'Certificates can only be generated again for completed batches.', 'class-manager-pro' ) );
	}

	if ( ! cmp_smartcertify_is_available() ) {
		return new WP_Error( 'cmp_smartcertify_missing', __( 'SmartCertify is not available on this site.', 'class-manager-pro' ) );
	}

	if ( function_exists( 'smartcertify_cmp_auto_request_regenerate_batch' ) ) {
		$result = smartcertify_cmp_auto_request_regenerate_batch( $batch_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	do_action(
		'cmp_smartcertify_batch_completed',
		array(
			'source_plugin' => 'class-manager-pro',
			'batch_id'      => $batch_id,
			'class_id'      => (int) $batch->class_id,
			'batch_name'    => $batch->batch_name,
			'class_name'    => $batch->class_name,
			'status'        => 'completed',
			'manual_retry'  => true,
			'force'         => true,
			'timestamp'     => current_time( 'mysql' ),
		)
	);

	return true;
}
