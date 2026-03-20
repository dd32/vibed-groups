<?php
/**
 * Recurrence Panel — registers editor sidebar panel for event recurrence.
 *
 * Enqueues the React-based PluginDocumentSettingPanel that allows
 * organizers to set recurrence rules on event posts. Also exposes
 * a lightweight REST endpoint for previewing occurrences.
 *
 * @package Groups\Blocks
 */

namespace Groups\Blocks;

use Groups\Models\Recurrence;
use Groups\Post_Types\Event;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor sidebar panel and supporting REST route
 * for event recurrence management.
 */
class Recurrence_Panel {

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'init', [ $this, 'register_post_meta' ] );
	}

	/**
	 * Register the _event_recurrence_rule meta field for the REST API.
	 *
	 * This allows the Gutenberg editor to read and write the meta value
	 * via the standard post meta mechanism.
	 */
	public function register_post_meta(): void {
		register_post_meta(
			Event::POST_TYPE,
			Recurrence::META_KEY,
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	/**
	 * Enqueue the sidebar panel script when editing an event.
	 */
	public function enqueue_editor_assets(): void {
		$screen = get_current_screen();

		if ( ! $screen || Event::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$asset_path = GROUPS_PLUGIN_DIR . '/assets/js/recurrence-panel.js';

		wp_enqueue_script(
			'groups-recurrence-panel',
			GROUPS_PLUGIN_URL . 'assets/js/recurrence-panel.js',
			[
				'wp-plugins',
				'wp-edit-post',
				'wp-components',
				'wp-data',
				'wp-element',
				'wp-api-fetch',
				'wp-i18n',
			],
			file_exists( $asset_path ) ? filemtime( $asset_path ) : GROUPS_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'groups-recurrence-panel',
			'groupsRecurrence',
			[
				'restUrl'  => rest_url( 'groups/v1/recurrence-preview' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'metaKey'  => Recurrence::META_KEY,
				'postType' => Event::POST_TYPE,
			]
		);
	}

	/**
	 * Register the REST route for previewing recurrence occurrences.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'groups/v1',
			'/recurrence-preview',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'preview_occurrences' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'frequency'  => [
						'required'          => true,
						'type'              => 'string',
						'enum'              => array_merge( [ 'none' ], Recurrence::VALID_FREQUENCIES ),
						'sanitize_callback' => 'sanitize_text_field',
					],
					'start_date' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * REST callback: return the next 3 occurrences for a given rule.
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function preview_occurrences( \WP_REST_Request $request ): \WP_REST_Response {
		$frequency  = $request->get_param( 'frequency' );
		$start_date = $request->get_param( 'start_date' );

		if ( 'none' === $frequency ) {
			return new \WP_REST_Response( [ 'occurrences' => [] ], 200 );
		}

		// Parse start_date — accept both Y-m-d and full ISO datetime.
		$date = \DateTime::createFromFormat( 'Y-m-d', substr( $start_date, 0, 10 ) );

		if ( ! $date ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Invalid start date.', 'wordpress-groups' ) ],
				400
			);
		}

		// Build a minimal rule for the preview.
		$rule = [ 'frequency' => $frequency ];

		// For weekly/biweekly, use the day of week from the start date.
		if ( in_array( $frequency, [ 'weekly', 'biweekly', 'monthly-day' ], true ) ) {
			$rule['day_of_week'] = (int) $date->format( 'w' );
		}

		if ( 'monthly-date' === $frequency ) {
			$rule['day_of_month'] = (int) $date->format( 'j' );
		}

		// Skip the first occurrence (the start date itself) by generating 4
		// and dropping the first, so the preview shows the *next* 3 dates.
		$occurrences = Recurrence::calculate_occurrences( $rule, $date->format( 'Y-m-d' ), 4 );
		array_shift( $occurrences );

		$formatted = array_map(
			static function ( \DateTime $dt ): string {
				return $dt->format( 'Y-m-d' );
			},
			$occurrences
		);

		return new \WP_REST_Response( [ 'occurrences' => array_values( $formatted ) ], 200 );
	}
}
