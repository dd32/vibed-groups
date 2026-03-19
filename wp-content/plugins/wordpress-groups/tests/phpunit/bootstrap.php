<?php
/**
 * PHPUnit bootstrap for the WordPress Groups plugin.
 *
 * Loads the WordPress test suite and activates the plugin.
 *
 * @package Groups\Tests
 */

// Assume the WP test suite is available via the WP_TESTS_DIR env variable
// or the default wp-env location.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php — is WP_TESTS_DIR set?\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for testing.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/wordpress-groups.php';
	}
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
