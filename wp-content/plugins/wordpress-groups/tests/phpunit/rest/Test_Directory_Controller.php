<?php
/**
 * Tests for the Directory REST API controller.
 *
 * @package Groups\Tests\REST
 */

use Groups\REST\Directory_Controller;

/**
 * @coversDefaultClass \Groups\REST\Directory_Controller
 * @group rest-api
 */
class Test_Directory_Controller extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
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
	 * Register the wp_meetup post type for tests.
	 *
	 * Called from set_up_before_class and when needed after switch_to_blog.
	 */
	private static function register_meetup_post_type(): void {
		if ( ! post_type_exists( 'wp_meetup' ) ) {
			register_post_type( 'wp_meetup', [
				'public'    => false,
				'show_ui'   => true,
				'label'     => 'Meetup Groups',
				'supports'  => [ 'title', 'editor' ],
			] );
		}

		// Register custom statuses.
		$statuses = [
			'meetup-active',
			'meetup-dormant',
			'meetup-pending',
			'meetup-vetting',
			'meetup-feedback',
			'meetup-orientation',
			'meetup-scheduling',
			'meetup-suspended',
			'meetup-removed',
			'meetup-declined',
		];

		foreach ( $statuses as $status ) {
			if ( ! get_post_status_object( $status ) ) {
				register_post_status( $status, [
					'label'  => ucfirst( str_replace( 'meetup-', '', $status ) ),
					'public' => true,
				] );
			}
		}
	}

	/**
	 * Helper to create a wp_meetup post with meta on the main site.
	 *
	 * @param array $args Optional post args.
	 * @param array $meta Optional meta values.
	 * @return int Post ID.
	 */
	private function create_meetup_group( array $args = [], array $meta = [] ): int {
		self::register_meetup_post_type();

		$defaults = [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Test Group',
			'post_status' => 'meetup-active',
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		$default_meta = [
			'_meetup_city'            => 'Melbourne',
			'_meetup_country'         => 'Australia',
			'_meetup_latitude'        => '-37.8136',
			'_meetup_longitude'       => '144.9631',
			'_meetup_member_count'    => '42',
			'_meetup_last_event_date' => '2026-03-01',
			'_meetup_site_id'         => '0',
			'_meetup_timezone'        => 'Australia/Melbourne',
		];

		foreach ( array_merge( $default_meta, $meta ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * @covers ::get_items
	 */
	public function test_list_returns_groups(): void {
		$this->create_meetup_group( [ 'post_title' => 'Melbourne WordPress' ] );
		$this->create_meetup_group( [ 'post_title' => 'Sydney WordPress' ], [
			'_meetup_city'    => 'Sydney',
			'_meetup_country' => 'Australia',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/groups' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 2, (int) $response->get_headers()['X-WP-Total'] );

		$names = array_column( $data, 'name' );
		$this->assertContains( 'Melbourne WordPress', $names );
		$this->assertContains( 'Sydney WordPress', $names );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_search_filter(): void {
		$this->create_meetup_group( [ 'post_title' => 'Melbourne WordPress' ] );
		$this->create_meetup_group( [ 'post_title' => 'Sydney WordPress' ], [
			'_meetup_city' => 'Sydney',
		] );
		$this->create_meetup_group( [ 'post_title' => 'London WordPress' ], [
			'_meetup_city'    => 'London',
			'_meetup_country' => 'United Kingdom',
		] );

		$request = new WP_REST_Request( 'GET', '/groups/v1/groups' );
		$request->set_param( 'search', 'Melbourne' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data  = $response->get_data();
		$names = array_column( $data, 'name' );

		$this->assertContains( 'Melbourne WordPress', $names );
		$this->assertNotContains( 'London WordPress', $names );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_single_group(): void {
		// Create a sub-site to use as the group's site.
		$blog_id = self::factory()->blog->create( [
			'domain' => 'example.org',
			'path'   => '/melbourne-wordpress/',
		] );

		$this->create_meetup_group(
			[ 'post_title' => 'Melbourne WordPress' ],
			[
				'_meetup_site_id'      => (string) $blog_id,
				'_meetup_city'         => 'Melbourne',
				'_meetup_country'      => 'Australia',
				'_meetup_member_count' => '100',
			]
		);

		$request  = new WP_REST_Request( 'GET', '/groups/v1/groups/' . $blog_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Melbourne WordPress', $data['name'] );
		$this->assertSame( 'Melbourne', $data['city'] );
		$this->assertSame( 'Australia', $data['country'] );
		$this->assertSame( 100, $data['member_count'] );
		$this->assertSame( $blog_id, $data['site_id'] );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_single_group_not_found(): void {
		$request  = new WP_REST_Request( 'GET', '/groups/v1/groups/999999' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::get_items_permissions_check
	 */
	public function test_public_access_list(): void {
		wp_set_current_user( 0 );

		$this->create_meetup_group( [ 'post_title' => 'Public Group' ] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/groups' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data() );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_dormant_groups_hidden_from_public(): void {
		wp_set_current_user( 0 );

		$this->create_meetup_group( [
			'post_title'  => 'Active Group',
			'post_status' => 'meetup-active',
		] );

		$this->create_meetup_group( [
			'post_title'  => 'Dormant Group',
			'post_status' => 'meetup-dormant',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/groups' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = array_column( $response->get_data(), 'name' );
		$this->assertContains( 'Active Group', $names );
		$this->assertNotContains( 'Dormant Group', $names );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_pagination_headers(): void {
		for ( $i = 1; $i <= 15; $i++ ) {
			$this->create_meetup_group( [ 'post_title' => "Group $i" ] );
		}

		$request = new WP_REST_Request( 'GET', '/groups/v1/groups' );
		$request->set_param( 'per_page', 10 );
		$request->set_param( 'page', 1 );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 10, $response->get_data() );
		$this->assertSame( 15, (int) $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 2, (int) $response->get_headers()['X-WP-TotalPages'] );
	}

	/**
	 * @covers ::get_item_schema
	 */
	public function test_schema_has_required_properties(): void {
		$controller = new Directory_Controller();
		$schema     = $controller->get_item_schema();

		$expected_keys = [
			'name',
			'city',
			'country',
			'latitude',
			'longitude',
			'member_count',
			'last_event_date',
			'site_url',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $schema['properties'], "Schema missing property: $key" );
		}
	}

	/**
	 * @covers ::register_routes
	 */
	public function test_routes_registered(): void {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/groups/v1/groups', $routes );
		$this->assertArrayHasKey( '/groups/v1/groups/(?P<blog_id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/groups/v1/groups/(?P<blog_id>[\d]+)/events', $routes );
	}
}
