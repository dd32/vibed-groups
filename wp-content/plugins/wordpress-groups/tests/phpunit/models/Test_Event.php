<?php
/**
 * Tests for the Event model.
 *
 * @package Groups\Tests
 */


use Groups\Models\Event;
use Groups\Post_Types\Event as Event_Post_Type;

/**
 * @coversDefaultClass \Groups\Models\Event
 */
class Test_Event extends WP_UnitTestCase {

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
	 * Helper to get default valid event args.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	private function get_event_args( array $overrides = [] ): array {
		return array_merge(
			[
				'title'     => 'Monthly WordPress Meetup',
				'content'   => 'Join us for our monthly meetup!',
				'start_utc' => '2026-04-15 18:00:00',
				'end_utc'   => '2026-04-15 20:00:00',
				'timezone'  => 'America/New_York',
			],
			$overrides
		);
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_returns_post_id(): void {
		$post_id = Event::create( $this->get_event_args() );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_stores_datetime_meta(): void {
		$args    = $this->get_event_args();
		$post_id = Event::create( $args );

		$this->assertSame( '2026-04-15 18:00:00', get_post_meta( $post_id, '_event_start_utc', true ) );
		$this->assertSame( '2026-04-15 20:00:00', get_post_meta( $post_id, '_event_end_utc', true ) );
		$this->assertSame( 'America/New_York', get_post_meta( $post_id, '_event_timezone', true ) );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_stores_optional_meta(): void {
		$args = $this->get_event_args(
			[
				'venue_id'         => 42,
				'online_link'      => 'https://meet.example.com/room',
				'attendee_limit'   => 50,
				'waitlist_enabled' => true,
			]
		);

		$post_id = Event::create( $args );

		$this->assertEquals( 42, get_post_meta( $post_id, '_event_venue_id', true ) );
		$this->assertSame( 'https://meet.example.com/room', get_post_meta( $post_id, '_event_online_link', true ) );
		$this->assertEquals( 50, get_post_meta( $post_id, '_event_attendee_limit', true ) );
		$this->assertEquals( true, get_post_meta( $post_id, '_event_waitlist_enabled', true ) );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_defaults_to_draft_status(): void {
		$post_id = Event::create( $this->get_event_args() );

		$this->assertSame( 'event-draft', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_with_custom_status(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-scheduled' ] ) );

		$this->assertSame( 'event-scheduled', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_requires_title(): void {
		$args = $this->get_event_args( [ 'title' => '' ] );
		$result = Event::create( $args );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_title', $result->get_error_code() );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_requires_datetime(): void {
		$args = $this->get_event_args();
		unset( $args['start_utc'], $args['end_utc'] );

		$result = Event::create( $args );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_datetime', $result->get_error_code() );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_event_requires_timezone(): void {
		$args = $this->get_event_args();
		unset( $args['timezone'] );

		$result = Event::create( $args );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_timezone', $result->get_error_code() );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_event_title(): void {
		$post_id = Event::create( $this->get_event_args() );

		$result = Event::update( $post_id, [ 'title' => 'Updated Meetup Title' ] );

		$this->assertTrue( $result );
		$this->assertSame( 'Updated Meetup Title', get_post( $post_id )->post_title );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_event_meta(): void {
		$post_id = Event::create( $this->get_event_args() );

		Event::update( $post_id, [ 'start_utc' => '2026-04-16 19:00:00' ] );

		$this->assertSame( '2026-04-16 19:00:00', get_post_meta( $post_id, '_event_start_utc', true ) );
	}

	/**
	 * @covers ::update
	 */
	public function test_update_invalid_event_returns_error(): void {
		$result = Event::update( 999999, [ 'title' => 'Nope' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_event', $result->get_error_code() );
	}

	/**
	 * @covers ::get
	 */
	public function test_get_returns_event_data(): void {
		$args    = $this->get_event_args(
			[
				'venue_id'    => 7,
				'online_link' => 'https://zoom.us/j/123',
			]
		);
		$post_id = Event::create( $args );

		$data = Event::get( $post_id );

		$this->assertIsArray( $data );
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'Monthly WordPress Meetup', $data['title'] );
		$this->assertSame( 'Join us for our monthly meetup!', $data['content'] );
		$this->assertSame( 'event-draft', $data['status'] );
		$this->assertSame( '2026-04-15 18:00:00', $data['start_utc'] );
		$this->assertSame( '2026-04-15 20:00:00', $data['end_utc'] );
		$this->assertSame( 'America/New_York', $data['timezone'] );
		$this->assertEquals( 7, $data['venue_id'] );
		$this->assertSame( 'https://zoom.us/j/123', $data['online_link'] );
	}

	/**
	 * @covers ::get
	 */
	public function test_get_invalid_event_returns_error(): void {
		$result = Event::get( 999999 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_event', $result->get_error_code() );
	}

	/**
	 * @covers ::transition_status
	 * @covers ::get_valid_transitions
	 */
	public function test_valid_transition_draft_to_scheduled(): void {
		$post_id = Event::create( $this->get_event_args() );

		$result = Event::transition_status( $post_id, 'event-scheduled' );

		$this->assertTrue( $result );
		$this->assertSame( 'event-scheduled', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_valid_transition_draft_to_cancelled(): void {
		$post_id = Event::create( $this->get_event_args() );

		$result = Event::transition_status( $post_id, 'event-cancelled' );

		$this->assertTrue( $result );
		$this->assertSame( 'event-cancelled', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_valid_transition_scheduled_to_active(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-scheduled' ] ) );

		$result = Event::transition_status( $post_id, 'event-active' );

		$this->assertTrue( $result );
		$this->assertSame( 'event-active', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_valid_transition_scheduled_to_draft(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-scheduled' ] ) );

		$result = Event::transition_status( $post_id, 'event-draft' );

		$this->assertTrue( $result );
		$this->assertSame( 'event-draft', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_valid_transition_active_to_past(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-active' ] ) );

		$result = Event::transition_status( $post_id, 'event-past' );

		$this->assertTrue( $result );
		$this->assertSame( 'event-past', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_valid_transition_cancelled_to_draft(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-cancelled' ] ) );

		$result = Event::transition_status( $post_id, 'event-draft' );

		$this->assertTrue( $result );
		$this->assertSame( 'event-draft', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_invalid_transition_draft_to_active_fails(): void {
		$post_id = Event::create( $this->get_event_args() );

		$result = Event::transition_status( $post_id, 'event-active' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_transition', $result->get_error_code() );
		$this->assertSame( 'event-draft', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_invalid_transition_past_is_terminal(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-past' ] ) );

		$result = Event::transition_status( $post_id, 'event-draft' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_transition', $result->get_error_code() );
		$this->assertSame( 'event-past', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_invalid_transition_cancelled_to_scheduled_fails(): void {
		$post_id = Event::create( $this->get_event_args( [ 'status' => 'event-cancelled' ] ) );

		$result = Event::transition_status( $post_id, 'event-scheduled' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_transition', $result->get_error_code() );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_transition_fires_action_hook(): void {
		$post_id = Event::create( $this->get_event_args() );
		$fired   = false;

		add_action(
			'groups_event_status_transition',
			function ( $id, $old, $new ) use ( $post_id, &$fired ) {
				$fired = true;
				$this->assertSame( $post_id, $id );
				$this->assertSame( 'event-draft', $old );
				$this->assertSame( 'event-scheduled', $new );
			},
			10,
			3
		);

		Event::transition_status( $post_id, 'event-scheduled' );

		$this->assertTrue( $fired, 'The groups_event_status_transition action should have fired.' );
	}

	/**
	 * @covers ::transition_status
	 */
	public function test_transition_invalid_post_returns_error(): void {
		$result = Event::transition_status( 999999, 'event-scheduled' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_event', $result->get_error_code() );
	}

	/**
	 * @covers ::get_valid_transitions
	 */
	public function test_get_valid_transitions_returns_correct_statuses(): void {
		$this->assertSame( [ 'event-scheduled', 'event-cancelled' ], Event::get_valid_transitions( 'event-draft' ) );
		$this->assertSame( [ 'event-active', 'event-cancelled', 'event-draft' ], Event::get_valid_transitions( 'event-scheduled' ) );
		$this->assertSame( [ 'event-past', 'event-cancelled' ], Event::get_valid_transitions( 'event-active' ) );
		$this->assertSame( [], Event::get_valid_transitions( 'event-past' ) );
		$this->assertSame( [ 'event-draft' ], Event::get_valid_transitions( 'event-cancelled' ) );
	}

	/**
	 * @covers ::get_valid_transitions
	 */
	public function test_get_valid_transitions_unknown_status_returns_empty(): void {
		$this->assertSame( [], Event::get_valid_transitions( 'nonexistent-status' ) );
	}
}
