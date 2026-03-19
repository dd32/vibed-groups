<?php
/**
 * Tests for the Recurrence_Generator model.
 *
 * @package Groups\Tests
 */

use Groups\Models\Event;
use Groups\Models\Recurrence;
use Groups\Models\Recurrence_Generator;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Models\Recurrence_Generator
 */
class Test_Recurrence_Generator extends WP_UnitTestCase {

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
	 * Helper to create a recurring template event.
	 *
	 * @param array $overrides Optional overrides for event args.
	 * @param array $rule      Optional overrides for recurrence rule.
	 * @return int The template event post ID.
	 */
	private function create_template_event( array $overrides = [], array $rule = [] ): int {
		// Default: an event starting next Monday at 18:00 UTC, 2 hours long.
		$next_monday = new DateTime( 'next monday', new DateTimeZone( 'UTC' ) );

		$defaults = [
			'title'            => 'Weekly WordPress Meetup',
			'content'          => 'Come join us every week!',
			'status'           => 'event-scheduled',
			'start_utc'        => $next_monday->format( 'Y-m-d' ) . ' 18:00:00',
			'end_utc'          => $next_monday->format( 'Y-m-d' ) . ' 20:00:00',
			'timezone'         => 'America/Chicago',
			'venue_id'         => 42,
			'online_link'      => 'https://meet.example.com/weekly',
			'attendee_limit'   => 30,
			'waitlist_enabled' => true,
		];

		$args     = array_merge( $defaults, $overrides );
		$event_id = Event::create( $args );

		$this->assertIsInt( $event_id );

		$default_rule = array_merge(
			[
				'frequency'   => 'weekly',
				'day_of_week' => (int) $next_monday->format( 'w' ),
			],
			$rule
		);

		$result = Recurrence::save_rule( $event_id, $default_rule );
		$this->assertTrue( $result );

		return $event_id;
	}

	/**
	 * @covers ::generate_for_event
	 */
	public function test_generates_future_instances(): void {
		// Create a template event starting today so occurrences fall within horizon.
		$today    = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$event_id = $this->create_template_event(
			[
				'start_utc' => $today->format( 'Y-m-d' ) . ' 18:00:00',
				'end_utc'   => $today->format( 'Y-m-d' ) . ' 20:00:00',
			],
			[
				'frequency'   => 'weekly',
				'day_of_week' => (int) $today->format( 'w' ),
			]
		);

		$created = Recurrence_Generator::generate_for_event( $event_id );

		// Should create instances for upcoming weeks within the 4-week horizon.
		// The first occurrence (today) counts as "not in the past" if it's today,
		// but the template itself already exists. We expect at least 1 future instance.
		$this->assertNotEmpty( $created, 'Should create at least one future instance.' );

		// Each created instance should be a valid post.
		foreach ( $created as $instance_id ) {
			$post = get_post( $instance_id );
			$this->assertSame( 'event', $post->post_type );
			$this->assertSame( 'event-scheduled', $post->post_status );
		}
	}

	/**
	 * @covers ::generate_for_event
	 */
	public function test_does_not_create_duplicates_on_rerun(): void {
		$today    = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$event_id = $this->create_template_event(
			[
				'start_utc' => $today->format( 'Y-m-d' ) . ' 18:00:00',
				'end_utc'   => $today->format( 'Y-m-d' ) . ' 20:00:00',
			],
			[
				'frequency'   => 'weekly',
				'day_of_week' => (int) $today->format( 'w' ),
			]
		);

		$first_run  = Recurrence_Generator::generate_for_event( $event_id );
		$second_run = Recurrence_Generator::generate_for_event( $event_id );

		$this->assertNotEmpty( $first_run, 'First run should create instances.' );
		$this->assertEmpty( $second_run, 'Second run should not create duplicates.' );
	}

