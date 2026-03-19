<?php
/**
 * REST API controller for events.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Post_Types\Event;
use WP_Error;
use WP_Post;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRUD operations for the event post type via the REST API.
 */
class Event_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for event routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'events';

	/**
	 * Meta keys stored on event posts.
	 *
	 * @var string[]
	 */
	const META_KEYS = [
		'_event_start_date',
		'_event_end_date',
		'_event_timezone',
		'_event_venue_id',
		'_event_online_link',
		'_event_attendee_limit',
		'_event_waitlist_enabled',
		'_event_rsvp_open_date',
		'_event_rsvp_close_date',
		'_event_is_recurring',
		'_event_recurrence_rule',
	];

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'id' => [
							'description' => __( 'Unique identifier for the event.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => [
						'id' => [
							'description' => __( 'Unique identifier for the event.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Check whether the current user can manage events.
	 *
	 * Organizers, co-organizers, and administrators may create/update/delete events.
	 *
	 * @return bool
	 */
	private function can_manage_events(): bool {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		$user = wp_get_current_user();

		return in_array( 'organizer', (array) $user->roles, true )
			|| in_array( 'co-organizer', (array) $user->roles, true );
	}

	/**
	 * Permission check for listing events.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true
	 */
	public function get_items_permissions_check( $request ): true {
		return true;
	}

	/**
	 * Permission check for reading a single event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for creating an event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to create events.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->can_manage_events() ) {
			return new WP_Error(
				'rest_cannot_create_events',
				__( 'Sorry, you are not allowed to create events.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for updating an event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to update events.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->can_manage_events() ) {
			return new WP_Error(
				'rest_cannot_update_events',
				__( 'Sorry, you are not allowed to update events.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for deleting (cancelling) an event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to cancel events.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->can_manage_events() ) {
			return new WP_Error(
				'rest_cannot_delete_events',
				__( 'Sorry, you are not allowed to cancel events.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Retrieve a collection of events.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$args = [
			'post_type'      => Event::POST_TYPE,
			'posts_per_page' => absint( $request->get_param( 'per_page' ) ) ?: 10,
			'paged'          => absint( $request->get_param( 'page' ) ) ?: 1,
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_start_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'ASC',
			'post_status'    => $this->get_allowed_statuses(),
		];

		// Filter by status.
		$status = $request->get_param( 'status' );
		if ( $status && in_array( $status, Event::get_statuses(), true ) ) {
			$args['post_status'] = sanitize_text_field( $status );
		}

		// Filter by category.
		$category = $request->get_param( 'category' );
		if ( $category ) {
			$args['category_name'] = sanitize_text_field( $category );
		}

		// Date range filtering.
		$after  = $request->get_param( 'after' );
		$before = $request->get_param( 'before' );

		if ( $after || $before ) {
			$meta_query = [];

			if ( $after ) {
				$meta_query[] = [
					'key'     => '_event_start_date',
					'value'   => sanitize_text_field( $after ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				];
			}

			if ( $before ) {
				$meta_query[] = [
					'key'     => '_event_start_date',
					'value'   => sanitize_text_field( $before ),
					'compare' => '<=',
					'type'    => 'DATETIME',
				];
			}

			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}

			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query  = new \WP_Query( $args );
		$events = [];

		foreach ( $query->posts as $post ) {
			$events[] = $this->prepare_item_for_response( $post, $request )->get_data();
		}

		$response = new WP_REST_Response( $events, 200 );
		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Retrieve a single event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Create an event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$post_data = [
			'post_type'    => Event::POST_TYPE,
			'post_title'   => sanitize_text_field( $request->get_param( 'title' ) ),
			'post_content' => wp_kses_post( $request->get_param( 'content' ) ?? '' ),
			'post_excerpt' => sanitize_text_field( $request->get_param( 'excerpt' ) ?? '' ),
			'post_status'  => $this->sanitize_event_status( $request->get_param( 'status' ) ),
			'post_author'  => get_current_user_id(),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->update_event_meta( $post_id, $request );

		$post = get_post( $post_id );

		$response = $this->prepare_item_for_response( $post, $request );
		$response->set_status( 201 );
		$response->header(
			'Location',
			rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post_id ) )
		);

		return $response;
	}

	/**
	 * Update an event.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$post_data = [ 'ID' => $post_id ];

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$post_data['post_title'] = sanitize_text_field( $title );
		}

		$content = $request->get_param( 'content' );
		if ( null !== $content ) {
			$post_data['post_content'] = wp_kses_post( $content );
		}

		$excerpt = $request->get_param( 'excerpt' );
		if ( null !== $excerpt ) {
			$post_data['post_excerpt'] = sanitize_text_field( $excerpt );
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$post_data['post_status'] = $this->sanitize_event_status( $status );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_event_meta( $post_id, $request );

		$post = get_post( $post_id );

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Cancel an event (set status to event-cancelled).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post || Event::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$result = wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'event-cancelled',
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post = get_post( $post_id );

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Prepare a single event post for the response.
	 *
	 * @param WP_Post         $post    Post object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $post, $request ): WP_REST_Response {
		$meta = [];

		foreach ( self::META_KEYS as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			// Strip the leading underscore and prefix for the public key name.
			$public_key          = preg_replace( '/^_event_/', '', $key );
			$meta[ $public_key ] = $this->escape_meta_value( $key, $value );
		}

		$data = [
			'id'       => (int) $post->ID,
			'title'    => esc_html( $post->post_title ),
			'content'  => wp_kses_post( $post->post_content ),
			'excerpt'  => esc_html( $post->post_excerpt ),
			'status'   => esc_html( $post->post_status ),
			'author'   => (int) $post->post_author,
			'date'     => mysql_to_rfc3339( $post->post_date ),
			'modified' => mysql_to_rfc3339( $post->post_modified ),
			'link'     => esc_url( get_permalink( $post ) ),
			'meta'     => $meta,
		];

		$response = new WP_REST_Response( $data, 200 );
		$response->add_links( $this->prepare_links( $post ) );

		return $response;
	}

	/**
	 * Escape a meta value based on its key.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed Escaped value.
	 */
	private function escape_meta_value( string $key, $value ) {
		return match ( $key ) {
			'_event_venue_id',
			'_event_attendee_limit'   => absint( $value ),
			'_event_waitlist_enabled',
			'_event_is_recurring'     => (bool) $value,
			'_event_online_link'      => esc_url( $value ),
			default                   => sanitize_text_field( (string) $value ),
		};
	}

	/**
	 * Prepare links for the response.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, array<string, mixed>>
	 */
	private function prepare_links( WP_Post $post ): array {
		$base = sprintf( '%s/%s', $this->namespace, $this->rest_base );

		return [
			'self'       => [
				'href' => rest_url( sprintf( '%s/%d', $base, $post->ID ) ),
			],
			'collection' => [
				'href' => rest_url( $base ),
			],
		];
	}

	/**
	 * Update event meta fields from the request.
	 *
	 * @param int             $post_id Post ID.
	 * @param WP_REST_Request $request Full details about the request.
	 */
	private function update_event_meta( int $post_id, WP_REST_Request $request ): void {
		$meta = $request->get_param( 'meta' );

		if ( ! is_array( $meta ) ) {
			return;
		}

		$sanitizers = [
			'start_date'       => 'sanitize_text_field',
			'end_date'         => 'sanitize_text_field',
			'timezone'         => 'sanitize_text_field',
			'venue_id'         => 'absint',
			'online_link'      => 'esc_url_raw',
			'attendee_limit'   => 'absint',
			'waitlist_enabled' => [ $this, 'sanitize_boolean' ],
			'rsvp_open_date'   => 'sanitize_text_field',
			'rsvp_close_date'  => 'sanitize_text_field',
			'is_recurring'     => [ $this, 'sanitize_boolean' ],
			'recurrence_rule'  => 'sanitize_text_field',
		];

		foreach ( $sanitizers as $field => $sanitizer ) {
			if ( array_key_exists( $field, $meta ) ) {
				$meta_key = '_event_' . $field;
				$value    = call_user_func( $sanitizer, $meta[ $field ] );
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Sanitize a boolean value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_boolean( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize event status ensuring it is a valid custom status.
	 *
	 * @param string|null $status Raw status.
	 * @return string Sanitized status, defaults to event-draft.
	 */
	private function sanitize_event_status( ?string $status ): string {
		$status = sanitize_text_field( (string) $status );

		if ( in_array( $status, Event::get_statuses(), true ) ) {
			return $status;
		}

		return 'event-draft';
	}

	/**
	 * Get allowed post statuses for public queries.
	 *
	 * @return string[]
	 */
	private function get_allowed_statuses(): array {
		return Event::get_statuses();
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		$params = parent::get_collection_params();

		$params['status'] = [
			'description' => __( 'Limit results to events with a specific status.', 'wordpress-groups' ),
			'type'        => 'string',
			'enum'        => Event::get_statuses(),
		];

		$params['category'] = [
			'description' => __( 'Limit results to events in a specific category.', 'wordpress-groups' ),
			'type'        => 'string',
		];

		$params['after'] = [
			'description' => __( 'Limit results to events starting on or after this date (YYYY-MM-DD).', 'wordpress-groups' ),
			'type'        => 'string',
			'format'      => 'date',
		];

		$params['before'] = [
			'description' => __( 'Limit results to events starting on or before this date (YYYY-MM-DD).', 'wordpress-groups' ),
			'type'        => 'string',
			'format'      => 'date',
		];

		return $params;
	}

	/**
	 * Get the event schema for responses.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'event',
			'type'       => 'object',
			'properties' => [
				'id'       => [
					'description' => __( 'Unique identifier for the event.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'title'    => [
					'description' => __( 'The title for the event.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'required'    => true,
				],
				'content'  => [
					'description' => __( 'The content for the event.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'excerpt'  => [
					'description' => __( 'The excerpt for the event.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'status'   => [
					'description' => __( 'The status of the event.', 'wordpress-groups' ),
					'type'        => 'string',
					'enum'        => Event::get_statuses(),
					'context'     => [ 'view', 'edit' ],
				],
				'author'   => [
					'description' => __( 'The ID of the author of the event.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date'     => [
					'description' => __( 'The date the event was published, in RFC3339 format.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'modified' => [
					'description' => __( 'The date the event was last modified, in RFC3339 format.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'link'     => [
					'description' => __( 'URL to the event on the site.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'meta'     => [
					'description' => __( 'Event metadata.', 'wordpress-groups' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'start_date'       => [
							'description' => __( 'Event start date/time (UTC).', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'end_date'         => [
							'description' => __( 'Event end date/time (UTC).', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'timezone'         => [
							'description' => __( 'Event timezone identifier.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'venue_id'         => [
							'description' => __( 'ID of the associated venue.', 'wordpress-groups' ),
							'type'        => 'integer',
						],
						'online_link'      => [
							'description' => __( 'URL for online event access.', 'wordpress-groups' ),
							'type'        => 'string',
							'format'      => 'uri',
						],
						'attendee_limit'   => [
							'description' => __( 'Maximum number of attendees.', 'wordpress-groups' ),
							'type'        => 'integer',
						],
						'waitlist_enabled' => [
							'description' => __( 'Whether the waitlist is enabled.', 'wordpress-groups' ),
							'type'        => 'boolean',
						],
						'rsvp_open_date'   => [
							'description' => __( 'Date when RSVPs open.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'rsvp_close_date'  => [
							'description' => __( 'Date when RSVPs close.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'is_recurring'     => [
							'description' => __( 'Whether the event is recurring.', 'wordpress-groups' ),
							'type'        => 'boolean',
						],
						'recurrence_rule'  => [
							'description' => __( 'Recurrence rule string (RRULE format).', 'wordpress-groups' ),
							'type'        => 'string',
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
