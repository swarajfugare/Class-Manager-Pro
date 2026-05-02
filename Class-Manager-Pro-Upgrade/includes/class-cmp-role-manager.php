<?php
/**
 * Class CMP_Role_Manager
 *
 * Granular role-based access control.
 *
 * @package ClassManagerPro
 */
class CMP_Role_Manager {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_cmp_caps' ), 10, 4 );
	}

	public static function ensure_capabilities() {
		$roles = array( 'administrator', 'editor' );
		$caps  = array(
			'cmp_manage',
			'cmp_view_analytics',
			'cmp_manage_settings',
			'cmp_manage_backups',
			'cmp_run_health_check',
			'cmp_view_teacher_console',
			'cmp_edit_students',
			'cmp_delete_students',
			'cmp_edit_payments',
			'cmp_delete_payments',
			'cmp_edit_batches',
			'cmp_edit_classes',
		);

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}

		// Teacher role.
		$teacher = get_role( 'teacher' );
		if ( $teacher ) {
			$teacher->add_cap( 'cmp_view_teacher_console' );
			$teacher->add_cap( 'cmp_edit_students' );
			$teacher->add_cap( 'cmp_edit_batches' );
		}
	}

	public static function map_cmp_caps( $caps, $cap, $user_id, $args ) {
		if ( false === strpos( $cap, 'cmp_' ) ) {
			return $caps;
		}

		// Admin has all CMP caps.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return array();
		}

		// Editor has cmp_manage.
		if ( user_can( $user_id, 'cmp_manage' ) ) {
			return array();
		}

		return $caps;
	}

	public static function user_can_view( $user_id, $context ) {
		$context_caps = array(
			'dashboard'    => 'cmp_manage',
			'classes'      => 'cmp_manage',
			'batches'      => 'cmp_manage',
			'students'     => 'cmp_manage',
			'payments'     => 'cmp_manage',
			'analytics'    => 'cmp_view_analytics',
			'settings'     => 'cmp_manage_settings',
			'health_check' => 'cmp_run_health_check',
			'backup'       => 'cmp_manage_backups',
			'teacher_console' => 'cmp_view_teacher_console',
		);

		$cap = $context_caps[ $context ] ?? 'cmp_manage';
		return user_can( $user_id, $cap );
	}
}
