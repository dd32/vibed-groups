<?php
/**
 * REST API controller for group membership.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Models\Membership;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles listing members and join/leave operations via the REST API.
 */
class Membership_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for membership routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'members';

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/join',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'join_item' ],
					'permission_callback' => [ $this, 'join_item_permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/leave',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'leave_item' ],
					'permission_callback' => [ $this, 'leave_item_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Permission check for listing members.
	 *
	 * Member lists are public.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true
	 */
	public function get_items_permissions_check( $request ): true {
		return true;
	}

	/**
	 * Permission check for joining a group.
	 *
	 * The user must be logged in and must not be banned.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function join_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to join a group.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		$user_id = get_current_user_id();
		$blog_id = get_current_blog_id();

		if ( Membership::is_banned( $user_id, $blog_id ) ) {
			return new WP_Error(
				'rest_user_banned',
				__( 'You are banned from this group.', 'wordpress-groups' ),
				[ 'status' => 403 ]
			);
		}

		if ( is_user_member_of_blog( $user_id, $blog_id ) ) {
			return new WP_Error(
				'rest_already_member',
				__( 'You are already a member of this group.', 'wordpress-groups' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for leaving a group.
	 *
	 * The user must be logged in and must be a member.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function leave_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to leave a group.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		$user_id = get_current_user_id();
		$blog_id = get_current_blog_id();

		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			return new WP_Error(
				'rest_not_a_member',
				__( 'You are not a member of this group.', 'wordpress-groups' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Retrieve a collection of group members.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$role    = sanitize_text_field( $request->get_param( 'role' ) ?? '' );
		$blog_id = get_current_blog_id();
		$members = Membership::get_members( $blog_id, $role );

		$data = [];
		foreach ( $members as $user ) {
			$data[] = $this->prepare_item_for_response( $user, $request )->get_data();
		}

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total', count( $data ) );

		return $response;
	}

	/**
	 * Join the current user to the group.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function join_item( $request ) {
		$user_id = get_current_user_id();
		$blog_id = get_current_blog_id();

		$result = Membership::join( $user_id, $blog_id );

		if ( is_wp_error( $result ) ) {
			$result->add_data( [ 'status' => 400 ] );
			return $result;
		}

		$user     = get_userdata( $user_id );
		$response = $this->prepare_item_for_response( $user, $request );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Remove the current user from the group.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function leave_item( $request ) {
		$user_id = get_current_user_id();
		$blog_id = get_current_blog_id();

		// Capture user data before removing (role will be gone after leave).
		$user = get_userdata( $user_id );
		$role = Membership::get_user_role( $user_id, $blog_id );

		$result = Membership::leave( $user_id, $blog_id );

		if ( is_wp_error( $result ) ) {
			$result->add_data( [ 'status' => 400 ] );
			return $result;
		}

		$data = [
			'user_id'      => (int) $user->ID,
			'display_name' => esc_html( $user->display_name ),
			'avatar_url'   => esc_url( get_avatar_url( $user->ID ) ),
			'role'         => $role ?: 'member',
			'joined_date'  => null,
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Prepare a single member for the response.
	 *
	 * @param \WP_User        $user    User object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ): WP_REST_Response {
		$blog_id     = get_current_blog_id();
		$role        = Membership::get_user_role( (int) $user->ID, $blog_id );
		$joined_date = $this->get_joined_date( (int) $user->ID, $blog_id );

		$data = [
			'user_id'      => (int) $user->ID,
			'display_name' => esc_html( $user->display_name ),
			'avatar_url'   => esc_url( get_avatar_url( $user->ID ) ),
			'role'         => $role ?: 'member',
			'joined_date'  => $joined_date,
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get the date a user joined a group site.
	 *
	 * Reads from the `_groups_joined_{blog_id}` user meta, which is set when a
	 * user joins via the Membership model. Falls back to `user_registered` for
	 * legacy members who joined before this meta was introduced.
	 *
	 * @param int $user_id The user ID.
	 * @param int $blog_id The blog ID.
	 * @return string|null ISO 8601 date string, or null if unavailable.
	 */
	private function get_joined_date( int $user_id, int $blog_id ): ?string {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		$joined = get_user_meta( $user_id, "_groups_joined_{$blog_id}", true );

		if ( $joined ) {
			return mysql_to_rfc3339( $joined );
		}

		// Legacy fallback: use account registration date.
		return mysql_to_rfc3339( $user->user_registered );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return [
			'role' => [
				'description' => __( 'Limit results to members with a specific role.', 'wordpress-groups' ),
				'type'        => 'string',
				'enum'        => [ 'organizer', 'co_organizer', 'member' ],
			],
		];
	}

	/**
	 * Get the member schema for responses.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'member',
			'type'       => 'object',
			'properties' => [
				'user_id'      => [
					'description' => __( 'Unique identifier for the user.', 'wordpress-groups' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'display_name' => [
					'description' => __( 'Display name of the user.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'avatar_url'   => [
					'description' => __( 'URL to the user avatar.', 'wordpress-groups' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'role'         => [
					'description' => __( 'The user role on this group site.', 'wordpress-groups' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'joined_date'  => [
					'description' => __( 'The date the user joined this group, in RFC3339 format.', 'wordpress-groups' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'date-time',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
