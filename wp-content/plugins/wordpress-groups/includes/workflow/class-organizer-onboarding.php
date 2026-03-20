<?php
/**
 * Organizer onboarding — mentor assignment and orientation tracking.
 *
 * Manages the mentor assignment process and orientation checklist
 * for new group organizers during the onboarding phase.
 *
 * @package Groups\Workflow
 */

namespace Groups\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Handles mentor assignment and orientation checklist tracking
 * for wp_meetup posts in the orientation phase.
 */
class Organizer_Onboarding {

	/**
	 * Orientation checklist items with labels.
	 *
	 * @var array<string, string>
	 */
	private const CHECKLIST_ITEMS = [
		'profile_complete'    => 'WordPress.org profile complete',
		'coc_accepted'        => 'Code of Conduct accepted',
		'first_event_planned' => 'First event planned',
		'venue_confirmed'     => 'Venue confirmed',
	];

	/**
	 * Post meta key for mentor assignment.
	 *
	 * @var string
	 */
	private const MENTOR_META_KEY = '_meetup_mentor_assigned';

	/**
	 * Post meta key prefix for checklist items.
	 *
	 * @var string
	 */
	private const CHECKLIST_META_PREFIX = '_meetup_checklist_';

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'groups_onboarding_checklist_complete', [ $this, 'auto_transition_to_scheduling' ] );
	}

	/**
	 * Assign a mentor to a wp_meetup post.
	 *
	 * Sets the mentor user ID as post meta and adds the mentor as a
	 * co_organizer on the group's site (if one has been provisioned).
	 *
	 * @param int $post_id        The wp_meetup post ID.
	 * @param int $mentor_user_id The user ID of the mentor.
	 * @return bool True on success, false on failure.
	 */
	public function assign_mentor( int $post_id, int $mentor_user_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return false;
		}

		$user = get_user_by( 'id', $mentor_user_id );

		if ( ! $user ) {
			return false;
		}

		update_post_meta( $post_id, self::MENTOR_META_KEY, $mentor_user_id );

		// If a site has been provisioned for this group, add the mentor as co_organizer.
		$site_id = (int) get_post_meta( $post_id, '_meetup_site_id', true );

		if ( $site_id ) {
			add_user_to_blog( $site_id, $mentor_user_id, 'co_organizer' );
		}

		/**
		 * Fires after a mentor is assigned to a group.
		 *
		 * @param int $post_id        The wp_meetup post ID.
		 * @param int $mentor_user_id The mentor's user ID.
		 */
		do_action( 'groups_mentor_assigned', $post_id, $mentor_user_id );

		return true;
	}

	/**
	 * Get the mentor user ID for a wp_meetup post.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return int Mentor user ID, or 0 if no mentor is assigned.
	 */
	public function get_mentor( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::MENTOR_META_KEY, true );
	}

	/**
	 * Get the orientation checklist with completion status.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return array<string, array{label: string, completed: bool}> Checklist items.
	 */
	public function get_orientation_checklist( int $post_id ): array {
		$checklist = [];

		foreach ( self::CHECKLIST_ITEMS as $key => $label ) {
			$completed = (bool) get_post_meta( $post_id, self::CHECKLIST_META_PREFIX . $key, true );

			$checklist[ $key ] = [
				'label'     => $label,
				'completed' => $completed,
			];
		}

		return $checklist;
	}

	/**
	 * Mark a checklist item as complete.
	 *
	 * When all items are complete, fires the `groups_onboarding_checklist_complete`
	 * action to trigger the auto-transition to meetup-scheduling.
	 *
	 * @param int    $post_id  The wp_meetup post ID.
	 * @param string $item_key The checklist item key.
	 * @return bool True on success, false if the item key is invalid.
	 */
	public function complete_checklist_item( int $post_id, string $item_key ): bool {
		if ( ! array_key_exists( $item_key, self::CHECKLIST_ITEMS ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return false;
		}

		update_post_meta( $post_id, self::CHECKLIST_META_PREFIX . $item_key, 1 );

		/**
		 * Fires after a checklist item is completed.
		 *
		 * @param int    $post_id  The wp_meetup post ID.
		 * @param string $item_key The checklist item key.
		 */
		do_action( 'groups_checklist_item_completed', $post_id, $item_key );

		// Check if all items are now complete.
		if ( $this->is_checklist_complete( $post_id ) ) {
			/**
			 * Fires when all orientation checklist items are complete.
			 *
			 * @param int $post_id The wp_meetup post ID.
			 */
			do_action( 'groups_onboarding_checklist_complete', $post_id );
		}

		return true;
	}

	/**
	 * Check whether all checklist items are complete.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return bool True if all items are complete.
	 */
	public function is_checklist_complete( int $post_id ): bool {
		foreach ( self::CHECKLIST_ITEMS as $key => $label ) {
			if ( ! get_post_meta( $post_id, self::CHECKLIST_META_PREFIX . $key, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Auto-transition the wp_meetup post to meetup-scheduling when
	 * all checklist items are complete.
	 *
	 * Only transitions posts currently in the meetup-orientation status.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 */
	public function auto_transition_to_scheduling( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return;
		}

		if ( 'meetup-orientation' !== $post->post_status ) {
			return;
		}

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-scheduling',
		] );
	}

	/**
	 * Get the defined checklist item keys and labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_checklist_items(): array {
		return self::CHECKLIST_ITEMS;
	}
}