	/**
	 * @covers ::generate_for_event
	 */
	public function test_instances_inherit_template_properties(): void {
		$today    = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$event_id = $this->create_template_event(
			[
				'title'            => 'Inherited Props Meetup',
				'content'          => 'Description to inherit.',
				'start_utc'        => $today->format( 'Y-m-d' ) . ' 19:00:00',
				'end_utc'          => $today->format( 'Y-m-d' ) . ' 21:00:00',
				'timezone'         => 'Europe/London',
				'venue_id'         => 99,
				'online_link'      => 'https://zoom.us/j/inherited',
				'attendee_limit'   => 25,
				'waitlist_enabled' => true,
			],
			[
				'frequency'   => 'weekly',
				'day_of_week' => (int) $today->format( 'w' ),
			]
		);

		$created = Recurrence_Generator::generate_for_event( $event_id );
		$this->assertNotEmpty( $created );

		$instance_id = $created[0];
		$instance     = get_post( $instance_id );

		// Title and content.
		$this->assertSame( 'Inherited Props Meetup', $instance->post_title );
		$this->assertSame( 'Description to inherit.', $instance->post_content );

		// Meta fields.
		$this->assertSame( 'Europe/London', get_post_meta( $instance_id, '_event_timezone', true ) );
		$this->assertEquals( 99, get_post_meta( $instance_id, '_event_venue_id', true ) );
		$this->assertSame( 'https://zoom.us/j/inherited', get_post_meta( $instance_id, '_event_online_link', true ) );
		$this->assertEquals( 25, get_post_meta( $instance_id, '_event_attendee_limit', true ) );
		$this->assertEquals( true, get_post_meta( $instance_id, '_event_waitlist_enabled', true ) );

		// Template link.
		$this->assertEquals( $event_id, get_post_meta( $instance_id, Recurrence_Generator::TEMPLATE_META_KEY, true ) );

		// Duration should be preserved (2 hours).
		$instance_start = new DateTime( get_post_meta( $instance_id, '_event_start_utc', true ) );
		$instance_end   = new DateTime( get_post_meta( $instance_id, '_event_end_utc', true ) );
		$diff           = $instance_start->diff( $instance_end );
		$this->assertSame( 2, $diff->h );
		$this->assertSame( 0, $diff->i );
	}

	/**
	 * @covers ::generate_for_event
	 */
	public function test_returns_empty_for_non_recurring_event(): void {
		$event_id = Event::create( [
			'title'     => 'One-off Event',
			'start_utc' => '2026-04-15 18:00:00',
			'end_utc'   => '2026-04-15 20:00:00',
			'timezone'  => 'UTC',
		] );

		$created = Recurrence_Generator::generate_for_event( $event_id );

		$this->assertEmpty( $created );
	}

	/**
	 * @covers ::generate_for_event
	 */
	public function test_respects_recurrence_end_date(): void {
		$today    = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		// End date is 1 week from now — should only create at most 1 instance.
		$end_date = ( clone $today )->modify( '+8 days' )->format( 'Y-m-d' );

		$event_id = $this->create_template_event(
			[
				'start_utc' => $today->format( 'Y-m-d' ) . ' 18:00:00',
				'end_utc'   => $today->format( 'Y-m-d' ) . ' 20:00:00',
			],
			[
				'frequency'   => 'weekly',
				'day_of_week' => (int) $today->format( 'w' ),
				'end_date'    => $end_date,
			]
		);

		$created = Recurrence_Generator::generate_for_event( $event_id );

		// With a 1-week-and-a-day end date, we expect exactly 1 future instance (next week).
		$this->assertCount( 1, $created );
	}

	/**
	 * @covers ::schedule_cron
	 */
	public function test_schedule_cron_registers_event(): void {
		// Clear any existing schedule.
		$ts = wp_next_scheduled( Recurrence_Generator::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, Recurrence_Generator::CRON_HOOK );
		}

		Recurrence_Generator::schedule_cron();

		$this->assertNotFalse(
			wp_next_scheduled( Recurrence_Generator::CRON_HOOK ),
			'Cron event should be scheduled.'
		);
	}

	/**
	 * @covers ::schedule_cron
	 */
	public function test_schedule_cron_does_not_duplicate(): void {
		// Clear any existing schedule.
		$ts = wp_next_scheduled( Recurrence_Generator::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, Recurrence_Generator::CRON_HOOK );
		}

		Recurrence_Generator::schedule_cron();
		$first = wp_next_scheduled( Recurrence_Generator::CRON_HOOK );

		Recurrence_Generator::schedule_cron();
		$second = wp_next_scheduled( Recurrence_Generator::CRON_HOOK );

		$this->assertSame( $first, $second, 'Should not create a duplicate cron schedule.' );
	}
}
