<?php
/**
 * Tests for the Newcomer Tracker.
 *
 * @package Groups\Tests
 */

use Groups\Analytics\Newcomer_Tracker;
use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Analytics\Newcomer_Tracker
 */
class Test_Newcomer_Tracker extends WP_UnitTestCase {

	/**
	 * Ensure CPTs and the tracker hook are registered.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		// Ensure the tracker is listening (idempotent — the plugin already
		// wires this up, but tests may run in isolation).
		new Newcomer_Tracker();
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
	 * @covers ::check_newcomer
	 */
	public function test_first_rsvp_gets_first_event_flag(): void {
		$user_id  = self::factory()->user->create();
		$event_id = $this->create_event();

		$comment_id = Rsvp::create( $event_id, $user_id );

		$this->assertNotWPError( $comment_id );
		$this->assertEquals( 1, get_comment_meta( $comment_id, '_rsvp_is_first_event', true ) );
	}

	/**
	 * @covers ::check_newcomer
	 */
	public function test_second_rsvp_does_not_get_first_event_flag(): void {
		$user_id   = self::factory()->user->create();
		$event_id  = $this->create_event();
		$event_id2 = $this->create_event();

		// First RSVP — mark attendance confirmed so the user counts as
		// having attended.
		$first_comment = Rsvp::create( $event_id, $user_id );
		$this->assertNotWPError( $first_comment );
		Rsvp::mark_attendance( $event_id, $user_id, true );

		// Second RSVP — should NOT be flagged as first event.
		$second_comment = Rsvp::create( $event_id2, $user_id );
		$this->assertNotWPError( $second_comment );

		$flag = get_comment_meta( $second_comment, '_rsvp_is_first_event', true );
		$this->assertEmpty( $flag, 'Second RSVP should not have _rsvp_is_first_event set.' );
	}

	/**
	 * @covers ::check_newcomer
	 */
	public function test_newcomer_detected_action_fires(): void {
		$user_id  = self::factory()->user->create();
		$event_id = $this->create_event();
		$fired    = false;

		add_action(
			'groups_newcomer_detected',
			function ( $u_id, $e_id, $c_id ) use ( $user_id, $event_id, &$fired ) {
				$fired = true;
				$this->assertSame( $user_id, $u_id );
				$this->assertSame( $event_id, $e_id );
				$this->assertIsInt( $c_id );
			},
			10,
			3
		);

		Rsvp::create( $event_id, $user_id );

		$this->assertTrue( $fired, 'The groups_newcomer_detected action should have fired for a newcomer.' );
	}

	/**
	 * @covers ::has_attended_before
	 */
	public function test_has_attended_before_caches_result(): void {
		$user_id  = self::factory()->user->create();
		$event_id = $this->create_event();

		// No attendance yet.
		$this->assertFalse( Newcomer_Tracker::has_attended_before( $user_id ) );

		// Create RSVP and confirm attendance.
		$comment_id = Rsvp::create( $event_id, $user_id );
		Rsvp::mark_attendance( $event_id, $user_id, true );

		// Clear any in-memory state by re-checking.
		$this->assertTrue( Newcomer_Tracker::has_attended_before( $user_id ) );

		// Verify the cache meta was set.
		$this->assertSame( '1', get_user_meta( $user_id, Newcomer_Tracker::HAS_ATTENDED_META, true ) );
	}
}
