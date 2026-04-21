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
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY name (name)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$batches} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		class_id int(11) unsigned NOT NULL,
		batch_name varchar(191) NOT NULL,
		start_date date NULL,
		fee_due_date date NULL,
		status enum('active','completed') NOT NULL DEFAULT 'active',
		public_token varchar(64) NULL DEFAULT NULL,
		razorpay_link text NULL,
		batch_fee float NOT NULL DEFAULT 0,
		is_free tinyint(1) NOT NULL DEFAULT 0,
		razorpay_page_id varchar(191) NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY class_id (class_id),
		KEY status (status),
		UNIQUE KEY public_token (public_token),
		KEY razorpay_page_id (razorpay_page_id)
	) {$charset_collate};";

	$sql[] = "CREATE TABLE {$students} (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		unique_id varchar(50) NOT NULL,
		name varchar(191) NOT NULL,
		phone varchar(50) NOT NULL,
		email varchar(191) NULL,
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

	$attendance = $wpdb->prefix . 'cmp_attendance';
	$reminders  = $wpdb->prefix . 'cmp_reminders';

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
		channel enum('sms','whatsapp') NOT NULL,
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

	foreach ( $sql as $statement ) {
		dbDelta( $statement );
	}

	cmp_backfill_batch_tokens();
	cmp_backfill_batch_defaults();

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
