<?php
/**
 * Database installer for Class Manager Pro.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or updates all plugin tables.
 */
function cmp_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$classes         = $wpdb->prefix . 'cmp_classes';
	$batches         = $wpdb->prefix . 'cmp_batches';
	$students        = $wpdb->prefix . 'cmp_students';
	$payments        = $wpdb->prefix . 'cmp_payments';

	$sql = array();

	$sql[] = "CREATE TABLE {$classes} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(191) NOT NULL,
		description text NULL,
		total_fee float NOT NULL DEFAULT 0,
		next_course_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY name (name),
		KEY next_course_id (next_course_id)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$batches} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		class_id int(11) unsigned NOT NULL,
		batch_name varchar(191) NOT NULL,
		course_id bigint(20) unsigned NOT NULL DEFAULT 0,
		start_date date NULL,
		fee_due_date date NULL,
		status enum('active','completed') NOT NULL DEFAULT 'active',
		public_token varchar(64) NULL DEFAULT NULL,
		razorpay_link text NULL,
		batch_fee float NOT NULL DEFAULT 0,
		is_free tinyint(1) NOT NULL DEFAULT 0,
		teacher_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		intake_limit int(11) unsigned NOT NULL DEFAULT 0,
		class_days varchar(191) NULL,
		manual_income float NOT NULL DEFAULT 0,
		razorpay_page_id varchar(191) NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY class_id (class_id),
		KEY batch_name (batch_name),
		KEY class_batch_name (class_id, batch_name),
		KEY course_id (course_id),
		KEY status (status),
		KEY teacher_user_id (teacher_user_id),
		UNIQUE KEY public_token (public_token),
		KEY razorpay_page_id (razorpay_page_id)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$students} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		unique_id varchar(50) NOT NULL,
		name varchar(191) NOT NULL,
		phone varchar(50) NOT NULL,
		email varchar(191) NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		class_id int(11) unsigned NOT NULL,
		batch_id int(11) unsigned NOT NULL,
		total_fee float NOT NULL DEFAULT 0,
		paid_fee float NOT NULL DEFAULT 0,
		status enum('active','completed','dropped') NOT NULL DEFAULT 'active',
		notes text NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY unique_id (unique_id),
		KEY phone (phone),
		KEY email (email),
		KEY user_id (user_id),
		KEY class_id (class_id),
		KEY batch_id (batch_id),
		KEY status (status)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$payments} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		student_id int(11) unsigned NOT NULL,
		amount float NOT NULL DEFAULT 0,
		payment_mode enum('razorpay','upi','cash','manual') NOT NULL DEFAULT 'manual',
		transaction_id varchar(191) NULL,
		payment_date datetime NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY student_id (student_id),
		KEY payment_mode (payment_mode),
		KEY transaction_id (transaction_id),
		KEY payment_date (payment_date)
	) {$charset_collate};";

	$attendance   = $wpdb->prefix . 'cmp_attendance';
	$reminders    = $wpdb->prefix . 'cmp_reminders';
	$expenses     = $wpdb->prefix . 'cmp_expenses';
	$activity     = $wpdb->prefix . 'cmp_activity_logs';
	$admin_logs   = $wpdb->prefix . 'cmp_admin_logs';
	$teacher_logs = $wpdb->prefix . 'cmp_teacher_logs';

	$sql[] = "CREATE TABLE {$attendance} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		batch_id int(11) unsigned NOT NULL,
		student_id int(11) unsigned NOT NULL,
		attendance_date date NOT NULL,
		status enum('present','absent','leave') NOT NULL DEFAULT 'present',
		notes text NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY batch_id (batch_id),
		KEY student_id (student_id),
		KEY attendance_date (attendance_date),
		UNIQUE KEY batch_student_date (batch_id, student_id, attendance_date)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$reminders} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		student_id int(11) unsigned NOT NULL,
		batch_id int(11) unsigned NOT NULL,
		reminder_date date NOT NULL,
		due_date date NULL,
		channel enum('sms','whatsapp','email') NOT NULL,
		status enum('sent','failed','skipped') NOT NULL DEFAULT 'sent',
		provider varchar(50) NULL,
		response_message text NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY student_id (student_id),
		KEY batch_id (batch_id),
		KEY reminder_date (reminder_date),
		KEY status (status),
		UNIQUE KEY student_batch_date_channel (student_id, batch_id, reminder_date, channel)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$expenses} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		class_id int(11) unsigned NOT NULL DEFAULT 0,
		batch_id int(11) unsigned NOT NULL DEFAULT 0,
		category enum('teacher_payment','meta_ads','ad_material','other') NOT NULL DEFAULT 'other',
		amount float NOT NULL DEFAULT 0,
		expense_date date NOT NULL,
		notes text NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY class_id (class_id),
		KEY batch_id (batch_id),
		KEY category (category),
		KEY expense_date (expense_date)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$activity} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		student_id int(11) unsigned NOT NULL DEFAULT 0,
		batch_id int(11) unsigned NOT NULL DEFAULT 0,
		class_id int(11) unsigned NOT NULL DEFAULT 0,
		payment_id int(11) unsigned NOT NULL DEFAULT 0,
		action varchar(100) NOT NULL,
		message text NULL,
		context longtext NULL,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY student_id (student_id),
		KEY batch_id (batch_id),
		KEY class_id (class_id),
		KEY payment_id (payment_id),
		KEY action (action),
		KEY created_at (created_at)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$admin_logs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		action varchar(100) NOT NULL,
		object_type varchar(100) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		message text NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY action (action),
		KEY object_type (object_type),
		KEY object_id (object_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$teacher_logs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		teacher_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		batch_id int(11) unsigned NOT NULL DEFAULT 0,
		student_id int(11) unsigned NOT NULL DEFAULT 0,
		action varchar(100) NOT NULL,
		message text NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY teacher_user_id (teacher_user_id),
		KEY batch_id (batch_id),
		KEY student_id (student_id),
		KEY action (action),
		KEY created_at (created_at)
	) {$charset_collate};";

	foreach ( $sql as $statement ) {
		dbDelta( $statement );
	}

	cmp_backfill_batch_tokens();
	cmp_backfill_batch_defaults();
	cmp_backfill_student_user_links();

	update_option( 'cmp_db_version', CMP_VERSION );
}

