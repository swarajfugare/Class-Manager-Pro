<?php
/**
 * Tutor LMS and WordPress user integration helpers.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns whether Tutor LMS is available.
 *
 * @return bool
 */
function cmp_is_tutor_lms_available() {
	return post_type_exists( 'courses' ) || function_exists( 'tutor_utils' );
}

/**
 * Returns Tutor LMS courses for batch assignment.
 *
 * @return array
 */
function cmp_get_tutor_courses() {
	if ( ! post_type_exists( 'courses' ) ) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => 'courses',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'all',
			'no_found_rows'  => true,
		)
	);

	return is_array( $posts ) ? $posts : array();
}

/**
 * Returns a Tutor LMS course title.
 *
 * @param int $course_id Course ID.
 * @return string
 */
function cmp_get_tutor_course_title( $course_id ) {
	$course_id = absint( $course_id );

	if ( ! $course_id ) {
		return __( 'Not linked', 'class-manager-pro' );
	}

	$title = get_the_title( $course_id );

	return $title ? $title : __( 'Course not found', 'class-manager-pro' );
}

/**
 * Returns a Tutor LMS course URL.
 *
 * @param int $course_id Course ID.
 * @return string
 */
function cmp_get_tutor_course_url( $course_id ) {
	$course_id = absint( $course_id );

	return $course_id ? get_permalink( $course_id ) : '';
}

/**
 * Returns Tutor LMS instructors, keeping a legacy selected user visible.
 *
 * @param int $include_user_id Optional selected user ID.
 * @return array
 */
