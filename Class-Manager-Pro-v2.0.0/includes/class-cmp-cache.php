<?php
/**
 * Class CMP_Cache
 *
 * Intelligent caching layer for all CMP database queries.
 * Uses WordPress object cache with automatic invalidation.
 *
 * @package ClassManagerPro
 */

class CMP_Cache {

	/** @var string Cache group name */
	const GROUP = 'class_manager_pro';

	/** @var int Default cache TTL in seconds */
	const DEFAULT_TTL = 300;

	/** @var int Dashboard metrics TTL */
	const DASHBOARD_TTL = 300;

	/** @var int Long cache TTL for mostly-static data */
	const LONG_TTL = 3600;

	/** @var array List of cache keys for selective invalidation */
	private static $entity_keys = array(
		'classes',
		'batches',
		'students',
		'payments',
		'attendance',
		'expenses',
		'announcements',
		'interested',
		'tokens',
		'teacher_logs',
		'admin_logs',
		'dashboard',
		'analytics',
		'metrics',
	);

	/** @var bool Whether caching is enabled */
	private static $enabled = true;

	/** @var array Cache hit/miss statistics */
	private static $stats = array(
		'hits'   => 0,
		'misses' => 0,
	);

	/**
	 * Initialize the caching system.
	 */
	public static function init() {
		self::$enabled = wp_using_ext_object_cache() || true; // Always use transients as fallback.
		
		// Auto-invalidate on data changes.
		add_action( 'cmp_after_save_class', array( __CLASS__, 'invalidate_classes' ) );
		add_action( 'cmp_after_save_batch', array( __CLASS__, 'invalidate_batches' ) );
		add_action( 'cmp_after_save_student', array( __CLASS__, 'invalidate_students' ) );
		add_action( 'cmp_after_save_payment', array( __CLASS__, 'invalidate_payments' ) );
		add_action( 'cmp_after_save_attendance', array( __CLASS__, 'invalidate_attendance' ) );
		add_action( 'cmp_after_save_expense', array( __CLASS__, 'invalidate_expenses' ) );
		add_action( 'cmp_after_save_announcement', array( __CLASS__, 'invalidate_announcements' ) );
		add_action( 'cmp_after_delete', array( __CLASS__, 'invalidate_all' ), 10, 2 );
	}

	/**
	 * Get cached data or compute it.
	 *
	 * @param string   $key     Cache key.
	 * @param callable $callback Function to compute value if not cached.
	 * @param int      $ttl      Time to live in seconds.
	 * @return mixed
	 */
	public static function get( $key, $callback, $ttl = self::DEFAULT_TTL ) {
		if ( ! self::$enabled ) {
			return call_user_func( $callback );
		}

		$cache_key = self::build_key( $key );
		$value     = wp_cache_get( $cache_key, self::GROUP );

		if ( false !== $value ) {
			self::$stats['hits']++;
			return is_array( $value ) && isset( $value['__cmp_serialized__'] ) ? maybe_unserialize( $value['data'] ) : $value;
		}

		self::$stats['misses']++;
		$value = call_user_func( $callback );

		// Store non-scalar values as serialized wrappers.
		if ( is_object( $value ) || is_array( $value ) ) {
			$stored = array( '__cmp_serialized__' => true, 'data' => maybe_serialize( $value ) );
		} else {
			$stored = $value;
		}

		wp_cache_set( $cache_key, $stored, self::GROUP, $ttl );

		return $value;
	}

	/**
	 * Set a cache value directly.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live.
	 */
	public static function set( $key, $value, $ttl = self::DEFAULT_TTL ) {
		$cache_key = self::build_key( $key );
		if ( is_object( $value ) || is_array( $value ) ) {
			$stored = array( '__cmp_serialized__' => true, 'data' => maybe_serialize( $value ) );
		} else {
			$stored = $value;
		}
		wp_cache_set( $cache_key, $stored, self::GROUP, $ttl );
	}

	/**
	 * Delete a specific cache key.
	 *
	 * @param string $key Cache key.
	 */
	public static function delete( $key ) {
		wp_cache_delete( self::build_key( $key ), self::GROUP );
	}

