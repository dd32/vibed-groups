<?php
/**
 * Plugin Name: WordPress Groups
 * Description: WordPress community groups platform for events.wordpress.org.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.3
 * Network: true
 * Author: WordPress.org Meta Team
 * Text Domain: wordpress-groups
 */

defined( 'ABSPATH' ) || exit;

define( 'GROUPS_PLUGIN_DIR', __DIR__ );
define( 'GROUPS_PLUGIN_FILE', __FILE__ );
define( 'GROUPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GROUPS_PLUGIN_VERSION', '0.1.0' );

// WordPress.org autoloader.
if ( function_exists( 'WordPressdotorg\Autoload\register_class_path' ) ) {
	\WordPressdotorg\Autoload\register_class_path( 'Groups', __DIR__ . '/includes' );
} else {
	// Fallback autoloader for standalone development.
	spl_autoload_register( function ( $class ) {
		$prefix = 'Groups\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$filename = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';
		$path     = __DIR__ . '/includes/'
			. ( $parts ? strtolower( implode( '/', array_map( fn( $p ) => str_replace( '_', '-', $p ), $parts ) ) ) . '/' : '' )
			. $filename;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	} );
}

/**
 * Create database tables on network activation.
 */
function groups_activate( $network_wide ) {
	if ( $network_wide ) {
		require_once __DIR__ . '/includes/database/class-schema.php';
		\Groups\Database\Schema::create_tables();
	}
}
register_activation_hook( __FILE__, 'groups_activate' );

// Boot the plugin.
require_once __DIR__ . '/includes/class-plugin.php';
Groups\Plugin::get_instance();

// Register WP-CLI commands when available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli.php';
	\WP_CLI::add_command( 'groups', Groups\CLI::class );
}
