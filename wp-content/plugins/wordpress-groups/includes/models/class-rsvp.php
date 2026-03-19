<?php
/**
 * RSVP model — comment-based RSVPs with waitlist promotion and attendance.
 *
 * RSVPs are stored as comments on event posts. Comment meta tracks status,
 * guest count, and attendance. Waitlist promotion happens automatically
 * when an attending RSVP is cancelled and there is capacity available.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Handles RSVP creation, updates, cancellation, waitlist promotion, and attendance.
 */
class Rsvp {

	/**
	 * Comment type used for RSVPs.
	 *
	 * @var string
	 */
	const COMMENT_TYPE = 'groups_rsvp';

	/**
	 * Valid RSVP statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [
		'attending',
		'waitlisted',
		'not_attending',
		'no_show',
	];

	/**
	 * Comment meta keys.
	 *
	 * @var string[]
	 */
	const META_KEYS = [
		'_rsvp_status',
		'_rsvp_guests',
		'_rsvp_answers',
		'_rsvp_attendance_confirmed',
		'_rsvp_is_first_event',
	];

	/**
	 * Create an RSVP for a user on an event.
	 *
	 * If the event has an attendee limit and it has been reached, the user
	 * is placed on the waitlist (if enabled).
	 *
	 * @param int    $event_id The event post ID.
	 * @param int    $user_id  The user ID.
	 * @param int    $guests   Number of additional guests. Default 0.
	 * @param string $answers  JSON-encoded custom question answers. Default empty.
	 * @return int|\WP_Error Comment ID on success, WP_Error on failure.
	 */
	public static function create( int $event_id, int $user_id, int $guests = 0, string $answers = '' ): int|\WP_Error {
		$post = get_post( $event_id );

		if ( ! $post || Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid event.', 'wordpress-groups' ) );
		}

		// Check for duplicate RSVP.
		$existing = self::get_user_rsvp( $event_id, $user_id );

		if ( $existing ) {
			return new \WP_Error(
				'duplicate_rsvp',
				__( 'You have already RSVPed to this event.', 'wordpress-groups' )
			);
		}

		// Determine status based on capacity.
		$status = self::determine_status( $event_id );

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', __( 'Invalid user.', 'wordpress-groups' ) );
		}

