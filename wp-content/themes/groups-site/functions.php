<?php
/**
 * Groups Site theme functions.
 *
 * @package Groups_Site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue theme styles.
 */
function groups_site_enqueue_styles() {
	wp_enqueue_style(
		'groups-site-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'groups_site_enqueue_styles' );

/**
 * Add theme support.
 */
function groups_site_setup() {
	add_theme_support( 'wp-block-styles' );
}
add_action( 'after_setup_theme', 'groups_site_setup' );
