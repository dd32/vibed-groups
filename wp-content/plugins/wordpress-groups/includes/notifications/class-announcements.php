<?php
/**
 * Group announcement system.
 *
 * Sends branded HTML email announcements from organizers to all group members.
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

use Groups\Database\Activity_Log_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Handles sending group-wide announcements to all members of a group site.
 *
 * Uses the `group-announcement.html` email template and respects per-user
 * notification preferences via User_Preferences.
 */
class Announcements {

	/**
	 * Email notifier instance.
	 *
	 * @var Email_Notifier
	 */
	private Email_Notifier $notifier;

	/**
	 * Constructor.
	 *
	 * @param Email_Notifier|null $notifier Optional. Email notifier instance.
	 */
	public function __construct( ?Email_Notifier $notifier = null ) {
		$this->notifier = $notifier ?? new Email_Notifier();
	}

	/**
	 * Send an announcement to all members of a group site.
	 *
	 * Retrieves all users on the specified blog, filters out those who have
	 * opted out of announcements, sends each a branded HTML email using the
	 * `group-announcement` template, and logs the action to the activity log.
	 *
	 * @param int    $blog_id   Blog ID of the group site.
	 * @param int    $sender_id User ID of the announcement sender.
	 * @param string $subject   Email subject line.
	 * @param string $body      Announcement body content (may contain HTML).
	 * @return int Number of emails sent successfully.
	 */
	public function send( int $blog_id, int $sender_id, string $subject, string $body ): int {
		$sender = get_userdata( $sender_id );

		if ( ! $sender ) {
			return 0;
		}

		// Switch to the target blog to get its users and details.
		switch_to_blog( $blog_id );

		$group_name = get_bloginfo( 'name' );
		$group_url  = home_url( '/' );
		$users      = get_users( [ 'blog_id' => $blog_id ] );

		restore_current_blog();

		$sent_count = 0;

		foreach ( $users as $user ) {
			// Skip the sender — they already know what they wrote.
			if ( $user->ID === $sender_id ) {
				continue;
			}

			// Respect user notification preferences.
			if ( ! User_Preferences::is_opted_in( $user->ID, 'announcements' ) ) {
				continue;
			}

			$template_data = [
				'announcement_title' => $subject,
				'announcement_body'  => $body,
				'user_name'          => $user->display_name,
				'group_name'         => $group_name,
				'group_url'          => $group_url,
				'email_subject'      => $subject,
				'preview_text'       => wp_strip_all_tags( $subject ),
			];

			$sent = $this->notifier->send(
				$user->user_email,
				$subject,
				'group-announcement',
				$template_data
			);

			if ( $sent ) {
				$sent_count++;
			}
		}

		// Log the announcement to the activity log.
		Activity_Log_Table::insert(
			$blog_id,
			$sender_id,
			'announcement_sent',
			0,
			[
				'subject'    => $subject,
				'recipients' => $sent_count,
			]
		);

		/**
		 * Fires after a group announcement has been sent.
		 *
		 * @param int    $blog_id    Blog ID of the group site.
		 * @param int    $sender_id  User ID of the sender.
		 * @param string $subject    Email subject.
		 * @param int    $sent_count Number of emails sent.
		 */
		do_action( 'groups_announcement_sent', $blog_id, $sender_id, $subject, $sent_count );

		return $sent_count;
	}
}
