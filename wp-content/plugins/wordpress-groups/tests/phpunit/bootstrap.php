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

// Load PHPUnit Polyfills if available via Composer.
$_polyfills_path = dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if ( file_exists( $_polyfills_path ) ) {
	require_once $_polyfills_path;
}
unset( $_polyfills_path );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/*
 * Workaround for wp-env multisite test environments.
 *
 * When wp-tests-config.php defines MULTISITE = true, the WP test installer's
 * populate_network() skips creating the wp_blogs row (it only does so when
 * is_multisite() returns false, i.e. upgrading from single to multi). This
 * leaves ms-settings.php unable to find the current site during bootstrap.
 *
 * We detect this and insert the missing row after the install subprocess
 * runs but before WP loads, by wrapping the bootstrap include.
 */
if ( '1' !== getenv( 'WP_TESTS_SKIP_INSTALL' ) ) {
	// The WP bootstrap's install subprocess will have run by the time
	// require_once wp-settings.php is called. We need to ensure the blog
	// row exists before that. So we run a pre-check: load the config,
	// run the install, then fix up.
	$_config_file = $_tests_dir . '/wp-tests-config.php';
	if ( file_exists( $_config_file ) ) {
		$_cfg = file_get_contents( $_config_file );
		if ( preg_match( '/define\s*\(\s*[\'"]MULTISITE[\'"]\s*,\s*true\s*\)/', $_cfg ) ) {
			// Run the install subprocess ourselves, same as the WP bootstrap does.
			$_php    = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
			$_ms     = 'run_ms_tests';
			$_core   = ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS ) ? 'run_core_tests' : 'no_core_tests';
			$_retval = 0;
			system(
				$_php . ' ' . escapeshellarg( $_tests_dir . '/includes/install.php' )
				. ' ' . escapeshellarg( $_config_file )
				. ' ' . $_ms . ' ' . $_core,
				$_retval
			);

			// Now fix the missing wp_blogs row.
			require_once $_config_file;
			$_prefix = isset( $table_prefix ) ? $table_prefix : 'wp_';
			$_db     = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
			if ( ! $_db->connect_error ) {
				$_result = $_db->query( "SELECT blog_id FROM {$_prefix}blogs LIMIT 1" );
				if ( $_result && 0 === $_result->num_rows ) {
					$_domain = defined( 'WP_TESTS_DOMAIN' ) ? WP_TESTS_DOMAIN : 'localhost';
					$_db->query( sprintf(
						"INSERT INTO {$_prefix}blogs (blog_id, site_id, domain, path, registered, last_updated, public) VALUES (1, 1, '%s', '/', NOW(), NOW(), 1)",
						$_db->real_escape_string( $_domain )
					) );
				}
				if ( $_result ) {
					$_result->free();
				}
				$_db->close();
			}
			unset( $_db, $_result, $_domain, $_prefix, $_php, $_ms, $_core, $_retval );

			// Tell the WP bootstrap to skip its own install since we already ran it.
			putenv( 'WP_TESTS_SKIP_INSTALL=1' );
		}
		unset( $_cfg );
	}
	unset( $_config_file );
}

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/wordpress-groups.php';
	}
);

// Tell the WP test bootstrap exactly where the config file lives so that
// symlinked includes directories do not break __DIR__ resolution.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', $_tests_dir . '/wp-tests-config.php' );
}

require $_tests_dir . '/includes/bootstrap.php';

// Create custom tables for tests.
if ( class_exists( 'Groups\Database\Schema' ) ) {
	\Groups\Database\Schema::create_tables();
}
