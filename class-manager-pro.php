<?php
/**
 * Plugin Name: Class Manager Pro
 * Description: Admin system for managing classes, batches, students, payments, analytics, and secure batch intake links.
 * Version: 1.3.1
 * Author: Class Manager Pro
 * Text Domain: class-manager-pro
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMP_VERSION', '1.3.1' );
define( 'CMP_PLUGIN_FILE', __FILE__ );
define( 'CMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CMP_PLUGIN_DIR . 'includes/db.php';
require_once CMP_PLUGIN_DIR . 'includes/functions.php';
require_once CMP_PLUGIN_DIR . 'includes/razorpay.php';
require_once CMP_PLUGIN_DIR . 'includes/public.php';

require_once CMP_PLUGIN_DIR . 'admin/dashboard.php';
require_once CMP_PLUGIN_DIR . 'admin/all-data.php';
require_once CMP_PLUGIN_DIR . 'admin/classes.php';
require_once CMP_PLUGIN_DIR . 'admin/batches.php';
require_once CMP_PLUGIN_DIR . 'admin/students.php';
require_once CMP_PLUGIN_DIR . 'admin/add-new.php';
require_once CMP_PLUGIN_DIR . 'admin/payments.php';
require_once CMP_PLUGIN_DIR . 'admin/razorpay-import.php';
require_once CMP_PLUGIN_DIR . 'admin/analytics.php';
require_once CMP_PLUGIN_DIR . 'admin/settings.php';

register_activation_hook( __FILE__, 'cmp_install' );
register_deactivation_hook( __FILE__, 'cmp_clear_scheduled_fee_reminders' );

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
	add_submenu_page( 'cmp-dashboard', __( 'All Data', 'class-manager-pro' ), __( 'All Data', 'class-manager-pro' ), 'manage_options', 'cmp-all-data', 'cmp_render_all_data_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Classes', 'class-manager-pro' ), __( 'Classes', 'class-manager-pro' ), 'manage_options', 'cmp-classes', 'cmp_render_classes_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Batches', 'class-manager-pro' ), __( 'Batches', 'class-manager-pro' ), 'manage_options', 'cmp-batches', 'cmp_render_batches_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Students', 'class-manager-pro' ), __( 'Students', 'class-manager-pro' ), 'manage_options', 'cmp-students', 'cmp_render_students_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Add New', 'class-manager-pro' ), __( 'Add New', 'class-manager-pro' ), 'manage_options', 'cmp-add-new', 'cmp_render_add_new_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Payments', 'class-manager-pro' ), __( 'Payments', 'class-manager-pro' ), 'manage_options', 'cmp-payments', 'cmp_render_payments_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Razorpay Import', 'class-manager-pro' ), __( 'Razorpay Import', 'class-manager-pro' ), 'manage_options', 'cmp-razorpay-import', 'cmp_render_razorpay_import_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Analytics', 'class-manager-pro' ), __( 'Analytics', 'class-manager-pro' ), 'manage_options', 'cmp-analytics', 'cmp_render_analytics_page' );
	add_submenu_page( 'cmp-dashboard', __( 'Settings', 'class-manager-pro' ), __( 'Settings', 'class-manager-pro' ), 'manage_options', 'cmp-settings', 'cmp_render_settings_page' );
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

	if ( in_array( $page, array( 'cmp-dashboard', 'cmp-analytics' ), true ) ) {
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
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cmp_admin_nonce' ),
		)
	);
}
