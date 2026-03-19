<?php
/**
 * Loader for WordPress.org mu-plugins.
 *
 * Loads the autoloader and register_class_path() function from wporg-mu-plugins.
 */

if ( ! class_exists( '\WordPressdotorg\Autoload\Autoloader', false ) ) {
	require_once WPMU_PLUGIN_DIR . '/wporg-mu-plugins/mu-plugins/autoloader/class-autoloader.php';
}
