<?php
/**
 * Tests for the Location_Query model.
 *
 * @package Groups\Tests
 */

use Groups\Models\Location_Query;
use Groups\Post_Types\Venue;

/**
 * @coversDefaultClass \Groups\Models\Location_Query
 */
class Test_Location_Query extends WP_UnitTestCase {

	/**
	 * Register the venue CPT before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$venue_cpt = new Venue();
		$venue_cpt->register_post_type();
		$venue_cpt->register_meta_fields();
	}

	/**
	 * Helper: create a venue post with lat/lon meta.
	 *
	 * @param string $title     Venue title.
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @return int Post ID.
	 */
	private function create_venue( string $title, float $latitude, float $longitude ): int {
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'venue',
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);

		update_post_meta( $post_id, '_venue_latitude', $latitude );
		update_post_meta( $post_id, '_venue_longitude', $longitude );

		return $post_id;
	}

	/**
	 * Helper: create a wp_meetup post with lat/lon meta.
	 *
	 * @param string $title     Post title.
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @return int Post ID.
	 */
	private function create_meetup( string $title, float $latitude, float $longitude ): int {
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'wp_meetup',
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);

		update_post_meta( $post_id, '_meetup_latitude', $latitude );
		update_post_meta( $post_id, '_meetup_longitude', $longitude );

		return $post_id;
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_finds_venues_within_radius(): void {
		// Melbourne: -37.8136, 144.9631
		$this->create_venue( 'Melbourne Venue', -37.8136, 144.9631 );

		// Geelong: ~75 km from Melbourne.
		$this->create_venue( 'Geelong Venue', -38.1499, 144.3617 );

		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0 );

		$this->assertNotWPError( $query_result );
		$this->assertArrayHasKey( 'results', $query_result );
		$this->assertArrayHasKey( 'total', $query_result );
		$this->assertCount( 2, $query_result['results'] );
		$this->assertSame( 2, $query_result['total'] );

		$titles = wp_list_pluck( $query_result['results'], 'post_title' );
		$this->assertContains( 'Melbourne Venue', $titles );
		$this->assertContains( 'Geelong Venue', $titles );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_excludes_venues_outside_radius(): void {
		// Melbourne.
		$this->create_venue( 'Melbourne Venue', -37.8136, 144.9631 );

		// Sydney: ~714 km from Melbourne.
		$this->create_venue( 'Sydney Venue', -33.8688, 151.2093 );

		// Search 100 km around Melbourne — Sydney should be excluded.
		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0 );

		$this->assertNotWPError( $query_result );
		$this->assertCount( 1, $query_result['results'] );
		$this->assertSame( 'Melbourne Venue', $query_result['results'][0]->post_title );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_results_ordered_by_distance_ascending(): void {
		// Melbourne.
		$this->create_venue( 'Melbourne Venue', -37.8136, 144.9631 );

		// Geelong: ~75 km.
		$this->create_venue( 'Geelong Venue', -38.1499, 144.3617 );

		// Sydney: ~714 km.
		$this->create_venue( 'Sydney Venue', -33.8688, 151.2093 );

		// Search 1000 km from Melbourne — all three should appear.
		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 1000.0 );

		$this->assertNotWPError( $query_result );
		$results = $query_result['results'];
		$this->assertCount( 3, $results );

		// Distance should increase: Melbourne (0) < Geelong (~75) < Sydney (~714).
		$this->assertSame( 'Melbourne Venue', $results[0]->post_title );
		$this->assertSame( 'Geelong Venue', $results[1]->post_title );
		$this->assertSame( 'Sydney Venue', $results[2]->post_title );

		// Verify distances are actually ascending.
		$this->assertLessThan( $results[1]->distance, $results[0]->distance );
		$this->assertLessThan( $results[2]->distance, $results[1]->distance );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_known_distance_melbourne_to_sydney(): void {
		// Melbourne.
		$this->create_venue( 'Melbourne Venue', -37.8136, 144.9631 );

		// Sydney.
		$this->create_venue( 'Sydney Venue', -33.8688, 151.2093 );

		// Search from Melbourne with a large radius to get Sydney.
		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 1000.0 );

		$this->assertNotWPError( $query_result );

		// Find the Sydney result.
		$sydney = null;
		foreach ( $query_result['results'] as $result ) {
			if ( 'Sydney Venue' === $result->post_title ) {
				$sydney = $result;
				break;
			}
		}

		$this->assertNotNull( $sydney, 'Sydney venue should be found.' );

		// Haversine distance Melbourne-Sydney is approximately 714 km.
		// Allow a tolerance of 20 km for floating-point precision.
		$this->assertEqualsWithDelta( 714.0, $sydney->distance, 20.0 );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_distance_property_is_float(): void {
		$this->create_venue( 'Test Venue', -37.8136, 144.9631 );

		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 10.0 );

		$this->assertNotWPError( $query_result );
		$this->assertCount( 1, $query_result['results'] );
		$this->assertIsFloat( $query_result['results'][0]->distance );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_respects_limit_argument(): void {
		$this->create_venue( 'Venue A', -37.8136, 144.9631 );
		$this->create_venue( 'Venue B', -37.8200, 144.9700 );
		$this->create_venue( 'Venue C', -37.8300, 144.9800 );

		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0, [ 'limit' => 2 ] );

		$this->assertNotWPError( $query_result );
		$this->assertCount( 2, $query_result['results'] );
		$this->assertSame( 3, $query_result['total'] );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_pagination_returns_correct_page(): void {
		$this->create_venue( 'Venue A', -37.8136, 144.9631 );
		$this->create_venue( 'Venue B', -37.8200, 144.9700 );
		$this->create_venue( 'Venue C', -37.8300, 144.9800 );

		$page1 = Location_Query::find_nearby( -37.8136, 144.9631, 100.0, [ 'limit' => 2, 'page' => 1 ] );
		$page2 = Location_Query::find_nearby( -37.8136, 144.9631, 100.0, [ 'limit' => 2, 'page' => 2 ] );

		$this->assertNotWPError( $page1 );
		$this->assertNotWPError( $page2 );
		$this->assertCount( 2, $page1['results'] );
		$this->assertCount( 1, $page2['results'] );
		$this->assertSame( 3, $page1['total'] );
		$this->assertSame( 3, $page2['total'] );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_filters_by_post_status(): void {
		$this->create_venue( 'Published Venue', -37.8136, 144.9631 );

		// Create a draft venue.
		$draft_id = $this->factory()->post->create(
			[
				'post_type'   => 'venue',
				'post_title'  => 'Draft Venue',
				'post_status' => 'draft',
			]
		);
		update_post_meta( $draft_id, '_venue_latitude', -37.8200 );
		update_post_meta( $draft_id, '_venue_longitude', 144.9700 );

		// Default status is publish — draft should be excluded.
		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0 );

		$this->assertNotWPError( $query_result );
		$this->assertCount( 1, $query_result['results'] );
		$this->assertSame( 'Published Venue', $query_result['results'][0]->post_title );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_queries_wp_meetup_post_type(): void {
		// Register wp_meetup CPT for this test.
		register_post_type( 'wp_meetup', [ 'public' => true ] );

		$this->create_meetup( 'Melbourne Group', -37.8136, 144.9631 );
		$this->create_meetup( 'Sydney Group', -33.8688, 151.2093 );

		// Search meetups near Melbourne.
		$query_result = Location_Query::find_nearby(
			-37.8136,
			144.9631,
			100.0,
			[ 'post_type' => 'wp_meetup' ]
		);

		$this->assertNotWPError( $query_result );
		$this->assertCount( 1, $query_result['results'] );
		$this->assertSame( 'Melbourne Group', $query_result['results'][0]->post_title );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_returns_empty_array_when_no_matches(): void {
		// Create venue far from search point.
		$this->create_venue( 'Far Away', 40.7128, -74.0060 ); // New York City.

		// Search near Melbourne.
		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0 );

		$this->assertNotWPError( $query_result );
		$this->assertIsArray( $query_result['results'] );
		$this->assertCount( 0, $query_result['results'] );
		$this->assertSame( 0, $query_result['total'] );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_invalid_latitude_returns_error(): void {
		$result = Location_Query::find_nearby( 91.0, 144.9631, 100.0 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_latitude', $result->get_error_code() );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_invalid_longitude_returns_error(): void {
		$result = Location_Query::find_nearby( -37.8136, 181.0, 100.0 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_longitude', $result->get_error_code() );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_invalid_radius_returns_error(): void {
		$result = Location_Query::find_nearby( -37.8136, 144.9631, 0.0 );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_radius', $result->get_error_code() );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_unsupported_post_type_returns_error(): void {
		$result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0, [ 'post_type' => 'page' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'unsupported_post_type', $result->get_error_code() );
	}

	/**
	 * @covers ::find_nearby
	 */
	public function test_venues_without_coordinates_excluded(): void {
		$this->create_venue( 'With Coords', -37.8136, 144.9631 );

		// Create venue without coordinates.
		$this->factory()->post->create(
			[
				'post_type'   => 'venue',
				'post_title'  => 'No Coords',
				'post_status' => 'publish',
			]
		);

		$query_result = Location_Query::find_nearby( -37.8136, 144.9631, 100.0 );

		$this->assertNotWPError( $query_result );
		$this->assertCount( 1, $query_result['results'] );
		$this->assertSame( 'With Coords', $query_result['results'][0]->post_title );
	}
}
