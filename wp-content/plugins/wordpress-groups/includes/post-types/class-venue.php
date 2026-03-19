<?php
/**
 * Venue custom post type registration.
 *
 * @package Groups\Post_Types
 */

namespace Groups\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `venue` CPT.
 */
class Venue {

	/**
	 * Post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'venue';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register the venue post type.
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => __( 'Venues', 'wordpress-groups' ),
			'singular_name'        => __( 'Venue', 'wordpress-groups' ),
			'add_new'              => __( 'Add New', 'wordpress-groups' ),
			'add_new_item'         => __( 'Add New Venue', 'wordpress-groups' ),
			'edit_item'            => __( 'Edit Venue', 'wordpress-groups' ),
			'new_item'             => __( 'New Venue', 'wordpress-groups' ),
			'view_item'            => __( 'View Venue', 'wordpress-groups' ),
			'view_items'           => __( 'View Venues', 'wordpress-groups' ),
			'search_items'         => __( 'Search Venues', 'wordpress-groups' ),
			'not_found'            => __( 'No venues found.', 'wordpress-groups' ),
			'not_found_in_trash'   => __( 'No venues found in Trash.', 'wordpress-groups' ),
			'all_items'            => __( 'All Venues', 'wordpress-groups' ),
			'archives'             => __( 'Venue Archives', 'wordpress-groups' ),
			'attributes'           => __( 'Venue Attributes', 'wordpress-groups' ),
			'insert_into_item'     => __( 'Insert into venue', 'wordpress-groups' ),
			'uploaded_to_this_item' => __( 'Uploaded to this venue', 'wordpress-groups' ),
			'menu_name'            => __( 'Venues', 'wordpress-groups' ),
		];

		$args = [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'rest_base'           => 'venues',
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-location',
			'supports'            => [ 'title', 'editor', 'thumbnail' ],
			'has_archive'         => true,
			'rewrite'             => [ 'slug' => 'venue' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
