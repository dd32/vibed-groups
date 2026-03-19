<?php
/**
 * Block Registrar — auto-registers all blocks in the blocks/ directory.
 *
 * @package Groups\Blocks
 */

namespace Groups\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Scans the plugin's build directory for block.json files and registers each
 * block with WordPress.
 */
class Block_Registrar {

	/**
	 * Hook into WordPress.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	/**
	 * Register all blocks that have a block.json in the build directory.
	 */
	public function register_blocks(): void {
		$build_dir = GROUPS_PLUGIN_DIR . '/build';

		if ( ! is_dir( $build_dir ) ) {
			return;
		}

		$block_dirs = glob( $build_dir . '/*/block.json' );

		if ( empty( $block_dirs ) ) {
			return;
		}

		foreach ( $block_dirs as $block_json ) {
			$block_dir = dirname( $block_json );
			register_block_type( $block_dir );
		}
	}
}
