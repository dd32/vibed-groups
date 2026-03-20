<?php
/**
 * SEO structured data and social meta tags for events and venues.
 *
 * Outputs JSON-LD (Schema.org) structured data and Open Graph / Twitter Card
 * meta tags on singular event and venue pages.
 *
 * @package Groups
 */

namespace Groups;

use Groups\Post_Types\Event as Event_Post_Type;
use Groups\Post_Types\Venue as Venue_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * SEO class — hooks into wp_head to output structured data and social meta tags.
 */
class SEO {

	/**
	 * Constructor — registers the wp_head hook.
	 */
	public function __construct() {
		add_action( 'wp_head', [ $this, 'output_structured_data' ] );
		add_action( 'wp_head', [ $this, 'output_open_graph_tags' ] );
		add_action( 'wp_head', [ $this, 'output_twitter_card_tags' ] );
	}

	/**
	 * Output JSON-LD structured data on singular event and venue pages.
	 */
	public function output_structured_data(): void {
		if ( is_singular( Event_Post_Type::POST_TYPE ) ) {
			$this->output_event_jsonld( get_queried_object() );
		} elseif ( is_singular( Venue_Post_Type::POST_TYPE ) ) {
			$this->output_venue_jsonld( get_queried_object() );
		}
	}

	/**
	 * Output Open Graph meta tags on singular event pages.
	 */
	public function output_open_graph_tags(): void {
		if ( ! is_singular( Event_Post_Type::POST_TYPE ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$description = $this->get_description( $post );
		$url         = get_permalink( $post );
		$site_name   = get_bloginfo( 'name' );

		$tags = [
			'og:title'       => $post->post_title,
			'og:description' => $description,
			'og:type'        => 'event',
			'og:url'         => $url,
			'og:site_name'   => $site_name,
		];

		$thumbnail_url = get_the_post_thumbnail_url( $post, 'full' );
		if ( $thumbnail_url ) {
			$tags['og:image'] = $thumbnail_url;
		}

		foreach ( $tags as $property => $content ) {
			printf(
				'<meta property="%s" content="%s" />' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}

	/**
	 * Output Twitter Card meta tags on singular event pages.
	 */
	public function output_twitter_card_tags(): void {
		if ( ! is_singular( Event_Post_Type::POST_TYPE ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$description = $this->get_description( $post );

		$tags = [
			'twitter:card'        => 'summary_large_image',
			'twitter:title'       => $post->post_title,
			'twitter:description' => $description,
		];

		foreach ( $tags as $name => $content ) {
			printf(
				'<meta name="%s" content="%s" />' . "\n",
				esc_attr( $name ),
				esc_attr( $content )
			);
		}
	}

	/**
	 * Output JSON-LD for a single event.
	 *
	 * @param \WP_Post $post The event post object.
	 */
	private function output_event_jsonld( \WP_Post $post ): void {
		$start_utc   = get_post_meta( $post->ID, '_event_start_utc', true );
		$end_utc     = get_post_meta( $post->ID, '_event_end_utc', true );
		$timezone    = get_post_meta( $post->ID, '_event_timezone', true );
		$venue_id    = (int) get_post_meta( $post->ID, '_event_venue_id', true );
		$online_link = get_post_meta( $post->ID, '_event_online_link', true );
		$description = $this->get_description( $post );

		$data = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => $post->post_title,
			'url'         => get_permalink( $post ),
			'description' => $description,
		];

		if ( $start_utc ) {
			$data['startDate'] = $this->format_datetime( $start_utc, $timezone );
		}

		if ( $end_utc ) {
			$data['endDate'] = $this->format_datetime( $end_utc, $timezone );
		}

		// Event status based on post status.
		$data['eventStatus'] = $this->get_event_status( $post->post_status );

		// Attendance mode and location.
		$has_venue  = $venue_id && get_post( $venue_id );
		$has_online = ! empty( $online_link );

		if ( $has_venue && $has_online ) {
			$data['eventAttendanceMode'] = 'https://schema.org/MixedEventAttendanceMode';
		} elseif ( $has_online ) {
			$data['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
		} else {
			$data['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
		}

		if ( $has_venue ) {
			$data['location'] = $this->build_place_data( $venue_id );
		}

		if ( $has_online ) {
			$virtual_location = [
				'@type' => 'VirtualLocation',
				'url'   => $online_link,
			];

			if ( $has_venue ) {
				// Mixed mode: location is an array of physical + virtual.
				$data['location'] = [ $data['location'], $virtual_location ];
			} else {
				$data['location'] = $virtual_location;
			}
		}

		// Organizer — use the site name as the organizing group.
		$data['organizer'] = [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		];

		$this->print_jsonld( $data );
	}

	/**
	 * Output JSON-LD for a single venue.
	 *
	 * @param \WP_Post $post The venue post object.
	 */
	private function output_venue_jsonld( \WP_Post $post ): void {
		$data = $this->build_place_data( $post->ID );
		$data['@context'] = 'https://schema.org';

		$this->print_jsonld( $data );
	}

	/**
	 * Build a Schema.org Place data array from a venue post ID.
	 *
	 * @param int $venue_id The venue post ID.
	 * @return array<string, mixed> Schema.org Place data.
	 */
	private function build_place_data( int $venue_id ): array {
		$venue_post = get_post( $venue_id );

		$data = [
			'@type' => 'Place',
			'name'  => $venue_post ? $venue_post->post_title : '',
			'url'   => get_permalink( $venue_id ),
		];

		$address = get_post_meta( $venue_id, '_venue_address', true );
		$city    = get_post_meta( $venue_id, '_venue_city', true );
		$state   = get_post_meta( $venue_id, '_venue_state', true );
		$country = get_post_meta( $venue_id, '_venue_country', true );
		$zip     = get_post_meta( $venue_id, '_venue_zip', true );

		$postal_address = [ '@type' => 'PostalAddress' ];
		$has_address    = false;

		if ( $address ) {
			$postal_address['streetAddress'] = $address;
			$has_address = true;
		}
		if ( $city ) {
			$postal_address['addressLocality'] = $city;
			$has_address = true;
		}
		if ( $state ) {
			$postal_address['addressRegion'] = $state;
			$has_address = true;
		}
		if ( $country ) {
			$postal_address['addressCountry'] = $country;
			$has_address = true;
		}
		if ( $zip ) {
			$postal_address['postalCode'] = $zip;
			$has_address = true;
		}

		if ( $has_address ) {
			$data['address'] = $postal_address;
		}

		$latitude  = get_post_meta( $venue_id, '_venue_latitude', true );
		$longitude = get_post_meta( $venue_id, '_venue_longitude', true );

		if ( '' !== $latitude && '' !== $longitude ) {
			$data['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $latitude,
				'longitude' => (float) $longitude,
			];
		}

		return $data;
	}

	/**
	 * Format a UTC datetime string into ISO 8601 with timezone offset.
	 *
	 * @param string $utc_datetime UTC datetime string (Y-m-d H:i:s).
	 * @param string $timezone     Timezone identifier.
	 * @return string ISO 8601 formatted datetime.
	 */
	private function format_datetime( string $utc_datetime, string $timezone ): string {
		try {
			$dt = new \DateTimeImmutable( $utc_datetime, new \DateTimeZone( 'UTC' ) );

			if ( $timezone ) {
				$dt = $dt->setTimezone( new \DateTimeZone( $timezone ) );
			}

			return $dt->format( 'c' );
		} catch ( \Exception $e ) {
			// Fallback: return the raw UTC value with Z suffix.
			return str_replace( ' ', 'T', $utc_datetime ) . 'Z';
		}
	}

	/**
	 * Map event post status to Schema.org EventStatusType.
	 *
	 * @param string $post_status The event post status.
	 * @return string Schema.org EventStatusType URL.
	 */
	private function get_event_status( string $post_status ): string {
		return match ( $post_status ) {
			'event-cancelled' => 'https://schema.org/EventCancelled',
			'event-past'      => 'https://schema.org/EventScheduled',
			default           => 'https://schema.org/EventScheduled',
		};
	}

	/**
	 * Get a plain-text description for a post, falling back to an excerpt.
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Plain-text description, max 200 characters.
	 */
	private function get_description( \WP_Post $post ): string {
		$text = $post->post_excerpt ?: $post->post_content;
		$text = wp_strip_all_tags( $text );
		$text = str_replace( [ "\r\n", "\r", "\n" ], ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) > 200 ) {
			$text = mb_substr( $text, 0, 197 ) . '...';
		}

		return $text;
	}

	/**
	 * Print a JSON-LD script tag with escaped data.
	 *
	 * @param array<string, mixed> $data The structured data array.
	 */
	private function print_jsonld( array $data ): void {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! $json ) {
			return;
		}

		// Escape closing script tags within JSON to prevent XSS.
		$json = str_replace( '</', '<\/', $json );

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			$json
		);
	}
}
