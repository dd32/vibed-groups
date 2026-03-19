<?php
/**
 * Event reminder scheduling via wp_cron.
 *
 * Schedules 24-hour and 1-hour reminder emails before events, and
 * unschedules them when events are cancelled.
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

use Groups\Models\Event;
use Groups\Models\Rsvp;

defined( 'ABSPATH' ) || exit;

/**
 * Manages event reminder cron jobs.
 */
class Scheduler {

	/**
	 * Cron hook for 24-hour reminders.
	 *
	 * @var string
	 */
	const HOOK_24H = 'groups_event_reminder_24h';

	/**
	 * Cron hook for 1-hour reminders.
	 *
	 * @var string
	 */
	const HOOK_1H = 'groups_event_reminder_1h';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'transition_post_status', [ $this, 'on_status_transition' ], 10, 3 );
		add_action( self::HOOK_24H, [ $this, 'send_reminder' ], 10, 1 );
		add_action( self::HOOK_1H, [ $this, 'send_reminder' ], 10, 1 );
	}

	/**
	 * Handle event post status transitions.
	 *
	 * Schedules reminders when an event moves to `event-scheduled`, and
	 * unschedules them when an event moves to `event-cancelled`.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'event' !== $post->post_type ) {
			return;
		}

		if ( 'event-scheduled' === $new_status ) {
			$this->schedule_reminders( $post->ID );
		}

		if ( 'event-cancelled' === $new_status ) {
			$this->unschedule_reminders( $post->ID );
		}
	}

	/**
	 * Schedule 24h and 1h reminder cron events for an event post.
	 *
	 * Clears any existing reminders first to avoid duplicates (e.g. when
	 * the event start time is updated).
	 *
	 * @param int $event_id The event post ID.
	 */
	public function schedule_reminders( int $event_id ): void {
		$start_utc = get_post_meta( $event_id, '_event_start_utc', true );

		if ( empty( $start_utc ) ) {
			return;
		}

		$start_timestamp = strtotime( $start_utc );

		if ( false === $start_timestamp ) {
			return;
		}

		// Clear existing reminders to avoid duplicates.
		$this->unschedule_reminders( $event_id );

		$time_24h = $start_timestamp - DAY_IN_SECONDS;
		$time_1h  = $start_timestamp - HOUR_IN_SECONDS;
		$now      = time();

		if ( $time_24h > $now ) {
			wp_schedule_single_event( $time_24h, self::HOOK_24H, [ $event_id ] );
		}

		if ( $time_1h > $now ) {
			wp_schedule_single_event( $time_1h, self::HOOK_1H, [ $event_id ] );
		}
	}

	/**
	 * Unschedule any pending reminder cron events for an event post.
	 *
	 * @param int $event_id The event post ID.
	 */
	public function unschedule_reminders( int $event_id ): void {
		$timestamp_24h = wp_next_scheduled( self::HOOK_24H, [ $event_id ] );

		if ( $timestamp_24h ) {
			wp_unschedule_event( $timestamp_24h, self::HOOK_24H, [ $event_id ] );
		}

		$timestamp_1h = wp_next_scheduled( self::HOOK_1H, [ $event_id ] );

		if ( $timestamp_1h ) {
			wp_unschedule_event( $timestamp_1h, self::HOOK_1H, [ $event_id ] );
		}
	}

	/**
	 * Send reminder emails to all attending RSVPs for an event.
	 *
	 * Called by the cron hooks. Skips sending if the event is cancelled.
	 *
	 * @param int $event_id The event post ID.
	 */
	public function send_reminder( int $event_id ): void {
		$event_data = Event::get( $event_id );

		if ( is_wp_error( $event_data ) ) {
			return;
		}

		// Don't send reminders for cancelled events.
		if ( 'event-cancelled' === $event_data['status'] ) {
			return;
		}

		$rsvps = Rsvp::get_rsvps( $event_id, 'attending' );

		if ( empty( $rsvps ) ) {
			return;
		}

		$notifier = new Email_Notifier();

		foreach ( $rsvps as $rsvp ) {
			$user = get_userdata( (int) $rsvp->user_id );

			if ( ! $user ) {
				continue;
			}

			/**
			 * Filters whether to send a reminder email to a specific user.
			 *
			 * @param bool $send     Whether to send the reminder. Default true.
			 * @param int  $event_id The event post ID.
			 * @param int  $user_id  The user ID.
			 */
			if ( ! apply_filters( 'groups_send_event_reminder', true, $event_id, (int) $rsvp->user_id ) ) {
				continue;
			}

			$notifier->send(
				$user->user_email,
				sprintf(
					/* translators: %s: event title */
					__( 'Reminder: %s', 'wordpress-groups' ),
					$event_data['title']
				),
				'event-reminder',
				[
					'event_title' => $event_data['title'],
					'event_date'  => $event_data['start_utc'],
					'event_url'   => get_permalink( $event_id ),
					'user_name'   => $user->display_name,
				]
			);
		}
	}
}
