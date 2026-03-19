<?php
/**
 * Application workflow state machine for wp_meetup status transitions.
 *
 * Defines valid transitions, validates them on transition_post_status,
 * fires a custom action, and updates date meta fields.
 *
 * @package Groups\Workflow
 */

namespace Groups\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * State machine governing wp_meetup post status transitions.
 */
class Application_Workflow {

	/**
	 * Valid status transitions.
	 *
	 * Keys are "from" statuses, values are arrays of allowed "to" statuses.
	 *
	 * @var array<string, string[]>
	 */
	private const TRANSITIONS = [
		'meetup-pending'     => [ 'meetup-vetting', 'meetup-declined' ],
		'meetup-vetting'     => [ 'meetup-feedback', 'meetup-orientation', 'meetup-declined' ],
		'meetup-feedback'    => [ 'meetup-vetting', 'meetup-declined' ],
		'meetup-orientation' => [ 'meetup-scheduling' ],
		'meetup-scheduling'  => [ 'meetup-active' ],
		'meetup-active'      => [ 'meetup-dormant', 'meetup-suspended' ],
		'meetup-dormant'     => [ 'meetup-active', 'meetup-removed' ],
		'meetup-suspended'   => [ 'meetup-active', 'meetup-removed' ],
	];

	/**
	 * Map of statuses to date meta keys that should be set on entry.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_DATE_META = [
		'meetup-pending'     => '_meetup_application_date',
		'meetup-vetting'     => '_meetup_vetting_date',
		'meetup-feedback'    => '_meetup_feedback_date',
		'meetup-orientation' => '_meetup_orientation_date',
		'meetup-scheduling'  => '_meetup_scheduling_date',
		'meetup-active'      => '_meetup_activation_date',
		'meetup-dormant'     => '_meetup_dormant_date',
		'meetup-suspended'   => '_meetup_suspended_date',
		'meetup-removed'     => '_meetup_removed_date',
		'meetup-declined'    => '_meetup_declined_date',
	];

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'transition_post_status', [ $this, 'validate_transition' ], 5, 3 );
		add_action( 'transition_post_status', [ $this, 'handle_transition' ], 10, 3 );
	}

	/**
	 * Get the valid transitions map.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_transitions(): array {
		return self::TRANSITIONS;
	}

	/**
	 * Check whether a transition from one status to another is valid.
	 *
	 * @param string $old_status The current status.
	 * @param string $new_status The target status.
	 * @return bool
	 */
	public static function is_valid_transition( string $old_status, string $new_status ): bool {
		if ( $old_status === $new_status ) {
			return true;
		}

		if ( ! isset( self::TRANSITIONS[ $old_status ] ) ) {
			return false;
		}

		return in_array( $new_status, self::TRANSITIONS[ $old_status ], true );
	}

	/**
	 * Validate a status transition and block invalid ones.
	 *
	 * Hooked to `transition_post_status` at priority 5 (before side effects).
	 * For invalid transitions, reverts the post status to the old value.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function validate_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'wp_meetup' !== $post->post_type ) {
			return;
		}

		// Skip if this isn't a meetup-status transition.
		if ( ! $this->is_meetup_status( $old_status ) || ! $this->is_meetup_status( $new_status ) ) {
			return;
		}

		if ( $old_status === $new_status ) {
			return;
		}

		if ( ! self::is_valid_transition( $old_status, $new_status ) ) {
			// Revert to the old status silently.
			remove_action( 'transition_post_status', [ $this, 'validate_transition' ], 5 );
			remove_action( 'transition_post_status', [ $this, 'handle_transition' ], 10 );

			wp_update_post(
				[
					'ID'          => $post->ID,
					'post_status' => $old_status,
				]
			);

			add_action( 'transition_post_status', [ $this, 'validate_transition' ], 5, 3 );
			add_action( 'transition_post_status', [ $this, 'handle_transition' ], 10, 3 );

			/**
			 * Fires when an invalid meetup status transition is blocked.
			 *
			 * @param int    $post_id    Post ID.
			 * @param string $old_status The original status.
			 * @param string $new_status The attempted (invalid) status.
			 */
			do_action( 'groups_meetup_invalid_transition', $post->ID, $old_status, $new_status );
		}
	}

	/**
	 * Handle a valid status transition: update date meta, fire action.
	 *
	 * Hooked to `transition_post_status` at priority 10.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function handle_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'wp_meetup' !== $post->post_type ) {
			return;
		}

		if ( ! $this->is_meetup_status( $old_status ) || ! $this->is_meetup_status( $new_status ) ) {
			return;
		}

		if ( $old_status === $new_status ) {
			return;
		}

		if ( ! self::is_valid_transition( $old_status, $new_status ) ) {
			return;
		}

		// Update date meta for the new status.
		if ( isset( self::STATUS_DATE_META[ $new_status ] ) ) {
			update_post_meta( $post->ID, self::STATUS_DATE_META[ $new_status ], current_time( 'mysql', true ) );
		}

		/**
		 * Fires when a wp_meetup post transitions between valid statuses.
		 *
		 * @param int    $post_id    The post ID.
		 * @param string $old_status The previous status.
		 * @param string $new_status The new status.
		 */
		do_action( 'groups_meetup_status_transition', $post->ID, $old_status, $new_status );
	}

	/**
	 * Check whether a status string is a meetup workflow status.
	 *
	 * @param string $status Status slug.
	 * @return bool
	 */
	private function is_meetup_status( string $status ): bool {
		return str_starts_with( $status, 'meetup-' );
	}

	/**
	 * Get the date meta key for a given status.
	 *
	 * @param string $status Status slug.
	 * @return string|null Meta key or null if not mapped.
	 */
	public static function get_date_meta_key( string $status ): ?string {
		return self::STATUS_DATE_META[ $status ] ?? null;
	}
}
