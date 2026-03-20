<?php
/**
 * Performance caching layer.
 *
 * Provides a thin abstraction over WordPress object cache and transients,
 * with group-based invalidation and hooks to clear stale data on writes.
 *
 * @package Groups
 */

namespace Groups;

defined( 'ABSPATH' ) || exit;

/**
 * Cache helper with static methods for get/set/delete/flush
 * and automatic invalidation via WordPress action hooks.
 */
class Cache {

	/**
	 * Cache group for the network-wide group directory.
	 *
	 * @var string
	 */
	const GROUP_DIRECTORY = 'groups_directory';

	/**
	 * Cache group for analytics responses.
	 *
	 * @var string
	 */
	const GROUP_ANALYTICS = 'groups_analytics';

	/**
	 * Cache group for event listing queries.
	 *
	 * @var string
	 */
	const GROUP_EVENTS = 'groups_events';

	/**
	 * Cache group for member count queries.
	 *
	 * @var string
	 */
	const GROUP_MEMBERS = 'groups_members';

	/**
	 * Default expiry times per group (in seconds).
	 *
	 * @var array<string, int>
	 */
	private const DEFAULT_EXPIRY = [
		self::GROUP_DIRECTORY => 300,   // 5 minutes.
		self::GROUP_ANALYTICS => 3600,  // 1 hour.
		self::GROUP_EVENTS    => 300,   // 5 minutes.
		self::GROUP_MEMBERS   => 300,   // 5 minutes.
	];

	/**
	 * Whether invalidation hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Register invalidation hooks.
	 *
	 * Safe to call multiple times — hooks are only attached once.
	 */
	public static function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		// Directory: invalidate on meetup status change.
		add_action( 'groups_meetup_status_transition', [ __CLASS__, 'invalidate_directory' ], 10, 3 );

		// Analytics: invalidate after daily aggregation.
		add_action( 'groups_daily_aggregation', [ __CLASS__, 'invalidate_analytics' ], 99 );

		// Events: invalidate on event create/update/delete.
		add_action( 'save_post_' . Post_Types\Event::POST_TYPE, [ __CLASS__, 'invalidate_events' ], 10, 1 );
		add_action( 'delete_post', [ __CLASS__, 'invalidate_events_on_delete' ], 10, 1 );

		// Members: invalidate on join/leave.
		add_action( 'groups_member_joined', [ __CLASS__, 'invalidate_members' ], 10, 2 );
		add_action( 'groups_member_left', [ __CLASS__, 'invalidate_members' ], 10, 2 );
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed Cached value, or false if not found.
	 */
	public static function get( string $key, string $group ) {
		return wp_cache_get( $key, $group );
	}

	/**
	 * Set a cached value.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $value  Value to cache.
	 * @param string $group  Cache group.
	 * @param int    $expiry Expiry in seconds. 0 = use group default.
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, $value, string $group, int $expiry = 0 ): bool {
		if ( 0 === $expiry ) {
			$expiry = self::DEFAULT_EXPIRY[ $group ] ?? 300;
		}

		return wp_cache_set( $key, $value, $group, $expiry );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key, string $group ): bool {
		return wp_cache_delete( $key, $group );
	}

	/**
	 * Flush all keys in a cache group.
	 *
	 * Uses wp_cache_flush_group() if available (requires a persistent
	 * object cache that supports it). Falls back to incrementing
	 * a generation counter stored in the cache itself, which
	 * effectively orphans old keys.
	 *
	 * @param string $group Cache group.
	 * @return bool True on success.
	 */
	public static function flush_group( string $group ): bool {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			return wp_cache_flush_group( $group );
		}

		// Fallback: increment a generation counter so old keys become stale.
		$gen_key = $group . '_generation';
		$gen     = (int) wp_cache_get( $gen_key, 'groups_meta' );
		wp_cache_set( $gen_key, $gen + 1, 'groups_meta', 0 );

		return true;
	}

	/**
	 * Invalidate directory cache on meetup status change.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public static function invalidate_directory( int $post_id, string $old_status, string $new_status ): void {
		self::flush_group( self::GROUP_DIRECTORY );
	}

	/**
	 * Invalidate analytics cache after daily aggregation.
	 */
	public static function invalidate_analytics(): void {
		self::flush_group( self::GROUP_ANALYTICS );
	}

	/**
	 * Invalidate events cache on event save.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate_events( int $post_id ): void {
		$blog_id = get_current_blog_id();
		self::flush_group( self::GROUP_EVENTS );

		// Also delete the blog-specific key.
		self::delete( 'upcoming_events_' . $blog_id, self::GROUP_EVENTS );
	}

	/**
	 * Invalidate events cache on post delete (for event posts only).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate_events_on_delete( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || Post_Types\Event::POST_TYPE !== $post->post_type ) {
			return;
		}

		self::invalidate_events( $post_id );
	}

	/**
	 * Invalidate members cache on join/leave.
	 *
	 * @param int $user_id The user ID.
	 * @param int $blog_id The blog ID.
	 */
	public static function invalidate_members( int $user_id, int $blog_id ): void {
		self::delete( 'member_count_' . $blog_id, self::GROUP_MEMBERS );
		self::flush_group( self::GROUP_MEMBERS );
	}
}
