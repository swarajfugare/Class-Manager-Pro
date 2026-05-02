<?php
/**
 * Razorpay webhook integration.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for Razorpay webhooks.
 */
class CMP_Razorpay_Webhook {
	/**
	 * Registers REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'cmp/v1',
			'/razorpay-webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles a Razorpay webhook request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
		$secret = (string) get_option( 'cmp_razorpay_webhook_secret', '' );
		$body   = $request->get_body();

		if ( '' === $secret ) {
			cmp_log_event( 'error', 'Razorpay webhook rejected because the secret is missing.' );
			return new WP_Error( 'cmp_webhook_secret_missing', __( 'Webhook secret is not configured.', 'class-manager-pro' ), array( 'status' => 403 ) );
		}

		$signature = $request->get_header( 'x-razorpay-signature' );
		$expected  = hash_hmac( 'sha256', $body, $secret );

		if ( ! $signature || ! hash_equals( $expected, $signature ) ) {
			cmp_log_event( 'error', 'Razorpay webhook signature verification failed.' );
			return new WP_Error( 'cmp_webhook_invalid_signature', __( 'Invalid webhook signature.', 'class-manager-pro' ), array( 'status' => 403 ) );
		}

		$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) ) {
			cmp_log_event( 'error', 'Razorpay webhook payload could not be decoded.' );
			return new WP_Error( 'cmp_webhook_invalid_payload', __( 'Invalid webhook payload.', 'class-manager-pro' ), array( 'status' => 400 ) );
		}

		$payment      = self::extract_payment_entity( $payload );
		$payment_link = self::extract_payment_link_entity( $payload );

		if ( empty( $payment ) && empty( $payment_link ) ) {
			cmp_log_event( 'error', 'Razorpay webhook did not include payment data.' );
			return new WP_Error( 'cmp_webhook_payment_missing', __( 'Payment data was not found in webhook payload.', 'class-manager-pro' ), array( 'status' => 400 ) );
		}

		if ( ! self::is_successful_payment_payload( $payload, $payment, $payment_link ) ) {
			self::capture_failed_payment_interest( $payload, $payment, $payment_link );
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Webhook ignored because the payment is not completed.',
				)
			);
		}

		$transaction_id = sanitize_text_field(
			self::first_non_empty(
				array(
					$payment['id'] ?? '',
					$payment_link['payment_id'] ?? '',
					$payment_link['id'] ?? '',
				)
			)
		);

		if ( '' !== $transaction_id && cmp_payment_exists( $transaction_id ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Duplicate webhook ignored.',
				)
			);
		}

		$payment_meta          = cmp_razorpay_entity_meta( $payment );
		$payment_customer      = isset( $payment['customer'] ) && is_array( $payment['customer'] ) ? $payment['customer'] : array();
		$payment_link_meta     = cmp_razorpay_entity_meta( $payment_link );
		$payment_link_customer = isset( $payment_link['customer'] ) && is_array( $payment_link['customer'] ) ? $payment_link['customer'] : array();
		$meta                  = array_merge( $payment_link_meta, $payment_meta );
		$batch                 = null;

		$name     = self::first_non_empty( array( $meta['name'] ?? '', $meta['student_name'] ?? '', $payment['name'] ?? '', $payment['customer_name'] ?? '', $payment_customer['name'] ?? '', $payment_link['customer_name'] ?? '', $payment_link_customer['name'] ?? '' ) );
		$email    = sanitize_email( self::first_non_empty( array( $meta['email'] ?? '', $meta['student_email'] ?? '', $payment['email'] ?? '', $payment_customer['email'] ?? '', $payment_link['email'] ?? '', $payment_link['customer_email'] ?? '', $payment_link_customer['email'] ?? '' ) ) );
		$phone    = sanitize_text_field( self::first_non_empty( array( $meta['phone'] ?? '', $meta['mobile'] ?? '', $meta['contact'] ?? '', $payment['contact'] ?? '', $payment['phone'] ?? '', $payment_customer['contact'] ?? '', $payment_link['contact'] ?? '', $payment_link['customer_contact'] ?? '', $payment_link_customer['contact'] ?? '' ) ) );
		$class_id = isset( $meta['class_id'] ) ? absint( $meta['class_id'] ) : 0;
		$batch_id = isset( $meta['batch_id'] ) ? absint( $meta['batch_id'] ) : 0;
		$amount   = self::amount_from_minor_units( self::first_non_empty( array( $payment['amount'] ?? '', $payment_link['amount_paid'] ?? '', $payment_link['amount'] ?? '' ) ) );

		if ( $amount <= 0 ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Webhook ignored because no successful paid amount was found.',
				)
			);
		}

		if ( ! $batch_id ) {
			$batch_token = self::first_non_empty( array( $meta['batch_token'] ?? '', $meta['cmp_batch_token'] ?? '', $meta['public_token'] ?? '' ) );
			$batch       = $batch_token ? cmp_get_batch_by_token( $batch_token ) : null;
			$batch_id    = $batch ? (int) $batch->id : 0;
		}

		if ( ! $batch_id ) {
			$batch = cmp_get_batch_by_razorpay_reference(
				array(
					$meta['razorpay_link'] ?? '',
					$meta['payment_link'] ?? '',
					$meta['payment_link_id'] ?? '',
					$payment['payment_link_id'] ?? '',
					$payment['payment_link_reference_id'] ?? '',
					$payment_link['id'] ?? '',
					$payment_link['short_url'] ?? '',
					$payment_link['reference_id'] ?? '',
				)
			);
			$batch_id = $batch ? (int) $batch->id : 0;
		}

		if ( $batch_id && ! $batch ) {
			$batch = cmp_get_batch( $batch_id );
		}

		if ( $batch && ! $class_id ) {
			$class_id = (int) $batch->class_id;
		}

		$recent_match = cmp_get_recent_intake_match( $phone, $email );

		if ( $recent_match && empty( $batch_id ) && ! empty( $recent_match['batch_id'] ) ) {
			$batch_id = (int) $recent_match['batch_id'];
			$batch    = cmp_get_batch( $batch_id );
			$class_id = $batch && ! $class_id ? (int) $batch->class_id : $class_id;
		}

		if ( '' === $name && '' !== $email ) {
			$name = $email;
		}

		if ( '' === $name && '' !== $phone ) {
			$name = $phone;
		}

		if ( '' === $phone ) {
			$phone = '' !== $email ? $email : $transaction_id;
		}

		if ( '' === $name && '' !== $phone ) {
			$name = $phone;
		}

		$student = null;

		if ( $recent_match && ! empty( $recent_match['student_id'] ) && ( ! $batch_id || empty( $recent_match['batch_id'] ) || (int) $recent_match['batch_id'] === (int) $batch_id ) ) {
			$student = cmp_get_student( (int) $recent_match['student_id'] );
		}

		if ( ! $student && $batch_id ) {
			$student = cmp_find_student_in_batch_by_contact( $batch_id, $phone, $email );
		}

		if ( ! $student ) {
			$student = cmp_find_reusable_import_student( $batch, $name, $email, $phone, $class_id );
		}

		if ( ! $student ) {
			$class = $class_id ? cmp_get_class( $class_id ) : null;
			$fee   = $batch ? cmp_get_batch_effective_fee( $batch ) : ( $class ? (float) $class->total_fee : 0 );

			$student_id = cmp_insert_student(
				array(
					'name'      => $name,
					'phone'     => $phone,
					'email'     => $email,
					'class_id'  => $class_id,
					'batch_id'  => $batch_id,
					'total_fee' => isset( $meta['total_fee'] ) ? cmp_money_value( $meta['total_fee'] ) : $fee,
					'paid_fee'  => 0,
					'status'    => 'active',
					'notes'     => __( 'Created by Razorpay webhook.', 'class-manager-pro' ),
				)
			);

			if ( is_wp_error( $student_id ) ) {
				cmp_log_event( 'error', 'Razorpay webhook student creation failed.', array( 'message' => $student_id->get_error_message() ) );
				return new WP_Error( $student_id->get_error_code(), $student_id->get_error_message(), array( 'status' => 400 ) );
			}
		} else {
			$student_id = (int) $student->id;
			self::maybe_update_student_assignment( $student, $class_id, $batch_id );
		}

		$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
			? wp_date( 'Y-m-d H:i:s', (int) $payment['created_at'] )
			: cmp_current_datetime();

		$result = cmp_insert_payment(
			array(
				'student_id'     => $student_id,
				'class_id'       => $class_id,
				'batch_id'       => $batch_id,
				'amount'         => $amount,
				'apply_gateway_charge' => 1,
				'payment_mode'   => 'razorpay',
				'transaction_id' => $transaction_id,
				'payment_date'   => $payment_date,
			)
		);

		if ( is_wp_error( $result ) ) {
			cmp_log_event( 'error', 'Razorpay webhook payment insert failed.', array( 'message' => $result->get_error_message(), 'transaction_id' => $transaction_id ) );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		cmp_sync_student_tutor_enrollment( $student_id );

		return rest_ensure_response(
			array(
				'success'    => true,
				'student_id' => $student_id,
				'payment_id' => (int) $result,
			)
		);
	}

	/**
	 * Extracts the payment entity from common Razorpay payload shapes.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private static function extract_payment_entity( $payload ) {
		if ( isset( $payload['payload']['payment']['entity'] ) && is_array( $payload['payload']['payment']['entity'] ) ) {
			return $payload['payload']['payment']['entity'];
		}

		if ( isset( $payload['payment']['entity'] ) && is_array( $payload['payment']['entity'] ) ) {
			return $payload['payment']['entity'];
		}

		if ( isset( $payload['payment'] ) && is_array( $payload['payment'] ) ) {
			return $payload['payment'];
		}

		return isset( $payload['id'], $payload['amount'] ) ? $payload : array();
	}

	/**
	 * Extracts the payment link entity from common Razorpay payload shapes.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private static function extract_payment_link_entity( $payload ) {
		if ( isset( $payload['payload']['payment_link']['entity'] ) && is_array( $payload['payload']['payment_link']['entity'] ) ) {
			return $payload['payload']['payment_link']['entity'];
		}

		if ( isset( $payload['payment_link']['entity'] ) && is_array( $payload['payment_link']['entity'] ) ) {
			return $payload['payment_link']['entity'];
		}

		if ( isset( $payload['payment_link'] ) && is_array( $payload['payment_link'] ) ) {
			return $payload['payment_link'];
		}

		if ( isset( $payload['id'] ) && is_scalar( $payload['id'] ) && 0 === strpos( (string) $payload['id'], 'plink_' ) ) {
			return $payload;
		}

		return array();
	}

	/**
	 * Confirms that the webhook represents a completed payment.
	 *
	 * @param array $payload Payload.
	 * @param array $payment Payment entity.
	 * @param array $payment_link Payment link entity.
	 * @return bool
	 */
	private static function is_successful_payment_payload( $payload, $payment, $payment_link ) {
		$event          = isset( $payload['event'] ) && is_scalar( $payload['event'] ) ? strtolower( (string) $payload['event'] ) : '';
		$payment_status = isset( $payment['status'] ) && is_scalar( $payment['status'] ) ? strtolower( (string) $payment['status'] ) : '';
		$link_status    = isset( $payment_link['status'] ) && is_scalar( $payment_link['status'] ) ? strtolower( (string) $payment_link['status'] ) : '';
		$amount_paid    = isset( $payment_link['amount_paid'] ) && is_numeric( $payment_link['amount_paid'] ) ? (float) $payment_link['amount_paid'] : 0;
		$captured       = ! isset( $payment['captured'] ) || true === filter_var( $payment['captured'], FILTER_VALIDATE_BOOLEAN );

		if ( false !== strpos( $event, 'failed' ) || false !== strpos( $event, 'cancelled' ) || false !== strpos( $event, 'expired' ) ) {
			return false;
		}

		if ( in_array( $payment_status, array( 'failed', 'created', 'authorized', 'refunded', 'refund_pending' ), true ) ) {
			return false;
		}

		if ( in_array( $link_status, array( 'created', 'issued', 'cancelled', 'expired' ), true ) ) {
			return false;
		}

		if ( ! empty( $payment ) ) {
			if ( 'captured' !== $payment_status || ! $captured || ! empty( $payment['error_code'] ) || ! empty( $payment['error_description'] ) ) {
				return false;
			}

			return in_array( $event, array( 'payment.captured', 'payment_link.paid' ), true );
		}

		if ( ! empty( $payment_link ) ) {
			return 'payment_link.paid' === $event && 'paid' === $link_status && $amount_paid > 0;
		}

		return false;
	}

