<?php
/**
 * REST API health-check controller.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use Groups\Database\Schema;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Returns system health information for administrators.
 */
class Health_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'groups/v1';

	/**
	 * Base path for health routes.
	 *
	 * @var string
	 */
	protected $rest_base = 'health';

	/**
	 * Register the health-check route.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Only administrators may access the health endpoint.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view health status.', 'wordpress-groups' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Return the health-check payload.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ): WP_REST_Response {
		$data = array(
			'plugin_version'    => GROUPS_PLUGIN_VERSION,
			'db_schema_version' => Schema::get_schema_version(),
			'cron_jobs'         => $this->get_cron_status(),
			'tables'            => $this->get_table_row_counts(),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Retrieve the next-scheduled timestamps for the plugin's cron hooks.
	 *
	 * @return array<string, array<string, string|false>>
	 */
	private function get_cron_status(): array {
		$hooks = array(
			'groups_daily_aggregation'         => 'Analytics aggregation',
			'groups_dormancy_check'            => 'Dormancy detection',
			'groups_generate_recurring_events' => 'Recurring event generation',
		);

		$status = array();

		foreach ( $hooks as $hook => $label ) {
			$next = wp_next_scheduled( $hook );

			$status[ $hook ] = array(
				'label'          => $label,
				'next_scheduled' => $next ? gmdate( 'Y-m-d\TH:i:s\Z', $next ) : false,
			);
		}

		return $status;
	}

	/**
	 * Get row counts for plugin custom tables.
	 *
	 * @return array<string, int|null>
	 */
	private function get_table_row_counts(): array {
		global $wpdb;

		$base_prefix = $wpdb->base_prefix;
		$tables      = array(
			'groups_analytics_daily' => "{$base_prefix}groups_analytics_daily",
			'groups_activity_log'    => "{$base_prefix}groups_activity_log",
		);

		$counts = array();

		foreach ( $tables as $key => $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
			);

			$counts[ $key ] = null !== $result ? (int) $result : null;
		}

		return $counts;
	}
}
