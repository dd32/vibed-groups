<?php
/**
 * Tests for the Dormancy Detector.
 *
 * @package Groups\Tests
 */

use Groups\Analytics\Dormancy_Detector;

/**
 * @coversDefaultClass \Groups\Analytics\Dormancy_Detector
 */
class Test_Dormancy_Detector extends WP_UnitTestCase {

	/**
	 * Register the wp_meetup post type and statuses for tests.
	 */
	public function set_up(): void {
		parent::set_up();

		// Register the wp_meetup post type if not already registered.
		if ( ! post_type_exists( 'wp_meetup' ) ) {
			register_post_type( 'wp_meetup', [
				'public' => false,
			] );
		}

		// Register custom statuses used by the workflow.
		foreach ( [ 'meetup-active', 'meetup-dormant' ] as $status ) {
			if ( ! get_post_status_object( $status ) ) {
				register_post_status( $status, [
					'label'                     => ucfirst( str_replace( 'meetup-', '', $status ) ),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
				] );
			}
		}
	}

	/**
	 * Helper: create a wp_meetup post with a given status and last event date.
	 *
	 * @param string      $status          Post status.
	 * @param string|null $last_event_date Last event date (Y-m-d H:i:s format).
	 * @param string|null $deputy_username Deputy username.
	 * @return int Post ID.
	 */
	private function create_group( string $status = 'meetup-active', ?string $last_event_date = null, ?string $deputy_username = null ): int {
		$post_id = self::factory()->post->create( [
			'post_type'   => 'wp_meetup',
			'post_status' => $status,
			'post_title'  => 'Test WordPress Group',
		] );

		if ( $last_event_date ) {
			update_post_meta( $post_id, Dormancy_Detector::LAST_EVENT_META, $last_event_date );
		}

		if ( $deputy_username ) {
			update_post_meta( $post_id, Dormancy_Detector::DEPUTY_META, $deputy_username );
		}

		return $post_id;
	}

