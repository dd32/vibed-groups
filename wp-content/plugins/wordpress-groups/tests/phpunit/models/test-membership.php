<?php
/**
 * Tests for the Membership model.
 *
 * @package Groups\Tests
 */

namespace Groups\Tests\Models;

use Groups\Models\Membership;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Groups\Models\Membership
 * @group multisite
 */
class Test_Membership extends WP_UnitTestCase {

	/**
	 * Test blog ID (a secondary site in the multisite network).
	 *
	 * @var int
	 */
	private int $blog_id;

	/**
	 * An organizer user ID for authorization in mutating methods.
	 *
	 * @var int
	 */
	private int $organizer_id;

	/**
	 * Set up a test site and register roles before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite tests require a multisite installation.' );
		}

		$this->blog_id = self::factory()->blog->create();

		// Register custom roles on the test site.
		switch_to_blog( $this->blog_id );
		Membership::register_roles();
		restore_current_blog();

		// Create an organizer to act as the authorized actor for mutating methods.
		$this->organizer_id = self::factory()->user->create();
		Membership::join( $this->organizer_id, $this->blog_id );

		// Promote to organizer directly (bypassing change_role auth check for setup).
		switch_to_blog( $this->blog_id );
		$user = new \WP_User( $this->organizer_id );
		$user->set_role( 'organizer' );
		restore_current_blog();
	}

	/**
	 * @covers ::register_roles
	 */
	public function test_register_roles_creates_custom_roles(): void {
		switch_to_blog( $this->blog_id );

		$organizer    = get_role( 'organizer' );
		$co_organizer = get_role( 'co_organizer' );
		$member       = get_role( 'member' );

		restore_current_blog();

		$this->assertNotNull( $organizer );
		$this->assertNotNull( $co_organizer );
		$this->assertNotNull( $member );

		$this->assertTrue( $organizer->has_cap( 'manage_options' ) );
		$this->assertTrue( $organizer->has_cap( 'edit_others_posts' ) );

		$this->assertTrue( $co_organizer->has_cap( 'edit_others_posts' ) );
		$this->assertFalse( $co_organizer->has_cap( 'manage_options' ) );

		$this->assertTrue( $member->has_cap( 'read' ) );
		$this->assertFalse( $member->has_cap( 'edit_posts' ) );
	}

