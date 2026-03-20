<?php
/**
 * RSVP confirmation and promotion email notifications.
 *
 * Sends confirmation emails when users RSVP to events and promotion
 * notifications when waitlisted users are moved to the attending list.
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

use Groups\Models\Event;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into RSVP lifecycle actions to send email notifications.
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
		/**
		 * Filters whether to send an RSVP confirmation email.
		 *
		 * @param bool   $send       Whether to send the confirmation. Default true.
		 * @param int    $comment_id The RSVP comment ID.
		 * @param int    $event_id   The event post ID.
		 * @param int    $user_id    The user ID.
		 * @param string $status     The RSVP status.
		 */
		if ( ! apply_filters( 'groups_send_rsvp_confirmation', true, $comment_id, $event_id, $user_id, $status ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$template_data = $this->get_event_template_data( $event_id, $user, $status );

		if ( null === $template_data ) {
			return;
		}

		$subject = 'attending' === $status
			? sprintf(
				/* translators: %s: event title */
				__( 'RSVP Confirmed: %s', 'wordpress-groups' ),
				$template_data['event_title']
			)
			: sprintf(
				/* translators: %s: event title */
				__( 'Waitlisted: %s', 'wordpress-groups' ),
				$template_data['event_title']
			);

		$this->notifier->send(
			$user->user_email,
			$subject,
			'rsvp-confirmation',
			$template_data
		);
	}

	/**
	 * Send a promotion notification when a waitlisted user is promoted to attending.
	 *
	 * @param int $comment_id The RSVP comment ID.
	 * @param int $event_id   The event post ID.
	 * @param int $user_id    The user ID.
	 */
	public function on_rsvp_promoted( int $comment_id, int $event_id, int $user_id ): void {
		/**
		 * Filters whether to send an RSVP promotion email.
		 *
		 * @param bool $send       Whether to send the promotion notification. Default true.
		 * @param int  $comment_id The RSVP comment ID.
		 * @param int  $event_id   The event post ID.
		 * @param int  $user_id    The user ID.
		 */
		if ( ! apply_filters( 'groups_send_rsvp_promotion', true, $comment_id, $event_id, $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$template_data = $this->get_event_template_data( $event_id, $user, 'attending' );

		if ( null === $template_data ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: event title */
			__( "You're in! %s", 'wordpress-groups' ),
			$template_data['event_title']
		);

		$this->notifier->send(
			$user->user_email,
			$subject,
			'rsvp-promotion',
			$template_data
		);
	}

	/**
	 * Build template data array for an event email.
	 *
	 * @param int      $event_id The event post ID.
	 * @param \WP_User $user     The recipient user.
	 * @param string   $status   The RSVP status.
	 * @return array|null Template data array, or null if the event is invalid.
	 */
	private function get_event_template_data( int $event_id, \WP_User $user, string $status ): ?array {
		$event_data = Event::get( $event_id );

		if ( is_wp_error( $event_data ) ) {
			return null;
		}

		$venue_name = '';
		$venue_id   = (int) get_post_meta( $event_id, '_event_venue_id', true );

		if ( $venue_id ) {
			$venue = get_post( $venue_id );

			if ( $venue ) {
				$venue_name = $venue->post_title;
			}
		}

		if ( empty( $venue_name ) ) {
			$online_link = get_post_meta( $event_id, '_event_online_link', true );
			$venue_name  = $online_link ? __( 'Online', 'wordpress-groups' ) : __( 'TBA', 'wordpress-groups' );
		}

		$start_utc  = $event_data['start_utc'] ?? '';
		$event_date = '';
		$event_time = '';

		if ( $start_utc ) {
			$timestamp  = strtotime( $start_utc );
			$event_date = wp_date( 'F j, Y', $timestamp );
			$event_time = wp_date( 'g:i A', $timestamp );
		}

		$status_label = 'attending' === $status
			? __( 'Attending', 'wordpress-groups' )
			: __( 'Waitlisted', 'wordpress-groups' );

		return [
			'user_name'   => $user->display_name,
			'event_title' => $event_data['title'],
			'event_date'  => $event_date,
			'event_time'  => $event_time,
			'event_url'   => get_permalink( $event_id ),
			'venue_name'  => $venue_name,
			'group_name'  => get_bloginfo( 'name' ),
			'rsvp_status' => $status,
			'status_label' => $status_label,
		];
	}
}
