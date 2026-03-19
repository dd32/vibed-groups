<?php
/**
 * Venue custom post type registration.
 *
 * @package Groups\Post_Types
 */

namespace Groups\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `venue` CPT and its geo meta fields.
 */
class Venue {

	/**
	 * Post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'venue';

	/**
	 * Meta keys for venue geo and detail fields.
	 *
	 * @var string[]
	 */
	const META_KEYS = [
		'_venue_address',
		'_venue_city',
		'_venue_state',
		'_venue_country',
		'_venue_zip',
		'_venue_latitude',
		'_venue_longitude',
		'_venue_capacity',
		'_venue_accessibility_notes',
		'_venue_website',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_meta_fields' ] );
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

	/**
	 * Register meta fields for REST API exposure.
	 */
	public function register_meta_fields(): void {
		$string_fields = [
			'_venue_address'             => __( 'Street address.', 'wordpress-groups' ),
			'_venue_city'                => __( 'City.', 'wordpress-groups' ),
			'_venue_state'               => __( 'State or province.', 'wordpress-groups' ),
			'_venue_country'             => __( 'Country.', 'wordpress-groups' ),
			'_venue_zip'                 => __( 'Postal / ZIP code.', 'wordpress-groups' ),
			'_venue_accessibility_notes' => __( 'Accessibility notes for the venue.', 'wordpress-groups' ),
			'_venue_website'             => __( 'Venue website URL.', 'wordpress-groups' ),
		];

		foreach ( $string_fields as $key => $description ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				[
					'type'              => 'string',
					'description'       => $description,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => '_venue_website' === $key
						? [ self::class, 'sanitize_url' ]
						: 'sanitize_text_field',
					'auth_callback'     => [ self::class, 'auth_callback' ],
				]
			);
		}

		// Float fields: latitude and longitude.
		register_post_meta(
			self::POST_TYPE,
			'_venue_latitude',
			[
				'type'              => 'number',
				'description'       => __( 'Latitude (-90 to 90).', 'wordpress-groups' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_latitude' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'_venue_longitude',
			[
				'type'              => 'number',
				'description'       => __( 'Longitude (-180 to 180).', 'wordpress-groups' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_longitude' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			]
		);

		// Integer field: capacity.
		register_post_meta(
			self::POST_TYPE,
			'_venue_capacity',
			[
				'type'              => 'integer',
				'description'       => __( 'Maximum capacity.', 'wordpress-groups' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			]
		);
	}

	/**
	 * Auth callback for meta fields — only organizers+ can edit.
	 *
	 * @param bool   $allowed Whether the user can edit the meta.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function auth_callback( bool $allowed, string $meta_key, int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize a URL value, restricting to http/https.
	 *
	 * @param mixed $value Raw value.
	 * @return string Sanitized URL.
	 */
	public static function sanitize_url( $value ): string {
		return esc_url_raw( (string) $value, [ 'http', 'https' ] );
	}

	/**
	 * Sanitize latitude, clamping to -90..90.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_latitude( $value ): float {
		$value = (float) $value;
		return max( -90.0, min( 90.0, $value ) );
	}

	/**
	 * Sanitize longitude, clamping to -180..180.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_longitude( $value ): float {
		$value = (float) $value;
		return max( -180.0, min( 180.0, $value ) );
	}
}
