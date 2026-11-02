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

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $auto_table ) ) ) === $auto_table ) {
		$generated_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $auto_table WHERE source_system = %s AND source_batch_id = %d",
				'class-manager-pro',
				$batch_id
			)
		);
	}

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $queue_table ) ) ) === $queue_table ) {
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

/**
 * Helper to get all students of a batch safely.
 *
 * @param int $batch_id Batch ID.
 * @return array Array of student objects.
 */
function cmp_get_batch_students( $batch_id ) {
	if ( function_exists( 'cmp_get_students' ) ) {
		return cmp_get_students( array( 'batch_id' => absint( $batch_id ) ) );
	}
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM " . cmp_table( 'students' ) . " WHERE batch_id = %d ORDER BY name ASC",
			absint( $batch_id )
		)
	);
}

/**
 * Returns comprehensive certificate tracking records and logs for all students in a batch.
 * Integrates with SmartCertify tables if present and CMP certificate logs.
 *
 * @param int $batch_id Batch ID.
 * @return array Array with records and metrics.
 */
function cmp_get_batch_certificate_records( $batch_id ) {
	global $wpdb;

	try {
		$batch_id = absint( $batch_id );
		$batch    = cmp_get_batch( $batch_id );
		if ( ! $batch ) {
			return array(
				'records'          => array(),
				'total_students'   => 0,
				'generated_count'  => 0,
				'downloaded_count' => 0,
				'pending_count'    => 0,
			);
		}

		$students = cmp_get_batch_students( $batch_id );
		if ( empty( $students ) ) {
			return array(
				'records'          => array(),
				'total_students'   => 0,
				'generated_count'  => 0,
				'downloaded_count' => 0,
				'pending_count'    => 0,
			);
		}

	// SmartCertify table names
	$codes_table = $wpdb->prefix . 'smartcertify_codes';
	$certs_table = $wpdb->prefix . 'smartcertify_certificates';
	$logs_table  = $wpdb->prefix . 'smartcertify_logs';

	$has_codes_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $codes_table ) ) ) === $codes_table;
	$has_certs_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $certs_table ) ) ) === $certs_table;
	$has_logs_table  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $logs_table ) ) ) === $logs_table;

	$sc_codes = array();
	$sc_certs = array();
	$sc_logs  = array();

	if ( $has_codes_table ) {
		$code_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $codes_table WHERE batch_id = %d OR (LOWER(TRIM(class_name)) = LOWER(TRIM(%s)))",
				$batch_id,
				$batch->class_name
			)
		);
		foreach ( $code_rows as $cr ) {
			$clean_name = strtolower( trim( (string) $cr->student_name ) );
			$clean_phone = preg_replace( '/\D+/', '', (string) $cr->student_phone );
			if ( $clean_name ) {
				$sc_codes[ 'name_' . $clean_name ] = $cr;
			}
			if ( $clean_phone ) {
				$sc_codes[ 'phone_' . $clean_phone ] = $cr;
			}
		}
	}

	if ( $has_certs_table ) {
		$cert_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $certs_table WHERE batch_id = %d OR (LOWER(TRIM(class_name)) = LOWER(TRIM(%s)) AND LOWER(TRIM(batch_name)) = LOWER(TRIM(%s))) ORDER BY id DESC",
				$batch_id,
				$batch->class_name,
				$batch->batch_name
			)
		);
		foreach ( $cert_rows as $cert ) {
			$clean_name = strtolower( trim( (string) $cert->student_name ) );
			$clean_phone = preg_replace( '/\D+/', '', (string) $cert->student_phone );
			if ( $clean_name && ! isset( $sc_certs[ 'name_' . $clean_name ] ) ) {
				$sc_certs[ 'name_' . $clean_name ] = $cert;
			}
			if ( $clean_phone && ! isset( $sc_certs[ 'phone_' . $clean_phone ] ) ) {
				$sc_certs[ 'phone_' . $clean_phone ] = $cert;
			}
		}
	}

	if ( $has_logs_table ) {
		$log_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $logs_table WHERE batch_id = %d OR (LOWER(TRIM(class_name)) = LOWER(TRIM(%s)) AND LOWER(TRIM(batch_name)) = LOWER(TRIM(%s))) ORDER BY id DESC",
				$batch_id,
				$batch->class_name,
				$batch->batch_name
			)
		);
		foreach ( $log_rows as $lr ) {
			$clean_name = strtolower( trim( (string) $lr->student_name ) );
			if ( $clean_name ) {
				if ( ! isset( $sc_logs[ $clean_name ] ) ) {
					$sc_logs[ $clean_name ] = array();
				}
				$sc_logs[ $clean_name ][] = $lr;
			}
		}
	}

	// CMP internal certificate cache/option
	$cmp_stored_certs = get_option( 'cmp_batch_certs_' . $batch_id, array() );

	$records          = array();
	$generated_count  = 0;
	$downloaded_count = 0;
	$pending_count    = 0;

	foreach ( $students as $st ) {
		$clean_name  = strtolower( trim( (string) $st->name ) );
		$clean_phone = preg_replace( '/\D+/', '', (string) $st->phone );

		// Check SmartCertify matches
		$sc_code = isset( $sc_codes[ 'phone_' . $clean_phone ] ) ? $sc_codes[ 'phone_' . $clean_phone ] : ( isset( $sc_codes[ 'name_' . $clean_name ] ) ? $sc_codes[ 'name_' . $clean_name ] : null );
		$sc_cert = isset( $sc_certs[ 'phone_' . $clean_phone ] ) ? $sc_certs[ 'phone_' . $clean_phone ] : ( isset( $sc_certs[ 'name_' . $clean_name ] ) ? $sc_certs[ 'name_' . $clean_name ] : null );
		$st_logs = isset( $sc_logs[ $clean_name ] ) ? $sc_logs[ $clean_name ] : array();

		// Check CMP internal fallback
		$cmp_cert = isset( $cmp_stored_certs[ $st->id ] ) ? $cmp_stored_certs[ $st->id ] : null;

		$code             = '';
		$serial           = '';
		$generated_at     = '';
		$download_count   = 0;
		$last_download_at = '';
		$pdf_url          = '';
		$verification_url = '';

		if ( $sc_cert ) {
			$code             = $sc_cert->code;
			$serial           = $sc_cert->serial;
			$generated_at     = $sc_cert->generated_at;
			$verification_url = $sc_cert->verification_url;
			if ( ! empty( $sc_cert->file_path ) ) {
				$upload_dir = wp_upload_dir();
				$pdf_url    = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $sc_cert->file_path );
			}
		} elseif ( $sc_code ) {
			$code             = $sc_code->code;
			$download_count   = (int) $sc_code->download_count;
			$generated_at     = $sc_code->last_generated_at ? $sc_code->last_generated_at : $sc_code->created_at;
		}

		if ( $sc_code ) {
			$download_count = max( $download_count, (int) $sc_code->download_count );
		}

		// Count downloads from logs
		if ( ! empty( $st_logs ) ) {
			foreach ( $st_logs as $lg ) {
				if ( in_array( strtolower( (string) $lg->action ), array( 'downloaded', 'download' ), true ) ) {
					$download_count++;
					if ( empty( $last_download_at ) || strtotime( (string) $lg->timestamp ) > strtotime( (string) $last_download_at ) ) {
						$last_download_at = $lg->timestamp;
					}
				}
				if ( empty( $serial ) && ! empty( $lg->serial ) ) {
					$serial = $lg->serial;
				}
				if ( empty( $code ) && ! empty( $lg->code ) ) {
					$code = $lg->code;
				}
			}
		}

		// Apply CMP fallback if present
		if ( $cmp_cert ) {
			if ( empty( $code ) && ! empty( $cmp_cert['code'] ) ) {
				$code = $cmp_cert['code'];
			}
			if ( empty( $serial ) && ! empty( $cmp_cert['serial'] ) ) {
				$serial = $cmp_cert['serial'];
			}
			if ( empty( $generated_at ) && ! empty( $cmp_cert['generated_at'] ) ) {
				$generated_at = $cmp_cert['generated_at'];
			}
			if ( empty( $pdf_url ) && ! empty( $cmp_cert['pdf_url'] ) ) {
				$pdf_url = $cmp_cert['pdf_url'];
			}
			if ( ! empty( $cmp_cert['download_count'] ) ) {
				$download_count = max( $download_count, (int) $cmp_cert['download_count'] );
			}
		}

		// Fallback code if generated
		if ( empty( $code ) && ( $sc_cert || $cmp_cert ) ) {
			$code = 'CMP-B' . $batch_id . '-S' . $st->id;
		}

		// Determine status
		if ( $download_count > 0 ) {
			$status_key   = 'downloaded';
			$status_label = __( 'Downloaded', 'class-manager-pro' );
			$downloaded_count++;
			$generated_count++;
		} elseif ( ! empty( $sc_cert ) || ! empty( $cmp_cert ) || ! empty( $generated_at ) ) {
			$status_key   = 'generated';
			$status_label = __( 'Generated / Ready', 'class-manager-pro' );
			$generated_count++;
		} else {
			$status_key   = 'not_generated';
			$status_label = __( 'Not Generated', 'class-manager-pro' );
			$pending_count++;
		}

		// WhatsApp pre-filled share link
		$wa_link = '';
		if ( ! empty( $clean_phone ) ) {
			$phone_full = strlen( $clean_phone ) === 10 ? '91' . $clean_phone : $clean_phone;
			$share_msg  = sprintf(
				/* translators: 1: student name 2: batch name 3: code */
				__( 'Hello %1$s, your certificate for %2$s has been generated! Certificate Code: %3$s.', 'class-manager-pro' ),
				$st->name,
				$batch->batch_name,
				$code ? $code : '-'
			);
			if ( ! empty( $pdf_url ) ) {
				$share_msg .= "\n" . __( 'Download PDF:', 'class-manager-pro' ) . ' ' . $pdf_url;
			}
			$wa_link = 'https://wa.me/' . $phone_full . '?text=' . rawurlencode( $share_msg );
		}

		$records[] = array(
			'student_id'       => (int) $st->id,
			'student_name'     => $st->name,
			'phone'            => $st->phone,
			'email'            => $st->email,
			'status'           => $status_key,
			'status_label'     => $status_label,
			'code'             => $code,
			'serial'           => $serial,
			'generated_at'     => $generated_at,
			'download_count'   => $download_count,
			'last_download_at' => $last_download_at,
			'pdf_url'          => $pdf_url,
			'verification_url' => $verification_url,
			'wa_link'          => $wa_link,
		);
	}

		return array(
			'records'          => $records,
			'total_students'   => count( $students ),
			'generated_count'  => $generated_count,
			'downloaded_count' => $downloaded_count,
			'pending_count'    => $pending_count,
		);
	} catch ( Throwable $e ) {
		return array(
			'records'          => array(),
			'total_students'   => 0,
			'generated_count'  => 0,
			'downloaded_count' => 0,
			'pending_count'    => 0,
		);
	}
}