/**
 * Runs database upgrades on plugin updates.
 */
function cmp_maybe_upgrade() {
	$installed_version = (string) get_option( 'cmp_db_version', '' );

	if ( CMP_VERSION !== $installed_version ) {
		cmp_install();
	}
}

/**
 * Ensures every batch has a public token for public intake links.
 */
function cmp_backfill_batch_tokens() {
	global $wpdb;

	$batches = $wpdb->get_results( 'SELECT id, public_token FROM ' . $wpdb->prefix . 'cmp_batches WHERE public_token = "" OR public_token IS NULL' );

	if ( empty( $batches ) ) {
		return;
	}

	foreach ( $batches as $batch ) {
		$wpdb->update(
			$wpdb->prefix . 'cmp_batches',
			array(
				'public_token' => cmp_generate_batch_public_token(),
			),
			array(
				'id' => (int) $batch->id,
			),
			array( '%s' ),
			array( '%d' )
		);
	}
}

/**
 * Backfills new batch defaults for older installs.
 */
function cmp_backfill_batch_defaults() {
	global $wpdb;

	$wpdb->query(
		'UPDATE ' . $wpdb->prefix . 'cmp_batches b
		LEFT JOIN ' . $wpdb->prefix . 'cmp_classes c ON c.id = b.class_id
		SET b.batch_fee = COALESCE(NULLIF(b.batch_fee, 0), c.total_fee, 0)
		WHERE b.batch_fee = 0'
	);

	$wpdb->query(
		'UPDATE ' . $wpdb->prefix . 'cmp_students s
		LEFT JOIN ' . $wpdb->prefix . 'cmp_batches b ON b.id = s.batch_id
		LEFT JOIN ' . $wpdb->prefix . 'cmp_classes c ON c.id = s.class_id
		SET s.total_fee = COALESCE(NULLIF(s.total_fee, 0), NULLIF(b.batch_fee, 0), c.total_fee, 0)
		WHERE s.total_fee = 0 AND COALESCE(b.is_free, 0) = 0'
	);
}

/**
 * Backfills student to WordPress user links where possible.
 */
function cmp_backfill_student_user_links() {
	global $wpdb;

	$students = $wpdb->get_results(
		'SELECT id, email, user_id
		FROM ' . $wpdb->prefix . 'cmp_students
		WHERE ( user_id = 0 OR user_id IS NULL )
			AND email IS NOT NULL
			AND email <> ""'
	);

	if ( empty( $students ) ) {
		return;
	}

	foreach ( $students as $student ) {
		$user = get_user_by( 'email', sanitize_email( $student->email ) );

		if ( ! $user ) {
			continue;
		}

		$wpdb->update(
			$wpdb->prefix . 'cmp_students',
			array(
				'user_id' => (int) $user->ID,
			),
			array(
				'id' => (int) $student->id,
			),
			array( '%d' ),
			array( '%d' )
		);
	}
}
