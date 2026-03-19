<?php
/**
 * Tests for the Activity Logger.
 *
 * @package Groups\Tests
 */

use Groups\Analytics\Activity_Logger;
use Groups\Database\Activity_Log_Table;
use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Analytics\Activity_Logger
 */
class Test_Activity_Logger extends WP_UnitTestCase {

	/**
	 * Ensure CPTs and the logger hooks are registered.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		// Ensure the logger is listening.
		new Activity_Logger();
	}

	/**
	 * Helper: create an event and return its post ID.
	 */
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

		do_action( 'groups_member_joined', $blog_id, $user_id, 'member' );

		$entries = Activity_Log_Table::query( [
			'blog_id' => $blog_id,
			'action'  => 'member_joined',
			'user_id' => $user_id,
		] );

		$this->assertCount( 1, $entries );
		$this->assertEquals( $user_id, $entries[0]->user_id );
		$this->assertEquals( 'member', $entries[0]->meta['role'] );
	}

	/**
	 * @covers ::log_rsvp_created
	 */
	public function test_rsvp_creates_log_entry(): void {
		$user_id  = self::factory()->user->create();
		$event_id = $this->create_event();

		Rsvp::create( $event_id, $user_id );

		$entries = Activity_Log_Table::query( [
			'blog_id' => get_current_blog_id(),
			'action'  => 'rsvp_created',
		] );

		$this->assertCount( 1, $entries );
		$this->assertEquals( $user_id, $entries[0]->user_id );
		$this->assertEquals( $event_id, $entries[0]->object_id );
		$this->assertEquals( 'attending', $entries[0]->meta['status'] );
	}

	/**
	 * @covers ::log_event_status_changed
	 */
	public function test_event_status_change_creates_log_entry(): void {
		$user_id  = self::factory()->user->create();
		$event_id = $this->create_event();
		$blog_id  = get_current_blog_id();

		do_action( 'groups_event_status_transition', $event_id, 'event-scheduled', 'event-cancelled', $blog_id );

		$entries = Activity_Log_Table::query( [
			'blog_id' => $blog_id,
			'action'  => 'event_status_changed',
		] );

		$this->assertCount( 1, $entries );
		$this->assertEquals( $event_id, $entries[0]->object_id );
		$this->assertEquals( 'event-scheduled', $entries[0]->meta['old_status'] );
		$this->assertEquals( 'event-cancelled', $entries[0]->meta['new_status'] );
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
		$this->assertEquals( 'event-scheduled', $entries[0]->meta['status'] );
	}
}
