<?php
/**
 * Plugin Name: Class Manager Pro
 * Description: Premium plugin to manage classes, batches, students, fees, payments, attendance, expenses, Razorpay imports, analytics, and teacher console.
 * Version: 2.0.0
 * Author: Swaraj Fugare
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: class-manager-pro
 * Domain Path: /languages
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'CMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CMP_VERSION', '2.0.0' );
define( 'CMP_DB_VERSION', '2.0.0' );

// Load upgrade compatibility layer.
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-compat.php';

// Load core class autoloader.
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-autoloader.php';
CMP_Autoloader::register();

// Load legacy includes (backward compatible).
require_once CMP_PLUGIN_DIR . 'includes/db.php';
require_once CMP_PLUGIN_DIR . 'includes/functions.php';
require_once CMP_PLUGIN_DIR . 'includes/next-version.php';
require_once CMP_PLUGIN_DIR . 'includes/public.php';
require_once CMP_PLUGIN_DIR . 'includes/razorpay.php';
require_once CMP_PLUGIN_DIR . 'includes/tutor.php';

// Load new core modules (additive only).
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-cache.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-security.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-health-check.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-ajax-filter.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-smart-search.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-inline-edit.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-admin-notifications.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-performance-monitor.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-auto-scheduler.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-data-migrator.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-role-manager.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-backup-manager.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-bulk-processor.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-quick-add.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-dashboard-realtime.php';

// Load admin pages.
require_once CMP_PLUGIN_DIR . 'admin/dashboard.php';
require_once CMP_PLUGIN_DIR . 'admin/classes.php';
require_once CMP_PLUGIN_DIR . 'admin/batches.php';
require_once CMP_PLUGIN_DIR . 'admin/students.php';
require_once CMP_PLUGIN_DIR . 'admin/payments.php';
require_once CMP_PLUGIN_DIR . 'admin/settings.php';
require_once CMP_PLUGIN_DIR . 'admin/analytics.php';
require_once CMP_PLUGIN_DIR . 'admin/teacher-console.php';
require_once CMP_PLUGIN_DIR . 'admin/add-new.php';
require_once CMP_PLUGIN_DIR . 'admin/all-data.php';
require_once CMP_PLUGIN_DIR . 'admin/interested-students.php';
require_once CMP_PLUGIN_DIR . 'admin/razorpay-import.php';

// Load new admin enhancements.
require_once CMP_PLUGIN_DIR . 'admin/admin-enhancements.php';
require_once CMP_PLUGIN_DIR . 'admin/health-check-page.php';
require_once CMP_PLUGIN_DIR . 'admin/notifications-page.php';

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, 'cmp_activate_plugin_v2' );

function cmp_activate_plugin_v2() {
	cmp_create_tables();
	cmp_add_default_templates();
	cmp_create_roles_and_caps();
	CMP_Data_Migrator::maybe_migrate();
	CMP_Cache::warmup_cache();
	CMP_Health_Check::schedule_scan();
	CMP_Auto_Scheduler::setup_default_schedule();
}

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, 'cmp_deactivate_plugin_v2' );

function cmp_deactivate_plugin_v2() {
	CMP_Auto_Scheduler::clear_all();
	CMP_Health_Check::unschedule_scan();
	CMP_Cache::clear_all();
	CMP_Performance_Monitor::clear_logs();
}

/**
 * Initialize the plugin.
 */
add_action( 'plugins_loaded', 'cmp_init_plugin_v2', 5 );

