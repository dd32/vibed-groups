<?php
/**
 * Tests for the Venue CPT registration.
 *
 * @package Groups\Tests
 */


use Groups\Post_Types\Venue;

/**
 * @coversDefaultClass \Groups\Post_Types\Venue
 */
class Test_Venue extends WP_UnitTestCase {

	/**
	 * Set up each test — ensure the CPT is registered.
	 */
	public function set_up(): void {
		parent::set_up();

		$venue = new Venue();
		$venue->register_post_type();
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_venue_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'venue' ) );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_venue_cpt_is_public(): void {
		$post_type = get_post_type_object( 'venue' );

		$this->assertTrue( $post_type->public );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_venue_cpt_shows_in_rest(): void {
		$post_type = get_post_type_object( 'venue' );

		$this->assertTrue( $post_type->show_in_rest );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_venue_cpt_supports(): void {
		$this->assertTrue( post_type_supports( 'venue', 'title' ) );
		$this->assertTrue( post_type_supports( 'venue', 'editor' ) );
		$this->assertTrue( post_type_supports( 'venue', 'thumbnail' ) );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_venue_cpt_does_not_support_excerpt(): void {
		$this->assertFalse( post_type_supports( 'venue', 'excerpt' ) );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_venue_cpt_has_archive(): void {
		$post_type = get_post_type_object( 'venue' );

		$this->assertTrue( $post_type->has_archive );
	}

	/**
	 * Test that a venue post can be created.
	 */
	public function test_can_create_venue(): void {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'venue',
				'post_status' => 'publish',
				'post_title'  => 'Test Venue',
			]
		);

		$this->assertIsInt( $post_id );
		$this->assertEquals( 'publish', get_post_status( $post_id ) );
		$this->assertEquals( 'venue', get_post_type( $post_id ) );
	}
}
