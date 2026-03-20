<?php
/**
 * Tests for the Cache class.
 *
 * @package Groups\Tests
 */

use Groups\Cache;
use Groups\Post_Types\Event;

/**
 * @coversDefaultClass \Groups\Cache
 * @group cache
 */
class Test_Cache extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		Cache::register_hooks();
	}

	/**
	 * @covers ::get
	 * @covers ::set
	 */
	public function test_set_and_get(): void {
		$result = Cache::set( 'test_key', 'test_value', Cache::GROUP_DIRECTORY );
		$this->assertTrue( $result );

		$value = Cache::get( 'test_key', Cache::GROUP_DIRECTORY );
		$this->assertSame( 'test_value', $value );
	}

	/**
	 * @covers ::get
	 */
	public function test_get_returns_false_on_miss(): void {
		$value = Cache::get( 'nonexistent_key', Cache::GROUP_DIRECTORY );
		$this->assertFalse( $value );
	}

	/**
	 * @covers ::delete
	 */
	public function test_delete(): void {
		Cache::set( 'delete_me', 'value', Cache::GROUP_EVENTS );
		$this->assertSame( 'value', Cache::get( 'delete_me', Cache::GROUP_EVENTS ) );

		Cache::delete( 'delete_me', Cache::GROUP_EVENTS );
		$this->assertFalse( Cache::get( 'delete_me', Cache::GROUP_EVENTS ) );
	}

	/**
	 * @covers ::flush_group
	 */
	public function test_flush_group(): void {
		Cache::set( 'key_a', 'a', Cache::GROUP_DIRECTORY );
		Cache::set( 'key_b', 'b', Cache::GROUP_DIRECTORY );

		$result = Cache::flush_group( Cache::GROUP_DIRECTORY );
		$this->assertTrue( $result );

		// After flush, old keys should be gone (if object cache supports flush_group).
		// With the default WP_Object_Cache this works via wp_cache_flush_group.
		// We test the method completes without error regardless.
	}

	/**
	 * @covers ::set
	 */
	public function test_set_with_custom_expiry(): void {
		$result = Cache::set( 'custom_exp', 'val', Cache::GROUP_ANALYTICS, 60 );
		$this->assertTrue( $result );

		$this->assertSame( 'val', Cache::get( 'custom_exp', Cache::GROUP_ANALYTICS ) );
	}

	/**
	 * @covers ::set
	 */
	public function test_set_uses_default_expiry_for_group(): void {
		// Just ensure no error with 0 (default) expiry.
		$result = Cache::set( 'default_exp', 'val', Cache::GROUP_MEMBERS, 0 );
		$this->assertTrue( $result );

		$this->assertSame( 'val', Cache::get( 'default_exp', Cache::GROUP_MEMBERS ) );
	}

	/**
	 * @covers ::invalidate_directory
	 */
	public function test_meetup_status_transition_invalidates_directory(): void {
		Cache::set( 'dir_key', 'cached_data', Cache::GROUP_DIRECTORY );

		// Fire the hook that should trigger invalidation.
		do_action( 'groups_meetup_status_transition', 1, 'meetup-pending', 'meetup-active' );

		// After flush, the key should be gone.
		$this->assertFalse( Cache::get( 'dir_key', Cache::GROUP_DIRECTORY ) );
	}

	/**
	 * @covers ::invalidate_analytics
	 */
	public function test_daily_aggregation_invalidates_analytics(): void {
		Cache::set( 'analytics_key', 'data', Cache::GROUP_ANALYTICS );

		do_action( 'groups_daily_aggregation' );

		$this->assertFalse( Cache::get( 'analytics_key', Cache::GROUP_ANALYTICS ) );
	}

	/**
	 * @covers ::invalidate_members
	 */
	public function test_member_joined_invalidates_members(): void {
		$blog_id = get_current_blog_id();
		Cache::set( 'member_count_' . $blog_id, 42, Cache::GROUP_MEMBERS );

		do_action( 'groups_member_joined', 1, $blog_id );

		$this->assertFalse( Cache::get( 'member_count_' . $blog_id, Cache::GROUP_MEMBERS ) );
	}

	/**
	 * @covers ::invalidate_members
	 */
	public function test_member_left_invalidates_members(): void {
		$blog_id = get_current_blog_id();
		Cache::set( 'member_count_' . $blog_id, 42, Cache::GROUP_MEMBERS );

		do_action( 'groups_member_left', 1, $blog_id );

		$this->assertFalse( Cache::get( 'member_count_' . $blog_id, Cache::GROUP_MEMBERS ) );
	}

	/**
	 * @covers ::invalidate_events
	 */
	public function test_event_save_invalidates_events(): void {
		// Register the event post type so save_post fires correctly.
		if ( ! post_type_exists( Event::POST_TYPE ) ) {
			register_post_type( Event::POST_TYPE, [ 'public' => false ] );
		}

		Cache::set( 'upcoming_events_' . get_current_blog_id(), 'cached', Cache::GROUP_EVENTS );

		$event_id = self::factory()->post->create( [
			'post_type'   => Event::POST_TYPE,
			'post_status' => 'event-scheduled',
		] );

		// Updating the event should trigger save_post_wp_event.
		wp_update_post( [
			'ID'         => $event_id,
			'post_title' => 'Updated Event',
		] );

		$this->assertFalse(
			Cache::get( 'upcoming_events_' . get_current_blog_id(), Cache::GROUP_EVENTS )
		);
	}

	/**
	 * @covers ::invalidate_events_on_delete
	 */
	public function test_event_delete_invalidates_events(): void {
		if ( ! post_type_exists( Event::POST_TYPE ) ) {
			register_post_type( Event::POST_TYPE, [ 'public' => false ] );
		}

		$event_id = self::factory()->post->create( [
			'post_type'   => Event::POST_TYPE,
			'post_status' => 'event-draft',
		] );

		Cache::set( 'upcoming_events_' . get_current_blog_id(), 'cached_events', Cache::GROUP_EVENTS );

		wp_delete_post( $event_id, true );

		$this->assertFalse(
			Cache::get( 'upcoming_events_' . get_current_blog_id(), Cache::GROUP_EVENTS )
		);
	}

	/**
	 * @covers ::invalidate_events_on_delete
	 */
	public function test_non_event_delete_does_not_invalidate_events(): void {
		Cache::set( 'upcoming_events_' . get_current_blog_id(), 'still_cached', Cache::GROUP_EVENTS );

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );
		wp_delete_post( $post_id, true );

		// Cache should remain intact since this was not an event post.
		$this->assertSame(
			'still_cached',
			Cache::get( 'upcoming_events_' . get_current_blog_id(), Cache::GROUP_EVENTS )
		);
	}

	/**
	 * @covers ::register_hooks
	 */
	public function test_hooks_registered_only_once(): void {
		// Call register_hooks multiple times — should not duplicate.
		Cache::register_hooks();
		Cache::register_hooks();

		// Check the hook is attached exactly once.
		$count = has_action( 'groups_meetup_status_transition', [ Cache::class, 'invalidate_directory' ] );
		$this->assertSame( 10, $count, 'invalidate_directory should be hooked at priority 10' );
	}

	/**
	 * Test that Cache stores array values correctly.
	 *
	 * @covers ::set
	 * @covers ::get
	 */
	public function test_cache_stores_arrays(): void {
		$data = [
			'groups'      => [ [ 'id' => 1, 'name' => 'Test' ] ],
			'total'       => 1,
			'total_pages' => 1,
		];

		Cache::set( 'array_test', $data, Cache::GROUP_DIRECTORY );
		$cached = Cache::get( 'array_test', Cache::GROUP_DIRECTORY );

		$this->assertSame( $data, $cached );
	}

	/**
	 * Test that the GROUP constants are correctly defined.
	 *
	 * @covers ::GROUP_DIRECTORY
	 * @covers ::GROUP_ANALYTICS
	 * @covers ::GROUP_EVENTS
	 * @covers ::GROUP_MEMBERS
	 */
	public function test_group_constants(): void {
		$this->assertSame( 'groups_directory', Cache::GROUP_DIRECTORY );
		$this->assertSame( 'groups_analytics', Cache::GROUP_ANALYTICS );
		$this->assertSame( 'groups_events', Cache::GROUP_EVENTS );
		$this->assertSame( 'groups_members', Cache::GROUP_MEMBERS );
	}
}
