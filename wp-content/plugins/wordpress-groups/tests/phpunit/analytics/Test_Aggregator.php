<?php
/**
 * Tests for the daily aggregation cron.
 *
 * @package Groups\Tests
 */

use Groups\Analytics\Aggregator;
use Groups\Database\Analytics_Table;
use Groups\Database\Activity_Log_Table;

/**
 * @coversDefaultClass \Groups\Analytics\Aggregator
 */
class Test_Aggregator extends WP_UnitTestCase {

	/**
	 * The blog ID of the test group site.
	 *
	 * @var int
	 */
	private int $group_blog_id;

	/**
	 * The wp_meetup post ID on the central tracker.
	 *
	 * @var int
	 */
	private int $meetup_post_id;

	/**
	 * The date to aggregate for.
	 *
	 * @var string
	 */
	private string $test_date;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->test_date = gmdate( 'Y-m-d' );

		// Register the wp_meetup post type and statuses.
		if ( ! post_type_exists( 'wp_meetup' ) ) {
			register_post_type( 'wp_meetup', [ 'public' => false ] );
		}

		if ( ! get_post_status_object( 'meetup-active' ) ) {
			register_post_status( 'meetup-active', [
				'label'  => 'Active',
				'public' => true,
			] );
		}

		// Register event post type and status.
		if ( ! post_type_exists( 'event' ) ) {
			register_post_type( 'event', [ 'public' => true ] );
		}

		if ( ! get_post_status_object( 'event-past' ) ) {
			register_post_status( 'event-past', [
				'label'  => 'Past',
				'public' => true,
			] );
		}

		// Use the current blog as the "group site" since we cannot create
		// subsites in the standard WP unit test environment.
		$this->group_blog_id = get_current_blog_id();

		// Create the wp_meetup post linking to this blog.
		$this->meetup_post_id = self::factory()->post->create( [
			'post_type'   => 'wp_meetup',
			'post_status' => 'meetup-active',
			'post_title'  => 'Test Group',
		] );

