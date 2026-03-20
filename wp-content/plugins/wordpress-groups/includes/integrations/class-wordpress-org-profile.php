<?php
/**
 * WordPress.org profile badge integration.
 *
 * Assigns organizer and attendee badges on WordPress.org profiles
 * when group status transitions occur, following the same pattern
 * used by WordCamp.org for badge assignment.
 *
 * @package Groups
 */

namespace Groups\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress_Org_Profile — assigns profile badges via the WordPress.org infrastructure.
 *
 * Listens for `groups_meetup_status_transition` and triggers badge assignment
 * for the group organizer when a group becomes active. Also provides a public
 * method for assigning attendee badges from other contexts.
 */
class WordPress_Org_Profile {

	/**
	 * Default API endpoint for badge assignment.
	 *
	 * @var string
	 */
	const DEFAULT_BADGE_API_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

	/**
	 * Valid badge types.
	 *
	 * @var array<string>
	 */
	const BADGE_TYPES = [
		'group-organizer',
		'group-attendee',
	];

	/**
	 * Constructor — registers action hooks.
	 */
	public function __construct() {
		add_action( 'groups_meetup_status_transition', [ $this, 'on_status_transition' ], 25, 3 );
	}

	/**
	 * Handle meetup status transitions.
	 *
	 * When a group transitions to `meetup-active`, assign the organizer badge
	 * to the post author (group organizer).
	 *
	 * @param int    $post_id    The wp_meetup post ID.
	 * @param string $old_status Previous status slug.
	 * @param string $new_status New status slug.
	 */
	public function on_status_transition( int $post_id, string $old_status, string $new_status ): void {
		if ( 'meetup-active' !== $new_status ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_author ) ) {
			return;
		}

		$this->assign_badge( (int) $post->post_author, 'group-organizer', [
			'post_id' => $post_id,
			'source'  => 'status_transition',
		] );
	}

	/**
	 * Assign a badge to a user's WordPress.org profile.
	 *
	 * Fires the `wporg_profile_badge_assign` action that the production
	 * WordPress.org infrastructure hooks into, matching the pattern used
	 * by WordCamp.org for badge assignment.
	 *
	 * @param int    $user_id    The WordPress user ID.
	 * @param string $badge_type Badge type: 'group-organizer' or 'group-attendee'.
	 * @param array  $context    Optional. Additional context for the badge assignment.
	 * @return bool True if the badge assignment action was fired, false on validation failure.
	 */
	public function assign_badge( int $user_id, string $badge_type, array $context = [] ): bool {
		if ( ! in_array( $badge_type, self::BADGE_TYPES, true ) ) {
			return false;
		}

		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$context = array_merge( $context, [
			'badge_type' => $badge_type,
			'user_id'    => $user_id,
			'plugin'     => 'wordpress-groups',
		] );

		/**
		 * Fires when a profile badge should be assigned to a user.
		 *
		 * The production WordPress.org infrastructure hooks into this action
		 * to update the user's profile badges on profiles.wordpress.org.
		 *
		 * @param int    $user_id    The WordPress user ID.
		 * @param string $badge_type Badge type: 'group-organizer' or 'group-attendee'.
		 * @param array  $context    Additional context including post_id, source, and plugin.
		 */
		do_action( 'wporg_profile_badge_assign', $user_id, $badge_type, $context );

		return true;
	}

	/**
	 * Get the badge API endpoint URL.
	 *
	 * @return string The filtered API endpoint URL.
	 */
	public function get_badge_api_url(): string {
		/**
		 * Filters the WordPress.org profile badge API endpoint URL.
		 *
		 * @param string $url The default API endpoint URL.
		 */
		return apply_filters( 'groups_profile_badge_url', self::DEFAULT_BADGE_API_URL );
	}
}
