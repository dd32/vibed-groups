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
	// Google Fonts: Plus Jakarta Sans (headings) + Inter (body) + JetBrains Mono (mono).
	if ( apply_filters( 'groups_load_google_fonts', true ) ) {
		wp_enqueue_style(
			'groups-site-google-fonts',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap',
			[],
			null
		);
	}

	wp_enqueue_style(
		'groups-site-custom',
		get_theme_file_uri( 'assets/css/custom.css' ),
		[ 'groups-site-google-fonts' ],
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
