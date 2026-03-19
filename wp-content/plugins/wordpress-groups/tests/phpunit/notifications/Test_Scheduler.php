<?php
/**
 * Tests for the event reminder Scheduler.
 *
 * @package Groups\Tests
 */

use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Notifications\Scheduler;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Notifications\Scheduler
 */
class Test_Scheduler extends WP_UnitTestCase {

	/**
	 * Scheduler instance under test.
	 *
	 * @var Scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		$this->scheduler = new Scheduler();
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
	 * @covers ::on_status_transition
	 * @covers ::schedule_reminders
	 */
	public function test_scheduling_creates_cron_events_at_correct_times(): void {
		$start_time = time() + ( 2 * DAY_IN_SECONDS );
		$event_id   = $this->create_scheduled_event(
			[
				'start_utc' => gmdate( 'Y-m-d H:i:s', $start_time ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', $start_time + ( 2 * HOUR_IN_SECONDS ) ),
				'status'    => 'event-draft',
			]
		);

		// Transition to scheduled — should create cron events.
		Event::transition_status( $event_id, 'event-scheduled' );

		$expected_24h = $start_time - DAY_IN_SECONDS;
		$expected_1h  = $start_time - HOUR_IN_SECONDS;

		$scheduled_24h = wp_next_scheduled( Scheduler::HOOK_24H, [ $event_id ] );
		$scheduled_1h  = wp_next_scheduled( Scheduler::HOOK_1H, [ $event_id ] );

		$this->assertSame( $expected_24h, $scheduled_24h, '24h reminder should be scheduled at start_time - 24 hours.' );
		$this->assertSame( $expected_1h, $scheduled_1h, '1h reminder should be scheduled at start_time - 1 hour.' );
	}

	/**
	 * @covers ::on_status_transition
	 * @covers ::unschedule_reminders
	 */
	public function test_cancellation_removes_cron_events(): void {
		$start_time = time() + ( 2 * DAY_IN_SECONDS );
		$event_id   = $this->create_scheduled_event(
			[
				'start_utc' => gmdate( 'Y-m-d H:i:s', $start_time ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', $start_time + ( 2 * HOUR_IN_SECONDS ) ),
				'status'    => 'event-draft',
			]
		);

		// Schedule reminders.
		Event::transition_status( $event_id, 'event-scheduled' );

		// Verify they exist.
		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_24H, [ $event_id ] ) );
		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_1H, [ $event_id ] ) );

		// Cancel the event.
		Event::transition_status( $event_id, 'event-cancelled' );

		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_24H, [ $event_id ] ), '24h reminder should be unscheduled after cancellation.' );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_1H, [ $event_id ] ), '1h reminder should be unscheduled after cancellation.' );
	}

	/**
	 * @covers ::send_reminder
	 */
	public function test_reminder_does_not_send_for_cancelled_events(): void {
		$event_id = $this->create_scheduled_event();

		// Create an attending RSVP.
		$user_id = self::factory()->user->create( [ 'user_email' => 'attendee@example.org' ] );
		Rsvp::create( $event_id, $user_id );

		// Cancel the event.
		Event::transition_status( $event_id, 'event-cancelled' );

		// Track emails sent.
		$emails_sent = 0;
		add_action(
			'groups_before_email_send',
			function () use ( &$emails_sent ) {
				$emails_sent++;
			}
		);

		// Fire the reminder handler.
		$this->scheduler->send_reminder( $event_id );

		$this->assertSame( 0, $emails_sent, 'No reminder emails should be sent for cancelled events.' );
	}

	/**
	 * @covers ::send_reminder
	 */
	public function test_reminder_sends_to_attending_rsvps(): void {
		$event_id = $this->create_scheduled_event();

		// Create attending RSVPs.
		$user1 = self::factory()->user->create( [ 'user_email' => 'user1@example.org' ] );
		$user2 = self::factory()->user->create( [ 'user_email' => 'user2@example.org' ] );
		Rsvp::create( $event_id, $user1 );
		Rsvp::create( $event_id, $user2 );

		// Track emails sent.
		$recipients = [];
		add_action(
			'groups_before_email_send',
			function ( $to ) use ( &$recipients ) {
				$recipients[] = $to;
			}
		);

		$this->scheduler->send_reminder( $event_id );

		$this->assertCount( 2, $recipients, 'Should send reminders to all attending users.' );
		$this->assertContains( 'user1@example.org', $recipients );
		$this->assertContains( 'user2@example.org', $recipients );
	}

	/**
	 * @covers ::schedule_reminders
	 */
	public function test_reschedule_clears_old_reminders(): void {
		$start_time = time() + ( 2 * DAY_IN_SECONDS );
		$event_id   = $this->create_scheduled_event(
			[
				'start_utc' => gmdate( 'Y-m-d H:i:s', $start_time ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', $start_time + ( 2 * HOUR_IN_SECONDS ) ),
				'status'    => 'event-draft',
			]
		);

		Event::transition_status( $event_id, 'event-scheduled' );

		$old_24h = wp_next_scheduled( Scheduler::HOOK_24H, [ $event_id ] );

		// Update start time and reschedule.
		$new_start = time() + ( 3 * DAY_IN_SECONDS );
		update_post_meta( $event_id, '_event_start_utc', gmdate( 'Y-m-d H:i:s', $new_start ) );
		$this->scheduler->schedule_reminders( $event_id );

		$new_24h = wp_next_scheduled( Scheduler::HOOK_24H, [ $event_id ] );

		$this->assertNotSame( $old_24h, $new_24h, 'Rescheduling should update the cron timestamp.' );
		$this->assertSame( $new_start - DAY_IN_SECONDS, $new_24h );
	}

	/**
	 * @covers ::schedule_reminders
	 */
	public function test_past_reminder_times_are_not_scheduled(): void {
		// Event starting in 30 minutes — 24h reminder is in the past, 1h reminder is in the past.
		$start_time = time() + ( 30 * MINUTE_IN_SECONDS );
		$event_id   = $this->create_scheduled_event(
			[
				'start_utc' => gmdate( 'Y-m-d H:i:s', $start_time ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', $start_time + HOUR_IN_SECONDS ),
			]
		);

		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_24H, [ $event_id ] ), '24h reminder should not be scheduled for events starting in 30 minutes.' );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_1H, [ $event_id ] ), '1h reminder should not be scheduled for events starting in 30 minutes.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_non_event_post_type_is_ignored(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		// Should not schedule anything for a regular post.
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_24H, [ $post_id ] ) );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_1H, [ $post_id ] ) );
	}
}