/**
 * Generates certificates for all students in a batch automatically or on-demand.
 *
 * @param int  $batch_id Batch ID.
 * @param bool $force    Force regeneration even if already generated.
 * @return array Result summary.
 */
function cmp_auto_generate_batch_certificates( $batch_id, $force = false ) {
	global $wpdb;

	try {
		$batch_id = absint( $batch_id );
		$batch    = cmp_get_batch( $batch_id );
		if ( ! $batch ) {
			return array( 'success' => false, 'message' => __( 'Batch not found.', 'class-manager-pro' ), 'generated' => 0 );
		}

		$students = cmp_get_batch_students( $batch_id );
		if ( empty( $students ) ) {
			return array( 'success' => true, 'message' => __( 'No students in this batch.', 'class-manager-pro' ), 'generated' => 0 );
		}

	$now_mysql = current_time( 'mysql' );
	$cmp_stored = get_option( 'cmp_batch_certs_' . $batch_id, array() );
	if ( ! is_array( $cmp_stored ) ) {
		$cmp_stored = array();
	}

	// SmartCertify database tables
	$classes_table = $wpdb->prefix . 'smartcertify_classes';
	$batches_table = $wpdb->prefix . 'smartcertify_batches';
	$codes_table   = $wpdb->prefix . 'smartcertify_codes';
	$certs_table   = $wpdb->prefix . 'smartcertify_certificates';

	$has_sc = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $codes_table ) ) ) === $codes_table;

	$sc_class_id = 0;
	$sc_batch_id = 0;

	if ( $has_sc ) {
		// 1. Ensure SmartCertify Class exists
		$existing_sc_class = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $classes_table WHERE LOWER(TRIM(class_name)) = LOWER(TRIM(%s)) LIMIT 1",
				$batch->class_name
			)
		);
		if ( $existing_sc_class ) {
			$sc_class_id = (int) $existing_sc_class->id;
		} else {
			$wpdb->insert(
				$classes_table,
				array(
					'class_name' => $batch->class_name,
					'created_at' => $now_mysql,
				),
				array( '%s', '%s' )
			);
			$sc_class_id = (int) $wpdb->insert_id;
		}

		// 2. Ensure SmartCertify Batch exists
		$existing_sc_batch = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $batches_table WHERE class_id = %d AND LOWER(TRIM(batch_name)) = LOWER(TRIM(%s)) LIMIT 1",
				$sc_class_id,
				$batch->batch_name
			)
		);
		if ( $existing_sc_batch ) {
			$sc_batch_id = (int) $existing_sc_batch->id;
		} else {
			$wpdb->insert(
				$batches_table,
				array(
					'class_id'     => $sc_class_id,
					'batch_name'   => $batch->batch_name,
					'teacher_name' => cmp_get_teacher_label( (int) $batch->teacher_user_id ),
					'is_active'    => 1,
					'created_at'   => $now_mysql,
				),
				array( '%d', '%s', '%s', '%d', '%s' )
			);
			$sc_batch_id = (int) $wpdb->insert_id;
		}
	}

	$generated_count = 0;

	foreach ( $students as $st ) {
		$student_id = (int) $st->id;
		$code       = 'CMP-B' . $batch_id . '-S' . $student_id;
		$serial     = 'SC-' . strtoupper( substr( md5( $batch_id . '-' . $student_id . '-' . $st->name ), 0, 8 ) );

		// If SmartCertify is present, insert/ensure code in smartcertify_codes
		if ( $has_sc && $sc_class_id && $sc_batch_id ) {
			$existing_code = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, code FROM $codes_table WHERE (batch_id = %d AND LOWER(TRIM(student_name)) = LOWER(TRIM(%s))) OR code = %s LIMIT 1",
					$sc_batch_id,
					$st->name,
					$code
				)
			);

			if ( ! $existing_code ) {
				$wpdb->insert(
					$codes_table,
					array(
						'class_id'          => $sc_class_id,
						'batch_id'          => $sc_batch_id,
						'class_name'        => $batch->class_name,
						'code'              => $code,
						'student_name'      => $st->name,
						'student_email'     => $st->email ? $st->email : '',
						'student_phone'     => $st->phone ? $st->phone : '',
						'status'            => 'active',
						'download_count'    => 0,
						'download_limit'    => 5,
						'last_generated_at' => $now_mysql,
						'created_at'        => $now_mysql,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
				);
			} elseif ( $existing_code->code ) {
				$code = $existing_code->code;
			}

			// If SmartCert_Service exists, issue official certificate
			if ( class_exists( 'SmartCert_Service' ) ) {
				$issue_res = SmartCert_Service::issue_certificate( array(
					'class_id'      => $sc_class_id,
					'batch_id'      => $sc_batch_id,
					'student_name'  => $st->name,
					'student_email' => $st->email,
					'student_phone' => $st->phone,
					'code'          => $code,
					'serial'        => $serial,
					'generated_at'  => $now_mysql,
				) );
				if ( ! is_wp_error( $issue_res ) && is_array( $issue_res ) && ! empty( $issue_res['serial'] ) ) {
					$serial = $issue_res['serial'];
				}
			}
		}

		// Update CMP internal tracking
		$cmp_stored[ $student_id ] = array(
			'student_id'   => $student_id,
			'student_name' => $st->name,
			'code'         => $code,
			'serial'       => $serial,
			'generated_at' => $now_mysql,
			'status'       => 'generated',
		);

		$generated_count++;
	}

	update_option( 'cmp_batch_certs_' . $batch_id, $cmp_stored );

	// Trigger SmartCertify batch completed hook
	do_action(
		'cmp_smartcertify_batch_completed',
		array(
			'source_plugin' => 'class-manager-pro',
			'batch_id'      => $batch_id,
			'class_id'      => (int) $batch->class_id,
			'batch_name'    => $batch->batch_name,
			'class_name'    => $batch->class_name,
			'status'        => 'completed',
			'timestamp'     => $now_mysql,
		)
	);

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Certificates successfully generated for %d students in batch "%s".', 'class-manager-pro' ), $generated_count, $batch->batch_name ),
			'generated' => $generated_count,
		);
	} catch ( Throwable $e ) {
		return array(
			'success'   => false,
			'message'   => $e->getMessage(),
			'generated' => 0,
		);
	}
}

