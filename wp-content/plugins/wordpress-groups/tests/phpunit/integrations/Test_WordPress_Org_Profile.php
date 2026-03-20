<?php
/**
 * Tests for WordPress.org profile badge integration.
 *
 * @package Groups\Tests
 */

use Groups\Integrations\WordPress_Org_Profile;

/**
 * @coversDefaultClass \Groups\Integrations\WordPress_Org_Profile
 */
class Test_WordPress_Org_Profile extends WP_UnitTestCase {

	/**
	 * WordPress_Org_Profile instance under test.
	 *
	 * @var WordPress_Org_Profile
	 */
	private WordPress_Org_Profile $profile;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->profile = new WordPress_Org_Profile();
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down(): void {
		remove_all_filters( 'groups_profile_badge_url' );
		remove_all_actions( 'wporg_profile_badge_assign' );

		parent::tear_down();
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_fires_action_for_valid_organizer_badge(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		add_action( 'wporg_profile_badge_assign', function ( $uid, $badge, $ctx ) use ( $user_id, &$fired ) {
			if ( $uid === $user_id && 'group-organizer' === $badge ) {
				$fired = true;
			}
		}, 10, 3 );

		$result = $this->profile->assign_badge( $user_id, 'group-organizer' );

		$this->assertTrue( $result, 'assign_badge() should return true for valid input.' );
		$this->assertTrue( $fired, 'wporg_profile_badge_assign action should fire for group-organizer.' );
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_fires_action_for_valid_attendee_badge(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		add_action( 'wporg_profile_badge_assign', function ( $uid, $badge, $ctx ) use ( $user_id, &$fired ) {
			if ( $uid === $user_id && 'group-attendee' === $badge ) {
				$fired = true;
			}
		}, 10, 3 );

		$result = $this->profile->assign_badge( $user_id, 'group-attendee' );

		$this->assertTrue( $result, 'assign_badge() should return true for attendee badge.' );
		$this->assertTrue( $fired, 'wporg_profile_badge_assign action should fire for group-attendee.' );
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_returns_false_for_invalid_badge_type(): void {
		$user_id = self::factory()->user->create();

		$result = $this->profile->assign_badge( $user_id, 'invalid-badge' );

		$this->assertFalse( $result, 'assign_badge() should return false for invalid badge type.' );
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_returns_false_for_zero_user_id(): void {
		$result = $this->profile->assign_badge( 0, 'group-organizer' );

		$this->assertFalse( $result, 'assign_badge() should return false for user ID 0.' );
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_returns_false_for_nonexistent_user(): void {
		$result = $this->profile->assign_badge( 999999, 'group-organizer' );

		$this->assertFalse( $result, 'assign_badge() should return false for nonexistent user.' );
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_passes_context_to_action(): void {
		$user_id          = self::factory()->user->create();
		$captured_context = null;

		add_action( 'wporg_profile_badge_assign', function ( $uid, $badge, $ctx ) use ( &$captured_context ) {
			$captured_context = $ctx;
		}, 10, 3 );

		$this->profile->assign_badge( $user_id, 'group-organizer', [
			'post_id' => 42,
			'source'  => 'test',
		] );

		$this->assertIsArray( $captured_context );
		$this->assertSame( 42, $captured_context['post_id'] );
		$this->assertSame( 'test', $captured_context['source'] );
		$this->assertSame( 'group-organizer', $captured_context['badge_type'] );
		$this->assertSame( $user_id, $captured_context['user_id'] );
		$this->assertSame( 'wordpress-groups', $captured_context['plugin'] );
	}

	/**
	 * @covers ::assign_badge
	 */
	public function test_assign_badge_does_not_fire_action_for_invalid_type(): void {
		$user_id = self::factory()->user->create();
		$fired   = false;

		add_action( 'wporg_profile_badge_assign', function () use ( &$fired ) {
			$fired = true;
		} );

		$this->profile->assign_badge( $user_id, 'super-admin' );

		$this->assertFalse( $fired, 'wporg_profile_badge_assign should not fire for invalid badge type.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_on_status_transition_assigns_organizer_badge_on_active(): void {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_author' => $user_id,
		] );

		$captured_uid   = null;
		$captured_badge = null;

		add_action( 'wporg_profile_badge_assign', function ( $uid, $badge ) use ( &$captured_uid, &$captured_badge ) {
			$captured_uid   = $uid;
			$captured_badge = $badge;
		}, 10, 3 );

		$this->profile->on_status_transition( $post_id, 'meetup-orientation', 'meetup-active' );

		$this->assertSame( $user_id, $captured_uid, 'Should assign badge to post author.' );
		$this->assertSame( 'group-organizer', $captured_badge, 'Should assign group-organizer badge.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_on_status_transition_ignores_non_active_transitions(): void {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_author' => $user_id,
		] );

		$fired = false;
		add_action( 'wporg_profile_badge_assign', function () use ( &$fired ) {
			$fired = true;
		} );

		$this->profile->on_status_transition( $post_id, 'meetup-pending', 'meetup-vetting' );

		$this->assertFalse( $fired, 'Should not assign badge for non-active transitions.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_on_status_transition_ignores_invalid_post(): void {
		$fired = false;
		add_action( 'wporg_profile_badge_assign', function () use ( &$fired ) {
			$fired = true;
		} );

		$this->profile->on_status_transition( 999999, 'meetup-orientation', 'meetup-active' );

		$this->assertFalse( $fired, 'Should not assign badge for nonexistent post.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_on_status_transition_context_includes_post_id(): void {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_author' => $user_id,
		] );

		$captured_context = null;
		add_action( 'wporg_profile_badge_assign', function ( $uid, $badge, $ctx ) use ( &$captured_context ) {
			$captured_context = $ctx;
		}, 10, 3 );

		$this->profile->on_status_transition( $post_id, 'meetup-scheduling', 'meetup-active' );

		$this->assertSame( $post_id, $captured_context['post_id'] );
		$this->assertSame( 'status_transition', $captured_context['source'] );
	}

	/**
	 * @covers ::get_badge_api_url
	 */
	public function test_get_badge_api_url_returns_default(): void {
		$url = $this->profile->get_badge_api_url();

		$this->assertSame( WordPress_Org_Profile::DEFAULT_BADGE_API_URL, $url );
	}

	/**
	 * @covers ::get_badge_api_url
	 */
	public function test_get_badge_api_url_is_filterable(): void {
		$custom_url = 'https://custom.example.com/api/badges';

		add_filter( 'groups_profile_badge_url', function () use ( $custom_url ) {
			return $custom_url;
		} );

		$url = $this->profile->get_badge_api_url();

		$this->assertSame( $custom_url, $url, 'groups_profile_badge_url filter should override the default URL.' );
	}

	/**
	 * @covers ::__construct
	 */
	public function test_constructor_registers_status_transition_hook(): void {
		$this->assertIsInt(
			has_action( 'groups_meetup_status_transition', [ $this->profile, 'on_status_transition' ] ),
			'Constructor should register on_status_transition on groups_meetup_status_transition.'
		);
	}
}
