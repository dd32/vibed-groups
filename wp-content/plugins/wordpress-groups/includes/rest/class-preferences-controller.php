<?php
/**
 * REST API controller for user notification preferences.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Notifications\User_Preferences;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles reading and updating notification preferences via the REST API.
 *
 * Endpoint: /groups/v1/preferences
 */
class Preferences_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for preferences routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'preferences';

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
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_items' ],
					'permission_callback' => [ $this, 'update_items_permissions_check' ],
					'args'                => $this->get_update_args(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Permission check for reading preferences — must be logged in.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to view notification preferences.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	/**
	 * Retrieve the current user's notification preferences.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$user_id = get_current_user_id();
		$prefs   = [];

		foreach ( User_Preferences::TYPES as $type ) {
			$prefs[ $type ] = User_Preferences::is_opted_in( $user_id, $type );
		}

		return new WP_REST_Response( $prefs, 200 );
	}

	/**
	 * Permission check for updating preferences — must be logged in.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to update notification preferences.', 'wordpress-groups' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	/**
	 * Update the current user's notification preferences.
	 *
	 * Accepts one or more notification type keys with boolean values.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_items( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$updated = [];

		foreach ( User_Preferences::TYPES as $type ) {
			$value = $request->get_param( $type );

			if ( null === $value ) {
				continue;
			}

			$enabled = (bool) $value;
			$result  = User_Preferences::set_preference( $user_id, $type, $enabled );

			if ( ! $result ) {
				return new WP_Error(
					'rest_preference_update_failed',
					sprintf(
						/* translators: %s: notification type */
						__( 'Failed to update preference for "%s".', 'wordpress-groups' ),
						$type
					),
					[ 'status' => 500 ]
				);
			}

			$updated[ $type ] = $enabled;
		}

		if ( empty( $updated ) ) {
			return new WP_Error(
				'rest_no_preferences_provided',
				__( 'No valid notification preferences were provided.', 'wordpress-groups' ),
				[ 'status' => 400 ]
			);
		}

		// Return the full current state.
		$prefs = [];
		foreach ( User_Preferences::TYPES as $type ) {
			$prefs[ $type ] = User_Preferences::is_opted_in( $user_id, $type );
		}

		return new WP_REST_Response( $prefs, 200 );
	}

	/**
	 * Build argument definitions for the update endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_update_args(): array {
		$args = [];

		foreach ( User_Preferences::TYPES as $type ) {
			$args[ $type ] = [
				'description' => sprintf(
					/* translators: %s: notification type */
					__( 'Enable or disable %s notifications.', 'wordpress-groups' ),
					str_replace( '_', ' ', $type )
				),
				'type'     => 'boolean',
				'required' => false,
			];
		}

		return $args;
	}

	/**
	 * Get the preferences schema for responses.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$properties = [];

		foreach ( User_Preferences::TYPES as $type ) {
			$properties[ $type ] = [
				'description' => sprintf(
					/* translators: %s: notification type */
					__( 'Whether %s notifications are enabled.', 'wordpress-groups' ),
					str_replace( '_', ' ', $type )
				),
				'type'    => 'boolean',
				'context' => [ 'view', 'edit' ],
			];
		}

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'notification-preferences',
			'type'       => 'object',
			'properties' => $properties,
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
