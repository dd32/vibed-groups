<?php
/**
 * iCalendar export for per-group and per-event feeds.
 *
 * @package Groups\Calendar
 */

namespace Groups\Calendar;

use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Handles iCal export via ?ical=1 query parameter.
 *
 * - On a single event page: exports that single event as a VCALENDAR.
 * - On any other page within a group site: exports all upcoming events as a VCALENDAR.
 */
class ICal_Export {

	/**
	 * Constructor — hooks into template_redirect.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_ical_request' ] );
	}

	/**
	 * Intercept requests with ?ical=1 and serve iCal output.
	 */
	public function handle_ical_request(): void {
		if ( empty( $_GET['ical'] ) || '1' !== $_GET['ical'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( is_singular( Event_Post_Type::POST_TYPE ) ) {
			$post = get_queried_object();
			if ( $post ) {
				$this->serve_single_event( $post );
			}
		} else {
			$this->serve_group_feed();
		}
	}

	/**
	 * Serve a single event as an iCal download.
	 *
	 * @param \WP_Post $post The event post.
	 */
	private function serve_single_event( \WP_Post $post ): void {
		$events  = $this->build_vevents( [ $post ] );
		$vcalendar = $this->build_vcalendar( $events['vevents'], $events['timezones'] );

		$filename = sanitize_file_name( $post->post_title ) . '.ics';
		$this->send_headers( $filename );
		echo $vcalendar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Serve all upcoming events for the current group site as an iCal download.
	 */
	private function serve_group_feed(): void {
		$posts = get_posts( [
			'post_type'      => Event_Post_Type::POST_TYPE,
			'post_status'    => [ 'event-scheduled', 'event-active' ],
			'posts_per_page' => 100,
			'meta_key'       => '_event_start_utc',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => '_event_start_utc',
					'value'   => gmdate( 'Y-m-d H:i:s' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				],
			],
		] );

		$events    = $this->build_vevents( $posts );
		$vcalendar = $this->build_vcalendar( $events['vevents'], $events['timezones'] );

		$blogname = sanitize_file_name( get_bloginfo( 'name' ) ?: 'events' );
		$this->send_headers( $blogname . '.ics' );
		echo $vcalendar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Send iCal HTTP headers.
	 *
	 * @param string $filename The download filename.
	 */
	private function send_headers( string $filename ): void {
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );
	}

	/**
	 * Build VEVENT strings and collect required VTIMEZONE components.
	 *
	 * @param \WP_Post[] $posts Array of event posts.
	 * @return array{vevents: string[], timezones: array<string, string>}
	 */
	private function build_vevents( array $posts ): array {
		$vevents   = [];
		$timezones = [];

		foreach ( $posts as $post ) {
			$start_utc = get_post_meta( $post->ID, '_event_start_utc', true );
			$end_utc   = get_post_meta( $post->ID, '_event_end_utc', true );
			$timezone  = get_post_meta( $post->ID, '_event_timezone', true );
			$venue_id  = get_post_meta( $post->ID, '_event_venue_id', true );

			if ( empty( $start_utc ) || empty( $end_utc ) || empty( $timezone ) ) {
				continue;
			}

			$tz = self::get_timezone_safe( $timezone );
			if ( ! $tz ) {
				continue;
			}

			$tz_id = $tz->getName();

			// Convert UTC datetimes to local datetimes in the event's timezone.
			$dt_start = new \DateTime( $start_utc, new \DateTimeZone( 'UTC' ) );
			$dt_start->setTimezone( $tz );

			$dt_end = new \DateTime( $end_utc, new \DateTimeZone( 'UTC' ) );
			$dt_end->setTimezone( $tz );

			// Build location from venue.
			$location = '';
			if ( $venue_id ) {
				$venue = get_post( (int) $venue_id );
				if ( $venue ) {
					$location = $venue->post_title;
					$address  = get_post_meta( $venue->ID, '_venue_address', true );
					$city     = get_post_meta( $venue->ID, '_venue_city', true );
					if ( $address || $city ) {
						$parts = array_filter( [ $address, $city ] );
						if ( $parts ) {
							$location .= ', ' . implode( ', ', $parts );
						}
					}
				}
			}

			$uid         = $post->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
			$url         = get_permalink( $post->ID );
			$description = wp_strip_all_tags( $post->post_content );
			$summary     = $post->post_title;
			$dtstamp     = gmdate( 'Ymd\THis\Z' );

			$vevent  = "BEGIN:VEVENT\r\n";
			$vevent .= 'UID:' . self::escape_ical( $uid ) . "\r\n";
			$vevent .= 'DTSTAMP:' . $dtstamp . "\r\n";
			$vevent .= 'DTSTART;TZID=' . $tz_id . ':' . $dt_start->format( 'Ymd\THis' ) . "\r\n";
			$vevent .= 'DTEND;TZID=' . $tz_id . ':' . $dt_end->format( 'Ymd\THis' ) . "\r\n";
			$vevent .= 'SUMMARY:' . self::escape_ical( $summary ) . "\r\n";

			if ( $description ) {
				$vevent .= 'DESCRIPTION:' . self::escape_ical( $description ) . "\r\n";
			}

			if ( $location ) {
				$vevent .= 'LOCATION:' . self::escape_ical( $location ) . "\r\n";
			}

			if ( $url ) {
				$vevent .= 'URL:' . $url . "\r\n";
			}

			$vevent .= "END:VEVENT\r\n";

			$vevents[] = $vevent;

			// Collect timezone.
			if ( ! isset( $timezones[ $tz_id ] ) ) {
				$timezones[ $tz_id ] = self::build_vtimezone( $tz, $dt_start );
			}
		}

		return [
			'vevents'   => $vevents,
			'timezones' => $timezones,
		];
	}

	/**
	 * Build a complete VCALENDAR string.
	 *
	 * @param string[]                $vevents   Array of VEVENT strings.
	 * @param array<string, string>   $timezones Array of VTIMEZONE strings keyed by timezone ID.
	 * @return string Complete iCalendar output.
	 */
	public function build_vcalendar( array $vevents, array $timezones = [] ): string {
		$output  = "BEGIN:VCALENDAR\r\n";
		$output .= "VERSION:2.0\r\n";
		$output .= "PRODID:-//WordPress Groups//Events//EN\r\n";
		$output .= "CALSCALE:GREGORIAN\r\n";
		$output .= "METHOD:PUBLISH\r\n";

		foreach ( $timezones as $vtimezone ) {
			$output .= $vtimezone;
		}

		foreach ( $vevents as $vevent ) {
			$output .= $vevent;
		}

		$output .= "END:VCALENDAR\r\n";

		return $output;
	}

	/**
	 * Build a VTIMEZONE component for a given timezone.
	 *
	 * Generates STANDARD and DAYLIGHT sub-components based on the
	 * timezone transitions around the given reference date.
	 *
	 * @param \DateTimeZone $tz        The timezone object.
	 * @param \DateTime     $reference A reference datetime to determine transitions.
	 * @return string VTIMEZONE component.
	 */
	public static function build_vtimezone( \DateTimeZone $tz, \DateTime $reference ): string {
		$tz_id = $tz->getName();

		$output  = "BEGIN:VTIMEZONE\r\n";
		$output .= 'TZID:' . $tz_id . "\r\n";

		// Get transitions for the year of the reference date.
		$year_start  = new \DateTime( $reference->format( 'Y' ) . '-01-01', $tz );
		$year_end    = new \DateTime( ( (int) $reference->format( 'Y' ) + 1 ) . '-01-01', $tz );
		$transitions = $tz->getTransitions( $year_start->getTimestamp(), $year_end->getTimestamp() );

		if ( empty( $transitions ) || count( $transitions ) === 1 ) {
			// No DST — output a single STANDARD component.
			$offset = $tz->getOffset( $reference );
			$output .= "BEGIN:STANDARD\r\n";
			$output .= 'TZOFFSETFROM:' . self::format_utc_offset( $offset ) . "\r\n";
			$output .= 'TZOFFSETTO:' . self::format_utc_offset( $offset ) . "\r\n";
			$output .= "DTSTART:19700101T000000\r\n";
			$output .= "END:STANDARD\r\n";
		} else {
			// Find standard and daylight transitions.
			$prev_offset = self::format_utc_offset( $transitions[0]['offset'] );
			for ( $i = 1; $i < count( $transitions ); $i++ ) {
				$t      = $transitions[ $i ];
				$type   = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';
				$dt     = new \DateTime( '@' . $t['ts'] );
				$dt->setTimezone( $tz );

				$output .= 'BEGIN:' . $type . "\r\n";
				$output .= 'TZOFFSETFROM:' . $prev_offset . "\r\n";
				$output .= 'TZOFFSETTO:' . self::format_utc_offset( $t['offset'] ) . "\r\n";
				$output .= 'DTSTART:' . $dt->format( 'Ymd\THis' ) . "\r\n";
				$output .= 'TZNAME:' . $t['abbr'] . "\r\n";
				$output .= 'END:' . $type . "\r\n";

				$prev_offset = self::format_utc_offset( $t['offset'] );
			}
		}

		$output .= "END:VTIMEZONE\r\n";

		return $output;
	}

	/**
	 * Format a UTC offset in seconds to the iCal offset format (+/-HHMM).
	 *
	 * @param int $offset_seconds Offset in seconds.
	 * @return string Formatted offset, e.g. "+0530" or "-0500".
	 */
	public static function format_utc_offset( int $offset_seconds ): string {
		$sign    = $offset_seconds >= 0 ? '+' : '-';
		$abs     = abs( $offset_seconds );
		$hours   = intdiv( $abs, 3600 );
		$minutes = intdiv( $abs % 3600, 60 );

		return sprintf( '%s%02d%02d', $sign, $hours, $minutes );
	}

	/**
	 * Escape a string for use in iCalendar property values.
	 *
	 * Per RFC 5545, backslashes, semicolons, commas, and newlines must be escaped.
	 *
	 * @param string $text The raw text.
	 * @return string The escaped text.
	 */
	public static function escape_ical( string $text ): string {
		// Escape backslashes first.
		$text = str_replace( '\\', '\\\\', $text );
		// Escape semicolons.
		$text = str_replace( ';', '\;', $text );
		// Escape commas.
		$text = str_replace( ',', '\,', $text );
		// Escape newlines (both \r\n and \n).
		$text = str_replace( [ "\r\n", "\r", "\n" ], '\n', $text );

		return $text;
	}

	/**
	 * Safely create a DateTimeZone, returning null on invalid timezone strings.
	 *
	 * @param string $timezone Timezone identifier.
	 * @return \DateTimeZone|null
	 */
	private static function get_timezone_safe( string $timezone ): ?\DateTimeZone {
		try {
			return new \DateTimeZone( $timezone );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
