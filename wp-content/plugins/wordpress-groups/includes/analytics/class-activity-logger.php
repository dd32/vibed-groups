<?php
/**
 * Activity logging system — hooks into significant actions and writes
 * to the groups_activity_log table.
 *
 * @package Groups\Analytics
 */

namespace Groups\Analytics;

use Groups\Database\Activity_Log_Table;
use Groups\Post_Types\Event as Event_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Listens for significant plugin actions and logs each one to the
 * network-wide activity log table for auditing and analytics.
 */
class Activity_Logger {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Membership actions — params match Membership model's do_action calls.
		add_action( 'groups_member_joined', [ $this, 'log_member_joined' ], 10, 2 );
		add_action( 'groups_member_left', [ $this, 'log_member_left' ], 10, 2 );
		add_action( 'groups_member_role_changed', [ $this, 'log_role_changed' ], 10, 4 );
		add_action( 'groups_member_banned', [ $this, 'log_member_banned' ], 10, 2 );

		// RSVP actions.
		add_action( 'groups_rsvp_created', [ $this, 'log_rsvp_created' ], 10, 4 );
		add_action( 'groups_rsvp_promoted', [ $this, 'log_rsvp_promoted' ], 10, 3 );

		// Event actions.
		add_action( 'groups_event_status_transition', [ $this, 'log_event_status_changed' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'log_event_created' ], 10, 3 );
	}

	/**
	 * Log a member joining a group.
	 *
	 * @param int $user_id User who joined.
	 * @param int $blog_id Blog ID of the group site.
	 */
	public function log_member_joined( int $user_id, int $blog_id ): void {
		Activity_Log_Table::insert(
			$blog_id,
			$user_id,
			'member_joined',
			$user_id,
			[]
		);
	}

	/**
	 * Log a member leaving a group.
	 *
	 * @param int $user_id User who left.
	 * @param int $blog_id Blog ID of the group site.
	 */
	public function log_member_left( int $user_id, int $blog_id ): void {
		Activity_Log_Table::insert(
			$blog_id,
			$user_id,
			'member_left',
			$user_id,
			[]
		);
	}

	/**
	 * Log a member role change.
	 *
	 * @param int    $user_id  User whose role changed.
	 * @param string $new_role New role.
	 * @param string $old_role Previous role.
	 * @param int    $blog_id  Blog ID of the group site.
	 */
	public function log_role_changed( int $user_id, string $new_role, string $old_role, int $blog_id ): void {
		Activity_Log_Table::insert(
			$blog_id,
			$user_id,
			'role_changed',
			$user_id,
			[
				'old_role' => $old_role,
				'new_role' => $new_role,
			]
		);
	}

	/**
	 * Log a member being banned.
	 *
	 * @param int $user_id User who was banned.
	 * @param int $blog_id Blog ID of the group site.
	 */
	public function log_member_banned( int $user_id, int $blog_id ): void {
		Activity_Log_Table::insert(
			$blog_id,
			$user_id,
			'member_banned',
			$user_id,
			[]
		);
	}

	/**
	 * Log an RSVP creation.
	 *
	 * Fires on the `groups_rsvp_created` action from the RSVP model.
	 *
	 * @param int    $comment_id The RSVP comment ID.
	 * @param int    $event_id   The event post ID.
	 * @param int    $user_id    The user who RSVPed.
	 * @param string $status     RSVP status (attending, waitlisted).
	 */
	public function log_rsvp_created( int $comment_id, int $event_id, int $user_id, string $status ): void {
		Activity_Log_Table::insert(
			get_current_blog_id(),
			$user_id,
			'rsvp_created',
			$event_id,
			[
				'comment_id' => $comment_id,
				'status'     => $status,
			]
		);
	}

	/**
	 * Log an RSVP promotion from waitlist.
	 *
	 * @param int $comment_id The RSVP comment ID.
	 * @param int $event_id   The event post ID.
	 * @param int $user_id    The user who was promoted.
	 */
	public function log_rsvp_promoted( int $comment_id, int $event_id, int $user_id ): void {
		Activity_Log_Table::insert(
			get_current_blog_id(),
			$user_id,
			'rsvp_promoted',
			$event_id,
			[ 'comment_id' => $comment_id ]
		);
	}

	/**
	 * Log an event status change.
	 *
	 * Fires on the `groups_event_status_transition` action.
	 *
	 * @param int    $event_id   The event post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function log_event_status_changed( int $event_id, string $old_status, string $new_status ): void {
		$post    = get_post( $event_id );
		$blog_id = get_current_blog_id();

		Activity_Log_Table::insert(
			$blog_id,
			$post ? (int) $post->post_author : 0,
			'event_status_changed',
			$event_id,
			[
				'old_status' => $old_status,
				'new_status' => $new_status,
			]
		);
	}

	/**
	 * Log the first publish of an event (event_created).
	 *
	 * Hooks into `transition_post_status` and only fires when the event
	 * post type transitions from a non-published status to a published
	 * or scheduled status for the first time.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       The post object.
	 */
	public function log_event_created( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		// Only log on first publish: transitioning from a draft/auto-draft
		// status to a published event status.
		$draft_statuses     = [ 'new', 'auto-draft', 'draft', 'event-draft' ];
		$published_statuses = [ 'publish', 'event-scheduled', 'event-active' ];

		if ( ! in_array( $old_status, $draft_statuses, true ) ) {
			return;
		}

		if ( ! in_array( $new_status, $published_statuses, true ) ) {
			return;
		}

		Activity_Log_Table::insert(
			get_current_blog_id(),
			(int) $post->post_author,
			'event_created',
			$post->ID,
			[ 'status' => $new_status ]
		);
	}
}