	/**
	 * Invalidate all caches for a specific entity type.
	 *
	 * @param string $entity Entity type.
	 */
	public static function invalidate( $entity ) {
		foreach ( self::$entity_keys as $key ) {
			if ( false !== strpos( $key, $entity ) || false !== strpos( $entity, $key ) ) {
				wp_cache_delete( self::build_key( $key ), self::GROUP );
			}
		}
		// Also invalidate dashboard and analytics.
		self::invalidate_dashboard();
		self::invalidate_analytics();
	}

	/**
	 * Invalidate class-related caches.
	 */
	public static function invalidate_classes() {
		self::invalidate( 'classes' );
		self::invalidate( 'batches' );
	}

	/**
	 * Invalidate batch-related caches.
	 */
	public static function invalidate_batches() {
		self::invalidate( 'batches' );
		self::invalidate( 'students' );
		self::invalidate( 'metrics' );
	}

	/**
	 * Invalidate student-related caches.
	 */
	public static function invalidate_students() {
		self::invalidate( 'students' );
		self::invalidate( 'payments' );
		self::invalidate( 'metrics' );
		self::invalidate( 'attendance' );
	}

	/**
	 * Invalidate payment-related caches.
	 */
	public static function invalidate_payments() {
		self::invalidate( 'payments' );
		self::invalidate( 'metrics' );
		self::invalidate( 'dashboard' );
	}

	/**
	 * Invalidate attendance-related caches.
	 */
	public static function invalidate_attendance() {
		self::invalidate( 'attendance' );
		self::invalidate( 'analytics' );
	}

	/**
	 * Invalidate expense-related caches.
	 */
	public static function invalidate_expenses() {
		self::invalidate( 'expenses' );
		self::invalidate( 'metrics' );
	}

	/**
	 * Invalidate announcement-related caches.
	 */
	public static function invalidate_announcements() {
		self::invalidate( 'announcements' );
	}

	/**
	 * Invalidate all dashboard caches.
	 */
	public static function invalidate_dashboard() {
		self::invalidate( 'dashboard' );
		self::invalidate( 'metrics' );
	}

	/**
	 * Invalidate all analytics caches.
	 */
	public static function invalidate_analytics() {
		self::invalidate( 'analytics' );
	}

	/**
	 * Invalidate all caches.
	 */
	public static function invalidate_all( $object_type = '', $object_id = 0 ) {
		foreach ( self::$entity_keys as $key ) {
			wp_cache_delete( self::build_key( $key ), self::GROUP );
		}
		// Clear wildcard caches.
		wp_cache_flush();
	}

	/**
	 * Clear all CMP caches.
	 */
	public static function clear_all() {
		self::invalidate_all();
	}

	/**
	 * Warm up the cache with commonly accessed data.
	 */
	public static function warmup_cache() {
		// Pre-load classes and batches into cache.
		if ( function_exists( 'cmp_get_classes' ) ) {
			$classes = cmp_get_classes();
			self::set( 'classes_all', $classes, self::LONG_TTL );
		}
		if ( function_exists( 'cmp_get_batches' ) ) {
			$batches = cmp_get_batches();
			self::set( 'batches_all', $batches, self::LONG_TTL );
		}
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$total = self::$stats['hits'] + self::$stats['misses'];
		return array(
			'hits'       => self::$stats['hits'],
			'misses'     => self::$stats['misses'],
			'total'      => $total,
			'hit_rate'   => $total > 0 ? round( self::$stats['hits'] / $total * 100, 2 ) : 0,
		);
	}

	/**
	 * Build a safe cache key.
	 *
	 * @param string $key Base key.
	 * @return string
	 */
	private static function build_key( $key ) {
		return 'cmp_' . md5( $key );
	}

	/**
	 * Cache wrapper for expensive functions.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Function to call.
	 * @param array    $args     Arguments for the callback.
	 * @param int      $ttl      TTL.
	 * @return mixed
	 */
	public static function remember( $key, $callback, $args = array(), $ttl = self::DEFAULT_TTL ) {
		return self::get( $key, function () use ( $callback, $args ) {
			return call_user_func_array( $callback, $args );
		}, $ttl );
	}
}
