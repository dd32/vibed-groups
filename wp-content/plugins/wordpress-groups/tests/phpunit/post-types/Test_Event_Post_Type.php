<?php
/**
 * Tests for the Event CPT registration.
 *
 * @package Groups\Tests
 */


use Groups\Post_Types\Event;

/**
 * @coversDefaultClass \Groups\Post_Types\Event
 */
class Test_Event_Post_Type extends WP_UnitTestCase {

	/**
	 * Set up each test — ensure the CPT and statuses are registered.
	 */
	public function set_up(): void {
		parent::set_up();

		// Trigger registration if not already done.
		$event = new Event();
		$event->register_post_type();
		$event->register_post_statuses();
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_event_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'event' ) );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_event_cpt_is_public(): void {
		$post_type = get_post_type_object( 'event' );

		$this->assertTrue( $post_type->public );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_event_cpt_shows_in_rest(): void {
		$post_type = get_post_type_object( 'event' );

		$this->assertTrue( $post_type->show_in_rest );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_event_cpt_supports(): void {
		$this->assertTrue( post_type_supports( 'event', 'title' ) );
		$this->assertTrue( post_type_supports( 'event', 'editor' ) );
		$this->assertTrue( post_type_supports( 'event', 'thumbnail' ) );
		$this->assertTrue( post_type_supports( 'event', 'excerpt' ) );
		$this->assertTrue( post_type_supports( 'event', 'custom-fields' ) );
	}

	/**
	 * @covers ::register_post_statuses
	 */
	public function test_custom_statuses_are_registered(): void {
		$expected = [
			'event-draft',
			'event-scheduled',
			'event-active',
			'event-past',
			'event-cancelled',
		];

		foreach ( $expected as $status ) {
			$status_obj = get_post_status_object( $status );
			$this->assertNotNull( $status_obj, "Status '{$status}' should be registered." );
			$this->assertTrue( $status_obj->public, "Status '{$status}' should be public." );
		}
	}

	/**
	 * @covers ::get_statuses
	 */
	public function test_get_statuses_returns_all_status_slugs(): void {
		$statuses = Event::get_statuses();

		$this->assertCount( 5, $statuses );
		$this->assertContains( 'event-draft', $statuses );
		$this->assertContains( 'event-scheduled', $statuses );
		$this->assertContains( 'event-active', $statuses );
		$this->assertContains( 'event-past', $statuses );
		$this->assertContains( 'event-cancelled', $statuses );
	}

	/**
	 * @covers ::register_post_type
	 */
	public function test_event_cpt_has_archive(): void {
		$post_type = get_post_type_object( 'event' );

		$this->assertTrue( $post_type->has_archive );
	}

	/**
	 * Test that an event post can be created with a custom status.
	 */
	public function test_can_create_event_with_custom_status(): void {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'event',
				'post_status' => 'event-scheduled',
				'post_title'  => 'Test Event',
			]
		);

		$this->assertIsInt( $post_id );
		$this->assertEquals( 'event-scheduled', get_post_status( $post_id ) );
	}
}
