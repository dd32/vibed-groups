<?php
/**
 * REST API controller for the network-wide group directory.
 *
 * Queries wp_meetup posts on the main site to provide a public directory
 * of active community groups, with geo-filtering and per-group event listings.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Cache;
use Groups\Models\Location_Query;
use Groups\Post_Types\Event;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles directory endpoints for listing groups and their events network-wide.
 */
class Directory_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for directory routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'groups';

	/**
	 * Meetup post type on the central tracker site.
	 *
	 * @var string
	 */
	const MEETUP_POST_TYPE = 'wp_meetup';

	/**
	 * Public meetup statuses visible in the directory.
	 *
	 * @var string[]
	 */
	const PUBLIC_STATUSES = [
		'meetup-active',
	];

	/**
	 * All statuses that deputies/admins can filter by.
	 *
	 * @var string[]
	 */
	const ALL_STATUSES = [
		'meetup-pending',
		'meetup-vetting',
		'meetup-feedback',
		'meetup-orientation',
		'meetup-scheduling',
		'meetup-active',
		'meetup-dormant',
		'meetup-suspended',
		'meetup-removed',
		'meetup-declined',
	];

	/**
	 * Meta keys stored on wp_meetup posts that we expose in the API.
	 *
	 * @var string[]
	 */
	const META_KEYS = [
		'_meetup_city',
		'_meetup_country',
		'_meetup_latitude',
		'_meetup_longitude',
		'_meetup_member_count',
		'_meetup_last_event_date',
		'_meetup_site_id',
		'_meetup_timezone',
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
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<blog_id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'blog_id' => [
							'description' => __( 'Blog ID of the group site.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<blog_id>[\d]+)/events',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_group_events' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => [
						'blog_id'  => [
							'description' => __( 'Blog ID of the group site.', 'wordpress-groups' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'per_page' => [
							'description' => __( 'Maximum number of events to return.', 'wordpress-groups' ),
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						],
						'page'     => [
							'description' => __( 'Current page of the collection.', 'wordpress-groups' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check for listing groups.
	 *
	 * The directory is public — no auth required.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true
	 */
	public function get_items_permissions_check( $request ): true {
		return true;
	}

	/**
	 * Permission check for reading a single group.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true
	 */
	public function get_item_permissions_check( $request ): true {
		return true;
	}

	/**
	 * Retrieve a collection of groups from the directory.
	 *
	 * Switches to the main site to query wp_meetup posts, then switches back.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;
		$page     = absint( $request->get_param( 'page' ) ) ?: 1;

		// Check for geo-filter parameters.
		$lat    = $request->get_param( 'lat' );
		$lon    = $request->get_param( 'lon' );
		$radius = $request->get_param( 'radius' );

		// If geo-filtering is requested, use Location_Query.
		if ( null !== $lat && null !== $lon && null !== $radius ) {
			return $this->get_items_by_location( $request, (float) $lat, (float) $lon, (float) $radius );
		}

		// Build a cache key from the request parameters.
		$cache_params = [
			'per_page' => $per_page,
			'page'     => $page,
			'search'   => $request->get_param( 'search' ) ?? '',
			'status'   => $request->get_param( 'status' ) ?? '',
			'country'  => $request->get_param( 'country' ) ?? '',
			'admin'    => is_user_logged_in() && is_super_admin() ? '1' : '0',
		];
		$cache_key    = 'directory_list_' . md5( wp_json_encode( $cache_params ) );

		$cached = Cache::get( $cache_key, Cache::GROUP_DIRECTORY );
		if ( false !== $cached ) {
			$response = new WP_REST_Response( $cached['groups'], 200 );
			$response->header( 'X-WP-Total', $cached['total'] );
			$response->header( 'X-WP-TotalPages', $cached['total_pages'] );

			return $response;
		}

		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		$args = [
			'post_type'      => self::MEETUP_POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => $this->get_allowed_statuses( $request ),
		];

		// Search filter.
		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		// Status filter.
		$status           = $request->get_param( 'status' );
		$allowed_statuses = $this->get_allowed_statuses( $request );

		if ( $status && in_array( $status, $allowed_statuses, true ) ) {
			$args['post_status'] = sanitize_text_field( $status );
		}

		// Country filter.
		$country = $request->get_param( 'country' );
		if ( $country ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_meetup_country',
					'value' => sanitize_text_field( $country ),
				],
			];
		}

		$query  = new \WP_Query( $args );
		$groups = [];

		foreach ( $query->posts as $post ) {
			$groups[] = $this->prepare_item_for_response( $post, $request )->get_data();
		}

		restore_current_blog();

		$total       = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		// Cache the result for 5 minutes.
		Cache::set(
			$cache_key,
			[
				'groups'      => $groups,
				'total'       => $total,
				'total_pages' => $total_pages,
			],
			Cache::GROUP_DIRECTORY
		);

		$response = new WP_REST_Response( $groups, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Retrieve groups filtered by geographic proximity.
	 *
	 * @param WP_REST_Request $request   Full details about the request.
	 * @param float           $lat       Latitude.
	 * @param float           $lon       Longitude.
	 * @param float           $radius    Radius in km.
	 * @return WP_REST_Response|WP_Error
	 */
	private function get_items_by_location( WP_REST_Request $request, float $lat, float $lon, float $radius ) {
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;

		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		$status = $request->get_param( 'status' );
		$allowed_statuses = $this->get_allowed_statuses( $request );
		$post_status = ( $status && in_array( $status, $allowed_statuses, true ) )
			? $status
			: 'meetup-active';

		$results = Location_Query::find_nearby( $lat, $lon, $radius, [
			'post_type'   => self::MEETUP_POST_TYPE,
			'post_status' => $post_status,
			'limit'       => $per_page,
		] );

		if ( is_wp_error( $results ) ) {
			restore_current_blog();
			return $results;
		}

		$groups = [];
		foreach ( $results as $post ) {
			$item     = $this->prepare_item_for_response( $post, $request )->get_data();
			$item['distance'] = round( $post->distance, 2 );
			$groups[] = $item;
		}

		restore_current_blog();

		$response = new WP_REST_Response( $groups, 200 );
		$response->header( 'X-WP-Total', count( $groups ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Retrieve a single group by its blog ID.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$blog_id = absint( $request->get_param( 'blog_id' ) );

		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		$post = $this->get_meetup_post_by_blog_id( $blog_id );

		if ( ! $post ) {
			restore_current_blog();
			return new WP_Error(
				'rest_group_not_found',
				__( 'Group not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		$response = $this->prepare_item_for_response( $post, $request );

		restore_current_blog();

		return $response;
	}

	/**
	 * Retrieve upcoming events for a group by switching to the group's site.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_group_events( $request ) {
		$blog_id  = absint( $request->get_param( 'blog_id' ) );
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;
		$page     = absint( $request->get_param( 'page' ) ) ?: 1;

		// Verify the blog exists.
		$blog = get_blog_details( $blog_id );
		if ( ! $blog || $blog->deleted || $blog->archived ) {
			return new WP_Error(
				'rest_group_not_found',
				__( 'Group not found.', 'wordpress-groups' ),
				[ 'status' => 404 ]
			);
		}

		// Check the per-site event cache.
		$cache_key = 'upcoming_events_' . $blog_id . '_' . $per_page . '_' . $page;
		$cached    = Cache::get( $cache_key, Cache::GROUP_EVENTS );

		if ( false !== $cached ) {
			$response = new WP_REST_Response( $cached['events'], 200 );
			$response->header( 'X-WP-Total', $cached['total'] );
			$response->header( 'X-WP-TotalPages', $cached['total_pages'] );

			return $response;
		}

		switch_to_blog( $blog_id );

		$now = current_time( 'mysql', true );

		$args = [
			'post_type'      => Event::POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => [ 'event-scheduled', 'event-active' ],
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_event_start_utc',
					'value'   => $now,
					'compare' => '>=',
					'type'    => 'DATETIME',
				],
			],
		];

		$query  = new \WP_Query( $args );
		$events = [];

		foreach ( $query->posts as $post ) {
			$events[] = $this->prepare_event_for_response( $post );
		}

		restore_current_blog();

		$total       = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		// Cache for 5 minutes.
		Cache::set(
			$cache_key,
			[
				'events'      => $events,
				'total'       => $total,
				'total_pages' => $total_pages,
			],
			Cache::GROUP_EVENTS
		);

		$response = new WP_REST_Response( $events, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Prepare a single wp_meetup post for the response.
	 *
	 * @param \WP_Post|\stdClass $post    Post object.
	 * @param WP_REST_Request    $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $post, $request ): WP_REST_Response {
		$post_id = (int) $post->ID;

		$city            = get_post_meta( $post_id, '_meetup_city', true );
		$country         = get_post_meta( $post_id, '_meetup_country', true );
		$latitude        = get_post_meta( $post_id, '_meetup_latitude', true );
		$longitude       = get_post_meta( $post_id, '_meetup_longitude', true );
		$member_count    = get_post_meta( $post_id, '_meetup_member_count', true );
		$last_event_date = get_post_meta( $post_id, '_meetup_last_event_date', true );
		$site_id         = get_post_meta( $post_id, '_meetup_site_id', true );
		$timezone        = get_post_meta( $post_id, '_meetup_timezone', true );

		$site_url = '';
		if ( $site_id ) {
			$blog_details = get_blog_details( (int) $site_id );
			if ( $blog_details ) {
				$site_url = esc_url( $blog_details->siteurl );
			}
		}

		$data = [
			'id'              => $post_id,
			'name'            => esc_html( $post->post_title ),
			'status'          => esc_html( $post->post_status ),
			'city'            => sanitize_text_field( $city ),
			'country'         => sanitize_text_field( $country ),
			'latitude'        => (float) $latitude,
			'longitude'       => (float) $longitude,
			'member_count'    => absint( $member_count ),
			'last_event_date' => sanitize_text_field( $last_event_date ),
			'site_id'         => absint( $site_id ),
			'site_url'        => $site_url,
			'timezone'        => sanitize_text_field( $timezone ),
		];

		$response = new WP_REST_Response( $data, 200 );
		$response->add_links( $this->prepare_links( $post ) );

		return $response;
	}

	/**
	 * Prepare an event post for the directory events response.
	 *
	 * @param \WP_Post $post Event post object.
	 * @return array<string, mixed>
	 */
	private function prepare_event_for_response( \WP_Post $post ): array {
		return [
			'id'         => (int) $post->ID,
			'title'      => esc_html( $post->post_title ),
			'status'     => esc_html( $post->post_status ),
			'start_date' => sanitize_text_field( get_post_meta( $post->ID, '_event_start_utc', true ) ),
			'end_date'   => sanitize_text_field( get_post_meta( $post->ID, '_event_end_utc', true ) ),
			'timezone'   => sanitize_text_field( get_post_meta( $post->ID, '_event_timezone', true ) ),
			'excerpt'    => esc_html( $post->post_excerpt ),
			'link'       => esc_url( get_permalink( $post ) ),
		];
	}

	/**
	 * Find the wp_meetup post that references a given blog ID via _meetup_site_id.
	 *
	 * @param int $blog_id Blog ID to look up.
	 * @return \WP_Post|null
	 */
	private function get_meetup_post_by_blog_id( int $blog_id ): ?\WP_Post {
		$query = new \WP_Query( [
			'post_type'      => self::MEETUP_POST_TYPE,
			'posts_per_page' => 1,
			'post_status'    => self::ALL_STATUSES,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_meetup_site_id',
					'value' => $blog_id,
					'type'  => 'NUMERIC',
				],
			],
		] );

		if ( empty( $query->posts ) ) {
			return null;
		}

		return $query->posts[0];
	}

	/**
	 * Prepare links for the response.
	 *
	 * @param \WP_Post|\stdClass $post Post object.
	 * @return array<string, array<string, mixed>>
	 */
	private function prepare_links( $post ): array {
		$site_id = get_post_meta( (int) $post->ID, '_meetup_site_id', true );
		$base    = sprintf( '%s/%s', $this->namespace, $this->rest_base );

		$links = [
			'self'       => [
				'href' => rest_url( sprintf( '%s/%d', $base, absint( $site_id ) ) ),
			],
			'collection' => [
				'href' => rest_url( $base ),
			],
		];

		if ( $site_id ) {
			$links['events'] = [
				'href' => rest_url( sprintf( '%s/%d/events', $base, absint( $site_id ) ) ),
			];
		}

		return $links;
	}

	/**
	 * Get allowed post statuses for the current request.
	 *
	 * Anonymous users see only active groups. Admins can see all statuses.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return string[]
	 */
	private function get_allowed_statuses( WP_REST_Request $request ): array {
		if ( is_user_logged_in() && is_super_admin() ) {
			return self::ALL_STATUSES;
		}

		return self::PUBLIC_STATUSES;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		$params = parent::get_collection_params();

		$params['status'] = [
			'description' => __( 'Limit results to groups with a specific status.', 'wordpress-groups' ),
			'type'        => 'string',
			'enum'        => self::ALL_STATUSES,
		];

		$params['country'] = [
			'description' => __( 'Filter groups by country.', 'wordpress-groups' ),
			'type'        => 'string',
		];

		$params['lat'] = [
			'description' => __( 'Latitude for location-based search.', 'wordpress-groups' ),
			'type'        => 'number',
			'minimum'     => -90,
			'maximum'     => 90,
		];

		$params['lon'] = [
			'description' => __( 'Longitude for location-based search.', 'wordpress-groups' ),
			'type'        => 'number',
			'minimum'     => -180,
			'maximum'     => 180,
		];

		$params['radius'] = [
			'description' => __( 'Search radius in kilometres (requires lat and lon).', 'wordpress-groups' ),
			'type'        => 'number',
			'minimum'     => 0,
			'exclusiveMinimum' => true,
		];

		return $params;
	}

	/**
	 * Get the group schema for responses.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'group',
			'type'       => 'object',
			'properties' => [
				'id'              => [
					'description' => __( 'Unique identifier for the group (post ID).', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'name'            => [
					'description' => __( 'The name of the group.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'status'          => [
					'description' => __( 'The status of the group.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'city'            => [
					'description' => __( 'The city of the group.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'country'         => [
					'description' => __( 'The country of the group.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'latitude'        => [
					'description' => __( 'Latitude of the group location.', 'wordpress-groups' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'longitude'       => [
					'description' => __( 'Longitude of the group location.', 'wordpress-groups' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'member_count'    => [
					'description' => __( 'Number of members in the group.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'last_event_date' => [
					'description' => __( 'Date of the last event.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'site_id'         => [
					'description' => __( 'Blog ID of the group site.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'site_url'        => [
					'description' => __( 'URL of the group site.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'timezone'        => [
					'description' => __( 'Timezone of the group.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
