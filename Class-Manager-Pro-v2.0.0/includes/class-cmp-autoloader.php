<?php
/**
 * Class CMP_Autoloader
 *
 * Simple PSR-4 style autoloader for CMP classes.
 *
 * @package ClassManagerPro
 */
class CMP_Autoloader {

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class ) {
		// Only handle CMP_ classes.
		if ( 0 !== strpos( $class, 'CMP_' ) ) {
			return;
		}

		// Convert CMP_Class_Name to class-cmp-class-name.php
		$filename = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
		$path     = CMP_PLUGIN_DIR . 'includes/' . $filename;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
