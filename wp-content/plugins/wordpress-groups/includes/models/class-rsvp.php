<?php
/**
 * RSVP model — comment-based RSVPs with waitlist promotion.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Handles RSVP creation, updates, cancellation, waitlist promotion,
 * and attendance tracking using WordPress comments on event posts.
 */
class Rsvp {

	/**
	 * Comment type used for RSVPs.
	 *
	 * @var string
	 */
	const COMMENT_TYPE = 'rsvp';

	/**
	 * Valid RSVP statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'attending', 'waitlisted', 'not_attending', 'no_show' ];

	/**
	 * Comment meta keys for RSVP data.
	 *
	 * @var string[]
	 */
	const META_KEYS = [
		'status'                => '_rsvp_status',
		'guest_count'           => '_rsvp_guest_count',
		'answers'               => '_rsvp_answers',
		'attendance_confirmed'  => '_rsvp_attendance_confirmed',
		'is_first_event'        => '_rsvp_is_first_event',
	];

	/**
	 * Create an RSVP for an event.
	 *
	 * Inserts a comment of type 'rsvp' on the event post. If the event
	 * has an attendee limit and is full, the status is set to 'waitlisted'.
	 *
	 * @param int   $event_id The event post ID.
	 * @param int   $user_id  The user ID.
	 * @param array $args {
	 *     Optional RSVP arguments.
	 *
	 *     @type string $status      RSVP status. Default 'attending'.
	 *     @type int    $guest_count Number of additional guests. Default 0.
	 *     @type array  $answers     Custom question answers. Default empty.
	 *     @type string $message     Optional comment content/message.
	 * }
	 * @return int|\WP_Error Comment ID on success, WP_Error on failure.
	 */
	public static function create( int $event_id, int $user_id, array $args = [] ): int|\WP_Error {
		$post = get_post( $event_id );

		if ( ! $post || Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid event post ID.', 'wordpress-groups' ) );
		}

		// Check for duplicate RSVP.
		$existing = self::get_user_rsvp( $event_id, $user_id );

		if ( $existing ) {
			return new \WP_Error( 'duplicate_rsvp', __( 'User has already RSVPed to this event.', 'wordpress-groups' ) );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', __( 'Invalid user ID.', 'wordpress-groups' ) );
		}

		$status      = $args['status'] ?? 'attending';
		$guest_count = absint( $args['guest_count'] ?? 0 );

		// Auto-waitlist if event is full.
		if ( 'attending' === $status && self::is_event_full( $event_id, 1 + $guest_count ) ) {
			$status = 'waitlisted';
		}

		$comment_data = [
			'comment_post_ID'  => $event_id,
			'user_id'          => $user_id,
			'comment_author'   => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_content'  => sanitize_textarea_field( $args['message'] ?? '' ),
			'comment_type'     => self::COMMENT_TYPE,
			'comment_approved' => 1,
		];

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			return new \WP_Error( 'rsvp_insert_failed', __( 'Failed to create RSVP.', 'wordpress-groups' ) );
		}

		// Store meta.
		update_comment_meta( $comment_id, self::META_KEYS['status'], $status );
		update_comment_meta( $comment_id, self::META_KEYS['guest_count'], $guest_count );

		if ( ! empty( $args['answers'] ) ) {
			update_comment_meta( $comment_id, self::META_KEYS['answers'], wp_json_encode( $args['answers'] ) );
		}

		/**
		 * Fires after an RSVP is created.
		 *
		 * @param int    $comment_id The RSVP comment ID.
		 * @param int    $event_id   The event post ID.
		 * @param int    $user_id    The user ID.
		 * @param string $status     The RSVP status.
		 */
		do_action( 'groups_rsvp_created', $comment_id, $event_id, $user_id, $status );

