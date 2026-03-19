<?php
/**
 * Membership model — site role management, join/leave groups.
 *
 * Uses native WordPress multisite user roles. Each group is a site,
 * and membership is managed by adding/removing users from that site.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Handles group membership via WordPress multisite site roles.
 */
class Membership {

	/**
	 * Custom roles and their capabilities.
	 *
	 * @var array<string, array{display_name: string, capabilities: array<string, bool>}>
	 */
	const ROLES = [
		'organizer' => [
			'display_name' => 'Organizer',
			'capabilities' => [
				// Editor capabilities.
				'moderate_comments'          => true,
				'manage_categories'          => true,
				'manage_links'               => true,
				'upload_files'               => true,
				'unfiltered_html'            => true,
				'edit_posts'                 => true,
				'edit_others_posts'          => true,
				'edit_published_posts'       => true,
				'publish_posts'              => true,
				'edit_pages'                 => true,
				'read'                       => true,
				'edit_others_pages'          => true,
				'edit_published_pages'       => true,
				'publish_pages'              => true,
				'delete_pages'               => true,
				'delete_others_pages'        => true,
				'delete_published_pages'     => true,
				'delete_posts'               => true,
				'delete_others_posts'        => true,
				'delete_published_posts'     => true,
				'delete_private_posts'       => true,
				'edit_private_posts'         => true,
				'read_private_posts'         => true,
				'delete_private_pages'       => true,
				'edit_private_pages'         => true,
				'read_private_pages'         => true,
				// Additional organizer capability.
				'manage_options'             => true,
			],
		],
		'co_organizer' => [
			'display_name' => 'Co-Organizer',
			'capabilities' => [
				// Editor capabilities.
				'moderate_comments'          => true,
				'manage_categories'          => true,
				'manage_links'               => true,
				'upload_files'               => true,
				'unfiltered_html'            => true,
				'edit_posts'                 => true,
				'edit_others_posts'          => true,
				'edit_published_posts'       => true,
				'publish_posts'              => true,
				'edit_pages'                 => true,
				'read'                       => true,
				'edit_others_pages'          => true,
				'edit_published_pages'       => true,
				'publish_pages'              => true,
				'delete_pages'               => true,
				'delete_others_pages'        => true,
				'delete_published_pages'     => true,
				'delete_posts'               => true,
				'delete_others_posts'        => true,
				'delete_published_posts'     => true,
				'delete_private_posts'       => true,
				'edit_private_posts'         => true,
				'read_private_posts'         => true,
				'delete_private_pages'       => true,
				'edit_private_pages'         => true,
				'read_private_pages'         => true,
			],
		],
		'member' => [
			'display_name' => 'Member',
			'capabilities' => [
				// Subscriber capabilities.
				'read' => true,
			],
		],
	];

	/**
	 * Register custom roles on the current site.
	 *
	 * Hooked to `init` so roles are available on every site in the network.
	 */
	public static function register_roles(): void {
		foreach ( self::ROLES as $role_slug => $role_data ) {
			// Only add if the role doesn't already exist.
			if ( null === get_role( $role_slug ) ) {
				add_role( $role_slug, $role_data['display_name'], $role_data['capabilities'] );
			}
		}
	}

	/**
	 * Add a user to a group site with the member role.
	 *
	 * @param int $user_id The user ID to add.
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function join( int $user_id, int $blog_id = 0 ): true|\WP_Error {
		$blog_id = $blog_id ?: get_current_blog_id();

		if ( self::is_banned( $user_id, $blog_id ) ) {
			return new \WP_Error(
				'user_banned',
				__( 'This user is banned from this group.', 'wordpress-groups' )
			);
		}

		$result = add_user_to_blog( $blog_id, $user_id, 'member' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a user joins a group.
		 *
		 * @param int $user_id The user ID.
		 * @param int $blog_id The blog ID.
		 */
		do_action( 'groups_member_joined', $user_id, $blog_id );

