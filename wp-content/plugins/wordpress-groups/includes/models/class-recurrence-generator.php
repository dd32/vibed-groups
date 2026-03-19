<?php
/**
 * Recurrence generator — creates future event instances from recurring templates.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Generates recurring event instances via WP-Cron.
 *
 * Queries all events with a recurrence rule, calculates upcoming occurrences
 * up to 4 weeks ahead, and creates new event posts for each occurrence that
 * does not already exist.
 */
class Recurrence_Generator {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'groups_generate_recurring_events';

	/**
	 * Post meta key linking a generated instance back to its template event.
	 *
	 * @var string
	 */
	const TEMPLATE_META_KEY = '_event_recurrence_template_id';

	/**
	 * How many weeks ahead to generate instances.
	 *
	 * @var int
	 */
	const WEEKS_AHEAD = 4;

	/**
	 * Constructor. Registers the cron callback.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
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
	 * Run the generator for all recurring events on the current site.
	 */
	public function run(): void {
		$template_ids = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => [ 'event-draft', 'event-scheduled', 'event-active', 'publish' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => Recurrence::META_KEY,
					'compare' => 'EXISTS',
				],
			],
		] );

		foreach ( $template_ids as $template_id ) {
			self::generate_for_event( (int) $template_id );
		}
	}

	/**
	 * Generate future instances for a single recurring event template.
	 *
	 * @param int $template_event_id The template event post ID.
	 * @return int[] Array of newly created event post IDs.
	 */
	public static function generate_for_event( int $template_event_id ): array {
		$rule = Recurrence::get_rule( $template_event_id );

		if ( ! $rule ) {
			return [];
		}

		$template_post = get_post( $template_event_id );

		if ( ! $template_post || Event_Post_Type::POST_TYPE !== $template_post->post_type ) {
			return [];
		}

		$start_utc = get_post_meta( $template_event_id, '_event_start_utc', true );
		$end_utc   = get_post_meta( $template_event_id, '_event_end_utc', true );

		if ( ! $start_utc || ! $end_utc ) {
			return [];
		}

		$start_dt = new \DateTime( $start_utc, new \DateTimeZone( 'UTC' ) );
		$end_dt   = new \DateTime( $end_utc, new \DateTimeZone( 'UTC' ) );

		// Calculate the duration of the original event.
		$duration = $start_dt->diff( $end_dt );

		// Extract the time portion from the template event.
		$time_of_day = $start_dt->format( 'H:i:s' );

		// Calculate occurrences starting from the template's start date.
		// We generate enough to cover at least 4 weeks from now.
		$today       = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$horizon     = ( clone $today )->modify( '+' . self::WEEKS_AHEAD . ' weeks' );
		$occurrences = Recurrence::calculate_occurrences(
			$rule,
			$start_dt->format( 'Y-m-d' ),
			200 // Generate enough candidates; the horizon check will limit.
		);

		$created_ids = [];

		foreach ( $occurrences as $occurrence ) {
			// Skip occurrences in the past.
			if ( $occurrence < $today ) {
				continue;
			}

			// Stop once we've gone past the horizon.
			if ( $occurrence > $horizon ) {
				break;
			}

			$occurrence_date = $occurrence->format( 'Y-m-d' );

			// Check if an instance already exists for this date.
			if ( self::instance_exists( $template_event_id, $occurrence_date ) ) {
				continue;
			}

			// Build the new event's start/end UTC.
			$new_start = new \DateTime( $occurrence_date . ' ' . $time_of_day, new \DateTimeZone( 'UTC' ) );
			$new_end   = ( clone $new_start )->add( $duration );

			$new_event_id = self::create_instance( $template_event_id, $template_post, $new_start, $new_end );

			if ( $new_event_id && ! is_wp_error( $new_event_id ) ) {
				$created_ids[] = $new_event_id;
			}
		}

		return $created_ids;
	}

	/**
	 * Check whether an event instance already exists for a given template and date.
	 *
	 * @param int    $template_event_id The template event post ID.
	 * @param string $date              The occurrence date in Y-m-d format.
	 * @return bool True if an instance already exists.
	 */
	private static function instance_exists( int $template_event_id, string $date ): bool {
		$existing = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => self::TEMPLATE_META_KEY,
					'value' => $template_event_id,
				],
				[
					'key'     => '_event_start_utc',
					'value'   => [ $date . ' 00:00:00', $date . ' 23:59:59' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			],
		] );

		return ! empty( $existing );
	}

	/**
	 * Create a single event instance from a template.
	 *
	 * Copies the template's title, content, venue_id, online_link,
	 * attendee_limit, waitlist_enabled, and timezone to the new event.
	 *
	 * @param int       $template_event_id The template event post ID.
	 * @param \WP_Post  $template_post     The template post object.
	 * @param \DateTime $start             The new event start datetime (UTC).
	 * @param \DateTime $end               The new event end datetime (UTC).
	 * @return int|\WP_Error New post ID on success, WP_Error on failure.
	 */
	private static function create_instance( int $template_event_id, \WP_Post $template_post, \DateTime $start, \DateTime $end ): int|\WP_Error {
		$timezone = get_post_meta( $template_event_id, '_event_timezone', true );

		$args = [
			'title'     => $template_post->post_title,
			'content'   => $template_post->post_content,
			'status'    => 'event-scheduled',
			'start_utc' => $start->format( 'Y-m-d H:i:s' ),
			'end_utc'   => $end->format( 'Y-m-d H:i:s' ),
			'timezone'  => $timezone ?: 'UTC',
		];

		// Copy optional meta from template.
		$optional_meta = [
			'venue_id'         => '_event_venue_id',
			'online_link'      => '_event_online_link',
			'attendee_limit'   => '_event_attendee_limit',
			'waitlist_enabled' => '_event_waitlist_enabled',
		];

		foreach ( $optional_meta as $arg_key => $meta_key ) {
			$value = get_post_meta( $template_event_id, $meta_key, true );
			if ( '' !== $value && false !== $value ) {
				$args[ $arg_key ] = $value;
			}
		}

		$new_id = Event::create( $args );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Link the new instance back to its template.
		update_post_meta( $new_id, self::TEMPLATE_META_KEY, $template_event_id );

		return $new_id;
	}
}
