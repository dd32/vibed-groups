<?php
/**
 * PHPUnit bootstrap for the WordPress Groups plugin.
 *
 * @package Groups\Tests
 */

// Force multisite mode for the test suite.
if ( ! getenv( 'WP_MULTISITE' ) ) {
	putenv( 'WP_MULTISITE=1' );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/wordpress-groups.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

// Create custom tables for tests.
if ( class_exists( 'Groups\Database\Schema' ) ) {
	\Groups\Database\Schema::create_tables();
}
