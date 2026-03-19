<?php
/**
 * Status transition side effects handler.
 *
 * Handles logging and email notifications when wp_meetup posts
 * transition between statuses.
 *
 * @package Groups\Workflow
 */

namespace Groups\Workflow;

use Groups\Database\Activity_Log_Table;
use Groups\Notifications\Email_Notifier;

defined( 'ABSPATH' ) || exit;

/**
 * Listens for valid meetup status transitions and performs side effects:
 * activity logging and email notifications.
 */
class Status_Transition {

	/**
	 * Email notifier instance.
	 *
	 * @var Email_Notifier
	 */
	private Email_Notifier $email_notifier;

	/**
	 * Human-readable labels for statuses, used in notifications.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_LABELS = [
		'meetup-pending'     => 'Pending Review',
		'meetup-vetting'     => 'Under Review',
		'meetup-feedback'    => 'Awaiting Feedback',
		'meetup-orientation' => 'Orientation',
		'meetup-scheduling'  => 'Scheduling First Event',
		'meetup-active'      => 'Active',
		'meetup-dormant'     => 'Dormant',
		'meetup-suspended'   => 'Suspended',
		'meetup-removed'     => 'Removed',
		'meetup-declined'    => 'Declined',
	];

	/**
	 * Constructor.
	 *
	 * @param Email_Notifier|null $email_notifier Optional. Email notifier instance.
	 */
	public function __construct( ?Email_Notifier $email_notifier = null ) {
		$this->email_notifier = $email_notifier ?? new Email_Notifier();

		add_action( 'groups_meetup_status_transition', [ $this, 'log_transition' ], 10, 3 );
		add_action( 'groups_meetup_status_transition', [ $this, 'send_notification' ], 20, 3 );
	}

	/**
	 * Log the status transition to the activity log.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function log_transition( int $post_id, string $old_status, string $new_status ): void {
		$blog_id = get_current_blog_id();
		$user_id = get_current_user_id();

		Activity_Log_Table::insert(
			$blog_id,
			$user_id,
			'meetup_status_changed',
			$post_id,
			[
				'old_status' => $old_status,
				'new_status' => $new_status,
			]
		);
	}

	/**
	 * Send email notification for the status transition.
	 *
	 * Notifies the organizer (post author) about the status change.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function send_notification( int $post_id, string $old_status, string $new_status ): void {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$author = get_user_by( 'id', $post->post_author );

		if ( ! $author || ! $author->user_email ) {
			return;
		}

		$old_label = self::STATUS_LABELS[ $old_status ] ?? $old_status;
		$new_label = self::STATUS_LABELS[ $new_status ] ?? $new_status;
		$subject   = sprintf(
			/* translators: 1: group name, 2: new status label */
			__( 'Application Update: %1$s is now %2$s', 'wordpress-groups' ),
			$post->post_title,
			$new_label
		);

		/**
		 * Filters whether to send a status transition email notification.
		 *
		 * @param bool   $send       Whether to send the email. Default true.
		 * @param int    $post_id    The post ID.
		 * @param string $old_status Previous status.
		 * @param string $new_status New status.
		 */
		if ( ! apply_filters( 'groups_send_transition_email', true, $post_id, $old_status, $new_status ) ) {
			return;
		}

		$this->email_notifier->send(
			$author->user_email,
			$subject,
			'application-status',
			[
				'group_name'       => $post->post_title,
				'organizer_name'   => $author->display_name,
				'old_status'       => $old_status,
				'new_status'       => $new_status,
				'old_status_label' => $old_label,
				'new_status_label' => $new_label,
				'dashboard_url'    => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			]
		);
	}

	/**
	 * Get the human-readable label for a status.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function get_status_label( string $status ): string {
		return self::STATUS_LABELS[ $status ] ?? $status;
	}
}
