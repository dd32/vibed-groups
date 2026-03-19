<?php
/**
 * Tests for the RSVP model.
 *
 * @package Groups\Tests
 */

namespace Groups\Tests\Models;

use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Post_Types\Event as Event_Post_Type;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Groups\Models\Rsvp
 */
class Test_Rsvp extends WP_UnitTestCase {

	/**
	 * Ensure the event CPT and statuses are registered before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();
	}

	/**
	 * Helper to create an event and return its post ID.
	 *
	 * @param array $overrides Optional event arg overrides.
	 * @return int Event post ID.
	 */
	private function create_event( array $overrides = [] ): int {
		$args = array_merge(
			[
				'title'     => 'Test Event',
				'content'   => 'A test event.',
				'start_utc' => '2026-04-15 18:00:00',
				'end_utc'   => '2026-04-15 20:00:00',
				'timezone'  => 'America/New_York',
			],
			$overrides
		);

		return Event::create( $args );
	}

	/**
	 * Helper to create a test user and return their ID.
	 *
	 * @param string $login Optional username.
	 * @return int User ID.
	 */
	private function create_user( string $login = '' ): int {
		if ( '' === $login ) {
			$login = 'testuser_' . wp_generate_password( 6, false );
		}

		return self::factory()->user->create( [
			'user_login' => $login,
			'user_email' => $login . '@example.com',
			'role'       => 'subscriber',
		] );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_returns_comment_id(): void {
		$event_id = $this->create_event();
		$user_id  = $this->create_user();

		$comment_id = Rsvp::create( $event_id, $user_id );

		$this->assertIsInt( $comment_id );
		$this->assertGreaterThan( 0, $comment_id );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_sets_attending_status_when_under_limit(): void {
		$event_id = $this->create_event( [ 'attendee_limit' => 10 ] );
		$user_id  = $this->create_user();

		$comment_id = Rsvp::create( $event_id, $user_id );

		$this->assertSame( 'attending', get_comment_meta( $comment_id, '_rsvp_status', true ) );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_auto_waitlists_when_event_full(): void {
		$event_id = $this->create_event( [ 'attendee_limit' => 2 ] );

		// Fill the event with 2 attendees.
		$user1 = $this->create_user( 'user_one' );
		$user2 = $this->create_user( 'user_two' );
		Rsvp::create( $event_id, $user1 );
		Rsvp::create( $event_id, $user2 );

		// Third person should be waitlisted.
		$user3      = $this->create_user( 'user_three' );
		$comment_id = Rsvp::create( $event_id, $user3 );

		$this->assertSame( 'waitlisted', get_comment_meta( $comment_id, '_rsvp_status', true ) );
	}

	/**
	 * @covers ::create
	 * @covers ::cancel
	 * @covers ::promote_next
	 */
	public function test_cancel_promotes_waitlisted_person(): void {
		$event_id = $this->create_event( [ 'attendee_limit' => 1 ] );

		$user1 = $this->create_user( 'user_one' );
		$user2 = $this->create_user( 'user_two' );

		$rsvp1 = Rsvp::create( $event_id, $user1 );
		$rsvp2 = Rsvp::create( $event_id, $user2 );

		// User 2 should be waitlisted.
		$this->assertSame( 'waitlisted', get_comment_meta( $rsvp2, '_rsvp_status', true ) );

		// Cancel user 1's RSVP.
		Rsvp::cancel( $rsvp1 );

		// User 2 should now be attending.
		$this->assertSame( 'attending', get_comment_meta( $rsvp2, '_rsvp_status', true ) );
	}

	/**
	 * @covers ::create
	 * @covers ::get_count
	 */
	public function test_guest_count_affects_capacity(): void {
		$event_id = $this->create_event( [ 'attendee_limit' => 3 ] );

		// User with 1 guest takes 2 spots.
		$user1 = $this->create_user( 'user_one' );
		Rsvp::create( $event_id, $user1, [ 'guest_count' => 1 ] );

		$this->assertSame( 2, Rsvp::get_count( $event_id, 'attending' ) );

		// Next user with 0 guests takes 1 spot — fills the event to 3.
		$user2 = $this->create_user( 'user_two' );
		Rsvp::create( $event_id, $user2 );

		$this->assertSame( 3, Rsvp::get_count( $event_id, 'attending' ) );

		// Third user should be waitlisted.
		$user3      = $this->create_user( 'user_three' );
		$comment_id = Rsvp::create( $event_id, $user3 );

		$this->assertSame( 'waitlisted', get_comment_meta( $comment_id, '_rsvp_status', true ) );
	}

	/**
	 * @covers ::mark_attendance
	 */
	public function test_mark_attendance_attended(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		$result = Rsvp::mark_attendance( $comment_id, true );

		$this->assertTrue( $result );
		$this->assertSame( 'attending', get_comment_meta( $comment_id, '_rsvp_status', true ) );
		$this->assertEquals( true, get_comment_meta( $comment_id, '_rsvp_attendance_confirmed', true ) );
	}

	/**
	 * @covers ::mark_attendance
	 */
	public function test_mark_attendance_no_show(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		Rsvp::mark_attendance( $comment_id, false );

		$this->assertSame( 'no_show', get_comment_meta( $comment_id, '_rsvp_status', true ) );
		$this->assertEquals( true, get_comment_meta( $comment_id, '_rsvp_attendance_confirmed', true ) );
	}

	/**
	 * @covers ::get_for_event
	 */
	public function test_get_for_event_returns_all_rsvps(): void {
		$event_id = $this->create_event();

		$user1 = $this->create_user( 'user_one' );
		$user2 = $this->create_user( 'user_two' );
		Rsvp::create( $event_id, $user1 );
		Rsvp::create( $event_id, $user2 );

		$rsvps = Rsvp::get_for_event( $event_id );

		$this->assertCount( 2, $rsvps );
	}

	/**
	 * @covers ::get_for_event
	 */
	public function test_get_for_event_filters_by_status(): void {
		$event_id = $this->create_event( [ 'attendee_limit' => 1 ] );

		$user1 = $this->create_user( 'user_one' );
		$user2 = $this->create_user( 'user_two' );
		Rsvp::create( $event_id, $user1 );
		Rsvp::create( $event_id, $user2 );

		$attending  = Rsvp::get_for_event( $event_id, 'attending' );
		$waitlisted = Rsvp::get_for_event( $event_id, 'waitlisted' );

		$this->assertCount( 1, $attending );
		$this->assertCount( 1, $waitlisted );
	}

	/**
	 * @covers ::get_user_rsvp
	 */
	public function test_get_user_rsvp_returns_comment(): void {
		$event_id = $this->create_event();
		$user_id  = $this->create_user();

		$comment_id = Rsvp::create( $event_id, $user_id );

		$rsvp = Rsvp::get_user_rsvp( $event_id, $user_id );

		$this->assertInstanceOf( \WP_Comment::class, $rsvp );
		$this->assertEquals( $comment_id, $rsvp->comment_ID );
	}

	/**
	 * @covers ::get_user_rsvp
	 */
	public function test_get_user_rsvp_returns_null_when_not_found(): void {
		$event_id = $this->create_event();

		$rsvp = Rsvp::get_user_rsvp( $event_id, 999999 );

		$this->assertNull( $rsvp );
	}

	/**
	 * @covers ::create
	 */
	public function test_cannot_rsvp_twice_same_user_same_event(): void {
		$event_id = $this->create_event();
		$user_id  = $this->create_user();

		Rsvp::create( $event_id, $user_id );
		$result = Rsvp::create( $event_id, $user_id );

		$this->assertWPError( $result );
		$this->assertSame( 'duplicate_rsvp', $result->get_error_code() );
	}

	/**
	 * @covers ::promote_next
	 */
	public function test_promote_next_fires_action_hook(): void {
		$event_id = $this->create_event( [ 'attendee_limit' => 1 ] );

		$user1 = $this->create_user( 'user_one' );
		$user2 = $this->create_user( 'user_two' );

		$rsvp1 = Rsvp::create( $event_id, $user1 );
		$rsvp2 = Rsvp::create( $event_id, $user2 );

		$fired = false;

		add_action(
			'groups_rsvp_promoted',
			function ( $comment_id, $fired_event_id, $fired_user_id ) use ( $rsvp2, $event_id, $user2, &$fired ) {
				$fired = true;
				$this->assertEquals( $rsvp2, $comment_id );
				$this->assertSame( $event_id, $fired_event_id );
				$this->assertSame( $user2, $fired_user_id );
			},
			10,
			3
		);

		// Cancel user 1 to trigger promotion.
		Rsvp::cancel( $rsvp1 );

		$this->assertTrue( $fired, 'The groups_rsvp_promoted action should have fired.' );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_fires_action_hook(): void {
		$event_id = $this->create_event();
		$user_id  = $this->create_user();

		$fired = false;

		add_action(
			'groups_rsvp_created',
			function ( $comment_id, $fired_event_id, $fired_user_id, $status ) use ( $event_id, $user_id, &$fired ) {
				$fired = true;
				$this->assertIsInt( $comment_id );
				$this->assertSame( $event_id, $fired_event_id );
				$this->assertSame( $user_id, $fired_user_id );
				$this->assertSame( 'attending', $status );
			},
			10,
			4
		);

		Rsvp::create( $event_id, $user_id );

		$this->assertTrue( $fired, 'The groups_rsvp_created action should have fired.' );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_with_invalid_event_returns_error(): void {
		$user_id = $this->create_user();

		$result = Rsvp::create( 999999, $user_id );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_event', $result->get_error_code() );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_with_invalid_user_returns_error(): void {
		$event_id = $this->create_event();

		$result = Rsvp::create( $event_id, 999999 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_rsvp_status(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		$result = Rsvp::update( $comment_id, [ 'status' => 'not_attending' ] );

		$this->assertTrue( $result );
		$this->assertSame( 'not_attending', get_comment_meta( $comment_id, '_rsvp_status', true ) );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_rsvp_guest_count(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		Rsvp::update( $comment_id, [ 'guest_count' => 3 ] );

		$this->assertEquals( 3, get_comment_meta( $comment_id, '_rsvp_guest_count', true ) );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_invalid_rsvp_returns_error(): void {
		$result = Rsvp::update( 999999, [ 'status' => 'attending' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_rsvp', $result->get_error_code() );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_with_invalid_status_returns_error(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		$result = Rsvp::update( $comment_id, [ 'status' => 'invalid_status' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_status', $result->get_error_code() );
	}

	/**
	 * @covers ::cancel
	 */
	public function test_cancel_sets_not_attending(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		Rsvp::cancel( $comment_id );

		$this->assertSame( 'not_attending', get_comment_meta( $comment_id, '_rsvp_status', true ) );
	}

	/**
	 * @covers ::cancel
	 */
	public function test_cancel_invalid_rsvp_returns_error(): void {
		$result = Rsvp::cancel( 999999 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_rsvp', $result->get_error_code() );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_uses_comment_type_rsvp(): void {
		$event_id   = $this->create_event();
		$user_id    = $this->create_user();
		$comment_id = Rsvp::create( $event_id, $user_id );

		$comment = get_comment( $comment_id );

		$this->assertSame( 'rsvp', $comment->comment_type );
		$this->assertEquals( 1, $comment->comment_approved );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_rsvp_with_no_limit_always_attends(): void {
		$event_id = $this->create_event(); // No attendee_limit set.

		// Create many RSVPs — all should be attending.
		for ( $i = 0; $i < 5; $i++ ) {
			$user_id    = $this->create_user();
			$comment_id = Rsvp::create( $event_id, $user_id );

			$this->assertSame( 'attending', get_comment_meta( $comment_id, '_rsvp_status', true ) );
		}
	}

	/**
	 * @covers ::promote_next
	 */
	public function test_promote_next_returns_false_when_no_waitlisted(): void {
		$event_id = $this->create_event();

		$result = Rsvp::promote_next( $event_id );

		$this->assertFalse( $result );
	}
}
