<?php
/**
 * Post-event cron tasks.
 *
 * Runs daily to:
 * 1. Transition ended events to event-past status.
 * 2. Send "Thanks for attending" emails the day after an event ends.
 *
 * @package Groups\Cron
 */

namespace Groups\Cron;

use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Notifications\Email_Notifier;
use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Handles daily post-event workflow tasks via WP-Cron.
 */
class Post_Event_Cron {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'groups_post_event_check';

	/**
	 * Post meta key to track that the thank-you email was sent.
	 *
	 * @var string
	 */
	const THANKED_META = '_event_thankyou_sent';

	/**
	 * Constructor. Registers hooks.
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
	 * Run all post-event tasks.
	 */
	public function run(): void {
		$this->transition_past_events();
		$this->send_thankyou_emails();
	}

	/**
	 * Transition events whose end time has passed to event-past.
	 *
	 * Queries for event-active and event-scheduled events where
	 * `_event_end_utc` is earlier than the current UTC time.
	 */
	private function transition_past_events(): void {
		$now_utc = gmdate( 'Y-m-d H:i:s' );

		$events = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => [ 'event-active', 'event-scheduled' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_event_end_utc',
					'value'   => $now_utc,
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			],
		] );

		foreach ( $events as $event_id ) {
			Event::transition_status( (int) $event_id, 'event-past' );
		}
	}

	/**
	 * Send thank-you emails to attendees the day after an event ends.
	 *
	 * Queries for event-past events whose end time was between 24 and
	 * 48 hours ago and that have not yet had thank-you emails sent.
	 */
	private function send_thankyou_emails(): void {
		$now       = time();
		$after_utc = gmdate( 'Y-m-d H:i:s', $now - ( 2 * DAY_IN_SECONDS ) );
		$before_utc = gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS );

		$events = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => 'event-past',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_event_end_utc',
					'value'   => [ $after_utc, $before_utc ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
				[
					'key'     => self::THANKED_META,
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		if ( empty( $events ) ) {
			return;
		}

		$notifier = new Email_Notifier();

		foreach ( $events as $event_id ) {
			$event_id   = (int) $event_id;
			$event_data = Event::get( $event_id );

			if ( is_wp_error( $event_data ) ) {
				continue;
			}

			// Get attendees (those who were marked as attending and confirmed attendance).
			$rsvps = Rsvp::get_rsvps( $event_id, 'attending' );

			foreach ( $rsvps as $rsvp ) {
				$attendance_confirmed = get_comment_meta( $rsvp->comment_ID, '_rsvp_attendance_confirmed', true );

				// Only send to users whose attendance was confirmed.
				if ( ! $attendance_confirmed ) {
					continue;
				}

				$user = get_userdata( (int) $rsvp->user_id );

				if ( ! $user ) {
					continue;
				}

				/**
				 * Filters whether to send a thank-you email to a specific attendee.
				 *
				 * @param bool $send     Whether to send the email. Default true.
				 * @param int  $event_id The event post ID.
				 * @param int  $user_id  The user ID.
				 */
				if ( ! apply_filters( 'groups_send_thankyou_email', true, $event_id, (int) $rsvp->user_id ) ) {
					continue;
				}

				$notifier->send(
					$user->user_email,
					sprintf(
						/* translators: %s: event title */
						__( 'Thanks for attending %s!', 'wordpress-groups' ),
						$event_data['title']
					),
					'post-event-thanks',
					[
						'event_title' => $event_data['title'],
						'event_url'   => get_permalink( $event_id ),
						'user_name'   => $user->display_name,
					]
				);
			}

			// Mark the event so we do not send again.
			update_post_meta( $event_id, self::THANKED_META, gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * Get attendance summary stats for an event.
	 *
	 * @param int $event_id The event post ID.
	 * @return array{attended: int, no_show: int, total_rsvps: int}
	 */
	public static function get_attendance_summary( int $event_id ): array {
		$all_rsvps = Rsvp::get_rsvps( $event_id );
		$attended  = 0;
		$no_show   = 0;
		$total     = 0;

		foreach ( $all_rsvps as $rsvp ) {
			$status = get_comment_meta( $rsvp->comment_ID, '_rsvp_status', true );

			// Skip cancelled RSVPs.
			if ( 'not_attending' === $status ) {
				continue;
			}

			++$total;

			if ( 'no_show' === $status ) {
				++$no_show;
			} elseif ( get_comment_meta( $rsvp->comment_ID, '_rsvp_attendance_confirmed', true ) ) {
				++$attended;
			}
		}

		return [
			'attended'    => $attended,
			'no_show'     => $no_show,
			'total_rsvps' => $total,
		];
	}
}