function cmp_init_plugin_v2() {
	load_plugin_textdomain( 'class-manager-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	// Initialize new core services.
	CMP_Cache::init();
	CMP_Security::init();
	CMP_AJAX_Filter::init();
	CMP_Smart_Search::init();
	CMP_Inline_Edit::init();
	CMP_Admin_Notifications::init();
	CMP_Performance_Monitor::init();
	CMP_Auto_Scheduler::init();
	CMP_Data_Migrator::init();
	CMP_Role_Manager::init();
	CMP_Backup_Manager::init();
	CMP_Bulk_Processor::init();
	CMP_Quick_Add::init();
	CMP_Dashboard_Realtime::init();
	CMP_Health_Check::init();
	
	// Add database indexes (idempotent).
	add_action( 'admin_init', 'cmp_ensure_database_indexes' );
}

/**
 * Add performance indexes on upgrade.
 */
function cmp_ensure_database_indexes() {
	$indexes_done = get_option( 'cmp_indexes_v200_done', false );
	if ( $indexes_done ) {
		return;
	}
	
	global $wpdb;
	$prefix = $wpdb->prefix . 'cmp_';
	
	$indexes = array(
		"{$prefix}students"  => array( 'idx_class_id' => 'class_id', 'idx_batch_id' => 'batch_id', 'idx_status' => 'status', 'idx_created' => 'created_at', 'idx_phone' => 'phone(20)' ),
		"{$prefix}payments"  => array( 'idx_student_id' => 'student_id', 'idx_deleted' => 'is_deleted', 'idx_payment_date' => 'payment_date', 'idx_transaction' => 'transaction_id(50)', 'idx_mode' => 'payment_mode' ),
		"{$prefix}attendance"=> array( 'idx_batch_date' => 'batch_id,attendance_date', 'idx_student' => 'student_id', 'idx_date' => 'attendance_date' ),
		"{$prefix}expenses"  => array( 'idx_batch_id' => 'batch_id', 'idx_category' => 'category', 'idx_expense_date' => 'expense_date' ),
		"{$prefix}batches"   => array( 'idx_class_id' => 'class_id', 'idx_teacher' => 'teacher_user_id', 'idx_status' => 'status' ),
		"{$prefix}announcements" => array( 'idx_batch_id' => 'batch_id', 'idx_created' => 'created_at' ),
	);
	
	foreach ( $indexes as $table => $table_indexes ) {
		foreach ( $table_indexes as $name => $columns ) {
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX {$name} ({$columns})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	
	update_option( 'cmp_indexes_v200_done', true, false );
}

/**
 * Admin scripts - enhanced with new v2 assets.
 */
add_action( 'admin_enqueue_scripts', 'cmp_enqueue_admin_assets_v2', 20 );

function cmp_enqueue_admin_assets_v2() {
	if ( ! cmp_is_cmp_admin_page() ) {
		return;
	}
	
	// Existing assets.
	wp_enqueue_style( 'cmp-admin', CMP_PLUGIN_URL . 'assets/css/admin.css', array(), CMP_VERSION );
	
	// New v2 enhancements.
	wp_enqueue_style( 'cmp-admin-v2', CMP_PLUGIN_URL . 'assets/css/admin-v2.css', array( 'cmp-admin' ), CMP_VERSION );
	
	wp_enqueue_script( 'cmp-admin', CMP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), CMP_VERSION, true );
	wp_enqueue_script( 'cmp-admin-v2', CMP_PLUGIN_URL . 'assets/js/admin-v2.js', array( 'jquery', 'cmp-admin' ), CMP_VERSION, true );
	
	// Localize with enhanced data.
	wp_localize_script(
		'cmp-admin-v2',
		'CMPAdminV2',
		array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'cmp_admin_nonce' ),
			'restUrl'           => rest_url( 'cmp/v2/' ),
			'restNonce'         => wp_create_nonce( 'wp_rest' ),
			'currentUserId'     => get_current_user_id(),
			'isAdmin'           => current_user_can( 'manage_options' ),
			'dashboardAutoRefresh' => apply_filters( 'cmp_dashboard_auto_refresh', true ),
			'dashboardRefreshInterval' => apply_filters( 'cmp_dashboard_refresh_interval', 60 ),
			'labels'            => array(
				'saving'           => __( 'Saving...', 'class-manager-pro' ),
				'saved'            => __( 'Saved!', 'class-manager-pro' ),
				'error'            => __( 'Error occurred', 'class-manager-pro' ),
				'loading'          => __( 'Loading...', 'class-manager-pro' ),
				'noResults'        => __( 'No results found', 'class-manager-pro' ),
				'searchPlaceholder'=> __( 'Type to search...', 'class-manager-pro' ),
				'bulkConfirm'      => __( 'Are you sure? This will affect {count} items.', 'class-manager-pro' ),
				'deleteConfirm'    => __( 'Are you sure you want to delete this?', 'class-manager-pro' ),
			),
			'features'          => array(
				'smartSearch'      => true,
				'ajaxFilter'       => true,
				'inlineEdit'       => true,
				'quickAdd'         => true,
				'realtime'         => true,
				'notifications'    => true,
			),
		)
	);
}

