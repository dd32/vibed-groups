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
	// Local web fonts: Plus Jakarta Sans (headings) + Inter (body).
	wp_enqueue_style(
		'groups-site-fonts',
		get_theme_file_uri( 'assets/css/fonts.css' ),
		[],
		filemtime( get_theme_file_path( 'assets/css/fonts.css' ) )
	);

	wp_enqueue_style(
		'groups-site-custom',
		get_theme_file_uri( 'assets/css/custom.css' ),
		[ 'groups-site-fonts' ],
		filemtime( get_theme_file_path( 'assets/css/custom.css' ) )
	);

	wp_enqueue_style(
		'groups-site-responsive',
		get_theme_file_uri( 'assets/css/responsive.css' ),
		[ 'groups-site-custom' ],
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
