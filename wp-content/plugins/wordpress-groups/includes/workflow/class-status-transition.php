<?php
/**
 * Status transition side effects handler.
 *
 * Handles logging and email notifications when wp_meetup posts
 * transition between statuses. Ensures every valid transition
 * produces a context-specific HTML email to the organizer and,
 * where appropriate, to the site administrators.
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
	 * Status-specific messages shown to the organizer in the email body.
	 *
	 * Each key is the *new* status. The value is an HTML-safe message
	 * displayed inside the status card of the `application-status` template.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_MESSAGES = [
		'meetup-vetting'     => 'Your application has been received and is now being reviewed by the Community Team. We will be in touch soon.',
		'meetup-feedback'    => 'The Community Team has reviewed your application and has a few questions. Please check your dashboard for details and respond at your earliest convenience.',
		'meetup-orientation' => 'Great news! Your application has been approved. You are now entering the orientation phase. A mentor will be assigned to help you get started.',
		'meetup-scheduling'  => 'Orientation is complete! Your group site has been created and you can now schedule your first event. Visit your dashboard to get started.',
		'meetup-active'      => 'Congratulations! Your group is now fully active. Keep up the great work organizing events for the WordPress community!',
		'meetup-dormant'     => 'Your group has been marked as dormant due to inactivity. If you would like to reactivate it, please reach out to the Community Team through your dashboard.',
		'meetup-suspended'   => 'Your group has been suspended. Please contact the Community Team for more information about the next steps.',
		'meetup-removed'     => 'Your group has been removed from the WordPress Community program. If you believe this is an error, please contact the Community Team.',
		'meetup-declined'    => 'Unfortunately your application has not been approved at this time. You can find more details and contact the Community Team through your dashboard.',
	];

	/**
	 * Transitions that should also notify site administrators.
	 *
	 * These are statuses where an admin review or action is typically needed.
	 *
	 * @var string[]
	 */
	private const ADMIN_NOTIFY_STATUSES = [
		'meetup-pending',
		'meetup-feedback',
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
	 * Send email notifications for the status transition.
	 *
	 * Sends a context-specific email to the organizer (post author) for every
	 * transition. For transitions that require admin attention, a separate
	 * notification is also sent to site administrators.
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

		$this->notify_organizer( $post, $old_status, $new_status );
		$this->notify_admins( $post, $old_status, $new_status );
	}

	/**
	 * Send a status-change email to the organizer (post author).
	 *
	 * @param \WP_Post $post       The meetup post.
	 * @param string   $old_status Previous status.
	 * @param string   $new_status New status.
	 */
	private function notify_organizer( \WP_Post $post, string $old_status, string $new_status ): void {
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

		$this->email_notifier->send(
			$author->user_email,
			$subject,
			'application-status',
			[
				'user_name'        => $author->display_name,
				'group_name'       => $post->post_title,
				'organizer_name'   => $author->display_name,
				'old_status'       => $old_status,
				'new_status'       => $new_status,
				'old_status_label' => $old_label,
				'new_status_label' => $new_label,
				'status'           => $new_label,
				'status_message'   => self::STATUS_MESSAGES[ $new_status ] ?? '',
				'dashboard_url'    => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			]
		);
	}

	/**
	 * Send an admin notification when the new status requires reviewer attention.
	 *
	 * @param \WP_Post $post       The meetup post.
	 * @param string   $old_status Previous status.
	 * @param string   $new_status New status.
	 */
	private function notify_admins( \WP_Post $post, string $old_status, string $new_status ): void {
		if ( ! in_array( $new_status, self::ADMIN_NOTIFY_STATUSES, true ) ) {
			return;
		}

		/**
		 * Filters the email address that receives admin-level transition notifications.
		 *
		 * Defaults to the site admin email. Can be changed to a mailing list or
		 * distribution address.
		 *
		 * @param string $admin_email Admin email address.
		 * @param int    $post_id     The meetup post ID.
		 * @param string $new_status  The new status.
		 */
		$admin_email = apply_filters(
			'groups_transition_admin_email',
			get_option( 'admin_email' ),
			$post->ID,
			$new_status
		);

		if ( ! $admin_email ) {
			return;
		}

		$old_label = self::STATUS_LABELS[ $old_status ] ?? $old_status;
		$new_label = self::STATUS_LABELS[ $new_status ] ?? $new_status;

		$admin_message = match ( $new_status ) {
			'meetup-pending'  => sprintf(
				'A new meetup application for <strong>%s</strong> has been submitted and is awaiting review.',
				esc_html( $post->post_title )
			),
			'meetup-feedback' => sprintf(
				'The organizer of <strong>%s</strong> has responded to feedback. Please review the updated application.',
				esc_html( $post->post_title )
			),
			default           => '',
		};

		$subject = sprintf(
			/* translators: 1: group name, 2: new status label */
			__( 'Admin Notice: %1$s moved to %2$s', 'wordpress-groups' ),
			$post->post_title,
			$new_label
		);

		$this->email_notifier->send(
			$admin_email,
			$subject,
			'application-status',
			[
				'user_name'        => 'Community Team',
				'group_name'       => $post->post_title,
				'old_status'       => $old_status,
				'new_status'       => $new_status,
				'old_status_label' => $old_label,
				'new_status_label' => $new_label,
				'status'           => $new_label,
				'status_message'   => $admin_message,
				'dashboard_url'    => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
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

	/**
	 * Get the status-specific message for a given status.
	 *
	 * @param string $status Status slug.
	 * @return string The message, or an empty string if none is defined.
	 */
	public static function get_status_message( string $status ): string {
		return self::STATUS_MESSAGES[ $status ] ?? '';
	}
}
