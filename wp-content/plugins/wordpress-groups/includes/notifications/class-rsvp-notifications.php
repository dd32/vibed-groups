<?php
/**
 * RSVP notification emails.
 *
 * Sends confirmation emails on RSVP creation and promotion from waitlist.
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

use Groups\Models\Event;

defined( 'ABSPATH' ) || exit;

/**
 * Handles RSVP-related email notifications.
 */
class Rsvp_Notifications {

	/**
	 * Email notifier instance.
	 *
	 * @var Email_Notifier
	 */
	private Email_Notifier $notifier;

	/**
	 * Constructor — registers hooks.
	 *
	 * @param Email_Notifier|null $notifier Optional. Email notifier instance.
	 */
	public function __construct( ?Email_Notifier $notifier = null ) {
		$this->notifier = $notifier ?? new Email_Notifier();

		add_action( 'groups_rsvp_created', [ $this, 'on_rsvp_created' ], 10, 4 );
		add_action( 'groups_rsvp_promoted', [ $this, 'on_rsvp_promoted' ], 10, 3 );
	}

	/**
	 * Send a confirmation email when an RSVP is created.
	 *
	 * @param int    $comment_id The RSVP comment ID.
	 * @param int    $event_id   The event post ID.
	 * @param int    $user_id    The user ID.
	 * @param string $status     The RSVP status (attending or waitlisted).
	 */
	public function on_rsvp_created( int $comment_id, int $event_id, int $user_id, string $status ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		/**
		 * Filters whether to send an RSVP confirmation email.
		 *
		 * @param bool   $send     Whether to send. Default true.
		 * @param int    $event_id The event post ID.
		 * @param int    $user_id  The user ID.
		 * @param string $status   The RSVP status.
		 */
		if ( ! apply_filters( 'groups_send_rsvp_confirmation', true, $event_id, $user_id, $status ) ) {
			return;
		}

		$data = $this->get_email_data( $event_id, $user, $status );

		if ( null === $data ) {
			return;
		}

		$subject = 'attending' === $status
			? sprintf(
				/* translators: %s: event title */
				__( 'RSVP Confirmed: %s', 'wordpress-groups' ),
				$data['event_title']
			)
			: sprintf(
				/* translators: %s: event title */
				__( 'Waitlisted: %s', 'wordpress-groups' ),
				$data['event_title']
			);

		$this->notifier->send(
			$user->user_email,
			$subject,
			'rsvp-confirmation',
			$data
		);
	}

	/**
	 * Send a promotion email when a waitlisted RSVP is promoted to attending.
	 *
	 * @param int $comment_id The RSVP comment ID.
	 * @param int $event_id   The event post ID.
	 * @param int $user_id    The user ID.
	 */
	public function on_rsvp_promoted( int $comment_id, int $event_id, int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		/**
		 * Filters whether to send an RSVP promotion email.
		 *
		 * @param bool $send     Whether to send. Default true.
		 * @param int  $event_id The event post ID.
		 * @param int  $user_id  The user ID.
		 */
		if ( ! apply_filters( 'groups_send_rsvp_promotion', true, $event_id, $user_id ) ) {
			return;
		}

		$data = $this->get_email_data( $event_id, $user, 'attending' );

		if ( null === $data ) {
			return;
		}

		$data['is_promotion'] = true;

		$subject = sprintf(
			/* translators: %s: event title */
			__( "You're In! %s", 'wordpress-groups' ),
			$data['event_title']
		);

		$this->notifier->send(
			$user->user_email,
			$subject,
			'rsvp-promotion',
			$data
		);
	}

	/**
	 * Build the template data array for an RSVP email.
	 *
	 * @param int      $event_id The event post ID.
	 * @param \WP_User $user     The user object.
	 * @param string   $status   The RSVP status.
	 * @return array|null Template data array, or null if event is invalid.
	 */
	private function get_email_data( int $event_id, \WP_User $user, string $status ): ?array {
		$event_data = Event::get( $event_id );

		if ( is_wp_error( $event_data ) ) {
			return null;
		}

		$venue_name = '';
		$venue_id   = $event_data['venue_id'] ?? 0;

		if ( $venue_id ) {
			$venue = get_post( (int) $venue_id );

			if ( $venue ) {
				$venue_name = $venue->post_title;
			}
		}

		if ( empty( $venue_name ) && ! empty( $event_data['online_link'] ) ) {
			$venue_name = __( 'Online Event', 'wordpress-groups' );
		}

		// Format date and time from UTC using the event timezone.
		$timezone   = $event_data['timezone'] ?? 'UTC';
		$start_utc  = $event_data['start_utc'] ?? '';
		$event_date = '';
		$event_time = '';

		if ( $start_utc ) {
			try {
				$dt = new \DateTime( $start_utc, new \DateTimeZone( 'UTC' ) );
				$dt->setTimezone( new \DateTimeZone( $timezone ) );
				$event_date = $dt->format( 'l, F j, Y' );
				$event_time = $dt->format( 'g:i A T' );
			} catch ( \Exception $e ) {
				$event_date = $start_utc;
				$event_time = '';
			}
		}

		$group_name = get_bloginfo( 'name' );

		$display_status = 'attending' === $status
			? __( 'Attending', 'wordpress-groups' )
			: __( 'Waitlisted', 'wordpress-groups' );

		return [
			'user_name'   => $user->display_name,
			'event_title' => $event_data['title'],
			'event_date'  => $event_date,
			'event_time'  => $event_time,
			'venue_name'  => $venue_name,
			'group_name'  => $group_name,
			'rsvp_status' => $display_status,
			'event_url'   => get_permalink( $event_id ),
		];
	}
}