/**
 * Public scripts - enhanced.
 */
add_action( 'wp_enqueue_scripts', 'cmp_enqueue_public_assets_v2', 20 );

function cmp_enqueue_public_assets_v2() {
	if ( ! is_singular() ) {
		return;
	}
	
	$content = get_post_field( 'post_content', get_the_ID() );
	if ( false === strpos( $content, '[class_manager_pro_form' ) ) {
		return;
	}
	
	wp_enqueue_style( 'cmp-public', CMP_PLUGIN_URL . 'assets/css/public.css', array(), CMP_VERSION );
	wp_enqueue_script( 'cmp-public-v2', CMP_PLUGIN_URL . 'assets/js/public-v2.js', array( 'jquery' ), CMP_VERSION, true );
	
	wp_localize_script(
		'cmp-public-v2',
		'CMPPublicV2',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'cmp_public_nonce' ),
			'messages'  => array(
				'submitting' => __( 'Submitting...', 'class-manager-pro' ),
				'success'    => __( 'Registration successful!', 'class-manager-pro' ),
				'error'      => __( 'Something went wrong. Please try again.', 'class-manager-pro' ),
			),
		)
	);
}

/**
 * Admin menu - enhanced with new pages.
 */
add_action( 'admin_menu', 'cmp_admin_menu_v2', 5 );

function cmp_admin_menu_v2() {
	// Existing pages remain exactly as-is for backward compatibility.
	add_menu_page(
		__( 'Class Manager Pro', 'class-manager-pro' ),
		__( 'Class Manager Pro', 'class-manager-pro' ),
		'cmp_manage',
		'cmp-dashboard',
		'cmp_render_dashboard_page',
		'dashicons-welcome-learn-more',
		25
	);
	
	add_submenu_page( 'cmp-dashboard', __( 'Dashboard', 'class-manager-pro' ), __( 'Dashboard', 'class-manager-pro' ), 'cmp_manage', 'cmp-dashboard', 'cmp_render_dashboard_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Classes', 'class-manager-pro' ), __( 'Classes', 'class-manager-pro' ), 'cmp_manage', 'cmp-classes', 'cmp_render_classes_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Batches', 'class-manager-pro' ), __( 'Batches', 'class-manager-pro' ), 'cmp_manage', 'cmp-batches', 'cmp_render_batches_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Students', 'class-manager-pro' ), __( 'Students', 'class-manager-pro' ), 'cmp_manage', 'cmp-students', 'cmp_render_students_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Payments', 'class-manager-pro' ), __( 'Payments', 'class-manager-pro' ), 'cmp_manage', 'cmp-payments', 'cmp_render_payments_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Payment Trash', 'class-manager-pro' ), __( 'Payment Trash', 'class-manager-pro' ), 'cmp_manage', 'cmp-payments-trash', 'cmp_render_payment_trash_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Analytics', 'class-manager-pro' ), __( 'Analytics', 'class-manager-pro' ), 'cmp_manage', 'cmp-analytics', 'cmp_render_analytics_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Teacher Console', 'class-manager-pro' ), __( 'Teacher Console', 'class-manager-pro' ), 'read', 'cmp-teacher-console', 'cmp_render_teacher_console_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Razorpay Import', 'class-manager-pro' ), __( 'Razorpay Import', 'class-manager-pro' ), 'cmp_manage', 'cmp-razorpay-import', 'cmp_render_razorpay_import_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Settings', 'class-manager-pro' ), __( 'Settings', 'class-manager-pro' ), 'manage_options', 'cmp-settings', 'cmp_render_settings_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Add New', 'class-manager-pro' ), __( 'Add New', 'class-manager-pro' ), 'cmp_manage', 'cmp-add-new', 'cmp_render_add_new_page' );
	add_submenu_page( 'cmp-dashboard', __( 'All Data', 'class-manager-pro' ), __( 'All Data', 'class-manager-pro' ), 'cmp_manage', 'cmp-all-data', 'cmp_render_all_data_page' );
	
	// New v2 pages (additive).
	add_submenu_page( 'cmp-dashboard', __( 'Health Check', 'class-manager-pro' ), __( 'Health Check', 'class-manager-pro' ), 'manage_options', 'cmp-health-check', 'cmp_render_health_check_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Notifications', 'class-manager-pro' ), __( 'Notifications', 'class-manager-pro' ), 'cmp_manage', 'cmp-notifications', 'cmp_render_notifications_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Backup & Export', 'class-manager-pro' ), __( 'Backup & Export', 'class-manager-pro' ), 'manage_options', 'cmp-backup', 'cmp_render_backup_page' );
}

