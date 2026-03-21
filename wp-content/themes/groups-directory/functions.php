<?php
/**
 * Groups Directory theme functions.
 *
 * @package Groups_Directory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set up theme support.
 */
function groups_directory_setup() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'editor-styles' );
}
add_action( 'after_setup_theme', 'groups_directory_setup' );

/**
 * Enqueue fonts and theme stylesheets.
 */
function groups_directory_enqueue_assets() {
	// Local web fonts: Plus Jakarta Sans (headings) + Inter (body).
	wp_enqueue_style(
		'groups-directory-fonts',
		get_theme_file_uri( 'assets/css/fonts.css' ),
		[],
		filemtime( get_theme_file_path( 'assets/css/fonts.css' ) )
	);

	// Enqueue custom.css if the file exists.
	$custom_css_path = get_theme_file_path( 'assets/css/custom.css' );
	if ( file_exists( $custom_css_path ) ) {
		wp_enqueue_style(
			'groups-directory-custom',
			get_theme_file_uri( 'assets/css/custom.css' ),
			[ 'groups-directory-fonts' ],
			filemtime( $custom_css_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'groups_directory_enqueue_assets' );
add_action( 'enqueue_block_editor_assets', 'groups_directory_enqueue_assets' );

/**
 * Register block pattern category for the theme.
 */
function groups_directory_register_pattern_category() {
	register_block_pattern_category(
		'groups-directory',
		[
			'label' => __( 'Groups Directory', 'groups-directory' ),
		]
	);
}
add_action( 'init', 'groups_directory_register_pattern_category' );
