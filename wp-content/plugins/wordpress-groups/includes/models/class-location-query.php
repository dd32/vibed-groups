<?php
/**
 * Location-based search using the Haversine formula.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Queries posts by geographic proximity using Haversine distance calculation.
 */
class Location_Query {

	/**
	 * Earth's mean radius in kilometres.
	 *
	 * @var float
	 */
	const EARTH_RADIUS_KM = 6371.0;

	/**
	 * Meta key mappings for supported post types.
	 *
	 * @var array<string, array{lat: string, lon: string}>
	 */
	const META_KEYS = [
		'wp_meetup' => [
			'lat' => '_meetup_latitude',
			'lon' => '_meetup_longitude',
		],
		'venue' => [
			'lat' => '_venue_latitude',
			'lon' => '_venue_longitude',
		],
	];

	/**
	 * Find posts within a given radius of a geographic point.
	 *
	 * Uses the Haversine formula in SQL to calculate the great-circle
	 * distance between two points on the Earth's surface.
	 *
	 * @param float $lat       Latitude of the search origin (-90 to 90).
	 * @param float $lon       Longitude of the search origin (-180 to 180).
	 * @param float $radius_km Search radius in kilometres.
	 * @param array $args {
	 *     Optional arguments.
	 *
	 *     @type string $post_type   Post type to query. Default 'venue'.
	 *                               Accepts 'venue' or 'wp_meetup'.
	 *     @type string $post_status Post status to filter by. Default 'publish'.
	 *     @type int    $limit       Maximum number of results per page. Default 50.
	 *     @type int    $page        Page number (1-based). Default 1.
	 * }
	 * @return array{results: object[], total: int}|\WP_Error Associative array with
	 *     'results' (post objects with a `distance` property in km, ordered by distance)
	 *     and 'total' (total matching count), or WP_Error on invalid arguments.
	 */
	public static function find_nearby( float $lat, float $lon, float $radius_km, array $args = [] ): array|\WP_Error {
		global $wpdb;

		// Validate coordinates.
		if ( $lat < -90.0 || $lat > 90.0 ) {
			return new \WP_Error(
				'invalid_latitude',
				__( 'Latitude must be between -90 and 90.', 'wordpress-groups' )
			);
		}

		if ( $lon < -180.0 || $lon > 180.0 ) {
			return new \WP_Error(
				'invalid_longitude',
				__( 'Longitude must be between -180 and 180.', 'wordpress-groups' )
			);
		}

		if ( $radius_km <= 0.0 ) {
			return new \WP_Error(
				'invalid_radius',
				__( 'Radius must be a positive number.', 'wordpress-groups' )
			);
		}

		$post_type   = $args['post_type'] ?? 'venue';
		$post_status = $args['post_status'] ?? 'publish';
		$limit       = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$page        = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset      = ( $page - 1 ) * $limit;

		if ( ! isset( self::META_KEYS[ $post_type ] ) ) {
			return new \WP_Error(
				'unsupported_post_type',
				sprintf(
					/* translators: %s: post type name */
					__( 'Location queries are not supported for the "%s" post type.', 'wordpress-groups' ),
					$post_type
				)
			);
		}

		$lat_key = self::META_KEYS[ $post_type ]['lat'];
		$lon_key = self::META_KEYS[ $post_type ]['lon'];

		/*
		 * Haversine formula:
		 *   d = R * acos(
		 *       cos(radians(lat1)) * cos(radians(lat2)) *
		 *       cos(radians(lon2) - radians(lon1)) +
		 *       sin(radians(lat1)) * sin(radians(lat2))
		 *   )
		 *
		 * We use %f for all float parameters in $wpdb->prepare().
		 */
		$haversine = sprintf(
			'( %f * ACOS( GREATEST( -1, LEAST( 1, COS( RADIANS(%%f) ) * COS( RADIANS( lat_meta.meta_value ) ) * COS( RADIANS( lon_meta.meta_value ) - RADIANS(%%f) ) + SIN( RADIANS(%%f) ) * SIN( RADIANS( lat_meta.meta_value ) ) ) ) ) )',
			self::EARTH_RADIUS_KM
		);

		$inner_query = "SELECT p.*, {$haversine} AS distance
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} lat_meta
					ON p.ID = lat_meta.post_id AND lat_meta.meta_key = %s
				INNER JOIN {$wpdb->postmeta} lon_meta
					ON p.ID = lon_meta.post_id AND lon_meta.meta_key = %s
				WHERE p.post_type = %s
					AND p.post_status = %s
					AND lat_meta.meta_value != ''
					AND lon_meta.meta_value != ''";

		// Count total matching rows.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM ( {$inner_query} ) AS nearby WHERE distance <= %f",
			$lat,
			$lon,
			$lat,
			$lat_key,
			$lon_key,
			$post_type,
			$post_status,
			$radius_km
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is fully prepared above.
		$total = (int) $wpdb->get_var( $count_sql );

		// Fetch the paginated results.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM ( {$inner_query} ) AS nearby
			WHERE distance <= %f
			ORDER BY distance ASC
			LIMIT %d OFFSET %d",
			$lat,
			$lon,
			$lat,
			$lat_key,
			$lon_key,
			$post_type,
			$post_status,
			$radius_km,
			$limit,
			$offset
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is fully prepared above.
		$results = $wpdb->get_results( $sql );

		if ( null === $results ) {
			return new \WP_Error(
				'query_failed',
				__( 'Location query failed.', 'wordpress-groups' )
			);
		}

		// Cast distance to float for each result.
		foreach ( $results as $result ) {
			$result->distance = (float) $result->distance;
		}

		return [
			'results' => $results,
			'total'   => $total,
		];
	}
}