	/**
	 * Stores failed Razorpay webhook attempts in the interested student list.
	 *
	 * @param array $payload Webhook payload.
	 * @param array $payment Payment entity.
	 * @param array $payment_link Payment Link entity.
	 * @return void
	 */
	private static function capture_failed_payment_interest( $payload, $payment, $payment_link ) {
		$event          = isset( $payload['event'] ) && is_scalar( $payload['event'] ) ? strtolower( (string) $payload['event'] ) : '';
		$payment_status = isset( $payment['status'] ) && is_scalar( $payment['status'] ) ? strtolower( (string) $payment['status'] ) : '';
		$link_status    = isset( $payment_link['status'] ) && is_scalar( $payment_link['status'] ) ? strtolower( (string) $payment_link['status'] ) : '';
		$is_failed      = false !== strpos( $event, 'failed' ) || false !== strpos( $event, 'cancelled' ) || false !== strpos( $event, 'expired' ) || 'failed' === $payment_status || in_array( $link_status, array( 'cancelled', 'expired' ), true ) || ! empty( $payment['error_code'] ) || ! empty( $payment['error_description'] );

		if ( ! $is_failed ) {
			return;
		}

		$payment_meta          = cmp_razorpay_entity_meta( $payment );
		$payment_customer      = isset( $payment['customer'] ) && is_array( $payment['customer'] ) ? $payment['customer'] : array();
		$payment_link_meta     = cmp_razorpay_entity_meta( $payment_link );
		$payment_link_customer = isset( $payment_link['customer'] ) && is_array( $payment_link['customer'] ) ? $payment_link['customer'] : array();
		$meta                  = array_merge( $payment_link_meta, $payment_meta );
		$batch                 = null;
		$class_id              = isset( $meta['class_id'] ) ? absint( $meta['class_id'] ) : 0;
		$batch_id              = isset( $meta['batch_id'] ) ? absint( $meta['batch_id'] ) : 0;
		$name                  = self::first_non_empty( array( $meta['name'] ?? '', $meta['student_name'] ?? '', $payment['name'] ?? '', $payment['customer_name'] ?? '', $payment_customer['name'] ?? '', $payment_link['customer_name'] ?? '', $payment_link_customer['name'] ?? '' ) );
		$email                 = sanitize_email( self::first_non_empty( array( $meta['email'] ?? '', $meta['student_email'] ?? '', $payment['email'] ?? '', $payment_customer['email'] ?? '', $payment_link['email'] ?? '', $payment_link['customer_email'] ?? '', $payment_link_customer['email'] ?? '' ) ) );
		$phone                 = sanitize_text_field( self::first_non_empty( array( $meta['phone'] ?? '', $meta['mobile'] ?? '', $meta['contact'] ?? '', $payment['contact'] ?? '', $payment['phone'] ?? '', $payment_customer['contact'] ?? '', $payment_link['contact'] ?? '', $payment_link['customer_contact'] ?? '', $payment_link_customer['contact'] ?? '' ) ) );
		$transaction_id        = sanitize_text_field( self::first_non_empty( array( $payment['id'] ?? '', $payment_link['payment_id'] ?? '', $payment_link['id'] ?? '' ) ) );
		$amount                = self::amount_from_minor_units( self::first_non_empty( array( $payment['amount'] ?? '', $payment_link['amount_paid'] ?? '', $payment_link['amount'] ?? '' ) ) );
		$payment_status_label  = self::first_non_empty( array( $payment_status, $link_status, $event, 'failed' ) );
		$failure_note          = self::first_non_empty( array( $payment['error_description'] ?? '', $payment['error_code'] ?? '', $payload['event'] ?? '', __( 'Captured from a failed Razorpay webhook attempt.', 'class-manager-pro' ) ) );

		if ( ! $batch_id ) {
			$batch_token = self::first_non_empty( array( $meta['batch_token'] ?? '', $meta['cmp_batch_token'] ?? '', $meta['public_token'] ?? '' ) );
			$batch       = $batch_token ? cmp_get_batch_by_token( $batch_token ) : null;
			$batch_id    = $batch ? (int) $batch->id : 0;
		}

		if ( ! $batch_id ) {
			$batch = cmp_get_batch_by_razorpay_reference(
				array(
					$meta['razorpay_link'] ?? '',
					$meta['payment_link'] ?? '',
					$meta['payment_link_id'] ?? '',
					$payment['payment_link_id'] ?? '',
					$payment['payment_link_reference_id'] ?? '',
					$payment_link['id'] ?? '',
					$payment_link['short_url'] ?? '',
					$payment_link['reference_id'] ?? '',
				)
			);
			$batch_id = $batch ? (int) $batch->id : 0;
		}

		if ( $batch_id && ! $batch ) {
			$batch = cmp_get_batch( $batch_id );
		}

		if ( $batch && ! $class_id ) {
			$class_id = (int) $batch->class_id;
		}

		if ( '' === $name ) {
			$name = '' !== $email ? $email : ( '' !== $phone ? $phone : $transaction_id );
		}

		if ( '' === $phone && '' === $email ) {
			return;
		}

		cmp_record_failed_payment_interest(
			array(
				'name'           => $name,
				'phone'          => $phone,
				'email'          => $email,
				'class_id'       => $class_id,
				'batch_id'       => $batch_id,
				'payment_status' => $payment_status_label,
				'payment_source' => 'razorpay_webhook',
				'attempt_amount' => $amount,
				'transaction_id' => $transaction_id,
				'notes'          => sanitize_text_field( $failure_note ),
				'payment_meta'   => array(
					'event'   => $event,
					'payment' => $payment,
					'link'    => $payment_link,
				),
			)
		);
	}

