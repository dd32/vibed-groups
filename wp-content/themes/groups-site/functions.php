<?php
/**
 * Groups Site theme functions.
 *
 * @package Groups_Site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set up theme support.
 */
function groups_site_setup() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'editor-styles' );
}
add_action( 'after_setup_theme', 'groups_site_setup' );

/**
 * Enqueue fonts and theme stylesheets.
 */
function groups_site_enqueue_assets() {
	wp_enqueue_style(
		'groups-site-google-fonts',
		'https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@300;400;500;600;700&display=swap',
		[],
		null
	);

	wp_enqueue_style(
		'groups-site-responsive',
		get_theme_file_uri( 'assets/css/responsive.css' ),
		[],
		filemtime( get_theme_file_path( 'assets/css/responsive.css' ) )
	);
}
add_action( 'wp_enqueue_scripts', 'groups_site_enqueue_assets' );
add_action( 'enqueue_block_editor_assets', 'groups_site_enqueue_assets' );

/**
 * Register block pattern category for the theme.
 */
function groups_site_register_pattern_category() {
	register_block_pattern_category(
		'groups-site',
		[
			'label' => __( 'Groups Site', 'groups-site' ),
		]
	);
}
add_action( 'init', 'groups_site_register_pattern_category' );
