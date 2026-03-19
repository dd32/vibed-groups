<?php
/**
 * Shim for \WordPressdotorg\Autoload\register_class_path().
 *
 * This file is only loaded when the wporg-mu-plugins autoloader is not
 * available (e.g. local development without the mu-plugin). It delegates to
 * the minimal fallback defined in the bootstrap file.
 *
 * @package Groups
 */

namespace WordPressdotorg\Autoload;

defined( 'ABSPATH' ) || exit;

/**
 * Register an autoload class path using the WordPress.org naming convention.
 *
 * @param string $prefix Root namespace prefix.
 * @param string $dir    Absolute directory path.
 */
function register_class_path( string $prefix, string $dir ): void {
	\_groups_register_class_path_fallback( $prefix, $dir );
}
