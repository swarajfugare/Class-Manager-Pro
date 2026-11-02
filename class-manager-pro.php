<?php
/**
 * Plugin Name: Class Manager Pro
 * Description: Advanced Class, Batch, Student and Payment Management System with Tutor LMS and Razorpay Integration
 * Version: 4.0.0
 * Author: Swaraj Fugare
 * Author URI: https://portfolio.matoshreecollection.in
 * Text Domain: class-manager-pro
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMP_VERSION', '4.0.0' );
define( 'CMP_PLUGIN_FILE', __FILE__ );
define( 'CMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CMP_PLUGIN_DIR . 'includes/db.php';
require_once CMP_PLUGIN_DIR . 'includes/functions.php';
require_once CMP_PLUGIN_DIR . 'includes/next-version.php';
require_once CMP_PLUGIN_DIR . 'includes/tutor.php';
require_once CMP_PLUGIN_DIR . 'includes/razorpay.php';
require_once CMP_PLUGIN_DIR . 'includes/public.php';
require_once CMP_PLUGIN_DIR . 'includes/smartcertify-integration.php';
require_once CMP_PLUGIN_DIR . 'includes/whatsapp.php';

if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once CMP_PLUGIN_DIR . 'admin/dashboard.php';
	require_once CMP_PLUGIN_DIR . 'admin/all-data.php';
	require_once CMP_PLUGIN_DIR . 'admin/classes.php';
	require_once CMP_PLUGIN_DIR . 'admin/batches.php';
	require_once CMP_PLUGIN_DIR . 'admin/students.php';
	require_once CMP_PLUGIN_DIR . 'admin/interested-students.php';
	require_once CMP_PLUGIN_DIR . 'admin/add-new.php';
	require_once CMP_PLUGIN_DIR . 'admin/payments.php';
	require_once CMP_PLUGIN_DIR . 'admin/razorpay-import.php';
	require_once CMP_PLUGIN_DIR . 'admin/teacher-console.php';
	require_once CMP_PLUGIN_DIR . 'admin/analytics.php';
	require_once CMP_PLUGIN_DIR . 'admin/settings.php';
}

register_activation_hook( __FILE__, 'cmp_activate_plugin' );
register_deactivation_hook( __FILE__, 'cmp_deactivate_plugin' );

/**
 * Handles plugin activation routines.
 */
function cmp_activate_plugin() {
	cmp_install();
	cmp_schedule_daily_fee_reminders();
	if ( function_exists( 'cmp_schedule_daily_institute_digest' ) ) {
		cmp_schedule_daily_institute_digest();
	}
}

/**
 * Handles plugin deactivation routines.
 */
function cmp_deactivate_plugin() {
	cmp_clear_scheduled_fee_reminders();
	cmp_clear_scheduled_hook( 'cmp_daily_institute_digest_cron' );
}

add_action( 'admin_menu', 'cmp_register_admin_menu' );
add_action( 'admin_enqueue_scripts', 'cmp_enqueue_admin_assets' );
add_action( 'rest_api_init', array( 'CMP_Razorpay_Webhook', 'register_routes' ) );
add_action( 'plugins_loaded', 'cmp_maybe_upgrade' );
add_action( 'template_redirect', 'cmp_maybe_render_public_batch_page' );

/**
 * Registers wp-admin menu pages.
 */
function cmp_register_admin_menu() {
	add_menu_page(
		__( 'Class Manager Pro', 'class-manager-pro' ),
		__( 'Class Manager Pro', 'class-manager-pro' ),
		'manage_options',
		'cmp-dashboard',
		'cmp_render_dashboard_page',
		'dashicons-welcome-learn-more',
		26
	);

	add_submenu_page( 'cmp-dashboard', __( 'Dashboard', 'class-manager-pro' ), __( 'Dashboard', 'class-manager-pro' ), 'manage_options', 'cmp-dashboard', 'cmp_render_dashboard_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Students', 'class-manager-pro' ), __( 'Students', 'class-manager-pro' ), 'manage_options', 'cmp-students', 'cmp_render_students_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Classes & Batches', 'class-manager-pro' ), __( 'Classes & Batches', 'class-manager-pro' ), 'manage_options', 'cmp-batches', 'cmp_render_batches_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Payments', 'class-manager-pro' ), __( 'Payments', 'class-manager-pro' ), 'manage_options', 'cmp-payments', 'cmp_render_payments_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Reports & Analytics', 'class-manager-pro' ), __( 'Reports & Analytics', 'class-manager-pro' ), 'manage_options', 'cmp-analytics', 'cmp_render_analytics_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Teachers', 'class-manager-pro' ), __( 'Teachers', 'class-manager-pro' ), 'manage_options', 'cmp-teacher-console', 'cmp_render_teacher_console_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Settings', 'class-manager-pro' ), __( 'Settings', 'class-manager-pro' ), 'manage_options', 'cmp-settings', 'cmp_render_settings_page' );

	$legacy_pages = array(
		array( __( 'Classes', 'class-manager-pro' ), 'cmp-classes', 'cmp_render_classes_page' ),
		array( __( 'Interested Students', 'class-manager-pro' ), 'cmp-interested-students', 'cmp_render_interested_students_page' ),
		array( __( 'All Data', 'class-manager-pro' ), 'cmp-all-data', 'cmp_render_all_data_page' ),
		array( __( 'Payment Trash', 'class-manager-pro' ), 'cmp-payments-trash', 'cmp_render_payment_trash_page' ),
		array( __( 'Razorpay Import', 'class-manager-pro' ), 'cmp-razorpay-import', 'cmp_render_razorpay_import_page' ),
	);

	foreach ( $legacy_pages as $legacy_page ) {
		add_submenu_page( null, $legacy_page[0], $legacy_page[0], 'manage_options', $legacy_page[1], $legacy_page[2] );
	}

	if ( ! current_user_can( 'manage_options' ) && function_exists( 'cmp_current_user_has_teacher_batches' ) && cmp_current_user_has_teacher_batches() ) {
		add_menu_page(
			__( 'My Classes', 'class-manager-pro' ),
			__( 'My Classes', 'class-manager-pro' ),
			'read',
			'cmp-teacher-console',
			'cmp_render_teacher_console_page',
			'dashicons-groups',
			27
		);
	}
}

/**
 * Enqueues plugin assets only on Class Manager Pro admin screens.
 *
 * @param string $hook Current admin hook.
 */
function cmp_enqueue_admin_assets( $hook ) {
	unset( $hook );

	$page = sanitize_key( cmp_field( $_GET, 'page' ) );

	if ( 0 !== strpos( $page, 'cmp-' ) ) {
		return;
	}

	wp_enqueue_style(
		'cmp-admin',
		CMP_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		CMP_VERSION
	);

	if ( in_array( $page, array( 'cmp-analytics', 'cmp-dashboard' ), true ) ) {
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js',
			array(),
			'4.4.1',
			true
		);
	}

	wp_enqueue_script(
		'cmp-admin',
		CMP_PLUGIN_URL . 'assets/js/admin.js',
		array( 'jquery' ),
		CMP_VERSION,
		true
	);

	wp_localize_script(
		'cmp-admin',
		'CMPAdmin',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'cmp_admin_nonce' ),
			'deleteNonce'   => wp_create_nonce( 'cmp_delete_nonce' ),
			'attendanceNonce' => wp_create_nonce( 'cmp_save_attendance' ),
		)
	);
}
