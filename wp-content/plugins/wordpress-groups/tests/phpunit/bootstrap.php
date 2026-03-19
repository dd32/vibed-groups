<?php
/**
 * PHPUnit bootstrap for the WordPress Groups plugin.
 *
 * Loads the WordPress test suite and activates the plugin.
 *
 * @package Groups\Tests
 */

// Try wp-env location first, then WP_TESTS_DIR env, then /tmp fallback.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// wp-env places the test suite here.
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Fallback for non-wp-env setups.
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
