<?php
/**
 * Tests for the Site_Provisioner workflow class.
 *
 * @package Groups\Tests
 */

use Groups\Workflow\Application_Workflow;
use Groups\Workflow\Site_Provisioner;

/**
 * @coversDefaultClass \Groups\Workflow\Site_Provisioner
 * @group multisite
 */
class Test_Site_Provisioner extends WP_UnitTestCase {

	/**
	 * Application workflow instance.
	 *
	 * @var Application_Workflow
	 */
	private Application_Workflow $workflow;

	/**
	 * Site provisioner instance.
	 *
	 * @var Site_Provisioner
	 */
	private Site_Provisioner $provisioner;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite is required for site provisioner tests.' );
		}

		// Register the wp_meetup post type for testing.
		register_post_type( 'wp_meetup', [
			'public' => false,
		] );

		// Register all meetup statuses.
		$statuses = [
			'meetup-pending',
			'meetup-vetting',
			'meetup-feedback',
			'meetup-orientation',
			'meetup-scheduling',
			'meetup-active',
			'meetup-dormant',
			'meetup-suspended',
			'meetup-removed',
			'meetup-declined',
		];

		foreach ( $statuses as $status ) {
			register_post_status( $status, [
				'public' => true,
			] );
		}

		$this->workflow    = new Application_Workflow();
		$this->provisioner = new Site_Provisioner();
	}

	/**
	 * Helper to create a wp_meetup post with a given status.
	 *
	 * @param string $status  Initial post status.
	 * @param array  $args    Additional post args.
	 * @return int Post ID.
	 */
	private function create_meetup_post( string $status = 'meetup-orientation', array $args = [] ): int {
		// Remove workflow hooks during creation to avoid triggering validation.
		remove_action( 'transition_post_status', [ $this->workflow, 'validate_transition' ], 5 );
		remove_action( 'transition_post_status', [ $this->workflow, 'handle_transition' ], 10 );

		$defaults = [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Melbourne WordPress User Group',
			'post_status' => $status,
		];

		$post_id = wp_insert_post( array_merge( $defaults, $args ) );

		add_action( 'transition_post_status', [ $this->workflow, 'validate_transition' ], 5, 3 );
		add_action( 'transition_post_status', [ $this->workflow, 'handle_transition' ], 10, 3 );

		return $post_id;
	}

	/**
	 * Transition a meetup post to a new status.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $new_status New status.
	 */
	private function transition_post( int $post_id, string $new_status ): void {
		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => $new_status,
		] );
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_site_created_on_meetup_scheduling_transition(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$site_id = get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $site_id, 'A site ID should be stored in post meta.' );
		$this->assertIsNumeric( $site_id );

		$blog = get_blog_details( (int) $site_id );
		$this->assertNotFalse( $blog, 'The provisioned site should exist.' );
		$this->assertStringContains( 'melbourne-wordpress-user-group', $blog->path );
	}

	/**
	 * @covers ::maybe_provision_site
	 * @covers ::add_organizer_as_admin
	 */
	public function test_organizer_added_as_admin(): void {
		$user_id = self::factory()->user->create( [
			'role' => 'subscriber',
		] );

		$post_id = $this->create_meetup_post( 'meetup-orientation', [
			'post_author' => $user_id,
		] );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$site_id = (int) get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $site_id );

		$this->assertTrue(
			is_user_member_of_blog( $user_id, $site_id ),
			'The organizer should be a member of the new site.'
		);

		switch_to_blog( $site_id );
		$user = new \WP_User( $user_id );
		$this->assertTrue(
			in_array( 'administrator', $user->roles, true ),
			'The organizer should have the administrator role on the new site.'
		);
		restore_current_blog();
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_organizer_from_post_meta_takes_priority(): void {
		$author_id    = self::factory()->user->create();
		$organizer_id = self::factory()->user->create();

		$post_id = $this->create_meetup_post( 'meetup-orientation', [
			'post_author' => $author_id,
		] );
		update_post_meta( $post_id, '_meetup_organizer_user_id', $organizer_id );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$site_id = (int) get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $site_id );

		$this->assertTrue(
			is_user_member_of_blog( $organizer_id, $site_id ),
			'The organizer from post meta should be a member of the new site.'
		);

		switch_to_blog( $site_id );
		$user = new \WP_User( $organizer_id );
		$this->assertTrue(
			in_array( 'administrator', $user->roles, true ),
			'The organizer from post meta should have the administrator role.'
		);
		restore_current_blog();
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_blog_id_stored_in_post_meta(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$site_id = get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $site_id, 'Blog ID should be stored in _meetup_site_id post meta.' );

		$blog = get_blog_details( (int) $site_id );
		$this->assertNotFalse( $blog, 'Stored blog ID should reference a valid site.' );
	}

	/**
	 * @covers ::create_default_categories
	 */
	public function test_default_categories_created(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$site_id = (int) get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $site_id );

		$expected_categories = [
			'In-person',
			'Online',
			'Hybrid',
			'Workshop',
			'Presentation',
			'Social',
		];

		switch_to_blog( $site_id );

		foreach ( $expected_categories as $category_name ) {
			$term = term_exists( $category_name, 'category' );
			$this->assertNotNull( $term, "Category '{$category_name}' should exist on the new site." );
			$this->assertNotFalse( $term, "Category '{$category_name}' should exist on the new site." );
		}

		restore_current_blog();
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_provisioned_action_fires(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$fired     = false;
		$hook_args = [];
		$callback  = function ( $blog_id, $meetup_post_id ) use ( &$fired, &$hook_args ) {
			$fired     = true;
			$hook_args = [
				'blog_id' => $blog_id,
				'post_id' => $meetup_post_id,
			];
		};

		add_action( 'groups_site_provisioned', $callback, 10, 2 );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		remove_action( 'groups_site_provisioned', $callback, 10 );

		$this->assertTrue( $fired, 'groups_site_provisioned action should fire.' );
		$this->assertSame( $post_id, $hook_args['post_id'] );
		$this->assertIsInt( $hook_args['blog_id'] );
		$this->assertGreaterThan( 0, $hook_args['blog_id'] );
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_no_duplicate_site_on_repeated_transition(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$first_site_id = get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $first_site_id );

		// Manually fire the action again to simulate a repeated transition.
		do_action( 'groups_meetup_status_transition', $post_id, 'meetup-orientation', 'meetup-scheduling' );

		$second_site_id = get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertSame( $first_site_id, $second_site_id, 'Should not create a second site.' );
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_non_scheduling_transition_does_not_provision(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );

		$this->transition_post( $post_id, 'meetup-vetting' );

		$site_id = get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertEmpty( $site_id, 'No site should be provisioned for non-scheduling transitions.' );
	}

	/**
	 * @covers ::maybe_provision_site
	 */
	public function test_theme_assigned_to_new_site(): void {
		$post_id = $this->create_meetup_post( 'meetup-orientation' );

		$this->transition_post( $post_id, 'meetup-scheduling' );

		$site_id = (int) get_post_meta( $post_id, '_meetup_site_id', true );
		$this->assertNotEmpty( $site_id );

		switch_to_blog( $site_id );
		$theme = get_option( 'stylesheet' );
		restore_current_blog();

		$this->assertSame( 'groups-site', $theme, 'The groups-site theme should be assigned.' );
	}

	/**
	 * Custom assertion for string containment (compatible with PHPUnit 9).
	 *
	 * @param string $needle   Substring to look for.
	 * @param string $haystack String to search in.
	 * @param string $message  Optional failure message.
	 */
	private function assertStringContains( string $needle, string $haystack, string $message = '' ): void {
		$this->assertStringContainsString( $needle, $haystack, $message );
	}
}