		return true;
	}

	/**
	 * Remove a user from a group site.
	 *
	 * @param int $user_id The user ID to remove.
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function leave( int $user_id, int $blog_id = 0 ): true|\WP_Error {
		$blog_id = $blog_id ?: get_current_blog_id();

		$result = remove_user_from_blog( $user_id, $blog_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a user leaves a group.
		 *
		 * @param int $user_id The user ID.
		 * @param int $blog_id The blog ID.
		 */
		do_action( 'groups_member_left', $user_id, $blog_id );

		return true;
	}

	/**
	 * Change a user's role on a group site.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $role    The new role (organizer, co_organizer, or member).
	 * @param int    $blog_id The blog ID. Defaults to current blog.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function change_role( int $user_id, string $role, int $blog_id = 0 ): true|\WP_Error {
		$blog_id = $blog_id ?: get_current_blog_id();

		if ( ! array_key_exists( $role, self::ROLES ) ) {
			return new \WP_Error(
				'invalid_role',
				sprintf(
					/* translators: %s: role slug */
					__( 'Invalid role: %s. Must be one of: organizer, co_organizer, member.', 'wordpress-groups' ),
					$role
				)
			);
		}

		// Verify the user is a member of the site.
		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			return new \WP_Error(
				'not_a_member',
				__( 'User is not a member of this group.', 'wordpress-groups' )
			);
		}

		// Switch to the target blog to modify the user's role.
		switch_to_blog( $blog_id );

		$user = new \WP_User( $user_id );
		$user->set_role( $role );

		restore_current_blog();

		return true;
	}

	/**
	 * Ban a user from a group site.
	 *
	 * Removes the user from the site and sets a user meta flag to prevent rejoin.
	 *
	 * @param int $user_id The user ID to ban.
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function ban( int $user_id, int $blog_id = 0 ): true|\WP_Error {
		$blog_id = $blog_id ?: get_current_blog_id();

		// Remove user from the site if they're a member.
		if ( is_user_member_of_blog( $user_id, $blog_id ) ) {
			$result = remove_user_from_blog( $user_id, $blog_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Set the ban flag.
		update_user_meta( $user_id, "_groups_banned_{$blog_id}", 1 );

		return true;
	}

	/**
	 * Check if a user is banned from a group site.
	 *
	 * @param int $user_id The user ID to check.
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return bool True if the user is banned, false otherwise.
	 */
	public static function is_banned( int $user_id, int $blog_id = 0 ): bool {
		$blog_id = $blog_id ?: get_current_blog_id();

		return (bool) get_user_meta( $user_id, "_groups_banned_{$blog_id}", true );
	}

	/**
	 * Get members of a group site, optionally filtered by role.
	 *
	 * @param int    $blog_id The blog ID. Defaults to current blog.
	 * @param string $role    Optional role to filter by.
	 * @return \WP_User[] Array of WP_User objects.
	 */
	public static function get_members( int $blog_id = 0, string $role = '' ): array {
		$blog_id = $blog_id ?: get_current_blog_id();

		$args = [
			'blog_id' => $blog_id,
		];

		if ( $role ) {
			$args['role'] = $role;
		}

		return get_users( $args );
	}

	/**
	 * Count members of a group site.
	 *
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return int The number of members.
	 */
	public static function get_member_count( int $blog_id = 0 ): int {
		$blog_id = $blog_id ?: get_current_blog_id();

		return count(
			get_users(
				[
					'blog_id' => $blog_id,
					'fields'  => 'ID',
				]
			)
		);
	}

	/**
	 * Get a user's role on a group site.
	 *
	 * @param int $user_id The user ID.
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return string|false The user's role slug, or false if not a member.
	 */
	public static function get_user_role( int $user_id, int $blog_id = 0 ): string|false {
		$blog_id = $blog_id ?: get_current_blog_id();

		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			return false;
		}

		switch_to_blog( $blog_id );

		$user  = new \WP_User( $user_id );
		$roles = $user->roles;

		restore_current_blog();

		if ( empty( $roles ) ) {
			return false;
		}

		return reset( $roles );
	}
}
