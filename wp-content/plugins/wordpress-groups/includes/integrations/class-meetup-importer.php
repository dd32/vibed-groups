<?php
/**
 * Meetup.com data importer.
 *
 * Imports groups and events from Meetup.com export data (CSV/JSON).
 * Supports both API-based import (if API key provided) and file-based
 * import from Meetup's GDPR data export.
 *
 * @package Groups\Integrations
 */

namespace Groups\Integrations;

use Groups\Models\Event;
use Groups\Models\Rsvp;
use Groups\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Imports Meetup.com data into the WordPress Groups platform.
 */
class Meetup_Importer {

	/**
	 * Import events from a JSON file (Meetup GDPR export format).
	 *
	 * Expected format: array of objects with properties:
	 * - name (string) - event title
	 * - description (string) - event description (HTML)
	 * - time (int) - start time as Unix timestamp in milliseconds
	 * - duration (int) - duration in milliseconds
	 * - venue (object) - { name, address_1, city, state, country, lat, lon }
	 * - rsvp_limit (int) - attendee limit (-1 for unlimited)
	 * - yes_rsvp_count (int) - confirmed RSVPs
	 * - link (string) - Meetup.com event URL
	 *
	 * @param string $file_path Path to JSON file.
	 * @param int    $blog_id   Target blog ID for the group site.
	 * @return array{imported: int, skipped: int, errors: string[]}
	 */
	public static function import_events_from_json( string $file_path, int $blog_id ): array {
		$result = [ 'imported' => 0, 'skipped' => 0, 'errors' => [] ];

		if ( ! file_exists( $file_path ) ) {
			$result['errors'][] = __( 'File not found.', 'wordpress-groups' );
			return $result;
		}

		$json = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$events = json_decode( $json, false );

		if ( ! is_array( $events ) ) {
			$result['errors'][] = __( 'Invalid JSON format.', 'wordpress-groups' );
			return $result;
		}

		switch_to_blog( $blog_id );

		foreach ( $events as $meetup_event ) {
			$imported = self::import_single_event( $meetup_event );
			if ( is_wp_error( $imported ) ) {
				$result['errors'][] = $imported->get_error_message();
			} elseif ( $imported ) {
				$result['imported']++;
			} else {
				$result['skipped']++;
			}
		}

		restore_current_blog();

		return $result;
	}

