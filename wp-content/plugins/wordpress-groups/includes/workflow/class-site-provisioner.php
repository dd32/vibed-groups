<?php
/**
 * Site provisioner — creates a multisite site when a group is approved.
 *
 * Listens for the `groups_meetup_status_transition` action and provisions
 * a new site when a wp_meetup post transitions to `meetup-scheduling`.
 *
 * @package Groups\Workflow
 */

namespace Groups\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and configures a new multisite site for an approved community group.
 */
class Site_Provisioner {

	/**
	 * Default event categories to create on new group sites.
	 *
	 * @var string[]
	 */
	private const DEFAULT_CATEGORIES = [
		'In-person',
		'Online',
		'Hybrid',
		'Workshop',
		'Presentation',
		'Social',
	];

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'groups_meetup_status_transition', [ $this, 'maybe_provision_site' ], 15, 3 );
	}

	/**
	 * Provision a site when a meetup transitions to `meetup-scheduling`.
	 *
	 * @param int    $post_id    The wp_meetup post ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function maybe_provision_site( int $post_id, string $old_status, string $new_status ): void {
		if ( 'meetup-scheduling' !== $new_status ) {
			return;
		}

		// Don't provision if a site already exists for this meetup.
		$existing_site_id = get_post_meta( $post_id, '_meetup_site_id', true );
		if ( $existing_site_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$blog_id = $this->create_site( $post );
		if ( is_wp_error( $blog_id ) || ! $blog_id ) {
			return;
		}

		// Store the blog ID on the meetup post.
		update_post_meta( $post_id, '_meetup_site_id', $blog_id );

		// Configure the new site.
		$this->assign_theme( $blog_id );
		$this->add_organizer_as_admin( $blog_id, $post );
		$this->create_default_categories( $blog_id );

		/**
		 * Fires after a new group site has been provisioned.
		 *
		 * @param int $blog_id The new site's blog ID.
		 * @param int $post_id The wp_meetup post ID.
		 */
		do_action( 'groups_site_provisioned', $blog_id, $post_id );
	}

	/**
	 * Create the multisite site.
	 *
	 * @param \WP_Post $post The wp_meetup post.
	 * @return int|\WP_Error Blog ID on success, WP_Error on failure.
	 */
	private function create_site( \WP_Post $post ) {
		$slug    = sanitize_title( $post->post_title );
		$domain  = get_network()->domain;
		$path    = get_network()->path . $slug . '/';
		$title   = $post->post_title;
		$user_id = $this->get_organizer_user_id( $post );

		return wpmu_create_blog( $domain, $path, $title, $user_id, [ 'public' => 1 ], get_current_network_id() );
	}

	/**
	 * Assign the `groups-site` theme to the new site.
	 *
	 * @param int $blog_id The blog ID.
	 */
	private function assign_theme( int $blog_id ): void {
		switch_to_blog( $blog_id );
		switch_theme( 'groups-site' );
		restore_current_blog();
	}

	/**
	 * Add the organizer as a site administrator.
	 *
	 * @param int      $blog_id The blog ID.
	 * @param \WP_Post $post    The wp_meetup post.
	 */
	private function add_organizer_as_admin( int $blog_id, \WP_Post $post ): void {
		$user_id = $this->get_organizer_user_id( $post );

		if ( ! $user_id ) {
			return;
		}

		add_user_to_blog( $blog_id, $user_id, 'administrator' );
	}

	/**
	 * Create default categories on the new site.
	 *
	 * @param int $blog_id The blog ID.
	 */
	private function create_default_categories( int $blog_id ): void {
		switch_to_blog( $blog_id );

		foreach ( self::DEFAULT_CATEGORIES as $category_name ) {
			if ( ! term_exists( $category_name, 'category' ) ) {
				wp_insert_term( $category_name, 'category' );
			}
		}

		restore_current_blog();
	}

	/**
	 * Get the organizer user ID from post meta or fall back to post author.
	 *
	 * @param \WP_Post $post The wp_meetup post.
	 * @return int User ID.
	 */
	private function get_organizer_user_id( \WP_Post $post ): int {
		$organizer_id = (int) get_post_meta( $post->ID, '_meetup_organizer_user_id', true );

		if ( $organizer_id ) {
			return $organizer_id;
		}

		return (int) $post->post_author;
	}
}
