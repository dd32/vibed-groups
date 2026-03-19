<?php
/**
 * Plugin Name: WordPress Groups
 * Plugin URI:  https://events.wordpress.org/
 * Description: Community group management for the WordPress.org Events network.
 * Version:     0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.3
 * Author:      WordPress.org Meta Team
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordpress-groups
 * Network:     true
 */

defined( 'ABSPATH' ) || exit;

define( 'GROUPS_VERSION', '0.1.0' );
define( 'GROUPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GROUPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Load the WordPress.org autoloader.
 *
 * In production, this is provided by wporg-mu-plugins. For environments where
 * the mu-plugin has not been loaded yet, define a minimal fallback so that the
 * plugin can still register its class path.
 */
if ( ! function_exists( '\WordPressdotorg\Autoload\register_class_path' ) ) {
	/**
	 * Minimal fallback autoloader compatible with the WordPress.org convention.
	 *
	 * Maps a root namespace to a directory using the `class-{name}.php` file
	 * naming pattern.
	 *
	 * @param string $prefix Root namespace prefix (no leading backslash).
	 * @param string $dir    Absolute path to the directory containing classes.
	 */
	function _groups_register_class_path_fallback( string $prefix, string $dir ): void {
		$prefix = rtrim( $prefix, '\\' ) . '\\';
		$dir    = rtrim( $dir, '/' ) . '/';

		spl_autoload_register(
			function ( string $class ) use ( $prefix, $dir ): void {
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}

				$relative = substr( $class, strlen( $prefix ) );
				$parts    = explode( '\\', $relative );
				$name     = array_pop( $parts );

				$path = $dir;
				if ( $parts ) {
					$path .= strtolower( implode( '/', str_replace( '_', '-', $parts ) ) ) . '/';
				}
				$path .= 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

				if ( file_exists( $path ) ) {
					require_once $path;
				}
			}
		);
	}

	// Shim the namespaced function so other code can call it transparently.
	require_once __DIR__ . '/includes/shim-autoloader.php';
}

\WordPressdotorg\Autoload\register_class_path( 'Groups', __DIR__ . '/includes' );

/**
 * Initialise the plugin on `plugins_loaded`.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\Groups\Plugin::get_instance()->init();
	}
);