		return $comment_id;
	}

	/**
	 * Update an existing RSVP.
	 *
	 * @param int   $comment_id The RSVP comment ID.
	 * @param array $args {
	 *     RSVP fields to update.
	 *
	 *     @type string $status      RSVP status.
	 *     @type int    $guest_count Number of additional guests.
	 *     @type array  $answers     Custom question answers.
	 * }
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( int $comment_id, array $args ): true|\WP_Error {
		$comment = get_comment( $comment_id );

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'invalid_rsvp', __( 'Invalid RSVP comment ID.', 'wordpress-groups' ) );
		}

		if ( isset( $args['status'] ) ) {
			if ( ! in_array( $args['status'], self::STATUSES, true ) ) {
				return new \WP_Error( 'invalid_status', __( 'Invalid RSVP status.', 'wordpress-groups' ) );
			}
			update_comment_meta( $comment_id, self::META_KEYS['status'], $args['status'] );
		}

		if ( isset( $args['guest_count'] ) ) {
			update_comment_meta( $comment_id, self::META_KEYS['guest_count'], absint( $args['guest_count'] ) );
		}

		if ( isset( $args['answers'] ) ) {
			update_comment_meta( $comment_id, self::META_KEYS['answers'], wp_json_encode( $args['answers'] ) );
		}

		return true;
	}

	/**
	 * Cancel an RSVP and promote the next waitlisted person.
	 *
	 * @param int $comment_id The RSVP comment ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function cancel( int $comment_id ): true|\WP_Error {
		$comment = get_comment( $comment_id );

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'invalid_rsvp', __( 'Invalid RSVP comment ID.', 'wordpress-groups' ) );
		}

		$previous_status = get_comment_meta( $comment_id, self::META_KEYS['status'], true );

		update_comment_meta( $comment_id, self::META_KEYS['status'], 'not_attending' );

		// Promote next waitlisted person if the cancelled RSVP was attending.
		if ( 'attending' === $previous_status ) {
			self::promote_next( (int) $comment->comment_post_ID );
		}

		return true;
	}

	/**
	 * Promote the next waitlisted RSVP to attending.
	 *
	 * Finds the oldest waitlisted RSVP for the event and sets its
	 * status to 'attending'.
	 *
	 * @param int $event_id The event post ID.
	 * @return int|false The promoted comment ID, or false if none to promote.
	 */
	public static function promote_next( int $event_id ): int|false {
		$waitlisted = get_comments( [
			'post_id' => $event_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date',
			'order'   => 'ASC',
			'number'  => 1,
			'meta_query' => [
				[
					'key'   => self::META_KEYS['status'],
					'value' => 'waitlisted',
				],
			],
		] );

		if ( empty( $waitlisted ) ) {
			return false;
		}

		$comment = $waitlisted[0];
		$comment_id = (int) $comment->comment_ID;

		update_comment_meta( $comment_id, self::META_KEYS['status'], 'attending' );

		/**
		 * Fires after a waitlisted RSVP is promoted to attending.
		 *
		 * @param int $comment_id The RSVP comment ID.
		 * @param int $event_id   The event post ID.
		 * @param int $user_id    The user ID.
		 */
		do_action( 'groups_rsvp_promoted', $comment_id, $event_id, (int) $comment->user_id );

		return $comment_id;
	}

	/**
	 * Mark attendance for an RSVP (organizer action).
	 *
	 * @param int  $comment_id The RSVP comment ID.
	 * @param bool $attended   Whether the person attended.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function mark_attendance( int $comment_id, bool $attended ): true|\WP_Error {
		$comment = get_comment( $comment_id );

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'invalid_rsvp', __( 'Invalid RSVP comment ID.', 'wordpress-groups' ) );
		}

		$status = $attended ? 'attending' : 'no_show';

		update_comment_meta( $comment_id, self::META_KEYS['status'], $status );
		update_comment_meta( $comment_id, self::META_KEYS['attendance_confirmed'], true );

		return true;
	}

	/**
	 * Get all RSVPs for an event, optionally filtered by status.
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $status   Optional. Filter by RSVP status.
	 * @return \WP_Comment[] Array of RSVP comments.
	 */
	public static function get_for_event( int $event_id, string $status = '' ): array {
		$query_args = [
			'post_id' => $event_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date',
			'order'   => 'ASC',
		];

		if ( '' !== $status ) {
			$query_args['meta_query'] = [
				[
					'key'   => self::META_KEYS['status'],
					'value' => $status,
				],
			];
		}

		return get_comments( $query_args );
	}

	/**
	 * Count RSVPs by status, including guest counts in the total.
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $status   RSVP status to count. Default 'attending'.
	 * @return int Total count of attendees (RSVPs + their guests).
	 */
	public static function get_count( int $event_id, string $status = 'attending' ): int {
		$rsvps = self::get_for_event( $event_id, $status );
		$count = 0;

		foreach ( $rsvps as $rsvp ) {
			$guest_count = (int) get_comment_meta( $rsvp->comment_ID, self::META_KEYS['guest_count'], true );
			$count += 1 + $guest_count; // The person + their guests.
		}

		return $count;
	}

	/**
	 * Get a specific user's RSVP for an event.
	 *
	 * @param int $event_id The event post ID.
	 * @param int $user_id  The user ID.
	 * @return \WP_Comment|null The RSVP comment, or null if not found.
	 */
	public static function get_user_rsvp( int $event_id, int $user_id ): ?\WP_Comment {
		$comments = get_comments( [
			'post_id' => $event_id,
			'user_id' => $user_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => 'approve',
			'number'  => 1,
		] );

		return $comments[0] ?? null;
	}

	/**
	 * Check whether the event is at capacity for attending RSVPs.
	 *
	 * @param int $event_id        The event post ID.
	 * @param int $additional_spots Number of additional spots needed. Default 1.
	 * @return bool True if the event is full (no room for additional_spots).
	 */
	private static function is_event_full( int $event_id, int $additional_spots = 1 ): bool {
		$limit = (int) get_post_meta( $event_id, '_event_attendee_limit', true );

		if ( $limit <= 0 ) {
			return false;
		}

		$current_count = self::get_count( $event_id, 'attending' );

		return ( $current_count + $additional_spots ) > $limit;
	}
}