		update_post_meta( $this->meetup_post_id, '_meetup_site_id', $this->group_blog_id );
	}

	/**
	 * @covers ::schedule_cron
	 * @covers ::unschedule_cron
	 */
	public function test_cron_scheduling(): void {
		Aggregator::unschedule_cron();
		$this->assertFalse( wp_next_scheduled( Aggregator::CRON_HOOK ) );

		Aggregator::schedule_cron();
		$this->assertNotFalse( wp_next_scheduled( Aggregator::CRON_HOOK ) );

		// Scheduling again should not create a duplicate.
		$first = wp_next_scheduled( Aggregator::CRON_HOOK );
		Aggregator::schedule_cron();
		$this->assertSame( $first, wp_next_scheduled( Aggregator::CRON_HOOK ) );

		Aggregator::unschedule_cron();
		$this->assertFalse( wp_next_scheduled( Aggregator::CRON_HOOK ) );
	}

	/**
	 * @covers ::aggregate_for_date
	 * @covers ::aggregate_group
	 * @covers ::collect_metrics
	 */
	public function test_aggregation_creates_metric_rows(): void {
		// Create a past event for today.
		$event_id = self::factory()->post->create( [
			'post_type'   => 'event',
			'post_status' => 'event-past',
			'post_title'  => 'Test Event',
		] );

		update_post_meta( $event_id, '_event_start_utc', $this->test_date . ' 18:00:00' );

		// Create an RSVP comment on the event.
		$comment_id = self::factory()->comment->create( [
			'comment_post_ID'  => $event_id,
			'comment_approved' => 1,
			'user_id'          => 1,
		] );

		update_comment_meta( $comment_id, 'rsvp_status', 'attending' );

		// Create a user on this blog so we have at least 1 member.
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		Aggregator::aggregate_for_date( $this->test_date );

		// Query all rows for this blog and date.
		$rows = Analytics_Table::query( [
			'blog_id'   => $this->group_blog_id,
			'date_from' => $this->test_date,
			'date_to'   => $this->test_date,
		] );

		// We expect rows for all 9 metrics.
		$metrics = wp_list_pluck( $rows, 'value', 'metric' );

		$this->assertArrayHasKey( 'members', $metrics );
		$this->assertArrayHasKey( 'events_held', $metrics );
		$this->assertArrayHasKey( 'rsvps_total', $metrics );
		$this->assertArrayHasKey( 'attendees', $metrics );
		$this->assertArrayHasKey( 'newcomers', $metrics );
		$this->assertArrayHasKey( 'no_shows', $metrics );
		$this->assertArrayHasKey( 'members_joined', $metrics );
		$this->assertArrayHasKey( 'members_left', $metrics );
		$this->assertArrayHasKey( 'events_created', $metrics );

		// Verify some specific values.
		$this->assertEquals( 1, (int) $metrics['events_held'], 'Should have 1 event held.' );
		$this->assertEquals( 1, (int) $metrics['rsvps_total'], 'Should have 1 RSVP.' );
		$this->assertEquals( 1, (int) $metrics['attendees'], 'Should have 1 attendee.' );
		$this->assertGreaterThanOrEqual( 1, (int) $metrics['members'], 'Should have at least 1 member.' );
	}

	/**
	 * @covers ::aggregate_for_date
	 */
	public function test_idempotent_rerun_updates_existing_rows(): void {
		// Create a past event.
		$event_id = self::factory()->post->create( [
			'post_type'   => 'event',
			'post_status' => 'event-past',
		] );

		update_post_meta( $event_id, '_event_start_utc', $this->test_date . ' 18:00:00' );

		// First aggregation.
		Aggregator::aggregate_for_date( $this->test_date );

		$rows_first = Analytics_Table::query( [
			'blog_id'   => $this->group_blog_id,
			'date_from' => $this->test_date,
			'date_to'   => $this->test_date,
		] );

		// Second aggregation — should not duplicate rows.
		Aggregator::aggregate_for_date( $this->test_date );

		$rows_second = Analytics_Table::query( [
			'blog_id'   => $this->group_blog_id,
			'date_from' => $this->test_date,
			'date_to'   => $this->test_date,
		] );

		$this->assertCount( count( $rows_first ), $rows_second, 'Re-running should not create duplicate rows.' );

		// Values should be identical.
		$metrics_first  = wp_list_pluck( $rows_first, 'value', 'metric' );
		$metrics_second = wp_list_pluck( $rows_second, 'value', 'metric' );

		$this->assertSame( $metrics_first, $metrics_second, 'Re-running should produce identical values.' );
	}

	/**
	 * @covers ::collect_metrics
	 */
	public function test_metrics_correct_for_known_data(): void {
		// Create 2 past events for the test date.
		for ( $i = 0; $i < 2; $i++ ) {
			$event_id = self::factory()->post->create( [
				'post_type'   => 'event',
				'post_status' => 'event-past',
			] );

			update_post_meta( $event_id, '_event_start_utc', $this->test_date . ' 18:00:00' );

			// Create RSVPs on each event.
			$attending = self::factory()->comment->create( [
				'comment_post_ID'  => $event_id,
				'comment_approved' => 1,
				'user_id'          => $i + 10,
			] );
			update_comment_meta( $attending, 'rsvp_status', 'attending' );

			// Add a newcomer on the first event.
			if ( 0 === $i ) {
				$newcomer = self::factory()->comment->create( [
					'comment_post_ID'  => $event_id,
					'comment_approved' => 1,
					'user_id'          => 100,
				] );
				update_comment_meta( $newcomer, 'rsvp_status', 'attending' );
				update_comment_meta( $newcomer, 'is_first_event', 1 );
			}

			// Add a no-show on the second event.
			if ( 1 === $i ) {
				$no_show = self::factory()->comment->create( [
					'comment_post_ID'  => $event_id,
					'comment_approved' => 1,
					'user_id'          => 200,
				] );
				update_comment_meta( $no_show, 'rsvp_status', 'no_show' );
			}
		}

		// Add activity log entries for the test date.
		Activity_Log_Table::insert( $this->group_blog_id, 1, 'member_joined', 1 );
		Activity_Log_Table::insert( $this->group_blog_id, 2, 'member_joined', 2 );
		Activity_Log_Table::insert( $this->group_blog_id, 3, 'member_left', 3 );
		Activity_Log_Table::insert( $this->group_blog_id, 4, 'event_created', 10 );

		$metrics = Aggregator::collect_metrics( $this->group_blog_id, $this->test_date );

		$this->assertSame( 2, $metrics['events_held'], 'Should count 2 past events.' );
		// 2 attending + 1 newcomer attending + 1 no_show = 4 RSVPs total.
		$this->assertSame( 4, $metrics['rsvps_total'], 'Should count 4 RSVPs total.' );
		// 2 attending + 1 newcomer attending = 3 attendees.
		$this->assertSame( 3, $metrics['attendees'], 'Should count 3 attendees.' );
		$this->assertSame( 1, $metrics['newcomers'], 'Should count 1 newcomer.' );
		$this->assertSame( 1, $metrics['no_shows'], 'Should count 1 no-show.' );
		$this->assertSame( 2, $metrics['members_joined'], 'Should count 2 members joined.' );
		$this->assertSame( 1, $metrics['members_left'], 'Should count 1 member left.' );
		$this->assertSame( 1, $metrics['events_created'], 'Should count 1 event created.' );
	}

	/**
	 * @covers ::aggregate_group
	 */
	public function test_group_without_site_id_skipped(): void {
		$post_id = self::factory()->post->create( [
			'post_type'   => 'wp_meetup',
			'post_status' => 'meetup-active',
		] );

		// No _meetup_site_id meta set.
		Aggregator::aggregate_group( $post_id, $this->test_date );

		$rows = Analytics_Table::query( [
			'date_from' => $this->test_date,
			'date_to'   => $this->test_date,
			'metric'    => 'members',
		] );

		// Filter to only rows that would have blog_id = 0 or similar.
		// Since no site_id was set, no rows should be written for this group.
		$blog_ids = wp_list_pluck( $rows, 'blog_id' );
		$this->assertNotContains( '0', $blog_ids, 'Group without site ID should not produce rows.' );
	}
}
