<?php
/**
 * Tests for RSVP notification emails.
 *
 * @package Groups\Tests
 */

use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Notifications\Rsvp_Notifications;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Notifications\Rsvp_Notifications
 */
class Test_Rsvp_Notifications extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		// Ensure the Rsvp_Notifications hooks are registered.
		new Rsvp_Notifications();
	}

	/**
	 * Helper to create a scheduled event post.
	 *
	 * @param array $overrides Optional overrides for event args.
	 * @return int Post ID.
	 */
	private function create_event( array $overrides = [] ): int {
		$args = array_merge(
			[
				'title'     => 'WordPress Meetup',
				'content'   => 'A community meetup.',
				'start_utc' => gmdate( 'Y-m-d H:i:s', time() + ( 2 * DAY_IN_SECONDS ) ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', time() + ( 2 * DAY_IN_SECONDS ) + ( 2 * HOUR_IN_SECONDS ) ),
				'timezone'  => 'America/New_York',
				'status'    => 'event-scheduled',
			],
			$overrides
		);

		return Event::create( $args );
	}

	/**
	 * @covers ::on_rsvp_created
	 */
	public function test_attending_rsvp_sends_confirmation_email(): void {
		$event_id = $this->create_event();
		$user_id  = self::factory()->user->create( [ 'user_email' => 'attendee@example.org' ] );

		$sent_emails = [];
		add_action(
			'groups_before_email_send',
			function ( $to, $subject, $template ) use ( &$sent_emails ) {
				$sent_emails[] = [
					'to'       => $to,
					'subject'  => $subject,
					'template' => $template,
				];
			},
			10,
			3
		);

		Rsvp::create( $event_id, $user_id );

		$this->assertCount( 1, $sent_emails, 'Should send exactly one email on RSVP creation.' );
		$this->assertSame( 'attendee@example.org', $sent_emails[0]['to'] );
		$this->assertSame( 'rsvp-confirmation', $sent_emails[0]['template'] );
		$this->assertStringContainsString( 'RSVP Confirmed', $sent_emails[0]['subject'] );
		$this->assertStringContainsString( 'WordPress Meetup', $sent_emails[0]['subject'] );
	}

	/**
	 * @covers ::on_rsvp_created
	 */
	public function test_waitlisted_rsvp_sends_waitlist_email(): void {
		$event_id = $this->create_event( [
			'attendee_limit'   => 1,
			'waitlist_enabled' => true,
		] );

		// First user fills the only spot.
		$user1 = self::factory()->user->create( [ 'user_email' => 'first@example.org' ] );
		Rsvp::create( $event_id, $user1 );

		$sent_emails = [];
		add_action(
			'groups_before_email_send',
			function ( $to, $subject, $template ) use ( &$sent_emails ) {
				$sent_emails[] = [
					'to'       => $to,
					'subject'  => $subject,
					'template' => $template,
				];
			},
			10,
			3
		);

		// Second user should be waitlisted.
		$user2 = self::factory()->user->create( [ 'user_email' => 'waitlisted@example.org' ] );
		Rsvp::create( $event_id, $user2 );

		$this->assertCount( 1, $sent_emails, 'Should send exactly one email for the waitlisted RSVP.' );
		$this->assertSame( 'waitlisted@example.org', $sent_emails[0]['to'] );
		$this->assertSame( 'rsvp-confirmation', $sent_emails[0]['template'] );
		$this->assertStringContainsString( 'Waitlisted', $sent_emails[0]['subject'] );
	}

	/**
	 * @covers ::on_rsvp_promoted
	 */
	public function test_promotion_sends_promotion_email(): void {
		$event_id = $this->create_event( [
			'attendee_limit'   => 1,
			'waitlist_enabled' => true,
		] );

		// First user takes the spot.
		$user1 = self::factory()->user->create( [ 'user_email' => 'first@example.org' ] );
		Rsvp::create( $event_id, $user1 );

		// Second user is waitlisted.
		$user2 = self::factory()->user->create( [ 'user_email' => 'promoted@example.org' ] );
		Rsvp::create( $event_id, $user2 );

		// Now track emails and cancel the first RSVP to trigger promotion.
		$sent_emails = [];
		add_action(
			'groups_before_email_send',
			function ( $to, $subject, $template ) use ( &$sent_emails ) {
				$sent_emails[] = [
					'to'       => $to,
					'subject'  => $subject,
					'template' => $template,
				];
			},
			10,
			3
		);

		$rsvp1 = Rsvp::get_user_rsvp( $event_id, $user1 );
		Rsvp::cancel( (int) $rsvp1->comment_ID );

		$this->assertCount( 1, $sent_emails, 'Should send exactly one promotion email.' );
		$this->assertSame( 'promoted@example.org', $sent_emails[0]['to'] );
		$this->assertSame( 'rsvp-promotion', $sent_emails[0]['template'] );
		$this->assertStringContainsString( "You're In!", $sent_emails[0]['subject'] );
	}

	/**
	 * @covers ::on_rsvp_created
	 */
	public function test_confirmation_not_sent_when_filtered_out(): void {
		$event_id = $this->create_event();
		$user_id  = self::factory()->user->create( [ 'user_email' => 'nomail@example.org' ] );

		add_filter( 'groups_send_rsvp_confirmation', '__return_false' );

		$sent_emails = 0;
		add_action(
			'groups_before_email_send',
			function () use ( &$sent_emails ) {
				$sent_emails++;
			}
		);

		Rsvp::create( $event_id, $user_id );

		$this->assertSame( 0, $sent_emails, 'No email should be sent when filtered out.' );

		remove_filter( 'groups_send_rsvp_confirmation', '__return_false' );
	}

	/**
	 * @covers ::on_rsvp_promoted
	 */
	public function test_promotion_not_sent_when_filtered_out(): void {
		$event_id = $this->create_event( [
			'attendee_limit'   => 1,
			'waitlist_enabled' => true,
		] );

		$user1 = self::factory()->user->create( [ 'user_email' => 'first@example.org' ] );
		Rsvp::create( $event_id, $user1 );

		$user2 = self::factory()->user->create( [ 'user_email' => 'promoted@example.org' ] );
		Rsvp::create( $event_id, $user2 );

		add_filter( 'groups_send_rsvp_promotion', '__return_false' );

		$sent_emails = 0;
		add_action(
			'groups_before_email_send',
			function () use ( &$sent_emails ) {
				$sent_emails++;
			}
		);

		$rsvp1 = Rsvp::get_user_rsvp( $event_id, $user1 );
		Rsvp::cancel( (int) $rsvp1->comment_ID );

		$this->assertSame( 0, $sent_emails, 'No promotion email should be sent when filtered out.' );

		remove_filter( 'groups_send_rsvp_promotion', '__return_false' );
	}
}