/**
 * Enhanced capability mapping.
 */
add_action( 'admin_init', 'cmp_create_roles_and_caps', 5 );

function cmp_create_roles_and_caps() {
	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->add_cap( 'cmp_manage' );
		$role->add_cap( 'cmp_view_analytics' );
		$role->add_cap( 'cmp_manage_settings' );
		$role->add_cap( 'cmp_manage_backups' );
		$role->add_cap( 'cmp_run_health_check' );
	}
	
	$role = get_role( 'editor' );
	if ( $role ) {
		$role->add_cap( 'cmp_manage' );
		$role->add_cap( 'cmp_view_analytics' );
	}
}

/**
 * Check if current screen is a CMP admin page.
 */
function cmp_is_cmp_admin_page() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}
	return false !== strpos( $screen->id, 'cmp-' );
}

/**
 * Global exception handler for CMP operations.
 */
add_action( 'init', 'cmp_register_exception_handler', 1 );

function cmp_register_exception_handler() {
	set_error_handler( 'cmp_error_handler', E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED );
}

function cmp_error_handler( $errno, $errstr, $errfile, $errline ) {
	if ( false !== strpos( $errfile, 'class-manager-pro' ) ) {
		CMP_Performance_Monitor::log_error( $errstr, $errfile, $errline, $errno );
	}
	return false; // Let PHP handle it too.
}

/**
 * Admin bar quick links for CMP.
 */
add_action( 'admin_bar_menu', 'cmp_admin_bar_quick_links', 100 );

function cmp_admin_bar_quick_links( $wp_admin_bar ) {
	if ( ! current_user_can( 'cmp_manage' ) ) {
		return;
	}
	
	$wp_admin_bar->add_node(
		array(
			'id'    => 'cmp-quick-menu',
			'title' => '<span class="ab-icon dashicons dashicons-welcome-learn-more"></span> ' . __( 'CMP', 'class-manager-pro' ),
			'href'  => admin_url( 'admin.php?page=cmp-dashboard' ),
			'meta'  => array( 'title' => __( 'Class Manager Pro', 'class-manager-pro' ) ),
		)
	);
	
	$quick_items = array(
		'cmp-quick-dashboard'  => array( __( 'Dashboard', 'class-manager-pro' ), 'cmp-dashboard' ),
		'cmp-quick-students'     => array( __( 'Students', 'class-manager-pro' ), 'cmp-students' ),
		'cmp-quick-payments'     => array( __( 'Payments', 'class-manager-pro' ), 'cmp-payments' ),
		'cmp-quick-batches'      => array( __( 'Batches', 'class-manager-pro' ), 'cmp-batches' ),
		'cmp-quick-analytics'    => array( __( 'Analytics', 'class-manager-pro' ), 'cmp-analytics' ),
		'cmp-quick-add-student'  => array( __( '+ Add Student', 'class-manager-pro' ), 'cmp-students#cmp-add-student' ),
		'cmp-quick-add-payment'  => array( __( '+ Add Payment', 'class-manager-pro' ), 'cmp-payments#cmp-add-payment' ),
	);
	
	foreach ( $quick_items as $id => $item ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => $id,
				'parent' => 'cmp-quick-menu',
				'title'  => $item[0],
				'href'   => admin_url( 'admin.php?page=' . $item[1] ),
			)
		);
	}
}

/**
 * Footer text enhancement.
 */
add_filter( 'admin_footer_text', 'cmp_admin_footer_text', 20 );

function cmp_admin_footer_text( $text ) {
	if ( ! cmp_is_cmp_admin_page() ) {
		return $text;
	}
	
	return sprintf(
		/* translators: %s: version number */
		__( 'Class Manager Pro %s | Built for scale. Need help? Check the Health Check page.', 'class-manager-pro' ),
		CMP_VERSION
	);
}
