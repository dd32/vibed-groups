<?php
/**
 * Central plugin orchestrator.
 *
 * @package Groups
 */

namespace Groups;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * Uses the singleton pattern so that subsystems can reference the same
 * instance via `Plugin::get_instance()`.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {}

	/**
	 * Initialise the plugin.
	 *
	 * Called on `plugins_loaded`. Registers hooks for all subsystems.
	 */
	public function init(): void {
		$this->register_post_types();
		$this->register_taxonomies();
		$this->register_rest_routes();
		$this->register_blocks();
		$this->register_admin_pages();
	}

	/**
	 * Register custom post types.
	 *
	 * Stub — implemented in issue #2.
	 */
	private function register_post_types(): void {
		// TODO: Register event and venue CPTs (issue #2).
	}

	/**
	 * Register custom taxonomies.
	 *
	 * Stub — implemented in a later issue.
	 */
	private function register_taxonomies(): void {
		// TODO: Register taxonomies.
	}

	/**
	 * Register REST API routes.
	 *
	 * Stub — implemented in issue #5.
	 */
	private function register_rest_routes(): void {
		// TODO: Register REST controllers (issue #5).
	}

	/**
	 * Register blocks.
	 *
	 * Delegates to the Block_Registrar which auto-discovers block
	 * directories inside the plugin's `blocks/` folder.
	 */
	private function register_blocks(): void {
		$registrar = new Blocks\Block_Registrar();
		$registrar->register();
	}

	/**
	 * Register admin pages and meta boxes.
	 *
	 * Stub — implemented in a later issue.
	 */
	private function register_admin_pages(): void {
		// TODO: Register admin pages.
	}
}
