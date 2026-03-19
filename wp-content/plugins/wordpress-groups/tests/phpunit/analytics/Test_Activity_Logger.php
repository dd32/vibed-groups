<?php
/**
 * Tests for the Activity Logger.
 *
 * @package Groups\Tests
 */

use Groups\Analytics\Activity_Logger;
use Groups\Database\Activity_Log_Table;
use Groups\Database\Schema;
use Groups\Models\Event;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Analytics\Activity_Logger
 */
class Test_Activity_Logger extends WP_UnitTestCase {

	private static bool $hooks_registered = false;

	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		Schema::create_tables();

		// Only register hooks once to avoid duplicate log entries.
		if ( ! self::$hooks_registered ) {
			new Activity_Logger();
			self::$hooks_registered = true;
		}

		// Clear log table between tests.
		global $wpdb;
		$table = $wpdb->base_prefix . 'groups_activity_log';
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	private function create_event(): int {
		return Event::create( [
			'title'     => 'Test Event',
			'content'   => 'A test event.',
			'start_utc' => '2026-06-01 18:00:00',
			'end_utc'   => '2026-06-01 20:00:00',
			'timezone'  => 'UTC',
			'status'    => 'event-scheduled',
		] );
	}

	/**
	 * @covers ::log_member_joined
	 */
	public function test_member_joined_creates_log_entry(): void {
		$user_id = self::factory()->user->create();
		$blog_id = get_current_blog_id();

		// Matches Membership model: do_action( 'groups_member_joined', $user_id, $blog_id )
		do_action( 'groups_member_joined', $user_id, $blog_id );

		$entries = Activity_Log_Table::query( [
			'blog_id' => $blog_id,
			'action'  => 'member_joined',
		] );

		$this->assertNotEmpty( $entries );
		$this->assertEquals( $user_id, $entries[0]->user_id );
	}

	/**
	 * @covers ::log_rsvp_created
	 */
	public function test_rsvp_creates_log_entry(): void {
		$user_id  = self::factory()->user->create();
		$event_id = $this->create_event();

		// Fire RSVP action directly — matches Rsvp model signature.
		do_action( 'groups_rsvp_created', 1, $event_id, $user_id, 'attending' );

		$entries = Activity_Log_Table::query( [
			'blog_id' => get_current_blog_id(),
			'action'  => 'rsvp_created',
		] );

		$this->assertCount( 1, $entries );
		$this->assertEquals( $user_id, $entries[0]->user_id );
		$this->assertEquals( $event_id, $entries[0]->object_id );
	}

	/**
	 * @covers ::log_event_status_changed
	 */
	public function test_event_status_change_creates_log_entry(): void {
		$event_id = $this->create_event();

		// Matches Event model: do_action( 'groups_event_status_transition', $post_id, $old, $new )
		do_action( 'groups_event_status_transition', $event_id, 'event-scheduled', 'event-cancelled' );

		$entries = Activity_Log_Table::query( [
			'blog_id' => get_current_blog_id(),
			'action'  => 'event_status_changed',
		] );

		$this->assertCount( 1, $entries );
		$this->assertEquals( $event_id, $entries[0]->object_id );
	}

	/**
	 * @covers ::log_event_created
	 */
	public function test_first_publish_creates_event_created_log(): void {
		$event_id = $this->create_event();

		$entries = Activity_Log_Table::query( [
			'blog_id' => get_current_blog_id(),
			'action'  => 'event_created',
		] );

		$this->assertCount( 1, $entries );
		$this->assertEquals( $event_id, $entries[0]->object_id );
	}
}
