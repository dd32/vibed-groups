<?php
/**
 * Organizer onboarding: mentor assignment and orientation checklist.
 *
 * Manages the orientation phase of the meetup application workflow.
 * Provides mentor assignment, a four-item checklist, and automatic
 * transition to meetup-scheduling when all items are complete.
 *
 * @package Groups\Workflow
 */

namespace Groups\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Handles mentor assignment and orientation checklist for new organizers.
 */
class Organizer_Onboarding {

	/**
	 * Orientation checklist items.
	 *
	 * @var string[]
	 */
	public const CHECKLIST_ITEMS = [
		'profile_complete',
		'coc_accepted',
		'first_event_planned',
		'venue_confirmed',
	];

	/**
	 * Meta key prefix for checklist items.
	 *
	 * @var string
	 */
	private const META_PREFIX = '_meetup_checklist_';

	/**
	 * Meta key for the assigned mentor user ID.
	 *
	 * @var string
	 */
	private const MENTOR_META_KEY = '_meetup_mentor_user_id';

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'groups_meetup_status_transition', [ $this, 'on_orientation_entry' ], 10, 3 );
	}

	/**
	 * Assign a mentor to a meetup post.
	 *
	 * @param int $post_id  The wp_meetup post ID.
	 * @param int $user_id  The mentor's user ID.
	 * @return bool True on success, false on failure.
	 */
	public static function assign_mentor( int $post_id, int $user_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$result = update_post_meta( $post_id, self::MENTOR_META_KEY, $user_id );

		if ( $result ) {
			/**
			 * Fires when a mentor is assigned to a meetup group.
			 *
			 * @param int $post_id The wp_meetup post ID.
			 * @param int $user_id The mentor's user ID.
			 */
			do_action( 'groups_mentor_assigned', $post_id, $user_id );
		}

		return (bool) $result;
	}

	/**
	 * Get the assigned mentor for a meetup post.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return int|null Mentor user ID, or null if none assigned.
	 */
	public static function get_mentor( int $post_id ): ?int {
		$mentor_id = get_post_meta( $post_id, self::MENTOR_META_KEY, true );

		if ( ! $mentor_id ) {
			return null;
		}

		$mentor_id = (int) $mentor_id;

		// Verify the user still exists.
		$user = get_userdata( $mentor_id );

		return $user ? $mentor_id : null;
	}

	/**
	 * Mark a checklist item as complete for a meetup post.
	 *
	 * @param int    $post_id The wp_meetup post ID.
	 * @param string $item    Checklist item key.
	 * @return bool True on success, false on failure.
	 */
	public static function complete_checklist_item( int $post_id, string $item ): bool {
		if ( ! in_array( $item, self::CHECKLIST_ITEMS, true ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return false;
		}

		$result = update_post_meta( $post_id, self::META_PREFIX . $item, 1 );

		if ( $result ) {
			/**
			 * Fires when an orientation checklist item is completed.
			 *
			 * @param int    $post_id The wp_meetup post ID.
			 * @param string $item    The checklist item key.
			 */
			do_action( 'groups_checklist_item_completed', $post_id, $item );
		}

		// Check if all items are now complete and auto-transition.
		if ( self::is_checklist_complete( $post_id ) ) {
			self::maybe_auto_transition( $post_id );
		}

		return (bool) $result;
	}

	/**
	 * Unmark a checklist item (set as incomplete).
	 *
	 * @param int    $post_id The wp_meetup post ID.
	 * @param string $item    Checklist item key.
	 * @return bool True on success.
	 */
	public static function uncomplete_checklist_item( int $post_id, string $item ): bool {
		if ( ! in_array( $item, self::CHECKLIST_ITEMS, true ) ) {
			return false;
		}

		return delete_post_meta( $post_id, self::META_PREFIX . $item );
	}

	/**
	 * Check whether a specific checklist item is complete.
	 *
	 * @param int    $post_id The wp_meetup post ID.
	 * @param string $item    Checklist item key.
	 * @return bool
	 */
	public static function is_item_complete( int $post_id, string $item ): bool {
		if ( ! in_array( $item, self::CHECKLIST_ITEMS, true ) ) {
			return false;
		}

		return (bool) get_post_meta( $post_id, self::META_PREFIX . $item, true );
	}

	/**
	 * Get the full checklist status for a meetup post.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return array<string, bool> Map of item => complete status.
	 */
	public static function get_checklist_status( int $post_id ): array {
		$status = [];

		foreach ( self::CHECKLIST_ITEMS as $item ) {
			$status[ $item ] = self::is_item_complete( $post_id, $item );
		}

		return $status;
	}

	/**
	 * Check whether all checklist items are complete.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return bool
	 */
	public static function is_checklist_complete( int $post_id ): bool {
		foreach ( self::CHECKLIST_ITEMS as $item ) {
			if ( ! self::is_item_complete( $post_id, $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Auto-transition from meetup-orientation to meetup-scheduling
	 * when the checklist is fully complete.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 */
	private static function maybe_auto_transition( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'meetup-orientation' !== $post->post_status ) {
			return;
		}

		/**
		 * Filters whether to auto-transition on checklist completion.
		 *
		 * @param bool $auto_transition Whether to auto-transition. Default true.
		 * @param int  $post_id         The wp_meetup post ID.
		 */
		if ( ! apply_filters( 'groups_auto_transition_on_checklist_complete', true, $post_id ) ) {
			return;
		}

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-scheduling',
		] );
	}

	/**
	 * Handle entry into the orientation status.
	 *
	 * Resets checklist items when a post first enters orientation.
	 *
	 * @param int    $post_id    The wp_meetup post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function on_orientation_entry( int $post_id, string $old_status, string $new_status ): void {
		if ( 'meetup-orientation' !== $new_status ) {
			return;
		}

		// Reset all checklist items on entry.
		foreach ( self::CHECKLIST_ITEMS as $item ) {
			delete_post_meta( $post_id, self::META_PREFIX . $item );
		}

		/**
		 * Fires when a meetup enters the orientation phase.
		 *
		 * @param int $post_id The wp_meetup post ID.
		 */
		do_action( 'groups_orientation_started', $post_id );
	}

	/**
	 * Get human-readable labels for checklist items.
	 *
	 * @return array<string, string>
	 */
	public static function get_checklist_labels(): array {
		return [
			'profile_complete'    => __( 'Profile Complete', 'wordpress-groups' ),
			'coc_accepted'        => __( 'Code of Conduct Accepted', 'wordpress-groups' ),
			'first_event_planned' => __( 'First Event Planned', 'wordpress-groups' ),
			'venue_confirmed'     => __( 'Venue Confirmed', 'wordpress-groups' ),
		];
	}
}
