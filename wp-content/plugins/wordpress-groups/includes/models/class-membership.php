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

use Groups\Cache;

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
	 * Uses wp_roles()->is_role() as a guard so that add_role() (which writes
	 * to wp_options) is only called once per site, not on every page load.
	 */
	public static function register_roles(): void {
		$wp_roles = wp_roles();

		foreach ( self::ROLES as $role_slug => $role_data ) {
			if ( ! $wp_roles->is_role( $role_slug ) ) {
				add_role( $role_slug, $role_data['display_name'], $role_data['capabilities'] );
			}
		}
	}

	/**
	 * Check whether a user can manage members on a group site.
	 *
	 * Returns true if the user is an organizer on the site or a network admin.
	 *
	 * @param int $user_id The user ID to check.
	 * @param int $blog_id The blog ID. Defaults to current blog.
	 * @return bool True if the user can manage members.
	 */
	public static function can_manage_members( int $user_id, int $blog_id = 0 ): bool {
		$blog_id = $blog_id ?: get_current_blog_id();

		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		return 'organizer' === self::get_user_role( $user_id, $blog_id );
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

		// Record when the user joined this group.
		update_user_meta( $user_id, "_groups_joined_{$blog_id}", current_time( 'mysql', true ) );

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

		// Clean up the joined timestamp.
		delete_user_meta( $user_id, "_groups_joined_{$blog_id}" );

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
	 * Requires the current user (or the caller) to be an organizer or network admin.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $role       The new role (organizer, co_organizer, or member).
	 * @param int    $blog_id    The blog ID. Defaults to current blog.
	 * @param int    $actor_id   The user performing the action. Defaults to current user.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function change_role( int $user_id, string $role, int $blog_id = 0, int $actor_id = 0 ): true|\WP_Error {
		$blog_id  = $blog_id ?: get_current_blog_id();
		$actor_id = $actor_id ?: get_current_user_id();

		if ( ! self::can_manage_members( $actor_id, $blog_id ) ) {
			return new \WP_Error(
				'unauthorized',
				__( 'You do not have permission to manage members in this group.', 'wordpress-groups' )
			);
		}

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

		$old_role = self::get_user_role( $user_id, $blog_id );

		// Switch to the target blog to modify the user's role.
		switch_to_blog( $blog_id );

		$user = new \WP_User( $user_id );
		$user->set_role( $role );

		restore_current_blog();

		/**
		 * Fires after a member's role is changed on a group site.
		 *
		 * @param int    $user_id  The user ID.
		 * @param string $role     The new role.
		 * @param string $old_role The previous role.
		 * @param int    $blog_id  The blog ID.
		 */
		do_action( 'groups_member_role_changed', $user_id, $role, $old_role, $blog_id );

		return true;
	}

	/**
	 * Ban a user from a group site.
	 *
	 * Removes the user from the site and sets a user meta flag to prevent rejoin.
	 * Requires the caller to be an organizer or network admin.
	 *
	 * @param int $user_id  The user ID to ban.
	 * @param int $blog_id  The blog ID. Defaults to current blog.
	 * @param int $actor_id The user performing the action. Defaults to current user.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function ban( int $user_id, int $blog_id = 0, int $actor_id = 0 ): true|\WP_Error {
		$blog_id  = $blog_id ?: get_current_blog_id();
		$actor_id = $actor_id ?: get_current_user_id();

		if ( ! self::can_manage_members( $actor_id, $blog_id ) ) {
			return new \WP_Error(
				'unauthorized',
				__( 'You do not have permission to manage members in this group.', 'wordpress-groups' )
			);
		}

		// Remove user from the site if they're a member.
		if ( is_user_member_of_blog( $user_id, $blog_id ) ) {
			$result = remove_user_from_blog( $user_id, $blog_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Set the ban flag.
		update_user_meta( $user_id, "_groups_banned_{$blog_id}", 1 );

		/**
		 * Fires after a user is banned from a group.
		 *
		 * @param int $user_id The user ID.
		 * @param int $blog_id The blog ID.
		 */
		do_action( 'groups_member_banned', $user_id, $blog_id );

		return true;
	}

	/**
	 * Unban a user from a group site.
	 *
	 * Removes the ban meta flag, allowing the user to rejoin.
	 * Requires the caller to be an organizer or network admin.
	 *
	 * @param int $user_id  The user ID to unban.
	 * @param int $blog_id  The blog ID. Defaults to current blog.
	 * @param int $actor_id The user performing the action. Defaults to current user.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function unban( int $user_id, int $blog_id = 0, int $actor_id = 0 ): true|\WP_Error {
		$blog_id  = $blog_id ?: get_current_blog_id();
		$actor_id = $actor_id ?: get_current_user_id();

		if ( ! self::can_manage_members( $actor_id, $blog_id ) ) {
			return new \WP_Error(
				'unauthorized',
				__( 'You do not have permission to manage members in this group.', 'wordpress-groups' )
			);
		}

		delete_user_meta( $user_id, "_groups_banned_{$blog_id}" );

		/**
		 * Fires after a user is unbanned from a group.
		 *
		 * @param int $user_id The user ID.
		 * @param int $blog_id The blog ID.
		 */
		do_action( 'groups_member_unbanned', $user_id, $blog_id );

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
		$blog_id   = $blog_id ?: get_current_blog_id();
		$cache_key = 'member_count_' . $blog_id;

		$cached = Cache::get( $cache_key, Cache::GROUP_MEMBERS );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = count(
			get_users(
				[
					'blog_id' => $blog_id,
					'fields'  => 'ID',
				]
			)
		);

		Cache::set( $cache_key, $count, Cache::GROUP_MEMBERS );

		return $count;
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
