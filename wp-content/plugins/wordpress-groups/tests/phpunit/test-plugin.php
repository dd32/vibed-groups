<?php
/**
 * Basic plugin loading tests.
 *
 * @package Groups\Tests
 */

/**
 * Verify the plugin loads correctly and essential classes are available.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * The Groups\Plugin class should exist after the plugin is loaded.
	 */
	public function test_plugin_class_exists(): void {
		$this->assertTrue( class_exists( \Groups\Plugin::class ) );
	}

	/**
	 * The singleton should return the same instance.
	 */
	public function test_plugin_singleton(): void {
		$a = \Groups\Plugin::get_instance();
		$b = \Groups\Plugin::get_instance();

		$this->assertSame( $a, $b );
	}

	/**
	 * The GROUPS_VERSION constant should be defined.
	 */
	public function test_version_constant_defined(): void {
		$this->assertTrue( defined( 'GROUPS_VERSION' ) );
	}

	/**
	 * The GROUPS_PLUGIN_DIR constant should be defined and point to the
	 * plugin directory.
	 */
	public function test_plugin_dir_constant(): void {
		$this->assertTrue( defined( 'GROUPS_PLUGIN_DIR' ) );
		$this->assertDirectoryExists( GROUPS_PLUGIN_DIR );
	}

	/**
	 * The Block_Registrar class should be autoloadable.
	 */
	public function test_block_registrar_class_exists(): void {
		$this->assertTrue( class_exists( \Groups\Blocks\Block_Registrar::class ) );
	}
}
