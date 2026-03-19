<?php
/**
 * Block auto-registration.
 *
 * @package Groups\Blocks
 */

namespace Groups\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers and registers all blocks found in the plugin's `blocks/` directory.
 *
 * Each subdirectory of `blocks/` that contains a `block.json` file is
 * registered via `register_block_type()` on the `init` action.
 */
class Block_Registrar {

	/**
	 * Hook into WordPress to register blocks on init.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Scan the blocks directory and register each block.
	 */
	public function register_blocks(): void {
		$blocks_dir = GROUPS_PLUGIN_DIR . 'blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$entries = scandir( $blocks_dir );

		if ( ! is_array( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$block_path = $blocks_dir . '/' . $entry;

			if ( is_dir( $block_path ) && file_exists( $block_path . '/block.json' ) ) {
				register_block_type( $block_path );
			}
		}
	}
}
