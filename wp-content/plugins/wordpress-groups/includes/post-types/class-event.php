<?php
/**
 * Event custom post type registration.
 *
 * @package Groups\Post_Types
 */

namespace Groups\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `event` CPT and its custom statuses.
 */
class Event {

	/**
	 * Post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'event';

	/**
	 * Custom statuses for events.
	 *
	 * @var array<string, string>
	 */
	const STATUSES = [
		'event-draft'     => 'Draft',
		'event-scheduled' => 'Scheduled',
		'event-active'    => 'Active',
		'event-past'      => 'Past',
		'event-cancelled' => 'Cancelled',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_post_statuses' ] );
	}

	/**
	 * Register the event post type.
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => __( 'Events', 'wordpress-groups' ),
			'singular_name'        => __( 'Event', 'wordpress-groups' ),
			'add_new'              => __( 'Add New', 'wordpress-groups' ),
			'add_new_item'         => __( 'Add New Event', 'wordpress-groups' ),
			'edit_item'            => __( 'Edit Event', 'wordpress-groups' ),
			'new_item'             => __( 'New Event', 'wordpress-groups' ),
			'view_item'            => __( 'View Event', 'wordpress-groups' ),
			'view_items'           => __( 'View Events', 'wordpress-groups' ),
			'search_items'         => __( 'Search Events', 'wordpress-groups' ),
			'not_found'            => __( 'No events found.', 'wordpress-groups' ),
			'not_found_in_trash'   => __( 'No events found in Trash.', 'wordpress-groups' ),
			'all_items'            => __( 'All Events', 'wordpress-groups' ),
			'archives'             => __( 'Event Archives', 'wordpress-groups' ),
			'attributes'           => __( 'Event Attributes', 'wordpress-groups' ),
			'insert_into_item'     => __( 'Insert into event', 'wordpress-groups' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'wordpress-groups' ),
			'menu_name'            => __( 'Events', 'wordpress-groups' ),
		];

		$args = [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'rest_base'           => 'events',
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-calendar-alt',
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'has_archive'         => true,
			'rewrite'             => [ 'slug' => 'event' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register custom post statuses for events.
	 */
	public function register_post_statuses(): void {
		foreach ( self::STATUSES as $status => $label ) {
			register_post_status(
				$status,
				[
					'label'                     => __( $label, 'wordpress-groups' ),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: Number of posts. */
					'label_count'               => _n_noop(
						$label . ' <span class="count">(%s)</span>',
						$label . ' <span class="count">(%s)</span>',
						'wordpress-groups'
					),
				]
			);
		}
	}

	/**
	 * Get all custom status slugs.
	 *
	 * @return string[]
	 */
	public static function get_statuses(): array {
		return array_keys( self::STATUSES );
	}
}