	/**
	 * @covers ::join
	 */
	public function test_join_adds_user_to_site(): void {
		$user_id = self::factory()->user->create();

		$result = Membership::join( $user_id, $this->blog_id );

		$this->assertTrue( $result );
		$this->assertTrue( is_user_member_of_blog( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::join
	 */
	public function test_join_assigns_member_role(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );

		$role = Membership::get_user_role( $user_id, $this->blog_id );
		$this->assertSame( 'member', $role );
	}

	/**
	 * @covers ::join
	 */
	public function test_join_fires_action_hook(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		add_action(
			'groups_member_joined',
			function ( $uid, $bid ) use ( $user_id, &$fired ) {
				$fired = true;
				$this->assertSame( $user_id, $uid );
				$this->assertSame( $this->blog_id, $bid );
			},
			10,
			2
		);

		Membership::join( $user_id, $this->blog_id );

		$this->assertTrue( $fired, 'The groups_member_joined action should have fired.' );
	}

	/**
	 * @covers ::leave
	 */
	public function test_leave_removes_user_from_site(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		$result = Membership::leave( $user_id, $this->blog_id );

		$this->assertTrue( $result );
		$this->assertFalse( is_user_member_of_blog( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::leave
	 */
	public function test_leave_fires_action_hook(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		Membership::join( $user_id, $this->blog_id );

		add_action(
			'groups_member_left',
			function ( $uid, $bid ) use ( $user_id, &$fired ) {
				$fired = true;
				$this->assertSame( $user_id, $uid );
				$this->assertSame( $this->blog_id, $bid );
			},
			10,
			2
		);

		Membership::leave( $user_id, $this->blog_id );

		$this->assertTrue( $fired, 'The groups_member_left action should have fired.' );
	}

	/**
	 * @covers ::change_role
	 */
	public function test_change_role_works(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		$result = Membership::change_role( $user_id, 'organizer', $this->blog_id, $this->organizer_id );

		$this->assertTrue( $result );
		$this->assertSame( 'organizer', Membership::get_user_role( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::change_role
	 */
	public function test_change_role_to_co_organizer(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		Membership::change_role( $user_id, 'co_organizer', $this->blog_id, $this->organizer_id );

		$this->assertSame( 'co_organizer', Membership::get_user_role( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::change_role
	 */
	public function test_change_role_invalid_role_returns_error(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		$result = Membership::change_role( $user_id, 'superadmin', $this->blog_id, $this->organizer_id );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_role', $result->get_error_code() );
	}

	/**
	 * @covers ::change_role
	 */
	public function test_change_role_non_member_returns_error(): void {
		$user_id = self::factory()->user->create();

		$result = Membership::change_role( $user_id, 'organizer', $this->blog_id, $this->organizer_id );

		$this->assertWPError( $result );
		$this->assertSame( 'not_a_member', $result->get_error_code() );
	}

	/**
	 * @covers ::change_role
	 */
	public function test_change_role_fires_action_hook(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		Membership::join( $user_id, $this->blog_id );

		add_action(
			'groups_member_role_changed',
			function ( $uid, $new_role, $old_role, $bid ) use ( $user_id, &$fired ) {
				$fired = true;
				$this->assertSame( $user_id, $uid );
				$this->assertSame( 'organizer', $new_role );
				$this->assertSame( 'member', $old_role );
				$this->assertSame( $this->blog_id, $bid );
			},
			10,
			4
		);

		Membership::change_role( $user_id, 'organizer', $this->blog_id, $this->organizer_id );

		$this->assertTrue( $fired, 'The groups_member_role_changed action should have fired.' );
	}

	/**
	 * @covers ::change_role
	 */
	public function test_unauthorized_role_change_returns_wp_error(): void {
		$user_id    = self::factory()->user->create();
		$non_org_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		Membership::join( $non_org_id, $this->blog_id );

		// non_org_id is a regular member, not an organizer.
		$result = Membership::change_role( $user_id, 'organizer', $this->blog_id, $non_org_id );

		$this->assertWPError( $result );
		$this->assertSame( 'unauthorized', $result->get_error_code() );
	}

	/**
	 * @covers ::ban
	 * @covers ::is_banned
	 */
	public function test_ban_removes_user_and_sets_flag(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		$result = Membership::ban( $user_id, $this->blog_id, $this->organizer_id );

		$this->assertTrue( $result );
		$this->assertFalse( is_user_member_of_blog( $user_id, $this->blog_id ) );
		$this->assertTrue( Membership::is_banned( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::is_banned
	 */
	public function test_is_banned_returns_false_for_non_banned_user(): void {
		$user_id = self::factory()->user->create();

		$this->assertFalse( Membership::is_banned( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::join
	 * @covers ::is_banned
	 */
	public function test_banned_user_cannot_rejoin(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		Membership::ban( $user_id, $this->blog_id, $this->organizer_id );

		$result = Membership::join( $user_id, $this->blog_id );

		$this->assertWPError( $result );
		$this->assertSame( 'user_banned', $result->get_error_code() );
		$this->assertFalse( is_user_member_of_blog( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::ban
	 */
	public function test_ban_fires_action_hook(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		Membership::join( $user_id, $this->blog_id );

		add_action(
			'groups_member_banned',
			function ( $uid, $bid ) use ( $user_id, &$fired ) {
				$fired = true;
				$this->assertSame( $user_id, $uid );
				$this->assertSame( $this->blog_id, $bid );
			},
			10,
			2
		);

		Membership::ban( $user_id, $this->blog_id, $this->organizer_id );

		$this->assertTrue( $fired, 'The groups_member_banned action should have fired.' );
	}

	/**
	 * @covers ::unban
	 */
	public function test_unban_allows_rejoin(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );
		Membership::ban( $user_id, $this->blog_id, $this->organizer_id );

		$this->assertTrue( Membership::is_banned( $user_id, $this->blog_id ) );

		$result = Membership::unban( $user_id, $this->blog_id, $this->organizer_id );
		$this->assertTrue( $result );
		$this->assertFalse( Membership::is_banned( $user_id, $this->blog_id ) );

		// User can now rejoin.
		$join_result = Membership::join( $user_id, $this->blog_id );
		$this->assertTrue( $join_result );
		$this->assertTrue( is_user_member_of_blog( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::unban
	 */
	public function test_unban_fires_action_hook(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		Membership::join( $user_id, $this->blog_id );
		Membership::ban( $user_id, $this->blog_id, $this->organizer_id );

		add_action(
			'groups_member_unbanned',
			function ( $uid, $bid ) use ( $user_id, &$fired ) {
				$fired = true;
				$this->assertSame( $user_id, $uid );
				$this->assertSame( $this->blog_id, $bid );
			},
			10,
			2
		);

		Membership::unban( $user_id, $this->blog_id, $this->organizer_id );

		$this->assertTrue( $fired, 'The groups_member_unbanned action should have fired.' );
	}

	/**
	 * @covers ::get_members
	 */
	public function test_get_members_returns_correct_users(): void {
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();
		$user3 = self::factory()->user->create();

		Membership::join( $user1, $this->blog_id );
		Membership::join( $user2, $this->blog_id );

		$members    = Membership::get_members( $this->blog_id );
		$member_ids = wp_list_pluck( $members, 'ID' );

		$this->assertContains( $user1, $member_ids );
		$this->assertContains( $user2, $member_ids );
		$this->assertNotContains( $user3, $member_ids );
	}

	/**
	 * @covers ::get_members
	 */
	public function test_get_members_filtered_by_role(): void {
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();

		Membership::join( $user1, $this->blog_id );
		Membership::join( $user2, $this->blog_id );
		Membership::change_role( $user1, 'organizer', $this->blog_id, $this->organizer_id );

		$organizers    = Membership::get_members( $this->blog_id, 'organizer' );
		$organizer_ids = wp_list_pluck( $organizers, 'ID' );

		$this->assertContains( $user1, $organizer_ids );
		$this->assertNotContains( $user2, $organizer_ids );

		$members    = Membership::get_members( $this->blog_id, 'member' );
		$member_ids = wp_list_pluck( $members, 'ID' );

		$this->assertContains( $user2, $member_ids );
		$this->assertNotContains( $user1, $member_ids );
	}

	/**
	 * @covers ::get_member_count
	 */
	public function test_get_member_count(): void {
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();

		// Count before adding members (site may have default admin).
		$initial_count = Membership::get_member_count( $this->blog_id );

		Membership::join( $user1, $this->blog_id );
		Membership::join( $user2, $this->blog_id );

		$this->assertSame( $initial_count + 2, Membership::get_member_count( $this->blog_id ) );
	}

	/**
	 * @covers ::get_user_role
	 */
	public function test_get_user_role_returns_role(): void {
		$user_id = self::factory()->user->create();

		Membership::join( $user_id, $this->blog_id );

		$this->assertSame( 'member', Membership::get_user_role( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::get_user_role
	 */
	public function test_get_user_role_returns_false_for_non_member(): void {
		$user_id = self::factory()->user->create();

		$this->assertFalse( Membership::get_user_role( $user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::can_manage_members
	 */
	public function test_can_manage_members_for_organizer(): void {
		$this->assertTrue( Membership::can_manage_members( $this->organizer_id, $this->blog_id ) );
	}

	/**
	 * @covers ::can_manage_members
	 */
	public function test_can_manage_members_false_for_regular_member(): void {
		$user_id = self::factory()->user->create();
		Membership::join( $user_id, $this->blog_id );

		$this->assertFalse( Membership::can_manage_members( $user_id, $this->blog_id ) );
	}
}
