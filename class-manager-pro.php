<?php
/**
 * Plugin Name: Class Manager Pro
 * Description: Advanced Class, Batch, Student and Payment Management System with Tutor LMS and Razorpay Integration
 * Version: 3.6.0
 * Author: Swaraj Fugare
 * Author URI: https://portfolio.matoshreecollection.in
 * Text Domain: class-manager-pro
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMP_VERSION', '3.6.0' );
define( 'CMP_PLUGIN_FILE', __FILE__ );
define( 'CMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CMP_PLUGIN_DIR . 'includes/db.php';
require_once CMP_PLUGIN_DIR . 'includes/functions.php';
require_once CMP_PLUGIN_DIR . 'includes/next-version.php';
require_once CMP_PLUGIN_DIR . 'includes/operations-center.php';
require_once CMP_PLUGIN_DIR . 'includes/communication-center.php';
require_once CMP_PLUGIN_DIR . 'includes/tutor.php';
require_once CMP_PLUGIN_DIR . 'includes/razorpay.php';
require_once CMP_PLUGIN_DIR . 'includes/public.php';
require_once CMP_PLUGIN_DIR . 'includes/smartcertify-integration.php';

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
	require_once CMP_PLUGIN_DIR . 'admin/operations-center.php';
	require_once CMP_PLUGIN_DIR . 'admin/communication-center.php';
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
	if ( function_exists( 'cmp_schedule_operations_center_reminders' ) ) {
		cmp_schedule_operations_center_reminders();
	}
	if ( function_exists( 'cmp_schedule_communication_campaign_runner' ) ) {
		cmp_schedule_communication_campaign_runner();
	}
	if ( function_exists( 'cmp_schedule_daily_institute_digest' ) ) {
		cmp_schedule_daily_institute_digest();
	}
}

/**
 * Handles plugin deactivation routines.
 */
function cmp_deactivate_plugin() {
	cmp_clear_scheduled_fee_reminders();
	if ( function_exists( 'cmp_operations_reminder_hook' ) ) {
		cmp_clear_scheduled_hook( cmp_operations_reminder_hook() );
	}
	cmp_clear_scheduled_hook( 'cmp_communication_campaign_runner' );
	cmp_clear_scheduled_hook( 'cmp_daily_institute_digest_cron' );
}

