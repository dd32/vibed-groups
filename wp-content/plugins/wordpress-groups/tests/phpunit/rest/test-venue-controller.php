<?php
/**
 * Tests for the Venue REST API controller.
 *
 * @package Groups\Tests\REST
 */

namespace Groups\Tests\REST;

use Groups\Post_Types\Venue;
use Groups\REST\Venue_Controller;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Groups\REST\Venue_Controller
 * @group rest-api
 */
class Test_Venue_Controller extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Organizer user ID.
	 *
	 * @var int
	 */
	private int $organizer_id;

	/**
	 * Co-organizer user ID.
	 *
	 * @var int
	 */
	private int $co_organizer_id;

	/**
	 * Set up the test suite — register CPT once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$venue_cpt = new Venue();
		$venue_cpt->register_post_type();
		$venue_cpt->register_meta_fields();

		if ( ! get_role( 'organizer' ) ) {
			add_role( 'organizer', 'Organizer', [ 'read' => true ] );
		}

		if ( ! get_role( 'co_organizer' ) ) {
			add_role( 'co_organizer', 'Co-Organizer', [ 'read' => true ] );
		}
	}

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();

		$controller = new Venue_Controller();
		$controller->register_routes();

		$this->admin_id        = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id   = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->organizer_id    = self::factory()->user->create( [ 'role' => 'organizer' ] );
		$this->co_organizer_id = self::factory()->user->create( [ 'role' => 'co_organizer' ] );
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
	 * Helper to create a venue post with meta.
	 *
	 * @param array $args Optional post args.
	 * @param array $meta Optional meta values.
	 * @return int Post ID.
	 */
	private function create_venue( array $args = [], array $meta = [] ): int {
		$defaults = [
			'post_type'   => Venue::POST_TYPE,
			'post_title'  => 'Test Venue',
			'post_status' => 'publish',
			'post_author' => $this->admin_id,
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		$default_meta = [
			'_venue_address'   => '123 Main St',
			'_venue_city'      => 'Melbourne',
			'_venue_state'     => 'VIC',
			'_venue_country'   => 'Australia',
			'_venue_zip'       => '3000',
			'_venue_latitude'  => -37.8136,
			'_venue_longitude' => 144.9631,
		];

		foreach ( array_merge( $default_meta, $meta ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	// =========================================================================
	// CRUD tests
	// =========================================================================

	/**
	 * @covers ::get_items
	 */
	public function test_get_venues_returns_venues(): void {
		$this->create_venue( [ 'post_title' => 'Venue One' ] );
		$this->create_venue( [ 'post_title' => 'Venue Two' ] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/venues' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 2, (int) $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_get_single_venue_returns_venue_with_meta(): void {
		$post_id = $this->create_venue(
			[ 'post_title' => 'Library Hall' ],
			[
				'_venue_address'             => '456 Oak Ave',
				'_venue_city'                => 'Sydney',
				'_venue_state'               => 'NSW',
				'_venue_country'             => 'Australia',
				'_venue_zip'                 => '2000',
				'_venue_latitude'            => -33.8688,
				'_venue_longitude'           => 151.2093,
				'_venue_capacity'            => 200,
				'_venue_accessibility_notes' => 'Wheelchair accessible',
				'_venue_website'             => 'https://library.example.com',
			]
		);

		$request  = new WP_REST_Request( 'GET', '/groups/v1/venues/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'Library Hall', $data['title'] );
		$this->assertSame( '456 Oak Ave', $data['meta']['address'] );
		$this->assertSame( 'Sydney', $data['meta']['city'] );
		$this->assertSame( 'NSW', $data['meta']['state'] );
		$this->assertSame( 'Australia', $data['meta']['country'] );
		$this->assertSame( '2000', $data['meta']['zip'] );
		$this->assertEqualsWithDelta( -33.8688, $data['meta']['latitude'], 0.0001 );
		$this->assertEqualsWithDelta( 151.2093, $data['meta']['longitude'], 0.0001 );
		$this->assertSame( 200, $data['meta']['capacity'] );
		$this->assertSame( 'Wheelchair accessible', $data['meta']['accessibility_notes'] );
		$this->assertSame( 'https://library.example.com', $data['meta']['website'] );
	}

	/**
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_nonexistent_venue_returns_404(): void {
		$request  = new WP_REST_Request( 'GET', '/groups/v1/venues/999999' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_create_venue_as_organizer(): void {
		wp_set_current_user( $this->organizer_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title'   => 'Community Center',
			'content' => 'A great space for events.',
			'status'  => 'publish',
			'meta'    => [
				'address'             => '789 Elm St',
				'city'                => 'Brisbane',
				'state'               => 'QLD',
				'country'             => 'Australia',
				'zip'                 => '4000',
				'latitude'            => -27.4698,
				'longitude'           => 153.0251,
				'capacity'            => 100,
				'accessibility_notes' => 'Ramp at rear entrance',
				'website'             => 'https://community.example.com',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Community Center', $data['title'] );
		$this->assertSame( '789 Elm St', $data['meta']['address'] );
		$this->assertSame( 'Brisbane', $data['meta']['city'] );
		$this->assertEqualsWithDelta( -27.4698, $data['meta']['latitude'], 0.0001 );
		$this->assertEqualsWithDelta( 153.0251, $data['meta']['longitude'], 0.0001 );
		$this->assertSame( 100, $data['meta']['capacity'] );
		$this->assertSame( 'Ramp at rear entrance', $data['meta']['accessibility_notes'] );
		$this->assertSame( 'https://community.example.com', $data['meta']['website'] );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_create_venue_with_all_meta_fields(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title'  => 'Full Meta Venue',
			'status' => 'publish',
			'meta'   => [
				'address'             => '1 Test Rd',
				'city'                => 'Perth',
				'state'               => 'WA',
				'country'             => 'Australia',
				'zip'                 => '6000',
				'latitude'            => -31.9505,
				'longitude'           => 115.8605,
				'capacity'            => 50,
				'accessibility_notes' => 'Lift available',
				'website'             => 'https://test.example.com',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( '1 Test Rd', $data['meta']['address'] );
		$this->assertSame( 'Perth', $data['meta']['city'] );
		$this->assertSame( 'WA', $data['meta']['state'] );
		$this->assertSame( 'Australia', $data['meta']['country'] );
		$this->assertSame( '6000', $data['meta']['zip'] );
		$this->assertEqualsWithDelta( -31.9505, $data['meta']['latitude'], 0.0001 );
		$this->assertEqualsWithDelta( 115.8605, $data['meta']['longitude'], 0.0001 );
		$this->assertSame( 50, $data['meta']['capacity'] );
		$this->assertSame( 'Lift available', $data['meta']['accessibility_notes'] );
		$this->assertSame( 'https://test.example.com', $data['meta']['website'] );
	}

	/**
	 * @covers ::update_item
	 */
	public function test_update_venue(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_venue( [ 'post_title' => 'Original Venue' ] );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/venues/' . $post_id );
		$request->set_body_params( [
			'title' => 'Updated Venue',
			'meta'  => [
				'city'      => 'Adelaide',
				'latitude'  => -34.9285,
				'longitude' => 138.6007,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Updated Venue', $data['title'] );
		$this->assertSame( 'Adelaide', $data['meta']['city'] );
		$this->assertEqualsWithDelta( -34.9285, $data['meta']['latitude'], 0.0001 );
	}

	/**
	 * @covers ::delete_item
	 */
	public function test_delete_venue_trashes_post(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_venue( [ 'post_title' => 'Doomed Venue' ] );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/venues/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Doomed Venue', $data['previous']['title'] );

		$post = get_post( $post_id );
		$this->assertSame( 'trash', $post->post_status );
	}

	// =========================================================================
	// Geo field validation tests
	// =========================================================================

	/**
	 * @covers ::create_item
	 */
	public function test_invalid_latitude_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Bad Lat Venue',
			'meta'  => [
				'latitude' => 91.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_latitude', $response->get_data()['code'] );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_negative_invalid_latitude_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Bad Lat Venue',
			'meta'  => [
				'latitude' => -91.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_latitude', $response->get_data()['code'] );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_invalid_longitude_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Bad Lon Venue',
			'meta'  => [
				'longitude' => 181.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_longitude', $response->get_data()['code'] );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_negative_invalid_longitude_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Bad Lon Venue',
			'meta'  => [
				'longitude' => -181.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_longitude', $response->get_data()['code'] );
	}

	/**
	 * @covers ::update_item
	 */
	public function test_invalid_latitude_rejected_on_update(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_venue();

		$request = new WP_REST_Request( 'PUT', '/groups/v1/venues/' . $post_id );
		$request->set_body_params( [
			'meta' => [
				'latitude' => 95.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_valid_boundary_latitude_accepted(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'North Pole Venue',
			'meta'  => [
				'latitude'  => 90.0,
				'longitude' => 0.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertEqualsWithDelta( 90.0, $response->get_data()['meta']['latitude'], 0.0001 );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_valid_boundary_longitude_accepted(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Date Line Venue',
			'meta'  => [
				'latitude'  => 0.0,
				'longitude' => -180.0,
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertEqualsWithDelta( -180.0, $response->get_data()['meta']['longitude'], 0.0001 );
	}

	// =========================================================================
	// Permission tests
	// =========================================================================

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_venue_rejected_for_anonymous(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Anonymous Venue',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_venue_rejected_for_subscribers(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title' => 'Subscriber Venue',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers ::create_item_permissions_check
	 * @covers ::create_item
	 */
	public function test_co_organizer_can_create_venue(): void {
		wp_set_current_user( $this->co_organizer_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/venues' );
		$request->set_body_params( [
			'title'  => 'Co-Org Venue',
			'status' => 'publish',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Co-Org Venue', $response->get_data()['title'] );
	}

	/**
	 * @covers ::update_item_permissions_check
	 */
	public function test_subscriber_cannot_update_venue(): void {
		wp_set_current_user( $this->subscriber_id );

		$post_id = $this->create_venue();

		$request = new WP_REST_Request( 'PUT', '/groups/v1/venues/' . $post_id );
		$request->set_body_params( [
			'title' => 'Hacked Venue',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers ::delete_item_permissions_check
	 */
	public function test_subscriber_cannot_delete_venue(): void {
		wp_set_current_user( $this->subscriber_id );

		$post_id = $this->create_venue();

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/venues/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers ::get_items_permissions_check
	 */
	public function test_anonymous_can_list_published_venues(): void {
		wp_set_current_user( 0 );

		$this->create_venue( [ 'post_title' => 'Public Venue', 'post_status' => 'publish' ] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/venues' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$titles = array_column( $response->get_data(), 'title' );
		$this->assertContains( 'Public Venue', $titles );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_anonymous_cannot_see_draft_venues_in_list(): void {
		wp_set_current_user( 0 );

		$this->create_venue( [ 'post_title' => 'Draft Venue', 'post_status' => 'draft' ] );
		$this->create_venue( [ 'post_title' => 'Published Venue', 'post_status' => 'publish' ] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/venues' );
		$response = $this->server->dispatch( $request );

		$titles = array_column( $response->get_data(), 'title' );
		$this->assertNotContains( 'Draft Venue', $titles );
		$this->assertContains( 'Published Venue', $titles );
	}

	/**
	 * @covers ::get_item_permissions_check
	 */
	public function test_anonymous_cannot_see_draft_venue(): void {
		wp_set_current_user( 0 );

		$post_id = $this->create_venue( [ 'post_status' => 'draft' ] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/venues/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::get_item_schema
	 */
	public function test_schema_includes_meta_properties(): void {
		$controller = new Venue_Controller();
		$schema     = $controller->get_item_schema();

		$this->assertArrayHasKey( 'meta', $schema['properties'] );
		$this->assertArrayHasKey( 'address', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'city', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'state', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'country', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'zip', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'latitude', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'longitude', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'capacity', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'accessibility_notes', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'website', $schema['properties']['meta']['properties'] );
	}

	/**
	 * @covers ::update_item
	 */
	public function test_update_nonexistent_venue_returns_404(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/venues/999999' );
		$request->set_body_params( [
			'title' => 'Ghost Venue',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::delete_item
	 */
	public function test_delete_nonexistent_venue_returns_404(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/venues/999999' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
