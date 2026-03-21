<?php
/**
 * REST API controller for venues.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Post_Types\Venue;
use WP_Error;
use WP_Post;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRUD operations for the venue post type via the REST API.
 */
class Venue_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for venue routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'venues';

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
							'description' => __( 'Unique identifier for the venue.', 'wordpress-groups' ),
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
							'description' => __( 'Unique identifier for the venue.', 'wordpress-groups' ),
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
	 * Check whether the current user can manage venues.
	 *
	 * Only organizers, co-organizers, and administrators may manage venues.
	 *
	 * @return bool
	 */
	private function can_manage_venues(): bool {
		if ( is_super_admin() ) {
			return true;
		}

		$user = wp_get_current_user();

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}

		return in_array( 'organizer', (array) $user->roles, true )
			|| in_array( 'co_organizer', (array) $user->roles, true );
	}

	/**
	 * Permission check for listing venues.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true
	 */
	public function get_items_permissions_check( $request ): true {
		return true;
	}

	/**
	 * Permission check for reading a single venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Venue::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_venue_not_found',
				__( 'Venue not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		// Non-published venues require authorization.
		if ( 'publish' !== $post->post_status ) {
			if ( ! is_user_logged_in() || ! $this->can_manage_venues() ) {
				return new WP_Error(
					'rest_venue_not_found',
					__( 'Venue not found.', 'wordpress-groups' ),
					[ 'status' => 404 ]
				);
			}
		}

		return true;
	}

	/**
	 * Permission check for creating a venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to create venues.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->can_manage_venues() ) {
			return new WP_Error(
				'rest_cannot_create_venues',
				__( 'Sorry, you are not allowed to create venues.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for updating a venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Venue::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_venue_not_found',
				__( 'Venue not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to update venues.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->can_manage_venues() ) {
			return new WP_Error(
				'rest_cannot_update_venues',
				__( 'Sorry, you are not allowed to update this venue.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for deleting a venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Venue::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_venue_not_found',
				__( 'Venue not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to delete venues.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->can_manage_venues() ) {
			return new WP_Error(
				'rest_cannot_delete_venues',
				__( 'Sorry, you are not allowed to delete this venue.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Retrieve a collection of venues.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$args = [
			'post_type'      => Venue::POST_TYPE,
			'posts_per_page' => absint( $request->get_param( 'per_page' ) ) ?: 10,
			'paged'          => absint( $request->get_param( 'page' ) ) ?: 1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		// Search by keyword.
		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		// If the user can manage venues, also show drafts.
		if ( is_user_logged_in() && $this->can_manage_venues() ) {
			$args['post_status'] = [ 'publish', 'draft' ];
		}

		$query  = new \WP_Query( $args );
		$venues = [];

		foreach ( $query->posts as $post ) {
			$venues[] = $this->prepare_item_for_response( $post, $request )->get_data();
		}

		$response = new WP_REST_Response( $venues, 200 );
		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Retrieve a single venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || Venue::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_venue_not_found',
				__( 'Venue not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Create a venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$meta = $request->get_param( 'meta' );

		if ( is_array( $meta ) ) {
			$validation_error = $this->validate_venue_meta( $meta );
			if ( is_wp_error( $validation_error ) ) {
				return $validation_error;
			}
		}

		$post_data = [
			'post_type'    => Venue::POST_TYPE,
			'post_title'   => sanitize_text_field( $request->get_param( 'title' ) ),
			'post_content' => wp_kses_post( $request->get_param( 'content' ) ?? '' ),
			'post_status'  => $this->sanitize_post_status( $request->get_param( 'status' ) ),
			'post_author'  => get_current_user_id(),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->update_venue_meta( $post_id, $request );

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
	 * Update a venue.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post || Venue::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_venue_not_found',
				__( 'Venue not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$meta = $request->get_param( 'meta' );

		if ( is_array( $meta ) ) {
			$validation_error = $this->validate_venue_meta( $meta );
			if ( is_wp_error( $validation_error ) ) {
				return $validation_error;
			}
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

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$post_data['post_status'] = $this->sanitize_post_status( $status );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_venue_meta( $post_id, $request );

		$post = get_post( $post_id );

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Delete a venue (move to trash).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post || Venue::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_venue_not_found',
				__( 'Venue not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$previous = $this->prepare_item_for_response( $post, $request );

		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return new WP_Error(
				'rest_cannot_delete_venue',
				__( 'The venue could not be deleted.', 'wordpress-groups' ),
				[ 'status' => 500 ]
			);
		}

		$response = new WP_REST_Response();
		$response->set_data( [
			'deleted'  => true,
			'previous' => $previous->get_data(),
		] );

		return $response;
	}

	/**
	 * Prepare a single venue post for the response.
	 *
	 * @param WP_Post         $post    Post object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $post, $request ): WP_REST_Response {
		$meta = [];

		foreach ( Venue::META_KEYS as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			// Strip the leading underscore and prefix for the public key name.
			$public_key          = preg_replace( '/^_venue_/', '', $key );
			$meta[ $public_key ] = $this->escape_meta_value( $key, $value );
		}

		$data = [
			'id'       => (int) $post->ID,
			'title'    => esc_html( $post->post_title ),
			'content'  => wp_kses_post( $post->post_content ),
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
			'_venue_latitude',
			'_venue_longitude'  => (float) $value,
			'_venue_capacity'   => absint( $value ),
			'_venue_website'    => esc_url( $value ),
			default             => sanitize_text_field( (string) $value ),
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
	 * Validate venue meta fields.
	 *
	 * @param array $meta Meta values from the request.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	private function validate_venue_meta( array $meta ) {
		if ( array_key_exists( 'latitude', $meta ) && '' !== $meta['latitude'] ) {
			$lat = (float) $meta['latitude'];
			if ( $lat < -90.0 || $lat > 90.0 ) {
				return new WP_Error(
					'rest_invalid_latitude',
					__( 'Latitude must be between -90 and 90.', 'wordpress-groups' ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( array_key_exists( 'longitude', $meta ) && '' !== $meta['longitude'] ) {
			$lon = (float) $meta['longitude'];
			if ( $lon < -180.0 || $lon > 180.0 ) {
				return new WP_Error(
					'rest_invalid_longitude',
					__( 'Longitude must be between -180 and 180.', 'wordpress-groups' ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( array_key_exists( 'website', $meta ) && '' !== $meta['website'] ) {
			$url = esc_url_raw( $meta['website'], [ 'http', 'https' ] );
			if ( empty( $url ) ) {
				return new WP_Error(
					'rest_invalid_website',
					__( 'Website must be a valid HTTP or HTTPS URL.', 'wordpress-groups' ),
					[ 'status' => 400 ]
				);
			}
		}

		return true;
	}

	/**
	 * Update venue meta fields from the request.
	 *
	 * @param int             $post_id Post ID.
	 * @param WP_REST_Request $request Full details about the request.
	 */
	private function update_venue_meta( int $post_id, WP_REST_Request $request ): void {
		$meta = $request->get_param( 'meta' );

		if ( ! is_array( $meta ) ) {
			return;
		}

		$sanitizers = [
			'address'             => 'sanitize_text_field',
			'city'                => 'sanitize_text_field',
			'state'               => 'sanitize_text_field',
			'country'             => 'sanitize_text_field',
			'zip'                 => 'sanitize_text_field',
			'latitude'            => [ Venue::class, 'sanitize_latitude' ],
			'longitude'           => [ Venue::class, 'sanitize_longitude' ],
			'capacity'            => 'absint',
			'accessibility_notes' => 'sanitize_text_field',
			'website'             => [ Venue::class, 'sanitize_url' ],
		];

		foreach ( $sanitizers as $field => $sanitizer ) {
			if ( array_key_exists( $field, $meta ) ) {
				$meta_key = '_venue_' . $field;
				$value    = call_user_func( $sanitizer, $meta[ $field ] );
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Sanitize venue post status.
	 *
	 * @param string|null $status Raw status.
	 * @return string Sanitized status, defaults to draft.
	 */
	private function sanitize_post_status( ?string $status ): string {
		$allowed = [ 'publish', 'draft' ];
		$status  = sanitize_text_field( (string) $status );

		if ( in_array( $status, $allowed, true ) ) {
			return $status;
		}

		return 'draft';
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		$params = parent::get_collection_params();

		$params['search'] = [
			'description' => __( 'Search venues by keyword.', 'wordpress-groups' ),
			'type'        => 'string',
		];

		return $params;
	}

	/**
	 * Get the venue schema for responses.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'venue',
			'type'       => 'object',
			'properties' => [
				'id'       => [
					'description' => __( 'Unique identifier for the venue.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'title'    => [
					'description' => __( 'The title for the venue.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'required'    => true,
				],
				'content'  => [
					'description' => __( 'The content / description for the venue.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'status'   => [
					'description' => __( 'The status of the venue.', 'wordpress-groups' ),
					'type'        => 'string',
					'enum'        => [ 'publish', 'draft' ],
					'context'     => [ 'view', 'edit' ],
				],
				'author'   => [
					'description' => __( 'The ID of the author of the venue.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date'     => [
					'description' => __( 'The date the venue was created, in RFC3339 format.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'modified' => [
					'description' => __( 'The date the venue was last modified, in RFC3339 format.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'link'     => [
					'description' => __( 'URL to the venue on the site.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'meta'     => [
					'description' => __( 'Venue metadata.', 'wordpress-groups' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'address'             => [
							'description' => __( 'Street address.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'city'                => [
							'description' => __( 'City.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'state'               => [
							'description' => __( 'State or province.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'country'             => [
							'description' => __( 'Country.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'zip'                 => [
							'description' => __( 'Postal / ZIP code.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'latitude'            => [
							'description' => __( 'Latitude (-90 to 90).', 'wordpress-groups' ),
							'type'        => 'number',
						],
						'longitude'           => [
							'description' => __( 'Longitude (-180 to 180).', 'wordpress-groups' ),
							'type'        => 'number',
						],
						'capacity'            => [
							'description' => __( 'Maximum capacity.', 'wordpress-groups' ),
							'type'        => 'integer',
						],
						'accessibility_notes' => [
							'description' => __( 'Accessibility notes for the venue.', 'wordpress-groups' ),
							'type'        => 'string',
						],
						'website'             => [
							'description' => __( 'Venue website URL.', 'wordpress-groups' ),
							'type'        => 'string',
							'format'      => 'uri',
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