	/**
	 * @covers ::check_group
	 */
	public function test_recent_event_not_flagged(): void {
		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) );
		$post_id    = $this->create_group( 'meetup-active', $last_event );

		$result = Dormancy_Detector::check_group( $post_id );

		$this->assertNull( $result, 'Group with recent event should not be flagged.' );
		$this->assertEmpty(
			get_post_meta( $post_id, Dormancy_Detector::AT_RISK_META, true ),
			'At-risk meta should not be set for active group.'
		);
	}

	/**
	 * @covers ::check_group
	 */
	public function test_60_days_inactive_gets_at_risk(): void {
		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-65 days' ) );
		$post_id    = $this->create_group( 'meetup-active', $last_event );

		$result = Dormancy_Detector::check_group( $post_id );

		$this->assertSame( 'at_risk', $result );
		$this->assertEquals( 1, get_post_meta( $post_id, Dormancy_Detector::AT_RISK_META, true ) );

		// Post status should still be active.
		$post = get_post( $post_id );
		$this->assertSame( 'meetup-active', $post->post_status );
	}

	/**
	 * @covers ::check_group
	 */
	public function test_90_days_inactive_transitions_to_dormant(): void {
		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-95 days' ) );
		$post_id    = $this->create_group( 'meetup-active', $last_event );

		$result = Dormancy_Detector::check_group( $post_id );

		$this->assertSame( 'dormant', $result );

		// Post status should now be dormant.
		$post = get_post( $post_id );
		$this->assertSame( 'meetup-dormant', $post->post_status );

		// At-risk flag should be cleared.
		$this->assertEmpty(
			get_post_meta( $post_id, Dormancy_Detector::AT_RISK_META, true ),
			'At-risk meta should be cleared when group becomes dormant.'
		);
	}

	/**
	 * @covers ::check_group
	 */
	public function test_already_dormant_group_not_reprocessed(): void {
		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-120 days' ) );
		$post_id    = $this->create_group( 'meetup-dormant', $last_event );

		$result = Dormancy_Detector::check_group( $post_id );

		$this->assertNull( $result, 'Already dormant group should not be re-processed.' );
	}

	/**
	 * @covers ::check_group
	 */
	public function test_at_risk_action_fires(): void {
		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-65 days' ) );
		$post_id    = $this->create_group( 'meetup-active', $last_event );
		$fired      = false;

		add_action(
			'groups_group_at_risk',
			function ( $id, $days ) use ( $post_id, &$fired ) {
				$fired = true;
				$this->assertSame( $post_id, $id );
				$this->assertGreaterThanOrEqual( 60, $days );
			},
			10,
			2
		);

		Dormancy_Detector::check_group( $post_id );

		$this->assertTrue( $fired, 'The groups_group_at_risk action should have fired.' );
	}

	/**
	 * @covers ::check_group
	 */
	public function test_dormant_action_fires(): void {
		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-95 days' ) );
		$post_id    = $this->create_group( 'meetup-active', $last_event );
		$fired      = false;

		add_action(
			'groups_group_dormant',
			function ( $id, $days ) use ( $post_id, &$fired ) {
				$fired = true;
				$this->assertSame( $post_id, $id );
				$this->assertGreaterThanOrEqual( 90, $days );
			},
			10,
			2
		);

		Dormancy_Detector::check_group( $post_id );

		$this->assertTrue( $fired, 'The groups_group_dormant action should have fired.' );
	}

	/**
	 * @covers ::check_group
	 */
	public function test_at_risk_flag_cleared_when_event_recent(): void {
		$post_id = $this->create_group( 'meetup-active', gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ) );

		// Simulate a previously set at-risk flag.
		update_post_meta( $post_id, Dormancy_Detector::AT_RISK_META, 1 );

		$result = Dormancy_Detector::check_group( $post_id );

		$this->assertNull( $result );
		$this->assertEmpty(
			get_post_meta( $post_id, Dormancy_Detector::AT_RISK_META, true ),
			'At-risk meta should be cleared when group has recent events.'
		);
	}

	/**
	 * @covers ::run
	 */
	public function test_run_processes_active_groups(): void {
		$active_recent  = $this->create_group( 'meetup-active', gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ) );
		$active_at_risk = $this->create_group( 'meetup-active', gmdate( 'Y-m-d H:i:s', strtotime( '-70 days' ) ) );
		$dormant_group  = $this->create_group( 'meetup-dormant', gmdate( 'Y-m-d H:i:s', strtotime( '-120 days' ) ) );

		$detector = new Dormancy_Detector();
		$detector->run();

		// Active recent group should not be flagged.
		$this->assertEmpty( get_post_meta( $active_recent, Dormancy_Detector::AT_RISK_META, true ) );

		// Active at-risk group should be flagged.
		$this->assertEquals( 1, get_post_meta( $active_at_risk, Dormancy_Detector::AT_RISK_META, true ) );

		// Already dormant group should remain unchanged.
		$this->assertSame( 'meetup-dormant', get_post( $dormant_group )->post_status );
	}

	/**
	 * @covers ::schedule_cron
	 * @covers ::unschedule_cron
	 */
	public function test_cron_scheduling(): void {
		// Unschedule first in case it was already scheduled.
		Dormancy_Detector::unschedule_cron();
		$this->assertFalse( wp_next_scheduled( Dormancy_Detector::CRON_HOOK ) );

		Dormancy_Detector::schedule_cron();
		$this->assertNotFalse( wp_next_scheduled( Dormancy_Detector::CRON_HOOK ) );

		// Scheduling again should not create a duplicate.
		$first_timestamp = wp_next_scheduled( Dormancy_Detector::CRON_HOOK );
		Dormancy_Detector::schedule_cron();
		$this->assertSame( $first_timestamp, wp_next_scheduled( Dormancy_Detector::CRON_HOOK ) );

		// Cleanup.
		Dormancy_Detector::unschedule_cron();
		$this->assertFalse( wp_next_scheduled( Dormancy_Detector::CRON_HOOK ) );
	}

	/**
	 * @covers ::check_group
	 */
	public function test_deputy_email_sent_on_at_risk(): void {
		// Create a deputy user.
		$deputy_id = self::factory()->user->create( [
			'user_login' => 'testdeputy',
			'user_email' => 'deputy@example.org',
		] );

		$last_event = gmdate( 'Y-m-d H:i:s', strtotime( '-65 days' ) );
		$post_id    = $this->create_group( 'meetup-active', $last_event, 'testdeputy' );

		// Capture wp_mail calls.
		$emails = [];
		add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$emails ) {
			$emails[] = $atts;
			return true; // Short-circuit actual sending.
		}, 10, 2 );

		Dormancy_Detector::check_group( $post_id );

		$this->assertNotEmpty( $emails, 'A dormancy alert email should have been sent.' );
		$this->assertSame( 'deputy@example.org', $emails[0]['to'] );
		$this->assertStringContainsString( 'Dormancy Alert', $emails[0]['subject'] );
	}
}