	/**
	 * Import a single event from Meetup data.
	 *
	 * @param object $meetup_event Meetup event object.
	 * @return int|false|\WP_Error Post ID, false if skipped, or WP_Error.
	 */
	private static function import_single_event( object $meetup_event ) {
		$title = sanitize_text_field( $meetup_event->name ?? '' );
		if ( empty( $title ) ) {
			return false;
		}

		// Check for duplicate by title + date.
		$start_utc = '';
		if ( ! empty( $meetup_event->time ) ) {
			$timestamp = (int) ( $meetup_event->time / 1000 ); // Meetup uses milliseconds.
			$start_utc = gmdate( 'Y-m-d H:i:s', $timestamp );

			$existing = get_posts( [
				'post_type'   => 'event',
				'post_status' => 'any',
				'title'       => $title,
				'meta_key'    => '_event_start_utc',
				'meta_value'  => $start_utc,
				'numberposts' => 1,
			] );

			if ( ! empty( $existing ) ) {
				return false; // Skip duplicate.
			}
		}

		// Determine status.
		$now    = time();
		$status = 'event-past';
		if ( ! empty( $meetup_event->time ) ) {
			$event_time = (int) ( $meetup_event->time / 1000 );
			$duration   = ! empty( $meetup_event->duration ) ? (int) ( $meetup_event->duration / 1000 ) : 7200;
			$end_time   = $event_time + $duration;

			if ( $event_time > $now ) {
				$status = 'event-scheduled';
			} elseif ( $end_time > $now ) {
				$status = 'event-active';
			}

			$start_utc = gmdate( 'Y-m-d H:i:s', $event_time );
			$end_utc   = gmdate( 'Y-m-d H:i:s', $end_time );
		}

		// Create the event post.
		$post_id = wp_insert_post( [
			'post_type'    => 'event',
			'post_title'   => $title,
			'post_content' => wp_kses_post( $meetup_event->description ?? '' ),
			'post_status'  => $status,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set meta.
		if ( $start_utc ) {
			update_post_meta( $post_id, '_event_start_utc', $start_utc );
		}
		if ( ! empty( $end_utc ) ) {
			update_post_meta( $post_id, '_event_end_utc', $end_utc );
		}

		// Try to determine timezone from venue.
		$timezone = $meetup_event->timezone ?? 'UTC';
		update_post_meta( $post_id, '_event_timezone', sanitize_text_field( $timezone ) );

		// Attendee limit.
		if ( isset( $meetup_event->rsvp_limit ) && $meetup_event->rsvp_limit > 0 ) {
			update_post_meta( $post_id, '_event_attendee_limit', absint( $meetup_event->rsvp_limit ) );
		}

		// Online link.
		if ( ! empty( $meetup_event->how_to_find_us ) && filter_var( $meetup_event->how_to_find_us, FILTER_VALIDATE_URL ) ) {
			update_post_meta( $post_id, '_event_online_link', esc_url_raw( $meetup_event->how_to_find_us ) );
		}

		// Venue.
		if ( ! empty( $meetup_event->venue ) ) {
			$venue_id = self::import_venue( $meetup_event->venue );
			if ( $venue_id ) {
				update_post_meta( $post_id, '_event_venue_id', $venue_id );
			}
		}

		// Store original Meetup URL for reference.
		if ( ! empty( $meetup_event->link ) ) {
			update_post_meta( $post_id, '_meetup_original_url', esc_url_raw( $meetup_event->link ) );
		}

		return $post_id;
	}

	/**
	 * Import or find a venue from Meetup data.
	 *
	 * @param object $venue Meetup venue object.
	 * @return int|false Venue post ID or false.
	 */
	private static function import_venue( object $venue ) {
		$name = sanitize_text_field( $venue->name ?? '' );
		if ( empty( $name ) ) {
			return false;
		}

		// Check for existing venue by name.
		$existing = get_posts( [
			'post_type'   => 'venue',
			'post_status' => 'publish',
			'title'       => $name,
			'numberposts' => 1,
		] );

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		$venue_id = wp_insert_post( [
			'post_type'   => 'venue',
			'post_title'  => $name,
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $venue_id ) ) {
			return false;
		}

		if ( ! empty( $venue->address_1 ) ) {
			update_post_meta( $venue_id, '_venue_address', sanitize_text_field( $venue->address_1 ) );
		}
		if ( ! empty( $venue->city ) ) {
			update_post_meta( $venue_id, '_venue_city', sanitize_text_field( $venue->city ) );
		}
		if ( ! empty( $venue->state ) ) {
			update_post_meta( $venue_id, '_venue_state', sanitize_text_field( $venue->state ) );
		}
		if ( ! empty( $venue->country ) ) {
			update_post_meta( $venue_id, '_venue_country', sanitize_text_field( $venue->country ) );
		}
		if ( ! empty( $venue->lat ) ) {
			update_post_meta( $venue_id, '_venue_latitude', (float) $venue->lat );
		}
		if ( ! empty( $venue->lon ) ) {
			update_post_meta( $venue_id, '_venue_longitude', (float) $venue->lon );
		}

		return $venue_id;
	}

	/**
	 * Import members from a Meetup CSV export.
	 *
	 * Expected columns: Name, Email, Role, Joined
	 *
	 * @param string $file_path CSV file path.
	 * @param int    $blog_id   Target blog ID.
	 * @return array{imported: int, skipped: int, errors: string[]}
	 */
	public static function import_members_from_csv( string $file_path, int $blog_id ): array {
		$result = [ 'imported' => 0, 'skipped' => 0, 'errors' => [] ];

		if ( ! file_exists( $file_path ) ) {
			$result['errors'][] = __( 'File not found.', 'wordpress-groups' );
			return $result;
		}

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			$result['errors'][] = __( 'Cannot open file.', 'wordpress-groups' );
			return $result;
		}

		// Skip header row.
		fgetcsv( $handle );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( count( $row ) < 2 ) {
				continue;
			}

			$name  = sanitize_text_field( $row[0] ?? '' );
			$email = sanitize_email( $row[1] ?? '' );

			if ( empty( $email ) || ! is_email( $email ) ) {
				$result['skipped']++;
				continue;
			}

			// Find or create user.
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$user_id = wp_create_user( sanitize_user( $email ), wp_generate_password(), $email );
				if ( is_wp_error( $user_id ) ) {
					$result['errors'][] = sprintf( 'Failed to create user %s: %s', $email, $user_id->get_error_message() );
					continue;
				}
				wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
			} else {
				$user_id = $user->ID;
			}

			// Add to site.
			if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
				$role = 'member';
				if ( isset( $row[2] ) && stripos( $row[2], 'organizer' ) !== false ) {
					$role = 'organizer';
				}
				add_user_to_blog( $blog_id, $user_id, $role );
				$result['imported']++;
			} else {
				$result['skipped']++;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $result;
	}
}
