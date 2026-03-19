<?php
/**
 * Loader for WordCamp.org dependencies.
 *
 * Maps in only the pieces we need from the WordPress/wordcamp.org repository.
 * The full repo is available at wp-content/wordcamp.org/ — this loader
 * selectively requires the parts relevant to the Groups platform.
 */

defined( 'ABSPATH' ) || exit;

$wordcamp_path = WP_CONTENT_DIR . '/wordcamp.org/public_html/wp-content';

// wcpt plugin — registers wp_meetup CPT and related functionality.
if ( file_exists( $wordcamp_path . '/plugins/wcpt/wcpt-meetup/meetup-loader.php' ) ) {
	require_once $wordcamp_path . '/plugins/wcpt/wcpt-meetup/meetup-loader.php';
}

// wporg-events-2023 theme is available at:
// wp-content/wordcamp.org/public_html/wp-content/themes/wporg-events-2023/
// Referenced for design tokens and patterns, not loaded directly.
