<?php
/**
 * Daily aggregation cron — summarizes data into groups_analytics_daily.
 *
 * @package Groups\Analytics
 */

namespace Groups\Analytics;

use Groups\Database\Analytics_Table;
use Groups\Database\Activity_Log_Table;
use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Iterates all active wp_meetup groups on a daily cron schedule,
 * collecting per-group metrics and writing them to the
 * groups_analytics_daily table via Analytics_Table::insert().
 *
 * Idempotent: re-running for the same date updates existing rows
 * thanks to INSERT ON DUPLICATE KEY UPDATE in Analytics_Table.
 */
class Aggregator {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'groups_daily_aggregation';

	/**
	 * Option key for tracking batch progress across cron runs.
	 *
	 * @var string
	 */
	const BATCH_OPTION = 'groups_aggregation_batch_offset';

	/**
	 * Number of groups to process per cron run.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Constructor. Registers the cron action hook.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_daily' ] );
	}

	/**
	 * Schedule the daily cron event if not already scheduled.
	 *
	 * Should be called during plugin initialisation.
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 *
	 * Called on plugin deactivation.
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Daily cron callback — aggregates for yesterday's date.
	 */
	public static function run_daily(): void {
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		self::aggregate_for_date( $yesterday );
	}

	/**
	 * Aggregate metrics for a batch of active groups on a given date.
	 *
	 * Processes up to BATCH_SIZE groups starting from the stored offset.
	 * When the full set has been processed, the offset is reset. Otherwise,
	 * a follow-up single event is scheduled to continue processing.
	 *
	 * This is the main public entry point, also usable for backfilling.
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	public static function aggregate_for_date( string $date ): void {
		$batch_state = get_option( self::BATCH_OPTION, [] );
		$offset      = 0;

		// If we have stored state for this date, resume from where we left off.
		if ( ! empty( $batch_state['date'] ) && $batch_state['date'] === $date ) {
			$offset = (int) ( $batch_state['offset'] ?? 0 );
		}

		$posts = get_posts( [
			'post_type'      => 'wp_meetup',
			'post_status'    => 'meetup-active',
			'posts_per_page' => self::BATCH_SIZE,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		foreach ( $posts as $post_id ) {
			self::aggregate_group( (int) $post_id, $date );
		}

		if ( count( $posts ) < self::BATCH_SIZE ) {
			// All groups processed -- reset for the next day.
			delete_option( self::BATCH_OPTION );
		} else {
			// More groups remain -- save progress and schedule continuation.
			update_option( self::BATCH_OPTION, [
				'date'   => $date,
				'offset' => $offset + self::BATCH_SIZE,
			], false );

			// Schedule a follow-up run if not already scheduled.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 30, self::CRON_HOOK );
			}
		}
	}

	/**
	 * Aggregate metrics for a single group on a given date.
	 *
	 * Switches to the group's blog, collects metrics, writes to
	 * the analytics daily table, then restores the original blog.
	 *
	 * @param int    $post_id The wp_meetup post ID on the central tracker.
	 * @param string $date    Date in Y-m-d format.
	 */
	public static function aggregate_group( int $post_id, string $date ): void {
		$site_id = get_post_meta( $post_id, '_meetup_site_id', true );

		if ( ! $site_id ) {
			return;
		}

		$site_id = (int) $site_id;
		$metrics = self::collect_metrics( $site_id, $date );

		foreach ( $metrics as $metric => $value ) {
			Analytics_Table::insert( $date, $site_id, $metric, $value );
		}
	}

	/**
	 * Collect all metrics for a group site on a given date.
	 *
	 * @param int    $blog_id The blog ID of the group site.
	 * @param string $date    Date in Y-m-d format.
	 * @return array<string, int> Associative array of metric => value.
	 */
	public static function collect_metrics( int $blog_id, string $date ): array {
		$metrics = [];

		// Switch to the group's blog for per-site queries.
		switch_to_blog( $blog_id );

		$metrics['members']    = self::count_members();
		$metrics['events_held'] = self::count_events_held( $date );

		$rsvp_data = self::count_rsvp_metrics( $date );

		$metrics['rsvps_total']    = $rsvp_data['rsvps_total'];
		$metrics['attendees']      = $rsvp_data['attendees'];
		$metrics['newcomers']      = $rsvp_data['newcomers'];
		$metrics['no_shows']       = $rsvp_data['no_shows'];

		restore_current_blog();

		// Activity log queries are network-wide, no blog switch needed.
		$metrics['members_joined']  = self::count_activity( $blog_id, $date, 'member_joined' );
		$metrics['members_left']    = self::count_activity( $blog_id, $date, 'member_left' );
		$metrics['events_created']  = self::count_activity( $blog_id, $date, 'event_created' );

		return $metrics;
	}

	/**
	 * Count current members on the switched-to blog.
	 *
	 * @return int
	 */
	private static function count_members(): int {
		$users = get_users( [
			'blog_id' => get_current_blog_id(),
			'fields'  => 'ID',
		] );

		return count( $users );
	}

	/**
	 * Count events with event-past status held on the given date.
	 *
	 * Uses the _event_start_utc meta to determine the event date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return int
	 */
	private static function count_events_held( string $date ): int {
		$events = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => 'event-past',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_event_start_utc',
					'value'   => [ $date . ' 00:00:00', $date . ' 23:59:59' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			],
		] );

		return count( $events );
	}

	/**
	 * Count RSVP-related metrics for events held on the given date.
	 *
	 * Iterates events held on the date and examines RSVP comments
	 * (stored as comments with meta) on each event.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array{rsvps_total: int, attendees: int, newcomers: int, no_shows: int}
	 */
	private static function count_rsvp_metrics( string $date ): array {
		$result = [
			'rsvps_total' => 0,
			'attendees'   => 0,
			'newcomers'   => 0,
			'no_shows'    => 0,
		];

		$events = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => 'event-past',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_event_start_utc',
					'value'   => [ $date . ' 00:00:00', $date . ' 23:59:59' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			],
		] );

		foreach ( $events as $event_id ) {
			$comments = get_comments( [
				'post_id' => $event_id,
				'status'  => 'approve',
				'number'  => 0, // No limit.
			] );

			foreach ( $comments as $comment ) {
				$rsvp_status = get_comment_meta( $comment->comment_ID, '_rsvp_status', true );

				if ( ! $rsvp_status ) {
					continue;
				}

				$result['rsvps_total']++;

				if ( 'attending' === $rsvp_status ) {
					$result['attendees']++;

					if ( get_comment_meta( $comment->comment_ID, 'is_first_event', true ) ) {
						$result['newcomers']++;
					}
				}

				if ( 'no_show' === $rsvp_status ) {
					$result['no_shows']++;
				}
			}
		}

		return $result;
	}

	/**
	 * Count activity log entries for a given blog, date, and action.
	 *
	 * @param int    $blog_id Blog ID.
	 * @param string $date    Date in Y-m-d format.
	 * @param string $action  Action name (e.g. 'member_joined').
	 * @return int
	 */
	private static function count_activity( int $blog_id, string $date, string $action ): int {
		$entries = Activity_Log_Table::query( [
			'blog_id'   => $blog_id,
			'action'    => $action,
			'date_from' => $date,
			'date_to'   => $date,
			'limit'     => 10000,
		] );

		return count( $entries );
	}
}