add_action( 'admin_menu', 'cmp_register_admin_menu' );
add_action( 'admin_enqueue_scripts', 'cmp_enqueue_admin_assets' );
add_action( 'rest_api_init', array( 'CMP_Razorpay_Webhook', 'register_routes' ) );
add_action( 'rest_api_init', 'cmp_register_whatsapp_webhook_routes' );
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
	add_submenu_page( 'cmp-dashboard', __( 'Communication Center', 'class-manager-pro' ), __( 'Communication Center', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center', 'cmp_render_communication_center_page' );
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

	$can_access_operations = current_user_can( 'manage_options' ) || ( function_exists( 'cmp_current_user_has_teacher_batches' ) && cmp_current_user_has_teacher_batches() );

	if ( $can_access_operations ) {
		add_menu_page(
			__( 'Operations Center', 'class-manager-pro' ),
			__( 'Operations Center', 'class-manager-pro' ),
			'read',
			'cmp-operations-center',
			'cmp_render_operations_center_page',
			'dashicons-calendar-alt',
			27
		);

		add_submenu_page( 'cmp-operations-center', __( 'Dashboard', 'class-manager-pro' ), __( 'Dashboard', 'class-manager-pro' ), 'read', 'cmp-operations-center', 'cmp_render_operations_center_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Calendar', 'class-manager-pro' ), __( 'Calendar', 'class-manager-pro' ), 'read', 'cmp-operations-center-calendar', 'cmp_render_operations_calendar_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Monthly Planner', 'class-manager-pro' ), __( 'Monthly Planner', 'class-manager-pro' ), 'read', 'cmp-operations-center-planner', 'cmp_render_operations_planner_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Batch Schedule', 'class-manager-pro' ), __( 'Batch Schedule', 'class-manager-pro' ), 'read', 'cmp-operations-center-batch-schedule', 'cmp_render_operations_batch_schedule_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Teacher Schedule', 'class-manager-pro' ), __( 'Teacher Schedule', 'class-manager-pro' ), 'read', 'cmp-operations-center-teacher-schedule', 'cmp_render_operations_teacher_schedule_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Marketing Planner', 'class-manager-pro' ), __( 'Marketing Planner', 'class-manager-pro' ), 'read', 'cmp-operations-center-marketing', 'cmp_render_operations_marketing_planner_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Business Planner', 'class-manager-pro' ), __( 'Business Planner', 'class-manager-pro' ), 'read', 'cmp-operations-center-business', 'cmp_render_operations_business_planner_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Documents', 'class-manager-pro' ), __( 'Documents', 'class-manager-pro' ), 'read', 'cmp-operations-center-documents', 'cmp_render_operations_documents_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Holiday Manager', 'class-manager-pro' ), __( 'Holiday Manager', 'class-manager-pro' ), 'read', 'cmp-operations-center-holidays', 'cmp_render_operations_holidays_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Goals', 'class-manager-pro' ), __( 'Goals', 'class-manager-pro' ), 'read', 'cmp-operations-center-goals', 'cmp_render_operations_goals_page' );
		add_submenu_page( 'cmp-operations-center', __( 'Settings', 'class-manager-pro' ), __( 'Settings', 'class-manager-pro' ), 'read', 'cmp-operations-center-settings', 'cmp_render_operations_settings_page' );
	}

	if ( current_user_can( 'manage_options' ) ) {
		add_menu_page(
			__( 'Communication Center', 'class-manager-pro' ),
			__( 'Communication Center', 'class-manager-pro' ),
			'manage_options',
			'cmp-communication-center',
			'cmp_render_communication_center_page',
			'dashicons-email-alt2',
			28
		);

		add_submenu_page( 'cmp-communication-center', __( 'Overview', 'class-manager-pro' ), __( 'Overview', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center', 'cmp_render_communication_center_page' );
		add_submenu_page( 'cmp-communication-center', __( 'Email Center', 'class-manager-pro' ), __( 'Email Center', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-email', 'cmp_render_communication_email_page' );
		add_submenu_page( 'cmp-communication-center', __( 'WhatsApp Center', 'class-manager-pro' ), __( 'WhatsApp Center', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-whatsapp', 'cmp_render_communication_whatsapp_page' );
		add_submenu_page( 'cmp-communication-center', __( 'Campaign Manager', 'class-manager-pro' ), __( 'Campaign Manager', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-campaigns', 'cmp_render_communication_campaigns_page' );
		add_submenu_page( 'cmp-communication-center', __( 'Templates', 'class-manager-pro' ), __( 'Templates', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-templates', 'cmp_render_communication_templates_page' );
		add_submenu_page( 'cmp-communication-center', __( 'Automation', 'class-manager-pro' ), __( 'Automation', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-automation', 'cmp_render_communication_automation_page' );
		add_submenu_page( 'cmp-communication-center', __( 'History', 'class-manager-pro' ), __( 'History', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-history', 'cmp_render_communication_history_page' );
		add_submenu_page( 'cmp-communication-center', __( 'Statistics', 'class-manager-pro' ), __( 'Statistics', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-statistics', 'cmp_render_communication_statistics_page' );
		add_submenu_page( 'cmp-communication-center', __( 'Settings', 'class-manager-pro' ), __( 'Settings', 'class-manager-pro' ), 'manage_options', 'cmp-communication-center-settings', 'cmp_render_communication_settings_page' );
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

	if ( in_array( $page, array( 'cmp-analytics', 'cmp-dashboard', 'cmp-communication-center-statistics' ), true ) ) {
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js',
			array(),
			'4.4.1',
			true
		);
	}

	if ( 0 === strpos( $page, 'cmp-operations-center' ) ) {
		wp_enqueue_style(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css',
			array(),
			'6.1.15'
		);

		wp_enqueue_script(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
			array(),
			'6.1.15',
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

	if ( 0 === strpos( $page, 'cmp-operations-center' ) ) {
		wp_localize_script(
			'cmp-admin',
			'CMPOperations',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'cmp_admin_nonce' ),
				'calendarView'  => cmp_get_operations_center_settings()['calendar_default_view'],
				'isAdmin'      => current_user_can( 'manage_options' ) ? 1 : 0,
				'isTeacher'    => cmp_operations_is_teacher_view() ? 1 : 0,
				'currentUserId'=> get_current_user_id(),
			)
		);
	}
}