function cmp_get_tutor_instructors( $include_user_id = 0 ) {
	$include_user_id = absint( $include_user_id );
	$users           = get_users(
		array(
			'role'    => 'tutor_instructor',
			'fields'  => array( 'ID', 'display_name', 'user_email', 'roles' ),
			'orderby' => 'display_name',
			'order'   => 'ASC',
		)
	);

	if ( ! is_array( $users ) ) {
		$users = array();
	}

	if ( empty( $users ) ) {
		$candidate_users = get_users(
			array(
				'fields'  => array( 'ID', 'display_name', 'user_email', 'roles' ),
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		foreach ( $candidate_users as $candidate_user ) {
			if (
				user_can( $candidate_user, 'tutor_instructor' ) ||
				user_can( $candidate_user, 'manage_tutor' ) ||
				user_can( $candidate_user, 'publish_course' ) ||
				user_can( $candidate_user, 'publish_courses' ) ||
				user_can( $candidate_user, 'edit_course' ) ||
				user_can( $candidate_user, 'edit_courses' ) ||
				user_can( $candidate_user, 'edit_others_courses' )
			) {
				$users[] = $candidate_user;
			}
		}
	}

	if ( $include_user_id ) {
		$found = false;

		foreach ( $users as $user ) {
			if ( (int) $user->ID === $include_user_id ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			$legacy_user = get_userdata( $include_user_id );

			if ( $legacy_user ) {
				$users[] = (object) array(
					'ID'           => (int) $legacy_user->ID,
					'display_name' => $legacy_user->display_name,
					'user_email'   => $legacy_user->user_email,
					'roles'        => (array) $legacy_user->roles,
				);
			}
		}
	}

	return $users;
}

/**
 * Returns the student role to use when creating WordPress users.
 *
 * @return string
 */
function cmp_get_tutor_student_role() {
	if ( get_role( 'subscriber' ) ) {
		return 'subscriber';
	}

	return get_option( 'default_role', 'subscriber' );
}

/**
 * Builds a unique WordPress username from an email address.
 *
 * @param string $email Student email.
 * @return string
 */
function cmp_build_wordpress_username_from_email( $email ) {
	$email    = sanitize_email( $email );
	$parts    = explode( '@', $email );
	$username = sanitize_user( isset( $parts[0] ) ? $parts[0] : $email, true );

	if ( '' === $username ) {
		$local_part = isset( $parts[0] ) ? $parts[0] : '';
		$username   = sanitize_user( $local_part, true );
	}

	if ( '' === $username ) {
		$username = 'cmp_student';
	}

	$candidate = $username;
	$suffix    = 1;

	while ( username_exists( $candidate ) ) {
		$candidate = $username . '_' . $suffix;
		++$suffix;
	}

	return $candidate;
}

/**
 * Sends a course enrollment email after a student is enrolled.
 *
 * @param object $student Student row.
 * @param int    $course_id Course ID.
 * @return void
 */
function cmp_send_course_enrollment_email( $student, $course_id ) {
	$student = is_array( $student ) ? (object) $student : $student;
	$email   = isset( $student->email ) ? sanitize_email( $student->email ) : '';

	if ( ! $student || '' === $email ) {
		return;
	}

	$course_title = cmp_get_tutor_course_title( $course_id );
	$batch_name   = ! empty( $student->batch_name ) ? sanitize_text_field( $student->batch_name ) : __( 'your batch', 'class-manager-pro' );
	$message      = sprintf(
		/* translators: 1: student name 2: course title 3: batch name */
		__( "Hello %1\$s,\n\nYou are enrolled in a course.\nYou have been assigned to a course from Matoshree Collection.\n\nCourse: %2\$s\nBatch: %3\$s\n\nThank you.", 'class-manager-pro' ),
		! empty( $student->name ) ? sanitize_text_field( $student->name ) : __( 'Student', 'class-manager-pro' ),
		$course_title,
		$batch_name
	);

	wp_mail(
		$email,
		__( 'You are enrolled in a course', 'class-manager-pro' ),
		$message
	);
}

/**
 * Updates the stored WordPress user ID on a student record.
 *
 * @param int $student_id Student ID.
 * @param int $user_id WordPress user ID.
 * @return void
 */
function cmp_update_student_user_id( $student_id, $user_id ) {
	global $wpdb;

	$student_id = absint( $student_id );
	$user_id    = absint( $user_id );

	if ( ! $student_id ) {
		return;
	}

	$wpdb->update(
		cmp_table( 'students' ),
		array(
			'user_id' => $user_id,
		),
		array(
			'id' => $student_id,
		),
		array( '%d' ),
		array( '%d' )
	);
}

/**
 * Returns the best profile URL for a student.
 *
 * @param object|array $student Student row.
 * @return string
 */
function cmp_get_student_profile_url( $student ) {
	$student = is_array( $student ) ? (object) $student : $student;
	$user_id = isset( $student->user_id ) ? absint( $student->user_id ) : 0;

	if ( ! $user_id ) {
		return '';
	}

	if ( function_exists( 'tutor_utils' ) && is_callable( array( tutor_utils(), 'profile_url' ) ) ) {
		$profile_url = tutor_utils()->profile_url( $user_id );

		if ( is_string( $profile_url ) && '' !== trim( $profile_url ) ) {
			return $profile_url;
		}
	}

	if ( current_user_can( 'manage_options' ) ) {
		return admin_url( 'user-edit.php?user_id=' . $user_id );
	}

	return get_edit_user_link( $user_id );
}

/**
 * Finds or creates a WordPress user for a student and stores the mapping.
 *
 * @param int         $student_id Student ID.
 * @param object|array|null $student_data Optional student data.
 * @return int
 */
function cmp_sync_student_wordpress_user( $student_id, $student_data = null ) {
	$student_id = absint( $student_id );
	$student    = null === $student_data ? cmp_get_student( $student_id ) : ( is_array( $student_data ) ? (object) $student_data : $student_data );

	if ( ! $student || empty( $student->email ) ) {
		return 0;
	}

	$email = sanitize_email( $student->email );

	if ( '' === $email ) {
		return 0;
	}

	$user_id     = isset( $student->user_id ) ? absint( $student->user_id ) : 0;
	$linked_user = $user_id ? get_userdata( $user_id ) : null;

	if ( $linked_user && strtolower( (string) $linked_user->user_email ) === strtolower( $email ) ) {
		return $user_id;
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user && $linked_user ) {
		$updated_user = wp_update_user(
			array(
				'ID'           => (int) $linked_user->ID,
				'user_email'   => $email,
				'display_name' => isset( $student->name ) ? sanitize_text_field( $student->name ) : $email,
				'first_name'   => isset( $student->name ) ? sanitize_text_field( $student->name ) : '',
			)
		);

		if ( ! is_wp_error( $updated_user ) ) {
			$user = get_userdata( (int) $linked_user->ID );
		}
	}

	if ( ! $user ) {
		$user_result = wp_create_user( cmp_build_wordpress_username_from_email( $email ), 'password', $email );

		if ( is_wp_error( $user_result ) ) {
			cmp_log_event(
				'error',
				'WordPress user creation failed for student.',
				array(
					'student_id' => $student_id,
					'email'      => $email,
					'message'    => $user_result->get_error_message(),
				)
			);

			return 0;
		}

		wp_set_password( 'password', (int) $user_result );
		wp_update_user(
			array(
				'ID'           => (int) $user_result,
				'display_name' => isset( $student->name ) ? sanitize_text_field( $student->name ) : $email,
				'first_name'   => isset( $student->name ) ? sanitize_text_field( $student->name ) : '',
				'role'         => cmp_get_tutor_student_role(),
			)
		);

		if ( function_exists( 'wp_new_user_notification' ) ) {
			wp_new_user_notification( (int) $user_result, null, 'both' );
		}

		$user = get_userdata( (int) $user_result );
	}

	if ( ! $user ) {
		return 0;
	}

	update_user_meta( (int) $user->ID, '_is_tutor_student', time() );
	cmp_update_student_user_id( $student_id, (int) $user->ID );

	return (int) $user->ID;
}

/**
 * Returns Tutor LMS enrollment table details when available.
 *
 * @return array
 */
function cmp_get_tutor_enrollment_table_details() {
	global $wpdb;

	$table  = $wpdb->prefix . 'tutor_enrolled';
	$like   = $wpdb->esc_like( $table );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

	if ( $table !== $exists ) {
		return array();
	}

	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	if ( empty( $columns ) || ! in_array( 'user_id', $columns, true ) ) {
		return array();
	}

	return array(
		'table'   => $table,
		'columns' => $columns,
	);
}

/**
 * Returns whether a user is already enrolled in a Tutor LMS course.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return bool
 */
function cmp_is_user_enrolled_in_tutor_course( $course_id, $user_id ) {
	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	if ( function_exists( 'tutor_utils' ) && is_callable( array( tutor_utils(), 'is_enrolled' ) ) ) {
		return (bool) tutor_utils()->is_enrolled( $course_id, $user_id );
	}

	$table_details = cmp_get_tutor_enrollment_table_details();

	if ( ! empty( $table_details['table'] ) && ! empty( $table_details['columns'] ) ) {
		global $wpdb;

		$course_column = '';

		if ( in_array( 'course_id', $table_details['columns'], true ) ) {
			$course_column = 'course_id';
		} elseif ( in_array( 'post_id', $table_details['columns'], true ) ) {
			$course_column = 'post_id';
		}

		if ( $course_column ) {
			$enrollment_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . $table_details['table'] . ' WHERE user_id = %d AND ' . $course_column . ' = %d',
					$user_id,
					$course_id
				)
			);

			if ( $enrollment_count > 0 ) {
				return true;
			}
		}
	}

	$existing = get_posts(
		array(
			'post_type'      => 'tutor_enrolled',
			'post_status'    => array( 'completed', 'publish', 'pending', 'private' ),
			'author'         => $user_id,
			'post_parent'    => $course_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	return ! empty( $existing );
}

/**
 * Returns the Tutor LMS enrollment post ID for a course/user pair.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return int
 */
function cmp_get_tutor_enrollment_post_id( $course_id, $user_id ) {
	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return 0;
	}

	$existing = get_posts(
		array(
			'post_type'      => 'tutor_enrolled',
			'post_status'    => array( 'completed', 'publish', 'pending', 'private' ),
			'author'         => $user_id,
			'post_parent'    => $course_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	return ! empty( $existing[0] ) ? (int) $existing[0] : 0;
}

/**
 * Ensures a Tutor LMS enrollment record grants course access.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return bool
 */
function cmp_complete_tutor_enrollment_access( $course_id, $user_id ) {
	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );
	$updated   = false;

	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	$table_details = cmp_get_tutor_enrollment_table_details();

	if ( ! empty( $table_details['table'] ) && ! empty( $table_details['columns'] ) && in_array( 'status', $table_details['columns'], true ) ) {
		global $wpdb;

		$course_column = '';

		if ( in_array( 'course_id', $table_details['columns'], true ) ) {
			$course_column = 'course_id';
		} elseif ( in_array( 'post_id', $table_details['columns'], true ) ) {
			$course_column = 'post_id';
		}

		if ( $course_column ) {
			$result = $wpdb->update(
				$table_details['table'],
				array( 'status' => 'completed' ),
				array(
					'user_id'          => $user_id,
					$course_column     => $course_id,
				),
				array( '%s' ),
				array( '%d', '%d' )
			);

			if ( false !== $result ) {
				$updated = true;
			}
		}
	}

	$enrollment_post_id = cmp_get_tutor_enrollment_post_id( $course_id, $user_id );

	if ( $enrollment_post_id ) {
		$post_update = wp_update_post(
			array(
				'ID'          => $enrollment_post_id,
				'post_status' => 'completed',
			),
			true
		);

		if ( ! is_wp_error( $post_update ) ) {
			$updated = true;
		}
	}

	if ( $updated ) {
		update_user_meta( $user_id, '_is_tutor_student', time() );
	}

	return $updated;
}

/**
 * Enrolls a user into Tutor LMS with completed access when possible.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return bool
 */
function cmp_do_tutor_course_enrollment( $course_id, $user_id ) {
	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );

	if ( ! $course_id || ! $user_id || ! function_exists( 'tutor_utils' ) || ! is_callable( array( tutor_utils(), 'do_enroll' ) ) ) {
		return false;
	}

	$force_completed = static function ( $enroll_data ) use ( $course_id, $user_id ) {
		if ( ! is_array( $enroll_data ) ) {
			return $enroll_data;
		}

		$enroll_data['post_parent'] = $course_id;
		$enroll_data['post_author'] = $user_id;
		$enroll_data['post_status'] = 'completed';

		return $enroll_data;
	};

	add_filter( 'tutor_enroll_data', $force_completed, 10, 1 );

	try {
		$enrolled = (bool) tutor_utils()->do_enroll( $course_id, 0, $user_id );
	} catch ( Exception $exception ) {
		$enrolled = false;
		cmp_log_event(
			'error',
			'Tutor LMS do_enroll threw an exception.',
			array(
				'course_id' => $course_id,
				'user_id'   => $user_id,
				'message'   => $exception->getMessage(),
			)
		);
	}

	remove_filter( 'tutor_enroll_data', $force_completed, 10 );

	if ( $enrolled ) {
		cmp_complete_tutor_enrollment_access( $course_id, $user_id );
	}

	return $enrolled;
}

/**
 * Creates a fallback Tutor LMS enrollment record when helper methods are unavailable.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return bool
 */
function cmp_create_tutor_enrollment_post( $course_id, $user_id ) {
	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );

	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	$enrollment_id = wp_insert_post(
		array(
			'post_type'   => 'tutor_enrolled',
			'post_title'  => sprintf(
				/* translators: 1: date 2: time */
				__( 'Course Enrolled - %1$s @ %2$s', 'class-manager-pro' ),
				wp_date( get_option( 'date_format' ) ),
				wp_date( get_option( 'time_format' ) )
			),
			'post_status' => 'completed',
			'post_author' => $user_id,
			'post_parent' => $course_id,
		),
		true
	);

	if ( is_wp_error( $enrollment_id ) ) {
		cmp_log_event(
			'error',
			'Tutor LMS fallback enrollment failed.',
			array(
				'course_id' => $course_id,
				'user_id'   => $user_id,
				'message'   => $enrollment_id->get_error_message(),
			)
		);

		return false;
	}

	update_user_meta( $user_id, '_is_tutor_student', time() );

	return true;
}

/**
 * Creates a fallback Tutor LMS enrollment row when a dedicated table exists.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return bool
 */
function cmp_create_tutor_enrollment_row( $course_id, $user_id ) {
	global $wpdb;

	$course_id = absint( $course_id );
	$user_id   = absint( $user_id );
	$details   = cmp_get_tutor_enrollment_table_details();

	if ( empty( $details['table'] ) || empty( $details['columns'] ) ) {
		return false;
	}

	$table   = $details['table'];
	$columns = $details['columns'];

	$data   = array(
		'user_id' => $user_id,
	);
	$format = array( '%d' );

	if ( in_array( 'course_id', $columns, true ) ) {
		$data['course_id'] = $course_id;
		$format[]          = '%d';
	} elseif ( in_array( 'post_id', $columns, true ) ) {
		$data['post_id'] = $course_id;
		$format[]        = '%d';
	} else {
		return false;
	}

	if ( in_array( 'status', $columns, true ) ) {
		$data['status'] = 'completed';
		$format[]       = '%s';
	}

	if ( in_array( 'created_at', $columns, true ) ) {
		$data['created_at'] = cmp_current_datetime();
		$format[]           = '%s';
	}

	if ( in_array( 'updated_at', $columns, true ) ) {
		$data['updated_at'] = cmp_current_datetime();
		$format[]           = '%s';
	}

	if ( in_array( 'enrolled_at', $columns, true ) ) {
		$data['enrolled_at'] = cmp_current_datetime();
		$format[]            = '%s';
	}

	$inserted = $wpdb->insert( $table, $data, $format );

	if ( false === $inserted ) {
		return false;
	}

	update_user_meta( $user_id, '_is_tutor_student', time() );

	return true;
}

/**
 * Enrolls a student into a specific Tutor LMS course.
 *
 * @param int $student_id Student ID.
 * @param int $course_id Course ID.
 * @return bool
 */
function cmp_enroll_student_in_specific_tutor_course( $student_id, $course_id ) {
	$student_id = absint( $student_id );
	$course_id  = absint( $course_id );
	$student    = cmp_get_student( $student_id );
	$course     = $course_id ? get_post( $course_id ) : null;

	if ( ! $student || ! $course_id || ! $course || 'courses' !== $course->post_type ) {
		return false;
	}

	$user_id = ! empty( $student->user_id ) ? absint( $student->user_id ) : cmp_sync_student_wordpress_user( $student_id, $student );
	$user    = $user_id ? get_userdata( $user_id ) : null;

	if ( ! $user_id || ! $user ) {
		cmp_log_event(
			'error',
			'Tutor LMS enrollment failed because the WordPress user could not be resolved.',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			)
		);

		return false;
	}

	if ( cmp_is_user_enrolled_in_tutor_course( $course_id, $user_id ) ) {
		cmp_complete_tutor_enrollment_access( $course_id, $user_id );
		return true;
	}

	$enrolled = false;

	$enrolled = cmp_do_tutor_course_enrollment( $course_id, $user_id );

	if ( ! $enrolled ) {
		$enrolled = cmp_create_tutor_enrollment_row( $course_id, $user_id );
	}

	if ( ! $enrolled ) {
		$enrolled = cmp_create_tutor_enrollment_post( $course_id, $user_id );
	}

	if ( ! $enrolled ) {
		cmp_log_event(
			'error',
			'Tutor LMS enrollment failed.',
			array(
				'student_id' => $student_id,
				'user_id'    => $user_id,
				'course_id'  => $course_id,
			)
		);
	}

	if ( $enrolled ) {
		cmp_complete_tutor_enrollment_access( $course_id, $user_id );
	}

	if ( $enrolled && function_exists( 'cmp_log_activity' ) ) {
		cmp_log_activity(
			array(
				'student_id' => $student_id,
				'batch_id'   => (int) $student->batch_id,
				'class_id'   => (int) $student->class_id,
				'action'     => 'course_enrolled',
				'message'    => sprintf(
					/* translators: %d: course ID */
					__( 'Student enrolled into Tutor LMS course #%d.', 'class-manager-pro' ),
					$course_id
				),
				'context'    => array(
					'user_id'   => $user_id,
					'course_id' => $course_id,
				),
			)
		);
	}

	if ( $enrolled ) {
		cmp_send_course_enrollment_email( $student, $course_id );
	}

	return $enrolled;
}

