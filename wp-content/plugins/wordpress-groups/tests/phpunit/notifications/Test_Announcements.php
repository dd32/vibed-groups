<?php
/**
 * Tests for the Announcements class.
 *
 * @package Groups\Tests
 */

use Groups\Database\Activity_Log_Table;
use Groups\Notifications\Announcements;
use Groups\Notifications\User_Preferences;

/**
 * @coversDefaultClass \Groups\Notifications\Announcements
 */
class Test_Announcements extends WP_UnitTestCase {

	/**
	 * Announcements instance under test.
	 *
	 * @var Announcements
	 */
	private Announcements $announcements;

	/**
	 * Blog ID of the test group site.
	 *
	 * @var int
	 */
	private int $blog_id;

	/**
	 * Sender user ID.
	 *
	 * @var int
	 */
	private int $sender_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->announcements = new Announcements();
		$this->blog_id       = get_current_blog_id();

		// Use the default admin user (ID 1) as sender so it is skipped
		// as a recipient and does not affect test counts.
		$this->sender_id = 1;
		wp_update_user( [
			'ID'         => 1,
			'user_email' => 'sender@example.org',
		] );
	}

	/**
	 * Helper: create a user and add them to the current blog.
	 *
	 * @param array $args User creation args.
	 * @return int User ID.
	 */
	private function create_member( array $args = [] ): int {
		$user_id = self::factory()->user->create( $args );
		add_user_to_blog( $this->blog_id, $user_id, 'subscriber' );
		return $user_id;
	}

	/**
	 * @covers ::send
	 */
	public function test_send_delivers_to_all_opted_in_members(): void {
		$member1 = $this->create_member( [ 'user_email' => 'member1@example.org' ] );
		$member2 = $this->create_member( [ 'user_email' => 'member2@example.org' ] );

		$recipients = [];
		add_action(
			'groups_before_email_send',
			function ( $to ) use ( &$recipients ) {
				$recipients[] = $to;
			}
		);

		$count = $this->announcements->send( $this->blog_id, $this->sender_id, 'Test Subject', 'Test body content.' );

		$this->assertSame( 2, $count );
		$this->assertContains( 'member1@example.org', $recipients );
		$this->assertContains( 'member2@example.org', $recipients );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_skips_opted_out_users(): void {
		$member1 = $this->create_member( [ 'user_email' => 'opted-in@example.org' ] );
		$member2 = $this->create_member( [ 'user_email' => 'opted-out@example.org' ] );

		User_Preferences::set_preference( $member2, 'announcements', false );

		$recipients = [];
		add_action(
			'groups_before_email_send',
			function ( $to ) use ( &$recipients ) {
				$recipients[] = $to;
			}
		);

		$count = $this->announcements->send( $this->blog_id, $this->sender_id, 'Test', 'Body.' );

		$this->assertSame( 1, $count );
		$this->assertContains( 'opted-in@example.org', $recipients );
		$this->assertNotContains( 'opted-out@example.org', $recipients );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_skips_the_sender(): void {
		$member = $this->create_member( [ 'user_email' => 'member@example.org' ] );

		$recipients = [];
		add_action(
			'groups_before_email_send',
			function ( $to ) use ( &$recipients ) {
				$recipients[] = $to;
			}
		);

		$this->announcements->send( $this->blog_id, $this->sender_id, 'Test', 'Body.' );

		$this->assertNotContains( 'sender@example.org', $recipients );
		$this->assertContains( 'member@example.org', $recipients );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_returns_zero_for_invalid_sender(): void {
		$count = $this->announcements->send( $this->blog_id, 999999, 'Test', 'Body.' );

		$this->assertSame( 0, $count );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_logs_to_activity_log(): void {
		$this->create_member( [ 'user_email' => 'member@example.org' ] );

		$this->announcements->send( $this->blog_id, $this->sender_id, 'Important Update', 'Body.' );

		$entries = Activity_Log_Table::query( [
			'blog_id' => $this->blog_id,
			'action'  => 'announcement_sent',
		] );

		$this->assertCount( 1, $entries );
		$this->assertSame( $this->sender_id, (int) $entries[0]->user_id );
		$this->assertSame( 'Important Update', $entries[0]->meta['subject'] );
		$this->assertSame( 1, $entries[0]->meta['recipients'] );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_fires_action_hook(): void {
		$this->create_member( [ 'user_email' => 'member@example.org' ] );

		$fired = false;
		add_action(
			'groups_announcement_sent',
			function ( $blog_id, $sender_id, $subject, $sent_count ) use ( &$fired ) {
				$fired = [
					'blog_id'    => $blog_id,
					'sender_id'  => $sender_id,
					'subject'    => $subject,
					'sent_count' => $sent_count,
				];
			},
			10,
			4
		);

		$this->announcements->send( $this->blog_id, $this->sender_id, 'Hello', 'World.' );

		$this->assertIsArray( $fired );
		$this->assertSame( $this->blog_id, $fired['blog_id'] );
		$this->assertSame( $this->sender_id, $fired['sender_id'] );
		$this->assertSame( 'Hello', $fired['subject'] );
		$this->assertSame( 1, $fired['sent_count'] );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_with_no_members_returns_zero(): void {
		// Remove all other users from this blog — only the sender is present.
		$count = $this->announcements->send( $this->blog_id, $this->sender_id, 'Test', 'Body.' );

		// Sender is skipped, so even if they are on the blog, count should be 0.
		$this->assertSame( 0, $count );
	}
}
