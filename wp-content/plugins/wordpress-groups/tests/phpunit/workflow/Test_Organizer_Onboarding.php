<?php
/**
 * Tests for the Organizer_Onboarding workflow.
 *
 * @package Groups\Tests
 */

use Groups\Workflow\Organizer_Onboarding;

/**
 * @coversDefaultClass \Groups\Workflow\Organizer_Onboarding
 */
class Test_Organizer_Onboarding extends WP_UnitTestCase {

	/**
	 * Onboarding instance.
	 *
	 * @var Organizer_Onboarding
	 */
	private Organizer_Onboarding $onboarding;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Register the wp_meetup post type for testing.
		register_post_type( 'wp_meetup', [
			'public' => false,
		] );

		$statuses = [
			'meetup-pending',
			'meetup-vetting',
			'meetup-feedback',
			'meetup-orientation',
			'meetup-scheduling',
			'meetup-active',
			'meetup-dormant',
			'meetup-suspended',
			'meetup-removed',
			'meetup-declined',
		];

		foreach ( $statuses as $status ) {
			register_post_status( $status, [
				'public' => true,
			] );
		}

		$this->onboarding = new Organizer_Onboarding();
	}

	/**
	 * Helper to create a wp_meetup post with a given status.
	 *
	 * @param string $status Initial post status.
	 * @return int Post ID.
	 */
	private function create_meetup_post( string $status = 'meetup-orientation' ): int {
		return wp_insert_post( [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Test Meetup Group',
			'post_status' => $status,
		] );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_sets_meta(): void {
		$post_id = $this->create_meetup_post();
		$mentor  = self::factory()->user->create();

		$result = $this->onboarding->assign_mentor( $post_id, $mentor );

		$this->assertTrue( $result );
		$this->assertSame( $mentor, $this->onboarding->get_mentor( $post_id ) );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_returns_false_for_invalid_post(): void {
		$mentor = self::factory()->user->create();

		$this->assertFalse( $this->onboarding->assign_mentor( 99999, $mentor ) );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_returns_false_for_wrong_post_type(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );
		$mentor  = self::factory()->user->create();

		$this->assertFalse( $this->onboarding->assign_mentor( $post_id, $mentor ) );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_returns_false_for_invalid_user(): void {
		$post_id = $this->create_meetup_post();

		$this->assertFalse( $this->onboarding->assign_mentor( $post_id, 99999 ) );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_fires_action(): void {
		$post_id = $this->create_meetup_post();
		$mentor  = self::factory()->user->create();

		$fired     = false;
		$hook_args = [];
		$callback  = function ( $pid, $mid ) use ( &$fired, &$hook_args ) {
			$fired     = true;
			$hook_args = [ 'post_id' => $pid, 'mentor_id' => $mid ];
		};

		add_action( 'groups_mentor_assigned', $callback, 10, 2 );

		$this->onboarding->assign_mentor( $post_id, $mentor );

		remove_action( 'groups_mentor_assigned', $callback, 10 );

		$this->assertTrue( $fired );
		$this->assertSame( $post_id, $hook_args['post_id'] );
		$this->assertSame( $mentor, $hook_args['mentor_id'] );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_adds_co_organizer_to_group_site(): void {
		$post_id = $this->create_meetup_post();
		$mentor  = self::factory()->user->create();

		// Simulate a provisioned site.
		$blog_id = self::factory()->blog->create();
		update_post_meta( $post_id, '_meetup_site_id', $blog_id );

		$this->onboarding->assign_mentor( $post_id, $mentor );

		$this->assertTrue( is_user_member_of_blog( $mentor, $blog_id ) );
	}

	/**
	 * @covers ::get_mentor
	 */
	public function test_get_mentor_returns_zero_when_none_assigned(): void {
		$post_id = $this->create_meetup_post();

		$this->assertSame( 0, $this->onboarding->get_mentor( $post_id ) );
	}

	/**
	 * @covers ::get_orientation_checklist
	 */
	public function test_get_orientation_checklist_returns_all_items(): void {
		$post_id   = $this->create_meetup_post();
		$checklist = $this->onboarding->get_orientation_checklist( $post_id );

		$this->assertArrayHasKey( 'profile_complete', $checklist );
		$this->assertArrayHasKey( 'coc_accepted', $checklist );
		$this->assertArrayHasKey( 'first_event_planned', $checklist );
		$this->assertArrayHasKey( 'venue_confirmed', $checklist );
		$this->assertCount( 4, $checklist );
	}

	/**
	 * @covers ::get_orientation_checklist
	 */
	public function test_checklist_items_default_to_incomplete(): void {
		$post_id   = $this->create_meetup_post();
		$checklist = $this->onboarding->get_orientation_checklist( $post_id );

		foreach ( $checklist as $item ) {
			$this->assertFalse( $item['completed'] );
			$this->assertNotEmpty( $item['label'] );
		}
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_marks_item_done(): void {
		$post_id = $this->create_meetup_post();

		$result = $this->onboarding->complete_checklist_item( $post_id, 'profile_complete' );

		$this->assertTrue( $result );

		$checklist = $this->onboarding->get_orientation_checklist( $post_id );
		$this->assertTrue( $checklist['profile_complete']['completed'] );
		$this->assertFalse( $checklist['coc_accepted']['completed'] );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_returns_false_for_invalid_key(): void {
		$post_id = $this->create_meetup_post();

		$this->assertFalse( $this->onboarding->complete_checklist_item( $post_id, 'nonexistent_item' ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_returns_false_for_invalid_post(): void {
		$this->assertFalse( $this->onboarding->complete_checklist_item( 99999, 'profile_complete' ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_returns_false_for_wrong_post_type(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertFalse( $this->onboarding->complete_checklist_item( $post_id, 'profile_complete' ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_fires_action(): void {
		$post_id = $this->create_meetup_post();

		$fired     = false;
		$hook_args = [];
		$callback  = function ( $pid, $key ) use ( &$fired, &$hook_args ) {
			$fired     = true;
			$hook_args = [ 'post_id' => $pid, 'item_key' => $key ];
		};

		add_action( 'groups_checklist_item_completed', $callback, 10, 2 );

		$this->onboarding->complete_checklist_item( $post_id, 'coc_accepted' );

		remove_action( 'groups_checklist_item_completed', $callback, 10 );

		$this->assertTrue( $fired );
		$this->assertSame( $post_id, $hook_args['post_id'] );
		$this->assertSame( 'coc_accepted', $hook_args['item_key'] );
	}

	/**
	 * @covers ::is_checklist_complete
	 */
	public function test_is_checklist_complete_returns_false_when_incomplete(): void {
		$post_id = $this->create_meetup_post();

		$this->onboarding->complete_checklist_item( $post_id, 'profile_complete' );
		$this->onboarding->complete_checklist_item( $post_id, 'coc_accepted' );

		$this->assertFalse( $this->onboarding->is_checklist_complete( $post_id ) );
	}

	/**
	 * @covers ::is_checklist_complete
	 */
	public function test_is_checklist_complete_returns_true_when_all_done(): void {
		$post_id = $this->create_meetup_post();

		$this->onboarding->complete_checklist_item( $post_id, 'profile_complete' );
		$this->onboarding->complete_checklist_item( $post_id, 'coc_accepted' );
		$this->onboarding->complete_checklist_item( $post_id, 'first_event_planned' );
		$this->onboarding->complete_checklist_item( $post_id, 'venue_confirmed' );

		$this->assertTrue( $this->onboarding->is_checklist_complete( $post_id ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_completing_all_items_fires_complete_action(): void {
		$post_id = $this->create_meetup_post();

		// Complete first three without checking.
		$this->onboarding->complete_checklist_item( $post_id, 'profile_complete' );
		$this->onboarding->complete_checklist_item( $post_id, 'coc_accepted' );
		$this->onboarding->complete_checklist_item( $post_id, 'first_event_planned' );

		$fired    = false;
		$callback = function () use ( &$fired ) {
			$fired = true;
		};

		add_action( 'groups_onboarding_checklist_complete', $callback, 5 );

		// Complete the last item.
		$this->onboarding->complete_checklist_item( $post_id, 'venue_confirmed' );

		remove_action( 'groups_onboarding_checklist_complete', $callback, 5 );

		$this->assertTrue( $fired, 'groups_onboarding_checklist_complete should fire when all items done.' );
	}

	/**
	 * @covers ::auto_transition_to_scheduling
	 */
	public function test_auto_transition_when_checklist_complete(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		// Temporarily remove transition validation to avoid interference.
		remove_all_actions( 'transition_post_status' );

		// Re-add only the onboarding hook.
		$onboarding = new Organizer_Onboarding();

		$onboarding->complete_checklist_item( $post_id, 'profile_complete' );
		$onboarding->complete_checklist_item( $post_id, 'coc_accepted' );
		$onboarding->complete_checklist_item( $post_id, 'first_event_planned' );
		$onboarding->complete_checklist_item( $post_id, 'venue_confirmed' );

		$this->assertSame( 'meetup-scheduling', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::auto_transition_to_scheduling
	 */
	public function test_auto_transition_skips_non_orientation_posts(): void {
		$post_id = $this->create_meetup_post( 'meetup-active' );

		$this->onboarding->auto_transition_to_scheduling( $post_id );

		$this->assertSame( 'meetup-active', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::get_checklist_items
	 */
	public function test_get_checklist_items_returns_all_keys(): void {
		$items = Organizer_Onboarding::get_checklist_items();

		$this->assertArrayHasKey( 'profile_complete', $items );
		$this->assertArrayHasKey( 'coc_accepted', $items );
		$this->assertArrayHasKey( 'first_event_planned', $items );
		$this->assertArrayHasKey( 'venue_confirmed', $items );
	}
}
