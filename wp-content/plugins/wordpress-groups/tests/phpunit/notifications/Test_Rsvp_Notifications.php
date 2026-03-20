<?php
/**
 * Tests for RSVP confirmation and promotion email notifications.
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
	 * Rsvp_Notifications instance under test.
	 *
	 * @var Rsvp_Notifications
	 */
	private Rsvp_Notifications $notifications;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		$this->notifications = new Rsvp_Notifications();
	}

	/**
	 * Helper to create a scheduled event post.
	 *
	 * @param array $overrides Optional overrides for event args.
	 * @return int Post ID.
	 */
	private function create_scheduled_event( array $overrides = [] ): int {
		$args = array_merge(
			[
				'title'     => 'Test Meetup',
				'content'   => 'A test event.',
				'start_utc' => gmdate( 'Y-m-d H:i:s', time() + ( 2 * DAY_IN_SECONDS ) ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', time() + ( 2 * DAY_IN_SECONDS ) + ( 2 * HOUR_IN_SECONDS ) ),
				'timezone'  => 'UTC',
				'status'    => 'event-scheduled',
			],
			$overrides
		);

		return Event::create( $args );
	}

	/**
	 * @covers ::on_rsvp_created
	 */
	public function test_confirmation_email_sent_on_rsvp(): void {
		$event_id = $this->create_scheduled_event();
		$user_id  = self::factory()->user->create( [ 'user_email' => 'rsvp-user@example.org' ] );

		$recipients = [];
		$templates  = [];
		add_action(
			'groups_before_email_send',
			function ( $to, $subject, $template ) use ( &$recipients, &$templates ) {
				$recipients[] = $to;
				$templates[]  = $template;
			},
			10,
			3
		);

		Rsvp::create( $event_id, $user_id );

		$this->assertContains( 'rsvp-user@example.org', $recipients, 'Confirmation email should be sent to the RSVP user.' );
		$this->assertContains( 'rsvp-confirmation', $templates, 'Should use the rsvp-confirmation template.' );
	}

	/**
	 * @covers ::on_rsvp_created
	 */
	public function test_confirmation_email_not_sent_when_filtered_out(): void {
		$event_id = $this->create_scheduled_event();
		$user_id  = self::factory()->user->create( [ 'user_email' => 'filtered@example.org' ] );

		add_filter( 'groups_send_rsvp_confirmation', '__return_false' );

		$emails_sent = 0;
		add_action(
			'groups_before_email_send',
			function () use ( &$emails_sent ) {
				$emails_sent++;
			}
		);

		Rsvp::create( $event_id, $user_id );

		$this->assertSame( 0, $emails_sent, 'No confirmation email should be sent when filtered out.' );

		remove_filter( 'groups_send_rsvp_confirmation', '__return_false' );
	}

	/**
	 * @covers ::on_rsvp_promoted
	 */
	public function test_promotion_email_sent_on_waitlist_promotion(): void {
		$event_id = $this->create_scheduled_event( [ 'attendee_limit' => 1 ] );

		// Enable waitlist.
		update_post_meta( $event_id, '_event_waitlist_enabled', true );

		$user1 = self::factory()->user->create( [ 'user_email' => 'first@example.org' ] );
		$user2 = self::factory()->user->create( [ 'user_email' => 'waitlisted@example.org' ] );

		// First user takes the only spot.
		$rsvp1 = Rsvp::create( $event_id, $user1 );

		// Second user should be waitlisted.
		Rsvp::create( $event_id, $user2 );

		// Track emails from this point forward.
		$recipients = [];
		$templates  = [];
		add_action(
			'groups_before_email_send',
			function ( $to, $subject, $template ) use ( &$recipients, &$templates ) {
				$recipients[] = $to;
				$templates[]  = $template;
			},
			10,
			3
		);

		// Cancel the first user's RSVP — should trigger promotion.
		Rsvp::cancel( $rsvp1 );

		$this->assertContains( 'waitlisted@example.org', $recipients, 'Promotion email should be sent to the promoted user.' );
		$this->assertContains( 'rsvp-promotion', $templates, 'Should use the rsvp-promotion template.' );
	}

	/**
	 * @covers ::on_rsvp_promoted
	 */
	public function test_promotion_email_not_sent_when_filtered_out(): void {
		$event_id = $this->create_scheduled_event( [ 'attendee_limit' => 1 ] );

		// Enable waitlist.
		update_post_meta( $event_id, '_event_waitlist_enabled', true );

		$user1 = self::factory()->user->create( [ 'user_email' => 'first2@example.org' ] );
		$user2 = self::factory()->user->create( [ 'user_email' => 'waitlisted2@example.org' ] );

		$rsvp1 = Rsvp::create( $event_id, $user1 );
		Rsvp::create( $event_id, $user2 );

		add_filter( 'groups_send_rsvp_promotion', '__return_false' );

		$emails_sent = 0;
		add_action(
			'groups_before_email_send',
			function () use ( &$emails_sent ) {
				$emails_sent++;
			}
		);

		Rsvp::cancel( $rsvp1 );

		$this->assertSame( 0, $emails_sent, 'No promotion email should be sent when filtered out.' );

		remove_filter( 'groups_send_rsvp_promotion', '__return_false' );
	}

	/**
	 * @covers ::on_rsvp_created
	 */
	public function test_waitlisted_rsvp_gets_waitlist_confirmation(): void {
		$event_id = $this->create_scheduled_event( [ 'attendee_limit' => 1 ] );

		// Enable waitlist.
		update_post_meta( $event_id, '_event_waitlist_enabled', true );

		$user1 = self::factory()->user->create( [ 'user_email' => 'attendee@example.org' ] );
		$user2 = self::factory()->user->create( [ 'user_email' => 'waiter@example.org' ] );

		// First user takes the spot.
		Rsvp::create( $event_id, $user1 );

		// Track emails from here.
		$subjects = [];
		add_action(
			'groups_before_email_send',
			function ( $to, $subject ) use ( &$subjects ) {
				$subjects[ $to ] = $subject;
			},
			10,
			2
		);

		// Second user is waitlisted.
		Rsvp::create( $event_id, $user2 );

		$this->assertArrayHasKey( 'waiter@example.org', $subjects, 'Waitlisted user should receive a confirmation email.' );
		$this->assertStringContainsString( 'Waitlisted', $subjects['waiter@example.org'], 'Subject should indicate waitlisted status.' );
	}
}
