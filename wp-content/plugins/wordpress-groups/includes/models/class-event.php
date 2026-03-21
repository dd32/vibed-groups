<?php
/**
 * Event model — business logic for event CRUD and status transitions.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Handles event creation, updates, retrieval, and status transitions.
 */
class Event {

	/**
	 * Post meta keys for event data.
	 *
	 * @var string[]
	 */
	const META_KEYS = [
		'start_utc'        => '_event_start_utc',
		'end_utc'          => '_event_end_utc',
		'timezone'         => '_event_timezone',
		'venue_id'         => '_event_venue_id',
		'online_link'      => '_event_online_link',
		'attendee_limit'   => '_event_attendee_limit',
		'waitlist_enabled' => '_event_waitlist_enabled',
	];

	/**
	 * Valid status transitions.
	 *
	 * Keys are the current status, values are arrays of allowed next statuses.
	 *
	 * @var array<string, string[]>
	 */
	const TRANSITIONS = [
		'event-draft'     => [ 'event-scheduled', 'event-cancelled' ],
		'event-scheduled' => [ 'event-active', 'event-past', 'event-cancelled', 'event-draft' ],
		'event-active'    => [ 'event-past', 'event-cancelled' ],
		'event-past'      => [],
		'event-cancelled' => [ 'event-draft' ],
	];

	/**
	 * Create a new event post with metadata.
	 *
	 * @param array $args {
	 *     Event arguments.
	 *
	 *     @type string $title            Event title. Required.
	 *     @type string $content          Event description. Optional.
	 *     @type string $status           Post status. Default 'event-draft'.
	 *     @type string $start_utc        Start datetime in UTC (Y-m-d H:i:s). Required.
	 *     @type string $end_utc          End datetime in UTC (Y-m-d H:i:s). Required.
	 *     @type string $timezone         Timezone identifier (e.g. 'America/New_York'). Required.
	 *     @type int    $venue_id         Venue post ID. Optional.
	 *     @type string $online_link      Online meeting link. Optional.
	 *     @type int    $attendee_limit   Maximum attendees. Optional.
	 *     @type bool   $waitlist_enabled Whether waitlist is enabled. Optional.
	 * }
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function create( array $args ): int|\WP_Error {
		if ( empty( $args['title'] ) ) {
			return new \WP_Error( 'missing_title', __( 'Event title is required.', 'wordpress-groups' ) );
		}

		if ( empty( $args['start_utc'] ) || empty( $args['end_utc'] ) ) {
			return new \WP_Error( 'missing_datetime', __( 'Event start and end datetimes are required.', 'wordpress-groups' ) );
		}

		if ( empty( $args['timezone'] ) ) {
			return new \WP_Error( 'missing_timezone', __( 'Event timezone is required.', 'wordpress-groups' ) );
		}

		$post_data = [
			'post_type'   => Event_Post_Type::POST_TYPE,
			'post_title'  => sanitize_text_field( $args['title'] ),
			'post_content' => wp_kses_post( $args['content'] ?? '' ),
			'post_status' => $args['status'] ?? 'event-draft',
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::update_meta( $post_id, $args );

		return $post_id;
	}

	/**
	 * Update an existing event post and its metadata.
	 *
	 * @param int   $post_id The event post ID.
	 * @param array $args    Event arguments to update. Same keys as create().
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( int $post_id, array $args ): true|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post || Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid event post ID.', 'wordpress-groups' ) );
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $args['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $args['title'] );
		}

		if ( isset( $args['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $args['content'] );
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		self::update_meta( $post_id, $args );

		return true;
	}

	/**
	 * Get event data including all metadata.
	 *
	 * @param int $post_id The event post ID.
	 * @return array|\WP_Error Event data array on success, WP_Error on failure.
	 */
	public static function get( int $post_id ): array|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post || Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid event post ID.', 'wordpress-groups' ) );
		}

		$data = [
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'status'  => $post->post_status,
		];

		foreach ( self::META_KEYS as $key => $meta_key ) {
			$data[ $key ] = get_post_meta( $post_id, $meta_key, true );
		}

		return $data;
	}

	/**
	 * Transition an event to a new status.
	 *
	 * Validates that the transition is allowed before applying it.
	 *
	 * @param int    $post_id    The event post ID.
	 * @param string $new_status The target status.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function transition_status( int $post_id, string $new_status ): true|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post || Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid event post ID.', 'wordpress-groups' ) );
		}

		$old_status        = $post->post_status;
		$valid_transitions = self::get_valid_transitions( $old_status );

		if ( ! in_array( $new_status, $valid_transitions, true ) ) {
			return new \WP_Error(
				'invalid_transition',
				sprintf(
					/* translators: 1: old status, 2: new status */
					__( 'Cannot transition from "%1$s" to "%2$s".', 'wordpress-groups' ),
					$old_status,
					$new_status
				)
			);
		}

		$result = wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => $new_status,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after an event status transition.
		 *
		 * @param int    $post_id    The event post ID.
		 * @param string $old_status The previous status.
		 * @param string $new_status The new status.
		 */
		do_action( 'groups_event_status_transition', $post_id, $old_status, $new_status );

		return true;
	}

	/**
	 * Get the valid next statuses for a given current status.
	 *
	 * @param string $current_status The current event status.
	 * @return string[] Array of valid next statuses.
	 */
	public static function get_valid_transitions( string $current_status ): array {
		return self::TRANSITIONS[ $current_status ] ?? [];
	}

	/**
	 * Update event post meta from an args array.
	 *
	 * @param int   $post_id The event post ID.
	 * @param array $args    Arguments containing meta values.
	 */
	private static function update_meta( int $post_id, array $args ): void {
		$meta_map = [
			'start_utc'        => '_event_start_utc',
			'end_utc'          => '_event_end_utc',
			'timezone'         => '_event_timezone',
			'venue_id'         => '_event_venue_id',
			'online_link'      => '_event_online_link',
			'attendee_limit'   => '_event_attendee_limit',
			'waitlist_enabled' => '_event_waitlist_enabled',
		];

		foreach ( $meta_map as $arg_key => $meta_key ) {
			if ( isset( $args[ $arg_key ] ) ) {
				update_post_meta( $post_id, $meta_key, $args[ $arg_key ] );
			}
		}
	}
}
