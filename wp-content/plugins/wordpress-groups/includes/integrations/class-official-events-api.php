<?php
/**
 * REST API feed endpoint for the official-wordpress-events plugin.
 *
 * Replaces Meetup.com as the data source by exposing a public, paginated,
 * cacheable endpoint that returns events across all group sites in the
 * network in the format the aggregation plugin expects.
 *
 * @package Groups
 */

namespace Groups\Integrations;

use Groups\Post_Types\Event;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Official_Events_API — network-wide events feed for the
 * official-wordpress-events aggregation plugin.
 */
class Official_Events_API extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * REST base path.
	 *
	 * @var string
	 */
	protected $rest_base = 'integration/events-feed';

	/**
	 * Default number of events per page.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Maximum number of events per page.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Cache max-age in seconds (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_MAX_AGE = 300;

	/**
	 * Constructor — hooks into rest_api_init.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the events-feed route.
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
	}

	/**
	 * Public endpoint — no authentication required.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true
	 */
	public function get_items_permissions_check( $request ): true {
		return true;
	}

	/**
	 * Retrieve events across all group sites in the network.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) ( $request->get_param( 'per_page' ) ?: self::DEFAULT_PER_PAGE ) ) );

		$events      = [];
		$total       = 0;
		$site_events = $this->query_network_events( $page, $per_page, $total );

		foreach ( $site_events as $item ) {
			$events[] = $this->format_event( $item );
		}

		$total_pages = (int) ceil( $total / $per_page );

		$response = new WP_REST_Response( $events, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		// Cache-Control headers.
		$response->header( 'Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE );

		// ETag for conditional requests.
		$etag = $this->generate_etag( $events );
		$response->header( 'ETag', $etag );

		// Handle If-None-Match for 304 responses.
		$if_none_match = $request->get_header( 'If-None-Match' );
		if ( $if_none_match && trim( $if_none_match, '" ' ) === trim( $etag, '"' ) ) {
			return new WP_REST_Response( null, 304 );
		}

		return $response;
	}

	/**
	 * Query events across all sites in the network.
	 *
	 * Iterates over group sites, collecting upcoming/active events,
	 * then sorts by start date and applies pagination.
	 *
	 * @param int $page     Current page number.
	 * @param int $per_page Events per page.
	 * @param int $total    Total event count (passed by reference).
	 * @return array Array of event data arrays.
	 */
	protected function query_network_events( int $page, int $per_page, int &$total ): array {
		$all_events = [];

		$sites = get_sites( [
			'number'   => 0, // All sites.
			'public'   => 1,
			'archived' => 0,
			'deleted'  => 0,
			'spam'     => 0,
			'fields'   => 'ids',
		] );

		foreach ( $sites as $site_id ) {
			$site_id = (int) $site_id;

			switch_to_blog( $site_id );

			// Only query sites that have the event post type registered.
			if ( ! post_type_exists( Event::POST_TYPE ) ) {
				restore_current_blog();
				continue;
			}

			$query = new \WP_Query( [
				'post_type'      => Event::POST_TYPE,
				'post_status'    => [ 'event-scheduled', 'event-active' ],
				'posts_per_page' => -1,
				'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			] );

			$blog_details = get_blog_details( $site_id );
			$group_name   = $blog_details ? $blog_details->blogname : '';
			$group_url    = $blog_details ? $blog_details->siteurl : '';

			foreach ( $query->posts as $post ) {
				$venue_id = (int) get_post_meta( $post->ID, '_event_venue_id', true );

				$location = [
					'latitude'  => '',
					'longitude' => '',
					'city'      => '',
					'country'   => '',
				];

				if ( $venue_id ) {
					$location = [
						'latitude'  => (string) get_post_meta( $venue_id, '_venue_latitude', true ),
						'longitude' => (string) get_post_meta( $venue_id, '_venue_longitude', true ),
						'city'      => (string) get_post_meta( $venue_id, '_venue_city', true ),
						'country'   => (string) get_post_meta( $venue_id, '_venue_country', true ),
					];
				}

				$all_events[] = [
					'post'       => $post,
					'blog_id'    => $site_id,
					'group_name' => $group_name,
					'group_url'  => $group_url,
					'location'   => $location,
					'start_date' => (string) get_post_meta( $post->ID, '_event_start_utc', true ),
					'end_date'   => (string) get_post_meta( $post->ID, '_event_end_utc', true ),
					'permalink'  => get_permalink( $post ),
				];
			}

			restore_current_blog();
		}

		// Sort by start date ascending.
		usort( $all_events, function ( $a, $b ) {
			return strcmp( $a['start_date'], $b['start_date'] );
		} );

		$total  = count( $all_events );
		$offset = ( $page - 1 ) * $per_page;

		return array_slice( $all_events, $offset, $per_page );
	}

	/**
	 * Format an event data array into the structure expected by
	 * the official-wordpress-events plugin.
	 *
	 * @param array $item Event data from query_network_events().
	 * @return array Formatted event.
	 */
	protected function format_event( array $item ): array {
		$post = $item['post'];

		return [
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'description' => wp_strip_all_tags( $post->post_content ),
			'url'         => esc_url( $item['permalink'] ),
			'date'        => $item['start_date'],
			'end_date'    => $item['end_date'],
			'location'    => [
				'latitude'  => $item['location']['latitude'],
				'longitude' => $item['location']['longitude'],
				'city'      => $item['location']['city'],
				'country'   => $item['location']['country'],
			],
			'group'       => [
				'name' => $item['group_name'],
				'url'  => esc_url( $item['group_url'] ),
			],
		];
	}

	/**
	 * Generate an ETag from the response data.
	 *
	 * @param array $events Formatted events array.
	 * @return string Quoted ETag value.
	 */
	protected function generate_etag( array $events ): string {
		return '"' . md5( wp_json_encode( $events ) ) . '"';
	}

	/**
	 * Get the query parameters for the collection.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return [
			'page'     => [
				'description'       => __( 'Current page of the collection.', 'wordpress-groups' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
			'per_page' => [
				'description'       => __( 'Maximum number of items to return per page.', 'wordpress-groups' ),
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Get the schema for the events feed.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'events-feed',
			'type'       => 'object',
			'properties' => [
				'title'       => [
					'description' => __( 'Event title.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'description' => [
					'description' => __( 'Plain text event description.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'url'         => [
					'description' => __( 'Event URL.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view' ],
				],
				'date'        => [
					'description' => __( 'Event start date/time in UTC (Y-m-d H:i:s).', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'end_date'    => [
					'description' => __( 'Event end date/time in UTC (Y-m-d H:i:s).', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'location'    => [
					'description' => __( 'Event location details.', 'wordpress-groups' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'latitude'  => [
							'type' => 'string',
						],
						'longitude' => [
							'type' => 'string',
						],
						'city'      => [
							'type' => 'string',
						],
						'country'   => [
							'type' => 'string',
						],
					],
				],
				'group'       => [
					'description' => __( 'Group that hosts the event.', 'wordpress-groups' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'name' => [
							'type' => 'string',
						],
						'url'  => [
							'type'   => 'string',
							'format' => 'uri',
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
