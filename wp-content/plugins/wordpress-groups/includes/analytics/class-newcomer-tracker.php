<?php
/**
 * Newcomer tracker — first-event detection on RSVP creation.
 *
 * When a user RSVPs for the first time, this component flags the RSVP
 * with `_rsvp_is_first_event` and fires the `groups_newcomer_detected`
 * action so other subsystems (emails, analytics) can react.
 *
 * @package Groups\Analytics
 */

namespace Groups\Analytics;

use Groups\Models\Rsvp;

defined( 'ABSPATH' ) || exit;

/**
 * Detects first-time attendees by checking attendance history across
 * all sites in the multisite network.
 */
class Newcomer_Tracker {

	/**
	 * User meta key that caches whether a user has attended any event.
	 *
	 * @var string
	 */
	const HAS_ATTENDED_META = '_groups_has_attended';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'groups_rsvp_created', [ $this, 'check_newcomer' ], 10, 4 );
	}

	/**
	 * Check whether the user who just RSVPed is a newcomer.
	 *
	 * Runs on the `groups_rsvp_created` action fired by the RSVP model.
	 *
	 * @param int    $comment_id The RSVP comment ID.
	 * @param int    $event_id   The event post ID.
	 * @param int    $user_id    The user ID.
	 * @param string $status     The RSVP status (attending or waitlisted).
	 */
	public function check_newcomer( int $comment_id, int $event_id, int $user_id, string $status ): void {
		if ( self::has_attended_before( $user_id ) ) {
			return;
		}

		update_comment_meta( $comment_id, '_rsvp_is_first_event', 1 );

		/**
		 * Fires when a newcomer (first-time attendee) is detected.
		 *
		 * @param int $user_id    The user ID.
		 * @param int $event_id   The event post ID.
		 * @param int $comment_id The RSVP comment ID.
		 */
		do_action( 'groups_newcomer_detected', $user_id, $event_id, $comment_id );
	}

	/**
	 * Determine whether a user has ever had a confirmed attendance.
	 *
	 * Checks a cached user meta flag first. If not set, performs a
	 * cross-site query across all network sites looking for any RSVP
	 * with `_rsvp_attendance_confirmed` = true.
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if the user has attended at least one event before.
	 */
	public static function has_attended_before( int $user_id ): bool {
		// Fast path: check cached user meta.
		$cached = get_user_meta( $user_id, self::HAS_ATTENDED_META, true );

		if ( '1' === $cached ) {
			return true;
		}

		// Cross-site lookup for any confirmed attendance.
		if ( is_multisite() ) {
			$sites = get_sites( [
				'fields' => 'ids',
				'number' => 0,
			] );

			foreach ( $sites as $blog_id ) {
				switch_to_blog( $blog_id );

				$found = self::has_confirmed_rsvp_on_current_site( $user_id );

				restore_current_blog();

				if ( $found ) {
					// Cache the result so we never scan again for this user.
					update_user_meta( $user_id, self::HAS_ATTENDED_META, '1' );
					return true;
				}
			}
		} else {
			// Single-site fallback.
			if ( self::has_confirmed_rsvp_on_current_site( $user_id ) ) {
				update_user_meta( $user_id, self::HAS_ATTENDED_META, '1' );
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the user has any RSVP with confirmed attendance on
	 * the current site (must be called within the correct blog context).
	 *
	 * @param int $user_id The user ID.
	 * @return bool
	 */
	private static function has_confirmed_rsvp_on_current_site( int $user_id ): bool {
		$comments = get_comments( [
			'user_id' => $user_id,
			'type'    => Rsvp::COMMENT_TYPE,
			'status'  => 'approve',
			'number'  => 1,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_rsvp_attendance_confirmed',
					'value' => '1',
				],
			],
		] );

		return ! empty( $comments );
	}
}
