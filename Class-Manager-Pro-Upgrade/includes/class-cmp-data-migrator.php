<?php
/**
 * Class CMP_Data_Migrator
 *
 * Database migration system with versioning.
 *
 * @package ClassManagerPro
 */
class CMP_Data_Migrator {

	const OPTION_KEY = 'cmp_db_version';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
	}

	public static function maybe_migrate() {
		$current = get_option( self::OPTION_KEY, '1.0.0' );
		if ( version_compare( $current, CMP_DB_VERSION, '>=' ) ) {
			return;
		}

		$migrations = array(
			'2.0.0' => array( __CLASS__, 'migrate_v200' ),
		);

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $current, $version, '<' ) ) {
				call_user_func( $callback );
				update_option( self::OPTION_KEY, $version, false );
				CMP_Admin_Notifications::add(
					sprintf( __( 'Database migrated to version %s.', 'class-manager-pro' ), $version ),
					'info',
					'system'
				);
			}
		}
	}

	public static function migrate_v200() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';

		// Add soft delete to students if not exists.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$prefix}students LIKE 'is_deleted'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$prefix}students ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status" );
			$wpdb->query( "ALTER TABLE {$prefix}students ADD INDEX idx_deleted (is_deleted)" );
		}

		// Add soft delete to classes.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$prefix}classes LIKE 'is_deleted'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$prefix}classes ADD COLUMN is_deleted TINYINT(1) DEFAULT 0" );
		}

		// Add soft delete to batches.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$prefix}batches LIKE 'is_deleted'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$prefix}batches ADD COLUMN is_deleted TINYINT(1) DEFAULT 0" );
		}

		// Add audit log table.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$prefix}payment_audit (
				id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				payment_id BIGINT(20) UNSIGNED NOT NULL,
				user_id BIGINT(20) UNSIGNED NOT NULL,
				action VARCHAR(50) NOT NULL,
				old_value LONGTEXT,
				new_value LONGTEXT,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_payment_id (payment_id),
				INDEX idx_created (created_at)
			) {$wpdb->get_charset_collate()};"
		);

		// Populate existing soft delete values.
		$wpdb->query( "UPDATE {$prefix}students SET is_deleted = 0 WHERE is_deleted IS NULL" );
		$wpdb->query( "UPDATE {$prefix}classes SET is_deleted = 0 WHERE is_deleted IS NULL" );
		$wpdb->query( "UPDATE {$prefix}batches SET is_deleted = 0 WHERE is_deleted IS NULL" );
	}
}