		$comment_data = [
			'comment_post_ID'  => $event_id,
			'user_id'          => $user_id,
			'comment_author'   => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_content'  => '',
			'comment_type'     => self::COMMENT_TYPE,
			'comment_approved' => 1,
		];

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			return new \WP_Error( 'rsvp_failed', __( 'Failed to create RSVP.', 'wordpress-groups' ) );
		}

		update_comment_meta( $comment_id, '_rsvp_status', $status );
		update_comment_meta( $comment_id, '_rsvp_guests', absint( $guests ) );
		update_comment_meta( $comment_id, '_rsvp_attendance_confirmed', false );

		if ( $answers ) {
			update_comment_meta( $comment_id, '_rsvp_answers', $answers );
		}

		/**
		 * Fires after an RSVP is created.
		 *
		 * @param int    $comment_id The comment ID.
		 * @param int    $event_id   The event post ID.
		 * @param int    $user_id    The user ID.
		 * @param string $status     The RSVP status (attending or waitlisted).
		 */
		do_action( 'groups_rsvp_created', $comment_id, $event_id, $user_id, $status );

		return $comment_id;
	}

	/**
	 * Update an existing RSVP.
	 *
	 * @param int    $comment_id The RSVP comment ID.
	 * @param array  $args {
	 *     Optional. Arguments to update.
	 *
	 *     @type int    $guests  Number of additional guests.
	 *     @type string $answers JSON-encoded custom question answers.
	 * }
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( int $comment_id, array $args ): true|\WP_Error {
		$comment = get_comment( $comment_id );

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'invalid_rsvp', __( 'Invalid RSVP.', 'wordpress-groups' ) );
		}

		if ( isset( $args['guests'] ) ) {
			update_comment_meta( $comment_id, '_rsvp_guests', absint( $args['guests'] ) );
		}

		if ( isset( $args['answers'] ) ) {
			update_comment_meta( $comment_id, '_rsvp_answers', $args['answers'] );
		}

		return true;
	}

	/**
	 * Cancel an RSVP and promote the next waitlisted user if applicable.
	 *
	 * @param int $comment_id The RSVP comment ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function cancel( int $comment_id ): true|\WP_Error {
		$comment = get_comment( $comment_id );

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'invalid_rsvp', __( 'Invalid RSVP.', 'wordpress-groups' ) );
		}

		$old_status = get_comment_meta( $comment_id, '_rsvp_status', true );
		$event_id   = (int) $comment->comment_post_ID;

		update_comment_meta( $comment_id, '_rsvp_status', 'not_attending' );

		/**
		 * Fires after an RSVP is cancelled.
		 *
		 * @param int    $comment_id The comment ID.
		 * @param int    $event_id   The event post ID.
		 * @param int    $user_id    The user ID.
		 * @param string $old_status The previous RSVP status.
		 */
		do_action( 'groups_rsvp_cancelled', $comment_id, $event_id, (int) $comment->user_id, $old_status );

		// Promote from waitlist if an attending spot opened up.
		if ( 'attending' === $old_status ) {
			self::maybe_promote_waitlist( $event_id );
		}

		return true;
	}

	/**
	 * Mark attendance for a user's RSVP on an event.
	 *
	 * @param int  $event_id The event post ID.
	 * @param int  $user_id  The user ID.
	 * @param bool $attended Whether the user attended. Default true.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function mark_attendance( int $event_id, int $user_id, bool $attended = true ): true|\WP_Error {
		$rsvp = self::get_user_rsvp( $event_id, $user_id );

		if ( ! $rsvp ) {
			return new \WP_Error( 'no_rsvp', __( 'No RSVP found for this user.', 'wordpress-groups' ) );
		}

		$comment_id = (int) $rsvp->comment_ID;

		update_comment_meta( $comment_id, '_rsvp_attendance_confirmed', $attended );

		if ( ! $attended ) {
			update_comment_meta( $comment_id, '_rsvp_status', 'no_show' );
		}

		return true;
	}

	/**
	 * Get all RSVPs for an event, optionally filtered by status.
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $status   Optional status to filter by.
	 * @return \WP_Comment[] Array of comment objects.
	 */
	public static function get_rsvps( int $event_id, string $status = '' ): array {
		$args = [
			'post_id' => $event_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date',
			'order'   => 'ASC',
		];

		if ( $status ) {
			$args['meta_key']   = '_rsvp_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = $status;         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		return get_comments( $args );
	}

	/**
	 * Get the RSVP comment for a specific user on an event.
	 *
	 * Only returns RSVPs that are not cancelled (not_attending).
	 *
	 * @param int $event_id The event post ID.
	 * @param int $user_id  The user ID.
	 * @return \WP_Comment|null The RSVP comment or null if not found.
	 */
	public static function get_user_rsvp( int $event_id, int $user_id ): ?\WP_Comment {
		$comments = get_comments( [
			'post_id'    => $event_id,
			'user_id'    => $user_id,
			'type'       => self::COMMENT_TYPE,
			'status'     => 'approve',
			'number'     => 1,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_rsvp_status',
					'value'   => 'not_attending',
					'compare' => '!=',
				],
			],
		] );

		return $comments[0] ?? null;
	}

	/**
	 * Get the count of attending RSVPs for an event.
	 *
	 * @param int $event_id The event post ID.
	 * @return int The number of attending RSVPs.
	 */
	public static function get_attending_count( int $event_id ): int {
		return count( self::get_rsvps( $event_id, 'attending' ) );
	}

	/**
	 * Determine the RSVP status based on event capacity.
	 *
	 * @param int $event_id The event post ID.
	 * @return string 'attending' or 'waitlisted'.
	 */
	private static function determine_status( int $event_id ): string {
		$limit = (int) get_post_meta( $event_id, '_event_attendee_limit', true );

		// No limit set — everyone attends.
		if ( 0 === $limit ) {
			return 'attending';
		}

		$attending_count = self::get_attending_count( $event_id );

		if ( $attending_count < $limit ) {
			return 'attending';
		}

		// Check if waitlist is enabled.
		$waitlist_enabled = (bool) get_post_meta( $event_id, '_event_waitlist_enabled', true );

		if ( $waitlist_enabled ) {
			return 'waitlisted';
		}

		// No waitlist — still mark as attending (over-capacity allowed if no waitlist).
		return 'attending';
	}

	/**
	 * Promote the next waitlisted RSVP to attending status.
	 *
	 * Called when an attending RSVP is cancelled to fill the opened spot.
	 *
	 * @param int $event_id The event post ID.
	 */
	public static function maybe_promote_waitlist( int $event_id ): void {
		$limit = (int) get_post_meta( $event_id, '_event_attendee_limit', true );

		// No limit — nothing to promote.
		if ( 0 === $limit ) {
			return;
		}

		$attending_count = self::get_attending_count( $event_id );

		if ( $attending_count >= $limit ) {
			return;
		}

		// Get the oldest waitlisted RSVP.
		$waitlisted = self::get_rsvps( $event_id, 'waitlisted' );

		if ( empty( $waitlisted ) ) {
			return;
		}

		$promoted = $waitlisted[0];
		update_comment_meta( $promoted->comment_ID, '_rsvp_status', 'attending' );

		/**
		 * Fires after a waitlisted RSVP is promoted to attending.
		 *
		 * @param int $comment_id The comment ID.
		 * @param int $event_id   The event post ID.
		 * @param int $user_id    The user ID.
		 */
		do_action( 'groups_rsvp_promoted', (int) $promoted->comment_ID, $event_id, (int) $promoted->user_id );
	}

	/**
	 * Prepare RSVP data for REST API response.
	 *
	 * @param \WP_Comment $comment The RSVP comment.
	 * @return array RSVP data array.
	 */
	public static function prepare_for_response( \WP_Comment $comment ): array {
		$user_id = (int) $comment->user_id;
		$user    = get_userdata( $user_id );

		return [
			'id'                    => (int) $comment->comment_ID,
			'event_id'              => (int) $comment->comment_post_ID,
			'user_id'               => $user_id,
			'display_name'          => $user ? esc_html( $user->display_name ) : '',
			'avatar_url'            => get_avatar_url( $user_id, [ 'size' => 96 ] ),
			'status'                => get_comment_meta( $comment->comment_ID, '_rsvp_status', true ),
			'guests'                => (int) get_comment_meta( $comment->comment_ID, '_rsvp_guests', true ),
			'attendance_confirmed'  => (bool) get_comment_meta( $comment->comment_ID, '_rsvp_attendance_confirmed', true ),
			'timestamp'             => $comment->comment_date_gmt,
		];
	}
}