	/**
	 * Converts Razorpay minor units (paise) to a money value.
	 *
	 * @param mixed $amount Amount in minor units.
	 * @return float
	 */
	private static function amount_from_minor_units( $amount ) {
		if ( ! is_numeric( $amount ) ) {
			return 0;
		}

		return round( (float) $amount / 100, 2 );
	}

	/**
	 * Returns the first non-empty scalar value.
	 *
	 * @param array $values Values.
	 * @return string
	 */
	private static function first_non_empty( $values ) {
		foreach ( $values as $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}

	/**
	 * Assigns class/batch to an existing student when the webhook provides useful data.
	 *
	 * @param object $student Student row.
	 * @param int    $class_id Class ID.
	 * @param int    $batch_id Batch ID.
	 */
	private static function maybe_update_student_assignment( $student, $class_id, $batch_id ) {
		global $wpdb;

		$updates = array();
		$formats = array();
		$batch   = $batch_id ? cmp_get_batch( $batch_id ) : null;

		if ( $batch && ! $class_id ) {
			$class_id = (int) $batch->class_id;
		}

		if ( empty( $student->class_id ) && $class_id && cmp_get_class( $class_id ) ) {
			$updates['class_id'] = $class_id;
			$formats[]           = '%d';
		}

		$target_class_id = isset( $updates['class_id'] ) ? (int) $updates['class_id'] : (int) $student->class_id;

		if ( $batch && empty( $student->batch_id ) && (int) $batch->class_id === $target_class_id ) {
			$updates['batch_id'] = $batch_id;
			$formats[]           = '%d';
		}

		if ( empty( $student->total_fee ) && $batch ) {
			$updates['total_fee'] = cmp_get_batch_effective_fee( $batch );
			$formats[]            = '%f';
		}

		if ( $updates ) {
			$wpdb->update( cmp_table( 'students' ), $updates, array( 'id' => (int) $student->id ), $formats, array( '%d' ) );
		}
	}
}
