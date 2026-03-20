<?php
/**
 * REST API controller for RSVPs.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Models\Membership;
use Groups\Models\Rsvp;
use Groups\Post_Types\Event;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles RSVP operations via the REST API.
 *
 * RSVPs are stored as comments on event posts. This controller provides
 * endpoints for listing, creating, updating, cancelling RSVPs, and
 * marking attendance.
 */
class Rsvp_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for RSVP routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'events/(?P<event_id>[\d]+)';

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// GET /events/{event_id}/rsvps — list RSVPs for an event.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/rsvps',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => [
						'event_id' => [
							'description' => __( 'The event ID.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'status'   => [
							'description' => __( 'Filter RSVPs by status.', 'wordpress-groups' ),
							'type'        => 'string',
							'enum'        => Rsvp::STATUSES,
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// POST/PUT/DELETE /events/{event_id}/rsvp — create, update, or cancel RSVP.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/rsvp',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => [
						'event_id' => [
							'description' => __( 'The event ID.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'guests'   => [
							'description' => __( 'Number of additional guests.', 'wordpress-groups' ),
							'type'        => 'integer',
							'default'     => 0,
						],
						'answers'  => [
							'description' => __( 'Custom question answers (JSON string).', 'wordpress-groups' ),
							'type'        => 'string',
							'default'     => '',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => [
						'event_id' => [
							'description' => __( 'The event ID.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'guests'   => [
							'description' => __( 'Number of additional guests.', 'wordpress-groups' ),
							'type'        => 'integer',
						],
						'answers'  => [
							'description' => __( 'Custom question answers (JSON string).', 'wordpress-groups' ),
							'type'        => 'string',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => [
						'event_id' => [
							'description' => __( 'The event ID.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
					],
				],
			]
		);

		// POST /events/{event_id}/attendance — mark attendance (organizer only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/attendance',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'mark_attendance' ],
					'permission_callback' => [ $this, 'mark_attendance_permissions_check' ],
					'args'                => [
						'event_id' => [
							'description' => __( 'The event ID.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'user_id'  => [
							'description' => __( 'The user ID to mark attendance for.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'attended' => [
							'description' => __( 'Whether the user attended.', 'wordpress-groups' ),
							'type'        => 'boolean',
							'default'     => true,
						],
					],
				],
			]
		);
	}

	/**
	 * Validate that an event exists and is a valid event post.
	 *
	 * @param int $event_id The event post ID.
	 * @return \WP_Post|WP_Error The event post or an error.
	 */
	private function validate_event( int $event_id ): \WP_Post|WP_Error {
		$post = get_post( $event_id );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		return $post;
	}

	/**
	 * Check whether the current user is an organizer or co-organizer.
	 *
	 * @return bool
	 */
	private function is_organizer(): bool {
		if ( is_super_admin() ) {
			return true;
		}

		$user = wp_get_current_user();

		return in_array( 'organizer', (array) $user->roles, true )
			|| in_array( 'co_organizer', (array) $user->roles, true )
			|| in_array( 'co-organizer', (array) $user->roles, true )
			|| in_array( 'administrator', (array) $user->roles, true );
	}

	/**
	 * Permission check for listing RSVPs — public.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$event = $this->validate_event( absint( $request->get_param( 'event_id' ) ) );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return true;
	}

	/**
	 * Retrieve RSVPs for an event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$status   = $request->get_param( 'status' ) ?? '';

		$rsvps = Rsvp::get_rsvps( $event_id, sanitize_text_field( $status ) );
		$items = [];

		foreach ( $rsvps as $rsvp ) {
			$items[] = Rsvp::prepare_for_response( $rsvp );
		}

		// Build summary counts expected by the rsvp-button block's view.js.
		$attending_count = count( Rsvp::get_rsvps( $event_id, 'attending' ) );
		$waitlist_count  = count( Rsvp::get_rsvps( $event_id, 'waitlisted' ) );

		$user_status = null;
		if ( is_user_logged_in() ) {
			$user_rsvp = Rsvp::get_user_rsvp( $event_id, get_current_user_id() );
			if ( $user_rsvp ) {
				$user_status = get_comment_meta( $user_rsvp->comment_ID, '_rsvp_status', true );
			}
		}

		$data = [
			'rsvps'           => $items,
			'attending_count' => $attending_count,
			'waitlist_count'  => $waitlist_count,
			'user_status'     => $user_status,
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Permission check for creating an RSVP — must be logged in.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to RSVP.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( Membership::is_banned( get_current_user_id() ) ) {
			return new WP_Error(
				'rest_user_banned',
				__( 'You are banned from this group.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		$event = $this->validate_event( absint( $request->get_param( 'event_id' ) ) );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return true;
	}

	/**
	 * Create an RSVP.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$event_id    = absint( $request->get_param( 'event_id' ) );
		$user_id     = get_current_user_id();
		$guests      = absint( $request->get_param( 'guests' ) );
		$raw_answers = $request->get_param( 'answers' ) ?? '';

		if ( '' !== $raw_answers ) {
			$decoded = json_decode( wp_unslash( $raw_answers ), true );

			if ( null === $decoded && 'null' !== $raw_answers ) {
				return new WP_Error(
					'rest_invalid_answers',
					__( 'The answers field must be valid JSON.', 'wordpress-groups' ),
					[ 'status' => 400 ]
				);
			}

			$answers = wp_json_encode( $decoded );
		} else {
			$answers = '';
		}

		$result = Rsvp::create( $event_id, $user_id, $guests, $answers );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = 400;

			if ( 'duplicate_rsvp' === $result->get_error_code() ) {
				$status = 409;
			}

			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => $status ]
			);
		}

		$comment  = get_comment( $result );
		$data     = Rsvp::prepare_for_response( $comment );
		$response = new WP_REST_Response( $data, 201 );

		return $response;
	}

	/**
	 * Permission check for updating own RSVP — must be logged in.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to update your RSVP.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		$event = $this->validate_event( absint( $request->get_param( 'event_id' ) ) );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return true;
	}

	/**
	 * Update own RSVP.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$user_id  = get_current_user_id();
		$rsvp     = Rsvp::get_user_rsvp( $event_id, $user_id );

		if ( ! $rsvp ) {
			return new WP_Error(
				'rest_rsvp_not_found',
				__( 'No RSVP found for this event.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$args = [];

		$guests = $request->get_param( 'guests' );
		if ( null !== $guests ) {
			$args['guests'] = absint( $guests );
		}

		$answers = $request->get_param( 'answers' );
		if ( null !== $answers ) {
			if ( '' !== $answers ) {
				$decoded = json_decode( wp_unslash( $answers ), true );

				if ( null === $decoded && 'null' !== $answers ) {
					return new WP_Error(
						'rest_invalid_answers',
						__( 'The answers field must be valid JSON.', 'wordpress-groups' ),
						[ 'status' => 400 ]
					);
				}

				$args['answers'] = wp_json_encode( $decoded );
			} else {
				$args['answers'] = '';
			}
		}

		$result = Rsvp::update( (int) $rsvp->comment_ID, $args );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		// Re-fetch to include updated data.
		$comment = get_comment( $rsvp->comment_ID );
		$data    = Rsvp::prepare_for_response( $comment );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Permission check for cancelling own RSVP — must be logged in.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to cancel your RSVP.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		$event = $this->validate_event( absint( $request->get_param( 'event_id' ) ) );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return true;
	}

	/**
	 * Cancel own RSVP.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$user_id  = get_current_user_id();
		$rsvp     = Rsvp::get_user_rsvp( $event_id, $user_id );

		if ( ! $rsvp ) {
			return new WP_Error(
				'rest_rsvp_not_found',
				__( 'No RSVP found for this event.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$result = Rsvp::cancel( (int) $rsvp->comment_ID );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		// Re-fetch to include updated status.
		$comment = get_comment( $rsvp->comment_ID );
		$data    = Rsvp::prepare_for_response( $comment );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Permission check for marking attendance — organizer or co-organizer only.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function mark_attendance_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to mark attendance.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->is_organizer() ) {
			return new WP_Error(
				'rest_cannot_mark_attendance',
				__( 'Only organizers and co-organizers can mark attendance.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		$event = $this->validate_event( absint( $request->get_param( 'event_id' ) ) );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return true;
	}

	/**
	 * Mark attendance for a user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_attendance( WP_REST_Request $request ) {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$user_id  = absint( $request->get_param( 'user_id' ) );
		$attended = (bool) $request->get_param( 'attended' );

		$result = Rsvp::mark_attendance( $event_id, $user_id, $attended );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		$rsvp = Rsvp::get_user_rsvp( $event_id, $user_id );

		// If the RSVP was marked as no_show, get_user_rsvp won't find it
		// (it excludes not_attending). Fetch by searching all RSVPs.
		if ( ! $rsvp ) {
			$all_rsvps = get_comments( [
				'post_id' => $event_id,
				'user_id' => $user_id,
				'type'    => Rsvp::COMMENT_TYPE,
				'status'  => 'approve',
				'number'  => 1,
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			] );

			$rsvp = $all_rsvps[0] ?? null;
		}

		if ( ! $rsvp ) {
			return new WP_Error(
				'rest_rsvp_not_found',
				__( 'RSVP not found after marking attendance.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$data = Rsvp::prepare_for_response( $rsvp );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get the RSVP schema for responses.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'rsvp',
			'type'       => 'object',
			'properties' => [
				'id'                   => [
					'description' => __( 'Unique identifier for the RSVP.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'event_id'             => [
					'description' => __( 'The event post ID.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'user_id'              => [
					'description' => __( 'The user ID.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'display_name'         => [
					'description' => __( 'The user display name.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'avatar_url'           => [
					'description' => __( 'The user avatar URL.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'status'               => [
					'description' => __( 'The RSVP status.', 'wordpress-groups' ),
					'type'        => 'string',
					'enum'        => Rsvp::STATUSES,
					'context'     => [ 'view' ],
				],
				'guests'               => [
					'description' => __( 'Number of additional guests.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'attendance_confirmed' => [
					'description' => __( 'Whether attendance has been confirmed.', 'wordpress-groups' ),
					'type'        => 'boolean',
					'context'     => [ 'view' ],
				],
				'timestamp'            => [
					'description' => __( 'The RSVP creation time (UTC).', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
