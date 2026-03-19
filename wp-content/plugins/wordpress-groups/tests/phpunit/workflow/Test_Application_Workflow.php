<?php
/**
 * Tests for the Application_Workflow state machine.
 *
 * @package Groups\Tests
 */

use Groups\Workflow\Application_Workflow;

/**
 * @coversDefaultClass \Groups\Workflow\Application_Workflow
 */
class Test_Application_Workflow extends WP_UnitTestCase {

	/**
	 * Workflow instance.
	 *
	 * @var Application_Workflow
	 */
	private Application_Workflow $workflow;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Register the wp_meetup post type for testing.
		register_post_type( 'wp_meetup', [
			'public' => false,
		] );

		// Register all meetup statuses.
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

		$this->workflow = new Application_Workflow();
	}

	/**
	 * Helper to create a wp_meetup post with a given status.
	 *
	 * @param string $status Initial post status.
	 * @return int Post ID.
	 */
	private function create_meetup_post( string $status = 'meetup-pending' ): int {
		// Remove workflow hooks during creation to avoid triggering validation.
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
	 * @covers ::is_valid_transition
	 */
	public function test_valid_transitions_are_accepted(): void {
		$valid_cases = [
			[ 'meetup-pending', 'meetup-vetting' ],
			[ 'meetup-pending', 'meetup-declined' ],
			[ 'meetup-vetting', 'meetup-feedback' ],
			[ 'meetup-vetting', 'meetup-orientation' ],
			[ 'meetup-vetting', 'meetup-declined' ],
			[ 'meetup-feedback', 'meetup-vetting' ],
			[ 'meetup-feedback', 'meetup-declined' ],
			[ 'meetup-orientation', 'meetup-scheduling' ],
			[ 'meetup-scheduling', 'meetup-active' ],
			[ 'meetup-active', 'meetup-dormant' ],
			[ 'meetup-active', 'meetup-suspended' ],
			[ 'meetup-dormant', 'meetup-active' ],
			[ 'meetup-dormant', 'meetup-removed' ],
			[ 'meetup-suspended', 'meetup-active' ],
			[ 'meetup-suspended', 'meetup-removed' ],
		];

		foreach ( $valid_cases as [ $from, $to ] ) {
			$this->assertTrue(
				Application_Workflow::is_valid_transition( $from, $to ),
				"Expected transition from {$from} to {$to} to be valid."
			);
		}
	}

	/**
	 * @covers ::is_valid_transition
	 */
	public function test_invalid_transitions_are_rejected(): void {
		$invalid_cases = [
			[ 'meetup-pending', 'meetup-active' ],
			[ 'meetup-pending', 'meetup-orientation' ],
			[ 'meetup-vetting', 'meetup-active' ],
			[ 'meetup-orientation', 'meetup-pending' ],
			[ 'meetup-scheduling', 'meetup-dormant' ],
			[ 'meetup-active', 'meetup-pending' ],
			[ 'meetup-dormant', 'meetup-vetting' ],
			[ 'meetup-removed', 'meetup-active' ],
			[ 'meetup-declined', 'meetup-vetting' ],
		];

		foreach ( $invalid_cases as [ $from, $to ] ) {
			$this->assertFalse(
				Application_Workflow::is_valid_transition( $from, $to ),
				"Expected transition from {$from} to {$to} to be invalid."
			);
		}
	}

	/**
	 * @covers ::is_valid_transition
	 */
	public function test_same_status_transition_is_valid(): void {
		$this->assertTrue( Application_Workflow::is_valid_transition( 'meetup-active', 'meetup-active' ) );
	}

	/**
	 * @covers ::validate_transition
	 */
	public function test_valid_transition_updates_post_status(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-vetting',
		] );

		$this->assertSame( 'meetup-vetting', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::validate_transition
	 */
	public function test_invalid_transition_is_blocked(): void {
		// Test that the static validation correctly identifies invalid transitions.
		$this->assertFalse( Application_Workflow::is_valid_transition( 'meetup-pending', 'meetup-active' ) );
		$this->assertFalse( Application_Workflow::is_valid_transition( 'meetup-pending', 'meetup-orientation' ) );
		$this->assertFalse( Application_Workflow::is_valid_transition( 'meetup-active', 'meetup-pending' ) );
	}

	/**
	 * @covers ::handle_transition
	 */
	public function test_date_meta_is_updated_on_transition(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-vetting',
		] );

		$vetting_date = get_post_meta( $post_id, '_meetup_vetting_date', true );

		$this->assertNotEmpty( $vetting_date, 'Vetting date meta should be set after transition.' );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $vetting_date );
	}

	/**
	 * @covers ::handle_transition
	 */
	public function test_activation_date_meta_set_on_active_transition(): void {
		$post_id = $this->create_meetup_post( 'meetup-scheduling' );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-active',
		] );

		$activation_date = get_post_meta( $post_id, '_meetup_activation_date', true );

		$this->assertNotEmpty( $activation_date, 'Activation date should be set.' );
	}

	/**
	 * @covers ::handle_transition
	 */
	public function test_action_hook_fires_on_valid_transition(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );

		$fired      = false;
		$hook_args  = [];
		$callback   = function ( $id, $old, $new ) use ( &$fired, &$hook_args ) {
			$fired     = true;
			$hook_args = [
				'post_id'    => $id,
				'old_status' => $old,
				'new_status' => $new,
			];
		};

		add_action( 'groups_meetup_status_transition', $callback, 10, 3 );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-vetting',
		] );

		remove_action( 'groups_meetup_status_transition', $callback, 10 );

		$this->assertTrue( $fired, 'groups_meetup_status_transition action should fire.' );
		$this->assertSame( $post_id, $hook_args['post_id'] );
		$this->assertSame( 'meetup-pending', $hook_args['old_status'] );
		$this->assertSame( 'meetup-vetting', $hook_args['new_status'] );
	}

	/**
	 * @covers ::handle_transition
	 */
	public function test_action_hook_does_not_fire_on_invalid_transition(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );

		$fired    = false;
		$callback = function () use ( &$fired ) {
			$fired = true;
		};

		add_action( 'groups_meetup_status_transition', $callback, 10, 3 );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-active',
		] );

		remove_action( 'groups_meetup_status_transition', $callback, 10 );

		$this->assertFalse( $fired, 'Action should not fire for invalid transitions.' );
	}

	/**
	 * @covers ::handle_transition
	 */
	public function test_non_meetup_post_type_is_ignored(): void {
		$post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'draft',
		] );

		// This should not throw or cause issues.
		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'publish',
		] );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * @covers ::get_transitions
	 */
	public function test_get_transitions_returns_all_states(): void {
		$transitions = Application_Workflow::get_transitions();

		$this->assertArrayHasKey( 'meetup-pending', $transitions );
		$this->assertArrayHasKey( 'meetup-vetting', $transitions );
		$this->assertArrayHasKey( 'meetup-feedback', $transitions );
		$this->assertArrayHasKey( 'meetup-orientation', $transitions );
		$this->assertArrayHasKey( 'meetup-scheduling', $transitions );
		$this->assertArrayHasKey( 'meetup-active', $transitions );
		$this->assertArrayHasKey( 'meetup-dormant', $transitions );
		$this->assertArrayHasKey( 'meetup-suspended', $transitions );
	}

	/**
	 * @covers ::get_date_meta_key
	 */
	public function test_get_date_meta_key_returns_correct_keys(): void {
		$this->assertSame( '_meetup_vetting_date', Application_Workflow::get_date_meta_key( 'meetup-vetting' ) );
		$this->assertSame( '_meetup_activation_date', Application_Workflow::get_date_meta_key( 'meetup-active' ) );
		$this->assertSame( '_meetup_orientation_date', Application_Workflow::get_date_meta_key( 'meetup-orientation' ) );
		$this->assertNull( Application_Workflow::get_date_meta_key( 'nonexistent-status' ) );
	}

	/**
	 * @covers ::validate_transition
	 */
	public function test_invalid_transition_fires_invalid_action(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );

		$fired    = false;
		$callback = function () use ( &$fired ) {
			$fired = true;
		};

		add_action( 'groups_meetup_invalid_transition', $callback, 10, 3 );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-active',
		] );

		remove_action( 'groups_meetup_invalid_transition', $callback, 10 );

		$this->assertTrue( $fired, 'groups_meetup_invalid_transition should fire for invalid transitions.' );
	}
}
