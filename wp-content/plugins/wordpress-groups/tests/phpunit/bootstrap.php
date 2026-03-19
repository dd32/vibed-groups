<?php
/**
 * PHPUnit bootstrap for the WordPress Groups plugin.
 *
 * @package Groups\Tests
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit( 1 );
}

// Disable multisite — wp-env bug: https://github.com/WordPress/gutenberg/issues/69818
if ( ! defined( 'WP_TESTS_MULTISITE' ) ) {
	define( 'WP_TESTS_MULTISITE', false );
}

// PHPUnit Polyfills path.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	$polyfills = dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills';
	if ( file_exists( $polyfills . '/phpunitpolyfills-autoload.php' ) ) {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills );
	}
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
