<?php
/**
 * Class CMP_Backup_Manager
 *
 * Automated CSV + JSON backup system.
 *
 * @package ClassManagerPro
 */
class CMP_Backup_Manager {

	const BACKUP_DIR = 'cmp-backups';

	public static function init() {
		add_action( 'wp_ajax_cmp_create_backup', array( __CLASS__, 'ajax_create_backup' ) );
		add_action( 'wp_ajax_cmp_download_backup', array( __CLASS__, 'ajax_download_backup' ) );
		add_action( 'wp_ajax_cmp_delete_backup', array( __CLASS__, 'ajax_delete_backup' ) );
		add_action( 'wp_ajax_cmp_list_backups', array( __CLASS__, 'ajax_list_backups' ) );
		add_action( 'wp_ajax_cmp_import_json_backup', array( __CLASS__, 'ajax_import_json_backup' ) );
	}

	public static function get_backup_path() {
		$upload_dir = wp_upload_dir();
		$path = trailingslashit( $upload_dir['basedir'] ) . self::BACKUP_DIR;

		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
			file_put_contents( $path . '/index.php', '<?php // Silence is golden.' );
			file_put_contents( $path . '/.htaccess', "deny from all\n" );
		}

		return $path;
	}

	public static function create_backup() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cmp_';
		$tables = array( 'classes', 'batches', 'students', 'payments', 'attendance', 'expenses', 'announcements', 'interested_students' );

		$backup = array(
			'created_at' => current_time( 'mysql' ),
			'version'    => CMP_VERSION,
			'site_url'   => get_site_url(),
			'tables'     => array(),
		);

		foreach ( $tables as $table ) {
			$rows = $wpdb->get_results( "SELECT * FROM {$prefix}{$table}", ARRAY_A );
			$backup['tables'][ $table ] = $rows;
		}

		$filename = 'cmp-backup-' . date( 'Y-m-d-H-i-s' ) . '.json';
		$path     = self::get_backup_path() . '/' . $filename;

		file_put_contents( $path, wp_json_encode( $backup, JSON_PRETTY_PRINT ) );

		CMP_Admin_Notifications::add(
			sprintf( __( 'Backup created: %s', 'class-manager-pro' ), $filename ),
			'success',
			'backup'
		);

		return $filename;
	}

	public static function list_backups() {
		$path = self::get_backup_path();
		$files = array();

		if ( file_exists( $path ) ) {
			foreach ( glob( $path . '/*.json' ) as $file ) {
				$files[] = array(
					'name' => basename( $file ),
					'size' => size_format( filesize( $file ) ),
					'date' => date( 'Y-m-d H:i:s', filemtime( $file ) ),
					'url'  => wp_upload_dir()['baseurl'] . '/' . self::BACKUP_DIR . '/' . basename( $file ),
				);
			}
		}

		usort( $files, function( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		} );

		return $files;
	}

	public static function delete_backup( $filename ) {
		$path = self::get_backup_path() . '/' . basename( $filename );
		if ( file_exists( $path ) ) {
			unlink( $path );
			return true;
		}
		return false;
	}

	public static function ajax_create_backup() {
		if ( ! current_user_can( 'cmp_manage_backups' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$filename = self::create_backup();
		wp_send_json_success( array( 'filename' => $filename, 'message' => __( 'Backup created successfully.', 'class-manager-pro' ) ) );
	}

	public static function ajax_list_backups() {
		if ( ! current_user_can( 'cmp_manage_backups' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		wp_send_json_success( array( 'backups' => self::list_backups() ) );
	}

	public static function ajax_delete_backup() {
		if ( ! current_user_can( 'cmp_manage_backups' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$filename = sanitize_file_name( $_POST['filename'] ?? '' );
		if ( ! $filename ) {
			wp_send_json_error( array( 'message' => __( 'No filename provided.', 'class-manager-pro' ) ) );
		}

		$result = self::delete_backup( $filename );
		wp_send_json_success( array( 'success' => $result ) );
	}

	public static function ajax_download_backup() {
		if ( ! current_user_can( 'cmp_manage_backups' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$filename = sanitize_file_name( $_POST['filename'] ?? '' );
		$path     = self::get_backup_path() . '/' . $filename;

		if ( ! file_exists( $path ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'class-manager-pro' ) ) );
		}

		wp_send_json_success( array( 'content' => file_get_contents( $path ) ) );
	}

	public static function ajax_import_json_backup() {
		if ( ! current_user_can( 'cmp_manage_backups' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

		$content = wp_unslash( $_POST['content'] ?? '' );
		$data    = json_decode( $content, true );

		if ( ! $data || empty( $data['tables'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup file.', 'class-manager-pro' ) ) );
		}

		// This is a restore - we just validate the format. Actual restore would require
		// careful merge logic and is beyond the scope of this auto-restore endpoint.
		wp_send_json_success( array(
			'message' => __( 'Backup validated. Use the restore tool to import data.', 'class-manager-pro' ),
			'tables'  => array_keys( $data['tables'] ),
			'rows'    => array_sum( array_map( 'count', $data['tables'] ) ),
		) );
	}
}
