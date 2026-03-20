<?php
/**
 * Tests for the Organizer_Onboarding workflow.
 *
 * @package Groups\Tests
 */

use Groups\Workflow\Organizer_Onboarding;
use Groups\Workflow\Application_Workflow;

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
	 * Workflow instance for transitions.
	 *
	 * @var Application_Workflow
	 */
	private Application_Workflow $workflow;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

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

		$this->workflow   = new Application_Workflow();
		$this->onboarding = new Organizer_Onboarding();
	}

	/**
	 * Helper to create a wp_meetup post with a given status.
	 *
	 * @param string $status Initial post status.
	 * @return int Post ID.
	 */
	private function create_meetup_post( string $status = 'meetup-orientation' ): int {
		// Remove hooks during creation to avoid triggering validation.
		remove_action( 'transition_post_status', [ $this->workflow, 'validate_transition' ], 5 );
		remove_action( 'transition_post_status', [ $this->workflow, 'handle_transition' ], 10 );

		$post_id = wp_insert_post( [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Test Meetup Group',
			'post_status' => $status,
		] );

		add_action( 'transition_post_status', [ $this->workflow, 'validate_transition' ], 5, 3 );
		add_action( 'transition_post_status', [ $this->workflow, 'handle_transition' ], 10, 3 );

		return $post_id;
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_stores_user_id(): void {
		$post_id = $this->create_meetup_post();
		$user_id = self::factory()->user->create();

		$result = Organizer_Onboarding::assign_mentor( $post_id, $user_id );

		$this->assertTrue( $result );
		$this->assertSame( $user_id, Organizer_Onboarding::get_mentor( $post_id ) );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_rejects_non_meetup_post(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );
		$user_id = self::factory()->user->create();

		$result = Organizer_Onboarding::assign_mentor( $post_id, $user_id );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_rejects_invalid_user(): void {
		$post_id = $this->create_meetup_post();

		$result = Organizer_Onboarding::assign_mentor( $post_id, 999999 );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::assign_mentor
	 */
	public function test_assign_mentor_fires_action(): void {
		$post_id = $this->create_meetup_post();
		$user_id = self::factory()->user->create();

		$fired     = false;
		$callback  = function () use ( &$fired ) {
			$fired = true;
		};

		add_action( 'groups_mentor_assigned', $callback, 10, 2 );

		Organizer_Onboarding::assign_mentor( $post_id, $user_id );

		remove_action( 'groups_mentor_assigned', $callback, 10 );

		$this->assertTrue( $fired );
	}

	/**
	 * @covers ::get_mentor
	 */
	public function test_get_mentor_returns_null_when_none_assigned(): void {
		$post_id = $this->create_meetup_post();

		$this->assertNull( Organizer_Onboarding::get_mentor( $post_id ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 * @covers ::is_item_complete
	 */
	public function test_complete_checklist_item(): void {
		$post_id = $this->create_meetup_post();

		$result = Organizer_Onboarding::complete_checklist_item( $post_id, 'profile_complete' );

		$this->assertTrue( $result );
		$this->assertTrue( Organizer_Onboarding::is_item_complete( $post_id, 'profile_complete' ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_rejects_invalid_item(): void {
		$post_id = $this->create_meetup_post();

		$result = Organizer_Onboarding::complete_checklist_item( $post_id, 'nonexistent_item' );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_rejects_non_meetup_post(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$result = Organizer_Onboarding::complete_checklist_item( $post_id, 'profile_complete' );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_complete_checklist_item_fires_action(): void {
		$post_id = $this->create_meetup_post();

		$fired    = false;
		$callback = function () use ( &$fired ) {
			$fired = true;
		};

		add_action( 'groups_checklist_item_completed', $callback, 10, 2 );

		Organizer_Onboarding::complete_checklist_item( $post_id, 'coc_accepted' );

		remove_action( 'groups_checklist_item_completed', $callback, 10 );

		$this->assertTrue( $fired );
	}

	/**
	 * @covers ::uncomplete_checklist_item
	 */
	public function test_uncomplete_checklist_item(): void {
		$post_id = $this->create_meetup_post();

		Organizer_Onboarding::complete_checklist_item( $post_id, 'profile_complete' );
		$this->assertTrue( Organizer_Onboarding::is_item_complete( $post_id, 'profile_complete' ) );

		Organizer_Onboarding::uncomplete_checklist_item( $post_id, 'profile_complete' );
		$this->assertFalse( Organizer_Onboarding::is_item_complete( $post_id, 'profile_complete' ) );
	}

	/**
	 * @covers ::uncomplete_checklist_item
	 */
	public function test_uncomplete_rejects_invalid_item(): void {
		$post_id = $this->create_meetup_post();

		$result = Organizer_Onboarding::uncomplete_checklist_item( $post_id, 'invalid' );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::get_checklist_status
	 */
	public function test_get_checklist_status_returns_all_items(): void {
		$post_id = $this->create_meetup_post();

		$status = Organizer_Onboarding::get_checklist_status( $post_id );

		$this->assertCount( 4, $status );
		$this->assertArrayHasKey( 'profile_complete', $status );
		$this->assertArrayHasKey( 'coc_accepted', $status );
		$this->assertArrayHasKey( 'first_event_planned', $status );
		$this->assertArrayHasKey( 'venue_confirmed', $status );

		// All should be false initially.
		foreach ( $status as $complete ) {
			$this->assertFalse( $complete );
		}
	}

	/**
	 * @covers ::get_checklist_status
	 */
	public function test_get_checklist_status_reflects_completed_items(): void {
		$post_id = $this->create_meetup_post();

		Organizer_Onboarding::complete_checklist_item( $post_id, 'profile_complete' );
		Organizer_Onboarding::complete_checklist_item( $post_id, 'coc_accepted' );

		$status = Organizer_Onboarding::get_checklist_status( $post_id );

		$this->assertTrue( $status['profile_complete'] );
		$this->assertTrue( $status['coc_accepted'] );
		$this->assertFalse( $status['first_event_planned'] );
		$this->assertFalse( $status['venue_confirmed'] );
	}

	/**
	 * @covers ::is_checklist_complete
	 */
	public function test_is_checklist_complete_false_when_incomplete(): void {
		$post_id = $this->create_meetup_post();

		Organizer_Onboarding::complete_checklist_item( $post_id, 'profile_complete' );
		Organizer_Onboarding::complete_checklist_item( $post_id, 'coc_accepted' );

		$this->assertFalse( Organizer_Onboarding::is_checklist_complete( $post_id ) );
	}

	/**
	 * @covers ::is_checklist_complete
	 */
	public function test_is_checklist_complete_true_when_all_done(): void {
		$post_id = $this->create_meetup_post();

		foreach ( Organizer_Onboarding::CHECKLIST_ITEMS as $item ) {
			update_post_meta( $post_id, '_meetup_checklist_' . $item, 1 );
		}

		$this->assertTrue( Organizer_Onboarding::is_checklist_complete( $post_id ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_auto_transition_on_checklist_complete(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		// Complete all items except the last.
		$items = Organizer_Onboarding::CHECKLIST_ITEMS;
		$last  = array_pop( $items );

		foreach ( $items as $item ) {
			// Set meta directly to avoid triggering auto-transition prematurely.
			update_post_meta( $post_id, '_meetup_checklist_' . $item, 1 );
		}

		// Completing the last item should trigger auto-transition.
		Organizer_Onboarding::complete_checklist_item( $post_id, $last );

		$this->assertSame( 'meetup-scheduling', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_auto_transition_does_not_happen_when_not_in_orientation(): void {
		$post_id = $this->create_meetup_post( 'meetup-active' );

		foreach ( Organizer_Onboarding::CHECKLIST_ITEMS as $item ) {
			update_post_meta( $post_id, '_meetup_checklist_' . $item, 1 );
		}

		// Even though checklist is complete, should not transition from active.
		$this->assertSame( 'meetup-active', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::complete_checklist_item
	 */
	public function test_auto_transition_can_be_filtered_off(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$callback = function () {
			return false;
		};
		add_filter( 'groups_auto_transition_on_checklist_complete', $callback );

		// Complete all items.
		$items = Organizer_Onboarding::CHECKLIST_ITEMS;
		$last  = array_pop( $items );

		foreach ( $items as $item ) {
			update_post_meta( $post_id, '_meetup_checklist_' . $item, 1 );
		}

		Organizer_Onboarding::complete_checklist_item( $post_id, $last );

		remove_filter( 'groups_auto_transition_on_checklist_complete', $callback );

		// Should remain in orientation because filter returned false.
		$this->assertSame( 'meetup-orientation', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::on_orientation_entry
	 */
	public function test_checklist_resets_on_orientation_entry(): void {
		$post_id = $this->create_meetup_post( 'meetup-vetting' );

		// Set some checklist items.
		update_post_meta( $post_id, '_meetup_checklist_profile_complete', 1 );
		update_post_meta( $post_id, '_meetup_checklist_coc_accepted', 1 );

		// Transition to orientation.
		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-orientation',
		] );

		// Checklist should be reset.
		$status = Organizer_Onboarding::get_checklist_status( $post_id );

		foreach ( $status as $complete ) {
			$this->assertFalse( $complete );
		}
	}

	/**
	 * @covers ::on_orientation_entry
	 */
	public function test_orientation_started_action_fires(): void {
		$post_id = $this->create_meetup_post( 'meetup-vetting' );

		$fired    = false;
		$callback = function () use ( &$fired ) {
			$fired = true;
		};

		add_action( 'groups_orientation_started', $callback );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-orientation',
		] );

		remove_action( 'groups_orientation_started', $callback );

		$this->assertTrue( $fired );
	}

	/**
	 * @covers ::get_checklist_labels
	 */
	public function test_get_checklist_labels_returns_all_items(): void {
		$labels = Organizer_Onboarding::get_checklist_labels();

		$this->assertCount( 4, $labels );
		$this->assertArrayHasKey( 'profile_complete', $labels );
		$this->assertArrayHasKey( 'coc_accepted', $labels );
		$this->assertArrayHasKey( 'first_event_planned', $labels );
		$this->assertArrayHasKey( 'venue_confirmed', $labels );

		foreach ( $labels as $label ) {
			$this->assertIsString( $label );
			$this->assertNotEmpty( $label );
		}
	}

	/**
	 * @covers ::is_item_complete
	 */
	public function test_is_item_complete_returns_false_for_invalid_item(): void {
		$post_id = $this->create_meetup_post();

		$this->assertFalse( Organizer_Onboarding::is_item_complete( $post_id, 'invalid_item' ) );
	}

	/**
	 * Test the CHECKLIST_ITEMS constant has the expected four items.
	 */
	public function test_checklist_has_four_items(): void {
		$this->assertCount( 4, Organizer_Onboarding::CHECKLIST_ITEMS );
		$this->assertContains( 'profile_complete', Organizer_Onboarding::CHECKLIST_ITEMS );
		$this->assertContains( 'coc_accepted', Organizer_Onboarding::CHECKLIST_ITEMS );
		$this->assertContains( 'first_event_planned', Organizer_Onboarding::CHECKLIST_ITEMS );
		$this->assertContains( 'venue_confirmed', Organizer_Onboarding::CHECKLIST_ITEMS );
	}
}
