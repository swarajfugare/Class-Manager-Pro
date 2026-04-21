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
			return new WP_Error( 'cmp_webhook_secret_missing', __( 'Webhook secret is not configured.', 'class-manager-pro' ), array( 'status' => 403 ) );
		}

		$signature = $request->get_header( 'x-razorpay-signature' );
		$expected  = hash_hmac( 'sha256', $body, $secret );

		if ( ! $signature || ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'cmp_webhook_invalid_signature', __( 'Invalid webhook signature.', 'class-manager-pro' ), array( 'status' => 403 ) );
		}

		$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'cmp_webhook_invalid_payload', __( 'Invalid webhook payload.', 'class-manager-pro' ), array( 'status' => 400 ) );
		}

		// FIX Bug 2: Only process successful payment events. Ignore failed/pending.
		$event          = isset( $payload['event'] ) ? (string) $payload['event'] : '';
		$allowed_events = array( 'payment.captured', 'payment.authorized', 'payment_link.paid', '' );
		if ( $event && ! in_array( $event, $allowed_events, true ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Event "' . $event . '" ignored — not a captured payment event.',
				)
			);
		}

		$payment = self::extract_payment_entity( $payload );

		if ( empty( $payment ) ) {
			return new WP_Error( 'cmp_webhook_payment_missing', __( 'Payment data was not found in webhook payload.', 'class-manager-pro' ), array( 'status' => 400 ) );
		}

		// FIX Bug 2: Block non-captured payment statuses.
		$payment_status = isset( $payment['status'] ) ? (string) $payment['status'] : '';
		if ( $payment_status && ! in_array( $payment_status, array( 'captured', 'authorized' ), true ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Payment status "' . $payment_status . '" ignored — only captured/authorized payments are recorded.',
				)
			);
		}

		$transaction_id = isset( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

		if ( '' !== $transaction_id && cmp_payment_exists( $transaction_id ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Duplicate webhook ignored.',
				)
			);
		}

		$notes    = isset( $payment['notes'] ) && is_array( $payment['notes'] ) ? $payment['notes'] : array();
		$metadata = isset( $payment['metadata'] ) && is_array( $payment['metadata'] ) ? $payment['metadata'] : array();
		$meta     = array_merge( $notes, $metadata );
		$batch    = null;

		$name     = self::first_non_empty( array( $meta['name'] ?? '', $payment['name'] ?? '', $payment['customer_name'] ?? '' ) );
		$email    = sanitize_email( self::first_non_empty( array( $meta['email'] ?? '', $payment['email'] ?? '' ) ) );
		$phone    = sanitize_text_field( self::first_non_empty( array( $meta['phone'] ?? '', $payment['contact'] ?? '', $payment['phone'] ?? '' ) ) );
		$class_id = isset( $meta['class_id'] ) ? absint( $meta['class_id'] ) : 0;
		$batch_id = isset( $meta['batch_id'] ) ? absint( $meta['batch_id'] ) : 0;
		$amount   = isset( $payment['amount'] ) ? round( (float) $payment['amount'] / 100, 2 ) : 0;

		// Try batch_token from payment notes.
		if ( ! $batch_id ) {
			$batch_token = self::first_non_empty( array( $meta['batch_token'] ?? '', $meta['cmp_batch_token'] ?? '', $meta['public_token'] ?? '' ) );
			$batch       = $batch_token ? cmp_get_batch_by_token( $batch_token ) : null;
			$batch_id    = $batch ? (int) $batch->id : 0;
		}

		// FIX Bug 5: Try matching batch via Razorpay payment link URL in webhook payload.
		if ( ! $batch_id ) {
			$link_short_url = '';
			if ( isset( $payload['payload']['payment_link']['entity']['short_url'] ) ) {
				$link_short_url = (string) $payload['payload']['payment_link']['entity']['short_url'];
			} elseif ( isset( $payload['payload']['payment_link']['entity']['url'] ) ) {
				$link_short_url = (string) $payload['payload']['payment_link']['entity']['url'];
			} elseif ( isset( $meta['payment_link_url'] ) ) {
				$link_short_url = (string) $meta['payment_link_url'];
			}

			if ( $link_short_url ) {
				$batch    = cmp_get_batch_by_razorpay_link( $link_short_url );
				$batch_id = $batch ? (int) $batch->id : 0;
			}
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

		if ( $recent_match && ! empty( $recent_match['student_id'] ) ) {
			$student = cmp_get_student( (int) $recent_match['student_id'] );
		}

		if ( ! $student && $batch_id ) {
			$student = cmp_find_student_in_batch_by_contact( $batch_id, $phone, $email );
		}

		if ( ! $student ) {
			$student = cmp_find_student_by_contact( $phone, $email );
		}

		if ( ! $student ) {
			$class = $class_id ? cmp_get_class( $class_id ) : null;

			$student_id = cmp_insert_student(
				array(
					'name'      => $name,
					'phone'     => $phone,
					'email'     => $email,
					'class_id'  => $class_id,
					'batch_id'  => $batch_id,
					'total_fee' => isset( $meta['total_fee'] ) ? cmp_money_value( $meta['total_fee'] ) : ( $class ? (float) $class->total_fee : 0 ),
					'paid_fee'  => 0,
					'status'    => 'active',
					'notes'     => __( 'Created by Razorpay webhook.', 'class-manager-pro' ),
				)
			);

			if ( is_wp_error( $student_id ) ) {
				return new WP_Error( $student_id->get_error_code(), $student_id->get_error_message(), array( 'status' => 400 ) );
			}
		} else {
			$student_id = (int) $student->id;
			self::maybe_update_student_assignment( $student, $class_id, $batch_id );
		}

		$payment_date = isset( $payment['created_at'] ) && is_numeric( $payment['created_at'] )
			? gmdate( 'Y-m-d H:i:s', (int) $payment['created_at'] )
			: cmp_current_datetime();

		$result = cmp_insert_payment(
			array(
				'student_id'     => $student_id,
				'amount'         => $amount,
				'payment_mode'   => 'razorpay',
				'transaction_id' => $transaction_id,
				'payment_date'   => $payment_date,
			)
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

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

		if ( $class_id && (int) $student->class_id !== $class_id && cmp_get_class( $class_id ) ) {
			$updates['class_id'] = $class_id;
			$formats[]           = '%d';
		}

		$target_class_id = isset( $updates['class_id'] ) ? (int) $updates['class_id'] : (int) $student->class_id;

		if ( $batch && (int) $student->batch_id !== $batch_id && (int) $batch->class_id === $target_class_id ) {
			$updates['batch_id'] = $batch_id;
			$formats[]           = '%d';
		}

		if ( $updates ) {
			$wpdb->update( cmp_table( 'students' ), $updates, array( 'id' => (int) $student->id ), $formats, array( '%d' ) );
		}
	}
}
