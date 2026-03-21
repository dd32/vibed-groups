<?php
/**
 * Handles automatic event lifecycle transitions.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

use Groups\Notifications\Email_Notifier;

defined( 'ABSPATH' ) || exit;

/**
 * Transitions events to past status when their end time has passed,
 * and sends post-event thank-you emails.
 */
class Event_Lifecycle {

	const CRON_HOOK = 'groups_event_lifecycle_check';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	/**
	 * Schedule the daily cron.
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Run lifecycle checks across all group sites.
	 */
	public function run(): void {
		$this->transition_past_events();
		$this->send_post_event_emails();
	}

	/**
	 * Transition events whose end time has passed to event-past.
	 */
	private function transition_past_events(): void {
		$now = gmdate( 'Y-m-d H:i:s' );

		$events = get_posts( [
			'post_type'      => 'event',
			'post_status'    => [ 'event-scheduled', 'event-active' ],
			'posts_per_page' => 50,
			'meta_query'     => [
				[
					'key'     => '_event_end_utc',
					'value'   => $now,
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			],
		] );

		foreach ( $events as $event ) {
			wp_update_post( [
				'ID'          => $event->ID,
				'post_status' => 'event-past',
			] );
		}
	}

	/**
	 * Send thank-you emails for events that ended yesterday.
	 */
	private function send_post_event_emails(): void {
		$yesterday_start = gmdate( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
		$yesterday_end   = gmdate( 'Y-m-d 23:59:59', strtotime( '-1 day' ) );

		$events = get_posts( [
			'post_type'      => 'event',
			'post_status'    => 'event-past',
			'posts_per_page' => 20,
			'meta_query'     => [
				[
					'key'     => '_event_end_utc',
					'value'   => [ $yesterday_start, $yesterday_end ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			],
		] );

		foreach ( $events as $event ) {
			// Skip if already sent.
			if ( get_post_meta( $event->ID, '_event_thanks_sent', true ) ) {
				continue;
			}

			$attendees = get_comments( [
				'post_id'    => $event->ID,
				'type'       => 'groups_rsvp',
				'status'     => 'approve',
				'meta_key'   => '_rsvp_status',
				'meta_value' => 'attending',
			] );

			foreach ( $attendees as $rsvp ) {
				if ( ! $rsvp->comment_author_email ) {
					continue;
				}

				Email_Notifier::send(
					$rsvp->comment_author_email,
					sprintf(
						/* translators: %s: event title */
						__( 'Thanks for attending: %s', 'wordpress-groups' ),
						get_the_title( $event->ID )
					),
					'post-event-thanks',
					[
						'user_name'   => $rsvp->comment_author,
						'event_title' => get_the_title( $event->ID ),
						'event_url'   => get_permalink( $event->ID ),
						'group_name'  => get_bloginfo( 'name' ),
						'group_url'   => home_url( '/' ),
					]
				);
			}

			update_post_meta( $event->ID, '_event_thanks_sent', 1 );
		}
	}
}
