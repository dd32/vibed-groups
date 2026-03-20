<?php
/**
 * Tests for the Official Events API feed endpoint.
 *
 * @package Groups\Tests
 */

use Groups\Integrations\Official_Events_API;
use Groups\Post_Types\Event;
use Groups\Post_Types\Venue;

/**
 * @coversDefaultClass \Groups\Integrations\Official_Events_API
 * @group rest-api
 */
class Test_Official_Events_API extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Register CPTs once for the test suite.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$event_cpt = new Event();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		$venue_cpt = new Venue();
		$venue_cpt->register_post_type();
		$venue_cpt->register_meta_fields();
	}

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Helper: create an event on the current site.
	 *
	 * @param array $args Override defaults.
	 * @return int Post ID.
	 */
	private function create_event( array $args = [] ): int {
		$defaults = [
			'post_type'   => Event::POST_TYPE,
			'post_status' => 'event-scheduled',
			'post_title'  => 'Test Event',
			'post_content' => 'A test event description.',
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		$meta_defaults = [
			'_event_start_utc' => '2026-06-15 18:00:00',
			'_event_end_utc'   => '2026-06-15 20:00:00',
		];

		$meta = array_merge( $meta_defaults, $args['meta'] ?? [] );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Helper: create a venue on the current site.
	 *
	 * @param array $meta Venue meta.
	 * @return int Post ID.
	 */
	private function create_venue( array $meta = [] ): int {
		$venue_id = self::factory()->post->create( [
			'post_type'   => Venue::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Test Venue',
		] );

		$defaults = [
			'_venue_latitude'  => '-37.8136',
			'_venue_longitude' => '144.9631',
			'_venue_city'      => 'Melbourne',
			'_venue_country'   => 'AU',
		];

		foreach ( array_merge( $defaults, $meta ) as $key => $value ) {
			update_post_meta( $venue_id, $key, $value );
		}

		return $venue_id;
	}

	/**
	 * @covers ::get_items_permissions_check
	 */
	public function test_endpoint_is_publicly_accessible(): void {
		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		$this->create_event();

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Endpoint should be accessible without authentication.' );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_endpoint_returns_events_in_expected_format(): void {
		$venue_id = $this->create_venue();
		$this->create_event( [
			'post_title'   => 'Melbourne WordPress Meetup',
			'post_content' => 'Monthly WordPress meetup in Melbourne.',
			'meta'         => [
				'_event_start_utc' => '2026-07-01 08:00:00',
				'_event_end_utc'   => '2026-07-01 10:00:00',
				'_event_venue_id'   => $venue_id,
			],
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data, 'Should return at least one event.' );

		$event = $data[0];

		// Top-level fields.
		$this->assertArrayHasKey( 'title', $event );
		$this->assertArrayHasKey( 'description', $event );
		$this->assertArrayHasKey( 'url', $event );
		$this->assertArrayHasKey( 'date', $event );
		$this->assertArrayHasKey( 'end_date', $event );

		$this->assertSame( 'Melbourne WordPress Meetup', $event['title'] );
		$this->assertSame( 'Monthly WordPress meetup in Melbourne.', $event['description'] );
		$this->assertSame( '2026-07-01 08:00:00', $event['date'] );
		$this->assertSame( '2026-07-01 10:00:00', $event['end_date'] );

		// Location.
		$this->assertArrayHasKey( 'location', $event );
		$this->assertArrayHasKey( 'latitude', $event['location'] );
		$this->assertArrayHasKey( 'longitude', $event['location'] );
		$this->assertArrayHasKey( 'city', $event['location'] );
		$this->assertArrayHasKey( 'country', $event['location'] );
		$this->assertSame( 'Melbourne', $event['location']['city'] );
		$this->assertSame( 'AU', $event['location']['country'] );

		// Group.
		$this->assertArrayHasKey( 'group', $event );
		$this->assertArrayHasKey( 'name', $event['group'] );
		$this->assertArrayHasKey( 'url', $event['group'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_only_scheduled_and_active_events_are_returned(): void {
		$this->create_event( [
			'post_title'  => 'Scheduled Event',
			'post_status' => 'event-scheduled',
		] );
		$this->create_event( [
			'post_title'  => 'Active Event',
			'post_status' => 'event-active',
		] );
		$this->create_event( [
			'post_title'  => 'Past Event',
			'post_status' => 'event-past',
		] );
		$this->create_event( [
			'post_title'  => 'Draft Event',
			'post_status' => 'event-draft',
		] );
		$this->create_event( [
			'post_title'  => 'Cancelled Event',
			'post_status' => 'event-cancelled',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$titles = array_column( $data, 'title' );

		$this->assertContains( 'Scheduled Event', $titles );
		$this->assertContains( 'Active Event', $titles );
		$this->assertNotContains( 'Past Event', $titles );
		$this->assertNotContains( 'Draft Event', $titles );
		$this->assertNotContains( 'Cancelled Event', $titles );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_pagination_works(): void {
		// Create 5 events with distinct start dates.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->create_event( [
				'post_title' => "Event {$i}",
				'meta'       => [
					'_event_start_utc' => sprintf( '2026-08-%02d 18:00:00', $i ),
					'_event_end_utc'   => sprintf( '2026-08-%02d 20:00:00', $i ),
				],
			] );
		}

		// Page 1 with 2 per page.
		$request = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 2 );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data() );
		$this->assertEquals( 5, $response->get_headers()['X-WP-Total'] );
		$this->assertEquals( 3, $response->get_headers()['X-WP-TotalPages'] );

		// Page 2.
		$request->set_param( 'page', 2 );
		$response2 = $this->server->dispatch( $request );
		$this->assertCount( 2, $response2->get_data() );

		// Page 3 (last page, should have 1 event).
		$request->set_param( 'page', 3 );
		$response3 = $this->server->dispatch( $request );
		$this->assertCount( 1, $response3->get_data() );

		// Ensure no overlap between pages.
		$page1_titles = array_column( $response->get_data(), 'title' );
		$page2_titles = array_column( $response2->get_data(), 'title' );
		$page3_titles = array_column( $response3->get_data(), 'title' );

		$all_titles = array_merge( $page1_titles, $page2_titles, $page3_titles );
		$this->assertCount( 5, array_unique( $all_titles ), 'All pages combined should contain 5 unique events.' );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_events_sorted_by_start_date(): void {
		$this->create_event( [
			'post_title' => 'Later Event',
			'meta'       => [ '_event_start_utc' => '2026-12-01 18:00:00' ],
		] );
		$this->create_event( [
			'post_title' => 'Earlier Event',
			'meta'       => [ '_event_start_utc' => '2026-06-01 18:00:00' ],
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'Earlier Event', $data[0]['title'] );
		$this->assertSame( 'Later Event', $data[1]['title'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_cache_control_headers_are_set(): void {
		$this->create_event();

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertArrayHasKey( 'Cache-Control', $headers );
		$this->assertStringContainsString( 'public', $headers['Cache-Control'] );
		$this->assertStringContainsString( 'max-age=', $headers['Cache-Control'] );

		$this->assertArrayHasKey( 'ETag', $headers );
		$this->assertNotEmpty( $headers['ETag'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_empty_response_when_no_events(): void {
		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
		$this->assertEquals( 0, $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_event_without_venue_has_empty_location(): void {
		$this->create_event( [
			'post_title' => 'Online Only Event',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
		$event = $data[0];

		$this->assertSame( '', $event['location']['latitude'] );
		$this->assertSame( '', $event['location']['longitude'] );
		$this->assertSame( '', $event['location']['city'] );
		$this->assertSame( '', $event['location']['country'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_description_is_plain_text(): void {
		$this->create_event( [
			'post_title'   => 'HTML Event',
			'post_content' => '<p>This is a <strong>bold</strong> description.</p>',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/integration/events-feed' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertStringNotContainsString( '<', $data[0]['description'] );
		$this->assertStringContainsString( 'bold', $data[0]['description'] );
	}
}