/**
 * Enrolls a student into the batch-linked Tutor LMS course.
 *
 * @param int         $student_id Student ID.
 * @param object|null $batch Optional batch row.
 * @return bool
 */
function cmp_enroll_student_in_tutor_course( $student_id, $batch = null ) {
	$student_id = absint( $student_id );
	$student    = cmp_get_student( $student_id );

	if ( ! $student ) {
		return false;
	}

	$batch = $batch ? $batch : cmp_get_batch( (int) $student->batch_id );

	if ( ! $batch || empty( $batch->course_id ) ) {
		return false;
	}

	return cmp_enroll_student_in_specific_tutor_course( $student_id, (int) $batch->course_id );
}

/**
 * Syncs WordPress user linkage and Tutor LMS enrollment for a student.
 *
 * @param int $student_id Student ID.
 * @return void
 */
function cmp_sync_student_tutor_enrollment( $student_id ) {
	$student_id = absint( $student_id );
	$student    = cmp_get_student( $student_id );

	if ( ! $student ) {
		return;
	}

	cmp_sync_student_wordpress_user( $student_id, $student );
	cmp_enroll_student_in_tutor_course( $student_id );
}

/**
 * Syncs Tutor LMS enrollment for all students in a batch.
 *
 * @param int $batch_id Batch ID.
 * @return void
 */
function cmp_sync_batch_students_tutor_enrollment( $batch_id ) {
	$batch_id = absint( $batch_id );
	$batch    = cmp_get_batch( $batch_id );

	if ( ! $batch_id || ! $batch || empty( $batch->course_id ) ) {
		return;
	}

	$students = cmp_get_students(
		array(
			'batch_id' => $batch_id,
			'limit'    => 0,
		)
	);

	foreach ( $students as $student ) {
		cmp_sync_student_tutor_enrollment( (int) $student->id );
	}
}
