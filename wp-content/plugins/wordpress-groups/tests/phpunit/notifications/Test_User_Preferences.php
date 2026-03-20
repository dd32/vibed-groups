<?php
/**
 * Tests for the User_Preferences class.
 *
 * @package Groups\Tests
 */

use Groups\Notifications\User_Preferences;

/**
 * @coversDefaultClass \Groups\Notifications\User_Preferences
 */
class Test_User_Preferences extends WP_UnitTestCase {

	/**
	 * User ID for testing.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * @covers ::get_preference
	 */
	public function test_defaults_to_opted_in(): void {
		$this->assertTrue( User_Preferences::get_preference( $this->user_id, 'announcements' ) );
		$this->assertTrue( User_Preferences::get_preference( $this->user_id, 'event_reminders' ) );
		$this->assertTrue( User_Preferences::get_preference( $this->user_id, 'rsvp_confirmations' ) );
	}

	/**
	 * @covers ::set_preference
	 * @covers ::get_preference
	 */
	public function test_set_and_get_preference(): void {
		User_Preferences::set_preference( $this->user_id, 'announcements', false );

		$this->assertFalse( User_Preferences::get_preference( $this->user_id, 'announcements' ) );
	}

	/**
	 * @covers ::set_preference
	 */
	public function test_set_preference_preserves_other_types(): void {
		User_Preferences::set_preference( $this->user_id, 'announcements', false );
		User_Preferences::set_preference( $this->user_id, 'event_reminders', false );

		$this->assertFalse( User_Preferences::get_preference( $this->user_id, 'announcements' ) );
		$this->assertFalse( User_Preferences::get_preference( $this->user_id, 'event_reminders' ) );
		$this->assertTrue( User_Preferences::get_preference( $this->user_id, 'rsvp_confirmations' ) );
	}

	/**
	 * @covers ::is_opted_in
	 */
	public function test_is_opted_in_returns_same_as_get_preference(): void {
		$this->assertSame(
			User_Preferences::get_preference( $this->user_id, 'announcements' ),
			User_Preferences::is_opted_in( $this->user_id, 'announcements' )
		);

		User_Preferences::set_preference( $this->user_id, 'announcements', false );

		$this->assertSame(
			User_Preferences::get_preference( $this->user_id, 'announcements' ),
			User_Preferences::is_opted_in( $this->user_id, 'announcements' )
		);
	}

	/**
	 * @covers ::set_preference
	 */
	public function test_re_enable_preference(): void {
		User_Preferences::set_preference( $this->user_id, 'announcements', false );
		$this->assertFalse( User_Preferences::is_opted_in( $this->user_id, 'announcements' ) );

		User_Preferences::set_preference( $this->user_id, 'announcements', true );
		$this->assertTrue( User_Preferences::is_opted_in( $this->user_id, 'announcements' ) );
	}

	/**
	 * @covers ::get_preference
	 */
	public function test_invalid_type_throws_exception(): void {
		$this->expectException( \InvalidArgumentException::class );

		User_Preferences::get_preference( $this->user_id, 'invalid_type' );
	}

	/**
	 * @covers ::set_preference
	 */
	public function test_set_invalid_type_throws_exception(): void {
		$this->expectException( \InvalidArgumentException::class );

		User_Preferences::set_preference( $this->user_id, 'invalid_type', true );
	}

	/**
	 * @covers ::get_preference
	 */
	public function test_preferences_are_per_user(): void {
		$other_user = self::factory()->user->create();

		User_Preferences::set_preference( $this->user_id, 'announcements', false );

		$this->assertFalse( User_Preferences::is_opted_in( $this->user_id, 'announcements' ) );
		$this->assertTrue( User_Preferences::is_opted_in( $other_user, 'announcements' ) );
	}
}
