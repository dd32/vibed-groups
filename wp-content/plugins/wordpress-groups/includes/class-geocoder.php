<?php
/**
 * Geocoder — auto-geocode venues via OpenStreetMap Nominatim.
 *
 * @package Groups
 */

namespace Groups;

use Groups\Post_Types\Venue;

defined( 'ABSPATH' ) || exit;

/**
 * Listens for venue saves and geocodes the address when lat/lon are missing.
 */
class Geocoder {

	/**
	 * Nominatim API endpoint.
	 *
	 * @var string
	 */
	const API_URL = 'https://nominatim.openstreetmap.org/search';

	/**
	 * Constructor — hook into venue save.
	 */
	public function __construct() {
		add_action( 'save_post_venue', [ $this, 'maybe_geocode' ], 20, 2 );
	}

	/**
	 * Geocode a venue on save if it has an address but no coordinates.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function maybe_geocode( int $post_id, \WP_Post $post ): void {
		// Don't run during autosave or revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$latitude  = get_post_meta( $post_id, '_venue_latitude', true );
		$longitude = get_post_meta( $post_id, '_venue_longitude', true );

		// Already has coordinates — skip.
		if ( '' !== $latitude && '' !== $longitude ) {
			return;
		}

		$address = $this->build_address_string( $post_id );

		if ( empty( $address ) ) {
			return;
		}

		$coords = $this->geocode( $address );

		if ( $coords ) {
			update_post_meta( $post_id, '_venue_latitude', $coords['lat'] );
			update_post_meta( $post_id, '_venue_longitude', $coords['lon'] );
		}
	}

	/**
	 * Build a geocodable address string from venue meta.
	 *
	 * @param int $post_id Venue post ID.
	 * @return string Address string, or empty if no address parts exist.
	 */
	private function build_address_string( int $post_id ): string {
		$parts = array_filter( [
			get_post_meta( $post_id, '_venue_address', true ),
			get_post_meta( $post_id, '_venue_city', true ),
			get_post_meta( $post_id, '_venue_state', true ),
			get_post_meta( $post_id, '_venue_zip', true ),
			get_post_meta( $post_id, '_venue_country', true ),
		] );

		return implode( ', ', $parts );
	}

	/**
	 * Query Nominatim for coordinates.
	 *
	 * @param string $address Human-readable address.
	 * @return array{lat: float, lon: float}|null Coordinates or null on failure.
	 */
	public function geocode( string $address ): ?array {
		$url = add_query_arg(
			[
				'q'      => $address,
				'format' => 'jsonv2',
				'limit'  => 1,
			],
			self::API_URL
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 5,
				'user-agent' => 'WordPress-Groups/0.1.0 (events.wordpress.org)',
				'headers'    => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body[0]['lat'] ) || empty( $body[0]['lon'] ) ) {
			return null;
		}

		return [
			'lat' => (float) $body[0]['lat'],
			'lon' => (float) $body[0]['lon'],
		];
	}
}