/**
 * POST action handler to manually generate/regenerate certificates for all students in a batch.
 */
function cmp_handle_generate_batch_certificates() {
	if ( function_exists( 'cmp_require_manage_options' ) ) {
		cmp_require_manage_options();
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized user.', 'class-manager-pro' ) );
	}

	$batch_id = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_generate_batch_certificates_' . $batch_id );

	$res = cmp_auto_generate_batch_certificates( $batch_id, true );

	cmp_redirect(
		'cmp-batches',
		$res['message'],
		$res['success'] ? 'success' : 'error',
		array(
			'action' => 'view',
			'id'     => $batch_id,
			'tab'    => 'certificates',
		)
	);
}
add_action( 'admin_post_cmp_generate_batch_certificates', 'cmp_handle_generate_batch_certificates' );

/**
 * POST action handler to generate certificate for a single student.
 */
function cmp_handle_generate_single_student_certificate() {
	if ( function_exists( 'cmp_require_manage_options' ) ) {
		cmp_require_manage_options();
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized user.', 'class-manager-pro' ) );
	}

	$student_id = absint( cmp_field( $_POST, 'student_id', 0 ) );
	$batch_id   = absint( cmp_field( $_POST, 'batch_id', 0 ) );
	check_admin_referer( 'cmp_generate_single_cert_' . $student_id );

	$student = cmp_get_student( $student_id );
	$batch   = cmp_get_batch( $batch_id );

	if ( ! $student || ! $batch ) {
		cmp_redirect( 'cmp-batches', __( 'Student or batch not found.', 'class-manager-pro' ), 'error', array( 'action' => 'view', 'id' => $batch_id, 'tab' => 'certificates' ) );
	}

	$now_mysql = current_time( 'mysql' );
	$code      = 'CMP-B' . $batch_id . '-S' . $student_id;
	$serial    = 'SC-' . strtoupper( substr( md5( $batch_id . '-' . $student_id . '-' . $student->name ), 0, 8 ) );

	$cmp_stored = get_option( 'cmp_batch_certs_' . $batch_id, array() );
	if ( ! is_array( $cmp_stored ) ) {
		$cmp_stored = array();
	}

	$cmp_stored[ $student_id ] = array(
		'student_id'   => $student_id,
		'student_name' => $student->name,
		'code'         => $code,
		'serial'       => $serial,
		'generated_at' => $now_mysql,
		'status'       => 'generated',
	);

	update_option( 'cmp_batch_certs_' . $batch_id, $cmp_stored );

	cmp_redirect(
		'cmp-batches',
		sprintf( __( 'Certificate generated successfully for "%s" (Code: %s).', 'class-manager-pro' ), $student->name, $code ),
		'success',
		array(
			'action' => 'view',
			'id'     => $batch_id,
			'tab'    => 'certificates',
		)
	);
}
add_action( 'admin_post_cmp_generate_single_student_certificate', 'cmp_handle_generate_single_student_certificate' );
